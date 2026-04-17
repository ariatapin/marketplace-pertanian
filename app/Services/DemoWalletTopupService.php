<?php

namespace App\Services;

use App\Models\User;
use App\Support\Concerns\GuardsFinanceDemoMode;
use App\Support\Concerns\HandlesWalletLedgerMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DemoWalletTopupService
{
    use GuardsFinanceDemoMode;
    use HandlesWalletLedgerMutation;

    /**
     * Topup demo dibuka untuk semua user login (saldo simulasi).
     */
    public function canUse(User $user): bool
    {
        return (int) ($user->id ?? 0) > 0;
    }

    /**
     * Menambah saldo demo ke wallet dengan transaksi atomik + idempotency key.
     *
     * @return array{inserted:bool,balance:float,amount:float}
     */
    public function topup(User $user, float $amount, string $idempotencyKey): array
    {
        $this->assertFinanceDemoModeEnabled(
            errorKey: 'topup',
            message: 'Topup saldo demo sedang dinonaktifkan.'
        );

        if (! $this->canUse($user)) {
            throw ValidationException::withMessages([
                'topup' => 'Topup demo hanya tersedia untuk akun demo.',
            ]);
        }

        if (! Schema::hasTable('wallet_transactions')) {
            throw ValidationException::withMessages([
                'topup' => 'Tabel wallet belum tersedia.',
            ]);
        }

        $topupAmount = round($amount, 2);
        if ($topupAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal topup harus lebih dari 0.',
            ]);
        }

        $safeIdempotencyKey = trim($idempotencyKey);
        if ($safeIdempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key topup tidak valid.',
            ]);
        }

        return DB::transaction(function () use ($user, $topupAmount, $safeIdempotencyKey): array {
            DB::table('users')
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first(['id']);

            $ledgerFlags = $this->walletLedgerCompatibilityFlags();
            $hasIdempotencyKey = (bool) ($ledgerFlags['has_idempotency_key'] ?? false);
            $hasReferenceOrder = (bool) ($ledgerFlags['has_reference_order'] ?? false);
            $hasReferenceWithdraw = (bool) ($ledgerFlags['has_reference_withdraw'] ?? false);

            $payload = [
                'wallet_id' => (int) $user->id,
                'amount' => $topupAmount,
                'transaction_type' => 'demo_topup',
                'description' => 'Topup saldo demo manual',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($hasReferenceOrder) {
                $payload['reference_order_id'] = null;
            }

            if ($hasReferenceWithdraw) {
                $payload['reference_withdraw_id'] = null;
            }

            $inserted = true;
            if ($hasIdempotencyKey) {
                $payload['idempotency_key'] = $safeIdempotencyKey;
                $inserted = DB::table('wallet_transactions')->insertOrIgnore($payload) > 0;
            } else {
                DB::table('wallet_transactions')->insert($payload);
            }

            $balance = (float) DB::table('wallet_transactions')
                ->where('wallet_id', (int) $user->id)
                ->sum('amount');

            return [
                'inserted' => $inserted,
                'balance' => $balance,
                'amount' => $topupAmount,
            ];
        });
    }
}
