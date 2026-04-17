<?php
/* =========================================================
 | 12) create_approvals_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();

            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');

            $table->string('status', 20)->default('pending'); // pending, approved, rejected

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('status');
            $table->index('decided_by');
        });

        DB::statement("ALTER TABLE approvals
            ADD CONSTRAINT chk_approvals_status
            CHECK (status IN ('pending','approved','rejected'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE approvals DROP CONSTRAINT IF EXISTS chk_approvals_status");
        Schema::dropIfExists('approvals');
    }
};
