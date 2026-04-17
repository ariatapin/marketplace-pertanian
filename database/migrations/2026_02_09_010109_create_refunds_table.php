<?php
/* =========================================================
 | 1) create_refunds_table.php
 | Refund untuk order (audit + bukti transfer balik)
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained('users');

            $table->decimal('amount', 15, 2);
            $table->string('reason', 255)->nullable();

            // pending, approved, paid, rejected, cancelled
            $table->string('status', 20)->default('pending');

            // diproses admin
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            // bukti transfer / reference pengembalian dana
            $table->text('refund_proof_url')->nullable();
            $table->string('refund_reference', 120)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('buyer_id');
            $table->index('seller_id');
        });

        DB::statement("ALTER TABLE refunds
            ADD CONSTRAINT chk_refunds_amount
            CHECK (amount > 0)");

        DB::statement("ALTER TABLE refunds
            ADD CONSTRAINT chk_refunds_status
            CHECK (status IN ('pending','approved','paid','rejected','cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE refunds DROP CONSTRAINT IF EXISTS chk_refunds_status");
        DB::statement("ALTER TABLE refunds DROP CONSTRAINT IF EXISTS chk_refunds_amount");
        Schema::dropIfExists('refunds');
    }
};
