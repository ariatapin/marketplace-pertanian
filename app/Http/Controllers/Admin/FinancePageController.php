<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AffiliateCommissionPolicyService;
use App\Services\PaymentMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancePageController extends Controller
{
    public function __construct(
        protected PaymentMethodService $paymentMethods,
        protected AffiliateCommissionPolicyService $affiliateCommissionPolicy
    ) {}

    public function __invoke(Request $request)
    {
        return $this->renderFinance($request, null);
    }

    public function withdrawByRole(Request $request, string $role)
    {
        return $this->renderFinance($request, $role);
    }

    public function updateAffiliateCommissionRange(Request $request): RedirectResponse
    {
        $range = $this->affiliateCommissionPolicy->resolveRange();

        $validated = $request->validate([
            'affiliate_commission_min_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'affiliate_commission_max_percent' => ['required', 'numeric', 'min:0', 'max:100', 'gte:affiliate_commission_min_percent'],
        ], [
            'affiliate_commission_max_percent.gte' => 'Komisi maksimal harus lebih besar atau sama dengan komisi minimal.',
        ]);

        $adminUser = User::query()
            ->whereNormalizedRole('admin')
            ->orderBy('id')
            ->first(['id']);

        $targetAdminUserId = (int) ($adminUser?->id ?? ($request->user()?->id ?? 0));
        $saved = $this->affiliateCommissionPolicy->persistRange(
            $targetAdminUserId,
            (float) $validated['affiliate_commission_min_percent'],
            (float) $validated['affiliate_commission_max_percent']
        );

        if (! $saved) {
            return back()->withErrors([
                'affiliate_commission_range' => 'Pengaturan komisi affiliate belum tersedia. Jalankan migration terbaru.',
            ]);
        }

        $updatedRange = $this->affiliateCommissionPolicy->resolveRange();

        return back()->with('status', sprintf(
            'Batas komisi affiliate disimpan: %s%% sampai %s%%.',
            $this->affiliateCommissionPolicy->formatPercent((float) ($updatedRange['min'] ?? $range['min'] ?? 0)),
            $this->affiliateCommissionPolicy->formatPercent((float) ($updatedRange['max'] ?? $range['max'] ?? 100))
        ));
    }

    private function renderFinance(Request $request, ?string $forcedRole)
    {
        $period = $request->string('period')->toString();
        $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'daily';
        $chartMode = $request->string('chart_mode')->toString();
        $chartMode = in_array($chartMode, ['nominal', 'count'], true) ? $chartMode : 'nominal';
        $periodConfig = $this->resolvePeriodConfig($period, (int) $request->integer('window'));
        $fromDate = $periodConfig['from'];
        $bucketSql = $periodConfig['bucket_sql'];
        $periodLabel = $periodConfig['label'];
        $window = $periodConfig['window'];
        $windowOptions = $periodConfig['window_options'];

        $section = $request->string('section')->toString();
        $withdrawStatus = $request->string('withdraw_status')->toString();
        $keyword = trim($request->string('q')->toString());
        $transferState = $request->string('transfer_state')->toString();
        $transferKeyword = trim($request->string('transfer_q')->toString());
        $transferMethod = $request->string('transfer_method')->toString();
        $activeRole = in_array($forcedRole, ['mitra', 'affiliate', 'farmer_seller'], true) ? $forcedRole : '';
        $normalizedSection = in_array($section, ['overview', 'affiliate', 'withdraw', 'transfer'], true) ? $section : 'overview';
        $paymentMethodMap = $this->paymentMethods->labelMap();
        $allowedTransferMethods = array_keys($paymentMethodMap);
        $affiliateCommissionRange = $this->affiliateCommissionPolicy->resolveRange();
        $primaryAdminUser = User::query()
            ->whereNormalizedRole('admin')
            ->orderBy('id')
            ->first(['id']);
        $adminWalletUserId = (int) ($primaryAdminUser?->id ?? ($request->user()?->id ?? 0));

        $summary = [
            'admin_wallet_balance' => 0,
            'all_time_wallet_in' => 0,
            'all_time_wallet_out' => 0,
            'all_time_net_wallet_flow' => 0,
            'gross_profit' => 0,
            'total_wallet_in' => 0,
            'total_wallet_out' => 0,
            'net_wallet_flow' => 0,
            'transaction_in_count' => 0,
            'transaction_out_count' => 0,
            'pending_withdraw_amount' => 0,
            'pending_withdraw_count' => 0,
            'period_pending_withdraw_amount' => 0,
            'period_pending_withdraw_count' => 0,
        ];

        $incomeSeries = collect();
        $periodRangeLabel = '-';
        $comparisonSummary = [
            'transaksi_masuk' => 0,
            'transaksi_keluar' => 0,
            'transaksi_masuk_count' => 0,
            'transaksi_keluar_count' => 0,
            'laba_kotor' => 0,
            'laba_bersih' => 0,
        ];
        $incomeChartRows = collect();
        $comparisonBars = collect();
        $transferSummary = [
            'waiting_verification' => 0,
            'verified' => 0,
            'with_proof' => 0,
            'without_proof' => 0,
        ];
        $affiliateCommissionSummary = [
            'total_products' => 0,
            'affiliate_enabled_products' => 0,
            'configured_min_percent' => (float) ($affiliateCommissionRange['min'] ?? 0),
            'configured_max_percent' => (float) ($affiliateCommissionRange['max'] ?? 100),
            'min_commission_percent' => 0,
            'max_commission_percent' => 0,
            'avg_commission_percent' => 0,
            'out_of_range_products' => 0,
        ];
        $affiliateCommissionRows = collect();
        $roleWithdrawSummary = collect();
        $withdrawRows = collect();
        $transferRows = collect();

        if (Schema::hasTable('wallet_transactions')) {
            $summary['admin_wallet_balance'] = (float) DB::table('wallet_transactions')
                ->where('wallet_id', $adminWalletUserId)
                ->sum('amount');

            $summary['all_time_wallet_in'] = (float) DB::table('wallet_transactions')
                ->where('wallet_id', $adminWalletUserId)
                ->where('amount', '>', 0)
                ->sum('amount');

            $summary['all_time_wallet_out'] = abs((float) DB::table('wallet_transactions')
                ->where('wallet_id', $adminWalletUserId)
                ->where('amount', '<', 0)
                ->sum('amount'));

            $summary['all_time_net_wallet_flow'] = $summary['all_time_wallet_in'] - $summary['all_time_wallet_out'];
            $periodRangeLabel = Carbon::parse($fromDate)->format('d M Y') . ' - ' . Carbon::now()->format('d M Y');

            $periodWalletBase = DB::table('wallet_transactions')
                ->where('wallet_id', $adminWalletUserId)
                ->where('created_at', '>=', $fromDate);

            $summary['total_wallet_in'] = (float) (clone $periodWalletBase)
                ->where('amount', '>', 0)
                ->sum('amount');
            $summary['total_wallet_out'] = abs((float) (clone $periodWalletBase)
                ->where('amount', '<', 0)
                ->sum('amount'));
            $summary['net_wallet_flow'] = $summary['total_wallet_in'] - $summary['total_wallet_out'];
            $summary['gross_profit'] = $summary['total_wallet_in'];
            $summary['transaction_in_count'] = (int) (clone $periodWalletBase)
                ->where('amount', '>', 0)
                ->count();
            $summary['transaction_out_count'] = (int) (clone $periodWalletBase)
                ->where('amount', '<', 0)
                ->count();

            $comparisonSummary['transaksi_masuk'] = (float) $summary['total_wallet_in'];
            $comparisonSummary['transaksi_keluar'] = (float) $summary['total_wallet_out'];
            $comparisonSummary['transaksi_masuk_count'] = (int) $summary['transaction_in_count'];
            $comparisonSummary['transaksi_keluar_count'] = (int) $summary['transaction_out_count'];
            $comparisonSummary['laba_kotor'] = (float) $summary['gross_profit'];
            $comparisonSummary['laba_bersih'] = (float) $summary['net_wallet_flow'];

            $incomeSeries = (clone $periodWalletBase)
                ->select(DB::raw("{$bucketSql} as bucket"), DB::raw('SUM(amount) as total_masuk'))
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            $cashflowSeries = (clone $periodWalletBase)
                ->select(
                    DB::raw("{$bucketSql} as bucket"),
                    DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_in'),
                    DB::raw('ABS(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END)) as total_out'),
                    DB::raw('COUNT(CASE WHEN amount > 0 THEN 1 END) as count_in'),
                    DB::raw('COUNT(CASE WHEN amount < 0 THEN 1 END) as count_out')
                )
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            $maxNominalSeries = max(
                1.0,
                (float) $cashflowSeries->max(function ($row) {
                    $totalIn = (float) ($row->total_in ?? 0);
                    $totalOut = (float) ($row->total_out ?? 0);
                    $net = $totalIn - $totalOut;
                    return max($totalIn, $totalOut, abs($net));
                })
            );
            $maxCountSeries = max(
                1.0,
                (float) $cashflowSeries->max(function ($row) {
                    $countIn = (int) ($row->count_in ?? 0);
                    $countOut = (int) ($row->count_out ?? 0);
                    $delta = $countIn - $countOut;
                    return max($countIn, $countOut, abs($delta));
                })
            );

            $incomeChartRows = $cashflowSeries->map(function ($row) use ($maxNominalSeries, $maxCountSeries) {
                $totalIn = (float) ($row->total_in ?? 0);
                $totalOut = (float) ($row->total_out ?? 0);
                $net = $totalIn - $totalOut;
                $countIn = (int) ($row->count_in ?? 0);
                $countOut = (int) ($row->count_out ?? 0);
                $deltaCount = $countIn - $countOut;

                return [
                    'bucket' => $row->bucket,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'net' => $net,
                    'count_in' => $countIn,
                    'count_out' => $countOut,
                    'delta_count' => $deltaCount,
                    'width_in' => round(min(100, ($totalIn / $maxNominalSeries) * 100), 1),
                    'width_out' => round(min(100, ($totalOut / $maxNominalSeries) * 100), 1),
                    'width_net' => round(min(100, (abs($net) / $maxNominalSeries) * 100), 1),
                    'width_count_in' => round(min(100, ($countIn / $maxCountSeries) * 100), 1),
                    'width_count_out' => round(min(100, ($countOut / $maxCountSeries) * 100), 1),
                    'width_delta_count' => round(min(100, (abs($deltaCount) / $maxCountSeries) * 100), 1),
                ];
            });

            if ($chartMode === 'count') {
                $comparisonBars = collect([
                    ['label' => 'Transaksi Masuk', 'amount' => (float) $comparisonSummary['transaksi_masuk_count'], 'color' => 'bg-emerald-500', 'unit' => 'trx'],
                    ['label' => 'Transaksi Keluar', 'amount' => (float) $comparisonSummary['transaksi_keluar_count'], 'color' => 'bg-rose-500', 'unit' => 'trx'],
                    ['label' => 'Selisih Transaksi', 'amount' => (float) ($comparisonSummary['transaksi_masuk_count'] - $comparisonSummary['transaksi_keluar_count']), 'color' => 'bg-sky-500', 'unit' => 'trx'],
                ]);
            } else {
                $comparisonBars = collect([
                    ['label' => 'Laba Kotor', 'amount' => (float) $comparisonSummary['laba_kotor'], 'color' => 'bg-emerald-500', 'unit' => 'currency'],
                    ['label' => 'Transaksi Keluar', 'amount' => (float) $comparisonSummary['transaksi_keluar'], 'color' => 'bg-rose-500', 'unit' => 'currency'],
                    ['label' => 'Laba Bersih', 'amount' => (float) $comparisonSummary['laba_bersih'], 'color' => 'bg-indigo-500', 'unit' => 'currency'],
                ]);
            }

            $maxComparison = max(1.0, (float) $comparisonBars->max(fn ($item) => abs((float) ($item['amount'] ?? 0))));
            $comparisonBars = $comparisonBars->map(function (array $item) use ($maxComparison) {
                $item['width_percent'] = round(min(100, (abs((float) ($item['amount'] ?? 0)) / $maxComparison) * 100), 1);
                return $item;
            });
        }

        if (Schema::hasTable('withdraw_requests')) {
            $summary['pending_withdraw_amount'] = (float) DB::table('withdraw_requests')
                ->whereIn('status', ['pending', 'approved'])
                ->sum('amount');

            $summary['pending_withdraw_count'] = DB::table('withdraw_requests')
                ->whereIn('status', ['pending', 'approved'])
                ->count();

            $summary['period_pending_withdraw_amount'] = (float) DB::table('withdraw_requests')
                ->whereIn('status', ['pending', 'approved'])
                ->where('created_at', '>=', $fromDate)
                ->sum('amount');

            $summary['period_pending_withdraw_count'] = (int) DB::table('withdraw_requests')
                ->whereIn('status', ['pending', 'approved'])
                ->where('created_at', '>=', $fromDate)
                ->count();

            if (Schema::hasTable('users')) {
                $hasConsumerProfiles = Schema::hasTable('consumer_profiles');
                $hasPaidAuditColumns = Schema::hasColumn('withdraw_requests', 'paid_by')
                    && Schema::hasColumn('withdraw_requests', 'paid_at');
                $effectiveRoleSql = $this->effectiveWithdrawRoleSql($hasConsumerProfiles);

                $roleWithdrawBase = DB::table('withdraw_requests')
                    ->join('users', 'users.id', '=', 'withdraw_requests.user_id');
                if ($hasConsumerProfiles) {
                    $roleWithdrawBase->leftJoin('consumer_profiles', 'consumer_profiles.user_id', '=', 'users.id');
                }

                $roleWithdrawSummary = (clone $roleWithdrawBase)
                    ->whereIn('withdraw_requests.status', ['pending', 'approved'])
                    ->select(
                        DB::raw("{$effectiveRoleSql} as effective_role"),
                        DB::raw('COUNT(withdraw_requests.id) as total_request'),
                        DB::raw('SUM(withdraw_requests.amount) as total_nominal')
                    )
                    ->groupBy(DB::raw($effectiveRoleSql))
                    ->get()
                    ->map(function ($row) {
                        $normalized = User::normalizeRoleValue((string) ($row->effective_role ?? 'lainnya'));

                        return [
                            'role' => $normalized,
                            'label' => $this->withdrawRoleLabel($normalized),
                            'total_request' => (int) $row->total_request,
                            'total_nominal' => (float) $row->total_nominal,
                        ];
                    })
                    ->filter(fn ($row) => $row['role'] !== 'lainnya')
                    ->values();

                $withdrawQuery = DB::table('withdraw_requests')
                    ->join('users', 'users.id', '=', 'withdraw_requests.user_id')
                    ->leftJoin('users as admin', 'admin.id', '=', 'withdraw_requests.processed_by')
                    ->when($hasConsumerProfiles, function ($query) {
                        $query->leftJoin('consumer_profiles', 'consumer_profiles.user_id', '=', 'users.id');
                    })
                    ->when($hasPaidAuditColumns, function ($query) {
                        $query->leftJoin('users as paid_admin', 'paid_admin.id', '=', 'withdraw_requests.paid_by');
                    })
                    ->select(
                        'withdraw_requests.id',
                        'withdraw_requests.amount',
                        'withdraw_requests.bank_name',
                        'withdraw_requests.account_number',
                        'withdraw_requests.account_holder',
                        'withdraw_requests.status',
                        'withdraw_requests.processed_at',
                        DB::raw($hasPaidAuditColumns ? 'withdraw_requests.paid_at' : 'null as paid_at'),
                        'users.id as user_id',
                        'users.name as user_name',
                        'users.email as user_email',
                        DB::raw("{$effectiveRoleSql} as user_role"),
                        'admin.name as processed_by_name',
                        DB::raw($hasPaidAuditColumns ? 'paid_admin.name as paid_by_name' : 'null as paid_by_name')
                    )
                    ->orderByDesc('withdraw_requests.id');

                if (in_array($withdrawStatus, ['pending', 'approved', 'paid', 'rejected', 'cancelled'], true)) {
                    $withdrawQuery->where('withdraw_requests.status', $withdrawStatus);
                }

                if ($activeRole !== '') {
                    $withdrawQuery->whereRaw("{$effectiveRoleSql} = ?", [User::normalizeRoleValue($activeRole)]);
                }

                if ($keyword !== '') {
                    $withdrawQuery->where(function ($sub) use ($keyword) {
                        $sub->where('users.name', 'like', "%{$keyword}%")
                            ->orWhere('users.email', 'like', "%{$keyword}%")
                            ->orWhere('withdraw_requests.id', 'like', "%{$keyword}%");
                    });
                }

                $withdrawRows = $withdrawQuery->paginate(15)->withQueryString();
            }
        }

        if (
            $normalizedSection === 'overview'
            && (in_array($withdrawStatus, ['pending', 'approved', 'paid', 'rejected', 'cancelled'], true) || $activeRole !== '' || $keyword !== '')
        ) {
            $normalizedSection = 'withdraw';
        }

        if (Schema::hasTable('orders') && Schema::hasTable('users')) {
            $ordersBase = DB::table('orders')
                ->whereIn('payment_method', $allowedTransferMethods);

            $transferSummary['waiting_verification'] = (clone $ordersBase)
                ->where('payment_status', 'unpaid')
                ->where('order_status', 'pending_payment')
                ->whereNotNull('payment_proof_url')
                ->count();
            $transferSummary['verified'] = (clone $ordersBase)
                ->where('payment_status', 'paid')
                ->whereIn('order_status', ['paid', 'packed', 'shipped', 'completed'])
                ->count();
            $transferSummary['with_proof'] = (clone $ordersBase)->whereNotNull('payment_proof_url')->count();
            $transferSummary['without_proof'] = (clone $ordersBase)->whereNull('payment_proof_url')->count();

            $transferQuery = DB::table('orders')
                ->join('users as buyer', 'buyer.id', '=', 'orders.buyer_id')
                ->join('users as seller', 'seller.id', '=', 'orders.seller_id')
                ->whereIn('orders.payment_method', $allowedTransferMethods)
                ->select(
                    'orders.id',
                    'orders.total_amount',
                    'orders.payment_method',
                    'orders.paid_amount',
                    'orders.payment_status',
                    'orders.order_status',
                    'orders.payment_proof_url',
                    'orders.payment_submitted_at',
                    'orders.created_at',
                    'buyer.name as buyer_name',
                    'buyer.email as buyer_email',
                    'seller.name as seller_name',
                    'seller.email as seller_email'
                )
                ->orderByDesc('orders.id');

            if ($transferState === 'waiting') {
                $transferQuery
                    ->where('orders.payment_status', 'unpaid')
                    ->where('orders.order_status', 'pending_payment')
                    ->whereNotNull('orders.payment_proof_url');
            } elseif ($transferState === 'verified') {
                $transferQuery
                    ->where('orders.payment_status', 'paid')
                    ->whereIn('orders.order_status', ['paid', 'packed', 'shipped', 'completed']);
            } elseif ($transferState === 'no_proof') {
                $transferQuery
                    ->where('orders.payment_status', 'unpaid')
                    ->whereNull('orders.payment_proof_url');
            }

            if ($transferKeyword !== '') {
                $transferQuery->where(function ($sub) use ($transferKeyword) {
                    $sub->where('orders.id', 'like', "%{$transferKeyword}%")
                        ->orWhere('buyer.name', 'like', "%{$transferKeyword}%")
                        ->orWhere('buyer.email', 'like', "%{$transferKeyword}%")
                        ->orWhere('seller.name', 'like', "%{$transferKeyword}%")
                        ->orWhere('seller.email', 'like', "%{$transferKeyword}%");
                });
            }

            if ($transferMethod !== '' && in_array($transferMethod, $allowedTransferMethods, true)) {
                $transferQuery->where('orders.payment_method', $transferMethod);
            }

            $transferRows = $transferQuery->paginate(15)->withQueryString();
        }

        if (
            $normalizedSection === 'overview'
            && (in_array($transferState, ['waiting', 'verified', 'no_proof'], true) || $transferKeyword !== '' || $transferMethod !== '')
        ) {
            $normalizedSection = 'transfer';
        }

        if (Schema::hasTable('store_products')) {
            $productsBase = DB::table('store_products');
            $affiliateCommissionSummary['total_products'] = (int) (clone $productsBase)->count();
            $affiliateCommissionSummary['affiliate_enabled_products'] = (int) (clone $productsBase)
                ->where('is_affiliate_enabled', true)
                ->count();
            $affiliateCommissionSummary['min_commission_percent'] = (float) ((clone $productsBase)
                ->where('is_affiliate_enabled', true)
                ->min('affiliate_commission') ?? 0);
            $affiliateCommissionSummary['max_commission_percent'] = (float) ((clone $productsBase)
                ->where('is_affiliate_enabled', true)
                ->max('affiliate_commission') ?? 0);
            $affiliateCommissionSummary['avg_commission_percent'] = (float) ((clone $productsBase)
                ->where('is_affiliate_enabled', true)
                ->avg('affiliate_commission') ?? 0);
            $affiliateCommissionSummary['out_of_range_products'] = (int) ((clone $productsBase)
                ->where('is_affiliate_enabled', true)
                ->where(function ($query) use ($affiliateCommissionRange) {
                    $query->where('affiliate_commission', '<', (float) ($affiliateCommissionRange['min'] ?? 0))
                        ->orWhere('affiliate_commission', '>', (float) ($affiliateCommissionRange['max'] ?? 100));
                })
                ->count());

            $productsQuery = DB::table('store_products')
                ->leftJoin('users as mitra', 'mitra.id', '=', 'store_products.mitra_id')
                ->select(
                    'store_products.id',
                    'store_products.name',
                    'store_products.price',
                    'store_products.stock_qty',
                    'store_products.is_affiliate_enabled',
                    'store_products.affiliate_commission',
                    'mitra.name as mitra_name'
                )
                ->orderByDesc('store_products.updated_at')
                ->limit(30);

            if (Schema::hasColumn('store_products', 'is_active')) {
                $productsQuery->addSelect('store_products.is_active');
            }

            $affiliateCommissionRows = $productsQuery->get();
        }

        return view('admin.finance', [
            'summary' => $summary,
            'incomeSeries' => $incomeSeries,
            'incomeChartRows' => $incomeChartRows,
            'comparisonSummary' => $comparisonSummary,
            'comparisonBars' => $comparisonBars,
            'periodMeta' => [
                'period' => $period,
                'window' => $window,
                'label' => $periodLabel,
                'window_options' => $windowOptions,
                'range_label' => $periodRangeLabel,
                'chart_mode' => $chartMode,
            ],
            'transferSummary' => $transferSummary,
            'affiliateCommissionRange' => $affiliateCommissionRange,
            'affiliateCommissionSummary' => $affiliateCommissionSummary,
            'affiliateCommissionRows' => $affiliateCommissionRows,
            'roleWithdrawSummary' => $roleWithdrawSummary,
            'withdrawRows' => $withdrawRows,
            'transferRows' => $transferRows,
            'filters' => [
                'period' => $period,
                'window' => $window,
                'chart_mode' => $chartMode,
                'section' => $normalizedSection,
                'withdraw_status' => $withdrawStatus,
                'q' => $keyword,
                'role' => $activeRole,
                'transfer_state' => $transferState,
                'transfer_q' => $transferKeyword,
                'transfer_method' => $transferMethod,
            ],
            'transferMethodOptions' => collect($paymentMethodMap)
                ->map(fn (string $label, string $method) => ['method' => $method, 'label' => $label])
                ->values(),
            'paymentMethodMap' => $paymentMethodMap,
            'currency' => static fn ($amount) => 'Rp' . number_format((float) $amount, 0, ',', '.'),
            'lastUpdated' => Carbon::now(),
        ]);
    }

    /**
     * @return array{
     *   period:string,
     *   window:int,
     *   from:Carbon,
     *   bucket_sql:string,
     *   label:string,
     *   window_options:array<int, int>
     * }
     */
    private function resolvePeriodConfig(string $period, int $window): array
    {
        $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'daily';

        $windowOptions = match ($period) {
            'weekly' => [4, 8, 12, 24],
            'monthly' => [3, 6, 12, 24],
            default => [7, 14, 30, 60],
        };

        if (! in_array($window, $windowOptions, true)) {
            $window = $windowOptions[1] ?? $windowOptions[0];
        }

        $from = match ($period) {
            'weekly' => now()->subWeeks(max(0, $window - 1))->startOfWeek(),
            'monthly' => now()->subMonths(max(0, $window - 1))->startOfMonth(),
            default => now()->subDays(max(0, $window - 1))->startOfDay(),
        };

        $bucketSql = match ($period) {
            'weekly' => "TO_CHAR(created_at, 'IYYY-IW')",
            'monthly' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => "TO_CHAR(created_at, 'YYYY-MM-DD')",
        };

        $label = match ($period) {
            'weekly' => "{$window} Minggu",
            'monthly' => "{$window} Bulan",
            default => "{$window} Hari",
        };

        return [
            'period' => $period,
            'window' => $window,
            'from' => $from,
            'bucket_sql' => $bucketSql,
            'label' => $label,
            'window_options' => $windowOptions,
        ];
    }

    private function effectiveWithdrawRoleSql(bool $hasConsumerProfiles): string
    {
        if (! $hasConsumerProfiles) {
            return "CASE
                WHEN LOWER(TRIM(users.role)) = 'mitra' THEN 'mitra'
                WHEN LOWER(TRIM(users.role)) = 'farmer_seller' THEN 'farmer_seller'
                ELSE 'lainnya'
            END";
        }

        return "CASE
            WHEN LOWER(TRIM(users.role)) = 'mitra' THEN 'mitra'
            WHEN LOWER(TRIM(users.role)) = 'consumer'
                AND consumer_profiles.mode_status = 'approved'
                AND consumer_profiles.mode = 'affiliate' THEN 'affiliate'
            WHEN LOWER(TRIM(users.role)) = 'consumer'
                AND consumer_profiles.mode_status = 'approved'
                AND consumer_profiles.mode = 'farmer_seller' THEN 'farmer_seller'
            WHEN LOWER(TRIM(users.role)) = 'farmer_seller' THEN 'farmer_seller'
            ELSE 'lainnya'
        END";
    }

    private function withdrawRoleLabel(string $normalizedRole): string
    {
        return match ($normalizedRole) {
            'mitra' => 'Mitra',
            'affiliate' => 'Affiliate',
            'farmer_seller' => 'Penjual',
            default => 'Lainnya',
        };
    }
}
