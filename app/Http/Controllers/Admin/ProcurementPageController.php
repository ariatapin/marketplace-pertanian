<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminProcurementViewModelFactory;
use App\Support\ProcurementStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcurementPageController extends Controller
{
    public function __construct(
        private readonly ProcurementStatusTransition $statusTransition,
        private readonly AdminProcurementViewModelFactory $procurementViewModelFactory
    ) {}

    public function __invoke(Request $request)
    {
        $status = $request->string('status')->toString();
        $keyword = trim($request->string('q')->toString());
        $paymentStatus = $request->string('payment_status')->toString();
        $hasPaymentColumns = Schema::hasTable('admin_orders') && Schema::hasColumn('admin_orders', 'payment_status');

        $summary = [
            'total_admin_products' => 0,
            'active_admin_products' => 0,
            'low_stock_admin_products' => 0,
            'pending_orders' => 0,
            'processing_orders' => 0,
            'shipped_orders' => 0,
            'new_orders_today' => 0,
            'pending_payment_verification_orders' => 0,
            'paid_orders' => 0,
        ];
        $latestOrderId = 0;

        $adminProducts = collect();
        $adminOrders = collect();
        $warehouses = collect();

        if (Schema::hasTable('warehouses')) {
            $warehouses = DB::table('warehouses')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']);
        }

        if (Schema::hasTable('admin_products')) {
            $summary['total_admin_products'] = DB::table('admin_products')->count();
            $summary['active_admin_products'] = DB::table('admin_products')->where('is_active', true)->count();
            $summary['low_stock_admin_products'] = DB::table('admin_products')->where('stock_qty', '<=', 10)->count();

            $productQuery = DB::table('admin_products as product')
                ->orderByDesc('product.id');

            $productColumns = [
                'product.id',
                'product.name',
                'product.description',
                'product.price',
                'product.unit',
                'product.min_order_qty',
                'product.stock_qty',
                'product.is_active',
                'product.warehouse_id',
                'product.updated_at',
            ];

            if (Schema::hasTable('warehouses')) {
                $productQuery->leftJoin('warehouses as warehouse', 'warehouse.id', '=', 'product.warehouse_id');
                $productColumns[] = 'warehouse.code as warehouse_code';
                $productColumns[] = 'warehouse.name as warehouse_name';
            } else {
                $productColumns[] = DB::raw('null as warehouse_code');
                $productColumns[] = DB::raw('null as warehouse_name');
            }

            $adminProducts = $productQuery
                ->limit(12)
                ->get($productColumns);
        }

        if (Schema::hasTable('admin_orders')) {
            $summary['pending_orders'] = DB::table('admin_orders')->where('status', 'pending')->count();
            $summary['processing_orders'] = DB::table('admin_orders')->whereIn('status', ['approved', 'processing'])->count();
            $summary['shipped_orders'] = DB::table('admin_orders')->where('status', 'shipped')->count();
            $summary['new_orders_today'] = DB::table('admin_orders')
                ->whereDate('created_at', today())
                ->count();
            if ($hasPaymentColumns) {
                $summary['pending_payment_verification_orders'] = DB::table('admin_orders')
                    ->where('payment_status', 'pending_verification')
                    ->count();
                $summary['paid_orders'] = DB::table('admin_orders')
                    ->where('payment_status', 'paid')
                    ->count();
            }
            $latestOrderId = (int) (DB::table('admin_orders')->max('id') ?? 0);

            $orderQuery = DB::table('admin_orders')
                ->leftJoin('users as mitra', 'mitra.id', '=', 'admin_orders.mitra_id')
                ->select(
                    'admin_orders.id',
                    'admin_orders.status',
                    'admin_orders.total_amount',
                    'admin_orders.notes',
                    'admin_orders.created_at',
                    'mitra.name as mitra_name',
                    'mitra.email as mitra_email'
                )
                ->orderByDesc('admin_orders.id');

            if ($hasPaymentColumns) {
                $orderQuery->addSelect(
                    'admin_orders.payment_status',
                    'admin_orders.payment_method',
                    'admin_orders.paid_amount',
                    'admin_orders.payment_proof_url',
                    'admin_orders.payment_submitted_at',
                    'admin_orders.payment_verified_at',
                    'admin_orders.payment_note'
                );
            }

            if (in_array($status, ['pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled'], true)) {
                $orderQuery->where('admin_orders.status', $status);
            }

            if ($keyword !== '') {
                $orderQuery->where(function ($sub) use ($keyword) {
                    $sub->where('mitra.name', 'like', "%{$keyword}%")
                        ->orWhere('mitra.email', 'like', "%{$keyword}%")
                        ->orWhere('admin_orders.id', 'like', "%{$keyword}%");
                });
            }

            if ($hasPaymentColumns && in_array($paymentStatus, ['unpaid', 'pending_verification', 'paid', 'rejected'], true)) {
                $orderQuery->where('admin_orders.payment_status', $paymentStatus);
            }

            $adminOrders = $orderQuery->paginate(12)->withQueryString();

            if (Schema::hasTable('admin_order_items')) {
                $itemRows = DB::table('admin_order_items')
                    ->whereIn('admin_order_id', $adminOrders->pluck('id'))
                    ->orderBy('admin_order_id')
                    ->get(['admin_order_id', 'product_name', 'qty', 'unit', 'price_per_unit'])
                    ->groupBy('admin_order_id');

                $adminOrders->setCollection(
                    $adminOrders->getCollection()->map(function ($row) use ($itemRows) {
                        $items = $itemRows->get($row->id, collect());
                        $row->items = $items;
                        $row->item_qty_total = (int) $items->sum('qty');
                        $row->allowed_status_targets = collect($this->statusTransition->allowedTargets((string) $row->status))
                            ->reject(fn ($target) => (string) $target === 'delivered')
                            ->values()
                            ->all();
                        return $row;
                    })
                );
            } else {
                $adminOrders->setCollection(
                    $adminOrders->getCollection()->map(function ($row) {
                        $row->allowed_status_targets = collect($this->statusTransition->allowedTargets((string) $row->status))
                            ->reject(fn ($target) => (string) $target === 'delivered')
                            ->values()
                            ->all();
                        return $row;
                    })
                );
            }

            if (Schema::hasTable('admin_order_status_histories') && $adminOrders->count() > 0) {
                $latestHistoryRows = DB::table('admin_order_status_histories as h')
                    ->leftJoin('users as actor', 'actor.id', '=', 'h.actor_user_id')
                    ->join(
                        DB::raw('(SELECT admin_order_id, MAX(id) as max_id FROM admin_order_status_histories GROUP BY admin_order_id) as latest'),
                        function ($join) {
                            $join->on('latest.admin_order_id', '=', 'h.admin_order_id')
                                ->on('latest.max_id', '=', 'h.id');
                        }
                    )
                    ->whereIn('h.admin_order_id', $adminOrders->pluck('id'))
                    ->select(
                        'h.admin_order_id',
                        'h.from_status',
                        'h.to_status',
                        'h.note',
                        'h.actor_role',
                        'h.actor_user_id',
                        'h.created_at',
                        'actor.name as actor_name'
                    )
                    ->get()
                    ->keyBy('admin_order_id');

                $adminOrders->setCollection(
                    $adminOrders->getCollection()->map(function ($row) use ($latestHistoryRows) {
                        $row->latest_history = $latestHistoryRows->get($row->id);
                        return $row;
                    })
                );
            }
        }

        [
            'summary' => $summary,
            'adminProducts' => $adminProducts,
            'adminOrders' => $adminOrders,
        ] = $this->procurementViewModelFactory->make(
            summary: $summary,
            adminProducts: $adminProducts,
            adminOrders: $adminOrders
        );

        return view('admin.procurement', [
            'summary' => $summary,
            'latestOrderId' => $latestOrderId,
            'adminProducts' => $adminProducts,
            'adminOrders' => $adminOrders,
            'warehouses' => $warehouses,
            'hasPaymentColumns' => $hasPaymentColumns,
            'filters' => [
                'status' => $status,
                'q' => $keyword,
                'payment_status' => $paymentStatus,
            ],
        ]);
    }
}
