<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('store_products', 'affiliate_expire_date')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->date('affiliate_expire_date')->nullable()->after('affiliate_commission');
                $table->index('affiliate_expire_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_products', 'affiliate_expire_date')) {
            Schema::table('store_products', function (Blueprint $table) {
                $table->dropIndex(['affiliate_expire_date']);
                $table->dropColumn('affiliate_expire_date');
            });
        }
    }
};

