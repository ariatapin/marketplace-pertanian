<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutBankTransferFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_checkout_and_upload_bank_transfer_proof(): void
    {
        Storage::fake('public');

        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedConsumerMode($consumer->id, 'farmer_seller');

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Bibit Cabai',
            'description' => 'Bibit cabai merah premium.',
            'price' => 35000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 2,
        ])->assertRedirect();

        $this->actingAs($consumer)
            ->post(route('checkout'))
            ->assertRedirect(route('orders.mine'));

        $order = DB::table('orders')->where('buyer_id', $consumer->id)->first();

        $this->assertNotNull($order);
        $this->assertSame('pending_payment', $order->order_status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $consumer->id,
        ]);
        $this->assertDatabaseHas('store_products', [
            'id' => $productId,
            'stock_qty' => 18,
        ]);

        $this->actingAs($consumer)->post(route('orders.transfer-proof', ['orderId' => $order->id]), [
            'paid_amount' => 70000,
            'proof' => UploadedFile::fake()->createWithContent('proof.jpg', 'dummy-proof'),
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'bank_transfer',
            'paid_amount' => 70000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
        ]);

        $this->assertNotNull(DB::table('orders')->where('id', $order->id)->value('payment_proof_url'));
    }

    public function test_consumer_store_checkout_with_wallet_method_is_paid_automatically(): void
    {
        Storage::fake('public');

        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedConsumerMode($consumer->id, 'affiliate');

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Pupuk Organik',
            'description' => 'Pupuk organik granul.',
            'price' => 45000,
            'stock_qty' => 10,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 2,
        ])->assertRedirect();

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $consumer->id,
            'amount' => 200000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:wallet-topup:' . uniqid(),
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Topup untuk uji wallet checkout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->post(route('checkout'), [
                'payment_method' => 'gopay',
            ])
            ->assertRedirect(route('orders.mine'));

        $order = DB::table('orders')->where('buyer_id', $consumer->id)->first();

        $this->assertNotNull($order);
        $this->assertSame('gopay', $order->payment_method);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'gopay',
            'paid_amount' => 90000,
            'payment_status' => 'paid',
            'order_status' => 'packed',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $consumer->id,
            'transaction_type' => 'order_payment_wallet',
            'reference_order_id' => $order->id,
            'amount' => -90000,
        ]);
        $this->assertDatabaseHas('store_products', [
            'id' => $productId,
            'stock_qty' => 8,
        ]);
    }

    private function seedApprovedConsumerMode(int $userId, string $mode): void
    {
        DB::table('consumer_profiles')->insert([
            'user_id' => $userId,
            'address' => null,
            'mode' => $mode,
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
