<?php

namespace Tests\Feature\Mitra;

use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreProductPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_mitra_cannot_edit_other_mitra_product(): void
    {
        $owner = User::factory()->create(['role' => 'mitra']);
        $otherMitra = User::factory()->create(['role' => 'mitra']);

        $product = StoreProduct::query()->create([
            'mitra_id' => $owner->id,
            'name' => 'Produk Pemilik',
            'description' => 'Produk milik mitra lain',
            'price' => 25000,
            'stock_qty' => 7,
            'image_url' => null,
        ]);

        $response = $this->actingAs($otherMitra)->get(route('mitra.products.edit', ['product' => $product->id]));

        $response->assertForbidden();
    }

    public function test_owner_mitra_can_open_edit_page_for_own_product(): void
    {
        $owner = User::factory()->create(['role' => 'mitra']);

        $product = StoreProduct::query()->create([
            'mitra_id' => $owner->id,
            'name' => 'Produk Saya',
            'description' => null,
            'price' => 18000,
            'stock_qty' => 12,
            'image_url' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('mitra.products.edit', ['product' => $product->id]));

        $response->assertOk();
        $response->assertSee('Produk Saya');
    }
}

