<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('total_amount');
            $table->decimal('paid_amount', 15, 2)->nullable()->after('payment_proof_url');
            $table->timestamp('payment_submitted_at')->nullable()->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_submitted_at', 'paid_amount', 'payment_method']);
        });
    }
};
