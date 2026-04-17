<?php
/* =========================================================
 | 2) create_consumer_profiles_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consumer_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->primary()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('address')->nullable();

            // NEW FLOW COLUMNS
            $table->string('mode', 20)->default('buyer');          // buyer, affiliate, farmer_seller
            $table->string('mode_status', 20)->default('none');    // none, pending, approved, rejected
            $table->string('requested_mode', 20)->nullable();      // affiliate, farmer_seller

            $table->timestamps();

            $table->index('mode');
            $table->index('mode_status');
            $table->index('requested_mode');
        });

        // Postgres CHECK constraints (recommended)
        DB::statement("ALTER TABLE consumer_profiles
            ADD CONSTRAINT chk_consumer_profiles_mode
            CHECK (mode IN ('buyer','affiliate','farmer_seller'))");

        DB::statement("ALTER TABLE consumer_profiles
            ADD CONSTRAINT chk_consumer_profiles_mode_status
            CHECK (mode_status IN ('none','pending','approved','rejected'))");

        DB::statement("ALTER TABLE consumer_profiles
            ADD CONSTRAINT chk_consumer_profiles_requested_mode
            CHECK (requested_mode IS NULL OR requested_mode IN ('affiliate','farmer_seller'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE consumer_profiles DROP CONSTRAINT IF EXISTS chk_consumer_profiles_requested_mode");
        DB::statement("ALTER TABLE consumer_profiles DROP CONSTRAINT IF EXISTS chk_consumer_profiles_mode_status");
        DB::statement("ALTER TABLE consumer_profiles DROP CONSTRAINT IF EXISTS chk_consumer_profiles_mode");

        Schema::dropIfExists('consumer_profiles');
    }
};
