<?php

namespace Tests\Feature\Seller;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerConsumerBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_cannot_add_own_farmer_product_to_cart(): void
    {
        $sellerModeUser = User::factory()->create(['role' => 'consumer']);

        $harvestId = DB::table('farmer_harvests')->insertGetId([
            'farmer_id' => $sellerModeUser->id,
            'name' => 'Cabai Rawit',
            'description' => 'Produk hasil tani sendiri.',
            'price' => 25000,
            'stock_qty' => 20,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sellerModeUser)
            ->from(route('landing'))
            ->post(route('cart.store'), [
                'product_id' => $harvestId,
                'product_type' => 'farmer',
                'qty' => 1,
            ])
            ->assertRedirect(route('landing'))
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_rejects_self_owned_farmer_item_from_existing_cart(): void
    {
        $sellerModeUser = User::factory()->create(['role' => 'consumer']);
        DB::table('consumer_profiles')->insert([
            'user_id' => $sellerModeUser->id,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $harvestId = DB::table('farmer_harvests')->insertGetId([
            'farmer_id' => $sellerModeUser->id,
            'name' => 'Tomat Cherry',
            'description' => 'Produk hasil tani sendiri.',
            'price' => 18000,
            'stock_qty' => 15,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cart_items')->insert([
            'user_id' => $sellerModeUser->id,
            'product_type' => 'farmer',
            'product_id' => $harvestId,
            'qty' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($sellerModeUser)
            ->from(route('cart.index'))
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('product');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_legacy_farmer_seller_role_is_normalized_to_consumer_on_request(): void
    {
        $legacySeller = User::factory()->create([
            'role' => 'farmer_seller',
        ]);

        $this->actingAs($legacySeller)
            ->get(route('cart.index'))
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $legacySeller->id,
            'role' => 'consumer',
        ]);
        $this->assertDatabaseHas('consumer_profiles', [
            'user_id' => $legacySeller->id,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
        ]);
    }
}
