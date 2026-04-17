<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->id();

            // siapa yang tarik (mitra / petani penjual / affiliate)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);

            // snapshot rekening saat request (biar audit aman walau profil berubah)
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 120)->nullable();
            $table->string('account_holder', 255)->nullable();

            // status proses
            $table->string('status', 20)->default('pending'); // pending, approved, paid, rejected, cancelled

            // diproses oleh admin
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('processed_at')->nullable();

            // bukti transfer / reference
            $table->text('transfer_proof_url')->nullable();
            $table->string('transfer_reference', 120)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE withdraw_requests
            ADD CONSTRAINT chk_withdraw_requests_amount
            CHECK (amount > 0)");

        DB::statement("ALTER TABLE withdraw_requests
            ADD CONSTRAINT chk_withdraw_requests_status
            CHECK (status IN ('pending','approved','paid','rejected','cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE withdraw_requests DROP CONSTRAINT IF EXISTS chk_withdraw_requests_status");
        DB::statement("ALTER TABLE withdraw_requests DROP CONSTRAINT IF EXISTS chk_withdraw_requests_amount");
        Schema::dropIfExists('withdraw_requests');
    }
};
