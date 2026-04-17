<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawBankAccountUpdateRequest;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use App\Services\Mitra\MitraMetricsService;
use App\Services\Recommendation\RuleBasedRecommendationService;
use App\Services\UserRatingService;
use App\Services\WithdrawBankAccountService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function __construct(
        private readonly MitraMetricsService $metricsService,
        private readonly WalletService $walletService,
        private readonly WithdrawBankAccountService $withdrawBankAccounts,
        private readonly RuleBasedRecommendationService $recommendationService,
        private readonly UserRatingService $userRatings
    ) {
    }

    public function dashboard()
    {
        // CATATAN-AUDIT: Dashboard mitra memadukan metrik operasional, cuaca, dan notifikasi rekomendasi.
        $user = request()->user();
        if (
            app()->environment('local')
            && Str::endsWith((string) ($user->email ?? ''), '@demo.test')
        ) {
            app(\App\Support\DemoUserProvisioner::class)->ensureUsers();
        }

        $demoMitraBalance = $this->resolveWalletBalance((int) $user->id);
        $hasProcurementPaymentColumns = Schema::hasTable('admin_orders')
            && Schema::hasColumn('admin_orders', 'payment_status');

        $metrics = $this->metricsService->dashboardMetrics((int) $user->id);
        $ratingSummary = $this->userRatings->summaryForUser((int) $user->id);
        $recentProducts = collect();
        $recentProcurements = collect();
        $incomingOrders = collect();
        $weatherNotifications = collect();
        $weatherNotificationUnreadCount = 0;

        try {
            // CATATAN-AUDIT: Sinkronisasi rekomendasi mitra dijalankan sebelum komponen cuaca/notifikasi dirender.
            $this->recommendationService->syncForUser($user);
        } catch (\Throwable $e) {
            // Silent fallback: dashboard tetap render walaupun sinkron gagal.
        }

        if (Schema::hasTable('store_products')) {
            $recentProducts = DB::table('store_products')
                ->where('mitra_id', $user->id)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get(['id', 'name', 'price', 'unit', 'stock_qty', 'updated_at']);
        }

        if (Schema::hasTable('orders')) {
            $incomingOrders = DB::table('orders')
                ->leftJoin('users as buyer', 'buyer.id', '=', 'orders.buyer_id')
                ->where('orders.seller_id', $user->id)
                ->where(function ($query) {
                    $query
                        ->where('orders.order_source', 'store_online')
                        ->orWhereNull('orders.order_source')
                        ->orWhere('orders.order_source', '');
                })
                ->whereIn('orders.order_status', ['pending_payment', 'paid', 'packed'])
                ->orderByDesc('orders.updated_at')
                ->limit(5)
                ->get([
                    'orders.id',
                    'orders.total_amount',
                    'orders.order_status',
                    'orders.payment_status',
                    'orders.payment_method',
                    'orders.payment_proof_url',
                    'orders.resi_number',
                    'orders.updated_at',
                    'buyer.name as buyer_name',
                    'buyer.email as buyer_email',
                ]);
        }

        if (Schema::hasTable('admin_orders')) {
            $procurementSelect = ['id', 'total_amount', 'status', 'created_at'];
            if ($hasProcurementPaymentColumns) {
                $procurementSelect[] = 'payment_status';
            }

            $recentProcurements = DB::table('admin_orders')
                ->where('mitra_id', $user->id)
                ->orderByDesc('id')
                ->limit(8)
                ->get($procurementSelect);

            if (Schema::hasTable('admin_order_items')) {
                $itemCounts = DB::table('admin_order_items')
                    ->select('admin_order_id', DB::raw('SUM(qty) as total_qty'))
                    ->whereIn('admin_order_id', $recentProcurements->pluck('id'))
                    ->groupBy('admin_order_id')
                    ->pluck('total_qty', 'admin_order_id');

                $recentProcurements = $recentProcurements->map(function ($row) use ($itemCounts) {
                    $row->total_qty = (int) ($itemCounts[$row->id] ?? 0);
                    return $row;
                });
            }
        }

        if (Schema::hasTable('notifications')) {
            $weatherNotificationQuery = $user->notifications()
                ->where(function ($innerQuery) {
                    $innerQuery->whereIn('type', [
                        AdminWeatherNoticeNotification::class,
                        BehaviorRecommendationNotification::class,
                    ])->orWhere('data', 'like', '%"category":"behavior_recommendation"%');
                })
                ->latest();

            $weatherNotificationUnreadCount = (int) (clone $weatherNotificationQuery)
                ->whereNull('read_at')
                ->count();

            $weatherNotifications = $weatherNotificationQuery
                ->limit(4)
                ->get()
                ->map(fn ($notification) => $this->formatWeatherNotificationRow($notification))
                ->values();
        }

        return view('mitra.dashboard', compact(
            'metrics',
            'ratingSummary',
            'recentProducts',
            'recentProcurements',
            'hasProcurementPaymentColumns',
            'demoMitraBalance',
            'incomingOrders',
            'weatherNotifications',
            'weatherNotificationUnreadCount'
        ));
    }

    public function procurement()
    {
        $mitra = request()->user();
        $hasProcurementPaymentColumns = Schema::hasTable('admin_orders')
            && Schema::hasColumn('admin_orders', 'payment_status')
            && Schema::hasColumn('admin_orders', 'payment_method')
            && Schema::hasColumn('admin_orders', 'paid_amount')
            && Schema::hasColumn('admin_orders', 'payment_proof_url')
            && Schema::hasColumn('admin_orders', 'payment_submitted_at')
            && Schema::hasColumn('admin_orders', 'payment_verified_at')
            && Schema::hasColumn('admin_orders', 'payment_note');

        $adminProducts = collect();
        $myProcurements = collect();

        if (Schema::hasTable('admin_products')) {
            $productQuery = DB::table('admin_products as product')
                ->where('product.is_active', true);
            $hasWarehouseData = Schema::hasTable('warehouses')
                && DB::table('warehouses')->exists();
            $selectColumns = [
                'product.id',
                'product.name',
                'product.description',
                'product.price',
                'product.unit',
                'product.stock_qty',
                'product.min_order_qty',
                'product.warehouse_id',
            ];

            if ($hasWarehouseData) {
                $productQuery
                    ->join('warehouses as warehouse', 'warehouse.id', '=', 'product.warehouse_id')
                    ->where('warehouse.is_active', true);
                $selectColumns[] = 'warehouse.code as warehouse_code';
                $selectColumns[] = 'warehouse.name as warehouse_name';
            } else {
                $selectColumns[] = DB::raw('null as warehouse_code');
                $selectColumns[] = DB::raw('null as warehouse_name');
            }

            $adminProducts = $productQuery
                ->orderBy('product.name')
                ->get($selectColumns);
        }

        if (Schema::hasTable('admin_orders')) {
            $orderSelect = ['id', 'total_amount', 'status', 'notes', 'created_at'];
            if ($hasProcurementPaymentColumns) {
                $orderSelect = array_merge($orderSelect, [
                    'payment_status',
                    'payment_method',
                    'paid_amount',
                    'payment_proof_url',
                    'payment_submitted_at',
                    'payment_verified_at',
                    'payment_note',
                ]);
            }

            $myProcurements = DB::table('admin_orders')
                ->where('mitra_id', $mitra->id)
                ->orderByDesc('id')
                ->limit(15)
                ->get($orderSelect);

            if (Schema::hasTable('admin_order_items')) {
                $itemSummary = DB::table('admin_order_items')
                    ->select(
                        'admin_order_id',
                        DB::raw('COUNT(*) as line_count'),
                        DB::raw('SUM(qty) as total_qty')
                    )
                    ->whereIn('admin_order_id', $myProcurements->pluck('id'))
                    ->groupBy('admin_order_id')
                    ->get()
                    ->keyBy('admin_order_id');

                $myProcurements = $myProcurements->map(function ($order) use ($itemSummary) {
                    $summary = $itemSummary->get($order->id);
                    $order->line_count = (int) ($summary->line_count ?? 0);
                    $order->total_qty = (int) ($summary->total_qty ?? 0);
                    return $order;
                });
            }
        }

        return view('mitra.procurement.index', [
            'adminProducts' => $adminProducts,
            'myProcurements' => $myProcurements,
            'hasProcurementPaymentColumns' => $hasProcurementPaymentColumns,
        ]);
    }

    public function finance()
    {
        $mitra = request()->user();
        $summary = [
            'gross_sales' => 0,
            'procurement_cost' => 0,
            'affiliate_commission' => 0,
            'net_profit' => 0,
        ];
        $recentTransactions = collect();
        $withdrawRequests = collect();
        $walletSummary = [
            'balance' => 0.0,
            'reserved_withdraw_amount' => 0.0,
            'available_balance' => 0.0,
        ];
        $bankProfile = null;
        $isBankProfileComplete = false;

        if (Schema::hasTable('orders')) {
            $summary['gross_sales'] = (float) DB::table('orders')
                ->where('seller_id', $mitra->id)
                ->where('order_source', 'store_online')
                ->where('payment_status', 'paid')
                ->sum('total_amount');
        }

        if (Schema::hasTable('admin_orders')) {
            $summary['procurement_cost'] = (float) DB::table('admin_orders')
                ->where('mitra_id', $mitra->id)
                ->whereIn('status', ['shipped', 'delivered'])
                ->sum('total_amount');
        }

        if (Schema::hasTable('order_items') && Schema::hasTable('orders')) {
            $summary['affiliate_commission'] = (float) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.seller_id', $mitra->id)
                ->where('orders.order_source', 'store_online')
                ->sum('order_items.commission_amount');
        }

        $summary['net_profit'] = $summary['gross_sales'] - $summary['procurement_cost'] - $summary['affiliate_commission'];

        if (Schema::hasTable('wallet_transactions')) {
            $walletSummary['balance'] = (float) DB::table('wallet_transactions')
                ->where('wallet_id', $mitra->id)
                ->sum('amount');

            $recentTransactions = DB::table('wallet_transactions')
                ->where('wallet_id', $mitra->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'amount', 'transaction_type', 'description', 'reference_order_id', 'created_at']);
        }

        if (Schema::hasTable('withdraw_requests')) {
            $walletSummary['reserved_withdraw_amount'] = (float) DB::table('withdraw_requests')
                ->where('user_id', $mitra->id)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('amount');

            $withdrawRequests = DB::table('withdraw_requests')
                ->where('user_id', $mitra->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get([
                    'id',
                    'amount',
                    'status',
                    'bank_name',
                    'account_number',
                    'account_holder',
                    'transfer_reference',
                    'processed_at',
                    'created_at',
                ]);
        }

        $walletSummary['available_balance'] = max(0.0, round(
            (float) $walletSummary['balance'] - (float) $walletSummary['reserved_withdraw_amount'],
            2
        ));

        $bankSnapshot = $this->withdrawBankAccounts->snapshot((int) $mitra->id);
        $bankProfile = (object) [
            'bank_name' => $bankSnapshot['bank_name'],
            'account_number' => $bankSnapshot['account_number'],
            'account_holder' => $bankSnapshot['account_holder'],
            'updated_at' => $bankSnapshot['updated_at'] ?? null,
        ];
        $isBankProfileComplete = (bool) ($bankSnapshot['complete'] ?? false);

        return view('mitra.finance', [
            'summary' => $summary,
            'recentTransactions' => $recentTransactions,
            'walletSummary' => $walletSummary,
            'withdrawRequests' => $withdrawRequests,
            'bankProfile' => $bankProfile,
            'isBankProfileComplete' => $isBankProfileComplete,
        ]);
    }

    public function updateFinanceBank(WithdrawBankAccountUpdateRequest $request): RedirectResponse
    {
        $mitra = $request->user();

        if (! $this->withdrawBankAccounts->hasStorage()) {
            return redirect()->route('mitra.finance')
                ->with('error', 'Tabel rekening belum tersedia. Hubungi admin.');
        }

        $data = $request->validated();

        $normalizedBank = $this->withdrawBankAccounts->normalizeInput(
            $data['bank_name'] ?? null,
            $data['account_number'] ?? null,
            $data['account_holder'] ?? null
        );

        $this->withdrawBankAccounts->upsert(
            (int) $mitra->id,
            $normalizedBank['bank_name'],
            $normalizedBank['account_number'],
            $normalizedBank['account_holder']
        );

        $message = ((int) ($normalizedBank['filled_count'] ?? 0)) === 0
            ? 'Data rekening withdraw berhasil dikosongkan.'
            : 'Data rekening withdraw berhasil disimpan.';

        return redirect()->route('mitra.finance')->with('status', $message);
    }

    public function affiliates()
    {
        $mitra = request()->user();
        $affiliateRows = collect();
        $productRows = collect();

        if (Schema::hasTable('order_items') && Schema::hasTable('orders') && Schema::hasTable('users')) {
            $affiliateRows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('users as affiliate', 'affiliate.id', '=', 'order_items.affiliate_id')
                ->where('orders.seller_id', $mitra->id)
                ->where('orders.order_source', 'store_online')
                ->whereNotNull('order_items.affiliate_id')
                ->select(
                    'order_items.affiliate_id',
                    'affiliate.name as affiliate_name',
                    'affiliate.email as affiliate_email',
                    DB::raw('COUNT(DISTINCT order_items.order_id) as total_orders'),
                    DB::raw('SUM(order_items.qty) as total_qty'),
                    DB::raw('SUM(order_items.commission_amount) as total_commission')
                )
                ->groupBy('order_items.affiliate_id', 'affiliate.name', 'affiliate.email')
                ->orderByDesc(DB::raw('SUM(order_items.commission_amount)'))
                ->get();

            $productRows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.seller_id', $mitra->id)
                ->where('orders.order_source', 'store_online')
                ->whereNotNull('order_items.affiliate_id')
                ->select(
                    'order_items.product_name',
                    DB::raw('SUM(order_items.qty) as total_qty'),
                    DB::raw('SUM(order_items.commission_amount) as total_commission'),
                    DB::raw('COUNT(DISTINCT order_items.affiliate_id) as affiliate_count')
                )
                ->groupBy('order_items.product_name')
                ->orderByDesc(DB::raw('SUM(order_items.qty)'))
                ->limit(20)
                ->get();
        }

        return view('mitra.affiliates', [
            'affiliateRows' => $affiliateRows,
            'productRows' => $productRows,
        ]);
    }

    private function resolveWalletBalance(int $userId): float
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return 0.0;
        }

        return $this->walletService->getBalance($userId);
    }

    private function formatWeatherNotificationRow(object $notification): array
    {
        $typeClass = (string) data_get($notification, 'type', '');
        $isRecommendation = $typeClass === BehaviorRecommendationNotification::class;
        $status = strtolower(trim((string) data_get($notification, 'data.status', 'unknown')));
        $badgeClass = match ($status) {
            'red' => 'border-rose-200 bg-rose-100 text-rose-700',
            'yellow' => 'border-amber-200 bg-amber-100 text-amber-700',
            'green' => 'border-emerald-200 bg-emerald-100 text-emerald-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };

        $sentAtLabel = '-';
        $sentAtRaw = data_get($notification, 'data.sent_at') ?: data_get($notification, 'created_at');
        if ($sentAtRaw) {
            try {
                $sentAtLabel = Carbon::parse($sentAtRaw)->diffForHumans();
            } catch (\Throwable $e) {
                $sentAtLabel = '-';
            }
        }

        $validUntilLabel = null;
        $validUntilRaw = data_get($notification, 'data.valid_until');
        if ($validUntilRaw) {
            try {
                $validUntilLabel = Carbon::parse($validUntilRaw)->translatedFormat('d M Y H:i');
            } catch (\Throwable $e) {
                $validUntilLabel = null;
            }
        }

        return [
            'id' => (string) data_get($notification, 'id'),
            'type' => $isRecommendation ? 'recommendation' : 'weather',
            'type_label' => $isRecommendation ? 'Rekomendasi' : 'Cuaca',
            'filter_type' => $isRecommendation ? 'recommendation' : 'weather',
            'status' => $status,
            'badge_class' => $badgeClass,
            'title' => trim((string) data_get(
                $notification,
                'data.title',
                $isRecommendation ? 'Rekomendasi Operasional' : 'Notifikasi Cuaca'
            )),
            'message' => trim((string) data_get(
                $notification,
                'data.message',
                $isRecommendation
                    ? 'Ada rekomendasi operasional baru untuk akun mitra Anda.'
                    : 'Ada pembaruan cuaca untuk wilayah mitra Anda.'
            )),
            'target_label' => trim((string) data_get($notification, 'data.target_label', 'Wilayah Mitra')),
            'sent_at_label' => $sentAtLabel,
            'valid_until_label' => $validUntilLabel,
            'is_unread' => is_null(data_get($notification, 'read_at')),
        ];
    }
}
