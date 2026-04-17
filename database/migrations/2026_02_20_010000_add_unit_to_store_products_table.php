<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('store_products', 'unit')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->string('unit', 20)->default('kg')->after('price');
                $table->index('unit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_products', 'unit')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->dropIndex(['unit']);
                $table->dropColumn('unit');
            });
        }
    }
};
