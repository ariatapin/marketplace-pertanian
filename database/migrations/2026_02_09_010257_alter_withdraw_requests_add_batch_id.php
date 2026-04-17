<?php
/* =========================================================
 | 4) alter_withdraw_requests_add_batch_id.php
 | Menghubungkan withdraw_requests ke payout_batches
 | (jalankan setelah create_withdraw_requests & create_payout_batches)
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->foreignId('payout_batch_id')
                ->nullable()
                ->after('id')
                ->constrained('payout_batches')
                ->nullOnDelete();

            $table->index('payout_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_batch_id');
        });
    }
};
