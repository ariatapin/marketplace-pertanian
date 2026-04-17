<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_clears_cart_and_moves_items_to_order_history(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedConsumerMode($consumer->id, 'farmer_seller');

        $productA = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Bibit Cabai Merah',
            'description' => 'Bibit cabai siap tanam.',
            'price' => 32000,
            'stock_qty' => 50,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productB = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Pupuk Organik Premium',
            'description' => 'Pupuk organik kualitas premium.',
            'price' => 48000,
            'stock_qty' => 30,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productA,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productB,
            'qty' => 2,
        ])->assertRedirect();

        $this->assertDatabaseCount('cart_items', 2);

        $this->actingAs($consumer)->post(route('checkout'))
            ->assertRedirect(route('orders.mine'));

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $consumer->id,
        ]);

        $order = DB::table('orders')
            ->where('buyer_id', $consumer->id)
            ->first();

        $this->assertNotNull($order);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'buyer_id' => $consumer->id,
            'order_status' => 'pending_payment',
            'payment_status' => 'unpaid',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $productA,
            'product_name' => 'Bibit Cabai Merah',
            'qty' => 1,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $productB,
            'product_name' => 'Pupuk Organik Premium',
            'qty' => 2,
        ]);

        $this->actingAs($consumer)
            ->get(route('orders.mine'))
            ->assertOk()
            ->assertSee('Order #' . $order->id)
            ->assertSee('Bibit Cabai Merah')
            ->assertSee('Pupuk Organik Premium');
    }

    public function test_invalid_legacy_cart_rows_are_cleaned_before_displaying_summary(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $validProduct = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Benih Jagung',
            'description' => 'Benih jagung hibrida.',
            'price' => 27000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cart_items')->insert([
            [
                'user_id' => $consumer->id,
                'product_type' => 'store',
                'product_id' => $validProduct,
                'qty' => 1,
                'affiliate_referral_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $consumer->id,
                'product_type' => 'store',
                'product_id' => 999999,
                'qty' => 1,
                'affiliate_referral_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($consumer)->get(route('landing'));
        $response->assertOk();
        $response->assertViewHas('cartSummary', function (array $summary): bool {
            return (int) ($summary['items'] ?? 0) === 1;
        });

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $consumer->id,
            'product_type' => 'store',
            'product_id' => 999999,
        ]);

        $this->actingAs($consumer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Benih Jagung')
            ->assertDontSee('Produk tidak tersedia');
    }

    public function test_consumer_can_add_farmer_product_to_cart_and_checkout(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'consumer']);
        $this->seedApprovedConsumerMode($consumer->id, 'farmer_seller');

        $farmerProductId = DB::table('farmer_harvests')->insertGetId([
            'farmer_id' => $seller->id,
            'name' => 'Cabai Merah Petani',
            'description' => 'Hasil panen petani lokal.',
            'price' => 28000,
            'stock_qty' => 40,
            'harvest_date' => now()->toDateString(),
            'image_url' => null,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $farmerProductId,
            'product_type' => 'farmer',
            'qty' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $consumer->id,
            'product_type' => 'farmer',
            'product_id' => $farmerProductId,
            'qty' => 2,
        ]);

        $this->actingAs($consumer)->post(route('checkout'))
            ->assertRedirect(route('orders.mine'));

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $consumer->id,
            'seller_id' => $seller->id,
            'order_source' => 'farmer_p2p',
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
        ]);
        $this->assertDatabaseHas('farmer_harvests', [
            'id' => $farmerProductId,
            'stock_qty' => 38,
        ]);
    }

    public function test_consumer_can_add_product_to_cart_via_json_request(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk JSON Keranjang',
            'description' => 'Produk untuk uji tambah keranjang via AJAX.',
            'price' => 31000,
            'stock_qty' => 12,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->postJson(route('cart.store'), [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 1,
            ])
            ->assertOk()
            ->assertJson([
                'message' => 'Produk masuk ke keranjang.',
                'cart_summary' => [
                    'items' => 1,
                    'qty_total' => 1,
                ],
            ]);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $consumer->id,
            'product_type' => 'store',
            'product_id' => $productId,
            'qty' => 1,
        ]);
    }

    public function test_checkout_can_process_selected_cart_items_only(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedConsumerMode($consumer->id, 'farmer_seller');

        $productA = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk A',
            'description' => 'Produk A',
            'price' => 30000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productB = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk B',
            'description' => 'Produk B',
            'price' => 40000,
            'stock_qty' => 20,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productA,
            'qty' => 1,
        ])->assertRedirect();

        $this->actingAs($consumer)->post(route('cart.store'), [
            'product_id' => $productB,
            'qty' => 1,
        ])->assertRedirect();

        $selectedCartItem = DB::table('cart_items')
            ->where('user_id', $consumer->id)
            ->where('product_id', $productA)
            ->value('id');

        $this->assertNotNull($selectedCartItem);

        $this->actingAs($consumer)
            ->post(route('checkout'), [
                'payment_method' => 'bank_transfer',
                'selection_required' => true,
                'cart_item_ids' => [(int) $selectedCartItem],
            ])
            ->assertRedirect(route('orders.mine'));

        $orderId = DB::table('orders')
            ->where('buyer_id', $consumer->id)
            ->value('id');

        $this->assertNotNull($orderId);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $productA,
        ]);

        $this->assertDatabaseMissing('order_items', [
            'order_id' => $orderId,
            'product_id' => $productB,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $consumer->id,
            'product_id' => $productB,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $consumer->id,
            'product_id' => $productA,
        ]);
    }

    public function test_checkout_requires_selected_items_when_selection_is_required(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedConsumerMode($consumer->id, 'farmer_seller');

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Wajib Pilih',
            'description' => 'Uji validasi checkout wajib pilih item.',
            'price' => 42000,
            'stock_qty' => 10,
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
            ->from(route('cart.index'))
            ->post(route('checkout'), [
                'selection_required' => true,
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart_item_ids');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $consumer->id,
            'product_id' => $productId,
        ]);
        $this->assertDatabaseMissing('orders', [
            'buyer_id' => $consumer->id,
        ]);
    }

    public function test_cart_item_links_to_product_detail_with_back_to_cart_action(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Link Keranjang',
            'description' => 'Produk untuk uji tautan detail dari keranjang.',
            'price' => 39000,
            'stock_qty' => 8,
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

        $detailUrl = route('marketplace.product.show', [
            'productType' => 'store',
            'productId' => $productId,
            'source' => 'cart',
        ]);

        $this->actingAs($consumer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee($detailUrl, false);

        $this->actingAs($consumer)
            ->get($detailUrl)
            ->assertOk()
            ->assertSee('Kembali ke Keranjang');
    }

    public function test_buy_now_bank_transfer_creates_pending_order_without_touching_cart(): void
    {
        Storage::fake('public');

        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedConsumerMode($consumer->id, 'farmer_seller');

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Buy Now Transfer',
            'description' => 'Produk buy now transfer.',
            'price' => 55000,
            'stock_qty' => 30,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->post(route('cart.store'), [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 2,
                'buy_now' => 1,
                'payment_method' => 'bank_transfer',
                'proof' => UploadedFile::fake()->createWithContent('proof.jpg', 'proof-content'),
            ])
            ->assertRedirect(route('orders.mine'));

        $orderId = DB::table('orders')
            ->where('buyer_id', $consumer->id)
            ->where('seller_id', $mitra->id)
            ->latest('id')
            ->value('id');

        $this->assertNotNull($orderId);

        $this->assertDatabaseHas('orders', [
            'id' => (int) $orderId,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
        ]);

        $order = DB::table('orders')->where('id', (int) $orderId)->first(['payment_proof_url', 'payment_submitted_at']);
        $this->assertNotNull($order);
        $this->assertNotEmpty((string) ($order->payment_proof_url ?? ''));
        $this->assertNotNull($order->payment_submitted_at);

        $storedPath = str_replace('storage/', '', (string) $order->payment_proof_url);
        Storage::disk('public')->assertExists($storedPath);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $consumer->id,
            'product_id' => $productId,
        ]);
    }

    public function test_buy_now_bank_transfer_requires_proof_file(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedConsumerMode($consumer->id, 'farmer_seller');

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Buy Now Wajib Bukti',
            'description' => 'Produk buy now wajib bukti transfer.',
            'price' => 35000,
            'stock_qty' => 30,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->from(route('landing'))
            ->post(route('cart.store'), [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 1,
                'buy_now' => 1,
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('landing'))
            ->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_buy_now_wallet_auto_pays_and_pushes_store_order_to_packed(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedConsumerMode($consumer->id, 'affiliate');

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Buy Now Wallet',
            'description' => 'Produk buy now wallet.',
            'price' => 45000,
            'stock_qty' => 30,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $consumer->id,
            'amount' => 200000,
            'transaction_type' => 'demo_topup',
            'description' => 'Saldo awal test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($consumer)
            ->post(route('cart.store'), [
                'product_id' => $productId,
                'product_type' => 'store',
                'qty' => 1,
                'buy_now' => 1,
                'payment_method' => 'gopay',
            ])
            ->assertRedirect(route('orders.mine'));

        $orderId = DB::table('orders')
            ->where('buyer_id', $consumer->id)
            ->where('seller_id', $mitra->id)
            ->latest('id')
            ->value('id');

        $this->assertNotNull($orderId);

        $this->assertDatabaseHas('orders', [
            'id' => (int) $orderId,
            'payment_method' => 'gopay',
            'payment_status' => 'paid',
            'order_status' => 'packed',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $consumer->id,
            'transaction_type' => 'order_payment_wallet',
            'amount' => -45000,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $consumer->id,
            'product_id' => $productId,
        ]);
    }

    public function test_consumer_can_update_qty_and_remove_item_from_cart(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Keranjang Update',
            'description' => 'Produk untuk uji update qty keranjang.',
            'price' => 15000,
            'stock_qty' => 4,
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

        $cartItemId = (int) DB::table('cart_items')
            ->where('user_id', $consumer->id)
            ->where('product_type', 'store')
            ->where('product_id', $productId)
            ->value('id');

        $this->assertGreaterThan(0, $cartItemId);

        $this->actingAs($consumer)
            ->patch(route('cart.update', ['cartItemId' => $cartItemId]), [
                'qty' => 99,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItemId,
            'user_id' => $consumer->id,
            'qty' => 4,
        ]);

        $this->actingAs($consumer)
            ->delete(route('cart.destroy', ['cartItemId' => $cartItemId]))
            ->assertRedirect();

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItemId,
        ]);
    }

    public function test_consumer_cannot_update_or_remove_another_users_cart_item(): void
    {
        $owner = User::factory()->create(['role' => 'consumer']);
        $attacker = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Cart Owner',
            'description' => 'Produk untuk uji ownership keranjang.',
            'price' => 17000,
            'stock_qty' => 9,
            'image_url' => null,
            'is_affiliate_enabled' => false,
            'affiliate_commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('cart.store'), [
            'product_id' => $productId,
            'qty' => 2,
        ])->assertRedirect();

        $cartItemId = (int) DB::table('cart_items')
            ->where('user_id', $owner->id)
            ->where('product_id', $productId)
            ->value('id');
        $this->assertGreaterThan(0, $cartItemId);

        $this->actingAs($attacker)
            ->from(route('cart.index'))
            ->patch(route('cart.update', ['cartItemId' => $cartItemId]), [
                'qty' => 7,
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItemId,
            'user_id' => $owner->id,
            'qty' => 2,
        ]);

        $this->actingAs($attacker)
            ->from(route('cart.index'))
            ->delete(route('cart.destroy', ['cartItemId' => $cartItemId]))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItemId,
            'user_id' => $owner->id,
            'qty' => 2,
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
