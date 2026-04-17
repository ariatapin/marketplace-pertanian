<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AffiliateReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceProductDetailFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_open_product_detail_page_and_checkout_actions_are_visible(): void
    {
        $buyer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $mitra = User::factory()->create([
            'role' => 'mitra',
            'name' => 'Mitra Uji Produk',
        ]);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Detail Uji',
            'description' => 'Deskripsi lengkap produk untuk halaman detail.',
            'price' => 42000,
            'stock_qty' => 10,
            'image_url' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('store_products')->where('name', 'Produk Detail Uji')->value('id');

        $response = $this->actingAs($buyer)->get(route('marketplace.product.show', [
            'productType' => 'store',
            'productId' => $productId,
        ]));

        $response->assertOk();
        $response->assertSee('Produk Detail Uji');
        $response->assertSee('Beli Sekarang');
        $response->assertSee('Masukkan Keranjang');
        $response->assertSee('Produk');
        $response->assertSee('Bergabung');
        $response->assertSee('Kunjungi Toko');
        $response->assertSee('Deskripsi Produk');
        $response->assertSee('Laporkan');
    }

    public function test_guest_detail_page_shows_login_register_only_and_redirect_links(): void
    {
        $mitra = User::factory()->create([
            'role' => 'mitra',
            'name' => 'Mitra Guest Detail',
        ]);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Guest Detail',
            'description' => 'Produk untuk validasi CTA guest.',
            'price' => 39000,
            'stock_qty' => 6,
            'image_url' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('store_products')->where('name', 'Produk Guest Detail')->value('id');
        $detailUrl = route('marketplace.product.show', [
            'productType' => 'store',
            'productId' => $productId,
        ]);

        $response = $this->get($detailUrl);

        $response->assertOk();
        $response->assertDontSee('Beli Sekarang');
        $response->assertDontSee('Masukkan Keranjang');
        $response->assertSee('Login');
        $response->assertSee('Daftar');
        $response->assertSee(route('login', ['redirect' => $detailUrl]), false);
        $response->assertSee(route('register', ['redirect' => $detailUrl]), false);
    }

    public function test_referral_from_product_detail_is_captured_and_applied_after_login(): void
    {
        $affiliate = User::factory()->create([
            'role' => 'consumer',
            'email' => 'affiliate.detail@example.test',
        ]);
        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $buyer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'buyer.detail@example.test',
        ]);

        $mitra = User::factory()->create([
            'role' => 'mitra',
            'name' => 'Mitra Referral Detail',
        ]);

        $productId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Referral Detail',
            'description' => 'Produk untuk validasi capture referral detail.',
            'price' => 27000,
            'stock_qty' => 12,
            'image_url' => null,
            'is_active' => true,
            'is_affiliate_enabled' => true,
            'affiliate_expire_date' => now()->addDays(15)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refCode = app(AffiliateReferralService::class)->encodeReferralCode((int) $affiliate->id);
        $detailUrl = route('marketplace.product.show', [
            'productType' => 'store',
            'productId' => $productId,
            'ref' => $refCode,
            'source' => 'affiliate',
        ]);

        $this->get($detailUrl)->assertOk();

        $this->post('/login', [
            'email' => $buyer->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->post(route('cart.store'), [
            'product_id' => $productId,
            'product_type' => 'store',
            'qty' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_type' => 'store',
            'product_id' => $productId,
            'affiliate_referral_id' => $affiliate->id,
        ]);
    }

    public function test_consumer_can_report_product_and_admin_can_see_it_in_reports_module(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
            'name' => 'Pelapor Product',
            'email' => 'pelapor.product@example.test',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $mitra = User::factory()->create([
            'role' => 'mitra',
            'name' => 'Mitra Target Laporan',
        ]);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Dilaporkan',
            'description' => 'Produk target laporan user.',
            'price' => 35000,
            'stock_qty' => 8,
            'image_url' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('store_products')->where('name', 'Produk Dilaporkan')->value('id');

        $this->actingAs($consumer)
            ->post(route('marketplace.product.report', [
                'productType' => 'store',
                'productId' => $productId,
            ]), [
                'category' => 'misleading_info',
                'description' => 'Informasi produk tidak sesuai foto dan deskripsi awal.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_reports', [
            'product_type' => 'store',
            'product_id' => $productId,
            'reporter_id' => $consumer->id,
            'reported_user_id' => $mitra->id,
            'category' => 'misleading_info',
            'status' => 'pending',
        ]);

        $reportId = (int) DB::table('product_reports')
            ->where('product_id', $productId)
            ->value('id');

        $reportsPage = $this->actingAs($admin)->get(route('admin.modules.reports'));
        $reportsPage->assertOk();
        $reportsPage->assertSee('Laporan Produk Marketplace');
        $reportsPage->assertSee('Produk Dilaporkan');

        $detailPage = $this->actingAs($admin)->get(route('admin.modules.reports.products.show', [
            'productReportId' => $reportId,
        ]));
        $detailPage->assertOk();
        $detailPage->assertSee('Detail Laporan Produk');
        $detailPage->assertSee('Produk Dilaporkan');

        $this->actingAs($admin)
            ->post(route('admin.modules.reports.products.review', [
                'productReportId' => $reportId,
            ]), [
                'status' => 'under_review',
                'resolution_notes' => 'Akan diverifikasi lebih lanjut.',
            ])
            ->assertRedirect(route('admin.modules.reports.products.show', ['productReportId' => $reportId]));

        $this->assertDatabaseHas('product_reports', [
            'id' => $reportId,
            'status' => 'under_review',
            'handled_by' => $admin->id,
        ]);
    }

    public function test_store_page_and_related_products_only_show_active_mitra_products(): void
    {
        $buyer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $mitra = User::factory()->create([
            'role' => 'mitra',
            'name' => 'Mitra Filter Aktif',
        ]);

        $currentProductId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Utama Aktif',
            'description' => 'Produk utama untuk buka detail.',
            'price' => 25000,
            'stock_qty' => 12,
            'image_url' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Lain Aktif',
            'description' => 'Masih aktif dan boleh tampil.',
            'price' => 30000,
            'stock_qty' => 5,
            'image_url' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_products')->insert([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Tersembunyi Nonaktif',
            'description' => 'Produk ini harus tersembunyi dari halaman publik.',
            'price' => 45000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storePage = $this->get(route('marketplace.store.show', [
            'sellerType' => 'mitra',
            'sellerId' => $mitra->id,
        ]));

        $storePage->assertOk();
        $storePage->assertSee('Produk Utama Aktif');
        $storePage->assertSee('Produk Lain Aktif');
        $storePage->assertDontSee('Produk Tersembunyi Nonaktif');

        $detailPage = $this->actingAs($buyer)->get(route('marketplace.product.show', [
            'productType' => 'store',
            'productId' => (int) $currentProductId,
        ]));

        $detailPage->assertOk();
        $detailPage->assertSee('Produk Lain dari Toko Ini');
        $detailPage->assertSee('Produk Lain Aktif');
        $detailPage->assertDontSee('Produk Tersembunyi Nonaktif');
    }
}
