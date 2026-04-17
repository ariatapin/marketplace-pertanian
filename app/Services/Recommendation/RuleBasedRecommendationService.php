<?php

namespace App\Services\Recommendation;

use App\Models\User;
use App\Services\Location\LocationResolver;
use App\Services\RoleAccessService;
use App\Services\Weather\WeatherAlertEngine;
use App\Services\Weather\WeatherService;
use App\Support\BehaviorRecommendationNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RuleBasedRecommendationService
{
    // CATATAN-AUDIT: Cache in-memory per request agar query rule ke DB tidak berulang.
    /**
     * @var array<string, array{rule_key:string,is_active:bool,settings:array<string,mixed>}>
     */
    private array $resolvedRuleCache = [];

    public function __construct(
        private readonly LocationResolver $locationResolver,
        private readonly RoleAccessService $roleAccess,
        private readonly WeatherService $weatherService,
        private readonly WeatherAlertEngine $weatherAlertEngine
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function syncForUser(User $user): array
    {
        // CATATAN-AUDIT: Guard utama - engine rekomendasi aktif hanya jika fitur enabled + tabel notifikasi tersedia.
        if (! $this->isEnabled() || ! Schema::hasTable('notifications')) {
            return [];
        }

        $role = strtolower(trim((string) ($user->role ?? '')));
        $hasSellerAccess = $this->roleAccess->canAccessSeller($user);
        if (! in_array($role, ['consumer', 'mitra', 'seller', 'farmer_seller'], true) && ! $hasSellerAccess) {
            return [];
        }

        if ($role === 'mitra') {
            $recommendations = $this->buildMitraRecommendations($user);
        } elseif ($hasSellerAccess) {
            $recommendations = $this->buildSellerRecommendations($user);
        } elseif ($role === 'consumer') {
            $recommendations = $this->buildConsumerRecommendations($user);
        } else {
            $recommendations = [];
        }

        if (count($recommendations) === 0) {
            return [];
        }

        $dispatched = [];
        foreach ($recommendations as $recommendation) {
            if ($this->dispatchRecommendation($user, $recommendation)) {
                $dispatched[] = $recommendation;
            }
        }

        return $dispatched;
    }

    /**
     * @param  array<int, string>  $roles
     * @return array{processed:int, dispatched:int}
     */
    public function syncForRoles(array $roles = ['consumer', 'mitra', 'seller'], int $chunk = 200): array
    {
        if (! $this->isEnabled() || ! Schema::hasTable('users')) {
            return ['processed' => 0, 'dispatched' => 0];
        }

        $normalizedRoles = collect($roles)
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter(fn ($role) => in_array($role, ['consumer', 'mitra', 'seller'], true))
            ->unique()
            ->values()
            ->all();

        if (count($normalizedRoles) === 0) {
            return ['processed' => 0, 'dispatched' => 0];
        }

        $processed = 0;
        $dispatched = 0;

        $includeConsumer = in_array('consumer', $normalizedRoles, true);
        $includeMitra = in_array('mitra', $normalizedRoles, true);
        $includeSeller = in_array('seller', $normalizedRoles, true);

        User::query()
            ->where(function ($query) use ($includeConsumer, $includeMitra, $includeSeller) {
                if ($includeConsumer) {
                    $query->orWhere('users.role', 'consumer');
                }

                if ($includeMitra) {
                    $query->orWhere('users.role', 'mitra');
                }

                if ($includeSeller) {
                    $query->orWhereIn('users.role', ['seller', 'farmer_seller']);

                    if (Schema::hasTable('consumer_profiles')) {
                        $query->orWhere(function ($sellerModeQuery) {
                            $sellerModeQuery->where('users.role', 'consumer')
                                ->whereExists(function ($profileQuery) {
                                    $profileQuery->selectRaw('1')
                                        ->from('consumer_profiles')
                                        ->whereColumn('consumer_profiles.user_id', 'users.id')
                                        ->where('consumer_profiles.mode', 'farmer_seller')
                                        ->where('consumer_profiles.mode_status', 'approved');
                                });
                        });
                    }
                }
            })
            ->orderBy('id')
            ->chunkById(max(20, min(1000, $chunk)), function ($users) use (&$processed, &$dispatched) {
                foreach ($users as $user) {
                    $processed++;
                    $dispatched += count($this->syncForUser($user));
                }
            });

        return [
            'processed' => $processed,
            'dispatched' => $dispatched,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildConsumerRecommendations(User $user): array
    {
        // CATATAN-AUDIT: Rule consumer berbasis perilaku pembelian + trigger waktu + kondisi cuaca lokal.
        if (! Schema::hasTable('orders') || ! Schema::hasTable('order_items')) {
            return [];
        }

        $rule = $this->resolveRule('consumer');
        if (! ((bool) ($rule['is_active'] ?? true))) {
            return [];
        }

        $settings = (array) ($rule['settings'] ?? []);

        $keywords = $this->normalizeKeywords($settings['product_keywords'] ?? config('recommendation.consumer.product_keywords', ['pupuk']));
        if (count($keywords) === 0) {
            return [];
        }

        $triggerDays = max(1, (int) ($settings['trigger_days_after_purchase'] ?? config('recommendation.consumer.trigger_days_after_purchase', 7)));
        $windowDays = max(1, (int) ($settings['trigger_window_days'] ?? config('recommendation.consumer.trigger_window_days', 7)));
        $lookbackDays = max(7, (int) ($settings['lookback_days'] ?? config('recommendation.consumer.lookback_days', 45)));

        $recentPurchase = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.buyer_id', (int) $user->id)
            ->where('orders.order_source', 'store_online')
            ->where('orders.payment_status', 'paid')
            ->where('orders.created_at', '>=', now()->subDays($lookbackDays))
            ->where(function ($query) use ($keywords) {
                $this->applyKeywordWhere($query, 'order_items.product_name', $keywords);
            })
            ->orderByDesc('orders.created_at')
            ->first([
                'orders.id as order_id',
                'orders.created_at as purchased_at',
                'order_items.product_name',
            ]);

        if (! $recentPurchase) {
            return [];
        }

        $purchasedAt = Carbon::parse((string) $recentPurchase->purchased_at);
        $daysSincePurchase = $purchasedAt->startOfDay()->diffInDays(now()->startOfDay());
        $triggerAnchor = $purchasedAt->copy()->startOfDay()->addDays($triggerDays);
        $triggerExpiry = $triggerAnchor->copy()->addDays($windowDays)->endOfDay();

        if ($daysSincePurchase < $triggerDays || now()->greaterThan($triggerExpiry)) {
            return [];
        }

        $weatherContext = $this->resolveWeatherContext($user);
        $humidityMin = max(1, (int) ($settings['humidity_min'] ?? config('recommendation.consumer.humidity_min', 70)));
        $clearKeywords = $this->normalizeKeywords($settings['clear_keywords'] ?? config('recommendation.consumer.clear_keywords', [
            'clear',
            'cerah',
            'sunny',
        ]));

        if (! $this->isClearWeather($weatherContext['weather_text'] ?? '', $clearKeywords)) {
            return [];
        }

        if ((int) ($weatherContext['humidity'] ?? 0) < $humidityMin) {
            return [];
        }

        $ruleKey = trim((string) ($rule['rule_key'] ?? ''));
        if ($ruleKey === '') {
            $ruleKey = (string) config('recommendation.consumer.rule_key', 'consumer_spraying_followup');
        }
        $locationLabel = trim((string) ($weatherContext['location_label'] ?? 'Wilayah akun'));
        $behaviorConfig = $this->resolveBehaviorConfig($settings, 'consumer', $lookbackDays);
        $behaviorTimeWindow = $this->resolvePreferredTimeWindowFromOrderQuery(
            DB::table('orders')
                ->join('order_items', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.buyer_id', (int) $user->id)
                ->where('orders.order_source', 'store_online')
                ->where('orders.payment_status', 'paid')
                ->where('orders.created_at', '>=', now()->subDays((int) ($behaviorConfig['lookback_days'] ?? $lookbackDays)))
                ->where(function ($query) use ($keywords) {
                    $this->applyKeywordWhere($query, 'order_items.product_name', $keywords);
                }),
            $behaviorConfig
        );
        $behaviorFragment = $this->formatBehaviorInsightFragment($behaviorTimeWindow, 'belanja Anda');
        $productName = trim((string) ($recentPurchase->product_name ?? 'produk pupuk'));
        if ($productName === '') {
            $productName = 'produk pupuk';
        }

        $dispatchKey = sha1(json_encode([
            'role' => 'consumer',
            'rule_key' => $ruleKey,
            'user_id' => (int) $user->id,
            'order_id' => (int) $recentPurchase->order_id,
            'trigger_anchor' => $triggerAnchor->toDateString(),
        ], JSON_UNESCAPED_UNICODE));

        $message = sprintf(
            'Anda membeli %s %d hari lalu. Cuaca cerah dengan kelembapan %d%% di %s. Rekomendasi: lakukan penyemprotan terjadwal hari ini.',
            $productName,
            $daysSincePurchase,
            (int) ($weatherContext['humidity'] ?? 0),
            $locationLabel
        );
        if ($behaviorFragment !== '') {
            $message .= ' ' . $behaviorFragment;
        }

        return [[
            'status' => 'green',
            'title' => 'Rekomendasi Penyemprotan',
            'message' => $message,
            'role_target' => 'consumer',
            'rule_key' => $ruleKey,
            'dispatch_key' => $dispatchKey,
            'target_label' => $locationLabel,
            'triggered_at' => now(),
            'valid_until' => $triggerExpiry,
            'action_url' => route('landing') . '#fitur-cuaca',
            'action_label' => 'Lihat Cuaca & Lokasi',
            'context' => [
                'order_id' => (int) $recentPurchase->order_id,
                'days_since_purchase' => $daysSincePurchase,
                'humidity' => (int) ($weatherContext['humidity'] ?? 0),
                'weather_text' => (string) ($weatherContext['weather_text'] ?? ''),
                'location' => $locationLabel,
                'behavior_time_window' => $behaviorTimeWindow,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMitraRecommendations(User $user): array
    {
        // CATATAN-AUDIT: Rule mitra membaca tren demand buyer per kota + validasi cuaca fase vegetatif.
        if (! Schema::hasTable('orders') || ! Schema::hasTable('order_items') || ! Schema::hasTable('users')) {
            return [];
        }

        $rule = $this->resolveRule('mitra');
        if (! ((bool) ($rule['is_active'] ?? true))) {
            return [];
        }

        $settings = (array) ($rule['settings'] ?? []);

        $cityId = (int) ($user->city_id ?? 0);
        if ($cityId <= 0) {
            return [];
        }

        $keywords = $this->normalizeKeywords($settings['product_keywords'] ?? config('recommendation.mitra.product_keywords', ['pupuk']));
        if (count($keywords) === 0) {
            return [];
        }

        $lookbackDays = max(1, (int) ($settings['lookback_days'] ?? config('recommendation.mitra.lookback_days', 7)));
        $minDistinctBuyers = max(1, (int) ($settings['min_distinct_buyers'] ?? config('recommendation.mitra.min_distinct_buyers', 20)));

        $demand = DB::table('orders')
            ->join('users as buyer', 'buyer.id', '=', 'orders.buyer_id')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('buyer.role', 'consumer')
            ->where('buyer.city_id', $cityId)
            ->where('orders.order_source', 'store_online')
            ->where('orders.payment_status', 'paid')
            ->where('orders.created_at', '>=', now()->subDays($lookbackDays))
            ->where(function ($query) use ($keywords) {
                $this->applyKeywordWhere($query, 'order_items.product_name', $keywords);
            })
            ->selectRaw('COUNT(DISTINCT orders.buyer_id) as total_buyers')
            ->selectRaw('COALESCE(SUM(order_items.qty), 0) as total_qty')
            ->first();

        $totalBuyers = (int) ($demand->total_buyers ?? 0);
        if ($totalBuyers < $minDistinctBuyers) {
            return [];
        }

        $weatherContext = $this->resolveWeatherContext($user);
        if (! $this->supportsVegetativePhase($weatherContext, $settings)) {
            return [];
        }

        $ruleKey = trim((string) ($rule['rule_key'] ?? ''));
        if ($ruleKey === '') {
            $ruleKey = (string) config('recommendation.mitra.rule_key', 'mitra_demand_forecast_pesticide');
        }
        $locationLabel = trim((string) ($weatherContext['location_label'] ?? 'Wilayah mitra'));
        $behaviorConfig = $this->resolveBehaviorConfig($settings, 'mitra', $lookbackDays);
        $behaviorTimeWindow = $this->resolvePreferredTimeWindowFromOrderQuery(
            DB::table('orders')
                ->join('users as buyer', 'buyer.id', '=', 'orders.buyer_id')
                ->join('order_items', 'order_items.order_id', '=', 'orders.id')
                ->where('buyer.role', 'consumer')
                ->where('buyer.city_id', $cityId)
                ->where('orders.order_source', 'store_online')
                ->where('orders.payment_status', 'paid')
                ->where('orders.created_at', '>=', now()->subDays((int) ($behaviorConfig['lookback_days'] ?? $lookbackDays)))
                ->where(function ($query) use ($keywords) {
                    $this->applyKeywordWhere($query, 'order_items.product_name', $keywords);
                }),
            $behaviorConfig
        );
        $behaviorFragment = $this->formatBehaviorInsightFragment($behaviorTimeWindow, 'demand pembelian');
        $dispatchDate = now()->toDateString();
        $dispatchKey = sha1(json_encode([
            'role' => 'mitra',
            'rule_key' => $ruleKey,
            'user_id' => (int) $user->id,
            'city_id' => $cityId,
            'dispatch_date' => $dispatchDate,
        ], JSON_UNESCAPED_UNICODE));

        $targetWindow = trim((string) ($settings['target_window_days'] ?? config('recommendation.mitra.target_window_days', '7-10')));
        $message = sprintf(
            'Dalam %d hari terakhir ada %d consumer di %s membeli pupuk. Cuaca mendukung fase vegetatif. Potensi peningkatan permintaan pestisida dalam %s hari ke depan.',
            $lookbackDays,
            $totalBuyers,
            $locationLabel,
            $targetWindow
        );
        if ($behaviorFragment !== '') {
            $message .= ' ' . $behaviorFragment;
        }

        return [[
            'status' => strtolower((string) ($weatherContext['severity'] ?? 'yellow')),
            'title' => 'Potensi Permintaan Pestisida',
            'message' => $message,
            'role_target' => 'mitra',
            'rule_key' => $ruleKey,
            'dispatch_key' => $dispatchKey,
            'target_label' => $locationLabel,
            'triggered_at' => now(),
            'valid_until' => now()->addDay(),
            'action_url' => route('mitra.dashboard') . '#cuaca-lokasi-mitra',
            'action_label' => 'Buka Cuaca Lokasi Mitra',
            'context' => [
                'city_id' => $cityId,
                'distinct_buyers' => $totalBuyers,
                'total_qty' => (int) ($demand->total_qty ?? 0),
                'temp' => (float) ($weatherContext['temp'] ?? 0),
                'humidity' => (int) ($weatherContext['humidity'] ?? 0),
                'severity' => (string) ($weatherContext['severity'] ?? 'unknown'),
                'location' => $locationLabel,
                'behavior_time_window' => $behaviorTimeWindow,
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSellerRecommendations(User $user): array
    {
        // CATATAN-AUDIT: Rule seller membaca tren order P2P produk petani milik seller + validasi cuaca lokasi seller.
        if (! Schema::hasTable('orders') || ! Schema::hasTable('order_items')) {
            return [];
        }

        $rule = $this->resolveRule('seller');
        if (! ((bool) ($rule['is_active'] ?? true))) {
            return [];
        }

        $settings = (array) ($rule['settings'] ?? []);
        $lookbackDays = max(1, (int) ($settings['lookback_days'] ?? config('recommendation.seller.lookback_days', 7)));
        $minPaidOrders = max(1, (int) ($settings['min_paid_orders'] ?? config('recommendation.seller.min_paid_orders', 5)));
        $minTotalQty = max(1, (int) ($settings['min_total_qty'] ?? config('recommendation.seller.min_total_qty', 10)));

        $sales = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.seller_id', (int) $user->id)
            ->where('orders.order_source', 'farmer_p2p')
            ->where('orders.payment_status', 'paid')
            ->where('orders.created_at', '>=', now()->subDays($lookbackDays))
            ->selectRaw('COUNT(DISTINCT orders.id) as total_paid_orders')
            ->selectRaw('COUNT(DISTINCT orders.buyer_id) as total_buyers')
            ->selectRaw('COALESCE(SUM(order_items.qty), 0) as total_qty')
            ->first();

        $totalPaidOrders = (int) ($sales->total_paid_orders ?? 0);
        $totalQty = (int) ($sales->total_qty ?? 0);
        if ($totalPaidOrders < $minPaidOrders || $totalQty < $minTotalQty) {
            return [];
        }

        $topCommodity = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.seller_id', (int) $user->id)
            ->where('orders.order_source', 'farmer_p2p')
            ->where('orders.payment_status', 'paid')
            ->where('orders.created_at', '>=', now()->subDays($lookbackDays))
            ->groupBy('order_items.product_name')
            ->orderByRaw('COALESCE(SUM(order_items.qty), 0) DESC')
            ->orderBy('order_items.product_name')
            ->value('order_items.product_name');

        $weatherContext = $this->resolveWeatherContext($user);
        if (! $this->supportsSellerDemandWeather($weatherContext, $settings)) {
            return [];
        }

        $ruleKey = trim((string) ($rule['rule_key'] ?? ''));
        if ($ruleKey === '') {
            $ruleKey = (string) config('recommendation.seller.rule_key', 'seller_demand_harvest_ops');
        }

        $locationLabel = trim((string) ($weatherContext['location_label'] ?? 'Wilayah penjual'));
        if ($locationLabel === '') {
            $locationLabel = 'Wilayah penjual';
        }

        $topCommodityLabel = trim((string) ($topCommodity ?? 'komoditas utama'));
        if ($topCommodityLabel === '') {
            $topCommodityLabel = 'komoditas utama';
        }
        $behaviorConfig = $this->resolveBehaviorConfig($settings, 'seller', $lookbackDays);
        $behaviorTimeWindow = $this->resolvePreferredTimeWindowFromOrderQuery(
            DB::table('orders')
                ->where('orders.seller_id', (int) $user->id)
                ->where('orders.order_source', 'farmer_p2p')
                ->where('orders.payment_status', 'paid')
                ->where('orders.created_at', '>=', now()->subDays((int) ($behaviorConfig['lookback_days'] ?? $lookbackDays))),
            $behaviorConfig
        );
        $behaviorFragment = $this->formatBehaviorInsightFragment($behaviorTimeWindow, 'order pembeli');

        $dispatchDate = now()->toDateString();
        $dispatchKey = sha1(json_encode([
            'role' => 'seller',
            'rule_key' => $ruleKey,
            'user_id' => (int) $user->id,
            'dispatch_date' => $dispatchDate,
        ], JSON_UNESCAPED_UNICODE));

        $targetWindow = trim((string) ($settings['target_window_days'] ?? config('recommendation.seller.target_window_days', '3-5')));
        $message = sprintf(
            'Dalam %d hari terakhir produk Anda terjual %d order (total %d unit) di %s. Komoditas dominan: %s. Cuaca mendukung operasional, siapkan panen dan stok untuk %s hari ke depan.',
            $lookbackDays,
            $totalPaidOrders,
            $totalQty,
            $locationLabel,
            $topCommodityLabel,
            $targetWindow
        );
        if ($behaviorFragment !== '') {
            $message .= ' ' . $behaviorFragment;
        }

        return [[
            'status' => strtolower((string) ($weatherContext['severity'] ?? 'green')),
            'title' => 'Potensi Permintaan Produk Petani',
            'message' => $message,
            'role_target' => 'seller',
            'rule_key' => $ruleKey,
            'dispatch_key' => $dispatchKey,
            'target_label' => $locationLabel,
            'triggered_at' => now(),
            'valid_until' => now()->addDay(),
            'action_url' => route('seller.dashboard') . '#seller-recommendation',
            'action_label' => 'Buka Rekomendasi Penjual',
            'context' => [
                'lookback_days' => $lookbackDays,
                'paid_orders' => $totalPaidOrders,
                'distinct_buyers' => (int) ($sales->total_buyers ?? 0),
                'total_qty' => $totalQty,
                'top_commodity' => $topCommodityLabel,
                'temp' => (float) ($weatherContext['temp'] ?? 0),
                'humidity' => (int) ($weatherContext['humidity'] ?? 0),
                'severity' => (string) ($weatherContext['severity'] ?? 'unknown'),
                'location' => $locationLabel,
                'behavior_time_window' => $behaviorTimeWindow,
            ],
        ]];
    }

    /**
     * @param  array<string, mixed>  $recommendation
     */
    private function dispatchRecommendation(User $user, array $recommendation): bool
    {
        // CATATAN-AUDIT: Idempotensi dispatch diproteksi oleh dispatch_key unik di tabel recommendation_dispatches.
        $dispatchKey = trim((string) ($recommendation['dispatch_key'] ?? ''));
        if ($dispatchKey === '') {
            return false;
        }

        if (! Schema::hasTable('recommendation_dispatches')) {
            return $this->dispatchWithoutLogTable($user, $recommendation);
        }

        return DB::transaction(function () use ($user, $recommendation, $dispatchKey) {
            $triggeredAt = $this->castTimestamp($recommendation['triggered_at'] ?? now()) ?? now();
            $expiresAt = isset($recommendation['valid_until'])
                ? $this->castTimestamp($recommendation['valid_until'])
                : null;

            $inserted = DB::table('recommendation_dispatches')->insertOrIgnore([
                'user_id' => (int) $user->id,
                'role' => strtolower(trim((string) ($user->role ?? 'consumer'))),
                'rule_key' => (string) ($recommendation['rule_key'] ?? 'behavior_rule'),
                'dispatch_key' => $dispatchKey,
                'context' => json_encode((array) ($recommendation['context'] ?? []), JSON_UNESCAPED_UNICODE),
                'triggered_at' => $triggeredAt,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ((int) $inserted <= 0) {
                return false;
            }

            $user->notify(new BehaviorRecommendationNotification(
                status: (string) ($recommendation['status'] ?? 'green'),
                title: (string) ($recommendation['title'] ?? 'Rekomendasi Operasional'),
                message: (string) ($recommendation['message'] ?? 'Ada rekomendasi baru untuk akun Anda.'),
                roleTarget: (string) ($recommendation['role_target'] ?? strtolower((string) ($user->role ?? 'consumer'))),
                ruleKey: (string) ($recommendation['rule_key'] ?? 'behavior_rule'),
                dispatchKey: $dispatchKey,
                targetLabel: (string) ($recommendation['target_label'] ?? ''),
                validUntil: isset($recommendation['valid_until'])
                    ? $this->castTimestamp($recommendation['valid_until'])?->toDateTimeString()
                    : null,
                actionUrl: (string) ($recommendation['action_url'] ?? ''),
                actionLabel: (string) ($recommendation['action_label'] ?? 'Lihat Rekomendasi')
            ));

            return true;
        });
    }

    /**
     * @param  array<string, mixed>  $recommendation
     */
    private function dispatchWithoutLogTable(User $user, array $recommendation): bool
    {
        $dispatchKey = trim((string) ($recommendation['dispatch_key'] ?? ''));
        $alreadyExists = DB::table('notifications')
            ->where('type', BehaviorRecommendationNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', (int) $user->id)
            ->where('data', 'like', '%"dispatch_key":"' . $dispatchKey . '"%')
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        $user->notify(new BehaviorRecommendationNotification(
            status: (string) ($recommendation['status'] ?? 'green'),
            title: (string) ($recommendation['title'] ?? 'Rekomendasi Operasional'),
            message: (string) ($recommendation['message'] ?? 'Ada rekomendasi baru untuk akun Anda.'),
            roleTarget: (string) ($recommendation['role_target'] ?? strtolower((string) ($user->role ?? 'consumer'))),
            ruleKey: (string) ($recommendation['rule_key'] ?? 'behavior_rule'),
            dispatchKey: $dispatchKey,
            targetLabel: (string) ($recommendation['target_label'] ?? ''),
            validUntil: isset($recommendation['valid_until'])
                ? $this->castTimestamp($recommendation['valid_until'])?->toDateTimeString()
                : null,
            actionUrl: (string) ($recommendation['action_url'] ?? ''),
            actionLabel: (string) ($recommendation['action_label'] ?? 'Lihat Rekomendasi')
        ));

        return true;
    }

    /**
     * @return array{location_label:string,temp:float,humidity:int,severity:string,weather_text:string}
     */
    private function resolveWeatherContext(User $user): array
    {
        $loc = $this->locationResolver->forUser($user);
        $locationLabel = trim((string) ($loc['label'] ?? 'Lokasi akun'));

        try {
            $current = $this->weatherService->current(
                (string) ($loc['type'] ?? 'custom'),
                (int) ($loc['id'] ?? 0),
                (float) ($loc['lat'] ?? 0),
                (float) ($loc['lng'] ?? 0)
            );
            $forecast = $this->weatherService->forecast(
                (string) ($loc['type'] ?? 'custom'),
                (int) ($loc['id'] ?? 0),
                (float) ($loc['lat'] ?? 0),
                (float) ($loc['lng'] ?? 0)
            );
            $alert = $this->weatherAlertEngine->evaluateForecast(is_array($forecast) ? $forecast : []);
        } catch (\Throwable $e) {
            $current = [];
            $alert = ['severity' => 'unknown'];
        }

        $weatherMain = strtolower(trim((string) data_get($current, 'weather.0.main', '')));
        $weatherDesc = strtolower(trim((string) data_get($current, 'weather.0.description', '')));
        $weatherText = trim($weatherMain . ' ' . $weatherDesc);

        return [
            'location_label' => $locationLabel !== '' ? $locationLabel : 'Lokasi akun',
            'temp' => (float) data_get($current, 'main.temp', 0),
            'humidity' => (int) data_get($current, 'main.humidity', 0),
            'severity' => strtolower(trim((string) ($alert['severity'] ?? 'unknown'))),
            'weather_text' => strtolower($weatherText),
        ];
    }

    /**
     * @param  array<int, string>  $clearKeywords
     */
    private function isClearWeather(string $weatherText, array $clearKeywords): bool
    {
        $haystack = strtolower(trim($weatherText));
        if ($haystack === '') {
            return false;
        }

        foreach ($clearKeywords as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{severity?:mixed,temp?:mixed,humidity?:mixed}  $weatherContext
     * @param  array<string, mixed>  $settings
     */
    private function supportsVegetativePhase(array $weatherContext, array $settings): bool
    {
        $severity = strtolower((string) ($weatherContext['severity'] ?? 'unknown'));
        $allowedSeverities = $this->normalizeKeywords($settings['allowed_weather_severities'] ?? config('recommendation.mitra.allowed_weather_severities', [
            'green',
            'yellow',
        ]));

        if (! in_array($severity, $allowedSeverities, true)) {
            return false;
        }

        $temp = (float) ($weatherContext['temp'] ?? 0);
        $humidity = (int) ($weatherContext['humidity'] ?? 0);

        $tempMin = (float) ($settings['vegetative_temp_min'] ?? config('recommendation.mitra.vegetative_temp_min', 20));
        $tempMax = (float) ($settings['vegetative_temp_max'] ?? config('recommendation.mitra.vegetative_temp_max', 33));
        $humidityMin = (int) ($settings['vegetative_humidity_min'] ?? config('recommendation.mitra.vegetative_humidity_min', 55));
        $humidityMax = (int) ($settings['vegetative_humidity_max'] ?? config('recommendation.mitra.vegetative_humidity_max', 95));

        return $temp >= $tempMin
            && $temp <= $tempMax
            && $humidity >= $humidityMin
            && $humidity <= $humidityMax;
    }

    /**
     * @param  array{severity?:mixed,temp?:mixed,humidity?:mixed}  $weatherContext
     * @param  array<string, mixed>  $settings
     */
    private function supportsSellerDemandWeather(array $weatherContext, array $settings): bool
    {
        $severity = strtolower((string) ($weatherContext['severity'] ?? 'unknown'));
        $allowedSeverities = $this->normalizeKeywords($settings['allowed_weather_severities'] ?? config('recommendation.seller.allowed_weather_severities', [
            'green',
            'yellow',
        ]));

        if (! in_array($severity, $allowedSeverities, true)) {
            return false;
        }

        $temp = (float) ($weatherContext['temp'] ?? 0);
        $humidity = (int) ($weatherContext['humidity'] ?? 0);

        $tempMin = (float) ($settings['harvest_temp_min'] ?? config('recommendation.seller.harvest_temp_min', 20));
        $tempMax = (float) ($settings['harvest_temp_max'] ?? config('recommendation.seller.harvest_temp_max', 34));
        $humidityMin = (int) ($settings['harvest_humidity_min'] ?? config('recommendation.seller.harvest_humidity_min', 50));
        $humidityMax = (int) ($settings['harvest_humidity_max'] ?? config('recommendation.seller.harvest_humidity_max', 95));

        return $temp >= $tempMin
            && $temp <= $tempMax
            && $humidity >= $humidityMin
            && $humidity <= $humidityMax;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{lookback_days:int,history_limit:int,min_samples:int,window_hours:int}
     */
    private function resolveBehaviorConfig(array $settings, string $role, int $fallbackLookbackDays): array
    {
        $role = strtolower(trim($role));
        $configPath = match ($role) {
            'consumer' => 'recommendation.consumer',
            'mitra' => 'recommendation.mitra',
            default => 'recommendation.seller',
        };

        $lookbackDefault = max(1, (int) config($configPath . '.behavior_lookback_days', max(14, $fallbackLookbackDays)));
        $historyLimitDefault = max(20, (int) config($configPath . '.behavior_history_limit', 300));
        $minSamplesDefault = max(1, (int) config($configPath . '.behavior_min_samples', 3));
        $windowHoursDefault = max(1, (int) config($configPath . '.behavior_window_hours', 2));

        return [
            'lookback_days' => max(1, (int) ($settings['behavior_lookback_days'] ?? $lookbackDefault)),
            'history_limit' => max(20, min(2000, (int) ($settings['behavior_history_limit'] ?? $historyLimitDefault))),
            'min_samples' => max(1, min(50, (int) ($settings['behavior_min_samples'] ?? $minSamplesDefault))),
            'window_hours' => max(1, min(6, (int) ($settings['behavior_window_hours'] ?? $windowHoursDefault))),
        ];
    }

    /**
     * @param  array{lookback_days:int,history_limit:int,min_samples:int,window_hours:int}  $behaviorConfig
     * @return array{
     *   available:bool,
     *   day_of_week:?int,
     *   day_label:?string,
     *   start_hour:?int,
     *   end_hour:?int,
     *   window_label:string,
     *   sample_size:int,
     *   dominant_count:int,
     *   confidence_pct:float
     * }
     */
    private function resolvePreferredTimeWindowFromOrderQuery($query, array $behaviorConfig): array
    {
        $rows = (clone $query)
            ->select('orders.id', 'orders.created_at')
            ->groupBy('orders.id', 'orders.created_at')
            ->orderByDesc('orders.created_at')
            ->limit((int) ($behaviorConfig['history_limit'] ?? 300))
            ->get();

        return $this->resolvePreferredTimeWindowFromTimestamps(
            $rows->pluck('created_at')->all(),
            (int) ($behaviorConfig['min_samples'] ?? 3),
            (int) ($behaviorConfig['window_hours'] ?? 2)
        );
    }

    /**
     * @param  array<int, mixed>  $timestamps
     * @return array{
     *   available:bool,
     *   day_of_week:?int,
     *   day_label:?string,
     *   start_hour:?int,
     *   end_hour:?int,
     *   window_label:string,
     *   sample_size:int,
     *   dominant_count:int,
     *   confidence_pct:float
     * }
     */
    private function resolvePreferredTimeWindowFromTimestamps(array $timestamps, int $minSamples, int $windowHours): array
    {
        $normalized = collect($timestamps)
            ->map(function ($value) {
                $parsed = $this->castTimestamp($value);
                if (! $parsed) {
                    return null;
                }

                return $parsed->copy()->timezone((string) config('app.timezone', 'Asia/Jakarta'));
            })
            ->filter()
            ->values();

        $sampleSize = (int) $normalized->count();
        if ($sampleSize < max(1, $minSamples)) {
            return [
                'available' => false,
                'day_of_week' => null,
                'day_label' => null,
                'start_hour' => null,
                'end_hour' => null,
                'window_label' => '',
                'sample_size' => $sampleSize,
                'dominant_count' => 0,
                'confidence_pct' => 0.0,
            ];
        }

        $buckets = [];
        foreach ($normalized as $time) {
            $dayOfWeek = (int) $time->dayOfWeek;
            $hour = (int) $time->hour;
            $key = $dayOfWeek . '-' . $hour;
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'day_of_week' => $dayOfWeek,
                    'hour' => $hour,
                    'count' => 0,
                    'latest_at' => $time->copy(),
                ];
            }

            $buckets[$key]['count']++;
            if ($time->greaterThan($buckets[$key]['latest_at'])) {
                $buckets[$key]['latest_at'] = $time->copy();
            }
        }

        uasort($buckets, function (array $left, array $right): int {
            $leftCount = (int) ($left['count'] ?? 0);
            $rightCount = (int) ($right['count'] ?? 0);
            if ($leftCount !== $rightCount) {
                return $rightCount <=> $leftCount;
            }

            $leftLatest = $left['latest_at'] instanceof Carbon ? $left['latest_at']->timestamp : 0;
            $rightLatest = $right['latest_at'] instanceof Carbon ? $right['latest_at']->timestamp : 0;
            if ($leftLatest !== $rightLatest) {
                return $rightLatest <=> $leftLatest;
            }

            return ((int) ($left['hour'] ?? 0)) <=> ((int) ($right['hour'] ?? 0));
        });

        $dominant = reset($buckets);
        if (! is_array($dominant)) {
            return [
                'available' => false,
                'day_of_week' => null,
                'day_label' => null,
                'start_hour' => null,
                'end_hour' => null,
                'window_label' => '',
                'sample_size' => $sampleSize,
                'dominant_count' => 0,
                'confidence_pct' => 0.0,
            ];
        }

        $startHour = (int) ($dominant['hour'] ?? 0);
        $windowHours = max(1, min(6, $windowHours));
        $endHour = ($startHour + $windowHours) % 24;
        $dayOfWeek = (int) ($dominant['day_of_week'] ?? 0);
        $dayLabel = $this->dayOfWeekLabel($dayOfWeek);
        $dominantCount = (int) ($dominant['count'] ?? 0);
        $confidencePct = round(($dominantCount / max(1, $sampleSize)) * 100, 1);

        return [
            'available' => true,
            'day_of_week' => $dayOfWeek,
            'day_label' => $dayLabel,
            'start_hour' => $startHour,
            'end_hour' => $endHour,
            'window_label' => sprintf('%s %02d:00-%02d:00', $dayLabel, $startHour, $endHour),
            'sample_size' => $sampleSize,
            'dominant_count' => $dominantCount,
            'confidence_pct' => $confidencePct,
        ];
    }

    /**
     * @param  array{
     *   available?:mixed,
     *   window_label?:mixed,
     *   confidence_pct?:mixed,
     *   sample_size?:mixed
     * }  $behaviorTimeWindow
     */
    private function formatBehaviorInsightFragment(array $behaviorTimeWindow, string $subject): string
    {
        if (! ((bool) ($behaviorTimeWindow['available'] ?? false))) {
            return '';
        }

        $windowLabel = trim((string) ($behaviorTimeWindow['window_label'] ?? ''));
        if ($windowLabel === '') {
            return '';
        }

        $confidence = (float) ($behaviorTimeWindow['confidence_pct'] ?? 0);
        $sampleSize = max(0, (int) ($behaviorTimeWindow['sample_size'] ?? 0));

        return sprintf(
            'Pola %s paling sering terjadi pada %s (confidence %.1f%% dari %d transaksi).',
            trim($subject) !== '' ? trim($subject) : 'transaksi',
            $windowLabel,
            $confidence,
            $sampleSize
        );
    }

    private function dayOfWeekLabel(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function applyKeywordWhere($query, string $column, array $keywords): void
    {
        $firstCondition = true;
        foreach ($keywords as $keyword) {
            if ($firstCondition) {
                $query->whereRaw('LOWER(' . $column . ') LIKE ?', ['%' . strtolower($keyword) . '%']);
                $firstCondition = false;
                continue;
            }

            $query->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . strtolower($keyword) . '%']);
        }

        if ($firstCondition) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @param  mixed  $value
     * @return Carbon|null
     */
    private function castTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $keywords
     * @return array<int, string>
     */
    private function normalizeKeywords(mixed $keywords): array
    {
        if (! is_array($keywords)) {
            return [];
        }

        return collect($keywords)
            ->map(fn ($keyword) => strtolower(trim((string) $keyword)))
            ->filter(fn ($keyword) => $keyword !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function isEnabled(): bool
    {
        return (bool) config('recommendation.enabled', true);
    }

    /**
     * @return array{rule_key:string,is_active:bool,settings:array<string,mixed>}
     */
    private function resolveRule(string $role): array
    {
        // CATATAN-AUDIT: Prioritas konfigurasi rule = DB recommendation_rules, fallback ke config/recommendation.php.
        $role = strtolower(trim($role));
        if (isset($this->resolvedRuleCache[$role])) {
            return $this->resolvedRuleCache[$role];
        }

        $default = $this->defaultRuleConfig($role);

        if (! Schema::hasTable('recommendation_rules')) {
            $this->resolvedRuleCache[$role] = $default;
            return $default;
        }

        $row = DB::table('recommendation_rules')
            ->where('role_target', $role)
            ->where('rule_key', $default['rule_key'])
            ->first([
                'rule_key',
                'is_active',
                'settings',
            ]);

        if (! $row) {
            $this->resolvedRuleCache[$role] = $default;
            return $default;
        }

        $rowSettings = $this->normalizeRuleSettings($row->settings ?? null);

        $resolved = [
            'rule_key' => trim((string) ($row->rule_key ?? $default['rule_key'])),
            'is_active' => (bool) ($row->is_active ?? true),
            'settings' => array_merge($default['settings'], $rowSettings),
        ];

        $this->resolvedRuleCache[$role] = $resolved;

        return $resolved;
    }

    /**
     * @return array{rule_key:string,is_active:bool,settings:array<string,mixed>}
     */
    private function defaultRuleConfig(string $role): array
    {
        if ($role === 'consumer') {
            return [
                'rule_key' => (string) config('recommendation.consumer.rule_key', 'consumer_spraying_followup'),
                'is_active' => true,
                'settings' => [
                    'product_keywords' => (array) config('recommendation.consumer.product_keywords', ['pupuk']),
                    'clear_keywords' => (array) config('recommendation.consumer.clear_keywords', ['clear', 'cerah', 'sunny']),
                    'trigger_days_after_purchase' => (int) config('recommendation.consumer.trigger_days_after_purchase', 7),
                    'trigger_window_days' => (int) config('recommendation.consumer.trigger_window_days', 7),
                    'lookback_days' => (int) config('recommendation.consumer.lookback_days', 45),
                    'humidity_min' => (int) config('recommendation.consumer.humidity_min', 70),
                    'behavior_lookback_days' => (int) config('recommendation.consumer.behavior_lookback_days', 60),
                    'behavior_history_limit' => (int) config('recommendation.consumer.behavior_history_limit', 300),
                    'behavior_min_samples' => (int) config('recommendation.consumer.behavior_min_samples', 3),
                    'behavior_window_hours' => (int) config('recommendation.consumer.behavior_window_hours', 2),
                ],
            ];
        }

        if ($role === 'seller') {
            return [
                'rule_key' => (string) config('recommendation.seller.rule_key', 'seller_demand_harvest_ops'),
                'is_active' => true,
                'settings' => [
                    'lookback_days' => (int) config('recommendation.seller.lookback_days', 7),
                    'min_paid_orders' => (int) config('recommendation.seller.min_paid_orders', 5),
                    'min_total_qty' => (int) config('recommendation.seller.min_total_qty', 10),
                    'target_window_days' => (string) config('recommendation.seller.target_window_days', '3-5'),
                    'allowed_weather_severities' => (array) config('recommendation.seller.allowed_weather_severities', ['green', 'yellow']),
                    'harvest_temp_min' => (float) config('recommendation.seller.harvest_temp_min', 20),
                    'harvest_temp_max' => (float) config('recommendation.seller.harvest_temp_max', 34),
                    'harvest_humidity_min' => (int) config('recommendation.seller.harvest_humidity_min', 50),
                    'harvest_humidity_max' => (int) config('recommendation.seller.harvest_humidity_max', 95),
                    'behavior_lookback_days' => (int) config('recommendation.seller.behavior_lookback_days', 30),
                    'behavior_history_limit' => (int) config('recommendation.seller.behavior_history_limit', 300),
                    'behavior_min_samples' => (int) config('recommendation.seller.behavior_min_samples', 3),
                    'behavior_window_hours' => (int) config('recommendation.seller.behavior_window_hours', 2),
                ],
            ];
        }

        return [
            'rule_key' => (string) config('recommendation.mitra.rule_key', 'mitra_demand_forecast_pesticide'),
            'is_active' => true,
            'settings' => [
                'product_keywords' => (array) config('recommendation.mitra.product_keywords', ['pupuk']),
                'allowed_weather_severities' => (array) config('recommendation.mitra.allowed_weather_severities', ['green', 'yellow']),
                'lookback_days' => (int) config('recommendation.mitra.lookback_days', 7),
                'min_distinct_buyers' => (int) config('recommendation.mitra.min_distinct_buyers', 20),
                'target_window_days' => (string) config('recommendation.mitra.target_window_days', '7-10'),
                'vegetative_temp_min' => (float) config('recommendation.mitra.vegetative_temp_min', 20),
                'vegetative_temp_max' => (float) config('recommendation.mitra.vegetative_temp_max', 33),
                'vegetative_humidity_min' => (int) config('recommendation.mitra.vegetative_humidity_min', 55),
                'vegetative_humidity_max' => (int) config('recommendation.mitra.vegetative_humidity_max', 95),
                'behavior_lookback_days' => (int) config('recommendation.mitra.behavior_lookback_days', 30),
                'behavior_history_limit' => (int) config('recommendation.mitra.behavior_history_limit', 300),
                'behavior_min_samples' => (int) config('recommendation.mitra.behavior_min_samples', 3),
                'behavior_window_hours' => (int) config('recommendation.mitra.behavior_window_hours', 2),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeRuleSettings(mixed $settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if (is_object($settings)) {
            return (array) $settings;
        }

        if (is_string($settings) && trim($settings) !== '') {
            $decoded = json_decode($settings, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
