<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Recommendation\RuleBasedRecommendationService;
use App\Support\BehaviorRecommendationNotification;
use App\Services\Location\LocationResolver;
use App\Services\UserRatingService;
use App\Services\Weather\WeatherService;
use App\Services\Weather\WeatherAlertEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SellerDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LocationResolver $location,
        protected WeatherService $weather,
        protected WeatherAlertEngine $alertEngine,
        protected RuleBasedRecommendationService $recommendationService,
        protected UserRatingService $userRatings
    ) {}

    public function summary(Request $request)
    {
        $user = $request->user();

        try {
            // CATATAN-AUDIT: API seller juga memicu sinkronisasi rekomendasi agar data unread konsisten.
            $this->recommendationService->syncForUser($user);
        } catch (\Throwable $e) {
            // Silent fallback: ringkasan dashboard tetap tersedia walau sinkron gagal.
        }

        $loc = $this->location->forUser($user);
        $productCounts = [
            'total' => 0,
            'total_stock' => 0,
        ];

        if (Schema::hasTable('farmer_harvests')) {
            $products = DB::table('farmer_harvests')
                ->where('farmer_id', $user->id);

            $productCounts['total'] = (int) (clone $products)->count();
            $productCounts['total_stock'] = (int) (clone $products)->sum('stock_qty');
        }

        $current = $this->weather->current($loc['type'], $loc['id'], $loc['lat'], $loc['lng']);
        $forecast = $this->weather->forecast($loc['type'], $loc['id'], $loc['lat'], $loc['lng']);
        $alert = $this->alertEngine->evaluateForecast($forecast);
        $recommendationUnreadCount = (int) $user->notifications()
            ->where('type', BehaviorRecommendationNotification::class)
            ->where('data', 'like', '%"role_target":"seller"%')
            ->whereNull('read_at')
            ->count();

        return $this->apiSuccess([
            'counts' => [
                'paid' => DB::table('orders')->where('seller_id', $user->id)->where('order_source', 'farmer_p2p')->where('order_status', 'paid')->count(),
                'packed' => DB::table('orders')->where('seller_id', $user->id)->where('order_source', 'farmer_p2p')->where('order_status', 'packed')->count(),
                'shipped' => DB::table('orders')->where('seller_id', $user->id)->where('order_source', 'farmer_p2p')->where('order_status', 'shipped')->count(),
            ],
            'products' => $productCounts,
            'rating' => $this->userRatings->summaryForUser((int) $user->id),
            'recommendation_unread_count' => $recommendationUnreadCount,
            'weather' => [
                'location' => $loc['label'],
                'lat' => $loc['lat'],
                'lng' => $loc['lng'],
                'current' => $current,
                'alert' => $alert,
            ],
        ], 'Ringkasan dashboard seller berhasil diambil.');
    }

    public function orders(Request $request)
    {
        $sellerId = $request->user()->id;

        $rows = DB::table('orders')
            ->where('seller_id', $sellerId)
            ->where('order_source', 'farmer_p2p')
            ->orderByDesc('id')
            ->get();

        return $this->apiSuccess($rows, 'Daftar order seller berhasil diambil.');
    }

    public function markPacked(Request $request, int $orderId)
    {
        return app(\App\Http\Controllers\SellerOrderStatusController::class)->markPacked($request, $orderId);
    }

    public function markShipped(Request $request, int $orderId)
    {
        return app(\App\Http\Controllers\SellerOrderStatusController::class)->markShipped($request, $orderId);
    }

    public function confirmCash(Request $request, int $orderId)
    {
        return app(\App\Http\Controllers\P2PSellerPaymentController::class)->confirmCash($request, $orderId);
    }
}
