<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('withdraw_requests')) {
            return;
        }

        $hasPaidBy = Schema::hasColumn('withdraw_requests', 'paid_by');
        $hasPaidAt = Schema::hasColumn('withdraw_requests', 'paid_at');

        if ($hasPaidBy && $hasPaidAt) {
            return;
        }

        Schema::table('withdraw_requests', function (Blueprint $table) use ($hasPaidBy, $hasPaidAt) {
            if (! $hasPaidBy) {
                $table->foreignId('paid_by')
                    ->nullable()
                    ->after('processed_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! $hasPaidAt) {
                $table->timestamp('paid_at')
                    ->nullable()
                    ->after('processed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('withdraw_requests')) {
            return;
        }

        $hasPaidBy = Schema::hasColumn('withdraw_requests', 'paid_by');
        $hasPaidAt = Schema::hasColumn('withdraw_requests', 'paid_at');

        if (! $hasPaidBy && ! $hasPaidAt) {
            return;
        }

        Schema::table('withdraw_requests', function (Blueprint $table) use ($hasPaidBy, $hasPaidAt) {
            if ($hasPaidBy) {
                $table->dropConstrainedForeignId('paid_by');
            }

            if ($hasPaidAt) {
                $table->dropColumn('paid_at');
            }
        });
    }
};

