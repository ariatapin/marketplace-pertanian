<?php
/* =========================================================
 | 8) create_orders_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained('users');

            $table->string('order_source', 30); // store_online, store_offline, farmer_p2p
            $table->decimal('total_amount', 15, 2);

            $table->string('payment_status', 30)->default('unpaid'); // unpaid, paid, refunded, failed
            $table->string('order_status', 30)->default('pending_payment'); // pending_payment, paid, packed, shipped, completed, cancelled

            $table->text('payment_proof_url')->nullable();

            $table->string('shipping_status', 30)->default('pending'); // pending, shipped, delivered, returned, cancelled
            $table->string('resi_number', 120)->nullable();

            $table->timestamps();

            $table->index('buyer_id');
            $table->index('seller_id');
            $table->index('order_status');
            $table->index('payment_status');
        });

        DB::statement("ALTER TABLE orders
            ADD CONSTRAINT chk_orders_source
            CHECK (order_source IN ('store_online','store_offline','farmer_p2p'))");

        DB::statement("ALTER TABLE orders
            ADD CONSTRAINT chk_orders_total_amount
            CHECK (total_amount >= 0)");

        DB::statement("ALTER TABLE orders
            ADD CONSTRAINT chk_orders_payment_status
            CHECK (payment_status IN ('unpaid','paid','refunded','failed'))");

        DB::statement("ALTER TABLE orders
            ADD CONSTRAINT chk_orders_order_status
            CHECK (order_status IN ('pending_payment','paid','packed','shipped','completed','cancelled'))");

        DB::statement("ALTER TABLE orders
            ADD CONSTRAINT chk_orders_shipping_status
            CHECK (shipping_status IN ('pending','shipped','delivered','returned','cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_shipping_status");
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_order_status");
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_payment_status");
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_total_amount");
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS chk_orders_source");
        Schema::dropIfExists('orders');
    }
};
