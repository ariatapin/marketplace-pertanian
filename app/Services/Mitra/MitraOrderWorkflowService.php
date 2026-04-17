<?php

namespace App\Services\Mitra;

use App\Models\User;
use App\Services\OrderShipmentService;
use App\Services\OrderTransferPaymentService;
use App\Support\OrderStatusHistoryLogger;
use App\Support\OrderStatusTransition;
use App\Support\PaymentOrderStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MitraOrderWorkflowService
{
    private const MITRA_ORDER_SOURCE = 'store_online';
    private const MITRA_PAYMENT_METHOD = 'bank_transfer';

    public function __construct(
        protected OrderTransferPaymentService $transferPayment,
        protected OrderStatusTransition $statusTransition,
        protected OrderStatusHistoryLogger $statusHistoryLogger,
        protected OrderShipmentService $shipments
    ) {}

    public function markPacked(User $mitra, int $orderId): array
    {
        return DB::transaction(function () use ($mitra, $orderId): array {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);
            }

            Gate::forUser($mitra)->authorize('order-belongs-to-seller', $order);
            $this->assertMitraOrderSource($order);

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
                actorUserId: (int) $mitra->id,
                actorRole: (string) $mitra->role,
                note: 'Mitra mengubah status order ke packed.'
            );

            return [
                'order_id' => $orderId,
                'order_status' => 'packed',
            ];
        });
    }

    public function markPaid(User $mitra, int $orderId): array
    {
        $order = $this->resolveOwnedMitraOrder($mitra, $orderId);
        $this->assertMitraTransferPayment($order);
        $this->transferPayment->verifyBySeller($mitra, $orderId);

        return $this->markPackedAfterPaymentVerified($mitra, $orderId);
    }

    public function markShipped(User $mitra, int $orderId, ?string $requestedResi = null): array
    {
        return DB::transaction(function () use ($mitra, $orderId, $requestedResi): array {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);
            }

            Gate::forUser($mitra)->authorize('order-belongs-to-seller', $order);
            $this->assertMitraOrderSource($order);

            $this->statusTransition->assertTransition((string) $order->order_status, 'shipped');
            $fromStatus = (string) $order->order_status;
            $resiNumber = trim((string) ($requestedResi ?: $order->resi_number));
            if ($resiNumber === '') {
                $resiNumber = null;
            }

            DB::table('orders')->where('id', $orderId)->update([
                'order_status' => 'shipped',
                'shipping_status' => 'shipped',
                'resi_number' => $resiNumber,
                'updated_at' => now(),
            ]);
            $this->shipments->markShipped(
                orderId: $orderId,
                trackingNumber: (string) ($resiNumber ?? '')
            );

            $this->statusHistoryLogger->log(
                orderId: $orderId,
                fromStatus: $fromStatus,
                toStatus: 'shipped',
                actorUserId: (int) $mitra->id,
                actorRole: (string) $mitra->role,
                note: 'Mitra mengubah status order ke shipped.',
                meta: [
                    'resi_number' => $resiNumber,
                ]
            );

            $this->notifyBuyerOrderShipped(
                buyerUserId: (int) ($order->buyer_id ?? 0),
                orderId: $orderId,
                resiNumber: $resiNumber
            );

            return [
                'order_id' => $orderId,
                'order_status' => 'shipped',
                'shipping_status' => 'shipped',
                'resi_number' => $resiNumber,
            ];
        });
    }

    public function detail(User $mitra, int $orderId): array
    {
        $order = DB::table('orders')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'orders.buyer_id')
            ->where('orders.id', $orderId)
            ->select(
                'orders.id',
                'orders.buyer_id',
                'orders.seller_id',
                'orders.order_source',
                'orders.total_amount',
                'orders.payment_method',
                'orders.payment_status',
                'orders.order_status',
                'orders.shipping_status',
                'orders.resi_number',
                'orders.payment_proof_url',
                'orders.paid_amount',
                'orders.payment_submitted_at',
                'orders.created_at',
                'orders.updated_at',
                'buyer.name as buyer_name',
                'buyer.email as buyer_email'
            )
            ->first();

        if (! $order) {
            throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);
        }

        Gate::forUser($mitra)->authorize('order-belongs-to-seller', $order);
        $this->assertMitraOrderSource($order);

        $items = collect();
        if (DB::getSchemaBuilder()->hasTable('order_items')) {
            $items = DB::table('order_items')
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->get(['id', 'product_id', 'product_name', 'qty', 'price_per_unit', 'commission_amount']);
        }

        $statusHistory = collect();
        if (Schema::hasTable('order_status_histories')) {
            $statusHistory = DB::table('order_status_histories')
                ->leftJoin('users as actor', 'actor.id', '=', 'order_status_histories.actor_user_id')
                ->where('order_status_histories.order_id', $orderId)
                ->orderBy('order_status_histories.id')
                ->get([
                    'order_status_histories.from_status',
                    'order_status_histories.to_status',
                    'order_status_histories.note',
                    'order_status_histories.meta',
                    'order_status_histories.created_at',
                    'order_status_histories.actor_role',
                    'actor.name as actor_name',
                ]);
        }

        return [
            'order' => $order,
            'items' => $items,
            'summary' => [
                'total_qty' => (int) $items->sum('qty'),
                'items_total_amount' => (float) $items->sum(fn ($i) => ((int) $i->qty) * ((float) $i->price_per_unit)),
            ],
            'status_history' => $statusHistory,
        ];
    }

    private function resolveOwnedMitraOrder(User $mitra, int $orderId): object
    {
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->first(['id', 'seller_id', 'order_source', 'payment_method']);

        if (! $order) {
            throw ValidationException::withMessages(['order' => 'Order tidak ditemukan']);
        }

        Gate::forUser($mitra)->authorize('order-belongs-to-seller', $order);
        $this->assertMitraOrderSource($order);

        return $order;
    }

    private function assertMitraOrderSource(object $order): void
    {
        $source = strtolower(trim((string) ($order->order_source ?? '')));
        if ($source !== self::MITRA_ORDER_SOURCE) {
            abort(403, 'Order ini bukan order marketplace mitra.');
        }
    }

    private function assertMitraTransferPayment(object $order): void
    {
        $paymentMethod = strtolower(trim((string) ($order->payment_method ?? '')));
        if ($paymentMethod !== self::MITRA_PAYMENT_METHOD) {
            throw ValidationException::withMessages([
                'payment_method' => 'Pesanan masuk Mitra hanya bisa diverifikasi jika metode pembayaran adalah transfer bank.',
            ]);
        }
    }

    /**
     * Order Mitra otomatis masuk packed setelah transfer tervalidasi.
     */
    private function markPackedAfterPaymentVerified(User $mitra, int $orderId): array
    {
        return DB::transaction(function () use ($mitra, $orderId): array {
            $order = DB::table('orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first(['id', 'seller_id', 'order_source', 'payment_status', 'order_status', 'shipping_status']);

            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan.']);
            }

            Gate::forUser($mitra)->authorize('order-belongs-to-seller', $order);
            $this->assertMitraOrderSource($order);

            if ((string) $order->order_status === 'packed') {
                return [
                    'order_id' => (int) $order->id,
                    'payment_status' => (string) ($order->payment_status ?? 'paid'),
                    'order_status' => 'packed',
                    'verification_status' => 'verified_by_seller',
                    'shipping_status' => (string) ($order->shipping_status ?? 'pending'),
                ];
            }

            $this->statusTransition->assertTransition((string) $order->order_status, 'packed');
            $fromStatus = (string) $order->order_status;

            DB::table('orders')
                ->where('id', $orderId)
                ->update([
                    'order_status' => 'packed',
                    'updated_at' => now(),
                ]);
            $this->shipments->ensurePending($orderId);

            $this->statusHistoryLogger->log(
                orderId: $orderId,
                fromStatus: $fromStatus,
                toStatus: 'packed',
                actorUserId: (int) $mitra->id,
                actorRole: (string) $mitra->role,
                note: 'Order otomatis dipindah ke packed setelah transfer diverifikasi.'
            );

            return [
                'order_id' => $orderId,
                'payment_status' => (string) ($order->payment_status ?? 'paid'),
                'order_status' => 'packed',
                'verification_status' => 'verified_by_seller',
                'shipping_status' => 'pending',
            ];
        });
    }

    private function notifyBuyerOrderShipped(int $buyerUserId, int $orderId, ?string $resiNumber): void
    {
        if ($buyerUserId <= 0 || ! $this->canDispatchNotification()) {
            return;
        }

        $buyer = User::query()->find($buyerUserId);
        if (! $buyer) {
            return;
        }

        $dispatchKey = "shipping:order:{$orderId}:shipped:buyer";
        $alreadyDispatched = DB::table('notifications')
            ->where('type', PaymentOrderStatusNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $buyerUserId)
            ->where('data', 'like', '%"dispatch_key":"' . $dispatchKey . '"%')
            ->exists();

        if ($alreadyDispatched) {
            return;
        }

        $message = $resiNumber
            ? "Pesanan #{$orderId} sudah dikirim oleh Mitra. Nomor resi: {$resiNumber}."
            : "Pesanan #{$orderId} sudah dikirim oleh Mitra dan sedang dalam pengiriman.";

        $buyer->notify(new PaymentOrderStatusNotification(
            status: 'shipped',
            title: "Pesanan #{$orderId} sudah dikirim",
            message: $message,
            actionUrl: route('orders.mine', absolute: false),
            actionLabel: 'Lihat Pesanan Saya',
            orderId: $orderId,
            paymentMethod: null,
            paidAmount: null,
            dispatchKey: $dispatchKey
        ));
    }

    private function canDispatchNotification(): bool
    {
        return Schema::hasTable('notifications') && Schema::hasTable('users');
    }
}
