<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Location\LocationResolver;
use App\Services\Weather\WeatherService;
use App\Services\Weather\WeatherAlertEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LocationResolver $location,
        protected WeatherService $weather,
        protected WeatherAlertEngine $alertEngine
    ) {}

    public function summary(Request $request)
    {
        $user = $request->user();

        $loc = $this->location->forUser($user);

        $current = $this->weather->current($loc['type'], $loc['id'], $loc['lat'], $loc['lng']);
        $forecast = $this->weather->forecast($loc['type'], $loc['id'], $loc['lat'], $loc['lng']);
        $alert = $this->alertEngine->evaluateForecast($forecast);

        return $this->apiSuccess([
            'counts' => [
                'pending_mode_requests' => DB::table('consumer_profiles')->where('mode_status','pending')->count(),
                'pending_withdraws' => DB::table('withdraw_requests')->where('status','pending')->count(),
                'orders_pending_payment' => DB::table('orders')->where('payment_status','unpaid')->count(),
                'orders_shipped' => DB::table('orders')->where('order_status','shipped')->count(),
            ],

            'weather' => [
                'location' => $loc['label'],
                'lat' => $loc['lat'],
                'lng' => $loc['lng'],
                'current' => $current,
                'alert' => $alert,
            ],
        ], 'Ringkasan dashboard admin berhasil diambil.');
    }
}
