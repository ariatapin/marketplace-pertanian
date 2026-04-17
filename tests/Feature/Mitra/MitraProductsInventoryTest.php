<?php

namespace Tests\Feature\Mitra;

use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MitraProductsInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_can_filter_products_by_stock_status(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Aman',
            'description' => null,
            'price' => 10000,
            'stock_qty' => 20,
            'image_url' => null,
        ]);

        StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Menipis',
            'description' => null,
            'price' => 10000,
            'stock_qty' => 5,
            'image_url' => null,
        ]);

        $response = $this->actingAs($mitra)
            ->get(route('mitra.products.index', ['stock' => 'low_stock']));

        $response->assertOk();
        $response->assertSee('Produk Menipis');
        $response->assertDontSee('Produk Aman');
    }

    public function test_mitra_can_adjust_product_stock_from_index_page(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $product = StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Stok',
            'description' => null,
            'price' => 10000,
            'stock_qty' => 10,
            'image_url' => null,
        ]);

        $response = $this->actingAs($mitra)
            ->post(route('mitra.products.adjustStock', ['product' => $product->id]), [
                'delta' => 3,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('store_products', [
            'id' => $product->id,
            'stock_qty' => 13,
        ]);
        $this->assertDatabaseHas('store_product_stock_mutations', [
            'store_product_id' => $product->id,
            'change_type' => 'adjust',
            'qty_before' => 10,
            'qty_delta' => 3,
            'qty_after' => 13,
        ]);
    }

    public function test_adjust_stock_rejects_negative_result(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $product = StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Minus',
            'description' => null,
            'price' => 10000,
            'stock_qty' => 2,
            'image_url' => null,
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.products.index'))
            ->post(route('mitra.products.adjustStock', ['product' => $product->id]), [
                'delta' => -10,
            ]);

        $response->assertRedirect(route('mitra.products.index'));
        $response->assertSessionHasErrors('delta');

        $this->assertDatabaseHas('store_products', [
            'id' => $product->id,
            'stock_qty' => 2,
        ]);
    }

    public function test_mitra_can_open_stock_history_page(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $product = StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Histori',
            'description' => null,
            'price' => 10000,
            'stock_qty' => 15,
            'image_url' => null,
        ]);

        $this->actingAs($mitra)->post(route('mitra.products.adjustStock', ['product' => $product->id]), [
            'delta' => -5,
            'note' => 'Koreksi stok fisik',
        ]);

        $response = $this->actingAs($mitra)->get(route('mitra.products.stockHistory', ['product' => $product->id]));

        $response->assertOk();
        $response->assertSee('Riwayat Mutasi Stok Produk');
        $response->assertSee('Koreksi stok fisik');
    }
}
