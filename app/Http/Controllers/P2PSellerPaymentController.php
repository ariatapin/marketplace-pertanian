<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Support\OrderStatusHistoryLogger;
use App\Support\OrderStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class P2PSellerPaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrderStatusTransition $statusTransition,
        protected OrderStatusHistoryLogger $statusHistoryLogger
    ) {}

    public function confirmCash(Request $request, int $orderId)
    {
        $seller = $request->user();

        return DB::transaction(function () use ($seller, $orderId) {

            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);
            }

            // Validasi: hanya seller untuk order ini
            Gate::forUser($seller)->authorize('order-belongs-to-seller', $order);

            // Validasi: hanya P2P
            if ($order->order_source !== 'farmer_p2p') {
                abort(403, 'Konfirmasi COD hanya untuk order P2P petani.');
            }

            // Validasi: hanya jika unpaid
            if ($order->payment_status !== 'unpaid') {
                throw ValidationException::withMessages(['payment_status' => 'Pembayaran sudah diproses.']);
            }

            $this->statusTransition->assertTransition((string) $order->order_status, 'paid');
            $fromStatus = (string) $order->order_status;

            // COD: seller menandai cash diterima
            DB::table('orders')->where('id', $orderId)->update([
                'payment_status' => 'paid',
                'order_status' => 'paid',
                'updated_at' => now(),
            ]);

            $this->statusHistoryLogger->log(
                orderId: $orderId,
                fromStatus: $fromStatus,
                toStatus: 'paid',
                actorUserId: (int) $seller->id,
                actorRole: (string) $seller->role,
                note: 'Seller mengonfirmasi pembayaran COD.'
            );

            if ($request->expectsJson()) {
                return $this->apiSuccess([
                    'order_id' => $orderId,
                    'payment_status' => 'paid',
                    'order_status' => 'paid',
                ], 'Pembayaran COD berhasil dikonfirmasi.');
            }

            return back()->with('status', 'COD dikonfirmasi. Status pembayaran: PAID.');
        });
    }
}
