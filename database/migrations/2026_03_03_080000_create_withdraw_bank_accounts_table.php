<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('withdraw_bank_accounts')) {
            Schema::create('withdraw_bank_accounts', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->primary()
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('bank_name', 120)->nullable();
                $table->string('account_number', 120)->nullable();
                $table->string('account_holder', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('farmer_profiles') || ! Schema::hasTable('withdraw_bank_accounts')) {
            return;
        }

        $legacyRows = DB::table('farmer_profiles')->get([
            'user_id',
            'bank_name',
            'account_number',
            'account_holder',
            'created_at',
            'updated_at',
        ]);

        foreach ($legacyRows as $row) {
            DB::table('withdraw_bank_accounts')->insertOrIgnore([
                'user_id' => (int) $row->user_id,
                'bank_name' => $row->bank_name,
                'account_number' => $row->account_number,
                'account_holder' => $row->account_holder,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_bank_accounts');
    }
};
