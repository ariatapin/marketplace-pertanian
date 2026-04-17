<?php

namespace Tests\Feature\Seller;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_mode_user_can_open_products_page(): void
    {
        $sellerModeUser = User::factory()->create(['role' => 'consumer']);
        $this->seedSellerMode($sellerModeUser->id);

        $response = $this->actingAs($sellerModeUser)->get(route('seller.products.index'));

        $response->assertOk();
        $response->assertSee('Produk Hasil Tani');
        $response->assertSee('Tambah Produk');

        $createResponse = $this->actingAs($sellerModeUser)->get(route('seller.products.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('Input Produk Hasil Tani Baru');
    }

    public function test_seller_mode_user_can_create_update_and_delete_own_product(): void
    {
        $sellerModeUser = User::factory()->create(['role' => 'consumer']);
        $this->seedSellerMode($sellerModeUser->id);

        $this->actingAs($sellerModeUser)
            ->post(route('seller.products.store'), [
                'name' => 'Cabai Rawit Merah',
                'description' => 'Panen lokal minggu ini.',
                'price' => 30000,
                'stock_qty' => 80,
                'harvest_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('farmer_harvests', [
            'farmer_id' => $sellerModeUser->id,
            'name' => 'Cabai Rawit Merah',
            'status' => 'approved',
        ]);

        $productId = (int) DB::table('farmer_harvests')
            ->where('farmer_id', $sellerModeUser->id)
            ->value('id');

        $this->actingAs($sellerModeUser)
            ->patch(route('seller.products.update', $productId), [
                'name' => 'Cabai Rawit Merah Super',
                'description' => 'Panen lokal kualitas premium.',
                'price' => 35000,
                'stock_qty' => 60,
                'harvest_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('farmer_harvests', [
            'id' => $productId,
            'farmer_id' => $sellerModeUser->id,
            'name' => 'Cabai Rawit Merah Super',
            'price' => 35000,
            'stock_qty' => 60,
        ]);

        $this->actingAs($sellerModeUser)
            ->delete(route('seller.products.destroy', $productId))
            ->assertRedirect();

        $this->assertDatabaseMissing('farmer_harvests', [
            'id' => $productId,
        ]);
    }

    public function test_non_seller_mode_user_cannot_access_seller_products_page(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($buyer)
            ->get(route('seller.products.index'))
            ->assertForbidden();
    }

    private function seedSellerMode(int $userId): void
    {
        DB::table('consumer_profiles')->insert([
            'user_id' => $userId,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
