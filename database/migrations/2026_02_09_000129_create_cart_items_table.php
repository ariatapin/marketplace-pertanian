<?php
/* =========================================================
 | 7) create_cart_items_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('product_type', 20); // store/farmer
            $table->unsignedBigInteger('product_id');
            $table->integer('qty');

            $table->foreignId('affiliate_referral_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('user_id');
            $table->index(['product_type', 'product_id']);
            $table->index('affiliate_referral_id');
        });

        DB::statement("ALTER TABLE cart_items
            ADD CONSTRAINT chk_cart_items_product_type
            CHECK (product_type IN ('store','farmer'))");

        DB::statement("ALTER TABLE cart_items
            ADD CONSTRAINT chk_cart_items_qty
            CHECK (qty > 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cart_items DROP CONSTRAINT IF EXISTS chk_cart_items_qty");
        DB::statement("ALTER TABLE cart_items DROP CONSTRAINT IF EXISTS chk_cart_items_product_type");
        Schema::dropIfExists('cart_items');
    }
};
