<?php

namespace Tests\Feature\Consumer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DisputeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_open_dispute_from_orders_page(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 120000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'shipped',
            'payment_proof_url' => 'proof/order-1.jpg',
            'paid_amount' => 120000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'shipped',
            'resi_number' => 'RESI-TEST-01',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($buyer)
            ->from(route('orders.mine'))
            ->post(route('orders.disputes.store', ['orderId' => $orderId]), [
                'category' => 'wrong_item',
                'description' => 'Produk yang diterima tidak sesuai deskripsi.',
            ]);

        $response->assertRedirect(route('orders.mine'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('disputes', [
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'category' => 'wrong_item',
        ]);
    }

    public function test_consumer_cannot_open_duplicate_dispute_for_same_order(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 70000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => 'proof/order-2.jpg',
            'paid_amount' => 70000,
            'payment_submitted_at' => now()->subHours(12),
            'shipping_status' => 'delivered',
            'resi_number' => 'RESI-TEST-02',
            'created_at' => now()->subHours(12),
            'updated_at' => now()->subHours(12),
        ]);

        DB::table('disputes')->insert([
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'opened_by' => $buyer->id,
            'category' => 'damaged',
            'description' => 'Barang rusak.',
            'status' => 'pending',
            'handled_by' => null,
            'handled_at' => null,
            'resolution' => null,
            'resolution_notes' => null,
            'evidence_urls' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($buyer)
            ->from(route('orders.mine'))
            ->post(route('orders.disputes.store', ['orderId' => $orderId]), [
                'category' => 'wrong_item',
                'description' => 'Percobaan sengketa kedua.',
            ]);

        $response->assertRedirect(route('orders.mine'));
        $response->assertSessionHasErrors('dispute');
    }
}

