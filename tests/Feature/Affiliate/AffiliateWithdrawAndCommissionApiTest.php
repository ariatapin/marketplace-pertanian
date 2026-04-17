<?php

namespace Tests\Feature\Affiliate;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AffiliateWithdrawAndCommissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_api_withdraw_requires_complete_bank_profile(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $this->seedApprovedAffiliateMode($affiliate->id);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $affiliate->id,
            'amount' => 120000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:affiliate:withdraw:bank-required:' . $affiliate->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo awal affiliate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($affiliate);

        $response = $this->postJson('/api/affiliate/withdraw', [
            'amount' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validasi gagal.',
                'data' => null,
            ])
            ->assertJsonStructure([
                'errors' => ['bank_profile'],
            ]);
    }

    public function test_affiliate_api_withdraw_success_when_bank_profile_complete(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $this->seedApprovedAffiliateMode($affiliate->id);
        $this->seedBankProfile($affiliate->id, 'BCA', '1234567890', 'Affiliate Demo');

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $affiliate->id,
            'amount' => 150000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:affiliate:withdraw:success:' . $affiliate->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo awal affiliate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($affiliate);

        $response = $this->postJson('/api/affiliate/withdraw', [
            'amount' => 50000,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Permintaan withdraw berhasil dibuat.',
                'errors' => null,
            ])
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('withdraw_requests', [
            'user_id' => $affiliate->id,
            'amount' => 50000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Affiliate Demo',
            'status' => 'pending',
        ]);
    }

    public function test_affiliate_commissions_api_supports_pagination_payload(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $this->seedApprovedAffiliateMode($affiliate->id);

        for ($i = 1; $i <= 5; $i++) {
            $orderId = (int) DB::table('orders')->insertGetId([
                'buyer_id' => $buyer->id,
                'seller_id' => $mitra->id,
                'order_source' => 'store_online',
                'total_amount' => 100000 + ($i * 1000),
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
                'payment_proof_url' => null,
                'paid_amount' => 100000 + ($i * 1000),
                'payment_submitted_at' => now()->subMinutes(10),
                'resi_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('wallet_transactions')->insert([
                'wallet_id' => $affiliate->id,
                'amount' => 1000 + $i,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => 'test:affiliate:commission:order:' . $orderId,
                'reference_order_id' => $orderId,
                'reference_withdraw_id' => null,
                'description' => 'Komisi order #' . $orderId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($affiliate);

        $response = $this->getJson('/api/affiliate/commissions?page=2&per_page=2');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ])
            ->assertJsonPath('data.pagination.page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonPath('data.pagination.last_page', 3)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_affiliate_dashboard_summary_uses_available_balance_after_reserved_withdraw(): void
    {
        $affiliate = User::factory()->create(['role' => 'consumer']);
        $this->seedApprovedAffiliateMode($affiliate->id);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $affiliate->id,
            'amount' => 200000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => 'test:affiliate:dashboard:balance:' . $affiliate->id,
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo awal affiliate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdraw_requests')->insert([
            'user_id' => $affiliate->id,
            'amount' => 80000,
            'bank_name' => 'BNI',
            'account_number' => '99887766',
            'account_holder' => 'Affiliate Demo',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($affiliate)->get('/affiliate/dashboard');
        $response->assertOk();

        $response->assertViewHas('summary', function (array $summary): bool {
            return (float) ($summary['balance'] ?? 0) === 200000.0
                && (float) ($summary['reserved_withdraw_amount'] ?? 0) === 80000.0
                && (float) ($summary['available_balance'] ?? 0) === 120000.0;
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

    private function seedBankProfile(int $userId, string $bankName, string $accountNumber, string $accountHolder): void
    {
        DB::table('withdraw_bank_accounts')->insert([
            'user_id' => $userId,
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_holder' => $accountHolder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
