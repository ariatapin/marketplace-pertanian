<?php

namespace App\Services;

use App\Models\User;
use App\Support\OrderStatusHistoryLogger;
use App\Support\OrderStatusTransition;
use App\Support\PaymentOrderStatusNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderTransferPaymentService
{
    public function __construct(
        protected PaymentMethodService $paymentMethods,
        protected OrderStatusTransition $statusTransition,
        protected OrderStatusHistoryLogger $statusHistoryLogger
    ) {}

    public function submit(
        User $buyer,
        int $orderId,
        UploadedFile $proofFile,
        float $paidAmount,
        ?string $paymentMethod = null
    ): array
    {
        return DB::transaction(function () use ($buyer, $orderId, $proofFile, $paidAmount, $paymentMethod) {
            $order = DB::table('orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan.']);
            }

            if ((int) $order->buyer_id !== (int) $buyer->id) {
                throw ValidationException::withMessages(['order' => 'Order bukan milik user login.']);
            }

            if ($order->payment_status !== 'unpaid' || $order->order_status !== 'pending_payment') {
                throw ValidationException::withMessages(['payment_status' => 'Order sudah diproses pembayaran.']);
            }

            $roundedAmount = round($paidAmount, 2);
            if ($roundedAmount < (float) $order->total_amount) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Nominal pembayaran harus sama atau lebih besar dari total order.',
                ]);
            }

            $existingMethod = trim((string) ($order->payment_method ?? ''));
            if ($existingMethod !== '') {
                $resolvedMethod = $this->paymentMethods->assertSupported($existingMethod);

                if ($paymentMethod !== null && trim((string) $paymentMethod) !== '' && trim((string) $paymentMethod) !== $resolvedMethod) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Metode pembayaran order sudah ditetapkan saat checkout dan tidak dapat diubah.',
                    ]);
                }
            } else {
                $resolvedMethod = $this->paymentMethods->assertSupported($paymentMethod);
            }

            $path = $proofFile->store('payment_proofs/orders', 'public');
            $url = 'storage/' . $path;

            DB::table('orders')
                ->where('id', $orderId)
                ->update([
                    'payment_method' => $resolvedMethod,
                    'payment_proof_url' => $url,
                    'paid_amount' => $roundedAmount,
                    'payment_submitted_at' => now(),
                    'payment_status' => 'unpaid',
                    'order_status' => 'pending_payment',
                    'updated_at' => now(),
                ]);

            $this->notifyPaymentSubmitted($buyer, $order, $resolvedMethod, $roundedAmount);

            return [
                'order_id' => $orderId,
                'payment_method' => $resolvedMethod,
                'payment_proof_url' => $url,
                'paid_amount' => $roundedAmount,
                'payment_status' => 'unpaid',
                'order_status' => 'pending_payment',
                'verification_status' => 'waiting_seller_verification',
            ];
        });
    }

    public function verifyBySeller(User $seller, int $orderId): array
    {
        return DB::transaction(function () use ($seller, $orderId) {
            $order = DB::table('orders')
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan.']);
            }

            if ((int) $order->seller_id !== (int) $seller->id) {
                throw ValidationException::withMessages(['order' => 'Order bukan milik seller login.']);
            }

            if ($order->payment_status !== 'unpaid') {
                throw ValidationException::withMessages(['payment_status' => 'Order tidak dalam status menunggu verifikasi pembayaran.']);
            }

            $this->statusTransition->assertTransition((string) $order->order_status, 'paid');

            if (! $order->payment_proof_url) {
                throw ValidationException::withMessages(['payment_proof_url' => 'Bukti pembayaran belum diupload oleh buyer.']);
            }

            if ($order->paid_amount === null) {
                throw ValidationException::withMessages(['paid_amount' => 'Nominal pembayaran belum tersedia.']);
            }

            if ((float) $order->paid_amount < (float) $order->total_amount) {
                throw ValidationException::withMessages(['paid_amount' => 'Nominal pembayaran kurang dari total order.']);
            }

            $fromStatus = (string) $order->order_status;

            DB::table('orders')
                ->where('id', $orderId)
                ->update([
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
                note: 'Pembayaran diverifikasi seller.'
            );

            $this->notifyPaymentVerified($seller, $order);

            return [
                'order_id' => $orderId,
                'payment_status' => 'paid',
                'order_status' => 'paid',
                'verification_status' => 'verified_by_seller',
            ];
        });
    }

    private function notifyPaymentSubmitted(User $buyer, object $order, string $paymentMethod, float $paidAmount): void
    {
        if (! $this->canDispatchNotification()) {
            return;
        }

        $orderId = (int) $order->id;
        $dispatchPrefix = "payment:order:{$orderId}:pending";
        $methodLabel = $this->paymentMethods->label($paymentMethod);
        $paidAmountLabel = 'Rp' . number_format($paidAmount, 0, ',', '.');
        $buyerDispatchKey = $dispatchPrefix . ':buyer';

        $this->notifyPaymentStatusOnce($buyer, $buyerDispatchKey, new PaymentOrderStatusNotification(
            status: 'pending',
            title: "Bukti pembayaran order #{$orderId} terkirim",
            message: "Pembayaran {$methodLabel} sebesar {$paidAmountLabel} menunggu verifikasi seller.",
            actionUrl: route('orders.mine', absolute: false),
            actionLabel: 'Lihat Pesanan',
            orderId: $orderId,
            paymentMethod: $paymentMethod,
            paidAmount: $paidAmount,
            dispatchKey: $buyerDispatchKey
        ));

        $seller = User::query()->find((int) $order->seller_id);
        if ($seller) {
            $sellerDispatchKey = $dispatchPrefix . ':seller';
            $this->notifyPaymentStatusOnce($seller, $sellerDispatchKey, new PaymentOrderStatusNotification(
                status: 'pending',
                title: "Bukti pembayaran baru order #{$orderId}",
                message: "{$buyer->name} mengunggah bukti pembayaran {$methodLabel} sebesar {$paidAmountLabel}.",
                actionUrl: $this->actionUrlForUser($seller, $orderId, 'waiting'),
                actionLabel: 'Cek Pembayaran',
                orderId: $orderId,
                paymentMethod: $paymentMethod,
                paidAmount: $paidAmount,
                dispatchKey: $sellerDispatchKey
            ));
        }

        $adminUsers = User::query()
            ->whereNormalizedRole('admin')
            ->get();

        foreach ($adminUsers as $admin) {
            if ((int) $admin->id === (int) $buyer->id || (int) $admin->id === (int) ($seller?->id ?? 0)) {
                continue;
            }

            $adminDispatchKey = $dispatchPrefix . ':admin';
            $this->notifyPaymentStatusOnce($admin, $adminDispatchKey, new PaymentOrderStatusNotification(
                status: 'pending',
                title: "Order #{$orderId} menunggu verifikasi",
                message: "Buyer {$buyer->name} mengirim bukti {$methodLabel} ({$paidAmountLabel}).",
                actionUrl: route('admin.modules.finance', [
                    'section' => 'transfer',
                    'transfer_state' => 'waiting',
                    'transfer_q' => (string) $orderId,
                ], absolute: false),
                actionLabel: 'Buka Monitoring',
                orderId: $orderId,
                paymentMethod: $paymentMethod,
                paidAmount: $paidAmount,
                dispatchKey: $adminDispatchKey
            ));
        }
    }

    private function notifyPaymentVerified(User $seller, object $order): void
    {
        if (! $this->canDispatchNotification()) {
            return;
        }

        $orderId = (int) $order->id;
        $dispatchPrefix = "payment:order:{$orderId}:approved";
        $paymentMethod = $this->paymentMethods->normalize((string) ($order->payment_method ?? null));
        $methodLabel = $this->paymentMethods->label($paymentMethod);
        $paidAmount = (float) ($order->paid_amount ?? 0);
        $paidAmountLabel = 'Rp' . number_format($paidAmount, 0, ',', '.');

        $buyer = User::query()->find((int) $order->buyer_id);
        if ($buyer) {
            $buyerDispatchKey = $dispatchPrefix . ':buyer';
            $this->notifyPaymentStatusOnce($buyer, $buyerDispatchKey, new PaymentOrderStatusNotification(
                status: 'approved',
                title: "Pembayaran order #{$orderId} sudah diverifikasi",
                message: "Seller memverifikasi pembayaran {$methodLabel} sebesar {$paidAmountLabel}.",
                actionUrl: route('orders.mine', absolute: false),
                actionLabel: 'Pantau Pesanan',
                orderId: $orderId,
                paymentMethod: $paymentMethod,
                paidAmount: $paidAmount,
                dispatchKey: $buyerDispatchKey
            ));
        }

        $adminUsers = User::query()
            ->whereNormalizedRole('admin')
            ->get();

        foreach ($adminUsers as $admin) {
            if ((int) $admin->id === (int) $seller->id || (int) $admin->id === (int) ($buyer?->id ?? 0)) {
                continue;
            }

            $adminDispatchKey = $dispatchPrefix . ':admin';
            $this->notifyPaymentStatusOnce($admin, $adminDispatchKey, new PaymentOrderStatusNotification(
                status: 'approved',
                title: "Pembayaran order #{$orderId} terverifikasi",
                message: "Seller {$seller->name} memverifikasi pembayaran {$methodLabel} ({$paidAmountLabel}).",
                actionUrl: route('admin.modules.finance', [
                    'section' => 'transfer',
                    'transfer_state' => 'verified',
                    'transfer_q' => (string) $orderId,
                ], absolute: false),
                actionLabel: 'Buka Monitoring',
                orderId: $orderId,
                paymentMethod: $paymentMethod,
                paidAmount: $paidAmount,
                dispatchKey: $adminDispatchKey
            ));
        }
    }

    private function notifyPaymentStatusOnce(User $user, string $dispatchKey, PaymentOrderStatusNotification $notification): void
    {
        $alreadyDispatched = DB::table('notifications')
            ->where('type', PaymentOrderStatusNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', (int) $user->id)
            ->where('data', 'like', '%"dispatch_key":"' . $dispatchKey . '"%')
            ->exists();

        if ($alreadyDispatched) {
            return;
        }

        $user->notify($notification);
    }

    private function actionUrlForUser(User $user, int $orderId, string $transferState): string
    {
        if ($user->isMitra()) {
            return route('mitra.orders.show', ['orderId' => $orderId], absolute: false);
        }

        if ($user->isAdmin()) {
            return route('admin.modules.finance', [
                'section' => 'transfer',
                'transfer_state' => $transferState,
                'transfer_q' => (string) $orderId,
            ], absolute: false);
        }

        return route('dashboard', absolute: false);
    }

    private function canDispatchNotification(): bool
    {
        return Schema::hasTable('notifications') && Schema::hasTable('users');
    }
}
