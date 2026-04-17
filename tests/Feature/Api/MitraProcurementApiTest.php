<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MitraProcurementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_can_get_procurement_orders_with_item_summary(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 84000,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            [
                'admin_order_id' => $orderId,
                'admin_product_id' => DB::table('admin_products')->insertGetId([
                    'name' => 'Bibit Cabai',
                    'description' => null,
                    'price' => 12000,
                    'min_order_qty' => 1,
                    'stock_qty' => 100,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                'product_name' => 'Bibit Cabai',
                'price_per_unit' => 12000,
                'qty' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/mitra/procurement/orders');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $orderId)
            ->assertJsonPath('data.0.line_count', 1)
            ->assertJsonPath('data.0.total_qty', 7);
    }

    public function test_mitra_can_get_own_procurement_order_detail_and_history(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $admin = User::factory()->create(['role' => 'admin']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 60000,
            'status' => 'approved',
            'notes' => 'Urgent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => DB::table('admin_products')->insertGetId([
                'name' => 'Pupuk Organik',
                'description' => null,
                'price' => 15000,
                'min_order_qty' => 1,
                'stock_qty' => 100,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'product_name' => 'Pupuk Organik',
            'price_per_unit' => 15000,
            'qty' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_status_histories')->insert([
            [
                'admin_order_id' => $orderId,
                'from_status' => null,
                'to_status' => 'pending',
                'actor_user_id' => $mitra->id,
                'actor_role' => 'mitra',
                'note' => 'Order dibuat',
                'created_at' => now()->subMinutes(5),
            ],
            [
                'admin_order_id' => $orderId,
                'from_status' => 'pending',
                'to_status' => 'approved',
                'actor_user_id' => $admin->id,
                'actor_role' => 'admin',
                'note' => 'Disetujui admin',
                'created_at' => now(),
            ],
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/mitra/procurement/orders/' . $orderId);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.id', $orderId)
            ->assertJsonPath('data.items.0.product_name', 'Pupuk Organik')
            ->assertJsonPath('data.status_history.1.to_status', 'approved');
    }

    public function test_mitra_cannot_get_other_mitra_procurement_order_detail(): void
    {
        $owner = User::factory()->create(['role' => 'mitra']);
        $other = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $owner->id,
            'total_amount' => 50000,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($other);

        $response = $this->getJson('/api/mitra/procurement/orders/' . $orderId);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null);
    }

    public function test_mitra_can_create_procurement_order_via_api_with_multiple_items(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productA = DB::table('admin_products')->insertGetId([
            'name' => 'Bibit Melon',
            'description' => null,
            'price' => 8000,
            'min_order_qty' => 1,
            'stock_qty' => 50,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productB = DB::table('admin_products')->insertGetId([
            'name' => 'Pestisida',
            'description' => null,
            'price' => 6000,
            'min_order_qty' => 1,
            'stock_qty' => 40,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders', [
            'items' => [
                ['admin_product_id' => $productA, 'qty' => 5],
                ['admin_product_id' => $productB, 'qty' => 2],
            ],
            'notes' => 'PO API multi item',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_amount', 52000)
            ->assertJsonPath('data.status', 'pending');

        $orderId = (int) $response->json('data.admin_order_id');
        $this->assertDatabaseHas('admin_order_items', [
            'admin_order_id' => $orderId,
            'admin_product_id' => $productA,
            'qty' => 5,
        ]);
        $this->assertDatabaseHas('admin_order_items', [
            'admin_order_id' => $orderId,
            'admin_product_id' => $productB,
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('admin_order_status_histories', [
            'admin_order_id' => $orderId,
            'to_status' => 'pending',
            'actor_user_id' => $mitra->id,
        ]);
    }

    public function test_mitra_can_cancel_procurement_order_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('admin_products')->insertGetId([
            'name' => 'Kapur Dolomit',
            'description' => null,
            'price' => 4000,
            'min_order_qty' => 1,
            'stock_qty' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 20000,
            'status' => 'approved',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $productId,
            'product_name' => 'Kapur Dolomit',
            'price_per_unit' => 4000,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_products')->where('id', $productId)->update([
            'stock_qty' => 25,
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/cancel', [
            'note' => 'Dibatalkan dari API',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.admin_order_id', $orderId)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $productId,
            'stock_qty' => 30,
        ]);
    }

    public function test_mitra_cannot_cancel_other_mitra_order_via_api(): void
    {
        $owner = User::factory()->create(['role' => 'mitra']);
        $other = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $owner->id,
            'total_amount' => 25000,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($other);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/cancel');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_mitra_cannot_cancel_processing_order_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 35000,
            'status' => 'processing',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/cancel');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validasi gagal.');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
        ]);
    }

    public function test_mitra_cannot_cancel_pending_verification_order_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('admin_products')->insertGetId([
            'name' => 'Pupuk Uji API Pending Verification',
            'description' => null,
            'price' => 5000,
            'min_order_qty' => 1,
            'stock_qty' => 12,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 25000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 25000,
            'payment_proof_url' => 'procurement-payments/api-pending-proof.jpg',
            'payment_submitted_at' => now()->subMinute(),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $productId,
            'product_name' => 'Pupuk Uji API Pending Verification',
            'price_per_unit' => 5000,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/cancel');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validasi gagal.');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'approved',
            'payment_status' => 'pending_verification',
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $productId,
            'stock_qty' => 12,
        ]);
    }

    public function test_mitra_can_pay_procurement_order_using_wallet_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 200000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:procurement:api:wallet:topup:' . $mitra->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Topup saldo mitra API',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 150000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'paid_amount' => null,
            'payment_proof_url' => null,
            'payment_submitted_at' => null,
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/submit-payment', [
            'payment_method' => 'wallet',
            'paid_amount' => 150000,
            'payment_note' => 'Bayar API via wallet',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_method', 'wallet')
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
            'paid_amount' => 150000,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $mitra->id,
            'transaction_type' => 'procurement_payment_wallet',
            'amount' => -150000,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'procurement_income',
            'amount' => 150000,
        ]);
    }

    public function test_mitra_wallet_payment_via_api_fails_when_balance_insufficient(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        User::factory()->create(['role' => 'admin']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 70000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:procurement:api:wallet:insufficient:' . $mitra->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Topup saldo kurang API',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 120000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'paid_amount' => null,
            'payment_proof_url' => null,
            'payment_submitted_at' => null,
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/submit-payment', [
            'payment_method' => 'wallet',
            'paid_amount' => 120000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'payment_method' => null,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $mitra->id,
            'transaction_type' => 'procurement_payment_wallet',
        ]);
    }

    public function test_mitra_wallet_payment_via_api_is_blocked_when_finance_demo_mode_is_disabled(): void
    {
        config()->set('finance.demo_mode', false);

        $mitra = User::factory()->create(['role' => 'mitra']);
        User::factory()->create(['role' => 'admin']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 200000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:procurement:api:wallet:demo-off:' . $mitra->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Topup saldo mitra API demo off',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 150000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'paid_amount' => null,
            'payment_proof_url' => null,
            'payment_submitted_at' => null,
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/submit-payment', [
            'payment_method' => 'wallet',
            'paid_amount' => 150000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonPath('errors.payment_method.0', 'Pembayaran saldo pengadaan sedang dinonaktifkan pada mode demo keuangan.');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'approved',
            'payment_status' => 'unpaid',
            'payment_method' => null,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $mitra->id,
            'transaction_type' => 'procurement_payment_wallet',
        ]);
    }

    public function test_mitra_transfer_payment_via_api_must_match_total_order_amount(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'paid_amount' => null,
            'payment_proof_url' => null,
            'payment_submitted_at' => null,
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/submit-payment', [
            'payment_method' => 'bank_transfer',
            'paid_amount' => 170000,
            'payment_note' => 'Nominal tidak penuh',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonPath('errors.paid_amount.0', 'Pembayaran transfer wajib sama dengan total tagihan order.');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'paid_amount' => null,
        ]);
    }

    public function test_mitra_can_confirm_received_procurement_order_via_api(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Produk Konfirmasi API',
            'description' => null,
            'price' => 25000,
            'unit' => 'kg',
            'min_order_qty' => 1,
            'stock_qty' => 20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 50000,
            'status' => 'shipped',
            'notes' => null,
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 50000,
            'payment_proof_url' => 'procurement-payments/proof-api.jpg',
            'payment_submitted_at' => now()->subHour(),
            'payment_verified_at' => now()->subMinutes(20),
            'payment_verified_by' => null,
            'payment_note' => 'Valid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'product_name' => 'Produk Konfirmasi API',
            'price_per_unit' => 25000,
            'unit' => 'kg',
            'qty' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->postJson('/api/mitra/procurement/orders/' . $orderId . '/confirm-received', [
            'note' => 'Diterima via API.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.admin_order_id', $orderId)
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.stock_settlement.settled', true);

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('procurement_stock_settlements', [
            'admin_order_id' => $orderId,
            'mitra_id' => $mitra->id,
            'line_count' => 1,
            'total_qty' => 2,
        ]);
        $this->assertDatabaseHas('store_products', [
            'mitra_id' => $mitra->id,
            'name' => 'Produk Konfirmasi API',
            'stock_qty' => 2,
        ]);
    }
}
