<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDisputeRefundFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_resolve_dispute_to_refund_and_mark_paid_with_wallet_ledger(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 100000,
            'payment_method' => 'gopay',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => null,
            'paid_amount' => 100000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'delivered',
            'resi_number' => 'RS-REFUND-01',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('order_settlements')->insert([
            'order_id' => $orderId,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'gross_amount' => 100000,
            'platform_fee' => 5000,
            'affiliate_commission' => 0,
            'net_to_seller' => 95000,
            'status' => 'paid',
            'eligible_at' => now()->subDay(),
            'settled_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $disputeId = DB::table('disputes')->insertGetId([
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'opened_by' => $buyer->id,
            'category' => 'wrong_item',
            'description' => 'Isi order tidak sesuai.',
            'status' => 'pending',
            'handled_by' => null,
            'handled_at' => null,
            'resolution' => null,
            'resolution_notes' => null,
            'evidence_urls' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subHours(10),
            'updated_at' => now()->subHours(10),
        ]);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $admin->id,
                'amount' => 200000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:admin:refund:topup:{$admin->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo admin untuk refund',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $buyer->id,
                'amount' => -100000,
                'transaction_type' => 'order_payment_wallet',
                'idempotency_key' => "test:buyer:wallet:payment:{$orderId}",
                'reference_order_id' => $orderId,
                'reference_withdraw_id' => null,
                'description' => 'Pembayaran order via saldo wallet',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
        ]);

        $this->actingAs($admin)
            ->from(route('admin.modules.reports.show', ['reportId' => $disputeId]))
            ->post(route('admin.modules.reports.review', ['reportId' => $disputeId]), [
                'status' => 'resolved_buyer',
                'resolution' => 'refund_partial',
                'refund_amount' => 30000,
                'resolution_notes' => 'Refund sebagian untuk item bermasalah.',
            ])
            ->assertRedirect(route('admin.modules.reports.show', ['reportId' => $disputeId]));

        $this->assertDatabaseHas('disputes', [
            'id' => $disputeId,
            'status' => 'resolved_buyer',
            'resolution' => 'refund_partial',
        ]);

        $refundId = (int) DB::table('refunds')
            ->where('order_id', $orderId)
            ->value('id');
        $this->assertGreaterThan(0, $refundId);

        $this->assertDatabaseHas('refunds', [
            'id' => $refundId,
            'status' => 'approved',
            'amount' => 30000,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.modules.reports.show', ['reportId' => $disputeId]))
            ->post(route('admin.modules.refunds.paid', ['refundId' => $refundId]), [
                'refund_reference' => 'RF-30000-TEST',
                'notes' => 'Refund diproses.',
            ])
            ->assertRedirect(route('admin.modules.reports.show', ['reportId' => $disputeId]));

        $this->assertDatabaseHas('refunds', [
            'id' => $refundId,
            'status' => 'paid',
            'refund_reference' => 'RF-30000-TEST',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'refunded',
        ]);

        $this->assertDatabaseHas('order_settlements', [
            'order_id' => $orderId,
            'status' => 'refunded',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $buyer->id,
            'transaction_type' => 'order_refund_wallet',
            'reference_order_id' => $orderId,
            'amount' => 30000,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'refund_admin_payout',
            'reference_order_id' => $orderId,
            'amount' => -30000,
        ]);
    }

    public function test_admin_mark_refund_paid_for_transfer_order_without_wallet_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 45000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => 'proof.jpg',
            'paid_amount' => 45000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'delivered',
            'resi_number' => 'RS-REFUND-02',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $disputeId = DB::table('disputes')->insertGetId([
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
            'created_at' => now()->subHours(8),
            'updated_at' => now()->subHours(8),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.reports.review', ['reportId' => $disputeId]), [
                'status' => 'resolved_buyer',
                'resolution' => 'refund_full',
                'resolution_notes' => 'Refund penuh disetujui.',
            ])
            ->assertRedirect();

        $refundId = (int) DB::table('refunds')
            ->where('order_id', $orderId)
            ->value('id');
        $this->assertGreaterThan(0, $refundId);

        $this->actingAs($admin)
            ->post(route('admin.modules.refunds.paid', ['refundId' => $refundId]), [
                'refund_reference' => 'RF-BANK-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('refunds', [
            'id' => $refundId,
            'status' => 'paid',
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $buyer->id,
            'transaction_type' => 'order_refund_wallet',
            'reference_order_id' => $orderId,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'refund_admin_payout',
            'reference_order_id' => $orderId,
            'amount' => -45000,
        ]);
    }

    public function test_admin_mark_refund_paid_is_idempotent_on_double_submit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 60000,
            'payment_method' => 'gopay',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => null,
            'paid_amount' => 60000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'delivered',
            'resi_number' => 'RS-IDEMPOTENT-01',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $admin->id,
                'amount' => 100000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:admin:refund:idempotent:{$admin->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo admin untuk uji idempotent refund',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $buyer->id,
                'amount' => -60000,
                'transaction_type' => 'order_payment_wallet',
                'idempotency_key' => "test:buyer:wallet:idempotent:{$orderId}",
                'reference_order_id' => $orderId,
                'reference_withdraw_id' => null,
                'description' => 'Pembayaran wallet',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
        ]);

        $refundId = DB::table('refunds')->insertGetId([
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 20000,
            'reason' => 'Refund partial',
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'refund_proof_url' => null,
            'refund_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.refunds.paid', ['refundId' => $refundId]), [
                'refund_reference' => 'RF-IDEMPOTENT-01',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.modules.refunds.paid', ['refundId' => $refundId]), [
                'refund_reference' => 'RF-IDEMPOTENT-02',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('refunds', [
            'id' => $refundId,
            'status' => 'paid',
        ]);

        $this->assertSame(1, DB::table('wallet_transactions')
            ->where('wallet_id', $buyer->id)
            ->where('reference_order_id', $orderId)
            ->where('transaction_type', 'order_refund_wallet')
            ->count());

        $this->assertSame(1, DB::table('wallet_transactions')
            ->where('wallet_id', $admin->id)
            ->where('reference_order_id', $orderId)
            ->where('transaction_type', 'refund_admin_payout')
            ->count());
    }

    public function test_admin_mark_refund_paid_partial_refund_reverses_seller_and_affiliate_proportionally(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);
        $affiliate = User::factory()->create(['role' => 'consumer']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 100000,
            'payment_method' => 'gopay',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => null,
            'paid_amount' => 100000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'delivered',
            'resi_number' => 'RS-REFUND-PARTIAL',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('order_settlements')->insert([
            'order_id' => $orderId,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'gross_amount' => 100000,
            'platform_fee' => 5000,
            'affiliate_commission' => 5000,
            'net_to_seller' => 90000,
            'status' => 'paid',
            'eligible_at' => now()->subDay(),
            'settled_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $admin->id,
                'amount' => 200000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:admin:refund:partial:topup:{$admin->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo admin untuk refund partial',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $buyer->id,
                'amount' => -100000,
                'transaction_type' => 'order_payment_wallet',
                'idempotency_key' => "test:buyer:refund:partial:wallet:{$orderId}",
                'reference_order_id' => $orderId,
                'reference_withdraw_id' => null,
                'description' => 'Pembayaran wallet order partial',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'wallet_id' => $seller->id,
                'amount' => 90000,
                'transaction_type' => 'sale_revenue',
                'idempotency_key' => "test:seller:refund:partial:sale-revenue:{$orderId}",
                'reference_order_id' => $orderId,
                'reference_withdraw_id' => null,
                'description' => 'Pendapatan seller sebelum refund',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'wallet_id' => $affiliate->id,
                'amount' => 5000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => "test:affiliate:refund:partial:commission:{$orderId}",
                'reference_order_id' => $orderId,
                'reference_withdraw_id' => null,
                'description' => 'Komisi affiliate sebelum refund',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
        ]);

        $refundId = DB::table('refunds')->insertGetId([
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => 50000,
            'reason' => 'Refund partial 50%',
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'refund_proof_url' => null,
            'refund_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.refunds.paid', ['refundId' => $refundId]), [
                'refund_reference' => 'RF-PARTIAL-01',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.modules.refunds.paid', ['refundId' => $refundId]), [
                'refund_reference' => 'RF-PARTIAL-02',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('refunds', [
            'id' => $refundId,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $buyer->id,
            'transaction_type' => 'order_refund_wallet',
            'reference_order_id' => $orderId,
            'amount' => 50000,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'refund_admin_payout',
            'reference_order_id' => $orderId,
            'amount' => -50000,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $seller->id,
            'transaction_type' => 'refund_sale_reversal',
            'reference_order_id' => $orderId,
            'amount' => -45000,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $affiliate->id,
            'transaction_type' => 'refund_affiliate_reversal',
            'reference_order_id' => $orderId,
            'amount' => -2500,
        ]);

        $this->assertSame(1, DB::table('wallet_transactions')
            ->where('wallet_id', $seller->id)
            ->where('reference_order_id', $orderId)
            ->where('transaction_type', 'refund_sale_reversal')
            ->count());

        $this->assertSame(1, DB::table('wallet_transactions')
            ->where('wallet_id', $affiliate->id)
            ->where('reference_order_id', $orderId)
            ->where('transaction_type', 'refund_affiliate_reversal')
            ->count());
    }

    public function test_admin_review_on_final_dispute_status_is_idempotent_for_same_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 50000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => 'proof.jpg',
            'paid_amount' => 50000,
            'payment_submitted_at' => now()->subDay(),
            'shipping_status' => 'delivered',
            'resi_number' => 'RS-IDEMPOTENT-02',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $disputeId = DB::table('disputes')->insertGetId([
            'order_id' => $orderId,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'opened_by' => $buyer->id,
            'category' => 'other',
            'description' => 'Kasus sudah diselesaikan.',
            'status' => 'resolved_seller',
            'handled_by' => $admin->id,
            'handled_at' => now()->subHours(4),
            'resolution' => 'release_to_seller',
            'resolution_notes' => 'Dispute final.',
            'evidence_urls' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subHours(4),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.modules.reports.review', ['reportId' => $disputeId]), [
                'status' => 'resolved_seller',
                'resolution' => 'release_to_seller',
                'resolution_notes' => 'Retry request.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('disputes', [
            'id' => $disputeId,
            'status' => 'resolved_seller',
            'resolution' => 'release_to_seller',
        ]);
    }
}
