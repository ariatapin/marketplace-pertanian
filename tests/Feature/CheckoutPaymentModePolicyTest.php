<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutPaymentModePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_cannot_access_consumer_checkout_route(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $this->actingAs($mitra)
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_access_consumer_checkout_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertForbidden();
    }

    public function test_regular_buyer_consumer_can_checkout_with_active_payment_method(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Buyer Belum Aktif',
            'description' => 'Produk untuk verifikasi policy checkout buyer.',
            'price' => 45000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($consumer)
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('orders.mine'));

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $consumer->id,
            'payment_method' => 'bank_transfer',
            'order_status' => 'pending_payment',
        ]);
    }

    public function test_affiliate_mode_consumer_cannot_checkout_with_bank_transfer(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Bibit Padi',
            'description' => 'Bibit padi unggul.',
            'price' => 50000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($consumer)
            ->from(route('landing'))
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('landing'))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_affiliate_mode_consumer_with_whitespace_role_cannot_checkout_with_bank_transfer(): void
    {
        $consumer = User::factory()->create(['role' => ' Consumer ']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Bibit Jagung',
            'description' => 'Bibit jagung unggulan.',
            'price' => 55000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($consumer)
            ->from(route('landing'))
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('landing'))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_affiliate_mode_consumer_can_checkout_store_order_with_wallet_method(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Pupuk Organik Cair',
            'description' => 'Pupuk cair premium.',
            'price' => 45000,
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

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $consumer->id,
            'amount' => 200000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:affiliate-wallet-topup:' . uniqid(),
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Topup affiliate mode test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->post(route('checkout'), [
                'payment_method' => 'gopay',
            ])
            ->assertRedirect(route('orders.mine'));

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $consumer->id,
            'payment_method' => 'gopay',
            'payment_status' => 'paid',
            'order_status' => 'packed',
        ]);
    }

    public function test_farmer_seller_mode_consumer_only_allows_bank_transfer(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Pestisida Organik',
            'description' => 'Pestisida aman untuk tanaman.',
            'price' => 80000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($consumer)
            ->from(route('landing'))
            ->post(route('checkout'), [
                'payment_method' => 'gopay',
            ])
            ->assertRedirect(route('landing'))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($consumer)
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('orders.mine'));

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $consumer->id,
            'payment_method' => 'bank_transfer',
            'order_status' => 'pending_payment',
        ]);
    }
}
