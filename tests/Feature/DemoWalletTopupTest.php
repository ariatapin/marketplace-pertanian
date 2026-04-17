<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoWalletTopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_can_topup_wallet_with_idempotency_key(): void
    {
        $demoUser = User::factory()->create([
            'role' => 'consumer',
            'email' => 'demo.consumer@demo.test',
        ]);

        $idempotencyKey = 'test:demo-topup:' . uniqid();

        $this->actingAs($demoUser)
            ->post(route('wallet.demo-topup'), [
                'amount' => 250000,
                'idempotency_key' => $idempotencyKey,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $demoUser->id,
            'transaction_type' => 'demo_topup',
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->actingAs($demoUser)
            ->post(route('wallet.demo-topup'), [
                'amount' => 250000,
                'idempotency_key' => $idempotencyKey,
            ])
            ->assertRedirect();

        $count = DB::table('wallet_transactions')
            ->where('wallet_id', $demoUser->id)
            ->where('transaction_type', 'demo_topup')
            ->where('idempotency_key', $idempotencyKey)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_non_demo_user_can_topup_demo_wallet(): void
    {
        $normalUser = User::factory()->create([
            'role' => 'consumer',
            'email' => 'normal.user@example.test',
        ]);

        $this->actingAs($normalUser)
            ->post(route('wallet.demo-topup'), [
                'amount' => 100000,
                'idempotency_key' => 'test:non-demo:' . uniqid(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $normalUser->id,
            'transaction_type' => 'demo_topup',
        ]);
    }

    public function test_demo_topup_is_blocked_when_finance_demo_mode_is_disabled(): void
    {
        config()->set('finance.demo_mode', false);

        $user = User::factory()->create([
            'role' => 'consumer',
            'email' => 'demo.blocked@example.test',
        ]);

        $response = $this->actingAs($user)
            ->from('/wallet')
            ->post(route('wallet.demo-topup'), [
                'amount' => 100000,
                'idempotency_key' => 'test:demo-topup:blocked:' . uniqid(),
            ]);

        $response->assertRedirect('/wallet');
        $response->assertSessionHasErrors('topup');

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $user->id,
            'transaction_type' => 'demo_topup',
        ]);
    }
}
