<?php
/* =========================================================
 | 3) create_payout_batches_table.php
 | Transfer massal (mingguan/harian) untuk payout seller/affiliate
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();

            // dibuat admin
            $table->foreignId('created_by')->constrained('users');

            // window payout (misal mingguan)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            // total summary
            $table->integer('total_requests')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // draft, processing, completed, failed, cancelled
            $table->string('status', 20)->default('draft');

            // reference transfer massal (jika bank provide)
            $table->string('batch_reference', 120)->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        DB::statement("ALTER TABLE payout_batches
            ADD CONSTRAINT chk_payout_batches_status
            CHECK (status IN ('draft','processing','completed','failed','cancelled'))");

        DB::statement("ALTER TABLE payout_batches
            ADD CONSTRAINT chk_payout_batches_total_amount
            CHECK (total_amount >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payout_batches DROP CONSTRAINT IF EXISTS chk_payout_batches_total_amount");
        DB::statement("ALTER TABLE payout_batches DROP CONSTRAINT IF EXISTS chk_payout_batches_status");
        Schema::dropIfExists('payout_batches');
    }
};
