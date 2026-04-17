<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutWalletAcidTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_p2p_wallet_checkout_credits_seller_revenue(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'consumer']);

        $harvestId = DB::table('farmer_harvests')->insertGetId([
            'farmer_id' => $seller->id,
            'name' => 'Cabai Merah Organik',
            'description' => 'Panen petani untuk uji wallet checkout.',
            'price' => 28000,
            'stock_qty' => 20,
            'harvest_date' => now()->toDateString(),
            'image_url' => null,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $buyer->id,
            'amount' => 200000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => "test:checkout:wallet:p2p:buyer:{$buyer->id}",
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo awal buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->post(route('cart.store'), [
                'product_id' => $harvestId,
                'product_type' => 'farmer',
                'qty' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('checkout'), [
                'payment_method' => 'gopay',
            ])
            ->assertRedirect(route('orders.mine'));

        $orderId = (int) DB::table('orders')
            ->where('buyer_id', $buyer->id)
            ->where('seller_id', $seller->id)
            ->where('order_source', 'farmer_p2p')
            ->value('id');

        $this->assertGreaterThan(0, $orderId);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_method' => 'gopay',
            'payment_status' => 'paid',
            'order_status' => 'paid',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $buyer->id,
            'transaction_type' => 'order_payment_wallet',
            'reference_order_id' => $orderId,
            'amount' => -56000,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $seller->id,
            'transaction_type' => 'sale_revenue',
            'reference_order_id' => $orderId,
            'amount' => 56000,
        ]);
    }

    public function test_wallet_checkout_rejects_when_balance_reserved_by_withdraw_request(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $seller->id,
            'name' => 'Produk Mitra Checkout Wallet',
            'description' => 'Produk untuk uji saldo ter-reservasi.',
            'price' => 30000,
            'stock_qty' => 10,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $buyer->id,
            'amount' => 100000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => "test:checkout:wallet:reserved:buyer:{$buyer->id}",
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo awal buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdraw_requests')->insert([
            'user_id' => $buyer->id,
            'amount' => 85000,
            'bank_name' => 'BCA',
            'account_number' => '123456',
            'account_holder' => 'Buyer Demo',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->post(route('cart.store'), [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->from(route('cart.index'))
            ->post(route('checkout'), [
                'payment_method' => 'gopay',
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, DB::table('orders')->where('buyer_id', $buyer->id)->count());
        $this->assertSame(0, DB::table('wallet_transactions')
            ->where('wallet_id', $buyer->id)
            ->where('transaction_type', 'order_payment_wallet')
            ->count());
    }
}

