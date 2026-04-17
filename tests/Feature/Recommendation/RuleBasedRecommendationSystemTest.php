<?php

namespace Tests\Feature\Recommendation;

use App\Models\User;
use App\Services\Recommendation\RuleBasedRecommendationService;
use App\Support\BehaviorRecommendationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RuleBasedRecommendationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_receives_behavior_recommendation_and_it_is_idempotent(): void
    {
        [$provinceId, $cityId] = $this->createRegion('Jawa Tengah', 'Semarang');
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

        $this->seedWeatherSnapshotForCity($cityId, temp: 29.1, humidity: 82, weatherMain: 'Clear', weatherDescription: 'cerah');

        $service = app(RuleBasedRecommendationService::class);
        $firstRun = $service->syncForUser($consumer);
        $secondRun = $service->syncForUser($consumer);

        $this->assertCount(1, $firstRun);
        $this->assertCount(0, $secondRun);

        $this->assertDatabaseCount('recommendation_dispatches', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'type' => BehaviorRecommendationNotification::class,
        ]);

        $response = $this->actingAs($consumer)->get(route('landing'));
        $response->assertOk();
        $response->assertSee('Rekomendasi Penyemprotan');
    }

    public function test_consumer_recommendation_includes_behavior_time_window_when_history_is_sufficient(): void
    {
        [$provinceId, $cityId] = $this->createRegion('Jawa Timur', 'Kediri');
        $consumer = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'lat' => null,
            'lng' => null,
        ]);
        $seller = User::factory()->create(['role' => 'mitra']);

        foreach ([0, 1, 2] as $dayOffset) {
            $orderedAt = now()->subDays(7 + $dayOffset)->setTime(8, 15, 0);

            $orderId = DB::table('orders')->insertGetId([
                'buyer_id' => $consumer->id,
                'seller_id' => $seller->id,
                'order_source' => 'store_online',
                'total_amount' => 120000,
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
                'payment_proof_url' => null,
                'paid_amount' => 120000,
                'payment_submitted_at' => $orderedAt,
                'resi_number' => null,
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
            ]);

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => 11 + $dayOffset,
                'product_name' => 'Pupuk NPK Plus',
                'qty' => 1,
                'price_per_unit' => 120000,
                'affiliate_id' => null,
                'commission_amount' => 0,
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
            ]);
        }

        $this->seedWeatherSnapshotForCity($cityId, temp: 29.3, humidity: 84, weatherMain: 'Clear', weatherDescription: 'cerah');

        $service = app(RuleBasedRecommendationService::class);
        $dispatched = $service->syncForUser($consumer);

        $this->assertCount(1, $dispatched);
        $this->assertTrue((bool) data_get($dispatched, '0.context.behavior_time_window.available', false));
        $this->assertNotSame('', (string) data_get($dispatched, '0.context.behavior_time_window.window_label', ''));
        $this->assertStringContainsString(
            'Pola belanja Anda paling sering terjadi pada',
            (string) ($dispatched[0]['message'] ?? '')
        );
    }

    public function test_mitra_receives_demand_recommendation_from_rule_based_trigger(): void
    {
        [$provinceId, $cityId] = $this->createRegion('Jawa Barat', 'Bandung');
        $mitra = User::factory()->create([
            'role' => 'mitra',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'lat' => null,
            'lng' => null,
        ]);

        for ($i = 1; $i <= 20; $i++) {
            $buyer = User::factory()->create([
                'role' => 'consumer',
                'province_id' => $provinceId,
                'city_id' => $cityId,
            ]);

            $orderId = DB::table('orders')->insertGetId([
                'buyer_id' => $buyer->id,
                'seller_id' => $mitra->id,
                'order_source' => 'store_online',
                'total_amount' => 120000,
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
                'payment_proof_url' => null,
                'paid_amount' => 120000,
                'payment_submitted_at' => now()->subDays(2),
                'resi_number' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $i,
                'product_name' => 'Pupuk Organik Kompos',
                'qty' => 1,
                'price_per_unit' => 120000,
                'affiliate_id' => null,
                'commission_amount' => 0,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        }

        $this->seedWeatherSnapshotForCity($cityId, temp: 28.4, humidity: 74, weatherMain: 'Clouds', weatherDescription: 'berawan');

        $service = app(RuleBasedRecommendationService::class);
        $firstRun = $service->syncForUser($mitra);
        $secondRun = $service->syncForUser($mitra);

        $this->assertCount(1, $firstRun);
        $this->assertCount(0, $secondRun);
        $this->assertTrue((bool) data_get($firstRun, '0.context.behavior_time_window.available', false));
        $this->assertNotSame('', (string) data_get($firstRun, '0.context.behavior_time_window.window_label', ''));

        $this->assertDatabaseCount('recommendation_dispatches', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $mitra->id,
            'type' => BehaviorRecommendationNotification::class,
        ]);

        $response = $this->actingAs($mitra)->get(route('mitra.dashboard'));
        $response->assertOk();
        $response->assertSee('Potensi Permintaan Pestisida');
    }

    public function test_seller_mode_user_receives_seller_recommendation_from_farmer_p2p_sales(): void
    {
        $sellerModeUser = $this->seedSellerModeRecommendationFixture();

        $service = app(RuleBasedRecommendationService::class);
        $firstRun = $service->syncForUser($sellerModeUser);
        $secondRun = $service->syncForUser($sellerModeUser);

        $this->assertCount(1, $firstRun);
        $this->assertCount(0, $secondRun);
        $this->assertSame('seller', (string) ($firstRun[0]['role_target'] ?? ''));
        $this->assertTrue((bool) data_get($firstRun, '0.context.behavior_time_window.available', false));
        $this->assertNotSame('', (string) data_get($firstRun, '0.context.behavior_time_window.window_label', ''));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $sellerModeUser->id,
            'type' => BehaviorRecommendationNotification::class,
        ]);

        $response = $this->actingAs($sellerModeUser)->get(route('seller.dashboard'));
        $response->assertOk();
        $response->assertSee('Notifikasi Dashboard Penjual');
        $response->assertDontSee('Potensi Permintaan Produk Petani');
    }

    public function test_seller_dashboard_triggers_recommendation_sync_automatically(): void
    {
        $sellerModeUser = $this->seedSellerModeRecommendationFixture();

        $this->assertDatabaseCount('recommendation_dispatches', 0);

        $firstResponse = $this->actingAs($sellerModeUser)->get(route('seller.dashboard'));
        $firstResponse->assertOk();
        $firstResponse->assertSee('Notifikasi Dashboard Penjual');
        $firstResponse->assertDontSee('Potensi Permintaan Produk Petani');

        $secondResponse = $this->actingAs($sellerModeUser)->get(route('seller.dashboard'));
        $secondResponse->assertOk();

        $this->assertDatabaseCount('recommendation_dispatches', 1);
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $sellerModeUser->id)
            ->where('type', BehaviorRecommendationNotification::class)
            ->count());
    }

    public function test_seller_api_summary_syncs_and_counts_only_seller_recommendation_unread(): void
    {
        $sellerModeUser = $this->seedSellerModeRecommendationFixture();

        // Noise notification untuk role lain tidak boleh dihitung di unread seller.
        $sellerModeUser->notify(new BehaviorRecommendationNotification(
            status: 'yellow',
            title: 'Noise Recommendation',
            message: 'Bukan untuk seller.',
            roleTarget: 'consumer',
            ruleKey: 'noise_consumer_rule',
            dispatchKey: 'noise-consumer-' . now()->format('YmdHisv'),
            targetLabel: 'Noise',
            validUntil: now()->addHour()->toDateTimeString(),
            actionUrl: '/dashboard',
            actionLabel: 'Lihat'
        ));

        Sanctum::actingAs($sellerModeUser);

        $response = $this->getJson('/api/seller/dashboard');
        $response->assertOk();
        $response->assertJsonPath('data.recommendation_unread_count', 1);

        $secondResponse = $this->getJson('/api/seller/dashboard');
        $secondResponse->assertOk();
        $secondResponse->assertJsonPath('data.recommendation_unread_count', 1);

        $this->assertDatabaseCount('recommendation_dispatches', 1);
    }

    private function seedSellerModeRecommendationFixture(): User
    {
        [$provinceId, $cityId] = $this->createRegion('DIY', 'Yogyakarta');
        $sellerModeUser = User::factory()->create([
            'role' => 'consumer',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'lat' => null,
            'lng' => null,
        ]);

        DB::table('consumer_profiles')->insert([
            'user_id' => $sellerModeUser->id,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $buyer = User::factory()->create([
                'role' => 'consumer',
                'province_id' => $provinceId,
                'city_id' => $cityId,
            ]);

            $orderId = DB::table('orders')->insertGetId([
                'buyer_id' => $buyer->id,
                'seller_id' => $sellerModeUser->id,
                'order_source' => 'farmer_p2p',
                'total_amount' => 150000,
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
                'payment_proof_url' => null,
                'paid_amount' => 150000,
                'payment_submitted_at' => now()->subDays(2),
                'resi_number' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $i,
                'product_name' => 'Cabai Rawit Merah',
                'qty' => 3,
                'price_per_unit' => 50000,
                'affiliate_id' => null,
                'commission_amount' => 0,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        }

        $this->seedWeatherSnapshotForCity($cityId, temp: 27.4, humidity: 72, weatherMain: 'Clouds', weatherDescription: 'berawan');

        return $sellerModeUser;
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
                'lat' => -6.9147440,
                'lng' => 107.6098100,
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
                        'speed' => 2.4,
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
                'lat' => -6.9147440,
                'lng' => 107.6098100,
                'payload' => json_encode([
                    'list' => collect(range(1, 8))->map(function () use ($temp, $humidity) {
                        return [
                            'main' => [
                                'temp' => $temp,
                                'humidity' => $humidity,
                            ],
                            'wind' => [
                                'speed' => 3.2,
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
            'lat' => -6.9147440,
            'lng' => 107.6098100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$provinceId, $cityId];
    }
}
