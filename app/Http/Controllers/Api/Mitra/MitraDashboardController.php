<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MitraProcurementController;
use App\Services\Location\LocationResolver;
use App\Services\Mitra\MitraMetricsService;
use App\Services\Weather\WeatherService;
use App\Services\Weather\WeatherAlertEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MitraDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LocationResolver $location,
        protected WeatherService $weather,
        protected WeatherAlertEngine $alertEngine,
        protected MitraMetricsService $metricsService
    ) {}
    
    public function summary(Request $request)
    {
        $user = $request->user();
        $loc = $this->location->forUser($user);
        $metrics = $this->metricsService->dashboardMetrics((int) $user->id);

        $current = $this->weather->current($loc['type'], $loc['id'], $loc['lat'], $loc['lng']);
        $forecast = $this->weather->forecast($loc['type'], $loc['id'], $loc['lat'], $loc['lng']);
        $alert = $this->alertEngine->evaluateForecast($forecast);

        return $this->apiSuccess([
            'counts' => [
                'procurement_orders' => (int) ($metrics['procurement_orders'] ?? 0),
                'procurement_orders_total' => (int) ($metrics['procurement_orders_total'] ?? 0),
                'customer_orders_active' => (int) ($metrics['customer_orders'] ?? 0),
                'customer_orders_completed' => (int) ($metrics['customer_orders_completed'] ?? 0),
                'my_products' => (int) ($metrics['my_products'] ?? 0),
                'active_products' => (int) ($metrics['active_products'] ?? 0),
                'low_stock_products' => (int) ($metrics['low_stock_products'] ?? 0),
            ],
            'weather' => [
                'location' => $loc['label'],
                'lat' => $loc['lat'],
                'lng' => $loc['lng'],
                'current' => $current,
                'alert' => $alert,
            ],
        ], 'Ringkasan dashboard mitra berhasil diambil.');
    }


    public function adminProducts()
    {
        $rows = DB::table('admin_products as product')
            ->where('product.is_active', true);

        $columns = ['product.*'];
        $hasWarehouseData = Schema::hasTable('warehouses')
            && DB::table('warehouses')->exists();

        if ($hasWarehouseData) {
            $rows->join('warehouses as warehouse', 'warehouse.id', '=', 'product.warehouse_id')
                ->where('warehouse.is_active', true);
            $columns[] = 'warehouse.code as warehouse_code';
            $columns[] = 'warehouse.name as warehouse_name';
        } else {
            $columns[] = DB::raw('null as warehouse_code');
            $columns[] = DB::raw('null as warehouse_name');
        }

        $rows = $rows->orderByDesc('product.id')->get($columns);
        return $this->apiSuccess($rows, 'Data produk admin aktif berhasil diambil.');
    }

    public function createProcurementOrder(Request $request)
    {
        return app(\App\Http\Controllers\MitraProcurementController::class)->createOrder($request);
    }

    public function procurementOrders(Request $request)
    {
        $mid = $request->user()->id;
        $hasPaymentColumns = Schema::hasTable('admin_orders')
            && Schema::hasColumn('admin_orders', 'payment_status')
            && Schema::hasColumn('admin_orders', 'payment_method')
            && Schema::hasColumn('admin_orders', 'paid_amount')
            && Schema::hasColumn('admin_orders', 'payment_submitted_at')
            && Schema::hasColumn('admin_orders', 'payment_verified_at');

        $orderSelect = ['id', 'mitra_id', 'total_amount', 'status', 'notes', 'created_at', 'updated_at'];
        if ($hasPaymentColumns) {
            $orderSelect = array_merge($orderSelect, [
                'payment_status',
                'payment_method',
                'paid_amount',
                'payment_submitted_at',
                'payment_verified_at',
            ]);
        }

        $rows = DB::table('admin_orders')
            ->where('mitra_id', $mid)
            ->orderByDesc('id')
            ->get($orderSelect);

        if ($rows->isNotEmpty() && Schema::hasTable('admin_order_items')) {
            $summaryRows = DB::table('admin_order_items')
                ->select(
                    'admin_order_id',
                    DB::raw('COUNT(*) as line_count'),
                    DB::raw('SUM(qty) as total_qty')
                )
                ->whereIn('admin_order_id', $rows->pluck('id'))
                ->groupBy('admin_order_id')
                ->get()
                ->keyBy('admin_order_id');

            $rows = $rows->map(function ($order) use ($summaryRows) {
                $summary = $summaryRows->get($order->id);
                $order->line_count = (int) ($summary->line_count ?? 0);
                $order->total_qty = (int) ($summary->total_qty ?? 0);
                return $order;
            });
        }

        if ($rows->isNotEmpty() && Schema::hasTable('admin_order_status_histories')) {
            $latestHistory = DB::table('admin_order_status_histories')
                ->select('admin_order_id', DB::raw('MAX(created_at) as latest_status_at'))
                ->whereIn('admin_order_id', $rows->pluck('id'))
                ->groupBy('admin_order_id')
                ->pluck('latest_status_at', 'admin_order_id');

            $rows = $rows->map(function ($order) use ($latestHistory) {
                $order->latest_status_at = $latestHistory[$order->id] ?? null;
                return $order;
            });
        }

        return $this->apiSuccess($rows, 'Data order pengadaan mitra berhasil diambil.');
    }

    public function procurementOrderDetail(Request $request, int $orderId)
    {
        return app(MitraProcurementController::class)->show($request, $orderId);
    }

    public function cancelProcurementOrder(Request $request, int $orderId)
    {
        return app(MitraProcurementController::class)->cancelOrder($request, $orderId);
    }

    public function submitProcurementPayment(Request $request, int $orderId)
    {
        return app(MitraProcurementController::class)->submitPayment($request, $orderId);
    }

    public function confirmProcurementReceived(Request $request, int $orderId)
    {
        return app(MitraProcurementController::class)->confirmReceived($request, $orderId);
    }
}
