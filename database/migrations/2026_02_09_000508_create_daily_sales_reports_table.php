<?php
/* =========================================================
 | 14) create_daily_sales_reports_table.php
 ========================================================= */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_sales_reports', function (Blueprint $table) {
            $table->id();

            $table->date('report_date');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_commission', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['report_date', 'user_id'], 'uq_daily_sales_report_date_user');
            $table->index('user_id');
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_reports');
    }
};
