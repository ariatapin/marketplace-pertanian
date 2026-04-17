<?php
/* =========================================================
 | 6) create_farmer_harvests_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('farmer_harvests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->integer('stock_qty')->default(0);
            $table->date('harvest_date')->nullable();
            $table->text('image_url')->nullable();
            $table->string('status', 30)->default('pending'); // pending, approved, rejected

            $table->timestamps();

            $table->index('farmer_id');
            $table->index('status');
            $table->index('name');
        });

        DB::statement("ALTER TABLE farmer_harvests
            ADD CONSTRAINT chk_farmer_harvests_status
            CHECK (status IN ('pending','approved','rejected'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE farmer_harvests DROP CONSTRAINT IF EXISTS chk_farmer_harvests_status");
        Schema::dropIfExists('farmer_harvests');
    }
};
