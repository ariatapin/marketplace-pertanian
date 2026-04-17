<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CartSanitizerService
{
    public function sanitize(int $userId): int
    {
        if (! Schema::hasTable('cart_items')) {
            return 0;
        }

        $deleted = 0;

        $deleted += DB::table('cart_items')
            ->where('user_id', $userId)
            ->whereNotIn('product_type', ['store', 'farmer'])
            ->delete();

        $invalidStoreIds = DB::table('cart_items')
            ->leftJoin('store_products', function ($join) {
                $join->on('store_products.id', '=', 'cart_items.product_id')
                    ->where('cart_items.product_type', '=', 'store');
            })
            ->where('cart_items.user_id', $userId)
            ->where('cart_items.product_type', 'store')
            ->whereNull('store_products.id')
            ->pluck('cart_items.id');

        if ($invalidStoreIds->isNotEmpty()) {
            $deleted += DB::table('cart_items')
                ->whereIn('id', $invalidStoreIds->all())
                ->delete();
        }

        if (! Schema::hasTable('farmer_harvests')) {
            $deleted += DB::table('cart_items')
                ->where('user_id', $userId)
                ->where('product_type', 'farmer')
                ->delete();

            return $deleted;
        }

        $invalidFarmerIds = DB::table('cart_items')
            ->leftJoin('farmer_harvests', function ($join) {
                $join->on('farmer_harvests.id', '=', 'cart_items.product_id')
                    ->where('cart_items.product_type', '=', 'farmer');
            })
            ->where('cart_items.user_id', $userId)
            ->where('cart_items.product_type', 'farmer')
            ->whereNull('farmer_harvests.id')
            ->pluck('cart_items.id');

        if ($invalidFarmerIds->isNotEmpty()) {
            $deleted += DB::table('cart_items')
                ->whereIn('id', $invalidFarmerIds->all())
                ->delete();
        }

        return $deleted;
    }
}
