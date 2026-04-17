<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_reports', function (Blueprint $table) {
            $table->id();
            $table->string('product_type', 20);
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 255)->nullable();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 50)->default('other');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['product_type', 'product_id'], 'idx_product_reports_product');
            $table->index('status');
            $table->index('reporter_id');
            $table->index('reported_user_id');
        });

        DB::statement("ALTER TABLE product_reports
            ADD CONSTRAINT chk_product_reports_product_type
            CHECK (product_type IN ('store','farmer'))");

        DB::statement("ALTER TABLE product_reports
            ADD CONSTRAINT chk_product_reports_status
            CHECK (status IN ('pending','under_review','resolved','cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE product_reports DROP CONSTRAINT IF EXISTS chk_product_reports_status");
        DB::statement("ALTER TABLE product_reports DROP CONSTRAINT IF EXISTS chk_product_reports_product_type");
        Schema::dropIfExists('product_reports');
    }
};
