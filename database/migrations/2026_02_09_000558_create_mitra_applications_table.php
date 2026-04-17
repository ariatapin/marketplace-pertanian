<?php
/* =========================================================
 | 16) create_mitra_applications_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mitra_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('full_name');
            $table->string('email');
            $table->integer('region_id')->nullable();

            $table->text('ktp_url')->nullable();
            $table->text('npwp_url')->nullable();
            $table->text('nib_url')->nullable();

            $table->text('warehouse_address')->nullable();
            $table->decimal('warehouse_lat', 10, 7)->nullable();
            $table->decimal('warehouse_lng', 10, 7)->nullable();
            $table->text('warehouse_building_photo_url')->nullable();

            $table->text('products_managed')->nullable();
            $table->integer('warehouse_capacity')->nullable();
            $table->text('special_certification_url')->nullable();

            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('region_id');
        });

        DB::statement("ALTER TABLE mitra_applications
            ADD CONSTRAINT chk_mitra_applications_status
            CHECK (status IN ('pending','approved','rejected'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mitra_applications DROP CONSTRAINT IF EXISTS chk_mitra_applications_status");
        Schema::dropIfExists('mitra_applications');
    }
};
