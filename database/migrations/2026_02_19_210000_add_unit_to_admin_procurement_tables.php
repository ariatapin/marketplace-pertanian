<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('admin_products')) {
            Schema::table('admin_products', function (Blueprint $table) {
                if (! Schema::hasColumn('admin_products', 'unit')) {
                    $table->string('unit', 20)->default('kg')->after('price');
                    $table->index('unit');
                }
            });
        }

        if (Schema::hasTable('admin_order_items')) {
            Schema::table('admin_order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('admin_order_items', 'unit')) {
                    $table->string('unit', 20)->default('kg')->after('price_per_unit');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_order_items')) {
            Schema::table('admin_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('admin_order_items', 'unit')) {
                    $table->dropColumn('unit');
                }
            });
        }

        if (Schema::hasTable('admin_products')) {
            Schema::table('admin_products', function (Blueprint $table) {
                if (Schema::hasColumn('admin_products', 'unit')) {
                    $table->dropIndex(['unit']);
                    $table->dropColumn('unit');
                }
            });
        }
    }
};
