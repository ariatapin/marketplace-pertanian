<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettlementAcidTest extends TestCase
{
    use RefreshDatabase;

    public function test_settlement_can_reconcile_missing_wallet_ledger_when_snapshot_already_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

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

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 100000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => null,
            'paid_amount' => 100000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'delivered',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => 1,
            'product_name' => 'Produk Demo',
            'qty' => 1,
            'price_per_unit' => 100000,
            'affiliate_id' => null,
            'commission_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_settlements')->insert([
            'order_id' => $orderId,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'gross_amount' => 100000,
            'platform_fee' => 10000,
            'affiliate_commission' => 0,
            'net_to_seller' => 90000,
            'status' => 'paid',
            'eligible_at' => now(),
            'settled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(SettlementService::class)->settleIfEligible($orderId);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'escrow_in',
            'reference_order_id' => $orderId,
            'amount' => 100000,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $seller->id,
            'transaction_type' => 'sale_revenue',
            'reference_order_id' => $orderId,
            'amount' => 90000,
        ]);
    }

    public function test_settlement_is_idempotent_for_wallet_ledger(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        DB::table('admin_profiles')->insert([
            'user_id' => $admin->id,
            'platform_name' => 'Demo',
            'platform_fee_percent' => 5,
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

        $orderId = DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_source' => 'store_online',
            'total_amount' => 100000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'payment_proof_url' => null,
            'paid_amount' => 100000,
            'payment_submitted_at' => now(),
            'shipping_status' => 'delivered',
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => 1,
            'product_name' => 'Produk Demo',
            'qty' => 1,
            'price_per_unit' => 100000,
            'affiliate_id' => null,
            'commission_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(SettlementService::class);
        $service->settleIfEligible($orderId);
        $service->settleIfEligible($orderId);

        $this->assertSame(1, DB::table('order_settlements')->where('order_id', $orderId)->count());
        $this->assertSame(1, DB::table('wallet_transactions')
            ->where('wallet_id', $admin->id)
            ->where('transaction_type', 'escrow_in')
            ->where('reference_order_id', $orderId)
            ->count());
        $this->assertSame(1, DB::table('wallet_transactions')
            ->where('wallet_id', $seller->id)
            ->where('transaction_type', 'sale_revenue')
            ->where('reference_order_id', $orderId)
            ->count());
    }
}
