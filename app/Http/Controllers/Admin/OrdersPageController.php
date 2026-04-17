<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrdersPageController extends Controller
{
    public function __invoke(Request $request)
    {
        $affiliateSource = $request->string('affiliate_source')->toString();
        $keyword = trim($request->string('q')->toString());

        $rows = collect();
        $summary = [
            'total_completed_orders' => 0,
            'unique_customers' => 0,
            'completed_affiliate_orders' => 0,
            'completed_non_affiliate_orders' => 0,
        ];

        if (Schema::hasTable('orders') && Schema::hasTable('users')) {
            $affiliateFlags = Schema::hasTable('order_items')
                ? DB::table('order_items')
                    ->select('order_id', DB::raw('MAX(CASE WHEN affiliate_id IS NOT NULL THEN 1 ELSE 0 END) as has_affiliate'))
                    ->groupBy('order_id')
                : DB::table('orders')
                    ->select('id as order_id', DB::raw('0 as has_affiliate'));

            $baseQuery = DB::table('orders')
                ->join('users as buyer', 'buyer.id', '=', 'orders.buyer_id')
                ->join('users as seller', 'seller.id', '=', 'orders.seller_id')
                ->leftJoinSub($affiliateFlags, 'affiliate_flags', function ($join) {
                    $join->on('affiliate_flags.order_id', '=', 'orders.id');
                })
                ->where('orders.order_status', 'completed')
                ->where('seller.role', 'mitra');

            if ($affiliateSource === 'affiliate') {
                $baseQuery->whereRaw('COALESCE(affiliate_flags.has_affiliate, 0) = 1');
            } elseif ($affiliateSource === 'non_affiliate') {
                $baseQuery->whereRaw('COALESCE(affiliate_flags.has_affiliate, 0) = 0');
            }

            if ($keyword !== '') {
                $baseQuery->where(function ($sub) use ($keyword) {
                    $sub->where('buyer.name', 'like', "%{$keyword}%")
                        ->orWhere('buyer.email', 'like', "%{$keyword}%");

                    if (is_numeric($keyword)) {
                        $sub->orWhere('buyer.id', (int) $keyword);
                    }
                });
            }

            $summary['total_completed_orders'] = (int) (clone $baseQuery)->count('orders.id');
            $summary['unique_customers'] = (int) (clone $baseQuery)->distinct('buyer.id')->count('buyer.id');
            $summary['completed_affiliate_orders'] = (int) (clone $baseQuery)
                ->whereRaw('COALESCE(affiliate_flags.has_affiliate, 0) = 1')
                ->count('orders.id');
            $summary['completed_non_affiliate_orders'] = (int) (clone $baseQuery)
                ->whereRaw('COALESCE(affiliate_flags.has_affiliate, 0) = 0')
                ->count('orders.id');

            $rows = (clone $baseQuery)
                ->select(
                    'buyer.id as buyer_id',
                    'buyer.name as buyer_name',
                    'buyer.email as buyer_email',
                    DB::raw('COUNT(orders.id) as total_completed_orders'),
                    DB::raw('SUM(CASE WHEN COALESCE(affiliate_flags.has_affiliate, 0) = 1 THEN 1 ELSE 0 END) as affiliate_completed_orders'),
                    DB::raw('SUM(CASE WHEN COALESCE(affiliate_flags.has_affiliate, 0) = 0 THEN 1 ELSE 0 END) as non_affiliate_completed_orders'),
                    DB::raw('SUM(orders.total_amount) as total_completed_amount'),
                    DB::raw('MAX(orders.created_at) as last_completed_at')
                )
                ->groupBy('buyer.id', 'buyer.name', 'buyer.email')
                ->orderByDesc(DB::raw('MAX(orders.created_at)'))
                ->paginate(20)
                ->withQueryString();

            $rows->setCollection(
                $rows->getCollection()->map(function ($row) {
                    return (object) [
                        'buyer_id' => (int) ($row->buyer_id ?? 0),
                        'buyer_name_label' => (string) (($row->buyer_name ?? '') !== '' ? $row->buyer_name : '-'),
                        'buyer_email_label' => (string) (($row->buyer_email ?? '') !== '' ? $row->buyer_email : '-'),
                        'total_completed_orders_label' => number_format((int) ($row->total_completed_orders ?? 0)),
                        'affiliate_completed_orders_label' => number_format((int) ($row->affiliate_completed_orders ?? 0)),
                        'non_affiliate_completed_orders_label' => number_format((int) ($row->non_affiliate_completed_orders ?? 0)),
                        'total_completed_amount_label' => 'Rp' . number_format((float) ($row->total_completed_amount ?? 0), 0, ',', '.'),
                        'last_completed_at_label' => !empty($row->last_completed_at)
                            ? date('d M Y H:i', strtotime((string) $row->last_completed_at))
                            : '-',
                    ];
                })
            );
        }

        return view('admin.orders', [
            'rows' => $rows,
            'filters' => [
                'affiliate_source' => $affiliateSource,
                'q' => $keyword,
            ],
            'summary' => $summary,
        ]);
    }
}
