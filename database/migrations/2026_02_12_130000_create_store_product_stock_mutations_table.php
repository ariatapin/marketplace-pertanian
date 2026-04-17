<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_product_stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_product_id')->nullable()->constrained('store_products')->nullOnDelete();
            $table->string('product_name');
            $table->string('change_type', 30);
            $table->integer('qty_before');
            $table->integer('qty_delta');
            $table->integer('qty_after');
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at');

            $table->index(['mitra_id', 'created_at']);
            $table->index(['store_product_id', 'created_at']);
            $table->index('change_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_stock_mutations');
    }
};
