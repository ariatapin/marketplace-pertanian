<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\OrderShipmentService;
use App\Services\SettlementService;
use App\Support\OrderStatusHistoryLogger;
use App\Support\OrderStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BuyerOrderStatusController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SettlementService $settlement,
        protected OrderShipmentService $shipments,
        protected OrderStatusTransition $statusTransition,
        protected OrderStatusHistoryLogger $statusHistoryLogger
    ) {}

    public function confirmReceived(Request $request, int $orderId)
    {
        $buyer = $request->user();

        return DB::transaction(function () use ($buyer, $orderId) {

            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);

            Gate::forUser($buyer)->authorize('order-belongs-to-buyer', $order);

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
            
            $this->settlement->settleIfEligible($orderId);

            return $this->apiSuccess([
                'order_id' => $orderId,
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
            ], 'Order berhasil dikonfirmasi diterima.');
        });
    }
}
