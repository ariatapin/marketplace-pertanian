<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS wallet_transactions_order_wallet_type_settlement_unique
            ON wallet_transactions (reference_order_id, wallet_id, transaction_type)
            WHERE reference_order_id IS NOT NULL
              AND transaction_type IN ('escrow_in', 'platform_fee', 'sale_revenue', 'affiliate_commission')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS wallet_transactions_order_wallet_type_settlement_unique');
    }
};

