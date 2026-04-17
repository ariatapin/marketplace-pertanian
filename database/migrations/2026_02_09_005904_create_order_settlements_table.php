<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_settlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('buyer_id')->constrained('users');

            // snapshot pembagian
            $table->decimal('gross_amount', 15, 2);        // total order
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->decimal('affiliate_commission', 15, 2)->default(0);
            $table->decimal('net_to_seller', 15, 2)->default(0);

            // status settlement
            $table->string('status', 20)->default('pending'); // pending, ready, paid, refunded

            // kapan dianggap “ready to payout” (misal setelah delivered + delay)
            $table->timestamp('eligible_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('seller_id');
        });

        DB::statement("ALTER TABLE order_settlements
            ADD CONSTRAINT chk_order_settlements_status
            CHECK (status IN ('pending','ready','paid','refunded'))");

        DB::statement("ALTER TABLE order_settlements
            ADD CONSTRAINT chk_order_settlements_amounts
            CHECK (
                gross_amount >= 0 AND platform_fee >= 0 AND affiliate_commission >= 0 AND net_to_seller >= 0
            )");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE order_settlements DROP CONSTRAINT IF EXISTS chk_order_settlements_amounts");
        DB::statement("ALTER TABLE order_settlements DROP CONSTRAINT IF EXISTS chk_order_settlements_status");
        Schema::dropIfExists('order_settlements');
    }
};
