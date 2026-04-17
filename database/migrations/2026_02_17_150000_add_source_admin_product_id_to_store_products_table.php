<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            if (! Schema::hasColumn('store_products', 'source_admin_product_id')) {
                $table->foreignId('source_admin_product_id')
                    ->nullable()
                    ->after('mitra_id')
                    ->constrained('admin_products')
                    ->nullOnDelete();

                $table->unique(
                    ['mitra_id', 'source_admin_product_id'],
                    'store_products_mitra_source_admin_unique'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_products', function (Blueprint $table) {
            if (Schema::hasColumn('store_products', 'source_admin_product_id')) {
                $table->dropUnique('store_products_mitra_source_admin_unique');
                $table->dropConstrainedForeignId('source_admin_product_id');
            }
        });
    }
};
