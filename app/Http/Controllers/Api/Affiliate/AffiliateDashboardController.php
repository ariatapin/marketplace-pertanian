<?php

namespace App\Http\Controllers\Api\Affiliate;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AffiliateReferralTrackingService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AffiliateDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WalletService $wallet,
        protected AffiliateReferralTrackingService $affiliateTracking
    ) {}

    public function summary(Request $request)
    {
        $uid = $request->user()->id;

        return $this->apiSuccess([
            'balance' => $this->wallet->getBalance($uid),
            'tracking' => $this->affiliateTracking->summaryForAffiliate((int) $uid),
        ], 'Ringkasan affiliate berhasil diambil.');
    }

    public function commissions(Request $request)
    {
        $uid = $request->user()->id;
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = DB::table('wallet_transactions')
            ->join('orders', 'orders.id', '=', 'wallet_transactions.reference_order_id')
            ->where('wallet_id', $uid)
            ->where('transaction_type', 'affiliate_commission')
            ->where('orders.order_source', 'store_online')
            ->where('orders.order_status', 'completed')
            ->where('orders.payment_status', 'paid');

        $total = (int) (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $rows = (clone $query)
            ->orderByDesc('wallet_transactions.id')
            ->forPage($page, $perPage)
            ->get([
                'wallet_transactions.id',
                'wallet_transactions.amount',
                'wallet_transactions.reference_order_id',
                'wallet_transactions.description',
                'wallet_transactions.created_at',
            ]);

        return $this->apiSuccess([
            'items' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ], 'Data komisi affiliate berhasil diambil.');
    }

    public function withdraw(Request $request)
    {
        return app(\App\Http\Controllers\WithdrawController::class)->requestWithdraw($request);
    }
}
