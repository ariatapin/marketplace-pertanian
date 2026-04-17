<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WithdrawAcidTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdraw_request_uses_available_balance_after_pending_reservation(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 100000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => "test:withdraw:balance:{$mitra->id}",
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo demo awal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdraw_requests')->insert([
            'user_id' => $mitra->id,
            'amount' => 70000,
            'bank_name' => 'BCA',
            'account_number' => '123',
            'account_holder' => 'Mitra Demo',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdraw_bank_accounts')->insert([
            'user_id' => $mitra->id,
            'bank_name' => 'BCA',
            'account_number' => '123',
            'account_holder' => 'Mitra Demo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('landing'))
            ->post(route('wallet.withdraw.request'), [
                'amount' => 40000,
            ]);

        $response->assertRedirect(route('landing'));
        $response->assertSessionHasErrors('amount');
        $this->assertSame(1, DB::table('withdraw_requests')->where('user_id', $mitra->id)->count());
    }

    public function test_withdraw_request_success_when_amount_within_available_balance(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 100000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => "test:withdraw:balance:ok:{$mitra->id}",
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo demo awal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdraw_requests')->insert([
            'user_id' => $mitra->id,
            'amount' => 70000,
            'bank_name' => 'BCA',
            'account_number' => '123',
            'account_holder' => 'Mitra Demo',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdraw_bank_accounts')->insert([
            'user_id' => $mitra->id,
            'bank_name' => 'BCA',
            'account_number' => '123',
            'account_holder' => 'Mitra Demo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($mitra)
            ->from(route('landing'))
            ->post(route('wallet.withdraw.request'), [
                'amount' => 30000,
            ]);

        $response->assertRedirect(route('landing'));
        $response->assertSessionHas('status', 'Permintaan withdraw berhasil dibuat.');
        $this->assertSame(2, DB::table('withdraw_requests')->where('user_id', $mitra->id)->count());
    }

    public function test_mitra_can_update_finance_bank_profile_and_submit_withdraw(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 150000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => "test:withdraw:mitra:topup:{$mitra->id}",
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo mitra',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($mitra)
            ->from(route('mitra.finance'))
            ->patch(route('mitra.finance.bank.update'), [
                'bank_name' => 'Mandiri',
                'account_number' => '9876543210',
                'account_holder' => 'Mitra Finansial',
            ])
            ->assertRedirect(route('mitra.finance'))
            ->assertSessionHas('status', 'Data rekening withdraw berhasil disimpan.');

        $this->assertDatabaseHas('withdraw_bank_accounts', [
            'user_id' => $mitra->id,
            'bank_name' => 'Mandiri',
            'account_number' => '9876543210',
            'account_holder' => 'Mitra Finansial',
        ]);

        $this->actingAs($mitra)
            ->from(route('mitra.finance'))
            ->post(route('wallet.withdraw.request'), [
                'amount' => 50000,
            ])
            ->assertRedirect(route('mitra.finance'))
            ->assertSessionHas('status', 'Permintaan withdraw berhasil dibuat.');

        $this->assertDatabaseHas('withdraw_requests', [
            'user_id' => $mitra->id,
            'amount' => 50000,
            'bank_name' => 'Mandiri',
            'account_number' => '9876543210',
            'account_holder' => 'Mitra Finansial',
            'status' => 'pending',
        ]);
    }

    public function test_mitra_finance_bank_profile_update_rejects_partial_payload(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        $response = $this->actingAs($mitra)
            ->from(route('mitra.finance'))
            ->patch(route('mitra.finance.bank.update'), [
                'bank_name' => 'BCA',
                'account_number' => '',
                'account_holder' => '',
            ]);

        $response->assertRedirect(route('mitra.finance'));
        $response->assertSessionHasErrors(['account_number', 'account_holder']);
        $this->assertDatabaseMissing('withdraw_bank_accounts', [
            'user_id' => $mitra->id,
            'bank_name' => 'BCA',
        ]);
    }

    public function test_duplicate_withdraw_request_is_idempotent_within_short_window(): void
    {
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('wallet_transactions')->insert([
            'wallet_id' => $mitra->id,
            'amount' => 200000,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => "test:withdraw:idempotent:topup:{$mitra->id}",
            'reference_order_id' => null,
            'reference_withdraw_id' => null,
            'description' => 'Saldo mitra',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdraw_bank_accounts')->insert([
            'user_id' => $mitra->id,
            'bank_name' => 'BCA',
            'account_number' => '123456789',
            'account_holder' => 'Mitra Idempotent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = $this->actingAs($mitra)
            ->from(route('mitra.finance'))
            ->post(route('wallet.withdraw.request'), [
                'amount' => 50000,
            ]);
        $first->assertRedirect(route('mitra.finance'));
        $first->assertSessionHas('status', 'Permintaan withdraw berhasil dibuat.');

        $second = $this->actingAs($mitra)
            ->from(route('mitra.finance'))
            ->post(route('wallet.withdraw.request'), [
                'amount' => 50000,
            ]);
        $second->assertRedirect(route('mitra.finance'));
        $second->assertSessionHas('status', 'Permintaan withdraw serupa sudah dibuat sebelumnya.');

        $this->assertSame(1, DB::table('withdraw_requests')->where('user_id', $mitra->id)->count());
    }
}
