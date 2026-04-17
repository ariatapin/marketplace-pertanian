<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0);

            // pending, approved, processing, shipped, delivered, cancelled
            $table->string('status', 20)->default('pending');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('mitra_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE admin_orders
            ADD CONSTRAINT chk_admin_orders_status
            CHECK (status IN ('pending','approved','processing','shipped','delivered','cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE admin_orders DROP CONSTRAINT IF EXISTS chk_admin_orders_status");
        Schema::dropIfExists('admin_orders');
    }
};
