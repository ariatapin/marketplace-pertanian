<?php

namespace App\Support\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

trait HandlesWalletLedgerMutation
{
    private function hasWalletLedgerColumns(): bool
    {
        return Schema::hasTable('wallet_transactions')
            && Schema::hasColumn('wallet_transactions', 'wallet_id')
            && Schema::hasColumn('wallet_transactions', 'amount')
            && Schema::hasColumn('wallet_transactions', 'transaction_type')
            && Schema::hasColumn('wallet_transactions', 'idempotency_key')
            && Schema::hasColumn('wallet_transactions', 'reference_order_id')
            && Schema::hasColumn('wallet_transactions', 'reference_withdraw_id')
            && Schema::hasColumn('wallet_transactions', 'description');
    }

    /**
     * @return array{has_idempotency_key:bool,has_reference_order:bool,has_reference_withdraw:bool}
     */
    private function walletLedgerCompatibilityFlags(): array
    {
        return [
            'has_idempotency_key' => Schema::hasColumn('wallet_transactions', 'idempotency_key'),
            'has_reference_order' => Schema::hasColumn('wallet_transactions', 'reference_order_id'),
            'has_reference_withdraw' => Schema::hasColumn('wallet_transactions', 'reference_withdraw_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureWalletLedgerMutation(
        array $payload,
        string $failureMessage = 'Ledger wallet tidak konsisten.'
    ): void {
        $inserted = DB::table('wallet_transactions')->insertOrIgnore($payload);
        if ((int) $inserted > 0) {
            return;
        }

        $exists = DB::table('wallet_transactions')
            ->where('wallet_id', (int) $payload['wallet_id'])
            ->where('transaction_type', (string) $payload['transaction_type'])
            ->where('idempotency_key', (string) $payload['idempotency_key'])
            ->where('reference_order_id', $payload['reference_order_id'])
            ->where('reference_withdraw_id', $payload['reference_withdraw_id'])
            ->where('amount', round((float) $payload['amount'], 2))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'wallet' => $failureMessage,
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function ensureWalletLedgerMutationCompat(
        array $payload,
        bool $hasIdempotencyKey,
        bool $hasReferenceOrder,
        bool $hasReferenceWithdraw,
        string $failureMessage
    ): void {
        if (! $hasIdempotencyKey) {
            DB::table('wallet_transactions')->insert($payload);
            return;
        }

        $inserted = DB::table('wallet_transactions')->insertOrIgnore($payload);
        if ((int) $inserted > 0) {
            return;
        }

        $query = DB::table('wallet_transactions')
            ->where('wallet_id', (int) $payload['wallet_id'])
            ->where('transaction_type', (string) $payload['transaction_type'])
            ->where('idempotency_key', (string) $payload['idempotency_key'])
            ->where('amount', round((float) $payload['amount'], 2));

        if ($hasReferenceOrder) {
            $query->where('reference_order_id', $payload['reference_order_id'] ?? null);
        }

        if ($hasReferenceWithdraw) {
            $query->where('reference_withdraw_id', $payload['reference_withdraw_id'] ?? null);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                'wallet' => $failureMessage,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function lockUsersForWalletMutation(array $userIds): void
    {
        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (count($ids) === 0) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get(['id']);
    }
}
