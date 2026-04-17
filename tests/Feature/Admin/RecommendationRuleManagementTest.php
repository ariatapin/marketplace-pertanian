<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Recommendation\RuleBasedRecommendationService;
use App\Support\BehaviorRecommendationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecommendationRuleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_recommendation_rule_page_and_create_default_rules(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.recommendationRules'));

        $response->assertOk();
        $response->assertSee('Rule-Based Time Triggered Recommendation');
        $response->assertSee('Rule Seller');

        $this->assertDatabaseHas('recommendation_rules', [
            'role_target' => 'consumer',
            'rule_key' => (string) config('recommendation.consumer.rule_key', 'consumer_spraying_followup'),
        ]);
        $this->assertDatabaseHas('recommendation_rules', [
            'role_target' => 'mitra',
            'rule_key' => (string) config('recommendation.mitra.rule_key', 'mitra_demand_forecast_pesticide'),
        ]);
        $this->assertDatabaseHas('recommendation_rules', [
            'role_target' => 'seller',
            'rule_key' => (string) config('recommendation.seller.rule_key', 'seller_demand_harvest_ops'),
        ]);
    }

    public function test_admin_with_whitespace_role_can_open_recommendation_rule_page(): void
    {
        $admin = User::factory()->create([
            'role' => ' Admin ',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.recommendationRules'));

        $response->assertOk();
    }

    public function test_admin_can_disable_consumer_rule_and_recommendation_service_skips_dispatch(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        [$provinceId, $cityId] = $this->createRegion('Jawa Timur', 'Surabaya');
        $consumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'lat' => null,
            'lng' => null,
        ]);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $consumer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 250000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'shipping_status' => 'delivered',
            'payment_proof_url' => null,
            'paid_amount' => 250000,
            'payment_submitted_at' => now()->subDays(7),
            'resi_number' => null,
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(7),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => 10,
            'product_name' => 'Pupuk Urea Premium',
            'qty' => 2,
            'price_per_unit' => 125000,
            'affiliate_id' => null,
            'commission_amount' => 0,
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(7),
        ]);

        $this->seedWeatherSnapshotForCity($cityId, temp: 29.0, humidity: 82, weatherMain: 'Clear', weatherDescription: 'cerah');

        $this->actingAs($admin)->get(route('admin.modules.recommendationRules'))->assertOk();

        $consumerRuleId = (int) DB::table('recommendation_rules')
            ->where('role_target', 'consumer')
            ->value('id');

        $updateResponse = $this->actingAs($admin)
            ->patch(route('admin.modules.recommendationRules.update', ['ruleId' => $consumerRuleId]), [
                'name' => 'Consumer: Rekomendasi Penyemprotan',
                'description' => 'Rule dinonaktifkan untuk validasi guard.',
                'is_active' => '0',
                'product_keywords' => 'pupuk',
                'clear_keywords' => 'clear,cerah,sunny',
                'trigger_days_after_purchase' => 7,
                'trigger_window_days' => 7,
                'lookback_days' => 45,
                'humidity_min' => 70,
            ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('status');

        $this->assertDatabaseHas('recommendation_rules', [
            'id' => $consumerRuleId,
            'is_active' => false,
        ]);

        $service = app(RuleBasedRecommendationService::class);
        $dispatched = $service->syncForUser($consumer);

        $this->assertCount(0, $dispatched);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'type' => BehaviorRecommendationNotification::class,
        ]);
    }

    public function test_admin_can_trigger_recommendation_sync_from_ui(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.modules.recommendationRules.syncNow'), [
                'roles' => ['consumer', 'mitra', 'seller'],
                'chunk' => 100,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    public function test_admin_can_update_seller_rule_configuration(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)->get(route('admin.modules.recommendationRules'))->assertOk();

        $sellerRuleId = (int) DB::table('recommendation_rules')
            ->where('role_target', 'seller')
            ->value('id');

        $response = $this->actingAs($admin)
            ->patch(route('admin.modules.recommendationRules.update', ['ruleId' => $sellerRuleId]), [
                'name' => 'Seller: Demand Panen',
                'description' => 'Rule seller untuk monitoring demand panen.',
                'is_active' => '1',
                'lookback_days' => 10,
                'min_paid_orders' => 8,
                'min_total_qty' => 25,
                'target_window_days' => '4-6',
                'allowed_weather_severities' => 'green,yellow',
                'harvest_temp_min' => 21,
                'harvest_temp_max' => 33,
                'harvest_humidity_min' => 55,
                'harvest_humidity_max' => 90,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('recommendation_rules', [
            'id' => $sellerRuleId,
            'name' => 'Seller: Demand Panen',
            'is_active' => true,
        ]);

        $settings = DB::table('recommendation_rules')
            ->where('id', $sellerRuleId)
            ->value('settings');

        $decodedSettings = is_string($settings) ? json_decode($settings, true) : (array) $settings;
        $decodedSettings = is_array($decodedSettings) ? $decodedSettings : [];

        $this->assertSame(10, (int) ($decodedSettings['lookback_days'] ?? 0));
        $this->assertSame(8, (int) ($decodedSettings['min_paid_orders'] ?? 0));
        $this->assertSame(25, (int) ($decodedSettings['min_total_qty'] ?? 0));
        $this->assertSame('4-6', (string) ($decodedSettings['target_window_days'] ?? ''));
    }

    private function seedWeatherSnapshotForCity(
        int $cityId,
        float $temp,
        int $humidity,
        string $weatherMain,
        string $weatherDescription
    ): void {
        DB::table('weather_snapshots')->insert([
            [
                'provider' => 'openweather',
                'kind' => 'current',
                'location_type' => 'city',
                'location_id' => $cityId,
                'lat' => -7.2574720,
                'lng' => 112.7520900,
                'payload' => json_encode([
                    'main' => [
                        'temp' => $temp,
                        'humidity' => $humidity,
                    ],
                    'weather' => [
                        [
                            'main' => $weatherMain,
                            'description' => $weatherDescription,
                        ],
                    ],
                    'wind' => [
                        'speed' => 2.1,
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'fetched_at' => now()->subMinutes(5),
                'valid_until' => now()->addMinutes(120),
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ],
            [
                'provider' => 'openweather',
                'kind' => 'forecast',
                'location_type' => 'city',
                'location_id' => $cityId,
                'lat' => -7.2574720,
                'lng' => 112.7520900,
                'payload' => json_encode([
                    'list' => collect(range(1, 8))->map(function () use ($temp, $humidity) {
                        return [
                            'main' => [
                                'temp' => $temp,
                                'humidity' => $humidity,
                            ],
                            'wind' => [
                                'speed' => 3.0,
                            ],
                            'rain' => [
                                '3h' => 0,
                            ],
                            'pop' => 0.1,
                            'dt_txt' => now()->addHours(3)->format('Y-m-d H:i:s'),
                        ];
                    })->all(),
                ], JSON_UNESCAPED_UNICODE),
                'fetched_at' => now()->subMinutes(5),
                'valid_until' => now()->addMinutes(180),
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ],
        ]);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function createRegion(string $provinceName, string $cityName): array
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => $provinceName,
            'code' => strtolower(str_replace(' ', '-', $provinceName)) . '-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => $cityName,
            'type' => 'Kota',
            'lat' => -7.2574720,
            'lng' => 112.7520900,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$provinceId, $cityId];
    }
}
