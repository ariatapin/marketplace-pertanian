<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\PaymentOrderStatusNotification;
use App\Services\AffiliateLockPolicyService;
use App\Services\AffiliateReferralService;
use App\Services\AffiliateReferralTrackingService;
use App\Services\Location\LocationResolver;
use App\Services\Recommendation\RuleBasedRecommendationService;
use App\Services\UserRatingService;
use App\Services\WithdrawPolicyService;
use App\Services\WalletService;
use App\Services\Weather\WeatherAlertEngine;
use App\Services\Weather\WeatherService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ConsumerModeDashboardController extends Controller
{
    public function __construct(
        protected WalletService $wallet,
        protected AffiliateLockPolicyService $affiliateLockPolicy,
        protected AffiliateReferralService $affiliateReferral,
        protected AffiliateReferralTrackingService $affiliateTracking,
        protected WithdrawPolicyService $withdrawPolicy,
        protected RuleBasedRecommendationService $recommendationService,
        protected UserRatingService $userRatings,
        protected LocationResolver $locationResolver,
        protected WeatherService $weatherService,
        protected WeatherAlertEngine $weatherAlertEngine
    ) {}

    public function affiliate(Request $request): View
    {
        $user = $request->user();
        $withdrawPolicy = $this->withdrawPolicy->evaluate($user);
        $walletData = $this->buildAffiliateWalletSummary((int) $user->id);
        $productCommissionData = $this->buildAffiliateProductCommissionBreakdown((int) $user->id);
        $trackingSummary = $this->affiliateTracking->summaryForAffiliate((int) $user->id);
        $recentActivePromotedProducts = $this->buildAffiliateRecentActivePromotedProducts((int) $user->id, 5);
        $weatherSummary = $this->buildAffiliateWeatherSummary($user);

        return view('affiliate.dashboard', [
            'user' => $user,
            'summary' => $walletData['summary'],
            'recentCommissions' => $walletData['recent_commissions'],
            'productCommissions' => $productCommissionData['rows'],
            'productCommissionSummary' => $productCommissionData['summary'],
            'trackingSummary' => $trackingSummary,
            'affiliateReferralLink' => $this->affiliateReferral->buildLandingUrlForUser($user),
            'withdrawAllowed' => (bool) ($withdrawPolicy['allowed'] ?? false),
            'withdrawPolicyMessage' => (string) ($withdrawPolicy['message'] ?? ''),
            'minWithdraw' => $this->resolveMinWithdraw(),
            'recentActivePromotedProducts' => $recentActivePromotedProducts,
            'weatherSummary' => $weatherSummary,
        ]);
    }

    public function marketedProducts(Request $request): View
    {
        $user = $request->user();
        $filter = strtolower(trim($request->string('filter')->toString()));
        if (! in_array($filter, ['all', 'laku'], true)) {
            $filter = 'all';
        }

        $marketingData = $this->buildAffiliateMarketingSnapshot((int) $user->id, $filter);
        $lockPolicy = $this->affiliateLockPolicy->resolve();

        return view('affiliate.marketings', [
            'user' => $user,
            'marketingFilter' => $filter,
            'promotedProducts' => $marketingData['promoted_products'],
            'cooldownLocks' => $marketingData['cooldown_locks'],
            'marketingHistories' => $marketingData['marketing_histories'],
            'marketingSummary' => $marketingData['summary'],
            'lockPolicyDays' => max(1, min(365, (int) ($lockPolicy['lock_days'] ?? 30))),
            'lockPolicyEnabled' => (bool) ($lockPolicy['cooldown_enabled'] ?? true),
        ]);
    }

    public function promoteProduct(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);
        $redirectTarget = $this->resolveSafeRedirectTarget((string) ($data['redirect_to'] ?? ''));
        $redirectBack = function () use ($redirectTarget) {
            if ($redirectTarget !== null) {
                return redirect()->to($redirectTarget);
            }

            return redirect()->route('affiliate.marketings');
        };

        $productId = (int) ($data['product_id'] ?? 0);
        if ($productId <= 0 || ! Schema::hasTable('store_products')) {
            return $redirectBack()
                ->with('error', 'Produk affiliate tidak ditemukan.');
        }

        $product = DB::table('store_products')
            ->where('id', $productId)
            ->first([
                'id',
                'name',
                'is_active',
                'is_affiliate_enabled',
                'affiliate_expire_date',
            ]);

        if (! $product) {
            return $redirectBack()
                ->with('error', 'Produk affiliate tidak ditemukan.');
        }

        if (property_exists($product, 'is_active') && ! (bool) $product->is_active) {
            return $redirectBack()
                ->with('error', 'Produk sedang nonaktif dan belum bisa dipasarkan.');
        }

        if (! (bool) ($product->is_affiliate_enabled ?? false)) {
            return $redirectBack()
                ->with('error', 'Mitra belum mengaktifkan mode affiliate untuk produk ini.');
        }

        $expiry = $product->affiliate_expire_date ?? null;
        if (! empty($expiry)) {
            try {
                $isExpired = Carbon::parse((string) $expiry)->endOfDay()->isPast();
            } catch (\Throwable) {
                $isExpired = true;
            }

            if ($isExpired) {
                return $redirectBack()
                    ->with('error', 'Masa affiliate produk ini sudah berakhir.');
            }
        }

        DB::transaction(function () use ($request, $user, $productId): void {
            $this->ensureAffiliateProductLock(
                affiliateId: (int) $user->id,
                productId: $productId
            );

            if (Schema::hasTable('affiliate_referral_events')) {
                DB::table('affiliate_referral_events')->insert([
                    'affiliate_user_id' => (int) $user->id,
                    'actor_user_id' => (int) $user->id,
                    'product_id' => $productId,
                    'order_id' => null,
                    'event_type' => 'promote_selected',
                    'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                    'meta' => json_encode(['entry' => 'affiliate_marketplace_select']),
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return $redirectBack()
            ->with('status', 'Produk siap dipasarkan dan masuk daftar produk aktif affiliate.');
    }

    public function performance(Request $request): View
    {
        $user = $request->user();
        $period = strtolower(trim($request->string('period')->toString()));
        if (! in_array($period, ['all', 'weekly', 'monthly'], true)) {
            $period = 'all';
        }

        $window = $this->resolveAffiliatePerformanceWindow($period);
        $series = $this->buildAffiliatePerformanceSeries(
            affiliateUserId: (int) $user->id,
            bucket: (string) $window['bucket'],
            startAt: $window['start_at'],
            endAt: $window['end_at']
        );
        $topProducts = $this->buildAffiliateTopSellingProducts(
            affiliateUserId: (int) $user->id,
            startAt: $window['start_at'],
            endAt: $window['end_at'],
            limit: 8
        );

        return view('affiliate.performance', [
            'user' => $user,
            'periodFilter' => $period,
            'periodLabel' => $window['label'],
            'trackingSummary' => $this->affiliateTracking->summaryForAffiliate((int) $user->id),
            'performanceSeries' => $series,
            'topSellingProducts' => $topProducts,
        ]);
    }

    public function walletPage(Request $request): View
    {
        $user = $request->user();
        $withdrawPolicy = $this->withdrawPolicy->evaluate($user);
        $walletData = $this->buildAffiliateWalletSummary((int) $user->id);

        return view('affiliate.wallet', [
            'user' => $user,
            'summary' => $walletData['summary'],
            'recentCommissions' => $walletData['recent_commissions'],
            'walletTransactions' => $this->buildAffiliateWalletTransactions((int) $user->id),
            'withdrawHistories' => $this->buildAffiliateWithdrawHistory((int) $user->id),
            'withdrawAllowed' => (bool) ($withdrawPolicy['allowed'] ?? false),
            'withdrawPolicyMessage' => (string) ($withdrawPolicy['message'] ?? ''),
            'minWithdraw' => $this->resolveMinWithdraw(),
        ]);
    }

    /**
     * @return array{
     *   summary: array<string, float|int>,
     *   recent_commissions: Collection<int, object>
     * }
     */
    private function buildAffiliateWalletSummary(int $affiliateUserId): array
    {
        $walletBalance = $this->wallet->getBalance($affiliateUserId);
        $summary = [
            'balance' => $walletBalance,
            'available_balance' => $walletBalance,
            'reserved_withdraw_amount' => 0.0,
            'total_commission' => 0.0,
            'commission_count' => 0,
            'pending_withdraw_count' => 0,
        ];
        $recentCommissions = collect();

        if (Schema::hasTable('wallet_transactions')) {
            $commissionQuery = DB::table('wallet_transactions')
                ->where('wallet_transactions.wallet_id', $affiliateUserId)
                ->where('wallet_transactions.transaction_type', 'affiliate_commission');

            if (Schema::hasTable('orders')) {
                $commissionQuery
                    ->join('orders', 'orders.id', '=', 'wallet_transactions.reference_order_id')
                    ->where('orders.order_source', 'store_online')
                    ->where('orders.order_status', 'completed')
                    ->where('orders.payment_status', 'paid');
            }

            $summary['total_commission'] = (float) (clone $commissionQuery)->sum('wallet_transactions.amount');
            $summary['commission_count'] = (int) (clone $commissionQuery)->count();
            $recentCommissions = (clone $commissionQuery)
                ->orderByDesc('wallet_transactions.id')
                ->limit(10)
                ->get([
                    'wallet_transactions.id',
                    'wallet_transactions.amount',
                    'wallet_transactions.description',
                    'wallet_transactions.reference_order_id',
                    'wallet_transactions.created_at',
                ]);
        }

        if (Schema::hasTable('withdraw_requests')) {
            $withdrawQuery = DB::table('withdraw_requests')
                ->where('user_id', $affiliateUserId)
                ->whereIn('status', ['pending', 'approved']);

            $summary['pending_withdraw_count'] = (int) (clone $withdrawQuery)->count();
            $summary['reserved_withdraw_amount'] = round((float) (clone $withdrawQuery)->sum('amount'), 2);
            $summary['available_balance'] = round(max(
                0.0,
                (float) $summary['balance'] - (float) $summary['reserved_withdraw_amount']
            ), 2);
        }

        return [
            'summary' => $summary,
            'recent_commissions' => $recentCommissions,
        ];
    }

    /**
     * @return array{
     *   rows: Collection<int, object>,
     *   summary: array<string, int>
     * }
     */
    private function buildAffiliateProductCommissionBreakdown(
        int $affiliateUserId,
        ?Carbon $startAt = null,
        ?Carbon $endAt = null,
        int $limit = 10
    ): array {
        $rows = collect();
        $summary = [
            'product_count' => 0,
            'total_qty' => 0,
        ];

        if (! Schema::hasTable('order_items') || ! Schema::hasTable('orders')) {
            return ['rows' => $rows, 'summary' => $summary];
        }

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.affiliate_id', $affiliateUserId)
            ->where('orders.order_source', 'store_online')
            ->where('orders.order_status', 'completed')
            ->where('orders.payment_status', 'paid');

        if ($startAt instanceof Carbon) {
            $query->where('orders.updated_at', '>=', $startAt->toDateTimeString());
        }
        if ($endAt instanceof Carbon) {
            $query->where('orders.updated_at', '<=', $endAt->toDateTimeString());
        }

        $rows = $query
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc(DB::raw('SUM(order_items.commission_amount)'))
            ->limit(max(1, $limit))
            ->get([
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as total_orders'),
                DB::raw('SUM(order_items.commission_amount) as total_commission'),
                DB::raw('MAX(orders.updated_at) as last_sold_at'),
            ]);

        $summary['product_count'] = (int) $rows->count();
        $summary['total_qty'] = (int) $rows->sum(function ($row): int {
            return (int) ($row->total_qty ?? 0);
        });

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{
     *   promoted_products: Collection<int, object>,
     *   cooldown_locks: Collection<int, object>,
     *   marketing_histories: Collection<int, object>,
     *   summary: array<string, int>
     * }
     */
    private function buildAffiliateMarketingSnapshot(int $affiliateUserId, string $filter = 'all'): array
    {
        $today = now()->toDateString();
        $promotedProducts = collect();
        $cooldownLocks = collect();
        $marketingHistories = collect();
        $allPromotedCount = 0;
        $lakuPromotedCount = 0;

        $trackedProductIds = collect();

        if (Schema::hasTable('order_items')) {
            $trackedProductIds = $trackedProductIds->merge(
                DB::table('order_items')
                    ->where('affiliate_id', $affiliateUserId)
                    ->whereNotNull('product_id')
                    ->pluck('product_id')
            );
        }

        if (Schema::hasTable('affiliate_referral_events')) {
            $trackedProductIds = $trackedProductIds->merge(
                DB::table('affiliate_referral_events')
                    ->where('affiliate_user_id', $affiliateUserId)
                    ->whereNotNull('product_id')
                    ->pluck('product_id')
            );
        }

        if (Schema::hasTable('affiliate_locks')) {
            $trackedProductIds = $trackedProductIds->merge(
                DB::table('affiliate_locks')
                    ->where('affiliate_id', $affiliateUserId)
                    ->whereNotNull('product_id')
                    ->pluck('product_id')
            );
        }

        $trackedProductIds = $trackedProductIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if (
            $trackedProductIds->isNotEmpty()
            && Schema::hasTable('store_products')
            && Schema::hasTable('users')
        ) {
            $productRows = DB::table('store_products')
                ->leftJoin('users as mitra', 'mitra.id', '=', 'store_products.mitra_id')
                ->whereIn('store_products.id', $trackedProductIds)
                ->get([
                    'store_products.id',
                    'store_products.name',
                    'store_products.price',
                    'store_products.stock_qty',
                    'store_products.unit',
                    'store_products.image_url',
                    'store_products.is_active',
                    'store_products.is_affiliate_enabled',
                    'store_products.affiliate_expire_date',
                    'store_products.updated_at',
                    'store_products.created_at',
                    'mitra.name as mitra_name',
                ]);

            $salesByProduct = collect();
            if (Schema::hasTable('order_items') && Schema::hasTable('orders')) {
                $salesByProduct = DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('order_items.affiliate_id', $affiliateUserId)
                    ->whereIn('order_items.product_id', $trackedProductIds)
                    ->where('orders.order_source', 'store_online')
                    ->where('orders.order_status', 'completed')
                    ->where('orders.payment_status', 'paid')
                    ->groupBy('order_items.product_id')
                    ->get([
                        'order_items.product_id',
                        DB::raw('SUM(order_items.qty) as total_qty'),
                        DB::raw('COUNT(DISTINCT order_items.order_id) as total_orders'),
                        DB::raw('SUM(order_items.commission_amount) as total_commission'),
                        DB::raw('MAX(orders.updated_at) as last_sold_at'),
                    ])
                    ->keyBy(function ($row): int {
                        return (int) ($row->product_id ?? 0);
                    });
            }

            $mapped = $productRows->map(function ($row) use ($salesByProduct, $today) {
                $productId = (int) ($row->id ?? 0);
                $sales = $salesByProduct->get($productId);
                $expireRaw = ! empty($row->affiliate_expire_date)
                    ? Carbon::parse((string) $row->affiliate_expire_date)->toDateString()
                    : null;
                $isAffiliateWindowOpen = (bool) ($row->is_affiliate_enabled ?? false)
                    && ($expireRaw === null || $expireRaw >= $today);
                $isMarketable = (bool) ($row->is_active ?? false) && $isAffiliateWindowOpen;

                $statusReason = 'Tidak aktif dipasarkan.';
                if (! (bool) ($row->is_affiliate_enabled ?? false)) {
                    $statusReason = 'Mitra menonaktifkan fitur affiliate produk ini.';
                } elseif ($expireRaw !== null && $expireRaw < $today) {
                    $statusReason = 'Masa pemasaran affiliate telah berakhir dari pengaturan Mitra.';
                } elseif (! (bool) ($row->is_active ?? false)) {
                    $statusReason = 'Produk dinonaktifkan Mitra dari marketplace.';
                }

                return (object) [
                    'id' => $productId,
                    'name' => (string) (($row->name ?? '') !== '' ? $row->name : ('Produk #' . $productId)),
                    'price' => (float) ($row->price ?? 0),
                    'stock_qty' => (int) ($row->stock_qty ?? 0),
                    'unit' => (string) ($row->unit ?? ''),
                    'image_url' => (string) ($row->image_url ?? ''),
                    'mitra_name' => (string) (($row->mitra_name ?? '') !== '' ? $row->mitra_name : 'Mitra'),
                    'is_active' => (bool) ($row->is_active ?? false),
                    'is_affiliate_enabled' => (bool) ($row->is_affiliate_enabled ?? false),
                    'affiliate_expire_date' => $expireRaw,
                    'is_marketable' => $isMarketable,
                    'status_reason' => $statusReason,
                    'total_sold_qty' => (int) ($sales->total_qty ?? 0),
                    'total_sold_orders' => (int) ($sales->total_orders ?? 0),
                    'total_commission' => (float) ($sales->total_commission ?? 0),
                    'last_sold_at' => $sales->last_sold_at ?? null,
                ];
            });

            $allPromoted = $mapped->filter(fn ($row) => (bool) ($row->is_marketable ?? false))->values();
            $allPromotedCount = (int) $allPromoted->count();
            $lakuPromotedCount = (int) $allPromoted->filter(fn ($row) => (int) ($row->total_sold_orders ?? 0) > 0)->count();

            $promotedProducts = $allPromoted;
            if ($filter === 'laku') {
                $promotedProducts = $promotedProducts
                    ->filter(fn ($row) => (int) ($row->total_sold_orders ?? 0) > 0)
                    ->values();
            }

            $promotedProducts = $promotedProducts
                ->sortByDesc('total_sold_orders')
                ->values();

            $marketingHistories = $mapped
                ->filter(fn ($row) => ! (bool) ($row->is_marketable ?? false))
                ->sortByDesc(function ($row): int {
                    $raw = (string) ($row->affiliate_expire_date ?? $row->last_sold_at ?? '');
                    $parsed = null;
                    if ($raw !== '') {
                        try {
                            $parsed = Carbon::parse($raw);
                        } catch (\Throwable) {
                            $parsed = null;
                        }
                    }

                    return (int) ($parsed?->timestamp ?? 0);
                })
                ->values();
        }

        if (
            Schema::hasTable('affiliate_locks')
            && Schema::hasTable('store_products')
            && Schema::hasTable('users')
        ) {
            $cooldownLocks = DB::table('affiliate_locks')
                ->join('store_products', 'store_products.id', '=', 'affiliate_locks.product_id')
                ->leftJoin('users as mitra', 'mitra.id', '=', 'store_products.mitra_id')
                ->where('affiliate_locks.affiliate_id', $affiliateUserId)
                ->where('affiliate_locks.is_active', true)
                ->whereDate('affiliate_locks.expiry_date', '>=', $today)
                ->orderBy('affiliate_locks.expiry_date')
                ->get([
                    'affiliate_locks.id',
                    'affiliate_locks.start_date',
                    'affiliate_locks.expiry_date',
                    'store_products.id as product_id',
                    'store_products.name as product_name',
                    'mitra.name as mitra_name',
                ])
                ->map(function ($row) {
                    $expiry = Carbon::parse((string) $row->expiry_date);
                    $productName = (string) (($row->product_name ?? '') !== '' ? $row->product_name : ('Produk #' . (int) ($row->product_id ?? 0)));
                    $mitraName = (string) (($row->mitra_name ?? '') !== '' ? $row->mitra_name : 'Mitra');

                    return (object) [
                        'id' => (int) ($row->id ?? 0),
                        'product_id' => (int) ($row->product_id ?? 0),
                        'product_name' => $productName,
                        'mitra_name' => $mitraName,
                        'start_date' => Carbon::parse((string) $row->start_date)->toDateString(),
                        'expiry_date' => $expiry->toDateString(),
                        'days_left' => max(0, now()->startOfDay()->diffInDays($expiry->startOfDay(), false)),
                        'lock_message' => "Anda sedang memasarkan produk {$productName} dari Toko {$mitraName}. Anda bisa memasarkan produk ini kembali pada {$expiry->translatedFormat('d M Y')}.",
                    ];
                })
                ->values();
        }

        return [
            'promoted_products' => $promotedProducts,
            'cooldown_locks' => $cooldownLocks,
            'marketing_histories' => $marketingHistories,
            'summary' => [
                'all_promoted_count' => $allPromotedCount,
                'laku_promoted_count' => $lakuPromotedCount,
                'current_filter_count' => (int) $promotedProducts->count(),
                'cooldown_count' => (int) $cooldownLocks->count(),
                'history_count' => (int) $marketingHistories->count(),
            ],
        ];
    }

    /**
     * @return array{label:string,bucket:string,start_at:Carbon,end_at:Carbon}
     */
    private function resolveAffiliatePerformanceWindow(string $period): array
    {
        $now = now();

        if ($period === 'weekly') {
            return [
                'label' => 'Mingguan (7 Hari)',
                'bucket' => 'day',
                'start_at' => $now->copy()->subDays(6)->startOfDay(),
                'end_at' => $now->copy()->endOfDay(),
            ];
        }

        if ($period === 'monthly') {
            return [
                'label' => 'Bulanan (30 Hari)',
                'bucket' => 'day',
                'start_at' => $now->copy()->subDays(29)->startOfDay(),
                'end_at' => $now->copy()->endOfDay(),
            ];
        }

        return [
            'label' => 'Semua (12 Bulan)',
            'bucket' => 'month',
            'start_at' => $now->copy()->subMonths(11)->startOfMonth(),
            'end_at' => $now->copy()->endOfMonth(),
        ];
    }

    /**
     * @return array{
     *   rows:Collection<int, array{label:string,checkout:int,completed:int}>,
     *   max_value:int
     * }
     */
    private function buildAffiliatePerformanceSeries(
        int $affiliateUserId,
        string $bucket,
        Carbon $startAt,
        Carbon $endAt
    ): array {
        $labels = [];
        $checkoutCounts = [];
        $completedCounts = [];

        if ($bucket === 'month') {
            $cursor = $startAt->copy()->startOfMonth();
            $last = $endAt->copy()->startOfMonth();
            while ($cursor->lte($last)) {
                $key = $cursor->format('Y-m');
                $labels[$key] = $cursor->translatedFormat('M Y');
                $checkoutCounts[$key] = 0;
                $completedCounts[$key] = 0;
                $cursor->addMonth();
            }
        } else {
            $cursor = $startAt->copy()->startOfDay();
            $last = $endAt->copy()->startOfDay();
            while ($cursor->lte($last)) {
                $key = $cursor->format('Y-m-d');
                $labels[$key] = $cursor->translatedFormat('d M');
                $checkoutCounts[$key] = 0;
                $completedCounts[$key] = 0;
                $cursor->addDay();
            }
        }

        if (Schema::hasTable('affiliate_referral_events')) {
            $checkoutRows = DB::table('affiliate_referral_events')
                ->where('affiliate_user_id', $affiliateUserId)
                ->where('event_type', 'checkout_created')
                ->whereNotNull('occurred_at')
                ->whereBetween('occurred_at', [$startAt->toDateTimeString(), $endAt->toDateTimeString()])
                ->get(['occurred_at']);

            foreach ($checkoutRows as $row) {
                try {
                    $occurredAt = Carbon::parse((string) $row->occurred_at);
                } catch (\Throwable) {
                    continue;
                }

                $key = $bucket === 'month'
                    ? $occurredAt->format('Y-m')
                    : $occurredAt->format('Y-m-d');
                if (! array_key_exists($key, $checkoutCounts)) {
                    continue;
                }
                $checkoutCounts[$key] = ((int) ($checkoutCounts[$key] ?? 0)) + 1;
            }
        }

        if (Schema::hasTable('order_items') && Schema::hasTable('orders')) {
            $completedRows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.affiliate_id', $affiliateUserId)
                ->where('orders.order_source', 'store_online')
                ->where('orders.order_status', 'completed')
                ->where('orders.payment_status', 'paid')
                ->whereBetween('orders.updated_at', [$startAt->toDateTimeString(), $endAt->toDateTimeString()])
                ->distinct()
                ->get(['orders.id as order_id', 'orders.updated_at']);

            foreach ($completedRows as $row) {
                try {
                    $occurredAt = Carbon::parse((string) $row->updated_at);
                } catch (\Throwable) {
                    continue;
                }

                $key = $bucket === 'month'
                    ? $occurredAt->format('Y-m')
                    : $occurredAt->format('Y-m-d');
                if (! array_key_exists($key, $completedCounts)) {
                    continue;
                }
                $completedCounts[$key] = ((int) ($completedCounts[$key] ?? 0)) + 1;
            }
        }

        $rows = collect();
        foreach ($labels as $key => $label) {
            $rows->push([
                'bucket_key' => $key,
                'label' => $label,
                'checkout' => (int) ($checkoutCounts[$key] ?? 0),
                'completed' => (int) ($completedCounts[$key] ?? 0),
            ]);
        }

        $maxCheckout = (int) ($rows->max('checkout') ?? 0);
        $maxCompleted = (int) ($rows->max('completed') ?? 0);
        $maxValue = max(1, $maxCheckout, $maxCompleted);

        return [
            'rows' => $rows,
            'max_value' => $maxValue,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function buildAffiliateTopSellingProducts(
        int $affiliateUserId,
        ?Carbon $startAt = null,
        ?Carbon $endAt = null,
        int $limit = 8
    ): Collection {
        if (! Schema::hasTable('order_items') || ! Schema::hasTable('orders')) {
            return collect();
        }

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.affiliate_id', $affiliateUserId)
            ->where('orders.order_source', 'store_online')
            ->where('orders.order_status', 'completed')
            ->where('orders.payment_status', 'paid');

        if ($startAt instanceof Carbon) {
            $query->where('orders.updated_at', '>=', $startAt->toDateTimeString());
        }
        if ($endAt instanceof Carbon) {
            $query->where('orders.updated_at', '<=', $endAt->toDateTimeString());
        }

        $rows = $query
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc(DB::raw('SUM(order_items.qty)'))
            ->limit(max(1, $limit))
            ->get([
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as total_orders'),
                DB::raw('SUM(order_items.commission_amount) as total_commission'),
            ]);

        $maxQty = (int) ($rows->max(function ($row): int {
            return (int) ($row->total_qty ?? 0);
        }) ?? 0);
        $safeMaxQty = max(1, $maxQty);

        return $rows
            ->map(function ($row) use ($safeMaxQty) {
                $qty = (int) ($row->total_qty ?? 0);

                return (object) [
                    'product_id' => (int) ($row->product_id ?? 0),
                    'product_name' => (string) (($row->product_name ?? '') !== '' ? $row->product_name : ('Produk #' . (int) ($row->product_id ?? 0))),
                    'total_qty' => $qty,
                    'total_orders' => (int) ($row->total_orders ?? 0),
                    'total_commission' => (float) ($row->total_commission ?? 0),
                    'progress_percent' => round(($qty / $safeMaxQty) * 100, 2),
                ];
            })
            ->values();
    }

    /**
     * Sinkron lock produk affiliate agar workspace "Dipasarkan" langsung mencerminkan pilihan produk terbaru.
     */
    private function ensureAffiliateProductLock(int $affiliateId, int $productId): void
    {
        if (
            $affiliateId <= 0
            || $productId <= 0
            || ! Schema::hasTable('affiliate_locks')
        ) {
            return;
        }

        $policy = $this->affiliateLockPolicy->resolve();
        if (! (bool) ($policy['cooldown_enabled'] ?? true)) {
            return;
        }

        $lockDays = max(1, min(365, (int) ($policy['lock_days'] ?? 30)));
        $refreshOnRepromote = (bool) ($policy['refresh_on_repromote'] ?? false);
        $today = now()->toDateString();

        $activeLock = DB::table('affiliate_locks')
            ->where('affiliate_id', $affiliateId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first(['id', 'expiry_date']);

        if ($activeLock) {
            $stillActive = false;
            if (! empty($activeLock->expiry_date)) {
                try {
                    $stillActive = Carbon::parse((string) $activeLock->expiry_date)->toDateString() >= $today;
                } catch (\Throwable) {
                    $stillActive = false;
                }
            }

            if ($stillActive && ! $refreshOnRepromote) {
                return;
            }

            DB::table('affiliate_locks')
                ->where('id', (int) $activeLock->id)
                ->update([
                    'start_date' => $today,
                    'expiry_date' => now()->addDays($lockDays)->toDateString(),
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('affiliate_locks')->insert([
            'affiliate_id' => $affiliateId,
            'product_id' => $productId,
            'is_active' => true,
            'start_date' => $today,
            'expiry_date' => now()->addDays($lockDays)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Ambil maksimal N produk aktif terbaru yang sedang dipasarkan affiliate berdasarkan waktu aktivasi lock.
     *
     * @return Collection<int, object>
     */
    private function buildAffiliateRecentActivePromotedProducts(int $affiliateUserId, int $limit = 5): Collection
    {
        if (
            $affiliateUserId <= 0
            || $limit <= 0
            || ! Schema::hasTable('affiliate_locks')
            || ! Schema::hasTable('store_products')
            || ! Schema::hasTable('users')
        ) {
            return collect();
        }

        $today = now()->toDateString();
        $query = DB::table('affiliate_locks')
            ->join('store_products', 'store_products.id', '=', 'affiliate_locks.product_id')
            ->leftJoin('users as mitra', 'mitra.id', '=', 'store_products.mitra_id')
            ->where('affiliate_locks.affiliate_id', $affiliateUserId)
            ->where('affiliate_locks.is_active', true)
            ->whereDate('affiliate_locks.expiry_date', '>=', $today)
            ->where('store_products.is_affiliate_enabled', true)
            ->where(function ($builder) use ($today) {
                $builder->whereNull('store_products.affiliate_expire_date')
                    ->orWhereDate('store_products.affiliate_expire_date', '>=', $today);
            });

        if (Schema::hasColumn('store_products', 'is_active')) {
            $query->where('store_products.is_active', true);
        }

        return $query
            ->orderByDesc('affiliate_locks.start_date')
            ->orderByDesc('affiliate_locks.id')
            ->limit(max(1, $limit))
            ->get([
                'affiliate_locks.product_id',
                'affiliate_locks.start_date',
                'affiliate_locks.expiry_date',
                'store_products.name as product_name',
                'mitra.name as mitra_name',
            ])
            ->map(function ($row) {
                $expiry = Carbon::parse((string) $row->expiry_date);
                return (object) [
                    'product_id' => (int) ($row->product_id ?? 0),
                    'product_name' => (string) (($row->product_name ?? '') !== '' ? $row->product_name : ('Produk #' . (int) ($row->product_id ?? 0))),
                    'mitra_name' => (string) (($row->mitra_name ?? '') !== '' ? $row->mitra_name : 'Mitra'),
                    'start_date' => Carbon::parse((string) $row->start_date)->toDateString(),
                    'expiry_date' => $expiry->toDateString(),
                    'days_left' => max(0, now()->startOfDay()->diffInDays($expiry->startOfDay(), false)),
                ];
            })
            ->values();
    }

    /**
     * Ringkasan cuaca dashboard affiliate berbasis lokasi user aktif.
     *
     * @return array<string, mixed>
     */
    private function buildAffiliateWeatherSummary(?User $user): array
    {
        $loc = $this->locationResolver->forUser($user);
        $summary = [
            'location_label' => (string) ($loc['label'] ?? 'Lokasi belum diset'),
            'temperature_label' => '-',
            'humidity_label' => '-',
            'wind_label' => '-',
            'severity_label' => 'NORMAL',
            'severity_badge_class' => 'border-emerald-200 bg-emerald-100 text-emerald-700',
            'message' => 'Cuaca relatif aman.',
            'valid_until_label' => null,
        ];

        try {
            $current = $this->weatherService->current(
                (string) ($loc['type'] ?? 'custom'),
                (int) ($loc['id'] ?? 0),
                (float) ($loc['lat'] ?? 0),
                (float) ($loc['lng'] ?? 0)
            );
            $forecast = $this->weatherService->forecast(
                (string) ($loc['type'] ?? 'custom'),
                (int) ($loc['id'] ?? 0),
                (float) ($loc['lat'] ?? 0),
                (float) ($loc['lng'] ?? 0)
            );
            $alert = $this->weatherAlertEngine->evaluateForecast(is_array($forecast) ? $forecast : []);

            $severity = strtolower(trim((string) ($alert['severity'] ?? 'green')));
            $severityLabel = match ($severity) {
                'red' => 'SIAGA TINGGI',
                'yellow' => 'WASPADA',
                default => 'NORMAL',
            };
            $severityBadgeClass = match ($severity) {
                'red' => 'border-rose-200 bg-rose-100 text-rose-700',
                'yellow' => 'border-amber-200 bg-amber-100 text-amber-700',
                default => 'border-emerald-200 bg-emerald-100 text-emerald-700',
            };

            $temperature = data_get($current, 'main.temp');
            $humidity = data_get($current, 'main.humidity');
            $wind = data_get($current, 'wind.speed');
            $validUntilRaw = data_get($alert, 'valid_until');
            $validUntilLabel = null;
            if (! empty($validUntilRaw)) {
                try {
                    $validUntilLabel = Carbon::parse((string) $validUntilRaw)->translatedFormat('d M Y H:i');
                } catch (\Throwable) {
                    $validUntilLabel = null;
                }
            }

            $summary['temperature_label'] = is_numeric($temperature) ? number_format((float) $temperature, 1, ',', '.') . ' C' : '-';
            $summary['humidity_label'] = is_numeric($humidity) ? number_format((float) $humidity, 0, ',', '.') . ' %' : '-';
            $summary['wind_label'] = is_numeric($wind) ? number_format((float) $wind, 1, ',', '.') . ' m/s' : '-';
            $summary['severity_label'] = $severityLabel;
            $summary['severity_badge_class'] = $severityBadgeClass;
            $summary['message'] = (string) ($alert['message'] ?? $summary['message']);
            $summary['valid_until_label'] = $validUntilLabel;
        } catch (\Throwable) {
            // Silent fallback: dashboard tetap render walau fetch cuaca gagal.
        }

        return $summary;
    }

    /**
     * @return Collection<int, object>
     */
    private function buildAffiliateWalletTransactions(int $affiliateUserId, int $limit = 30): Collection
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return collect();
        }

        return DB::table('wallet_transactions')
            ->where('wallet_id', $affiliateUserId)
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get([
                'id',
                'amount',
                'transaction_type',
                'description',
                'reference_order_id',
                'reference_withdraw_id',
                'created_at',
            ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function buildAffiliateWithdrawHistory(int $affiliateUserId, int $limit = 20): Collection
    {
        if (! Schema::hasTable('withdraw_requests')) {
            return collect();
        }

        return DB::table('withdraw_requests')
            ->where('user_id', $affiliateUserId)
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get([
                'id',
                'amount',
                'status',
                'notes',
                'processed_at',
                'transfer_reference',
                'created_at',
            ]);
    }

    public function seller(Request $request): View
    {
        $user = $request->user();
        $withdrawPolicy = $this->withdrawPolicy->evaluate($user);

        $orderCounts = [
            'pending_payment' => 0,
            'paid' => 0,
            'packed' => 0,
            'shipped' => 0,
            'completed' => 0,
        ];
        $recentOrders = collect();
        $allBuyerOrdersCount = 0;
        $productSummary = [
            'total' => 0,
            'total_stock' => 0,
        ];
        $ratingSummary = $this->userRatings->summaryForUser((int) $user->id);
        $recentProducts = collect();
        $dashboardNotifications = collect();
        $dashboardNotificationUnreadCount = 0;

        try {
            // CATATAN-AUDIT: Seller dashboard harus memicu sinkronisasi rekomendasi agar feed selalu mutakhir.
            $this->recommendationService->syncForUser($user);
        } catch (\Throwable $e) {
            // Silent fallback: dashboard tetap render walau sinkronisasi rekomendasi gagal.
        }

        if (Schema::hasTable('orders')) {
            // Dashboard penjual consumer hanya menampilkan order P2P hasil tani sendiri.
            $orders = DB::table('orders')
                ->where('seller_id', $user->id)
                ->where('order_source', 'farmer_p2p');

            $allBuyerOrdersCount = (int) (clone $orders)->count();

            $orderCounts['pending_payment'] = (int) (clone $orders)->where('order_status', 'pending_payment')->count();
            $orderCounts['paid'] = (int) (clone $orders)->where('order_status', 'paid')->count();
            $orderCounts['packed'] = (int) (clone $orders)->where('order_status', 'packed')->count();
            $orderCounts['shipped'] = (int) (clone $orders)->where('order_status', 'shipped')->count();
            $orderCounts['completed'] = (int) (clone $orders)->where('order_status', 'completed')->count();

            $recentOrdersQuery = clone $orders;
            $hasUsersTable = Schema::hasTable('users');
            if ($hasUsersTable) {
                $recentOrdersQuery->leftJoin('users as buyers', 'buyers.id', '=', 'orders.buyer_id');
            }

            $recentOrderSelect = [
                'orders.id',
                'orders.buyer_id',
                'orders.total_amount',
                'orders.order_status',
                'orders.payment_status',
                'orders.payment_method',
                'orders.updated_at',
            ];
            if ($hasUsersTable) {
                $recentOrderSelect[] = DB::raw("COALESCE(buyers.name, '') as buyer_name");
                $recentOrderSelect[] = DB::raw("COALESCE(buyers.email, '') as buyer_email");
            } else {
                $recentOrderSelect[] = DB::raw("'' as buyer_name");
                $recentOrderSelect[] = DB::raw("'' as buyer_email");
            }

            $recentOrders = $recentOrdersQuery
                ->select($recentOrderSelect)
                ->orderByDesc('id')
                ->limit(3)
                ->get();
        }

        if (Schema::hasTable('farmer_harvests')) {
            $products = DB::table('farmer_harvests')
                ->where('farmer_id', $user->id);

            $productSummary['total'] = (int) (clone $products)->count();
            $productSummary['total_stock'] = (int) (clone $products)->sum('stock_qty');

            $recentProducts = (clone $products)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get([
                    'id',
                    'name',
                    'price',
                    'stock_qty',
                    'updated_at',
                ]);
        }

        [
            'feed' => $dashboardNotifications,
            'unread_count' => $dashboardNotificationUnreadCount,
        ] = $this->buildSellerDashboardNotifications($user);

        return view('seller.dashboard', [
            'user' => $user,
            'walletBalance' => $this->wallet->getBalance($user->id),
            'orderCounts' => $orderCounts,
            'recentOrders' => $recentOrders,
            'allBuyerOrdersCount' => $allBuyerOrdersCount,
            'hasMoreBuyerOrders' => $allBuyerOrdersCount > $recentOrders->count(),
            'productSummary' => $productSummary,
            'ratingSummary' => $ratingSummary,
            'recentProducts' => $recentProducts,
            'dashboardNotifications' => $dashboardNotifications,
            'dashboardNotificationUnreadCount' => $dashboardNotificationUnreadCount,
            'withdrawAllowed' => (bool) ($withdrawPolicy['allowed'] ?? false),
            'withdrawPolicyMessage' => (string) ($withdrawPolicy['message'] ?? ''),
            'minWithdraw' => $this->resolveMinWithdraw(),
        ]);
    }

    public function sellerOrders(Request $request): View
    {
        $user = $request->user();

        $orderCounts = [
            'pending_payment' => 0,
            'paid' => 0,
            'packed' => 0,
            'shipped' => 0,
            'completed' => 0,
        ];
        $allBuyerOrdersCount = 0;
        $ordersPaginator = null;

        if (Schema::hasTable('orders')) {
            $orders = DB::table('orders')
                ->where('seller_id', $user->id)
                ->where('order_source', 'farmer_p2p');

            $allBuyerOrdersCount = (int) (clone $orders)->count();
            $orderCounts['pending_payment'] = (int) (clone $orders)->where('order_status', 'pending_payment')->count();
            $orderCounts['paid'] = (int) (clone $orders)->where('order_status', 'paid')->count();
            $orderCounts['packed'] = (int) (clone $orders)->where('order_status', 'packed')->count();
            $orderCounts['shipped'] = (int) (clone $orders)->where('order_status', 'shipped')->count();
            $orderCounts['completed'] = (int) (clone $orders)->where('order_status', 'completed')->count();

            $ordersQuery = clone $orders;
            $hasUsersTable = Schema::hasTable('users');
            if ($hasUsersTable) {
                $ordersQuery->leftJoin('users as buyers', 'buyers.id', '=', 'orders.buyer_id');
            }

            $orderSelect = [
                'orders.id',
                'orders.buyer_id',
                'orders.total_amount',
                'orders.order_status',
                'orders.payment_status',
                'orders.payment_method',
                'orders.updated_at',
            ];

            if ($hasUsersTable) {
                $orderSelect[] = DB::raw("COALESCE(buyers.name, '') as buyer_name");
                $orderSelect[] = DB::raw("COALESCE(buyers.email, '') as buyer_email");
            } else {
                $orderSelect[] = DB::raw("'' as buyer_name");
                $orderSelect[] = DB::raw("'' as buyer_email");
            }

            $ordersPaginator = $ordersQuery
                ->select($orderSelect)
                ->orderByDesc('orders.id')
                ->paginate(12)
                ->withQueryString();
        }

        return view('seller.orders', [
            'user' => $user,
            'orderCounts' => $orderCounts,
            'allBuyerOrdersCount' => $allBuyerOrdersCount,
            'ordersPaginator' => $ordersPaginator,
        ]);
    }

    /**
     * @return array{
     *   feed: Collection<int, array<string, mixed>>,
     *   unread_count: int
     * }
     */
    private function buildSellerDashboardNotifications(User $user, int $limit = 6): array
    {
        $feed = collect();
        $unreadCount = 0;

        if (! Schema::hasTable('notifications')) {
            return [
                'feed' => $feed,
                'unread_count' => $unreadCount,
            ];
        }

        $notificationTypes = [
            PaymentOrderStatusNotification::class,
            AdminWeatherNoticeNotification::class,
        ];

        $query = $user->notifications()
            ->whereIn('type', $notificationTypes);

        $unreadCount = (int) (clone $query)
            ->whereNull('read_at')
            ->count();

        $feed = (clone $query)
            ->latest('created_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(function ($notification) {
                $data = is_array($notification->data) ? $notification->data : [];
                $isOrderNotification = (string) ($notification->type ?? '') === PaymentOrderStatusNotification::class;
                $status = strtolower(trim((string) ($data['status'] ?? 'info')));
                if ($status === '') {
                    $status = 'info';
                }

                return [
                    'id' => (string) $notification->id,
                    'channel' => $isOrderNotification ? 'order' : 'admin',
                    'channel_label' => $isOrderNotification ? 'Order Pembeli' : 'Admin',
                    'status' => $status,
                    'title' => trim((string) ($data['title'] ?? '')) !== ''
                        ? (string) $data['title']
                        : ($isOrderNotification ? 'Update Order Pembeli' : 'Notifikasi Admin'),
                    'message' => trim((string) ($data['message'] ?? '')) !== ''
                        ? (string) $data['message']
                        : 'Tidak ada detail notifikasi.',
                    'action_url' => (string) ($data['action_url'] ?? ''),
                    'action_label' => (string) ($data['action_label'] ?? 'Buka Detail'),
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'created_at_label' => $notification->created_at
                        ? $notification->created_at->translatedFormat('d M Y H:i')
                        : '-',
                ];
            })
            ->values();

        return [
            'feed' => $feed,
            'unread_count' => $unreadCount,
        ];
    }

    private function resolveMinWithdraw(): float
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('admin_profiles')) {
            return 0.0;
        }

        $admin = User::query()
            ->whereNormalizedRole('admin')
            ->orderBy('id')
            ->first(['id']);
        if (! $admin) {
            return 0.0;
        }

        $adminProfile = DB::table('admin_profiles')->where('user_id', $admin->id)->first(['min_withdraw_amount']);

        return (float) ($adminProfile?->min_withdraw_amount ?? 0);
    }

    private function resolveSafeRedirectTarget(string $rawTarget): ?string
    {
        $target = trim($rawTarget);
        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '/')) {
            return str_starts_with($target, '//') ? null : $target;
        }

        if (! preg_match('/^https?:\/\//i', $target)) {
            return null;
        }

        $parsedTarget = parse_url($target);
        $parsedAppUrl = parse_url((string) config('app.url', ''));
        if (! is_array($parsedTarget) || ! is_array($parsedAppUrl)) {
            return null;
        }

        $targetHost = strtolower((string) ($parsedTarget['host'] ?? ''));
        $appHost = strtolower((string) ($parsedAppUrl['host'] ?? ''));
        if ($targetHost === '' || $appHost === '' || $targetHost !== $appHost) {
            return null;
        }

        $path = (string) ($parsedTarget['path'] ?? '/');
        $query = isset($parsedTarget['query']) ? ('?' . $parsedTarget['query']) : '';
        $fragment = isset($parsedTarget['fragment']) ? ('#' . $parsedTarget['fragment']) : '';

        return $path . $query . $fragment;
    }
}
