<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('store_product_images')) {
            Schema::create('store_product_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_product_id')->constrained('store_products')->cascadeOnDelete();
                $table->text('image_url');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['store_product_id', 'sort_order'], 'store_product_images_product_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_images');
    }
};
