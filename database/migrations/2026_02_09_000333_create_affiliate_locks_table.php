<?php
/* =========================================================
 | 11) create_affiliate_locks_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('affiliate_locks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('affiliate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('expiry_date');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('affiliate_id');
            $table->index('product_id');
            $table->index('is_active');
        });

        DB::statement("ALTER TABLE affiliate_locks
            ADD CONSTRAINT chk_affiliate_locks_dates
            CHECK (expiry_date >= start_date)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE affiliate_locks DROP CONSTRAINT IF EXISTS chk_affiliate_locks_dates");
        Schema::dropIfExists('affiliate_locks');
    }
};
