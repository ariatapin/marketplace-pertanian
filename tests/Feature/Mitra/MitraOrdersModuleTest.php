<?php

namespace Tests\Feature\Mitra;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MitraOrdersModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_can_open_orders_page_and_see_owned_orders(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $otherMitra = User::factory()->create(['role' => 'mitra']);

        $myOrderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 75000,
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
            'seller_id' => $otherMitra->id,
            'order_source' => 'store_online',
            'total_amount' => 88000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)->get(route('mitra.orders.index'));

        $response->assertOk();
        $response->assertSee('Daftar Order Customer');
        $response->assertSee('#' . $myOrderId);
    }

    public function test_mitra_can_mark_paid_order_to_packed_from_orders_page(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
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

        $response = $this->actingAs($mitra)
            ->post(route('mitra.orders.markPacked', ['orderId' => $orderId]));

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 'packed',
        ]);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $orderId,
            'status' => 'pending',
        ]);
    }

    public function test_mitra_transfer_verification_moves_order_directly_to_packed(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 125000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => 'storage/payment_proofs/orders/demo.jpg',
            'paid_amount' => 125000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->post(route('mitra.orders.markPaid', ['orderId' => $orderId]));

        $response->assertRedirect();

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

    public function test_mitra_cannot_verify_non_transfer_payment_method_from_orders_page(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 125000,
            'payment_method' => 'gopay',
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => 'storage/payment_proofs/orders/demo.jpg',
            'paid_amount' => 125000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.orders.index'))
            ->post(route('mitra.orders.markPaid', ['orderId' => $orderId]));

        $response->assertRedirect(route('mitra.orders.index'));
        $response->assertSessionHasErrors('payment_method');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_method' => 'gopay',
        ]);
    }

    public function test_mitra_can_mark_packed_order_to_shipped_with_resi(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 99000,
            'payment_status' => 'paid',
            'order_status' => 'packed',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->post(route('mitra.orders.markShipped', ['orderId' => $orderId]), [
                'resi_number' => 'RESI-12345',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 'shipped',
            'shipping_status' => 'shipped',
            'resi_number' => 'RESI-12345',
        ]);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $orderId,
            'status' => 'shipped',
            'tracking_number' => 'RESI-12345',
        ]);
    }

    public function test_mitra_can_open_order_detail_page_with_items(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 150000,
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
            'product_id' => 101,
            'product_name' => 'Cabai Merah',
            'qty' => 3,
            'price_per_unit' => 50000,
            'affiliate_id' => null,
            'commission_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)->get(route('mitra.orders.show', ['orderId' => $orderId]));

        $response->assertOk();
        $response->assertSee('Order #' . $orderId);
        $response->assertSee('Cabai Merah');
    }

    public function test_mitra_cannot_open_order_detail_of_other_seller(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $otherSeller = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $otherSeller->id,
            'order_source' => 'store_online',
            'total_amount' => 64000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)->get(route('mitra.orders.show', ['orderId' => $orderId]));

        $response->assertForbidden();
    }

    public function test_mitra_cannot_process_non_store_online_order(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'farmer_p2p',
            'total_amount' => 70000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->post(route('mitra.orders.markPacked', ['orderId' => $orderId]));

        $response->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'order_status' => 'paid',
            'order_source' => 'farmer_p2p',
        ]);
    }
}
