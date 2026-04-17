<?php
/* =========================================================
 | 13) create_shipments_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();

            $table->string('courier', 100)->nullable();
            $table->string('service', 100)->nullable();
            $table->string('tracking_number', 120)->nullable();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->string('status', 30)->default('pending'); // pending, shipped, delivered, returned, cancelled
            $table->timestamps();

            $table->index('status');
            $table->index('tracking_number');
        });

        DB::statement("ALTER TABLE shipments
            ADD CONSTRAINT chk_shipments_status
            CHECK (status IN ('pending','shipped','delivered','returned','cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE shipments DROP CONSTRAINT IF EXISTS chk_shipments_status");
        Schema::dropIfExists('shipments');
    }
};
