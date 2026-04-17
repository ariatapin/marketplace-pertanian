<?php
/* =========================================================
 | 18) create_farmer_seller_applications_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('farmer_seller_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->string('full_name');
            $table->string('email');
            $table->integer('region_id')->nullable();

            $table->text('ktp_url')->nullable();
            $table->text('selfie_url')->nullable();
            $table->text('bankbook_photo_url')->nullable();

            $table->text('land_location')->nullable();
            $table->decimal('land_lat', 10, 7)->nullable();
            $table->decimal('land_lng', 10, 7)->nullable();
            $table->text('land_photo_url')->nullable();
            $table->decimal('estimated_land_area', 12, 2)->nullable();
            $table->jsonb('main_commodities')->nullable();

            $table->text('pickup_address')->nullable();
            $table->boolean('has_scale')->default(false);

            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('region_id');
        });

        DB::statement("ALTER TABLE farmer_seller_applications
            ADD CONSTRAINT chk_farmer_seller_applications_status
            CHECK (status IN ('pending','approved','rejected'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE farmer_seller_applications DROP CONSTRAINT IF EXISTS chk_farmer_seller_applications_status");
        Schema::dropIfExists('farmer_seller_applications');
    }
};
