<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecommendationRule;
use App\Services\Recommendation\RuleBasedRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RecommendationRulePageController extends Controller
{
    public function __invoke(Request $request)
    {
        // CATATAN-AUDIT: Halaman ini khusus admin untuk mengelola rule rekomendasi berbasis perilaku.
        $this->authorize('access-admin');

        if (! Schema::hasTable('recommendation_rules')) {
            return redirect()
                ->route('admin.modules.weather', ['panel' => 'automation'])
                ->withErrors(['recommendation_rules' => 'Tabel recommendation_rules belum tersedia. Jalankan migrasi terbaru.']);
        }

        [$consumerRule, $mitraRule, $sellerRule] = $this->loadOrCreateDefaultRules((int) $request->user()->id);

        return view('admin.recommendation-rules', [
            'consumerRule' => $consumerRule,
            'mitraRule' => $mitraRule,
            'sellerRule' => $sellerRule,
            'syncConfig' => [
                'enabled' => (bool) config('recommendation.sync.enabled', true),
                'cron' => (string) config('recommendation.sync.cron', '23 * * * *'),
            ],
        ]);
    }

    public function update(Request $request, int $ruleId): RedirectResponse
    {
        // CATATAN-AUDIT: Update rule akan langsung mempengaruhi engine rekomendasi (tanpa deploy ulang config).
        $this->authorize('access-admin');

        if (! Schema::hasTable('recommendation_rules')) {
            return back()->withErrors([
                'recommendation_rules' => 'Tabel recommendation_rules belum tersedia. Jalankan migrasi terbaru.',
            ]);
        }

        /** @var RecommendationRule|null $rule */
        $rule = RecommendationRule::query()->find($ruleId);
        if (! $rule) {
            return back()->withErrors([
                'recommendation_rule' => 'Rule rekomendasi tidak ditemukan.',
            ]);
        }

        $basePayload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $settings = match (strtolower(trim((string) $rule->role_target))) {
            'consumer' => $this->validatedConsumerSettings($request),
            'mitra' => $this->validatedMitraSettings($request),
            'seller' => $this->validatedSellerSettings($request),
            default => null,
        };

        if (! is_array($settings)) {
            return back()->withErrors([
                'recommendation_rule' => 'Role target rule tidak valid.',
            ]);
        }

        $rule->fill([
            'name' => trim((string) $basePayload['name']),
            'description' => trim((string) ($basePayload['description'] ?? '')) ?: null,
            'is_active' => (bool) ($basePayload['is_active'] ?? false),
            'settings' => $settings,
            'updated_by' => (int) $request->user()->id,
        ])->save();

        return back()->with('status', 'Rule rekomendasi berhasil diperbarui.');
    }

    public function syncNow(Request $request, RuleBasedRecommendationService $service): RedirectResponse
    {
        // CATATAN-AUDIT: Tombol sinkronisasi manual dipakai untuk trigger rekomendasi real-time dari panel admin.
        $this->authorize('access-admin');

        $payload = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:consumer,mitra,seller'],
            'chunk' => ['nullable', 'integer', 'min:20', 'max:1000'],
        ]);

        $roles = collect((array) ($payload['roles'] ?? []))
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter(fn ($role) => in_array($role, ['consumer', 'mitra', 'seller'], true))
            ->unique()
            ->values()
            ->all();
        if (count($roles) === 0) {
            $roles = ['consumer', 'mitra', 'seller'];
        }

        $chunk = (int) ($payload['chunk'] ?? config('recommendation.sync.chunk', 200));
        $result = $service->syncForRoles($roles, $chunk);

        return back()->with(
            'status',
            'Sinkronisasi rekomendasi selesai. Processed: '
            . number_format((int) ($result['processed'] ?? 0))
            . ', Dispatched: '
            . number_format((int) ($result['dispatched'] ?? 0))
            . '.'
        );
    }

    /**
     * @return array{0:RecommendationRule,1:RecommendationRule,2:RecommendationRule}
     */
    private function loadOrCreateDefaultRules(int $adminId): array
    {
        $consumerRule = RecommendationRule::query()
            ->where('role_target', 'consumer')
            ->where('rule_key', (string) config('recommendation.consumer.rule_key', 'consumer_spraying_followup'))
            ->first();
        if (! $consumerRule) {
            $consumerRule = RecommendationRule::query()->create([
                'role_target' => 'consumer',
                'rule_key' => (string) config('recommendation.consumer.rule_key', 'consumer_spraying_followup'),
                'name' => 'Consumer: Rekomendasi Penyemprotan',
                'description' => 'Rule time-trigger + cuaca untuk rekomendasi penyemprotan pasca pembelian pupuk.',
                'is_active' => true,
                'settings' => $this->defaultConsumerSettings(),
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        }

        $mitraRule = RecommendationRule::query()
            ->where('role_target', 'mitra')
            ->where('rule_key', (string) config('recommendation.mitra.rule_key', 'mitra_demand_forecast_pesticide'))
            ->first();
        if (! $mitraRule) {
            $mitraRule = RecommendationRule::query()->create([
                'role_target' => 'mitra',
                'rule_key' => (string) config('recommendation.mitra.rule_key', 'mitra_demand_forecast_pesticide'),
                'name' => 'Mitra: Prediksi Permintaan Pestisida',
                'description' => 'Rule perilaku pembelian lokasi + cuaca vegetatif untuk peringatan potensi demand.',
                'is_active' => true,
                'settings' => $this->defaultMitraSettings(),
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        }

        $sellerRule = RecommendationRule::query()
            ->where('role_target', 'seller')
            ->where('rule_key', (string) config('recommendation.seller.rule_key', 'seller_demand_harvest_ops'))
            ->first();
        if (! $sellerRule) {
            $sellerRule = RecommendationRule::query()->create([
                'role_target' => 'seller',
                'rule_key' => (string) config('recommendation.seller.rule_key', 'seller_demand_harvest_ops'),
                'name' => 'Seller: Potensi Permintaan Produk Petani',
                'description' => 'Rule perilaku penjualan P2P penjual + cuaca lokal untuk antisipasi stok dan panen.',
                'is_active' => true,
                'settings' => $this->defaultSellerSettings(),
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        }

        return [$consumerRule, $mitraRule, $sellerRule];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedConsumerSettings(Request $request): array
    {
        $payload = $request->validate([
            'product_keywords' => ['required', 'string', 'max:250'],
            'clear_keywords' => ['required', 'string', 'max:250'],
            'trigger_days_after_purchase' => ['required', 'integer', 'min:1', 'max:30'],
            'trigger_window_days' => ['required', 'integer', 'min:1', 'max:30'],
            'lookback_days' => ['required', 'integer', 'min:7', 'max:180'],
            'humidity_min' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        return [
            'product_keywords' => $this->explodeKeywords((string) $payload['product_keywords']),
            'clear_keywords' => $this->explodeKeywords((string) $payload['clear_keywords']),
            'trigger_days_after_purchase' => (int) $payload['trigger_days_after_purchase'],
            'trigger_window_days' => (int) $payload['trigger_window_days'],
            'lookback_days' => (int) $payload['lookback_days'],
            'humidity_min' => (int) $payload['humidity_min'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMitraSettings(Request $request): array
    {
        $payload = $request->validate([
            'product_keywords' => ['required', 'string', 'max:250'],
            'allowed_weather_severities' => ['required', 'string', 'max:120'],
            'lookback_days' => ['required', 'integer', 'min:1', 'max:30'],
            'min_distinct_buyers' => ['required', 'integer', 'min:1', 'max:10000'],
            'target_window_days' => ['required', 'string', 'max:40'],
            'vegetative_temp_min' => ['required', 'numeric', 'min:-10', 'max:60'],
            'vegetative_temp_max' => ['required', 'numeric', 'min:-10', 'max:60'],
            'vegetative_humidity_min' => ['required', 'integer', 'min:1', 'max:100'],
            'vegetative_humidity_max' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        return [
            'product_keywords' => $this->explodeKeywords((string) $payload['product_keywords']),
            'allowed_weather_severities' => $this->explodeKeywords((string) $payload['allowed_weather_severities']),
            'lookback_days' => (int) $payload['lookback_days'],
            'min_distinct_buyers' => (int) $payload['min_distinct_buyers'],
            'target_window_days' => trim((string) $payload['target_window_days']),
            'vegetative_temp_min' => (float) $payload['vegetative_temp_min'],
            'vegetative_temp_max' => (float) $payload['vegetative_temp_max'],
            'vegetative_humidity_min' => (int) $payload['vegetative_humidity_min'],
            'vegetative_humidity_max' => (int) $payload['vegetative_humidity_max'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSellerSettings(Request $request): array
    {
        $payload = $request->validate([
            'lookback_days' => ['required', 'integer', 'min:1', 'max:30'],
            'min_paid_orders' => ['required', 'integer', 'min:1', 'max:10000'],
            'min_total_qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'target_window_days' => ['required', 'string', 'max:40'],
            'allowed_weather_severities' => ['required', 'string', 'max:120'],
            'harvest_temp_min' => ['required', 'numeric', 'min:-10', 'max:60'],
            'harvest_temp_max' => ['required', 'numeric', 'min:-10', 'max:60'],
            'harvest_humidity_min' => ['required', 'integer', 'min:1', 'max:100'],
            'harvest_humidity_max' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        return [
            'lookback_days' => (int) $payload['lookback_days'],
            'min_paid_orders' => (int) $payload['min_paid_orders'],
            'min_total_qty' => (int) $payload['min_total_qty'],
            'target_window_days' => trim((string) $payload['target_window_days']),
            'allowed_weather_severities' => $this->explodeKeywords((string) $payload['allowed_weather_severities']),
            'harvest_temp_min' => (float) $payload['harvest_temp_min'],
            'harvest_temp_max' => (float) $payload['harvest_temp_max'],
            'harvest_humidity_min' => (int) $payload['harvest_humidity_min'],
            'harvest_humidity_max' => (int) $payload['harvest_humidity_max'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function defaultConsumerSettings(): array
    {
        return [
            'product_keywords' => (array) config('recommendation.consumer.product_keywords', ['pupuk']),
            'clear_keywords' => (array) config('recommendation.consumer.clear_keywords', ['clear', 'cerah', 'sunny']),
            'trigger_days_after_purchase' => (int) config('recommendation.consumer.trigger_days_after_purchase', 7),
            'trigger_window_days' => (int) config('recommendation.consumer.trigger_window_days', 7),
            'lookback_days' => (int) config('recommendation.consumer.lookback_days', 45),
            'humidity_min' => (int) config('recommendation.consumer.humidity_min', 70),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function defaultMitraSettings(): array
    {
        return [
            'product_keywords' => (array) config('recommendation.mitra.product_keywords', ['pupuk']),
            'allowed_weather_severities' => (array) config('recommendation.mitra.allowed_weather_severities', ['green', 'yellow']),
            'lookback_days' => (int) config('recommendation.mitra.lookback_days', 7),
            'min_distinct_buyers' => (int) config('recommendation.mitra.min_distinct_buyers', 20),
            'target_window_days' => (string) config('recommendation.mitra.target_window_days', '7-10'),
            'vegetative_temp_min' => (float) config('recommendation.mitra.vegetative_temp_min', 20),
            'vegetative_temp_max' => (float) config('recommendation.mitra.vegetative_temp_max', 33),
            'vegetative_humidity_min' => (int) config('recommendation.mitra.vegetative_humidity_min', 55),
            'vegetative_humidity_max' => (int) config('recommendation.mitra.vegetative_humidity_max', 95),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function defaultSellerSettings(): array
    {
        return [
            'lookback_days' => (int) config('recommendation.seller.lookback_days', 7),
            'min_paid_orders' => (int) config('recommendation.seller.min_paid_orders', 5),
            'min_total_qty' => (int) config('recommendation.seller.min_total_qty', 10),
            'target_window_days' => (string) config('recommendation.seller.target_window_days', '3-5'),
            'allowed_weather_severities' => (array) config('recommendation.seller.allowed_weather_severities', ['green', 'yellow']),
            'harvest_temp_min' => (float) config('recommendation.seller.harvest_temp_min', 20),
            'harvest_temp_max' => (float) config('recommendation.seller.harvest_temp_max', 34),
            'harvest_humidity_min' => (int) config('recommendation.seller.harvest_humidity_min', 50),
            'harvest_humidity_max' => (int) config('recommendation.seller.harvest_humidity_max', 95),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function explodeKeywords(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($token) => strtolower(trim((string) $token)))
            ->filter(fn ($token) => $token !== '')
            ->unique()
            ->values()
            ->all();
    }
}
