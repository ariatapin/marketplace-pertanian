<?php
/* =========================================================
 | 10) create_wallet_transactions_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2); // +/- allowed
            $table->string('transaction_type', 40);

            $table->foreignId('reference_order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('wallet_id');
            $table->index('reference_order_id');
            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
