<?php
/* =========================================================
 | create_admin_profiles_table.php
 | Admin profile untuk setting marketplace (escrow, fee, payout)
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->primary()
                ->constrained('users')
                ->cascadeOnDelete();

            // Identitas platform
            $table->string('platform_name')->default('Marketplace App');

            // Fee & komisi (persentase)
            $table->decimal('platform_fee_percent', 5, 2)->default(5.00);
            $table->decimal('affiliate_fee_percent', 5, 2)->default(2.00);

            // Rekening escrow / penampung dana platform
            $table->string('escrow_bank_name', 120)->nullable();
            $table->string('escrow_account_number', 120)->nullable();
            $table->string('escrow_account_holder', 255)->nullable();

            // Aturan payout
            $table->decimal('min_withdraw_amount', 15, 2)->default(50000);
            $table->integer('payout_delay_days')->default(2); // contoh: H+2 sejak order selesai
            $table->boolean('auto_payout_enabled')->default(false);

            // Operasional
            $table->string('default_courier', 100)->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 50)->nullable();

            $table->timestamps();

            $table->index('auto_payout_enabled');
        });

        // Optional CHECK constraints (Postgres)
        DB::statement("ALTER TABLE admin_profiles
            ADD CONSTRAINT chk_admin_profiles_platform_fee
            CHECK (platform_fee_percent >= 0 AND platform_fee_percent <= 100)");

        DB::statement("ALTER TABLE admin_profiles
            ADD CONSTRAINT chk_admin_profiles_affiliate_fee
            CHECK (affiliate_fee_percent >= 0 AND affiliate_fee_percent <= 100)");

        DB::statement("ALTER TABLE admin_profiles
            ADD CONSTRAINT chk_admin_profiles_min_withdraw
            CHECK (min_withdraw_amount >= 0)");

        DB::statement("ALTER TABLE admin_profiles
            ADD CONSTRAINT chk_admin_profiles_payout_delay
            CHECK (payout_delay_days >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_payout_delay");
        DB::statement("ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_min_withdraw");
        DB::statement("ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_affiliate_fee");
        DB::statement("ALTER TABLE admin_profiles DROP CONSTRAINT IF EXISTS chk_admin_profiles_platform_fee");

        Schema::dropIfExists('admin_profiles');
    }
};
