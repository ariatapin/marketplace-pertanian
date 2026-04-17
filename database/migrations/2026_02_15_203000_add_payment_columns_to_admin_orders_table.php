<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_orders', 'payment_status')) {
                $table->string('payment_status', 30)->default('unpaid')->after('status');
                $table->index('payment_status');
            }

            if (! Schema::hasColumn('admin_orders', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('admin_orders', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('admin_orders', 'payment_proof_url')) {
                $table->text('payment_proof_url')->nullable()->after('paid_amount');
            }

            if (! Schema::hasColumn('admin_orders', 'payment_submitted_at')) {
                $table->timestamp('payment_submitted_at')->nullable()->after('payment_proof_url');
            }

            if (! Schema::hasColumn('admin_orders', 'payment_verified_at')) {
                $table->timestamp('payment_verified_at')->nullable()->after('payment_submitted_at');
            }

            if (! Schema::hasColumn('admin_orders', 'payment_verified_by')) {
                $table->foreignId('payment_verified_by')
                    ->nullable()
                    ->after('payment_verified_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('admin_orders', 'payment_note')) {
                $table->string('payment_note', 255)->nullable()->after('payment_verified_by');
            }
        });

        DB::statement("ALTER TABLE admin_orders
            ADD CONSTRAINT chk_admin_orders_payment_status
            CHECK (payment_status IN ('unpaid','pending_verification','paid','rejected'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE admin_orders DROP CONSTRAINT IF EXISTS chk_admin_orders_payment_status");

        Schema::table('admin_orders', function (Blueprint $table) {
            if (Schema::hasColumn('admin_orders', 'payment_note')) {
                $table->dropColumn('payment_note');
            }

            if (Schema::hasColumn('admin_orders', 'payment_verified_by')) {
                $table->dropConstrainedForeignId('payment_verified_by');
            }

            if (Schema::hasColumn('admin_orders', 'payment_verified_at')) {
                $table->dropColumn('payment_verified_at');
            }

            if (Schema::hasColumn('admin_orders', 'payment_submitted_at')) {
                $table->dropColumn('payment_submitted_at');
            }

            if (Schema::hasColumn('admin_orders', 'payment_proof_url')) {
                $table->dropColumn('payment_proof_url');
            }

            if (Schema::hasColumn('admin_orders', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }

            if (Schema::hasColumn('admin_orders', 'payment_method')) {
                $table->dropColumn('payment_method');
            }

            if (Schema::hasColumn('admin_orders', 'payment_status')) {
                $table->dropIndex(['payment_status']);
                $table->dropColumn('payment_status');
            }
        });
    }
};
