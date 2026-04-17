<?php

namespace Tests\Feature\Mitra;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MitraProductEditFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_procured_product_edit_cannot_change_listing_status_directly(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Produk Pengadaan',
            'description' => 'Produk dari pengadaan admin.',
            'price' => 18000,
            'min_order_qty' => 1,
            'stock_qty' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Mitra Aktif',
            'description' => 'Produk untuk validasi pemisahan edit dan aktivasi.',
            'price' => 25000,
            'unit' => 'kg',
            'stock_qty' => 40,
            'image_url' => 'products/demo.jpg',
            'is_active' => true,
            'source_admin_product_id' => $adminProductId,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'affiliate_expire_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.products.edit', ['product' => $productId]))
            ->put(route('mitra.products.update', ['product' => $productId]), [
                'name' => 'Produk Mitra Aktif (Edit)',
                'description' => 'Update deskripsi dari form edit.',
                'price' => 27000,
                'unit' => 'kg',
                'stock_qty' => 35,
                'is_active' => 0,
                'is_affiliate_enabled' => 0,
            ]);

        $response->assertRedirect(route('mitra.products.edit', ['product' => $productId]));
        $response->assertSessionHasErrors('is_active');

        $this->assertDatabaseHas('store_products', [
            'id' => $productId,
            'name' => 'Produk Mitra Aktif',
            'is_active' => true,
        ]);
    }

    public function test_self_created_product_can_change_listing_status_from_edit_form(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Mitra Sendiri',
            'description' => 'Produk mitra nonaktif.',
            'price' => 22000,
            'unit' => 'kg',
            'stock_qty' => 30,
            'image_url' => 'products/self.jpg',
            'is_active' => false,
            'source_admin_product_id' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'affiliate_expire_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->put(route('mitra.products.update', ['product' => $productId]), [
                'name' => 'Produk Mitra Sendiri',
                'description' => 'Produk mitra nonaktif.',
                'price' => 23000,
                'unit' => 'kg',
                'stock_qty' => 32,
                'is_active' => 1,
                'is_affiliate_enabled' => 0,
            ]);

        $response->assertRedirect(route('mitra.products.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('store_products', [
            'id' => $productId,
            'is_active' => true,
            'price' => 23000,
            'stock_qty' => 32,
        ]);
    }

    public function test_store_product_rejects_source_admin_product_id_for_manual_mitra_product(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Produk Admin',
            'description' => 'Produk pengadaan admin.',
            'price' => 15000,
            'min_order_qty' => 1,
            'stock_qty' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.products.create'))
            ->post(route('mitra.products.store'), [
                'name' => 'Produk Mitra Baru',
                'description' => 'Produk ini ditambah manual oleh Mitra.',
                'price' => 21000,
                'unit' => 'kg',
                'stock_qty' => 20,
                'is_active' => 0,
                'is_affiliate_enabled' => 0,
                'source_admin_product_id' => $adminProductId,
            ]);

        $response->assertRedirect(route('mitra.products.create'));
        $response->assertSessionHasErrors('source_admin_product_id');
        $this->assertDatabaseMissing('store_products', [
            'mitra_id' => $mitra->id,
            'name' => 'Produk Mitra Baru',
        ]);
    }
}
