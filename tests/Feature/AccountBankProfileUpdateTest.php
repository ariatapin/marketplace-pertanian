<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBankProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_update_account_bank_profile(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        $response = $this->actingAs($consumer)
            ->from(route('account.show'))
            ->patch(route('account.bank.update'), [
                'bank_name' => 'BRI',
                'account_number' => '111222333',
                'account_holder' => 'Consumer Demo',
            ]);

        $response->assertRedirect(route('account.show'));
        $response->assertSessionHas('status', 'Data rekening berhasil disimpan.');
        $this->assertDatabaseHas('withdraw_bank_accounts', [
            'user_id' => $consumer->id,
            'bank_name' => 'BRI',
            'account_number' => '111222333',
            'account_holder' => 'Consumer Demo',
        ]);
    }

    public function test_consumer_account_bank_profile_rejects_partial_payload(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        $response = $this->actingAs($consumer)
            ->from(route('account.show'))
            ->patch(route('account.bank.update'), [
                'bank_name' => 'BRI',
                'account_number' => '',
                'account_holder' => '',
            ]);

        $response->assertRedirect(route('account.show'));
        $response->assertSessionHasErrors(['account_number', 'account_holder']);
        $this->assertDatabaseMissing('withdraw_bank_accounts', [
            'user_id' => $consumer->id,
            'bank_name' => 'BRI',
        ]);
    }
}
