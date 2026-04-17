<?php

namespace App\Support;

use App\Models\User;
use App\Support\Concerns\HandlesWalletLedgerMutation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoUserProvisioner
{
    use HandlesWalletLedgerMutation;

    public function ensureUsers(): void
    {
        $demoUsers = [
            [
                'name' => 'Admin System',
                'email' => 'admin@demo.test',
                'role' => 'admin',
                'phone_number' => '081111111111',
                'demo_wallet_balance' => 350000000,
            ],
        ];

        if ((bool) config('demo.seed_legacy_users', false)) {
            $demoUsers = array_merge($demoUsers, [
                [
                    'name' => 'Mitra Toko Sumber Rejeki',
                    'email' => 'mitra@demo.test',
                    'role' => 'mitra',
                    'phone_number' => '082222222222',
                    'demo_wallet_balance' => 125000000,
                ],
                [
                    'name' => 'Petani Penjual',
                    'email' => 'petani.penjual@demo.test',
                    'role' => 'consumer',
                    'phone_number' => '083333333333',
                    'demo_wallet_balance' => 45000000,
                ],
                [
                    'name' => 'Petani Affiliate',
                    'email' => 'petani.affiliate@demo.test',
                    'role' => 'consumer',
                    'phone_number' => '084444444444',
                    'demo_wallet_balance' => 30000000,
                ],
                [
                    'name' => 'Petani Consumer',
                    'email' => 'petani.consumer@demo.test',
                    'role' => 'consumer',
                    'phone_number' => '085555555555',
                    'demo_wallet_balance' => 15000000,
                ],
            ]);
        }

        foreach ($demoUsers as $item) {
            $user = User::query()
                ->where('email', $item['email'])
                ->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $item['name'],
                    'email' => $item['email'],
                    'role' => $item['role'],
                    'phone_number' => $item['phone_number'],
                    'password' => Hash::make('password'),
                ]);
            } else {
                $payload = [
                    'name' => $item['name'],
                    'role' => $item['role'],
                    'phone_number' => $item['phone_number'],
                ];

                if (! Hash::check('password', (string) $user->password)) {
                    $payload['password'] = Hash::make('password');
                }

                $user->fill($payload);
                if ($user->isDirty()) {
                    $user->save();
                }
            }

            $this->ensureDemoWalletBalance(
                $user,
                (float) ($item['demo_wallet_balance'] ?? 0)
            );
        }
    }

    private function ensureDemoWalletBalance(User $user, float $amount): void
    {
        if ($amount <= 0 || ! Schema::hasTable('wallet_transactions')) {
            return;
        }

        $ledgerFlags = $this->walletLedgerCompatibilityFlags();
        $hasIdempotencyKey = (bool) ($ledgerFlags['has_idempotency_key'] ?? false);
        $hasReferenceOrder = (bool) ($ledgerFlags['has_reference_order'] ?? false);
        $hasReferenceWithdraw = (bool) ($ledgerFlags['has_reference_withdraw'] ?? false);

        $payload = [
            'wallet_id' => (int) $user->id,
            'amount' => $amount,
            'transaction_type' => 'demo_topup',
            'description' => 'Topup saldo demo awal',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($hasReferenceOrder) {
            $payload['reference_order_id'] = null;
        }

        if ($hasReferenceWithdraw) {
            $payload['reference_withdraw_id'] = null;
        }

        if ($hasIdempotencyKey) {
            $payload['idempotency_key'] = "demo:wallet:initial:user:{$user->id}";
            DB::table('wallet_transactions')->insertOrIgnore($payload);
            return;
        }

        $exists = DB::table('wallet_transactions')
            ->where('wallet_id', (int) $user->id)
            ->where('transaction_type', 'demo_topup')
            ->exists();

        if (! $exists) {
            DB::table('wallet_transactions')->insert($payload);
        }
    }
}
