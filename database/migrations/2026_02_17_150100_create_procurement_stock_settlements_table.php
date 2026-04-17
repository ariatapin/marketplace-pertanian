<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_stock_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_order_id')->constrained('admin_orders')->cascadeOnDelete();
            $table->foreignId('mitra_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('settled_by_role', 30)->nullable();
            $table->integer('line_count')->default(0);
            $table->integer('total_qty')->default(0);
            $table->timestamp('settled_at');
            $table->timestamps();

            $table->unique('admin_order_id', 'procurement_stock_settlements_admin_order_unique');
            $table->index(['mitra_id', 'settled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_stock_settlements');
    }
};
