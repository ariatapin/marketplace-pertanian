<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (
            Schema::hasTable('store_products')
            && Schema::hasColumn('store_products', 'source_admin_product_id')
            && Schema::hasColumn('store_products', 'is_active')
        ) {
            DB::table('store_products')
                ->whereNotNull('source_admin_product_id')
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // no-op: tidak mengembalikan status aktif lama karena tidak ada snapshot sebelumnya
    }
};
