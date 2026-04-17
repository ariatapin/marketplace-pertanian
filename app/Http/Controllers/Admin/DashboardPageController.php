<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WalletService;
use App\Services\Weather\WeatherAlertEngine;
use App\Support\AdminDashboardViewModelFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardPageController extends Controller
{
    public function __construct(
        protected AdminDashboardViewModelFactory $dashboardViewModelFactory,
        protected WalletService $walletService,
        protected WeatherAlertEngine $weatherAlertEngine
    ) {}

    public function __invoke()
    {
        $adminUser = auth()->user();
        if (
            app()->environment('local')
            && Str::endsWith((string) ($adminUser?->email ?? ''), '@demo.test')
        ) {
            app(\App\Support\DemoUserProvisioner::class)->ensureUsers();
        }

        $demoAdminBalance = $this->resolveAdminWalletBalance((int) ($adminUser?->id ?? 0));

        $metrics = [
            'total_users' => 0,
            'total_mitra' => 0,
            'pending_mitra_applications' => 0,
            'pending_affiliate_applications' => 0,
            'pending_farmer_seller_applications' => 0,
            'active_affiliates' => 0,
            'active_sellers' => 0,
            'total_pengajuan' => 0,
            'total_store_products' => 0,
            'active_orders' => 0,
            'active_mitra_orders' => 0,
        ];
        $pendingModeRequests = collect();
        $sellerRoleUserIds = collect();

        if (Schema::hasTable('users')) {
            $metrics['total_users'] = DB::table('users')->count();
            $metrics['total_mitra'] = User::query()->whereNormalizedRole('mitra')->count();
            $sellerRoleUserIds = User::query()
                ->whereInNormalizedRoles(['seller', 'farmer_seller'])
                ->pluck('id');
        }

        if (Schema::hasTable('mitra_applications')) {
            $metrics['pending_mitra_applications'] = DB::table('mitra_applications')
                ->where('status', 'pending')
                ->count();
        }

        if (Schema::hasTable('consumer_profiles')) {
            $pendingModeBase = DB::table('consumer_profiles')
                ->where('mode_status', 'pending');

            $metrics['pending_affiliate_applications'] = (clone $pendingModeBase)
                ->where('requested_mode', 'affiliate')
                ->count();

            $metrics['pending_farmer_seller_applications'] = (clone $pendingModeBase)
                ->where('requested_mode', 'farmer_seller')
                ->count();

            $approvedAffiliateUserIds = DB::table('consumer_profiles')
                ->where('mode_status', 'approved')
                ->where('mode', 'affiliate')
                ->pluck('user_id');
            $approvedSellerUserIds = DB::table('consumer_profiles')
                ->where('mode_status', 'approved')
                ->where('mode', 'farmer_seller')
                ->pluck('user_id');

            $metrics['active_affiliates'] = $approvedAffiliateUserIds->unique()->count();
            $metrics['active_sellers'] = $sellerRoleUserIds
                ->merge($approvedSellerUserIds)
                ->unique()
                ->count();

            if (Schema::hasTable('users')) {
                $pendingModeRequests = DB::table('consumer_profiles')
                    ->join('users', 'users.id', '=', 'consumer_profiles.user_id')
                    ->where('consumer_profiles.mode_status', 'pending')
                    ->whereIn('consumer_profiles.requested_mode', ['affiliate', 'farmer_seller'])
                    ->select(
                        'users.id as user_id',
                        'users.name',
                        'users.email',
                        'consumer_profiles.requested_mode',
                        'consumer_profiles.updated_at'
                    )
                    ->orderByDesc('consumer_profiles.updated_at')
                    ->limit(10)
                    ->get();
            }
        } else {
            $metrics['active_affiliates'] = 0;
            $metrics['active_sellers'] = $sellerRoleUserIds->unique()->count();
        }

        if (Schema::hasTable('store_products')) {
            $metrics['total_store_products'] = DB::table('store_products')->count();
        }

        if (Schema::hasTable('orders')) {
            $metrics['active_orders'] = DB::table('orders')
                ->whereIn('order_status', ['pending_payment', 'paid', 'packed', 'shipped'])
                ->count();
        }

        if (Schema::hasTable('admin_orders')) {
            $metrics['active_mitra_orders'] = DB::table('admin_orders')
                ->whereIn('status', ['pending', 'approved', 'processing', 'shipped'])
                ->count();
        }

        $metrics['total_pengajuan'] =
            (int) ($metrics['pending_mitra_applications'] ?? 0)
            + (int) ($metrics['pending_affiliate_applications'] ?? 0)
            + (int) ($metrics['pending_farmer_seller_applications'] ?? 0);

        $dashboardViewData = $this->dashboardViewModelFactory->make(
            metrics: $metrics,
            adminName: auth()->user()?->name
        );
        $weatherDashboard = $this->buildWeatherDashboardSummary();

        return view('admin.dashboard', array_merge(
            [
                'metrics' => $metrics,
                'pendingModeRequests' => $pendingModeRequests,
                'demoAdminBalance' => $demoAdminBalance,
                'weatherDashboard' => $weatherDashboard,
            ],
            $dashboardViewData
        ));
    }

    private function resolveAdminWalletBalance(int $adminUserId): float
    {
        if ($adminUserId <= 0 || ! Schema::hasTable('wallet_transactions')) {
            return 0.0;
        }

        return $this->walletService->getBalance($adminUserId);
    }

    /**
     * Ringkas status cuaca nasional/lokasi dari snapshot + notifikasi aktif.
     *
     * @return array<string, mixed>
     */
    private function buildWeatherDashboardSummary(): array
    {
        $summary = [
            'sync_status_label' => 'Belum sinkron',
            'sync_status_class' => 'border-slate-200 bg-slate-100 text-slate-700',
            'source_label' => 'Belum ada data cuaca',
            'source_hint' => 'Jalankan Status Cuaca Wilayah untuk sinkron data terbaru.',
            'openweather_count' => 0,
            'bmkg_fallback_count' => 0,
            'city_covered_count' => 0,
            'stale_count' => 0,
            'latest_sync_label' => '-',
            'cache_valid_until_label' => '-',
            'severity' => [
                'green' => 0,
                'yellow' => 0,
                'red' => 0,
                'unknown' => 0,
            ],
            'priority_regions' => 0,
            'active_notices_total' => 0,
            'active_notices_global' => 0,
            'active_notices_region' => 0,
        ];

        if (! Schema::hasTable('weather_snapshots')) {
            return $summary;
        }

        $now = now();
        $latestCurrentSnapshots = $this->latestWeatherCitySnapshotsByKind('current');
        $latestForecastSnapshots = $this->latestWeatherCitySnapshotsByKind('forecast');

        $latestFetchedAt = null;
        $nearestValidUntil = null;

        foreach ($latestCurrentSnapshots as $snapshot) {
            $summary['city_covered_count']++;

            $payload = $this->normalizeWeatherPayload($snapshot->payload ?? null);
            $source = strtolower(trim((string) data_get($payload, 'source', 'openweather')));

            if ($source === 'bmkg_fallback') {
                $summary['bmkg_fallback_count']++;
            } else {
                $summary['openweather_count']++;
            }

            $fetchedAt = $this->parseDateTime($snapshot->fetched_at ?? null);
            $validUntil = $this->parseDateTime($snapshot->valid_until ?? null);

            if ($fetchedAt && (! $latestFetchedAt || $fetchedAt->greaterThan($latestFetchedAt))) {
                $latestFetchedAt = $fetchedAt;
            }

            if ($validUntil && $validUntil->greaterThanOrEqualTo($now)) {
                if (! $nearestValidUntil || $validUntil->lessThan($nearestValidUntil)) {
                    $nearestValidUntil = $validUntil;
                }
            } else {
                $summary['stale_count']++;
            }
        }

        foreach ($latestForecastSnapshots as $snapshot) {
            $payload = $this->normalizeWeatherPayload($snapshot->payload ?? null);
            $alert = $this->weatherAlertEngine->evaluateForecast($payload);
            $severity = strtolower(trim((string) ($alert['severity'] ?? 'unknown')));

            if (! array_key_exists($severity, $summary['severity'])) {
                $severity = 'unknown';
            }

            $summary['severity'][$severity]++;
        }

        if (Schema::hasTable('admin_weather_notices')) {
            $activeNotices = DB::table('admin_weather_notices')
                ->where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', $now);
                });

            $summary['active_notices_total'] = (clone $activeNotices)->count();
            $summary['active_notices_global'] = (clone $activeNotices)
                ->where('scope', 'global')
                ->count();
            $summary['active_notices_region'] = (clone $activeNotices)
                ->whereIn('scope', ['province', 'city', 'district'])
                ->count();
        }

        $summary['priority_regions'] = (int) $summary['severity']['red'] + (int) $summary['severity']['yellow'];
        $summary['latest_sync_label'] = $latestFetchedAt ? $latestFetchedAt->format('d M Y H:i') : '-';
        $summary['cache_valid_until_label'] = $nearestValidUntil ? $nearestValidUntil->format('d M Y H:i') : '-';

        if ($summary['city_covered_count'] <= 0) {
            $summary['sync_status_label'] = 'Belum sinkron';
            $summary['sync_status_class'] = 'border-slate-200 bg-slate-100 text-slate-700';
        } elseif ($summary['stale_count'] > 0) {
            $summary['sync_status_label'] = 'Perlu sinkron';
            $summary['sync_status_class'] = 'border-amber-200 bg-amber-50 text-amber-700';
        } else {
            $summary['sync_status_label'] = 'Sinkron';
            $summary['sync_status_class'] = 'border-emerald-200 bg-emerald-50 text-emerald-700';
        }

        if ($summary['city_covered_count'] <= 0) {
            $summary['source_label'] = 'Belum ada data cuaca';
            $summary['source_hint'] = 'Jalankan Status Cuaca Wilayah untuk sinkron data terbaru.';
        } elseif ($summary['openweather_count'] > 0 && $summary['bmkg_fallback_count'] > 0) {
            $summary['source_label'] = 'OpenWeather + BMKG Fallback';
            $summary['source_hint'] = 'Fallback BMKG aktif untuk wilayah yang data OpenWeather-nya invalid.';
        } elseif ($summary['bmkg_fallback_count'] > 0) {
            $summary['source_label'] = 'BMKG Fallback';
            $summary['source_hint'] = 'Data dominan berasal dari BMKG fallback.';
        } else {
            $summary['source_label'] = 'OpenWeather';
            $summary['source_hint'] = 'Data cuaca utama dari OpenWeather.';
        }

        return $summary;
    }

    /**
     * Ambil snapshot terbaru per kota untuk kind tertentu.
     */
    private function latestWeatherCitySnapshotsByKind(string $kind): Collection
    {
        if (! Schema::hasTable('weather_snapshots')) {
            return collect();
        }

        $subQuery = DB::table('weather_snapshots as ws')
            ->select('ws.location_type', 'ws.location_id', DB::raw('MAX(ws.fetched_at) as max_fetched_at'))
            ->where('ws.provider', 'openweather')
            ->where('ws.kind', $kind)
            ->where('ws.location_type', 'city')
            ->groupBy('ws.location_type', 'ws.location_id');

        return DB::table('weather_snapshots as ws')
            ->joinSub($subQuery, 'latest', function ($join) {
                $join->on('latest.location_type', '=', 'ws.location_type')
                    ->on('latest.location_id', '=', 'ws.location_id')
                    ->on('latest.max_fetched_at', '=', 'ws.fetched_at');
            })
            ->where('ws.provider', 'openweather')
            ->where('ws.kind', $kind)
            ->where('ws.location_type', 'city')
            ->orderByDesc('ws.fetched_at')
            ->get([
                'ws.location_id',
                'ws.payload',
                'ws.fetched_at',
                'ws.valid_until',
            ]);
    }

    /**
     * Normalisasi payload snapshot supaya aman diproses.
     *
     * @return array<string, mixed>
     */
    private function normalizeWeatherPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return (array) $payload;
        }

        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
