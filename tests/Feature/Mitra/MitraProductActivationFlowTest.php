<?php

namespace Tests\Feature\Mitra;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MitraProductActivationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_created_product_cannot_use_admin_activation_flow(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $storeProductId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Mitra Sendiri',
            'description' => 'Draft produk buatan mitra.',
            'price' => 30000,
            'unit' => 'kg',
            'stock_qty' => 25,
            'image_url' => 'products/main-image.jpg',
            'is_active' => false,
            'source_admin_product_id' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'affiliate_expire_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_product_images')->insert([
            [
                'store_product_id' => $storeProductId,
                'image_url' => 'products/gallery-1.jpg',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'store_product_id' => $storeProductId,
                'image_url' => 'products/gallery-2.jpg',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.products.index'))
            ->post(route('mitra.products.activateListing', ['product' => $storeProductId]), [
                'name' => 'Produk Mitra Sendiri',
                'description' => 'Produk mitra yang tidak boleh lewat jalur aktivasi pengadaan admin.',
                'price' => 32000,
                'unit' => 'kg',
                'stock_qty' => 30,
                'is_affiliate_enabled' => 0,
            ]);

        $response->assertRedirect(route('mitra.products.index'));
        $response->assertSessionHasErrors('product');
        $this->assertDatabaseHas('store_products', [
            'id' => $storeProductId,
            'is_active' => false,
            'price' => 30000,
        ]);
    }

    public function test_admin_procured_product_activation_rejects_price_below_admin_margin_floor(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Produk Pengadaan',
            'description' => 'Produk dari pengadaan admin.',
            'price' => 20000,
            'min_order_qty' => 1,
            'stock_qty' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storeProductId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Pengadaan',
            'description' => 'Draft produk pengadaan.',
            'price' => 20000,
            'unit' => 'kg',
            'stock_qty' => 25,
            'image_url' => 'products/main-image.jpg',
            'is_active' => false,
            'source_admin_product_id' => $adminProductId,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'affiliate_expire_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_product_images')->insert([
            [
                'store_product_id' => $storeProductId,
                'image_url' => 'products/gallery-1.jpg',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'store_product_id' => $storeProductId,
                'image_url' => 'products/gallery-2.jpg',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.products.index'))
            ->post(route('mitra.products.activateListing', ['product' => $storeProductId]), [
                'name' => 'Produk Pengadaan Aktif',
                'description' => 'Produk ini siap dijual di marketplace mitra.',
                'price' => 20999,
                'unit' => 'kg',
                'stock_qty' => 30,
                'is_affiliate_enabled' => 0,
            ]);

        $response->assertRedirect(route('mitra.products.index'));
        $response->assertSessionHasErrors('price');

        $this->assertDatabaseHas('store_products', [
            'id' => $storeProductId,
            'price' => 20000,
            'is_active' => false,
        ]);
    }

    public function test_admin_procured_product_activation_updates_price_without_affiliate_commission(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Produk Pengadaan',
            'description' => 'Produk dari pengadaan admin.',
            'price' => 50000,
            'min_order_qty' => 1,
            'stock_qty' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storeProductId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Pengadaan',
            'description' => 'Draft produk pengadaan.',
            'price' => 50000,
            'unit' => 'kg',
            'stock_qty' => 25,
            'image_url' => 'products/main-image.jpg',
            'is_active' => false,
            'source_admin_product_id' => $adminProductId,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'affiliate_expire_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_product_images')->insert([
            [
                'store_product_id' => $storeProductId,
                'image_url' => 'products/gallery-1.jpg',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'store_product_id' => $storeProductId,
                'image_url' => 'products/gallery-2.jpg',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($mitra)
            ->post(route('mitra.products.activateListing', ['product' => $storeProductId]), [
                'name' => 'Produk Pengadaan Aktif',
                'description' => 'Produk ini siap dijual di marketplace mitra.',
                'price' => 72500,
                'unit' => 'kg',
                'stock_qty' => 30,
                'is_affiliate_enabled' => 0,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('store_products', [
            'id' => $storeProductId,
            'price' => 72500,
            'stock_qty' => 30,
            'is_active' => true,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
        ]);
    }
}
