<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerOrderSourceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_orders_endpoint_only_returns_farmer_p2p_orders(): void
    {
        $seller = User::factory()->create(['role' => 'consumer']);
        $this->grantSellerMode($seller);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $farmerOrderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'farmer_p2p',
            'total_amount' => 98000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 120000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($seller);

        $response = $this->getJson('/api/seller/orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $farmerOrderId)
            ->assertJsonPath('data.0.order_source', 'farmer_p2p');
    }

    public function test_seller_mark_packed_rejects_non_farmer_p2p_order(): void
    {
        $seller = User::factory()->create(['role' => 'consumer']);
        $this->grantSellerMode($seller);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 99000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($seller);

        $response = $this->postJson('/api/seller/orders/' . $orderId . '/mark-packed');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 'paid',
            'order_source' => 'store_online',
        ]);
    }

    private function grantSellerMode(User $user): void
    {
        DB::table('consumer_profiles')->insert([
            'user_id' => $user->id,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

