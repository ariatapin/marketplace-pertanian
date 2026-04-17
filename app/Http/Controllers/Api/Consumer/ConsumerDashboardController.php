<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ConsumerModeService;
use App\Services\OrderShipmentService;
use App\Services\OrderTransferPaymentService;
use App\Services\SettlementService;
use App\Services\Location\LocationResolver;
use App\Services\Weather\WeatherService;
use App\Services\Weather\WeatherAlertEngine;
use App\Support\OrderStatusHistoryLogger;
use App\Support\OrderStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConsumerDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ConsumerModeService $consumerMode,
        protected SettlementService $settlement,
        protected OrderShipmentService $shipments,
        protected OrderTransferPaymentService $transferPayment,
        protected LocationResolver $location,
        protected WeatherService $weather,
        protected WeatherAlertEngine $alertEngine,
        protected OrderStatusTransition $statusTransition,
        protected OrderStatusHistoryLogger $statusHistoryLogger
    ) {}

    public function summary(Request $request)
    {
        $user = $request->user();
        $loc = $this->location->forUser($user);

        $current = $this->weather->current($loc['type'], $loc['id'], $loc['lat'], $loc['lng']);
        $forecast = $this->weather->forecast($loc['type'], $loc['id'], $loc['lat'], $loc['lng']);
        $alert = $this->alertEngine->evaluateForecast($forecast);

        $counts = [
            'pending_payment' => DB::table('orders')
                ->where('buyer_id', $user->id)
                ->where('order_status', 'pending_payment')
                ->count(),
            'shipped' => DB::table('orders')
                ->where('buyer_id', $user->id)
                ->where('order_status', 'shipped')
                ->count(),
            'completed' => DB::table('orders')
                ->where('buyer_id', $user->id)
                ->where('order_status', 'completed')
                ->count(),
        ];

        return $this->apiSuccess([
            'counts' => $counts,
            'weather' => [
                'location' => $loc['label'],
                'lat' => $loc['lat'],
                'lng' => $loc['lng'],
                'current' => $current,
                'alert' => $alert,
            ],
        ], 'Ringkasan dashboard consumer berhasil diambil.');
    }

    public function orders(Request $request)
    {
        $user = $request->user();

        $data = DB::table('orders')
            ->where('buyer_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return $this->apiSuccess($data, 'Daftar order consumer berhasil diambil.');
    }

    public function requestAffiliate(Request $request)
    {
        $user = $request->user();
        $result = $this->consumerMode->requestAffiliate($user);

        return $this->apiSuccess($result, 'Pengajuan mode affiliate berhasil dikirim.');
    }

    public function requestFarmerSeller(Request $request)
    {
        $user = $request->user();
        $result = $this->consumerMode->requestFarmerSeller($user);

        return $this->apiSuccess($result, 'Pengajuan mode farmer seller berhasil dikirim.');
    }

    public function uploadP2PProof(Request $request, int $orderId)
    {
        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:4096'],
            'paid_amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $payload = $this->transferPayment->submit(
            $request->user(),
            $orderId,
            $request->file('proof'),
            (float) $request->input('paid_amount'),
            $request->string('payment_method')->toString()
        );

        return $this->apiSuccess($payload, 'Bukti pembayaran berhasil disimpan. Menunggu verifikasi seller.');
    }

    public function confirmReceived(Request $request, int $orderId)
    {
        $buyer = $request->user();

        return DB::transaction(function () use ($buyer, $orderId) {

            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);

            if ((int)$order->buyer_id !== (int)$buyer->id) abort(403, 'Bukan buyer order ini.');

            $this->statusTransition->assertTransition((string) $order->order_status, 'completed');
            $fromStatus = (string) $order->order_status;

            $payload = [
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('orders', 'completed_at')) {
                $payload['completed_at'] = now();
            }

            DB::table('orders')->where('id', $orderId)->update($payload);
            $this->shipments->markDelivered($orderId);

            $this->statusHistoryLogger->log(
                orderId: $orderId,
                fromStatus: $fromStatus,
                toStatus: 'completed',
                actorUserId: (int) $buyer->id,
                actorRole: (string) $buyer->role,
                note: 'Buyer mengonfirmasi pesanan diterima.'
            );

            // settlement hanya akan jalan untuk non-P2P, sesuai service kamu
            $this->settlement->settleIfEligible($orderId);

            return $this->apiSuccess([
                'order_id' => $orderId,
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
            ], 'Order berhasil dikonfirmasi diterima.');
        });
    }
}
