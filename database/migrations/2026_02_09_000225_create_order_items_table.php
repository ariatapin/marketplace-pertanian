<?php
/* =========================================================
 | 9) create_order_items_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->integer('qty');
            $table->decimal('price_per_unit', 15, 2);

            $table->foreignId('affiliate_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('commission_amount', 15, 2)->default(0);

            $table->timestamps();

            $table->index('order_id');
            $table->index('affiliate_id');
        });

        DB::statement("ALTER TABLE order_items
            ADD CONSTRAINT chk_order_items_qty CHECK (qty > 0)");

        DB::statement("ALTER TABLE order_items
            ADD CONSTRAINT chk_order_items_price CHECK (price_per_unit >= 0)");

        DB::statement("ALTER TABLE order_items
            ADD CONSTRAINT chk_order_items_commission CHECK (commission_amount >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE order_items DROP CONSTRAINT IF EXISTS chk_order_items_commission");
        DB::statement("ALTER TABLE order_items DROP CONSTRAINT IF EXISTS chk_order_items_price");
        DB::statement("ALTER TABLE order_items DROP CONSTRAINT IF EXISTS chk_order_items_qty");
        Schema::dropIfExists('order_items');
    }
};
