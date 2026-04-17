<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Support\AdminWeatherNoticeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleAutomationCycleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_skips_when_feature_flag_is_disabled(): void
    {
        DB::table('feature_flags')->insert([
            'key' => 'automation_role_cycle',
            'is_enabled' => false,
            'description' => 'Automation OFF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('automation:role-cycle')
            ->expectsOutputToContain('nonaktif')
            ->assertSuccessful();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_command_runs_and_creates_consumer_order_when_feature_flag_is_enabled(): void
    {
        config()->set('recommendation.enabled', false);

        DB::table('feature_flags')->insert([
            'key' => 'automation_role_cycle',
            'is_enabled' => true,
            'description' => 'Automation ON',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $consumer = User::factory()->create(['role' => 'consumer']);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Pupuk Urea',
            'description' => 'Pupuk untuk simulasi checkout otomatis.',
            'price' => 55000,
            'unit' => 'kg',
            'stock_qty' => 50,
            'image_url' => 'products/pupuk-a.jpg',
            'is_active' => true,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('automation:role-cycle')
            ->expectsOutputToContain('Siklus otomatis role selesai dijalankan')
            ->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $consumer->id,
            'order_source' => 'store_online',
        ]);
    }

    public function test_command_auto_activates_inactive_mitra_procured_product_using_existing_random_images(): void
    {
        config()->set('recommendation.enabled', false);

        DB::table('feature_flags')->insert([
            'key' => 'automation_role_cycle',
            'is_enabled' => true,
            'description' => 'Automation ON',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $seller = User::factory()->create(['role' => 'seller']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Produk Pengadaan A',
            'description' => 'Produk sumber admin.',
            'price' => 10000,
            'unit' => 'kg',
            'min_order_qty' => 1,
            'stock_qty' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('farmer_harvests')->insert([
            [
                'farmer_id' => $seller->id,
                'name' => 'Gambar 1',
                'description' => 'Pool gambar 1',
                'price' => 10000,
                'stock_qty' => 20,
                'harvest_date' => now()->toDateString(),
                'image_url' => 'products/pool-1.jpg',
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'farmer_id' => $seller->id,
                'name' => 'Gambar 2',
                'description' => 'Pool gambar 2',
                'price' => 11000,
                'stock_qty' => 20,
                'harvest_date' => now()->toDateString(),
                'image_url' => 'products/pool-2.jpg',
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'farmer_id' => $seller->id,
                'name' => 'Gambar 3',
                'description' => 'Pool gambar 3',
                'price' => 12000,
                'stock_qty' => 20,
                'harvest_date' => now()->toDateString(),
                'image_url' => 'products/pool-3.jpg',
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $storeProductId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'source_admin_product_id' => $adminProductId,
            'name' => 'Produk Mitra Inactive',
            'description' => 'Deskripsi produk valid untuk aktivasi auto.',
            'price' => 15000,
            'unit' => 'kg',
            'stock_qty' => 30,
            'image_url' => null,
            'is_active' => false,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('automation:role-cycle')->assertSuccessful();

        $this->assertDatabaseHas('store_products', [
            'id' => $storeProductId,
            'is_active' => true,
        ]);

        $galleryCount = DB::table('store_product_images')
            ->where('store_product_id', $storeProductId)
            ->count();

        $this->assertGreaterThanOrEqual(2, $galleryCount);
    }

    public function test_command_allows_admin_to_auto_respond_weather_and_send_notifications(): void
    {
        config()->set('recommendation.enabled', false);

        DB::table('feature_flags')->insert([
            'key' => 'automation_role_cycle',
            'is_enabled' => true,
            'description' => 'Automation ON',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create(['role' => 'admin']);
        $consumer = User::factory()->create(['role' => 'consumer']);

        DB::table('weather_snapshots')->insert([
            'provider' => 'openweather',
            'kind' => 'forecast',
            'location_type' => 'city',
            'location_id' => 1,
            'lat' => -7.2504450,
            'lng' => 112.7688450,
            'payload' => json_encode([
                'list' => [
                    [
                        'dt_txt' => now()->addHours(3)->toDateTimeString(),
                        'pop' => 0.92,
                        'rain' => ['3h' => 12.5],
                        'wind' => ['speed' => 5.1],
                        'main' => ['temp' => 29.4],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'fetched_at' => now(),
            'valid_until' => now()->addHours(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('automation:role-cycle')->assertSuccessful();

        $this->assertDatabaseHas('admin_weather_notices', [
            'scope' => 'global',
            'severity' => 'red',
            'title' => '[AUTO][ADMIN][WEATHER] SIAGA TINGGI',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'type' => AdminWeatherNoticeNotification::class,
        ]);
    }

    public function test_command_auto_processes_withdraw_cycle_for_mitra_seller_and_affiliate(): void
    {
        config()->set('recommendation.enabled', false);
        config()->set('finance.demo_mode', true);

        DB::table('feature_flags')->insert([
            'key' => 'automation_role_cycle',
            'is_enabled' => true,
            'description' => 'Automation ON',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $seller = User::factory()->create(['role' => 'farmer_seller']);
        $affiliate = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => (int) $affiliate->id,
            'address' => 'Alamat affiliate test',
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => 'affiliate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$mitra, $seller, $affiliate] as $index => $actor) {
            DB::table('withdraw_bank_accounts')->insert([
                'user_id' => (int) $actor->id,
                'bank_name' => 'Bank Simulasi',
                'account_number' => '8800' . ($index + 1) . '123456',
                'account_holder' => $actor->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $seedWallet = function (User $user, float $amount, string $scope): void {
            DB::table('wallet_transactions')->insert([
                'wallet_id' => (int) $user->id,
                'amount' => $amount,
                'transaction_type' => 'demo_credit',
                'idempotency_key' => "test:auto-withdraw:{$scope}:{$user->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Seed saldo untuk test auto withdraw role cycle.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $seedWallet($admin, 1_500_000, 'admin');
        $seedWallet($mitra, 300_000, 'mitra');
        $seedWallet($seller, 300_000, 'seller');
        $seedWallet($affiliate, 300_000, 'affiliate');

        $this->artisan('automation:role-cycle')
            ->expectsOutputToContain('Siklus otomatis role selesai dijalankan')
            ->assertSuccessful();

        $this->assertDatabaseHas('withdraw_requests', [
            'user_id' => (int) $mitra->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('withdraw_requests', [
            'user_id' => (int) $seller->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('withdraw_requests', [
            'user_id' => (int) $affiliate->id,
            'status' => 'paid',
        ]);

        $this->assertGreaterThanOrEqual(
            3,
            DB::table('wallet_transactions')->where('transaction_type', 'withdrawal')->count()
        );
        $this->assertGreaterThanOrEqual(
            3,
            DB::table('wallet_transactions')->where('transaction_type', 'admin_payout')->count()
        );
    }
}
