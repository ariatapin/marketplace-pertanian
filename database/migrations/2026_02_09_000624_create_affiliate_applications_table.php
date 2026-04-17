<?php
/* =========================================================
 | 17) create_affiliate_applications_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('affiliate_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('full_name');
            $table->string('email');
            $table->integer('region_id')->nullable();

            $table->text('ktp_url')->nullable();
            $table->text('selfie_url')->nullable();

            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 120)->nullable();
            $table->string('account_holder', 255)->nullable();

            $table->boolean('is_auto_approved')->default(false);

            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('region_id');
        });

        DB::statement("ALTER TABLE affiliate_applications
            ADD CONSTRAINT chk_affiliate_applications_status
            CHECK (status IN ('pending','approved','rejected'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE affiliate_applications DROP CONSTRAINT IF EXISTS chk_affiliate_applications_status");
        Schema::dropIfExists('affiliate_applications');
    }
};
