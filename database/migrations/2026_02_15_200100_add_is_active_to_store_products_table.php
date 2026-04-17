<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('store_products', 'is_active')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('image_url');
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_products', 'is_active')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            });
        }
    }
};
