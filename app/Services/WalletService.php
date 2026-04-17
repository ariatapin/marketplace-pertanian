<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getBalance(int $userId): float
    {
        return (float) DB::table('wallet_transactions')
            ->where('wallet_id', $userId)
            ->sum('amount');
    }
}
