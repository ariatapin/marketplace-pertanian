<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Services\OrderShipmentService;
use App\Services\OrderTransferPaymentService;
use App\Services\SettlementService;
use App\Services\UserRatingService;
use App\Support\OrderStatusHistoryLogger;
use App\Support\OrderStatusTransition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BuyerOrderController extends Controller
{
    private const RATING_WINDOW_DAYS = 7;

    public function __construct(
        protected OrderTransferPaymentService $transferPayment,
        protected SettlementService $settlement,
        protected OrderShipmentService $shipments,
        protected OrderStatusTransition $statusTransition,
        protected OrderStatusHistoryLogger $statusHistoryLogger,
        protected UserRatingService $userRatings
    ) {}

    public function index(Request $request)
    {
        $buyer = $request->user();
        $hasCompletedAtColumn = Schema::hasColumn('orders', 'completed_at');

        $ordersQuery = DB::table('orders')
            ->leftJoin('users as seller', 'seller.id', '=', 'orders.seller_id')
            ->where('orders.buyer_id', $buyer->id)
            ->select(
                'orders.id',
                'orders.seller_id',
                'orders.order_source',
                'orders.total_amount',
                'orders.payment_method',
                'orders.payment_status',
                'orders.payment_proof_url',
                'orders.paid_amount',
                'orders.payment_submitted_at',
                'orders.order_status',
                'orders.shipping_status',
                'orders.resi_number',
                'orders.updated_at',
                'orders.created_at',
                'seller.name as seller_name',
                'seller.email as seller_email'
            )
            ->orderByDesc('orders.id');

        if ($hasCompletedAtColumn) {
            $ordersQuery->addSelect('orders.completed_at');
        } else {
            $ordersQuery->selectRaw('NULL as completed_at');
        }

        $orders = $ordersQuery
            ->paginate(10)
            ->withQueryString();

        $itemsByOrder = collect();
        $disputesByOrder = collect();
        $ratingsByOrder = collect();
        $ratingMetaByOrder = collect();
        if ($orders->isNotEmpty()) {
            $itemsByOrder = DB::table('order_items')
                ->whereIn('order_id', $orders->pluck('id'))
                ->orderBy('id')
                ->get(['order_id', 'product_name', 'qty', 'price_per_unit'])
                ->groupBy('order_id');

            if (Schema::hasTable('disputes')) {
                $disputesByOrder = DB::table('disputes')
                    ->whereIn('order_id', $orders->pluck('id'))
                    ->where('buyer_id', $buyer->id)
                    ->get([
                        'id',
                        'order_id',
                        'status',
                        'category',
                        'resolution',
                        'updated_at',
                    ])
                    ->keyBy('order_id');
            }

            $ratingsByOrder = $this->userRatings->ratingsForBuyerOrders(
                (int) $buyer->id,
                $orders->pluck('id')->all()
            );

            $ratingMetaByOrder = $orders->mapWithKeys(function ($order) use ($ratingsByOrder): array {
                $existingRating = $ratingsByOrder->get((int) ($order->id ?? 0));
                $hasExistingRating = $existingRating !== null;
                $receivedAt = $this->resolveOrderReceivedAt($order);
                $deadlineAt = $receivedAt?->copy()->addDays(self::RATING_WINDOW_DAYS);
                $isCompletedPaid = (string) ($order->order_status ?? '') === 'completed'
                    && (string) ($order->payment_status ?? '') === 'paid';
                $isExpired = $deadlineAt instanceof Carbon
                    ? now()->greaterThan($deadlineAt)
                    : false;

                return [
                    (int) ($order->id ?? 0) => [
                        'has_rating' => $hasExistingRating,
                        'is_completed_paid' => $isCompletedPaid,
                        'can_rate' => $isCompletedPaid && ! $isExpired && ! $hasExistingRating,
                        'is_expired' => $isCompletedPaid && $isExpired,
                        'received_at_label' => $receivedAt?->format('d M Y H:i'),
                        'deadline_at_label' => $deadlineAt?->format('d M Y H:i'),
                        'deadline_at_iso' => $deadlineAt?->toIso8601String(),
                        'time_left_label' => $this->formatRatingTimeLeftLabel($deadlineAt),
                        'window_days' => self::RATING_WINDOW_DAYS,
                    ],
                ];
            });
        }

        return view('marketplace.orders-mine', [
            'orders' => $orders,
            'itemsByOrder' => $itemsByOrder,
            'disputesByOrder' => $disputesByOrder,
            'ratingsByOrder' => $ratingsByOrder,
            'ratingMetaByOrder' => $ratingMetaByOrder,
            'notificationCount' => (int) $buyer->unreadNotifications()->count(),
        ]);
    }

    public function submitTransferProof(Request $request, int $orderId)
    {
        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:4096'],
            'paid_amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $this->transferPayment->submit(
            $request->user(),
            $orderId,
            $request->file('proof'),
            (float) $data['paid_amount'],
            $data['payment_method'] ?? null
        );

        return back()->with('status', "Bukti pembayaran untuk order #{$orderId} berhasil dikirim.");
    }

    public function confirmReceived(Request $request, int $orderId)
    {
        $buyer = $request->user();

        DB::transaction(function () use ($buyer, $orderId) {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if (! $order) {
                throw ValidationException::withMessages(['order' => 'Order tidak ditemukan.']);
            }

            Gate::forUser($buyer)->authorize('order-belongs-to-buyer', $order);
            $this->statusTransition->assertTransition((string) $order->order_status, 'completed');
            $fromStatus = (string) $order->order_status;

            $orderUpdatePayload = [
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('orders', 'completed_at')) {
                $orderUpdatePayload['completed_at'] = now();
            }

            DB::table('orders')->where('id', $orderId)->update($orderUpdatePayload);
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
        });

        return back()->with('status', "Order #{$orderId} berhasil dikonfirmasi diterima.");
    }

    public function storeRating(Request $request, int $orderId): RedirectResponse
    {
        if (! $this->userRatings->isAvailable()) {
            return back()->withErrors([
                'rating' => 'Fitur rating belum tersedia. Hubungi admin sistem.',
            ]);
        }

        $buyer = $request->user();
        $payload = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:1200'],
        ], [
            'score.required' => 'Nilai rating wajib diisi.',
            'score.min' => 'Nilai rating minimal 1.',
            'score.max' => 'Nilai rating maksimal 5.',
        ]);

        $order = DB::table('orders')
            ->where('id', $orderId)
            ->where('buyer_id', (int) $buyer->id)
            ->first($this->ratingOrderSelectColumns());

        if (! $order) {
            throw ValidationException::withMessages([
                'order' => 'Order tidak ditemukan atau bukan milik akun Anda.',
            ]);
        }

        if ((string) ($order->order_status ?? '') !== 'completed') {
            throw ValidationException::withMessages([
                'rating' => 'Rating hanya bisa dikirim untuk order yang sudah selesai.',
            ]);
        }

        if ((string) ($order->payment_status ?? '') !== 'paid') {
            throw ValidationException::withMessages([
                'rating' => 'Rating hanya bisa dikirim untuk order dengan status pembayaran paid.',
            ]);
        }

        $receivedAt = $this->resolveOrderReceivedAt($order);
        if (! $receivedAt) {
            throw ValidationException::withMessages([
                'rating' => 'Waktu penerimaan order tidak ditemukan. Hubungi admin sistem.',
            ]);
        }

        if (now()->greaterThan($receivedAt->copy()->addDays(self::RATING_WINDOW_DAYS))) {
            throw ValidationException::withMessages([
                'rating' => 'Masa rating sudah berakhir. Rating hanya bisa dikirim maksimal 7 hari setelah barang diterima.',
            ]);
        }

        $ratedUserId = (int) ($order->seller_id ?? 0);
        if ($ratedUserId < 1 || $ratedUserId === (int) $buyer->id) {
            throw ValidationException::withMessages([
                'rating' => 'User tujuan rating tidak valid.',
            ]);
        }

        if ($this->userRatings->hasRatingForOrder(
            orderId: (int) $order->id,
            buyerId: (int) $buyer->id,
            ratedUserId: $ratedUserId
        )) {
            throw ValidationException::withMessages([
                'rating' => 'Rating untuk order ini sudah pernah dikirim dan tidak dapat diperbarui.',
            ]);
        }

        $stored = $this->userRatings->storeOnceForOrder(
            orderId: (int) $order->id,
            buyerId: (int) $buyer->id,
            ratedUserId: $ratedUserId,
            score: (int) $payload['score'],
            review: $payload['review'] ?? null
        );
        if (! $stored) {
            throw ValidationException::withMessages([
                'rating' => 'Rating untuk order ini sudah pernah dikirim dan tidak dapat diperbarui.',
            ]);
        }

        return back()->with('status', "Rating untuk order #{$orderId} berhasil disimpan.");
    }

    /**
     * @return array<int, string|\Illuminate\Database\Query\Expression>
     */
    private function ratingOrderSelectColumns(): array
    {
        $columns = [
            'id',
            'buyer_id',
            'seller_id',
            'order_status',
            'payment_status',
            'updated_at',
            'created_at',
        ];

        if (Schema::hasColumn('orders', 'completed_at')) {
            $columns[] = 'completed_at';
        } else {
            $columns[] = DB::raw('NULL as completed_at');
        }

        return $columns;
    }

    private function resolveOrderReceivedAt(object $order): ?Carbon
    {
        $candidates = [
            (string) ($order->completed_at ?? ''),
            (string) ($order->updated_at ?? ''),
            (string) ($order->created_at ?? ''),
        ];

        foreach ($candidates as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }

            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function formatRatingTimeLeftLabel(?Carbon $deadlineAt): string
    {
        if (! $deadlineAt instanceof Carbon) {
            return '-';
        }

        $secondsLeft = now()->diffInSeconds($deadlineAt, false);
        if ($secondsLeft <= 0) {
            return 'Waktu habis';
        }

        $days = intdiv($secondsLeft, 86400);
        $hours = intdiv($secondsLeft % 86400, 3600);
        $minutes = intdiv($secondsLeft % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' hari';
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . ' jam';
        }
        $parts[] = $minutes . ' menit';

        return implode(' ', $parts);
    }
}
