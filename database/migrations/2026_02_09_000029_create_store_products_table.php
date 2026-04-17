<?php
/* =========================================================
 | 5) create_store_products_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->integer('stock_qty')->default(0);
            $table->text('image_url')->nullable();

            $table->boolean('is_affiliate_enabled')->default(false);
            $table->decimal('affiliate_commission', 15, 2)->default(0);

            $table->timestamps();

            $table->index('mitra_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_products');
    }
};
