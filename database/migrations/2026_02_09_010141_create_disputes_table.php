<?php
/* =========================================================
 | 2) create_disputes_table.php
 | Sengketa (buyer vs seller) + evidence
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained('users');

            // siapa yang membuka sengketa
            $table->foreignId('opened_by')->constrained('users');

            $table->string('category', 50)->nullable(); // mis: not_received, damaged, wrong_item, etc
            $table->text('description')->nullable();

            // pending, under_review, resolved_buyer, resolved_seller, cancelled
            $table->string('status', 30)->default('pending');

            // admin handler
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            // hasil keputusan
            $table->string('resolution', 50)->nullable(); // refund_full, refund_partial, release_to_seller, etc
            $table->text('resolution_notes')->nullable();

            // bukti (simple: simpan url list via jsonb)
            $table->jsonb('evidence_urls')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('buyer_id');
            $table->index('seller_id');
            $table->index('handled_by');
        });

        DB::statement("ALTER TABLE disputes
            ADD CONSTRAINT chk_disputes_status
            CHECK (status IN ('pending','under_review','resolved_buyer','resolved_seller','cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE disputes DROP CONSTRAINT IF EXISTS chk_disputes_status");
        Schema::dropIfExists('disputes');
    }
};
