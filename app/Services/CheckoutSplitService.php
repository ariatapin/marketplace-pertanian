<?php

namespace App\Services;

use App\Support\Concerns\HandlesWalletLedgerMutation;
use App\Support\OrderStatusHistoryLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CheckoutSplitService
{
    use HandlesWalletLedgerMutation;

    public function __construct(
        protected PaymentMethodService $paymentMethods,
        protected CartSanitizerService $cartSanitizer,
        protected AffiliateReferralTrackingService $affiliateTracking,
        protected OrderStatusHistoryLogger $statusHistoryLogger,
        protected OrderShipmentService $shipments
    ) {}

    /**
     * Checkout dari keranjang; bisa semua item atau subset item terpilih.
     *
     * @param  array<int, int>  $cartItemIds
     * @return array<int, int>
     */
    public function checkout(int $buyerId, ?string $paymentMethod = null, array $cartItemIds = []): array
    {
        return DB::transaction(function () use ($buyerId, $paymentMethod, $cartItemIds) {
            $resolvedPaymentMethod = $this->paymentMethods->assertSupported($paymentMethod);
            $this->cartSanitizer->sanitize($buyerId);
            $selectedIds = collect($cartItemIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $cartQuery = DB::table('cart_items')
                ->where('user_id', $buyerId);
            if (! empty($selectedIds)) {
                $cartQuery->whereIn('id', $selectedIds);
            }

            $cart = $cartQuery->lockForUpdate()->get();

            if ($cart->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Keranjang kosong']);
            }

            $resolved = $cart->map(function ($item) use ($buyerId) {
                return $this->resolveCheckoutItemFromCartRow($item, $buyerId);
            });

            $createdOrders = $this->createOrdersFromResolved(
                $buyerId,
                $resolved,
                $resolvedPaymentMethod
            );

            $deleteQuery = DB::table('cart_items')
                ->where('user_id', $buyerId);
            if (! empty($selectedIds)) {
                $deleteQuery->whereIn('id', $selectedIds);
            }
            $deleteQuery->delete();

            return $createdOrders;
        });
    }

    /**
     * Checkout langsung untuk tombol "Beli sekarang" tanpa menyentuh keranjang.
     *
     * @param  array{product_type:string,product_id:int,qty:int,affiliate_referral_id?:int|null}  $payload
     * @return array<int, int>
     */
    public function checkoutBuyNow(int $buyerId, array $payload, ?string $paymentMethod = null): array
    {
        return DB::transaction(function () use ($buyerId, $payload, $paymentMethod) {
            $resolvedPaymentMethod = $this->paymentMethods->assertSupported($paymentMethod);
            $resolvedItem = $this->resolveCheckoutItemFromPayload($payload, $buyerId);

            return $this->createOrdersFromResolved(
                $buyerId,
                collect([$resolvedItem]),
                $resolvedPaymentMethod
            );
        });
    }

    /**
     * Membuat order per seller/source lalu menyelesaikan pembayaran instan jika metode wallet.
     *
     * @return array<int, int>
     */
    private function createOrdersFromResolved(int $buyerId, Collection $resolved, string $resolvedPaymentMethod): array
    {
        $paymentKind = $this->paymentMethods->kind($resolvedPaymentMethod);
        $isWalletPayment = $paymentKind === 'wallet';

        $availableWalletBalance = 0.0;
        if ($isWalletPayment) {
            // Kunci user buyer agar debit saldo checkout tetap konsisten (ACID).
            DB::table('users')
                ->where('id', $buyerId)
                ->lockForUpdate()
                ->first(['id']);

            $walletBalance = (float) DB::table('wallet_transactions')
                ->where('wallet_id', $buyerId)
                ->sum('amount');

            $reservedWithdrawAmount = 0.0;
            if (Schema::hasTable('withdraw_requests')) {
                $reservedWithdrawRows = DB::table('withdraw_requests')
                    ->where('user_id', $buyerId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->get(['amount']);
                $reservedWithdrawAmount = (float) $reservedWithdrawRows->sum('amount');
            }

            $availableWalletBalance = round(max(0.0, $walletBalance - $reservedWithdrawAmount), 2);
        }

        $groups = $resolved->groupBy(function ($r) {
            return $r->seller_id . '|' . $r->order_source;
        });

        $createdOrders = [];

        foreach ($groups as $items) {
            $sellerId = (int) $items->first()->seller_id;
            $source = (string) $items->first()->order_source;
            $total = round((float) $items->sum(fn ($i) => (float) $i->price * (int) $i->qty), 2);

            if ($isWalletPayment && $availableWalletBalance < $total) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Saldo tidak mencukupi untuk checkout. Lakukan topup terlebih dahulu.',
                ]);
            }

            $orderId = DB::table('orders')->insertGetId([
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'order_source' => $source,
                'total_amount' => $total,
                'payment_method' => $resolvedPaymentMethod,
                'payment_status' => 'unpaid',
                'order_status' => 'pending_payment',
                'shipping_status' => 'pending',
                'payment_proof_url' => null,
                'paid_amount' => null,
                'payment_submitted_at' => null,
                'resi_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->shipments->ensurePending((int) $orderId);

            foreach ($items as $i) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $i->product_id,
                    'product_name' => $i->product_name,
                    'qty' => $i->qty,
                    'price_per_unit' => $i->price,
                    'affiliate_id' => $i->affiliate_id,
                    'commission_amount' => $i->commission_amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->decrementPurchasedStocks(
                orderSource: $source,
                items: $items,
                orderId: (int) $orderId
            );

            if ($source === 'store_online') {
                $affiliateIds = $items->pluck('affiliate_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();

                foreach ($affiliateIds as $affiliateId) {
                    $this->affiliateTracking->trackCheckoutCreated(
                        affiliateUserId: (int) $affiliateId,
                        actorUserId: $buyerId,
                        orderId: (int) $orderId,
                        sessionId: null
                    );
                }
            }

            if ($isWalletPayment) {
                $this->applyInstantWalletPayment(
                    buyerId: $buyerId,
                    sellerId: $sellerId,
                    orderId: (int) $orderId,
                    totalAmount: $total,
                    orderSource: $source,
                    paymentMethod: $resolvedPaymentMethod
                );
                $availableWalletBalance = round($availableWalletBalance - $total, 2);
            }

            $createdOrders[] = (int) $orderId;
        }

        return $createdOrders;
    }

    /**
     * Debit saldo buyer secara instan dan update status order.
     */
    private function applyInstantWalletPayment(
        int $buyerId,
        int $sellerId,
        int $orderId,
        float $totalAmount,
        string $orderSource,
        string $paymentMethod
    ): void {
        $ledgerFlags = $this->walletLedgerCompatibilityFlags();
        $hasIdempotencyKey = (bool) ($ledgerFlags['has_idempotency_key'] ?? false);
        $hasReferenceOrder = (bool) ($ledgerFlags['has_reference_order'] ?? false);
        $hasReferenceWithdraw = (bool) ($ledgerFlags['has_reference_withdraw'] ?? false);
        $safeAmount = round($totalAmount, 2);
        $walletPayload = [
            'wallet_id' => $buyerId,
            'amount' => -1 * $safeAmount,
            'transaction_type' => 'order_payment_wallet',
            'description' => 'Pembayaran order via saldo wallet',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($hasIdempotencyKey) {
            $walletPayload['idempotency_key'] = "checkout:order:{$orderId}:wallet:{$buyerId}";
        }
        if ($hasReferenceOrder) {
            $walletPayload['reference_order_id'] = $orderId;
        }
        if ($hasReferenceWithdraw) {
            $walletPayload['reference_withdraw_id'] = null;
        }

        $this->ensureWalletLedgerMutationCompat(
            payload: $walletPayload,
            hasIdempotencyKey: $hasIdempotencyKey,
            hasReferenceOrder: $hasReferenceOrder,
            hasReferenceWithdraw: $hasReferenceWithdraw,
            failureMessage: 'Ledger wallet buyer tidak konsisten saat pembayaran checkout.'
        );

        // Untuk P2P wallet, pendapatan seller harus dikreditkan langsung (tidak menunggu settlement store_online).
        if ($orderSource === 'farmer_p2p') {
            $this->lockUsersForWalletMutation([$buyerId, $sellerId]);

            $sellerPayload = [
                'wallet_id' => $sellerId,
                'amount' => $safeAmount,
                'transaction_type' => 'sale_revenue',
                'description' => 'Pendapatan penjual P2P dari pembayaran wallet',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasIdempotencyKey) {
                $sellerPayload['idempotency_key'] = "checkout:order:{$orderId}:wallet:{$sellerId}:sale_revenue";
            }
            if ($hasReferenceOrder) {
                $sellerPayload['reference_order_id'] = $orderId;
            }
            if ($hasReferenceWithdraw) {
                $sellerPayload['reference_withdraw_id'] = null;
            }

            $this->ensureWalletLedgerMutationCompat(
                payload: $sellerPayload,
                hasIdempotencyKey: $hasIdempotencyKey,
                hasReferenceOrder: $hasReferenceOrder,
                hasReferenceWithdraw: $hasReferenceWithdraw,
                failureMessage: 'Ledger wallet seller tidak konsisten saat kredit pendapatan P2P.'
            );
        }

        DB::table('orders')
            ->where('id', $orderId)
            ->update([
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'order_status' => $orderSource === 'store_online' ? 'packed' : 'paid',
                'shipping_status' => 'pending',
                'paid_amount' => $safeAmount,
                'payment_submitted_at' => now(),
                'payment_proof_url' => null,
                'updated_at' => now(),
            ]);

        $this->statusHistoryLogger->log(
            orderId: $orderId,
            fromStatus: 'pending_payment',
            toStatus: 'paid',
            actorUserId: $buyerId,
            actorRole: 'consumer',
            note: 'Pembayaran saldo wallet berhasil diproses otomatis.'
        );

        if ($orderSource === 'store_online') {
            $this->statusHistoryLogger->log(
                orderId: $orderId,
                fromStatus: 'paid',
                toStatus: 'packed',
                actorUserId: $buyerId,
                actorRole: 'consumer',
                note: 'Order Mitra otomatis masuk tahap packing setelah pembayaran saldo.'
            );
        }
    }

    /**
     * Kurangi stok saat order terbentuk agar inventory sinkron real-time.
     */
    private function decrementPurchasedStocks(string $orderSource, Collection $items, int $orderId): void
    {
        if ($orderSource === 'store_online') {
            $this->decrementStoreProductStocks($items, $orderId);
            return;
        }

        if ($orderSource === 'farmer_p2p') {
            $this->decrementFarmerHarvestStocks($items);
        }
    }

    private function decrementStoreProductStocks(Collection $items, int $orderId): void
    {
        $qtyPerProduct = $items
            ->groupBy(fn ($item) => (int) ($item->product_id ?? 0))
            ->map(fn (Collection $group) => (int) $group->sum(fn ($item) => (int) ($item->qty ?? 0)))
            ->filter(fn ($qty, $productId) => (int) $productId > 0 && (int) $qty > 0);

        foreach ($qtyPerProduct as $productId => $qtySold) {
            $product = DB::table('store_products')
                ->where('id', (int) $productId)
                ->lockForUpdate()
                ->first(['id', 'mitra_id', 'name', 'stock_qty']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'product' => "Produk store #{$productId} tidak ditemukan saat sinkron stok checkout.",
                ]);
            }

            $stockBefore = (int) ($product->stock_qty ?? 0);
            if ($stockBefore < $qtySold) {
                throw ValidationException::withMessages([
                    'product' => "Stok produk {$product->name} tidak cukup untuk checkout.",
                ]);
            }

            $stockAfter = $stockBefore - $qtySold;

            DB::table('store_products')
                ->where('id', (int) $productId)
                ->update([
                    'stock_qty' => $stockAfter,
                    'updated_at' => now(),
                ]);

            $this->logStoreStockMutation(
                mitraId: (int) ($product->mitra_id ?? 0),
                productId: (int) $productId,
                productName: (string) ($product->name ?? 'Produk'),
                qtyBefore: $stockBefore,
                qtyDelta: -1 * $qtySold,
                note: "Pengurangan stok karena order #{$orderId} berhasil dibuat."
            );
        }
    }

    private function decrementFarmerHarvestStocks(Collection $items): void
    {
        $qtyPerProduct = $items
            ->groupBy(fn ($item) => (int) ($item->product_id ?? 0))
            ->map(fn (Collection $group) => (int) $group->sum(fn ($item) => (int) ($item->qty ?? 0)))
            ->filter(fn ($qty, $productId) => (int) $productId > 0 && (int) $qty > 0);

        foreach ($qtyPerProduct as $productId => $qtySold) {
            $product = DB::table('farmer_harvests')
                ->where('id', (int) $productId)
                ->lockForUpdate()
                ->first(['id', 'name', 'stock_qty']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'product' => "Produk panen #{$productId} tidak ditemukan saat sinkron stok checkout.",
                ]);
            }

            $stockBefore = (int) ($product->stock_qty ?? 0);
            if ($stockBefore < $qtySold) {
                throw ValidationException::withMessages([
                    'product' => "Stok produk panen {$product->name} tidak cukup untuk checkout.",
                ]);
            }

            DB::table('farmer_harvests')
                ->where('id', (int) $productId)
                ->update([
                    'stock_qty' => $stockBefore - $qtySold,
                    'updated_at' => now(),
                ]);
        }
    }

    private function logStoreStockMutation(
        int $mitraId,
        int $productId,
        string $productName,
        int $qtyBefore,
        int $qtyDelta,
        ?string $note = null
    ): void {
        if (! Schema::hasTable('store_product_stock_mutations')) {
            return;
        }

        DB::table('store_product_stock_mutations')->insert([
            'mitra_id' => $mitraId,
            'store_product_id' => $productId,
            'product_name' => $productName,
            'change_type' => 'checkout_sale',
            'qty_before' => $qtyBefore,
            'qty_delta' => $qtyDelta,
            'qty_after' => $qtyBefore + $qtyDelta,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private function resolveCheckoutItemFromCartRow(object $item, ?int $buyerId = null): object
    {
        if ($item->product_type === 'store') {
            $p = DB::table('store_products')
                ->where('id', (int) $item->product_id)
                ->lockForUpdate()
                ->first();
            if (! $p) {
                throw ValidationException::withMessages(['product' => 'Produk store tidak ditemukan']);
            }
            if ((int) ($p->stock_qty ?? 0) <= 0) {
                throw ValidationException::withMessages(['product' => 'Stok produk store habis.']);
            }
            if (property_exists($p, 'is_active') && ! (bool) $p->is_active) {
                throw ValidationException::withMessages(['product' => 'Produk store sedang nonaktif.']);
            }

            $qty = max(1, min((int) ($item->qty ?? 1), (int) ($p->stock_qty ?? 1)));

            $eligibleAffiliateId = null;
            if ($this->isAffiliateCommissionActive($p)) {
                $eligibleAffiliateId = $this->resolveAffiliateAttributionId(
                    ! empty($item->affiliate_referral_id) ? (int) $item->affiliate_referral_id : null
                );
            }

            $commissionPercent = $eligibleAffiliateId !== null
                ? max(0, min(100, (float) ($p->affiliate_commission ?? 0)))
                : 0.0;
            $commissionAmount = ((float) $p->price * $qty) * ($commissionPercent / 100);

            return (object) [
                'seller_id' => (int) $p->mitra_id,
                'order_source' => 'store_online',
                'product_id' => (int) $p->id,
                'product_name' => (string) $p->name,
                'price' => (float) $p->price,
                'qty' => $qty,
                'affiliate_id' => $eligibleAffiliateId,
                'commission_amount' => round($commissionAmount, 2),
            ];
        }

        if ($item->product_type === 'farmer') {
            $p = DB::table('farmer_harvests')
                ->where('id', (int) $item->product_id)
                ->lockForUpdate()
                ->first();
            if (! $p) {
                throw ValidationException::withMessages(['product' => 'Produk panen tidak ditemukan']);
            }
            if ((int) ($p->stock_qty ?? 0) <= 0) {
                throw ValidationException::withMessages(['product' => 'Stok produk panen habis.']);
            }
            if (strtolower(trim((string) ($p->status ?? ''))) !== 'approved') {
                throw ValidationException::withMessages(['product' => 'Produk panen tidak aktif untuk checkout.']);
            }
            $resolvedBuyerId = $buyerId ?? (int) ($item->user_id ?? 0);
            if ($resolvedBuyerId > 0 && (int) ($p->farmer_id ?? 0) === $resolvedBuyerId) {
                throw ValidationException::withMessages([
                    'product' => 'Produk hasil tani milik Anda sendiri tidak dapat dibeli.',
                ]);
            }

            $qty = max(1, min((int) ($item->qty ?? 1), (int) ($p->stock_qty ?? 1)));

            return (object) [
                'seller_id' => (int) $p->farmer_id,
                'order_source' => 'farmer_p2p',
                'product_id' => (int) $p->id,
                'product_name' => (string) $p->name,
                'price' => (float) $p->price,
                'qty' => $qty,
                'affiliate_id' => null, // P2P penjual tidak menggunakan komisi affiliate.
                'commission_amount' => 0,
            ];
        }

        throw ValidationException::withMessages(['product_type' => 'product_type tidak valid']);
    }

    /**
     * Resolve item buy-now ke snapshot checkout yang sama dengan item keranjang.
     *
     * @param  array{product_type:string,product_id:int,qty:int,affiliate_referral_id?:int|null}  $payload
     */
    private function resolveCheckoutItemFromPayload(array $payload, ?int $buyerId = null): object
    {
        return $this->resolveCheckoutItemFromCartRow((object) [
            'product_type' => (string) ($payload['product_type'] ?? 'store'),
            'product_id' => (int) ($payload['product_id'] ?? 0),
            'qty' => (int) ($payload['qty'] ?? 1),
            'affiliate_referral_id' => isset($payload['affiliate_referral_id']) ? (int) $payload['affiliate_referral_id'] : null,
            'user_id' => $buyerId,
        ], $buyerId);
    }

    /**
     * Komisi affiliate aktif jika fitur affiliate produk menyala dan belum melewati masa berlaku.
     */
    private function isAffiliateCommissionActive(object $product): bool
    {
        if (! (bool) ($product->is_affiliate_enabled ?? false)) {
            return false;
        }

        $rawExpiry = $product->affiliate_expire_date ?? null;
        if (empty($rawExpiry)) {
            return true;
        }

        try {
            $expiry = Carbon::parse((string) $rawExpiry)->endOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $expiry->greaterThanOrEqualTo(now());
    }

    /**
     * Validasi affiliate attribution agar komisi tidak jatuh ke user non-affiliate.
     */
    private function resolveAffiliateAttributionId(?int $candidateAffiliateId): ?int
    {
        if (($candidateAffiliateId ?? 0) <= 0) {
            return null;
        }

        $user = DB::table('users')
            ->where('id', $candidateAffiliateId)
            ->first(['id', 'role']);
        if (! $user) {
            return null;
        }

        $normalizedRole = strtolower(trim((string) ($user->role ?? '')));
        if ($normalizedRole !== 'consumer') {
            return null;
        }

        if (! Schema::hasTable('consumer_profiles')) {
            return null;
        }

        $approvedAffiliate = DB::table('consumer_profiles')
            ->where('user_id', (int) $user->id)
            ->where('mode', 'affiliate')
            ->where('mode_status', 'approved')
            ->exists();

        return $approvedAffiliate ? (int) $user->id : null;
    }
}
