<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 160)->nullable()->after('transaction_type');

            $table->foreignId('reference_withdraw_id')
                ->nullable()
                ->after('reference_order_id')
                ->constrained('withdraw_requests')
                ->nullOnDelete();

            $table->unique('idempotency_key', 'wallet_transactions_idempotency_key_unique');
            $table->unique(
                ['reference_withdraw_id', 'wallet_id', 'transaction_type'],
                'wallet_transactions_withdraw_wallet_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('wallet_transactions_withdraw_wallet_type_unique');
            $table->dropUnique('wallet_transactions_idempotency_key_unique');
            $table->dropConstrainedForeignId('reference_withdraw_id');
            $table->dropColumn('idempotency_key');
        });
    }
};

