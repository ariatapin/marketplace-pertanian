<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiResponseContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_regions_endpoint_uses_standard_success_contract(): void
    {
        DB::table('provinces')->insert([
            'name' => 'Jawa Tengah',
            'code' => 'JATENG',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/regions/provinces');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'errors',
            ])
            ->assertJson([
                'success' => true,
                'errors' => null,
            ]);

        $this->assertSame('Jawa Tengah', $response->json('data.0.name'));
    }

    public function test_unauthenticated_api_endpoint_uses_standard_error_contract(): void
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_admin_pending_approvals_endpoint_uses_standard_success_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = User::factory()->create(['role' => 'consumer']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $consumer->id,
            'address' => null,
            'mode' => 'buyer',
            'mode_status' => 'pending',
            'requested_mode' => 'affiliate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/approvals/pending');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'errors',
            ])
            ->assertJson([
                'success' => true,
                'errors' => null,
            ]);

        $this->assertSame('affiliate', $response->json('data.0.requested_mode'));
    }

    public function test_mitra_endpoint_uses_standard_success_contract(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Jawa Barat',
            'code' => 'JABAR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Bandung',
            'type' => 'Kota',
            'lat' => -6.9174640,
            'lng' => 107.6191230,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'GDG-BDG-01',
            'name' => 'Gudang Bandung',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'address' => 'Jl. Gudang Bandung',
            'is_active' => true,
            'notes' => null,
            'created_by' => $mitra->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_products')->insert([
            'name' => 'Pupuk Demo',
            'description' => 'Produk demo',
            'price' => 15000,
            'unit' => 'kg',
            'min_order_qty' => 1,
            'stock_qty' => 50,
            'is_active' => true,
            'warehouse_id' => $warehouseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/mitra/admin-products');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'errors',
            ])
            ->assertJson([
                'success' => true,
                'errors' => null,
            ]);

        $this->assertSame('Pupuk Demo', $response->json('data.0.name'));
    }

    public function test_consumer_route_with_wrong_role_uses_standard_error_contract(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        Sanctum::actingAs($mitra);

        $response = $this->getJson('/api/consumer/orders');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized role.',
                'data' => null,
            ])
            ->assertJsonPath('errors.role', 'mitra');
    }

    public function test_withdraw_for_consumer_without_active_mode_returns_forbidden_contract(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        Sanctum::actingAs($consumer);

        $response = $this->postJson('/api/wallet/withdraw', [
            'amount' => 10000,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Withdraw hanya untuk affiliate/penjual yang sudah disetujui admin.',
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_consumer_open_dispute_api_without_accept_header_returns_json_contract(): void
    {
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 85000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'shipped',
            'payment_proof_url' => 'proof.jpg',
            'paid_amount' => 85000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'shipped',
            'resi_number' => 'RS-API-01',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->post('/api/consumer/orders/' . $orderId . '/disputes', [
            'category' => 'wrong_item',
            'description' => 'Produk tidak sesuai.',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_admin_withdraw_approve_api_returns_json_without_accept_header(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $affiliate = User::factory()->create(['role' => 'consumer']);
        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $withdrawId = DB::table('withdraw_requests')->insertGetId([
            'user_id' => $affiliate->id,
            'amount' => 45000,
            'bank_name' => 'BCA',
            'account_number' => '123456',
            'account_holder' => 'Affiliate User',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->post('/api/admin/withdraws/' . $withdrawId . '/approve');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawId,
            'status' => 'approved',
            'processed_by' => $admin->id,
        ]);
    }

    public function test_admin_dispute_review_and_refund_paid_api_without_accept_header_return_json_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 90000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => 'proof.jpg',
            'paid_amount' => 90000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'delivered',
            'resi_number' => 'RS-API-02',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $reportId = DB::table('disputes')->insertGetId([
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'opened_by' => $buyer->id,
            'category' => 'damaged',
            'description' => 'Kemasan rusak.',
            'status' => 'pending',
            'handled_by' => null,
            'handled_at' => null,
            'resolution' => null,
            'resolution_notes' => null,
            'evidence_urls' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $review = $this->post('/api/admin/reports/' . $reportId . '/review', [
            'status' => 'resolved_buyer',
            'resolution' => 'refund_full',
            'resolution_notes' => 'Refund disetujui penuh.',
        ]);
        $review->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.status', 'resolved_buyer');

        $refundId = (int) DB::table('refunds')->where('order_id', $orderId)->value('id');
        $this->assertGreaterThan(0, $refundId);

        $paid = $this->post('/api/admin/refunds/' . $refundId . '/paid', [
            'refund_reference' => 'RF-API-01',
        ]);
        $paid->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.status', 'paid');

        $paidRetry = $this->post('/api/admin/refunds/' . $refundId . '/paid', [
            'refund_reference' => 'RF-API-02',
        ]);
        $paidRetry->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_p2p_upload_proof_success_uses_standard_success_contract(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'farmer_p2p',
            'total_amount' => 120000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->withHeader('Accept', 'application/json')
            ->post('/api/orders/' . $orderId . '/p2p/upload-proof', [
                'proof' => UploadedFile::fake()->createWithContent('proof.jpg', 'dummy-proof'),
                'paid_amount' => 120000,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.payment_method', 'bank_transfer')
            ->assertJsonPath('data.paid_amount', 120000)
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.order_status', 'pending_payment')
            ->assertJsonPath('data.verification_status', 'waiting_seller_verification');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_method' => 'bank_transfer',
            'paid_amount' => 120000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
        ]);
    }

    public function test_p2p_upload_proof_can_use_gopay_method(): void
    {
        Storage::fake('public');

        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'farmer_p2p',
            'total_amount' => 95000,
            'payment_status' => 'unpaid',
            'order_status' => 'pending_payment',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->withHeader('Accept', 'application/json')
            ->post('/api/orders/' . $orderId . '/p2p/upload-proof', [
                'payment_method' => 'gopay',
                'proof' => UploadedFile::fake()->createWithContent('proof.jpg', 'dummy-proof'),
                'paid_amount' => 95000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_method', 'gopay')
            ->assertJsonPath('data.paid_amount', 95000);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_method' => 'gopay',
            'paid_amount' => 95000,
        ]);
    }

    public function test_admin_create_product_uses_standard_success_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'DKI Jakarta',
            'code' => 'DKI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Jakarta Selatan',
            'type' => 'Kota',
            'lat' => -6.2614930,
            'lng' => 106.8106000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'GDG-JKT-01',
            'name' => 'Gudang Jakarta Selatan',
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'address' => 'Jl. Gudang Jakarta',
            'is_active' => true,
            'notes' => null,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/admin-products', [
            'name' => 'Benih API',
            'description' => 'Input pengadaan dari API',
            'price' => 22000,
            'unit' => 'kg',
            'min_order_qty' => 2,
            'stock_qty' => 30,
            'warehouse_id' => $warehouseId,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['admin_product_id'],
                'errors',
            ]);
    }

    public function test_seller_mark_packed_wrong_owner_returns_standard_error_contract(): void
    {
        $sellerOwner = User::factory()->create(['role' => 'consumer']);
        $otherUser = User::factory()->create(['role' => 'consumer']);
        $buyer = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $sellerOwner->id,
            'order_source' => 'store_online',
            'total_amount' => 98000,
            'payment_status' => 'paid',
            'order_status' => 'paid',
            'payment_proof_url' => null,
            'shipping_status' => 'pending',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson('/api/seller/orders/' . $orderId . '/mark-packed');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_admin_procurement_status_valid_transition_uses_standard_success_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-status', [
            'status' => 'approved',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'approved',
        ]);
    }

    public function test_admin_procurement_status_invalid_transition_returns_validation_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-status', [
            'status' => 'shipped',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
            ])
            ->assertJsonStructure([
                'errors' => ['status'],
            ]);

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'pending',
        ]);
    }

    public function test_admin_procurement_status_delivered_is_rejected_by_api_validation_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'shipped',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-status', [
            'status' => 'delivered',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
            ])
            ->assertJsonStructure([
                'errors' => ['status'],
            ]);

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'shipped',
        ]);
    }

    public function test_admin_procurement_paid_order_cannot_be_cancelled_directly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'processing',
            'notes' => null,
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 175000,
            'payment_proof_url' => 'procurement-payments/proof.jpg',
            'payment_submitted_at' => now()->subMinutes(10),
            'payment_verified_at' => now()->subMinutes(5),
            'payment_verified_by' => $admin->id,
            'payment_note' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-status', [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
            ])
            ->assertJsonStructure([
                'errors' => ['status'],
            ]);

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);
    }

    public function test_admin_procurement_payment_verification_paid_returns_standard_success_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 175000,
            'payment_proof_url' => 'procurement-payments/proof-paid.jpg',
            'payment_submitted_at' => now()->subMinutes(10),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-payment-status', [
            'payment_status' => 'paid',
            'payment_note' => 'Valid, pembayaran diterima.',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_verified_by' => $admin->id,
            'payment_note' => 'Valid, pembayaran diterima.',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'amount' => 175000,
            'transaction_type' => 'procurement_income',
            'idempotency_key' => "procurement:order:{$orderId}:wallet:{$admin->id}:income",
        ]);
    }

    public function test_admin_procurement_payment_verification_paid_uses_total_amount_when_paid_amount_is_null(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 210000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => null,
            'payment_proof_url' => 'procurement-payments/proof-paid-fallback.jpg',
            'payment_submitted_at' => now()->subMinutes(10),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-payment-status', [
            'payment_status' => 'paid',
            'payment_note' => 'Valid, pakai fallback total_amount.',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_verified_by' => $admin->id,
            'payment_note' => 'Valid, pakai fallback total_amount.',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'amount' => 210000,
            'transaction_type' => 'procurement_income',
            'idempotency_key' => "procurement:order:{$orderId}:wallet:{$admin->id}:income",
        ]);
    }

    public function test_admin_procurement_payment_verification_paid_skips_wallet_credit_when_finance_demo_mode_disabled(): void
    {
        config()->set('finance.demo_mode', false);

        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 175000,
            'payment_proof_url' => 'procurement-payments/proof-paid-demo-off.jpg',
            'payment_submitted_at' => now()->subMinutes(10),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-payment-status', [
            'payment_status' => 'paid',
            'payment_note' => 'Valid, pembayaran diterima (demo mode off).',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_verified_by' => $admin->id,
            'payment_note' => 'Valid, pembayaran diterima (demo mode off).',
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'procurement_income',
            'idempotency_key' => "procurement:order:{$orderId}:wallet:{$admin->id}:income",
        ]);
    }

    public function test_admin_procurement_payment_verification_rejected_returns_standard_success_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'approved',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 150000,
            'payment_proof_url' => 'procurement-payments/proof-rejected.jpg',
            'payment_submitted_at' => now()->subMinutes(10),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-payment-status', [
            'payment_status' => 'rejected',
            'payment_note' => 'Nominal tidak sesuai tagihan.',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.payment_status', 'rejected');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'approved',
            'payment_status' => 'rejected',
            'payment_verified_by' => $admin->id,
            'payment_note' => 'Nominal tidak sesuai tagihan.',
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'procurement_income',
            'idempotency_key' => "procurement:order:{$orderId}:wallet:{$admin->id}:income",
        ]);
    }

    public function test_admin_procurement_payment_verification_cancelled_order_returns_validation_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($admin);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 175000,
            'status' => 'cancelled',
            'notes' => null,
            'payment_status' => 'pending_verification',
            'payment_method' => 'bank_transfer',
            'paid_amount' => 175000,
            'payment_proof_url' => 'procurement-payments/proof-paid.jpg',
            'payment_submitted_at' => now()->subMinutes(10),
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/admin-orders/' . $orderId . '/set-payment-status', [
            'payment_status' => 'paid',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
            ])
            ->assertJsonStructure([
                'errors' => ['payment_status'],
            ]);

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
            'payment_status' => 'pending_verification',
            'payment_verified_by' => null,
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'procurement_income',
            'idempotency_key' => "procurement:order:{$orderId}:wallet:{$admin->id}:income",
        ]);
    }

    public function test_mitra_procurement_cancel_api_returns_json_without_accept_header(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);
        Sanctum::actingAs($mitra);

        $adminProductId = DB::table('admin_products')->insertGetId([
            'name' => 'Produk Uji',
            'description' => 'Produk untuk pengujian cancel API',
            'price' => 10000,
            'unit' => 'kg',
            'min_order_qty' => 1,
            'stock_qty' => 8,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('admin_orders')->insertGetId([
            'mitra_id' => $mitra->id,
            'total_amount' => 20000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'paid_amount' => null,
            'payment_proof_url' => null,
            'payment_submitted_at' => null,
            'payment_verified_at' => null,
            'payment_verified_by' => null,
            'payment_note' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_order_items')->insert([
            'admin_order_id' => $orderId,
            'admin_product_id' => $adminProductId,
            'product_name' => 'Produk Uji',
            'price_per_unit' => 10000,
            'unit' => 'kg',
            'qty' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/api/mitra/procurement/orders/' . $orderId . '/cancel');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('admin_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('admin_products', [
            'id' => $adminProductId,
            'stock_qty' => 10,
        ]);
    }
}
