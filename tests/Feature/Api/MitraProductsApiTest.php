<?php

namespace Tests\Feature\Api;

use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MitraProductsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_can_get_own_store_products(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $other = User::factory()->create(['role' => 'mitra']);

        StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Saya API',
            'description' => null,
            'price' => 12000,
            'stock_qty' => 7,
            'image_url' => null,
        ]);

        StoreProduct::query()->create([
            'mitra_id' => $other->id,
            'name' => 'Produk Mitra Lain',
            'description' => null,
            'price' => 12000,
            'stock_qty' => 7,
            'image_url' => null,
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/mitra/store-products');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ]);

        $this->assertSame('Produk Saya API', $response->json('data.0.name'));
    }

    public function test_mitra_can_adjust_stock_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $product = StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk API Stok',
            'description' => null,
            'price' => 11000,
            'stock_qty' => 8,
            'image_url' => null,
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/store-products/' . $product->id . '/stock-adjust', [
            'delta' => -3,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stock_qty', 5);

        $this->assertDatabaseHas('store_products', [
            'id' => $product->id,
            'stock_qty' => 5,
        ]);
        $this->assertDatabaseHas('store_product_stock_mutations', [
            'store_product_id' => $product->id,
            'change_type' => 'adjust',
            'qty_before' => 8,
            'qty_delta' => -3,
            'qty_after' => 5,
        ]);
    }

    public function test_mitra_cannot_adjust_stock_of_other_mitra_product(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $other = User::factory()->create(['role' => 'mitra']);

        $product = StoreProduct::query()->create([
            'mitra_id' => $other->id,
            'name' => 'Produk Bukan Milik',
            'description' => null,
            'price' => 11000,
            'stock_qty' => 8,
            'image_url' => null,
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/store-products/' . $product->id . '/stock-adjust', [
            'delta' => 2,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_mitra_can_get_stock_mutation_history_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $product = StoreProduct::query()->create([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Mutasi API',
            'description' => null,
            'price' => 13000,
            'stock_qty' => 9,
            'image_url' => null,
        ]);

        Sanctum::actingAs($mitra);

        $this->postJson('/api/mitra/store-products/' . $product->id . '/stock-adjust', [
            'delta' => 4,
            'note' => 'Penambahan stok gudang',
        ])->assertOk();

        $response = $this->getJson('/api/mitra/store-products/' . $product->id . '/stock-mutations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.qty_after', 13)
            ->assertJsonPath('data.0.note', 'Penambahan stok gudang');
    }
}
