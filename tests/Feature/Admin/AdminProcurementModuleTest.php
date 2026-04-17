<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminProcurementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_procurement_module_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.modules.procurement'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-procurement-page"', false);
        $response->assertSee('data-testid="admin-procurement-tabs"', false);
        $response->assertSee('data-testid="admin-procurement-stock-input"', false);
    }

    public function test_admin_can_create_admin_product_from_procurement_module(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Timur',
            'code' => 'JATIM',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Surabaya',
            'type' => 'Kota',
            'lat' => -7.2574720,
            'lng' => 112.7520880,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'GDG-SBY-01',
            'name' => 'Gudang Surabaya Utama',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'address' => 'Jl. Industri Surabaya',
            'is_active' => true,
            'notes' => null,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('referer', route('admin.modules.procurement'))
            ->post(route('admin.adminProducts.store'), [
                'name' => 'Benih Padi Premium',
                'description' => 'Produk pengadaan untuk mitra',
                'price' => 25000,
                'unit' => 'kg',
                'min_order_qty' => 2,
                'stock_qty' => 100,
                'warehouse_id' => $warehouseId,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $this->assertDatabaseHas('admin_products', [
            'name' => 'Benih Padi Premium',
            'warehouse_id' => $warehouseId,
            'unit' => 'kg',
            'min_order_qty' => 2,
            'stock_qty' => 100,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_procurement_order_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 150000,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('referer', route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.status', ['adminOrderId' => $orderId]), [
                'status' => 'processing',
                'note' => 'Barang masuk antrean proses gudang',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
        ]);
        $this->assertDatabaseHas('admin_order_status_histories', [
            'admin_order_id' => $orderId,
            'from_status' => 'pending',
            'to_status' => 'processing',
            'actor_user_id' => $admin->id,
            'note' => 'Barang masuk antrean proses gudang',
        ]);
    }

    public function test_admin_can_get_procurement_snapshot_for_polling(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('admin_orders')->insert([
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 120000,
                'status' => 'pending',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 200000,
                'status' => 'approved',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 240000,
                'status' => 'shipped',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.procurement.snapshot'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pending_orders', 1)
            ->assertJsonPath('data.processing_orders', 1)
            ->assertJsonPath('data.shipped_orders', 1)
            ->assertJsonPath('data.new_orders_today', 3);

        $this->assertGreaterThan(0, (int) $response->json('data.latest_order_id'));
    }

    public function test_procurement_page_disables_invalid_status_options_and_final_state_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('admin_orders')->insert([
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 150000,
                'status' => 'pending',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mitra_id' => $mitra->id,
                'total_amount' => 200000,
                'status' => 'delivered',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.procurement', ['section' => 'orders']));

        $response->assertOk();
        $response->assertSee('data-testid="admin-procurement-orders"', false);
        $response->assertSee('DELIVERED');
        $response->assertSee('PENDING');
        $response->assertSee('Detail');
    }

    public function test_procurement_page_shows_latest_status_history_for_each_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 220000,
            'status' => 'processing',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_status_histories')->insert([
            'admin_order_id' => $orderId,
            'from_status' => 'approved',
            'to_status' => 'processing',
            'actor_user_id' => $admin->id,
            'actor_role' => 'admin',
            'note' => 'Status diperbarui oleh admin',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.procurement', ['section' => 'orders']));

        $response->assertOk();
        $response->assertSee('PROCESSING');
        $response->assertSee('#' . $orderId);
    }

    public function test_admin_can_open_procurement_order_detail_with_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 220000,
            'status' => 'processing',
            'notes' => 'urgent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Pupuk Organik',
            'description' => null,
            'price' => 11000,
            'stock_qty' => 100,
            'min_order_qty' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'product_name' => 'Pupuk Organik',
            'price_per_unit' => 11000,
            'qty' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_status_histories')->insert([
            'admin_order_id' => $orderId,
            'from_status' => 'approved',
            'to_status' => 'processing',
            'actor_user_id' => $admin->id,
            'actor_role' => 'admin',
            'note' => 'Status diperbarui oleh admin',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.procurement.orders.show', ['adminOrderId' => $orderId]));

        $response->assertOk();
        $response->assertSee('data-testid="admin-procurement-order-detail-page"', false);
        $response->assertSee('data-testid="admin-procurement-order-audit-log"', false);
        $response->assertSee('Pupuk Organik');
        $response->assertSee('APPROVED');
        $response->assertSee('PROCESSING');
    }

    public function test_admin_can_filter_procurement_order_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Utama']);
        $operator = User::factory()->create(['role' => 'admin', 'name' => 'Operator Gudang']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 180000,
            'status' => 'processing',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_status_histories')->insert([
            [
                'admin_order_id' => $orderId,
                'from_status' => 'pending',
                'to_status' => 'approved',
                'actor_user_id' => $admin->id,
                'actor_role' => 'admin',
                'note' => 'Approve awal',
                'created_at' => now()->subDays(4),
            ],
            [
                'admin_order_id' => $orderId,
                'from_status' => 'approved',
                'to_status' => 'processing',
                'actor_user_id' => $operator->id,
                'actor_role' => 'admin',
                'note' => 'Masuk proses gudang',
                'created_at' => now()->subDay(),
            ],
            [
                'admin_order_id' => $orderId,
                'from_status' => 'processing',
                'to_status' => 'shipped',
                'actor_user_id' => $admin->id,
                'actor_role' => 'admin',
                'note' => 'Siap kirim',
                'created_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.procurement.orders.show', [
            'adminOrderId' => $orderId,
            'history_actor' => 'Operator Gudang',
            'history_status' => 'processing',
            'history_date_from' => now()->subDays(2)->format('Y-m-d'),
            'history_date_to' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertSee('Filter histori berdasarkan actor, status, dan rentang tanggal.');
        $response->assertSee('APPROVED');
        $response->assertSee('PROCESSING');
        $response->assertSee('Masuk proses gudang');
        $response->assertDontSee('PROCESSING -> SHIPPED');
        $response->assertSee('Menampilkan 1 histori.');
    }

    public function test_admin_can_filter_procurement_order_audit_log_via_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Utama']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 210000,
            'status' => 'processing',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_status_histories')->insert([
            [
                'admin_order_id' => $orderId,
                'from_status' => 'pending',
                'to_status' => 'approved',
                'actor_user_id' => $admin->id,
                'actor_role' => 'admin',
                'note' => 'Approve awal',
                'created_at' => now()->subDays(2),
            ],
            [
                'admin_order_id' => $orderId,
                'from_status' => 'approved',
                'to_status' => 'processing',
                'actor_user_id' => $admin->id,
                'actor_role' => 'admin',
                'note' => 'Masuk proses',
                'created_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.procurement.orders.show', [
            'adminOrderId' => $orderId,
            'history_status' => 'processing',
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.history_filters.status', 'processing')
            ->assertJsonCount(1, 'data.status_history')
            ->assertJsonPath('data.status_history.0.to_status', 'processing');
    }

    public function test_admin_can_verify_procurement_payment_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 200000,
            'status' => 'processing',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 200000,
            'payment_proof_url' => 'procurement-payments/sample-proof.jpg',
            'payment_submitted_at' => now()->subMinute(),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('referer', route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.paymentStatus', ['adminOrderId' => $orderId]), [
                'payment_status' => 'paid',
                'payment_note' => 'Bukti valid, pembayaran diterima.',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'payment_status' => 'paid',
            'payment_verified_by' => $admin->id,
            'payment_note' => 'Bukti valid, pembayaran diterima.',
        ]);
    }

    public function test_admin_verify_procurement_payment_status_uses_total_amount_when_paid_amount_is_null(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 200000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => null,
            'payment_proof_url' => 'procurement-payments/sample-proof-fallback.jpg',
            'payment_submitted_at' => now()->subMinute(),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('referer', route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.paymentStatus', ['adminOrderId' => $orderId]), [
                'payment_status' => 'paid',
                'payment_note' => 'Bukti valid, fallback total_amount.',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_verified_by' => $admin->id,
            'payment_note' => 'Bukti valid, fallback total_amount.',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'amount' => 200000,
            'transaction_type' => 'procurement_income',
            'idempotency_key' => "procurement:order:{$orderId}:wallet:{$admin->id}:income",
        ]);
    }

    public function test_admin_verify_procurement_payment_status_skips_wallet_credit_when_finance_demo_mode_disabled(): void
    {
        config()->set('finance.demo_mode', false);

        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 200000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 200000,
            'payment_proof_url' => 'procurement-payments/sample-proof-demo-off.jpg',
            'payment_submitted_at' => now()->subMinute(),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('referer', route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.paymentStatus', ['adminOrderId' => $orderId]), [
                'payment_status' => 'paid',
                'payment_note' => 'Bukti valid, pembayaran diterima (demo mode off).',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_verified_by' => $admin->id,
            'payment_note' => 'Bukti valid, pembayaran diterima (demo mode off).',
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'procurement_income',
            'idempotency_key' => "procurement:order:{$orderId}:wallet:{$admin->id}:income",
        ]);
    }

    public function test_admin_cannot_verify_payment_for_cancelled_procurement_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 200000,
            'status' => 'cancelled',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 200000,
            'payment_proof_url' => 'procurement-payments/sample-proof.jpg',
            'payment_submitted_at' => now()->subMinute(),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.paymentStatus', ['adminOrderId' => $orderId]), [
                'payment_status' => 'paid',
                'payment_note' => 'Uji verifikasi saat order cancelled.',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $response->assertSessionHasErrors('payment_status');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
            'payment_status' => 'pending_verification',
            'payment_verified_by' => null,
        ]);
    }

    public function test_admin_cannot_ship_procurement_order_when_payment_is_not_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 135000,
            'status' => 'processing',
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

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.status', ['adminOrderId' => $orderId]), [
                'status' => 'shipped',
                'note' => 'Uji guard pengiriman saat belum bayar.',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_admin_cancel_unpaid_procurement_order_restores_reserved_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Bibit Uji Restock',
            'description' => null,
            'price' => 10000,
            'min_order_qty' => 1,
            'stock_qty' => 15,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 50000,
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

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'product_name' => 'Bibit Uji Restock',
            'price_per_unit' => 10000,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.status', ['adminOrderId' => $orderId]), [
                'status' => 'cancelled',
                'note' => 'Batal proses order',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $adminProductId,
            'stock_qty' => 20,
        ]);
    }

    public function test_admin_cannot_cancel_paid_procurement_order_without_refund_flow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Bibit Uji Paid',
            'description' => null,
            'price' => 10000,
            'min_order_qty' => 1,
            'stock_qty' => 15,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 50000,
            'status' => 'processing',
            'notes' => null,
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 50000,
            'payment_proof_url' => 'procurement-payments/paid-proof.jpg',
            'payment_submitted_at' => now()->subMinutes(5),
            'payment_verified_at' => now()->subMinutes(2),
            'payment_verified_by' => $admin->id,
            'payment_note' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'product_name' => 'Bibit Uji Paid',
            'price_per_unit' => 10000,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.status', ['adminOrderId' => $orderId]), [
                'status' => 'cancelled',
                'note' => 'Uji cancel paid',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $adminProductId,
            'stock_qty' => 15,
        ]);
    }

    public function test_admin_cannot_cancel_pending_verification_procurement_order_and_stock_is_not_restored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Bibit Uji Pending Verification',
            'description' => null,
            'price' => 10000,
            'min_order_qty' => 1,
            'stock_qty' => 15,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 50000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 50000,
            'payment_proof_url' => 'procurement-payments/pending-proof.jpg',
            'payment_submitted_at' => now()->subMinutes(3),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'product_name' => 'Bibit Uji Pending Verification',
            'price_per_unit' => 10000,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.procurement'))
            ->post(route('admin.procurement.orders.status', ['adminOrderId' => $orderId]), [
                'status' => 'cancelled',
                'note' => 'Uji cancel pending verification',
            ]);

        $response->assertRedirect(route('admin.modules.procurement'));
        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'approved',
            'payment_status' => 'pending_verification',
        ]);
        $this->assertDatabaseHas('admin_products', [
            'id' => $adminProductId,
            'stock_qty' => 15,
        ]);
    }
}
