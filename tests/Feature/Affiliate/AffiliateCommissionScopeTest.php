<?php

namespace Tests\Feature\Affiliate;

use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateCommissionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_commission_only_generated_from_mitra_store_online_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $seller = User::factory()->create(['role' => 'farmer_seller']);
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

        $mitraOrderId = $this->insertOrder(
            buyerId: $buyer->id,
            sellerId: $mitra->id,
            source: 'store_online',
            total: 300000,
            orderStatus: 'completed',
            paymentStatus: 'paid'
        );
        $this->insertOrderItem(
            orderId: $mitraOrderId,
            productId: 10,
            productName: 'Pupuk Mitra',
            qty: 3,
            unitPrice: 100000,
            affiliateId: $affiliate->id,
            commissionAmount: 30000
        );

        $sellerOrderId = $this->insertOrder(
            buyerId: $buyer->id,
            sellerId: $seller->id,
            source: 'farmer_p2p',
            total: 150000,
            orderStatus: 'completed',
            paymentStatus: 'paid'
        );
        // Simulasi data kotor: item penjual punya affiliate_id/commission_amount.
        $this->insertOrderItem(
            orderId: $sellerOrderId,
            productId: 20,
            productName: 'Produk Penjual',
            qty: 1,
            unitPrice: 150000,
            affiliateId: $affiliate->id,
            commissionAmount: 15000
        );

        app(SettlementService::class)->settleIfEligible($mitraOrderId);
        app(SettlementService::class)->settleIfEligible($sellerOrderId);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $affiliate->id,
            'transaction_type' => 'affiliate_commission',
            'reference_order_id' => $mitraOrderId,
            'amount' => 30000,
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $affiliate->id,
            'transaction_type' => 'affiliate_commission',
            'reference_order_id' => $sellerOrderId,
        ]);

        $this->assertSame(
            30000.0,
            (float) DB::table('wallet_transactions')
                ->where('wallet_id', $affiliate->id)
                ->where('transaction_type', 'affiliate_commission')
                ->sum('amount')
        );

        $this->assertDatabaseMissing('order_settlements', [
            'order_id' => $sellerOrderId,
        ]);

        $this->assertNotNull($admin->id);
    }

    public function test_affiliate_dashboard_product_breakdown_only_reads_completed_mitra_orders(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $seller = User::factory()->create(['role' => 'farmer_seller']);

        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $validOrderId = $this->insertOrder(
            buyerId: $buyer->id,
            sellerId: $mitra->id,
            source: 'store_online',
            total: 250000,
            orderStatus: 'completed',
            paymentStatus: 'paid'
        );
        $this->insertOrderItem(
            orderId: $validOrderId,
            productId: 101,
            productName: 'Produk Mitra Laku',
            qty: 5,
            unitPrice: 50000,
            affiliateId: $affiliate->id,
            commissionAmount: 25000
        );

        $notCompletedOrderId = $this->insertOrder(
            buyerId: $buyer->id,
            sellerId: $mitra->id,
            source: 'store_online',
            total: 120000,
            orderStatus: 'shipped',
            paymentStatus: 'paid'
        );
        $this->insertOrderItem(
            orderId: $notCompletedOrderId,
            productId: 102,
            productName: 'Produk Belum Selesai',
            qty: 2,
            unitPrice: 60000,
            affiliateId: $affiliate->id,
            commissionAmount: 12000
        );

        $farmerOrderId = $this->insertOrder(
            buyerId: $buyer->id,
            sellerId: $seller->id,
            source: 'farmer_p2p',
            total: 90000,
            orderStatus: 'completed',
            paymentStatus: 'paid'
        );
        $this->insertOrderItem(
            orderId: $farmerOrderId,
            productId: 103,
            productName: 'Produk Penjual P2P',
            qty: 3,
            unitPrice: 30000,
            affiliateId: $affiliate->id,
            commissionAmount: 9000
        );

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $affiliate->id,
                'amount' => 25000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => 'test:affiliate:valid:order:' . $validOrderId,
                'reference_order_id' => $validOrderId,
                'reference_withdraw_id' => null,
                'description' => 'Komisi valid',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $affiliate->id,
                'amount' => 12000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => 'test:affiliate:invalid:status:order:' . $notCompletedOrderId,
                'reference_order_id' => $notCompletedOrderId,
                'reference_withdraw_id' => null,
                'description' => 'Komisi order belum selesai',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($affiliate)->get('/affiliate/dashboard');
        $response->assertOk();

        $response->assertViewHas('productCommissions', function (Collection $rows): bool {
            if ($rows->count() !== 1) {
                return false;
            }

            $first = $rows->first();

            return (string) ($first->product_name ?? '') === 'Produk Mitra Laku'
                && (int) ($first->total_qty ?? 0) === 5
                && (float) ($first->total_commission ?? 0) === 25000.0;
        });

        $response->assertViewHas('productCommissionSummary', function (array $summary): bool {
            return (int) ($summary['product_count'] ?? 0) === 1
                && (int) ($summary['total_qty'] ?? 0) === 5;
        });
    }

    public function test_invalid_affiliate_recipient_is_not_counted_in_settlement_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);
        $invalidAffiliate = User::factory()->create(['role' => 'mitra']);

        DB::table('admin_profiles')->insert([
            'user_id' => $admin->id,
            'platform_name' => 'Demo',
            'platform_fee_percent' => 10,
            'affiliate_fee_percent' => 2,
            'escrow_bank_name' => null,
            'escrow_account_number' => null,
            'escrow_account_holder' => null,
            'min_withdraw_amount' => 50000,
            'payout_delay_days' => 2,
            'auto_payout_enabled' => false,
            'default_courier' => null,
            'support_email' => null,
            'support_phone' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->insertOrder(
            buyerId: $buyer->id,
            sellerId: $seller->id,
            source: 'store_online',
            total: 200000,
            orderStatus: 'completed',
            paymentStatus: 'paid'
        );

        $this->insertOrderItem(
            orderId: $orderId,
            productId: 1001,
            productName: 'Produk Non Affiliate Valid',
            qty: 1,
            unitPrice: 200000,
            affiliateId: $invalidAffiliate->id,
            commissionAmount: 40000
        );

        app(SettlementService::class)->settleIfEligible($orderId);

        $this->assertDatabaseHas('order_settlements', [
            'order_id' => $orderId,
            'gross_amount' => 200000,
            'platform_fee' => 20000,
            'affiliate_commission' => 0,
            'net_to_seller' => 180000,
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $invalidAffiliate->id,
            'transaction_type' => 'affiliate_commission',
            'reference_order_id' => $orderId,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $seller->id,
            'transaction_type' => 'sale_revenue',
            'reference_order_id' => $orderId,
            'amount' => 180000,
        ]);
    }

    private function insertOrder(
        int $buyerId,
        int $sellerId,
        string $source,
        float $total,
        string $orderStatus,
        string $paymentStatus
    ): int {
        return (int) DB::table('orders')->insertGetId([
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'order_source' => $source,
            'total_amount' => $total,
            'payment_method' => 'bank_transfer',
            'payment_status' => $paymentStatus,
            'order_status' => $orderStatus,
            'shipping_status' => 'pending',
            'payment_proof_url' => null,
            'paid_amount' => $paymentStatus === 'paid' ? $total : null,
            'payment_submitted_at' => $paymentStatus === 'paid' ? now() : null,
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOrderItem(
        int $orderId,
        int $productId,
        string $productName,
        int $qty,
        float $unitPrice,
        ?int $affiliateId,
        float $commissionAmount
    ): void {
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => $productName,
            'qty' => $qty,
            'price_per_unit' => $unitPrice,
            'affiliate_id' => $affiliateId,
            'commission_amount' => $commissionAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
