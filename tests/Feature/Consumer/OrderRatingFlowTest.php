<?php

namespace Tests\Feature\Consumer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderRatingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_submit_rating_once_for_completed_order_and_cannot_update_again(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 140000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => 'proof/order-rating-1.jpg',
            'paid_amount' => 140000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'delivered',
            'resi_number' => 'RESI-RATING-001',
            'completed_at' => now()->subHours(12),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subHours(2),
        ]);

        $this->actingAs($buyer)
            ->from(route('orders.mine'))
            ->post(route('orders.rating.store', ['orderId' => $orderId]), [
                'score' => 5,
                'review' => 'Pengiriman cepat dan sesuai deskripsi.',
            ])
            ->assertRedirect(route('orders.mine'));

        $this->assertDatabaseHas('user_ratings', [
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'rated_user_id' => $seller->id,
            'score' => 5,
            'review' => 'Pengiriman cepat dan sesuai deskripsi.',
        ]);

        $this->actingAs($buyer)
            ->from(route('orders.mine'))
            ->post(route('orders.rating.store', ['orderId' => $orderId]), [
                'score' => 3,
                'review' => 'Update ulasan: pengiriman agak telat, tapi barang aman.',
            ])
            ->assertRedirect(route('orders.mine'))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseHas('user_ratings', [
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'rated_user_id' => $seller->id,
            'score' => 5,
            'review' => 'Pengiriman cepat dan sesuai deskripsi.',
        ]);

        $this->assertSame(
            1,
            DB::table('user_ratings')
                ->where('order_id', $orderId)
                ->where('buyer_id', $buyer->id)
                ->count()
        );
    }

    public function test_consumer_cannot_submit_rating_for_non_completed_order(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 90000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'shipped',
            'payment_proof_url' => 'proof/order-rating-2.jpg',
            'paid_amount' => 90000,
            'payment_submitted_at' => now()->subHours(8),
            'shipping_status' => 'shipped',
            'resi_number' => 'RESI-RATING-002',
            'created_at' => now()->subHours(10),
            'updated_at' => now()->subHours(4),
        ]);

        $this->actingAs($buyer)
            ->from(route('orders.mine'))
            ->post(route('orders.rating.store', ['orderId' => $orderId]), [
                'score' => 4,
                'review' => 'Belum selesai tapi dicoba rating.',
            ])
            ->assertRedirect(route('orders.mine'))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseMissing('user_ratings', [
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'rated_user_id' => $seller->id,
        ]);
    }

    public function test_consumer_cannot_submit_rating_after_seven_days_from_completed_time(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 75000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => 'proof/order-rating-3.jpg',
            'paid_amount' => 75000,
            'payment_submitted_at' => now()->subDays(10),
            'shipping_status' => 'delivered',
            'resi_number' => 'RESI-RATING-003',
            'completed_at' => now()->subDays(8),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(8),
        ]);

        $this->actingAs($buyer)
            ->from(route('orders.mine'))
            ->post(route('orders.rating.store', ['orderId' => $orderId]), [
                'score' => 5,
                'review' => 'Coba rating terlambat.',
            ])
            ->assertRedirect(route('orders.mine'))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseMissing('user_ratings', [
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'rated_user_id' => $seller->id,
        ]);
    }
}
