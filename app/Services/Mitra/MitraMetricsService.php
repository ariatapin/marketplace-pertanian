<?php

namespace App\Services\Mitra;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MitraMetricsService
{
    public function dashboardMetrics(int $mitraId): array
    {
        $metrics = [
            'my_products' => 0,
            'active_products' => 0,
            'total_stock' => 0,
            'procurement_orders' => 0,
            'procurement_orders_total' => 0,
            'customer_orders' => 0,
            'customer_orders_completed' => 0,
            'out_of_stock_products' => 0,
            'low_stock_products' => 0,
            'inventory_value' => 0,
        ];

        if (Schema::hasTable('store_products')) {
            $products = DB::table('store_products')->where('mitra_id', $mitraId);
            $metrics['my_products'] = (clone $products)->count();
            $activeProducts = (clone $products)->where('stock_qty', '>', 0);
            if (Schema::hasColumn('store_products', 'is_active')) {
                $activeProducts->where('is_active', true);
            }
            $metrics['active_products'] = $activeProducts->count();
            $metrics['total_stock'] = (int) ((clone $products)->sum('stock_qty') ?? 0);
            $metrics['out_of_stock_products'] = (clone $products)->where('stock_qty', '<=', 0)->count();
            $metrics['low_stock_products'] = (clone $products)->whereBetween('stock_qty', [1, 10])->count();
            $metrics['inventory_value'] = (float) ((clone $products)->sum(DB::raw('stock_qty * price')) ?? 0);
        }

        if (Schema::hasTable('admin_orders')) {
            $procurements = DB::table('admin_orders')->where('mitra_id', $mitraId);
            $metrics['procurement_orders_total'] = (clone $procurements)->count();
            $metrics['procurement_orders'] = (clone $procurements)
                ->whereIn('status', ['pending', 'approved', 'processing', 'shipped'])
                ->count();
        }

        if (Schema::hasTable('orders')) {
            // Mitra hanya menghitung order marketplace B2B/store_online.
            $orders = DB::table('orders')
                ->where('seller_id', $mitraId)
                ->where(function ($query) {
                    $query
                        ->where('order_source', 'store_online')
                        ->orWhereNull('order_source')
                        ->orWhere('order_source', '');
                });
            $metrics['customer_orders'] = (clone $orders)
                ->whereIn('order_status', ['pending_payment', 'paid', 'packed', 'shipped'])
                ->count();
            $metrics['customer_orders_completed'] = (clone $orders)
                ->where('order_status', 'completed')
                ->count();
        }

        return $metrics;
    }
}
