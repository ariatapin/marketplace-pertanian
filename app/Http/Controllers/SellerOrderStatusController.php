<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\OrderShipmentService;
use App\Support\OrderStatusHistoryLogger;
use App\Support\OrderStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerOrderStatusController extends Controller
{
    use ApiResponse;

    private const SELLER_ORDER_SOURCE = 'farmer_p2p';

    public function __construct(
        protected OrderStatusTransition $statusTransition,
        protected OrderStatusHistoryLogger $statusHistoryLogger,
        protected OrderShipmentService $shipments
    ) {}

    public function markPacked(Request $request, int $orderId)
    {
        $seller = $request->user();

        return DB::transaction(function () use ($request, $seller, $orderId) {

            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);

            // seller hanya bisa ubah order miliknya
            Gate::forUser($seller)->authorize('order-belongs-to-seller', $order);
            $this->assertSellerOrderSource($order);

            $this->statusTransition->assertTransition((string) $order->order_status, 'packed');
            $fromStatus = (string) $order->order_status;

            DB::table('orders')->where('id', $orderId)->update([
                'order_status' => 'packed',
                'updated_at' => now(),
            ]);
            $this->shipments->ensurePending($orderId);

            $this->statusHistoryLogger->log(
                orderId: $orderId,
                fromStatus: $fromStatus,
                toStatus: 'packed',
                actorUserId: (int) $seller->id,
                actorRole: (string) $seller->role,
                note: 'Seller mengubah status order ke packed.'
            );

            if ($request->expectsJson()) {
                return $this->apiSuccess([
                    'order_id' => $orderId,
                    'order_status' => 'packed',
                ], 'Order berhasil diubah ke packed.');
            }

            return back()->with('status', "Order #{$orderId} berhasil diubah ke PACKED.");
        });
    }

    public function markShipped(Request $request, int $orderId)
    {
        $seller = $request->user();

        $request->validate([
            'resi_number' => 'nullable|string|max:120',
        ]);

        return DB::transaction(function () use ($request, $seller, $orderId) {

            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);

            Gate::forUser($seller)->authorize('order-belongs-to-seller', $order);
            $this->assertSellerOrderSource($order);

            $this->statusTransition->assertTransition((string) $order->order_status, 'shipped');
            $fromStatus = (string) $order->order_status;

            DB::table('orders')->where('id', $orderId)->update([
                'order_status' => 'shipped',
                'shipping_status' => 'shipped',
                'resi_number' => $request->resi_number ?? $order->resi_number,
                'updated_at' => now(),
            ]);
            $this->shipments->markShipped(
                orderId: $orderId,
                trackingNumber: (string) ($request->resi_number ?? $order->resi_number ?? '')
            );

            $this->statusHistoryLogger->log(
                orderId: $orderId,
                fromStatus: $fromStatus,
                toStatus: 'shipped',
                actorUserId: (int) $seller->id,
                actorRole: (string) $seller->role,
                note: 'Seller mengubah status order ke shipped.',
                meta: [
                    'resi_number' => $request->resi_number ?? $order->resi_number,
                ]
            );

            if ($request->expectsJson()) {
                return $this->apiSuccess([
                    'order_id' => $orderId,
                    'order_status' => 'shipped',
                    'shipping_status' => 'shipped',
                    'resi_number' => $request->resi_number ?? $order->resi_number,
                ], 'Order berhasil diubah ke shipped.');
            }

            return back()->with('status', "Order #{$orderId} berhasil diubah ke SHIPPED.");
        });
    }

    private function assertSellerOrderSource(object $order): void
    {
        if ((string) ($order->order_source ?? '') !== self::SELLER_ORDER_SOURCE) {
            abort(403, 'Aksi penjual hanya untuk order P2P hasil tani.');
        }
    }
}
