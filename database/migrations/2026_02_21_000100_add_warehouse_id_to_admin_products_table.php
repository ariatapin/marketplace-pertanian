<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_products')) {
            return;
        }

        Schema::table('admin_products', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_products', 'warehouse_id')) {
                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->after('is_active')
                    ->constrained('warehouses')
                    ->nullOnDelete();
                $table->index('warehouse_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_products') || ! Schema::hasColumn('admin_products', 'warehouse_id')) {
            return;
        }

        Schema::table('admin_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};

