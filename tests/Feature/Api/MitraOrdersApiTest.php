<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MitraOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_can_fetch_own_orders_from_mitra_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        DB::table('orders')->insert([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 81000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/mitra/orders');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'errors',
            ]);
    }

    public function test_mitra_mark_packed_invalid_state_returns_validation_contract(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 81000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/orders/' . $orderId . '/mark-packed');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
            ])
            ->assertJsonStructure([
                'errors' => ['order_status'],
            ]);
    }

    public function test_mitra_can_verify_transfer_payment_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 81000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => 'storage/payment_proofs/orders/demo.jpg',
            'paid_amount' => 81000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/orders/' . $orderId . '/mark-paid');

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.order_status', 'packed')
            ->assertJsonPath('data.verification_status', 'verified_by_seller');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'paid',
            'order_status' => 'packed',
        ]);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $orderId,
            'status' => 'pending',
        ]);
    }

    public function test_mitra_can_get_order_detail_with_items_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 90000,
            'payment_status' => 'paid',
            'order_status' => 'packed',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => 77,
            'product_name' => 'Tomat Segar',
            'qty' => 2,
            'price_per_unit' => 45000,
            'affiliate_id' => null,
            'commission_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/mitra/orders/' . $orderId);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.order.id', $orderId)
            ->assertJsonPath('data.items.0.product_name', 'Tomat Segar')
            ->assertJsonPath('data.summary.total_qty', 2);
    }

    public function test_mitra_cannot_get_order_detail_of_other_seller_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $otherSeller = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $otherSeller->id,
            'order_source' => 'store_online',
            'total_amount' => 90000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/mitra/orders/' . $orderId);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_mitra_mark_paid_is_forbidden_for_non_store_online_order(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'farmer_p2p',
            'total_amount' => 81000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => 'storage/payment_proofs/orders/demo.jpg',
            'paid_amount' => 81000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/orders/' . $orderId . '/mark-paid');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'data' => null,
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'order_source' => 'farmer_p2p',
        ]);
    }

    public function test_mitra_mark_paid_returns_validation_error_for_non_transfer_payment_method(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 81000,
            'payment_method' => 'gopay',
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => 'storage/payment_proofs/orders/demo.jpg',
            'paid_amount' => 81000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/orders/' . $orderId . '/mark-paid');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
            ])
            ->assertJsonStructure([
                'errors' => ['payment_method'],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_method' => 'gopay',
        ]);
    }
}
