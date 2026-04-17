<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AffiliateReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandingPageAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_landing_page(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Benih Padi Organik',
            'description' => 'Produk benih untuk uji landing.',
            'price' => 25000,
            'stock_qty' => 15,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Produk');
        $response->assertSee('Benih Padi Organik');
        $response->assertSee('Login');
    }

    public function test_logged_in_user_sees_account_dropdown_on_landing(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        $response = $this->actingAs($consumer)->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Akun');
        $response->assertSee('Profile');
        $response->assertSee('Rekening');
        $response->assertSee('Logout');
        $response->assertDontSee('Daftar');
        $response->assertDontSee('Dashboard Mitra');
    }

    public function test_mitra_is_redirected_to_mitra_dashboard_when_accessing_landing(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $response = $this->actingAs($mitra)->get(route('landing'));

        $response->assertRedirect(route('mitra.dashboard'));
    }

    public function test_mitra_cannot_open_marketplace_account_page(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $this->actingAs($mitra)
            ->get(route('account.show'))
            ->assertRedirect(route('mitra.dashboard'));
    }

    public function test_mitra_cannot_open_marketplace_notifications_page(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $this->actingAs($mitra)
            ->get(route('notifications.index'))
            ->assertRedirect(route('mitra.dashboard'));
    }

    public function test_mitra_cannot_mark_all_marketplace_notifications_as_read(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => \App\Support\MitraApplicationStatusNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $mitra->id,
            'data' => json_encode([
                'status' => 'pending',
                'title' => 'Status Mitra',
                'message' => 'Masih diproses.',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($mitra)
            ->post(route('notifications.readAll'))
            ->assertRedirect(route('mitra.dashboard'));

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $mitra->id)
            ->whereNull('read_at')
            ->count());
    }

    public function test_mitra_cannot_mark_single_marketplace_notification_as_read(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $notificationId = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => \App\Support\MitraApplicationStatusNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $mitra->id,
            'data' => json_encode([
                'status' => 'pending',
                'title' => 'Status Mitra',
                'message' => 'Masih diproses.',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($mitra)
            ->post(route('notifications.read', $notificationId))
            ->assertRedirect(route('mitra.dashboard'));

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'read_at' => null,
        ]);
    }

    public function test_admin_is_redirected_to_admin_dashboard_when_accessing_landing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('landing'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_active_consumer_sees_summary_on_landing_page(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            [
                'buyer_id' => $consumer->id,
                'seller_id' => $seller->id,
                'order_source' => 'store_online',
                'total_amount' => 100000,
                'payment_status' => 'paid',
                'order_status' => 'paid',
                'payment_proof_url' => null,
                'shipping_status' => 'pending',
                'resi_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'buyer_id' => $consumer->id,
                'seller_id' => $seller->id,
                'order_source' => 'store_online',
                'total_amount' => 120000,
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'payment_proof_url' => null,
                'shipping_status' => 'delivered',
                'resi_number' => 'RESI-001',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($consumer)->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Ringkasan Consumer Aktif');
        $response->assertSee('AFFILIATE');
        $response->assertSee('APPROVED');
        $response->assertSee('Masuk Dashboard Affiliate');
    }

    public function test_consumer_login_sees_checkout_shortcuts_on_landing(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        $response = $this->actingAs($consumer)->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Pesanan Saya');
        $response->assertSee(route('cart.index'));
    }

    public function test_seller_mode_consumer_sees_dashboard_seller_button_on_landing(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Masuk Dashboard Penjual');
    }

    public function test_landing_source_seller_only_shows_seller_products(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $seller = User::factory()->create(['role' => 'consumer']);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Mitra A',
            'description' => 'Produk dari mitra.',
            'price' => 35000,
            'stock_qty' => 10,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('farmer_harvests')->insert([
            'farmer_id' => $seller->id,
            'name' => 'Produk Penjual A',
            'description' => 'Produk hasil tani penjual.',
            'price' => 22000,
            'stock_qty' => 12,
            'harvest_date' => now()->toDateString(),
            'image_url' => null,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('landing', ['source' => 'seller']));

        $response->assertOk();
        $response->assertSee('Produk Penjual A');
        $response->assertDontSee('Produk Mitra A');
    }

    public function test_affiliate_mode_user_sees_produk_affiliate_tab(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Produk Affiliate');
    }

    public function test_non_affiliate_user_does_not_see_produk_affiliate_tab_and_referral_banner(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $consumer = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refCode = app(AffiliateReferralService::class)->encodeReferralCode($affiliate->id);

        $response = $this->actingAs($consumer)->get(route('landing', ['ref' => $refCode]));

        $response->assertOk();
        $response->assertDontSee('Produk Affiliate');
        $response->assertDontSee('Link Affiliate Aktif');
    }

    public function test_affiliate_source_only_shows_affiliate_enabled_mitra_products_for_affiliate_mode_user(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_products')->insert([
            [
                'mitra_id' => $mitra->id,
                'name' => 'Produk Affiliate On',
                'description' => 'Mitra dengan affiliate aktif.',
                'price' => 40000,
                'stock_qty' => 15,
                'image_url' => null,
                'is_affiliate_enabled' => true,
                'affiliate_commission' => 10,
                'affiliate_expire_date' => now()->addDays(7)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'name' => 'Produk Affiliate Off',
                'description' => 'Mitra tanpa affiliate.',
                'price' => 35000,
                'stock_qty' => 15,
                'image_url' => null,
                'is_affiliate_enabled' => false,
                'affiliate_commission' => 0,
                'affiliate_expire_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($consumer)->get(route('landing', ['source' => 'affiliate']));

        $response->assertOk();
        $response->assertSee('Produk Affiliate On');
        $response->assertDontSee('Produk Affiliate Off');
        $response->assertSee('Affiliate Aktif');
    }

    public function test_affiliate_ready_marketing_filter_only_shows_products_already_marketed_by_affiliate(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productReadyId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Sudah Ready',
            'description' => 'Produk yang sudah dipilih affiliate.',
            'price' => 42000,
            'stock_qty' => 10,
            'image_url' => null,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 10,
            'affiliate_expire_date' => now()->addDays(7)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Belum Ready',
            'description' => 'Belum masuk daftar siap dipasarkan.',
            'price' => 33000,
            'stock_qty' => 10,
            'image_url' => null,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 8,
            'affiliate_expire_date' => now()->addDays(7)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('affiliate_locks')->insert([
            'affiliate_id' => $affiliate->id,
            'product_id' => $productReadyId,
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(29)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($affiliate)->get(route('landing', [
            'source' => 'affiliate',
            'ready_marketing' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('Produk Sudah Ready');
        $response->assertDontSee('Produk Belum Ready');
        $response->assertSee('Sudah Dipasarkan');
        $response->assertSee('Siap Dipasarkan (1)');
    }

    public function test_landing_search_filters_products_by_keyword(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $seller = User::factory()->create(['role' => 'consumer']);

        DB::table('store_products')->insert([
            [
                'mitra_id' => $mitra->id,
                'name' => 'Beras Premium Organik',
                'description' => 'Produk mitra untuk uji keyword.',
                'price' => 48000,
                'stock_qty' => 10,
                'image_url' => null,
                'is_affiliate_enabled' => false,
                'affiliate_commission' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'name' => 'Pupuk NPK',
                'description' => 'Produk berbeda untuk validasi filter.',
                'price' => 52000,
                'stock_qty' => 8,
                'image_url' => null,
                'is_affiliate_enabled' => false,
                'affiliate_commission' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('farmer_harvests')->insert([
            [
                'farmer_id' => $seller->id,
                'name' => 'Jagung Manis',
                'description' => 'Panen reguler.',
                'price' => 18000,
                'stock_qty' => 14,
                'harvest_date' => now()->toDateString(),
                'image_url' => null,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'farmer_id' => $seller->id,
                'name' => 'Beras Medium',
                'description' => 'Panen beras dari penjual.',
                'price' => 32000,
                'stock_qty' => 11,
                'harvest_date' => now()->toDateString(),
                'image_url' => null,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('landing', ['q' => 'beras']));

        $response->assertOk();
        $response->assertSee('Beras Premium Organik');
        $response->assertSee('Beras Medium');
        $response->assertDontSee('Pupuk NPK');
        $response->assertDontSee('Jagung Manis');
    }

    public function test_landing_partial_products_respects_keyword_and_source_filter(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $seller = User::factory()->create(['role' => 'consumer']);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Cabai Merah Mitra',
            'description' => 'Produk mitra untuk uji partial.',
            'price' => 36000,
            'stock_qty' => 9,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('farmer_harvests')->insert([
            'farmer_id' => $seller->id,
            'name' => 'Cabai Merah Penjual',
            'description' => 'Produk penjual untuk uji partial.',
            'price' => 28000,
            'stock_qty' => 9,
            'harvest_date' => now()->toDateString(),
            'image_url' => null,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('landing', [
            'q' => 'cabai',
            'source' => 'seller',
            'partial_products' => 1,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertJsonStructure(['html']);
        $html = (string) $response->json('html');
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('Cabai Merah Penjual', $html);
        $this->assertStringNotContainsString('Cabai Merah Mitra', $html);
    }
}
