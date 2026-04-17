<?php

namespace Tests\Feature\Affiliate;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateWorkspacePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketed_page_separates_active_products_lock_and_history(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        $this->seedApprovedAffiliateMode($affiliate->id);

        $activeProductId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Aktif Dipasarkan',
            'description' => 'Produk aktif',
            'price' => 120000,
            'stock_qty' => 30,
            'image_url' => null,
            'is_active' => true,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 10,
            'affiliate_expire_date' => now()->addDays(7)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expiredProductId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk History',
            'description' => 'Produk history',
            'price' => 85000,
            'stock_qty' => 12,
            'image_url' => null,
            'is_active' => true,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 8,
            'affiliate_expire_date' => now()->subDays(1)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = (int) DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 120000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'shipping_status' => 'delivered',
            'payment_proof_url' => null,
            'paid_amount' => 120000,
            'payment_submitted_at' => now(),
            'resi_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $activeProductId,
            'product_name' => 'Produk Aktif Dipasarkan',
            'qty' => 2,
            'price_per_unit' => 60000,
            'affiliate_id' => $affiliate->id,
            'commission_amount' => 12000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('affiliate_referral_events')->insert([
            'affiliate_user_id' => $affiliate->id,
            'actor_user_id' => $buyer->id,
            'product_id' => $expiredProductId,
            'order_id' => null,
            'event_type' => 'add_to_cart',
            'session_id' => 'session-test',
            'meta' => null,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('affiliate_locks')->insert([
            'affiliate_id' => $affiliate->id,
            'product_id' => $activeProductId,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($affiliate)->get(route('affiliate.marketings', ['filter' => 'laku']));
        $response->assertOk();
        $response->assertSee('Mulai lock:');
        $response->assertSee('Sisa waktu:');
        $response->assertViewHas('promotedProducts', function (Collection $rows) use ($activeProductId): bool {
            return $rows->count() === 1
                && (int) ($rows->first()->id ?? 0) === $activeProductId;
        });
        $response->assertViewHas('cooldownLocks', function (Collection $rows) use ($activeProductId): bool {
            return $rows->count() === 1
                && (int) ($rows->first()->product_id ?? 0) === $activeProductId;
        });
        $response->assertViewHas('marketingHistories', function (Collection $rows) use ($expiredProductId): bool {
            return $rows->contains(fn ($row) => (int) ($row->id ?? 0) === $expiredProductId);
        });
    }

    public function test_wallet_page_shows_available_balance_after_reserved_withdraw(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $this->seedApprovedAffiliateMode($affiliate->id);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $affiliate->id,
                'amount' => 250000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => 'test:affiliate:workspace:wallet:topup:' . $affiliate->id,
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Topup awal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $affiliate->id,
                'amount' => 30000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => 'test:affiliate:workspace:wallet:commission:' . $affiliate->id,
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Komisi demo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('withdraw_requests')->insert([
            'user_id' => $affiliate->id,
            'amount' => 90000,
            'bank_name' => 'BCA',
            'account_number' => '123123',
            'account_holder' => 'Affiliate',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($affiliate)->get(route('affiliate.wallet'));
        $response->assertOk();
        $response->assertSee('Mutasi Wallet');
        $response->assertViewHas('summary', function (array $summary): bool {
            return (float) ($summary['balance'] ?? 0) === 280000.0
                && (float) ($summary['reserved_withdraw_amount'] ?? 0) === 90000.0
                && (float) ($summary['available_balance'] ?? 0) === 190000.0;
        });
    }

    public function test_affiliate_can_select_product_from_marketplace_into_marketed_workspace(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedAffiliateMode($affiliate->id);

        $productId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Pilihan Affiliate',
            'description' => 'Produk untuk uji pilih dipasarkan.',
            'price' => 99000,
            'stock_qty' => 25,
            'image_url' => null,
            'is_active' => true,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 10,
            'affiliate_expire_date' => now()->addDays(10)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($affiliate)
            ->post(route('affiliate.marketings.promote'), [
                'product_id' => $productId,
            ])
            ->assertRedirect(route('affiliate.marketings'));

        $this->assertDatabaseHas('affiliate_locks', [
            'affiliate_id' => $affiliate->id,
            'product_id' => $productId,
            'is_active' => true,
        ]);

        $response = $this->actingAs($affiliate)->get(route('affiliate.marketings'));
        $response->assertOk();
        $response->assertSee('Produk Pilihan Affiliate');
    }

    public function test_affiliate_promote_product_can_redirect_back_to_marketplace_ready_list(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedAffiliateMode($affiliate->id);

        $productId = (int) DB::table('store_products')->insertGetId([
            'mitra_id' => $mitra->id,
            'name' => 'Produk Redirect Ready',
            'description' => 'Produk untuk validasi redirect siap dipasarkan.',
            'price' => 88000,
            'stock_qty' => 15,
            'image_url' => null,
            'is_active' => true,
            'is_affiliate_enabled' => true,
            'affiliate_commission' => 9,
            'affiliate_expire_date' => now()->addDays(10)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $readyListUrl = route('landing', [
            'source' => 'affiliate',
            'ready_marketing' => 1,
        ]);

        $this->actingAs($affiliate)
            ->post(route('affiliate.marketings.promote'), [
                'product_id' => $productId,
                'redirect_to' => $readyListUrl,
            ])
            ->assertRedirect($readyListUrl);

        $this->assertDatabaseHas('affiliate_locks', [
            'affiliate_id' => $affiliate->id,
            'product_id' => $productId,
            'is_active' => true,
        ]);
    }

    public function test_performance_page_counts_completed_transactions_per_order_not_per_item(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedAffiliateMode($affiliate->id);

        $orderId = (int) DB::table('orders')->insertGetId([
            'buyer_id' => $buyer->id,
            'seller_id' => $mitra->id,
            'order_source' => 'store_online',
            'total_amount' => 230000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'shipping_status' => 'delivered',
            'payment_proof_url' => null,
            'paid_amount' => 230000,
            'payment_submitted_at' => now()->subHours(1),
            'resi_number' => null,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subMinutes(30),
        ]);

        // Same order with 2 items should be counted as 1 completed transaction on performance chart.
        DB::table('order_items')->insert([
            [
                'order_id' => $orderId,
                'product_id' => 1001,
                'product_name' => 'Produk A',
                'qty' => 1,
                'price_per_unit' => 100000,
                'affiliate_id' => $affiliate->id,
                'commission_amount' => 10000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'product_id' => 1002,
                'product_name' => 'Produk B',
                'qty' => 1,
                'price_per_unit' => 130000,
                'affiliate_id' => $affiliate->id,
                'commission_amount' => 13000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($affiliate)->get(route('affiliate.performance', ['period' => 'weekly']));
        $response->assertOk();
        $response->assertViewHas('performanceSeries', function (array $series): bool {
            $rows = $series['rows'] ?? collect();
            if (! $rows instanceof Collection) {
                return false;
            }

            $totalCompleted = (int) $rows->sum(function (array $row): int {
                return (int) ($row['completed'] ?? 0);
            });

            return $totalCompleted === 1;
        });
    }

    public function test_affiliate_dashboard_shows_only_five_latest_active_promoted_products(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedAffiliateMode($affiliate->id);

        $productIds = [];
        for ($i = 1; $i <= 6; $i++) {
            $productId = (int) DB::table('store_products')->insertGetId([
                'mitra_id' => $mitra->id,
                'name' => 'Produk Dashboard Aktif #' . $i,
                'description' => 'Produk aktif untuk uji batas dashboard.',
                'price' => 50000 + ($i * 1000),
                'stock_qty' => 10 + $i,
                'image_url' => null,
                'is_active' => true,
                'is_affiliate_enabled' => true,
                'affiliate_commission' => 10,
                'affiliate_expire_date' => now()->addDays(20)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $productIds[] = $productId;

            DB::table('affiliate_locks')->insert([
                'affiliate_id' => $affiliate->id,
                'product_id' => $productId,
                'start_date' => now()->subDays(6 - $i)->toDateString(),
                'expiry_date' => now()->addDays(30)->toDateString(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($affiliate)->get(route('affiliate.dashboard'));
        $response->assertOk();
        $response->assertSee('5 terbaru');
        $response->assertViewHas('recentActivePromotedProducts', function (Collection $rows) use ($productIds): bool {
            $actualIds = $rows->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $expectedIds = [
                (int) $productIds[5],
                (int) $productIds[4],
                (int) $productIds[3],
                (int) $productIds[2],
                (int) $productIds[1],
            ];

            return $rows->count() === 5
                && $actualIds === $expectedIds;
        });
    }

    private function seedApprovedAffiliateMode(int $userId): void
    {
        DB::table('consumer_profiles')->insert([
            'user_id' => $userId,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
