<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('admin_profiles')) {
            return;
        }

        Schema::table('admin_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_profiles', 'affiliate_commission_min_percent')) {
                $table->decimal('affiliate_commission_min_percent', 5, 2)
                    ->default(0.00)
                    ->after('affiliate_fee_percent');
            }

            if (! Schema::hasColumn('admin_profiles', 'affiliate_commission_max_percent')) {
                $table->decimal('affiliate_commission_max_percent', 5, 2)
                    ->default(100.00)
                    ->after('affiliate_commission_min_percent');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_affiliate_commission_min');
            DB::statement('ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_affiliate_commission_max');
            DB::statement('ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_affiliate_commission_range');

            DB::statement("ALTER TABLE admin_profiles
                ADD CONSTRAINT chk_admin_profiles_affiliate_commission_min
                CHECK (affiliate_commission_min_percent >= 0 AND affiliate_commission_min_percent <= 100)");

            DB::statement("ALTER TABLE admin_profiles
                ADD CONSTRAINT chk_admin_profiles_affiliate_commission_max
                CHECK (affiliate_commission_max_percent >= 0 AND affiliate_commission_max_percent <= 100)");

            DB::statement("ALTER TABLE admin_profiles
                ADD CONSTRAINT chk_admin_profiles_affiliate_commission_range
                CHECK (affiliate_commission_max_percent >= affiliate_commission_min_percent)");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_profiles')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_affiliate_commission_range');
            DB::statement('ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_affiliate_commission_max');
            DB::statement('ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_affiliate_commission_min');
        }

        Schema::table('admin_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('admin_profiles', 'affiliate_commission_max_percent')) {
                $table->dropColumn('affiliate_commission_max_percent');
            }

            if (Schema::hasColumn('admin_profiles', 'affiliate_commission_min_percent')) {
                $table->dropColumn('affiliate_commission_min_percent');
            }
        });
    }
};
