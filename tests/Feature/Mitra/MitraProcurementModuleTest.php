<?php

namespace Tests\Feature\Mitra;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MitraProcurementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mitra_can_open_procurement_module_page(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $response = $this->actingAs($mitra)->get(route('mitra.procurement.index'));

        $response->assertOk();
        $response->assertSee('data-testid="mitra-procurement-page"', false);
        $response->assertSee('data-testid="mitra-procurement-catalog"', false);
        $response->assertSee('data-testid="mitra-procurement-history"', false);
    }

    public function test_mitra_can_create_procurement_order_from_admin_product(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Bibit Jagung',
            'description' => 'Bibit kualitas unggul',
            'price' => 10000,
            'min_order_qty' => 2,
            'stock_qty' => 50,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)->post(route('mitra.procurement.createOrder'), [
            'items' => [
                [
                    'admin_product_id' => $adminProductId,
                    'qty' => 5,
                    'selected' => 1,
                ],
            ],
            'notes' => 'Order untuk restock mingguan',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/mitra/procurement/orders/', (string) $response->headers->get('Location'));

        $this->assertDatabaseHas('admin_orders', [
            'mitra_id' => $mitra->id,
            'status' => 'pending',
            'total_amount' => 50000,
        ]);

        $orderId = DB::table('admin_orders')->where('mitra_id', $mitra->id)->value('id');

        $this->assertDatabaseHas('admin_order_items', [
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'qty' => 5,
        ]);
        $this->assertDatabaseHas('admin_order_status_histories', [
            'admin_order_id' => $orderId,
            'from_status' => null,
            'to_status' => 'pending',
        ]);

        $this->assertDatabaseHas('admin_products', [
            'id' => $adminProductId,
            'stock_qty' => 45,
        ]);
    }

    public function test_mitra_can_open_own_procurement_detail_with_status_history(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $admin = User::factory()->create(['role' => 'admin']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Pupuk Urea',
            'description' => 'Pupuk dasar',
            'price' => 9000,
            'min_order_qty' => 1,
            'stock_qty' => 20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->actingAs($mitra)->post(route('mitra.procurement.createOrder'), [
            'items' => [
                [
                    'admin_product_id' => $adminProductId,
                    'qty' => 2,
                    'selected' => 1,
                ],
            ],
        ]);
        $createResponse->assertRedirect();
        $this->assertStringContainsString('/mitra/procurement/orders/', (string) $createResponse->headers->get('Location'));

        $orderId = DB::table('admin_orders')->where('mitra_id', $mitra->id)->value('id');

        $this->actingAs($admin)->post(route('admin.procurement.orders.status', ['adminOrderId' => $orderId]), [
            'status' => 'approved',
        ])->assertRedirect();

        $response = $this->actingAs($mitra)->get(route('mitra.procurement.show', ['orderId' => $orderId]));

        $response->assertOk();
        $response->assertSee('Order #' . $orderId);
        $response->assertSee('Pupuk Urea');
        $response->assertSee('Timeline Status');
        $response->assertSee('PENDING');
        $response->assertSee('APPROVED');
    }

    public function test_mitra_cannot_open_other_mitra_procurement_detail(): void
    {
        $owner = User::factory()->create(['role' => 'mitra']);
        $other = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $owner->id,
            'total_amount' => 45000,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($other)->get(route('mitra.procurement.show', ['orderId' => $orderId]));
        $response->assertForbidden();
    }

    public function test_mitra_can_create_multi_item_procurement_order(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productA = DB::table('admin_products')->insertGetId([
            'name' => 'Benih Tomat',
            'description' => null,
            'price' => 10000,
            'min_order_qty' => 1,
            'stock_qty' => 20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productB = DB::table('admin_products')->insertGetId([
            'name' => 'Pupuk NPK',
            'description' => null,
            'price' => 5000,
            'min_order_qty' => 2,
            'stock_qty' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)->post(route('mitra.procurement.createOrder'), [
            'items' => [
                [
                    'admin_product_id' => $productA,
                    'qty' => 3,
                    'selected' => 1,
                ],
                [
                    'admin_product_id' => $productB,
                    'qty' => 4,
                    'selected' => 1,
                ],
            ],
            'notes' => 'PO campuran mingguan',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/mitra/procurement/orders/', (string) $response->headers->get('Location'));

        $order = DB::table('admin_orders')->where('mitra_id', $mitra->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('50000.00', (string) $order->total_amount);

        $this->assertDatabaseHas('admin_order_items', [
            'admin_order_id' => $order->id,
            'admin_product_id' => $productA,
            'qty' => 3,
        ]);
        $this->assertDatabaseHas('admin_order_items', [
            'admin_order_id' => $order->id,
            'admin_product_id' => $productB,
            'qty' => 4,
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $productA,
            'stock_qty' => 17,
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $productB,
            'stock_qty' => 26,
        ]);
    }

    public function test_mitra_can_cancel_pending_procurement_order_and_restore_stock(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('admin_products')->insertGetId([
            'name' => 'Pupuk Kandang',
            'description' => null,
            'price' => 7000,
            'min_order_qty' => 1,
            'stock_qty' => 25,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->actingAs($mitra)->post(route('mitra.procurement.createOrder'), [
            'items' => [
                ['admin_product_id' => $productId, 'qty' => 5, 'selected' => 1],
            ],
        ]);
        $createResponse->assertRedirect();
        $this->assertStringContainsString('/mitra/procurement/orders/', (string) $createResponse->headers->get('Location'));

        $orderId = DB::table('admin_orders')->where('mitra_id', $mitra->id)->value('id');
        $this->assertDatabaseHas('admin_products', ['id' => $productId, 'stock_qty' => 20]);

        $response = $this->actingAs($mitra)
            ->post(route('mitra.procurement.cancel', ['orderId' => $orderId]), [
                'note' => 'Rencana distribusi berubah',
            ]);

        $response->assertRedirect(route('mitra.procurement.index'));
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $productId,
            'stock_qty' => 25,
        ]);
        $this->assertDatabaseHas('admin_order_status_histories', [
            'admin_order_id' => $orderId,
            'from_status' => 'pending',
            'to_status' => 'cancelled',
            'actor_user_id' => $mitra->id,
        ]);
    }

    public function test_mitra_cannot_cancel_processing_procurement_order(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 100000,
            'status' => 'processing',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.procurement.index'))
            ->post(route('mitra.procurement.cancel', ['orderId' => $orderId]));

        $response->assertRedirect(route('mitra.procurement.index'));
        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
        ]);
    }

    public function test_mitra_cannot_cancel_pending_verification_procurement_order(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $productId = DB::table('admin_products')->insertGetId([
            'name' => 'Pupuk Uji Pending Verification',
            'description' => null,
            'price' => 8000,
            'min_order_qty' => 1,
            'stock_qty' => 18,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 40000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 40000,
            'payment_proof_url' => 'procurement-payments/pending-proof.jpg',
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
            'product_name' => 'Pupuk Uji Pending Verification',
            'price_per_unit' => 8000,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.procurement.index'))
            ->post(route('mitra.procurement.cancel', ['orderId' => $orderId]), [
                'note' => 'Uji cancel pending verification',
            ]);

        $response->assertRedirect(route('mitra.procurement.index'));
        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'approved',
            'payment_status' => 'pending_verification',
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $productId,
            'stock_qty' => 18,
        ]);
    }

    public function test_mitra_can_confirm_received_for_shipped_paid_procurement_order(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Pupuk Konfirmasi Terima',
            'description' => 'Produk uji konfirmasi diterima',
            'price' => 50000,
            'unit' => 'kg',
            'min_order_qty' => 1,
            'stock_qty' => 50,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 100000,
            'status' => 'shipped',
            'notes' => null,
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 100000,
            'payment_proof_url' => 'procurement-payments/proof.jpg',
            'payment_submitted_at' => now()->subHour(),
            'payment_verified_at' => now()->subMinutes(30),
            'payment_verified_by' => null,
            'payment_note' => 'Valid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'product_name' => 'Pupuk Konfirmasi Terima',
            'price_per_unit' => 50000,
            'unit' => 'kg',
            'qty' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->post(route('mitra.procurement.confirmReceived', ['orderId' => $orderId]), [
                'note' => 'Barang diterima lengkap.',
            ]);

        $response->assertRedirect(route('mitra.procurement.show', ['orderId' => $orderId]));
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
            'name' => 'Pupuk Konfirmasi Terima',
            'stock_qty' => 2,
        ]);
        $this->assertDatabaseHas('admin_order_status_histories', [
            'admin_order_id' => $orderId,
            'to_status' => 'delivered',
            'actor_user_id' => $mitra->id,
        ]);
    }

    public function test_mitra_can_submit_procurement_payment_for_admin_verification(): void
    {
        Storage::fake('public');
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

        $response = $this->actingAs($mitra)
            ->post(route('mitra.procurement.submitPayment', ['orderId' => $orderId]), [
                'payment_method' => 'bank_transfer',
                'paid_amount' => 175000,
                'payment_note' => 'Pembayaran via transfer bank',
                'payment_proof' => UploadedFile::fake()->create('proof.pdf', 128, 'application/pdf'),
            ]);

        $response->assertRedirect(route('mitra.procurement.show', ['orderId' => $orderId]));
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
        ]);

        $storedProof = DB::table('admin_orders')->where('id', $orderId)->value('payment_proof_url');
        $this->assertNotNull($storedProof);
        Storage::disk('public')->assertExists((string) $storedProof);
    }

    public function test_mitra_transfer_payment_must_match_total_order_amount(): void
    {
        Storage::fake('public');
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

        $response = $this->actingAs($mitra)
            ->from(route('mitra.procurement.show', ['orderId' => $orderId]))
            ->post(route('mitra.procurement.submitPayment', ['orderId' => $orderId]), [
                'payment_method' => 'bank_transfer',
                'paid_amount' => 170000,
                'payment_note' => 'Nominal tidak penuh',
                'payment_proof' => UploadedFile::fake()->create('proof.pdf', 128, 'application/pdf'),
            ]);

        $response->assertRedirect(route('mitra.procurement.show', ['orderId' => $orderId]));
        $response->assertSessionHasErrors('paid_amount');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'paid_amount' => null,
        ]);
    }

    public function test_mitra_can_pay_procurement_order_using_wallet_balance(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $admin = User::factory()->create(['role' => 'admin']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 250000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:procurement:wallet:topup:' . $mitra->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Topup saldo mitra untuk uji pembayaran procurement',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        $response = $this->actingAs($mitra)
            ->post(route('mitra.procurement.submitPayment', ['orderId' => $orderId]), [
                'payment_method' => 'wallet',
                'paid_amount' => 175000,
                'payment_note' => 'Bayar via saldo wallet',
            ]);

        $response->assertRedirect(route('mitra.procurement.show', ['orderId' => $orderId]));
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
            'paid_amount' => 175000,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $mitra->id,
            'transaction_type' => 'procurement_payment_wallet',
            'amount' => -175000,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'procurement_income',
            'amount' => 175000,
        ]);
        $this->assertNotNull(DB::table('admin_orders')->where('id', $orderId)->value('payment_verified_at'));
    }

    public function test_mitra_wallet_payment_fails_when_available_balance_is_insufficient(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        User::factory()->create(['role' => 'admin']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 100000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:procurement:wallet:insufficient:' . $mitra->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Topup saldo mitra untuk uji saldo kurang',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdraw_requests')->insert([
            'user_id' => $mitra->id,
            'amount' => 30000,
            'bank_name' => 'BCA',
            'account_number' => '123',
            'account_holder' => 'Mitra Test',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 90000,
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

        $response = $this->actingAs($mitra)
            ->from(route('mitra.procurement.show', ['orderId' => $orderId]))
            ->post(route('mitra.procurement.submitPayment', ['orderId' => $orderId]), [
                'payment_method' => 'wallet',
                'paid_amount' => 90000,
            ]);

        $response->assertRedirect(route('mitra.procurement.show', ['orderId' => $orderId]));
        $response->assertSessionHasErrors('paid_amount');
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

    public function test_mitra_wallet_procurement_payment_is_blocked_when_finance_demo_mode_is_disabled(): void
    {
        config()->set('finance.demo_mode', false);

        $mitra = User::factory()->create(['role' => 'mitra']);
        User::factory()->create(['role' => 'admin']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 250000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:procurement:wallet:demo-off:' . $mitra->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Topup saldo mitra untuk uji finance demo off',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        $response = $this->actingAs($mitra)
            ->from(route('mitra.procurement.show', ['orderId' => $orderId]))
            ->post(route('mitra.procurement.submitPayment', ['orderId' => $orderId]), [
                'payment_method' => 'wallet',
                'paid_amount' => 175000,
            ]);

        $response->assertRedirect(route('mitra.procurement.show', ['orderId' => $orderId]));
        $response->assertSessionHasErrors('payment_method');
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

    public function test_mitra_procurement_payment_rejects_non_transfer_method(): void
    {
        Storage::fake('public');
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

        $response = $this->actingAs($mitra)
            ->from(route('mitra.procurement.show', ['orderId' => $orderId]))
            ->post(route('mitra.procurement.submitPayment', ['orderId' => $orderId]), [
                'payment_method' => 'gopay',
                'paid_amount' => 175000,
                'payment_note' => 'Uji metode non-transfer',
                'payment_proof' => UploadedFile::fake()->create('proof.pdf', 128, 'application/pdf'),
            ]);

        $response->assertRedirect(route('mitra.procurement.show', ['orderId' => $orderId]));
        $response->assertSessionHasErrors('payment_method');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'payment_method' => null,
        ]);
    }
}
