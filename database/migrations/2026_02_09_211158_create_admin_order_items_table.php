<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_order_id')->constrained('admin_orders')->cascadeOnDelete();
            $table->foreignId('admin_product_id')->constrained('admin_products')->cascadeOnDelete();

            $table->string('product_name'); // snapshot
            $table->decimal('price_per_unit', 15, 2);
            $table->integer('qty');
            $table->timestamps();

            $table->index('admin_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_order_items');
    }
};
