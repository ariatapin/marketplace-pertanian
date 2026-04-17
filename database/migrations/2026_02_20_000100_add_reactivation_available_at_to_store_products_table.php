<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('store_products', 'reactivation_available_at')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->timestamp('reactivation_available_at')->nullable()->after('is_active');
                $table->index('reactivation_available_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_products', 'reactivation_available_at')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->dropIndex(['reactivation_available_at']);
                $table->dropColumn('reactivation_available_at');
            });
        }
    }
};
