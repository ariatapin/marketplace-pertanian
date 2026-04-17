<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WithdrawBankAccountService
{
    private const PRIMARY_TABLE = 'withdraw_bank_accounts';
    private const LEGACY_TABLE = 'farmer_profiles';

    /**
     * @return array{
     *   bank_name:?string,
     *   account_number:?string,
     *   account_holder:?string,
     *   complete:bool,
     *   updated_at:mixed
     * }
     */
    public function snapshot(int $userId): array
    {
        if ($userId <= 0) {
            return $this->emptySnapshot();
        }

        $primary = $this->fetchSnapshotFromTable(self::PRIMARY_TABLE, $userId);
        if ($primary !== null) {
            return $primary;
        }

        $legacy = $this->fetchSnapshotFromTable(self::LEGACY_TABLE, $userId);
        if ($legacy === null) {
            return $this->emptySnapshot();
        }

        if (Schema::hasTable(self::PRIMARY_TABLE)) {
            $this->upsert(
                $userId,
                $legacy['bank_name'],
                $legacy['account_number'],
                $legacy['account_holder']
            );
        }

        return $legacy;
    }

    /**
     * @return array{
     *   bank_name:?string,
     *   account_number:?string,
     *   account_holder:?string,
     *   complete:bool,
     *   filled_count:int
     * }
     */
    public function normalizeInput(
        ?string $bankName,
        ?string $accountNumber,
        ?string $accountHolder
    ): array {
        $normalizedBankName = $this->nullIfEmpty($bankName);
        $normalizedAccountNumber = $this->nullIfEmpty($accountNumber);
        $normalizedAccountHolder = $this->nullIfEmpty($accountHolder);

        $filledCount = 0;
        foreach ([$normalizedBankName, $normalizedAccountNumber, $normalizedAccountHolder] as $value) {
            if ($value !== null) {
                $filledCount++;
            }
        }

        return [
            'bank_name' => $normalizedBankName,
            'account_number' => $normalizedAccountNumber,
            'account_holder' => $normalizedAccountHolder,
            'complete' => $filledCount === 3,
            'filled_count' => $filledCount,
        ];
    }

    public function upsert(
        int $userId,
        ?string $bankName,
        ?string $accountNumber,
        ?string $accountHolder
    ): void {
        if ($userId <= 0) {
            return;
        }

        $normalized = $this->normalizeInput($bankName, $accountNumber, $accountHolder);
        $payload = [
            'bank_name' => $normalized['bank_name'],
            'account_number' => $normalized['account_number'],
            'account_holder' => $normalized['account_holder'],
            'updated_at' => now(),
        ];

        $this->upsertToTable(self::PRIMARY_TABLE, $userId, $payload);
        $this->upsertToTable(self::LEGACY_TABLE, $userId, $payload);
    }

    public function hasStorage(): bool
    {
        return Schema::hasTable(self::PRIMARY_TABLE) || Schema::hasTable(self::LEGACY_TABLE);
    }

    /**
     * @return array{
     *   bank_name:?string,
     *   account_number:?string,
     *   account_holder:?string,
     *   complete:bool,
     *   updated_at:mixed
     * }|null
     */
    private function fetchSnapshotFromTable(string $table, int $userId): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $row = DB::table($table)
            ->where('user_id', $userId)
            ->first(['bank_name', 'account_number', 'account_holder', 'updated_at']);

        if (! $row) {
            return null;
        }

        $bankName = $this->nullIfEmpty($row->bank_name ?? null);
        $accountNumber = $this->nullIfEmpty($row->account_number ?? null);
        $accountHolder = $this->nullIfEmpty($row->account_holder ?? null);

        $complete = $bankName !== null && $accountNumber !== null && $accountHolder !== null;

        return [
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'account_holder' => $accountHolder,
            'complete' => $complete,
            'updated_at' => $row->updated_at ?? null,
        ];
    }

    /**
     * @param  array{bank_name:?string,account_number:?string,account_holder:?string,updated_at:mixed}  $payload
     */
    private function upsertToTable(string $table, int $userId, array $payload): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $exists = DB::table($table)->where('user_id', $userId)->exists();
        if ($exists) {
            DB::table($table)
                ->where('user_id', $userId)
                ->update($payload);
            return;
        }

        DB::table($table)->insert($payload + [
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{
     *   bank_name:?string,
     *   account_number:?string,
     *   account_holder:?string,
     *   complete:bool,
     *   updated_at:mixed
     * }
     */
    private function emptySnapshot(): array
    {
        return [
            'bank_name' => null,
            'account_number' => null,
            'account_holder' => null,
            'complete' => false,
            'updated_at' => null,
        ];
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }
}
