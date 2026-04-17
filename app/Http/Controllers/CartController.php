<?php

namespace App\Http\Controllers;

use App\Services\AffiliateReferralService;
use App\Services\AffiliateLockPolicyService;
use App\Services\AffiliateReferralTrackingService;
use App\Services\CartSanitizerService;
use App\Services\CheckoutSplitService;
use App\Services\ConsumerPurchasePolicyService;
use App\Services\OrderTransferPaymentService;
use App\Services\PaymentMethodService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        protected AffiliateReferralService $affiliateReferral,
        protected AffiliateLockPolicyService $affiliateLockPolicy,
        protected AffiliateReferralTrackingService $affiliateTracking,
        protected CartSanitizerService $cartSanitizer,
        protected ConsumerPurchasePolicyService $consumerPurchasePolicy,
        protected PaymentMethodService $paymentMethods,
        protected OrderTransferPaymentService $transferPayment,
        protected WalletService $walletService
    ) {}

    public function index(Request $request)
    {
        $buyer = $request->user();
        $this->cartSanitizer->sanitize($buyer->id);
        $canCheckoutByMode = $this->consumerPurchasePolicy->canCheckout($buyer);
        $checkoutRestrictionMessage = $canCheckoutByMode
            ? null
            : $this->consumerPurchasePolicy->checkoutUnavailableMessage($buyer);

        $items = DB::table('cart_items')
            ->leftJoin('store_products', function ($join) {
                $join->on('store_products.id', '=', 'cart_items.product_id')
                    ->where('cart_items.product_type', '=', 'store');
            })
            ->leftJoin('users as mitra', 'mitra.id', '=', 'store_products.mitra_id')
            ->leftJoin('farmer_harvests', function ($join) {
                $join->on('farmer_harvests.id', '=', 'cart_items.product_id')
                    ->where('cart_items.product_type', '=', 'farmer');
            })
            ->leftJoin('users as seller', 'seller.id', '=', 'farmer_harvests.farmer_id')
            ->where('cart_items.user_id', $buyer->id)
            ->orderByDesc('cart_items.updated_at')
            ->select([
                'cart_items.id',
                'cart_items.product_type',
                'cart_items.product_id',
                'cart_items.qty',
                'store_products.name as store_product_name',
                'store_products.price as store_price',
                'store_products.stock_qty as store_stock_qty',
                'store_products.is_active as store_is_active',
                'store_products.image_url as store_image_url',
                'mitra.name as mitra_name',
                'farmer_harvests.name as farmer_product_name',
                'farmer_harvests.price as farmer_price',
                'farmer_harvests.stock_qty as farmer_stock_qty',
                'farmer_harvests.status as farmer_status',
                'farmer_harvests.image_url as farmer_image_url',
                'seller.name as seller_name',
            ])
            ->get()
            ->map(function ($row) use ($canCheckoutByMode, $checkoutRestrictionMessage): array {
                $qty = max(1, (int) ($row->qty ?? 1));
                $isStoreProduct = (string) ($row->product_type ?? '') === 'store';
                $productName = $isStoreProduct
                    ? (string) ($row->store_product_name ?? '')
                    : (string) ($row->farmer_product_name ?? '');
                $productId = (int) ($row->product_id ?? 0);
                $stockQty = $isStoreProduct
                    ? max(0, (int) ($row->store_stock_qty ?? 0))
                    : max(0, (int) ($row->farmer_stock_qty ?? 0));
                $price = $isStoreProduct
                    ? (float) ($row->store_price ?? 0)
                    : (float) ($row->farmer_price ?? 0);
                $rawImage = $isStoreProduct
                    ? (string) ($row->store_image_url ?? '')
                    : (string) ($row->farmer_image_url ?? '');
                $sellerName = $isStoreProduct
                    ? (string) ($row->mitra_name ?? '-')
                    : (string) ($row->seller_name ?? '-');

                $isProductFound = $productName !== '';
                $isActive = $isStoreProduct
                    ? ($row->store_is_active === null ? true : (bool) $row->store_is_active)
                    : (strtolower(trim((string) ($row->farmer_status ?? ''))) === 'approved');
                $isInStock = $stockQty > 0;
                $canCheckout = $isProductFound && $isActive && $isInStock && $canCheckoutByMode;
                $effectiveQty = $isInStock ? min($qty, $stockQty) : $qty;

                return [
                    'id' => (int) $row->id,
                    'product_type' => (string) $row->product_type,
                    'product_id' => $productId,
                    'product_name' => $productName !== '' ? $productName : 'Produk tidak tersedia',
                    'product_image_src' => $this->resolveProductImageSource($rawImage),
                    'product_detail_url' => ($isProductFound && $productId > 0)
                        ? route('marketplace.product.show', [
                            'productType' => $isStoreProduct ? 'store' : 'farmer',
                            'productId' => $productId,
                            'source' => 'cart',
                        ])
                        : null,
                    'can_open_product' => $isProductFound && $productId > 0,
                    'seller_label' => $isStoreProduct ? 'Mitra' : 'Penjual',
                    'seller_name' => $sellerName !== '' ? $sellerName : '-',
                    'qty' => $qty,
                    'effective_qty' => $effectiveQty,
                    'stock_qty' => $stockQty,
                    'price' => $price,
                    'price_label' => 'Rp' . number_format($price, 0, ',', '.'),
                    'line_total' => $canCheckout ? ($effectiveQty * $price) : 0,
                    'line_total_label' => 'Rp' . number_format($canCheckout ? ($effectiveQty * $price) : 0, 0, ',', '.'),
                    'is_store_product' => $isStoreProduct,
                    'is_active' => $isActive,
                    'is_in_stock' => $isInStock,
                    'can_checkout' => $canCheckout,
                    'can_update_qty' => $isProductFound && $isInStock,
                    'warning' => ! $isProductFound
                        ? 'Item lama tidak lagi terhubung ke katalog produk aktif.'
                        : (! $isActive
                            ? ($isStoreProduct ? 'Produk sedang nonaktif.' : 'Produk penjual tidak aktif untuk checkout.')
                            : (! $isInStock
                                ? 'Stok habis untuk produk ini.'
                                : (! $canCheckoutByMode ? $checkoutRestrictionMessage : null))),
                ];
            })
            ->values();

        $checkoutItems = $items->filter(fn (array $item): bool => (bool) $item['can_checkout'])->values();

        $checkoutHasMitraProducts = $checkoutItems
            ->contains(fn (array $item): bool => (bool) ($item['is_store_product'] ?? false));
        $checkoutHasSellerProducts = $checkoutItems
            ->contains(fn (array $item): bool => ! (bool) ($item['is_store_product'] ?? false));

        $paymentOptions = $this->consumerPurchasePolicy->checkoutOptions($buyer);
        $resolvedMode = $this->consumerPurchasePolicy->resolveMode($buyer);
        $checkoutPaymentMethodMeta = $this->paymentMethods->methodsForConsumerMode($resolvedMode);

        $defaultPaymentMethod = null;
        if ($canCheckoutByMode && ! empty($paymentOptions)) {
            $defaultPaymentMethod = $this->consumerPurchasePolicy->defaultCheckoutMethod($buyer);
            if (! collect($paymentOptions)->contains(fn (array $option): bool => (string) ($option['method'] ?? '') === $defaultPaymentMethod)) {
                $defaultPaymentMethod = (string) ($paymentOptions[0]['method'] ?? $defaultPaymentMethod);
            }
        }

        $checkoutModeMeta = $this->consumerPurchasePolicy->modeMeta($buyer);
        $checkoutPaymentHelper = (string) ($checkoutModeMeta['helper'] ?? 'Pilih metode pembayaran aktif untuk melanjutkan checkout.');
        $walletBalance = 0.0;
        $hasWalletBalance = false;
        if (Schema::hasTable('wallet_transactions')) {
            $walletBalance = max(0, (float) $this->walletService->getBalance((int) $buyer->id));
            $hasWalletBalance = $walletBalance > 0;
        }

        return view('marketplace.cart', [
            'items' => $items,
            'checkoutItems' => $checkoutItems,
            'summary' => [
                'line_count' => $items->count(),
                'qty_total' => (int) $items->sum('qty'),
                'checkout_line_count' => $checkoutItems->count(),
                'checkout_qty_total' => (int) $checkoutItems->sum('effective_qty'),
                'estimated_total' => (float) $checkoutItems->sum('line_total'),
            ],
            'paymentOptions' => $paymentOptions,
            'defaultPaymentMethod' => $defaultPaymentMethod,
            'checkoutModeMeta' => $checkoutModeMeta,
            'checkoutPaymentHelper' => $checkoutPaymentHelper,
            'checkoutPaymentMethodMeta' => $checkoutPaymentMethodMeta,
            'walletBalance' => $walletBalance,
            'hasWalletBalance' => $hasWalletBalance,
            'checkoutHasMitraProducts' => $checkoutHasMitraProducts,
            'checkoutHasSellerProducts' => $checkoutHasSellerProducts,
            'paymentMethodLabels' => $this->paymentMethods->labelMap(),
            'notificationCount' => (int) $buyer->unreadNotifications()->count(),
            'canCheckoutByMode' => $canCheckoutByMode,
            'checkoutRestrictionMessage' => $checkoutRestrictionMessage,
        ]);
    }

    private function resolveProductImageSource(?string $rawImage): string
    {
        $image = trim((string) $rawImage);
        if ($image === '') {
            return asset('images/product-placeholder.svg');
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        if (Str::startsWith($image, ['/storage/', 'storage/'])) {
            $relativePath = ltrim(Str::replaceFirst('storage/', '', ltrim($image, '/')), '/');
            if ($relativePath !== '' && Storage::disk('public')->exists($relativePath)) {
                return asset('storage/' . $relativePath);
            }

            return asset('images/product-placeholder.svg');
        }

        $relative = ltrim($image, '/');
        if ($relative !== '' && Storage::disk('public')->exists($relative)) {
            return asset('storage/' . $relative);
        }

        if (Str::startsWith($image, '/')) {
            $publicPath = public_path(ltrim($image, '/'));
            if (is_file($publicPath)) {
                return $image;
            }
        }

        return asset('images/product-placeholder.svg');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'product_type' => ['nullable', 'string', 'in:store,farmer'],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
            'buy_now' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', 'string'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:4096'],
        ]);
        $productType = (string) ($data['product_type'] ?? 'store');
        $isBuyNow = $request->boolean('buy_now');

        if ($isBuyNow) {
            $safeQty = (int) $data['qty'];
            $productId = (int) $data['product_id'];
            $affiliateReferralId = null;

            if ($productType === 'farmer') {
                if (! Schema::hasTable('farmer_harvests')) {
                    throw ValidationException::withMessages(['product_id' => 'Produk penjual belum tersedia.']);
                }
                $product = DB::table('farmer_harvests')->where('id', $productId)->first();
                if (! $product) {
                    throw ValidationException::withMessages(['product_id' => 'Produk penjual tidak ditemukan.']);
                }
                if ((int) ($product->farmer_id ?? 0) === (int) $request->user()->id) {
                    throw ValidationException::withMessages([
                        'product_id' => 'Produk hasil tani milik Anda sendiri tidak dapat dibeli.',
                    ]);
                }
                if (strtolower(trim((string) ($product->status ?? ''))) !== 'approved') {
                    throw ValidationException::withMessages(['product_id' => 'Produk penjual tidak aktif untuk dibeli.']);
                }
                if ((int) ($product->stock_qty ?? 0) < 1) {
                    throw ValidationException::withMessages(['product_id' => 'Stok produk habis.']);
                }

                $safeQty = min($safeQty, max(1, (int) ($product->stock_qty ?? 1)));
                $resolvedProductId = (int) $product->id;
            } else {
                $product = DB::table('store_products')->where('id', $productId)->first();
                if (! $product) {
                    throw ValidationException::withMessages(['product_id' => 'Produk tidak ditemukan.']);
                }
                if ((int) ($product->stock_qty ?? 0) < 1) {
                    throw ValidationException::withMessages(['product_id' => 'Stok produk habis.']);
                }
                if (property_exists($product, 'is_active') && ! (bool) $product->is_active) {
                    throw ValidationException::withMessages(['product_id' => 'Produk sedang nonaktif dan belum dapat dibeli.']);
                }

                $safeQty = min($safeQty, max(1, (int) ($product->stock_qty ?? 1)));
                $resolvedProductId = (int) $product->id;
                $affiliateReferralId = $this->affiliateReferral->referralIdForStoreProduct(
                    $request,
                    $request->user(),
                    $product
                );
            }

            /** @var CheckoutSplitService $checkoutService */
            $checkoutService = app(CheckoutSplitService::class);
            $selectedMethod = $this->consumerPurchasePolicy->assertCheckoutMethod(
                $request->user(),
                $data['payment_method'] ?? null
            );
            $paymentKind = $this->paymentMethods->kind($selectedMethod);
            if ($paymentKind === 'bank' && ! $request->hasFile('proof')) {
                throw ValidationException::withMessages([
                    'proof' => 'Upload bukti transfer wajib diisi untuk Beli Sekarang dengan metode transfer bank.',
                ]);
            }

            $orderIds = DB::transaction(function () use (
                $checkoutService,
                $request,
                $productType,
                $resolvedProductId,
                $safeQty,
                $affiliateReferralId,
                $selectedMethod,
                $paymentKind
            ) {
                if ($productType === 'store' && ($affiliateReferralId ?? 0) > 0) {
                    $this->ensureAffiliateProductLock(
                        affiliateId: (int) $affiliateReferralId,
                        productId: (int) $resolvedProductId
                    );
                }

                $createdOrderIds = $checkoutService->checkoutBuyNow(
                    (int) $request->user()->id,
                    [
                        'product_type' => $productType,
                        'product_id' => $resolvedProductId,
                        'qty' => max(1, $safeQty),
                        'affiliate_referral_id' => $affiliateReferralId,
                    ],
                    $selectedMethod
                );

                if ($paymentKind === 'bank') {
                    $orderId = (int) ($createdOrderIds[0] ?? 0);
                    if ($orderId <= 0) {
                        throw ValidationException::withMessages([
                            'order' => 'Order gagal dibuat untuk proses upload bukti transfer.',
                        ]);
                    }

                    $proofFile = $request->file('proof');
                    if (! $proofFile) {
                        throw ValidationException::withMessages([
                            'proof' => 'File bukti transfer tidak terbaca. Silakan unggah ulang.',
                        ]);
                    }

                    $totalAmount = (float) DB::table('orders')
                        ->where('id', $orderId)
                        ->value('total_amount');
                    $paidAmount = max(1, $totalAmount);

                    $this->transferPayment->submit(
                        $request->user(),
                        $orderId,
                        $proofFile,
                        $paidAmount,
                        $selectedMethod
                    );
                }

                return $createdOrderIds;
            });

            $statusMessage = $paymentKind === 'wallet'
                ? 'Pembayaran saldo berhasil. Order langsung diproses: #' . implode(', #', $orderIds)
                : 'Order langsung dibuat. Bukti transfer berhasil dikirim untuk order: #' . implode(', #', $orderIds);

            return redirect()
                ->route('orders.mine')
                ->with('status', $statusMessage);
        }

        $result = DB::transaction(function () use ($request, $data, $productType) {
            $safeQty = (int) $data['qty'];
            $productId = (int) $data['product_id'];

            $affiliateReferralId = null;

            if ($productType === 'farmer') {
                if (! Schema::hasTable('farmer_harvests')) {
                    throw ValidationException::withMessages(['product_id' => 'Produk penjual belum tersedia.']);
                }
                $product = DB::table('farmer_harvests')
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages(['product_id' => 'Produk penjual tidak ditemukan.']);
                }
                if ((int) ($product->farmer_id ?? 0) === (int) $request->user()->id) {
                    throw ValidationException::withMessages([
                        'product_id' => 'Produk hasil tani milik Anda sendiri tidak dapat dibeli.',
                    ]);
                }
                if (strtolower(trim((string) ($product->status ?? ''))) !== 'approved') {
                    throw ValidationException::withMessages(['product_id' => 'Produk penjual tidak aktif untuk dibeli.']);
                }
                if ((int) $product->stock_qty < 1) {
                    throw ValidationException::withMessages(['product_id' => 'Stok produk habis.']);
                }

                $safeQty = min($safeQty, (int) $product->stock_qty);
                $resolvedProductId = (int) $product->id;
            } else {
                $product = DB::table('store_products')
                    ->where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages(['product_id' => 'Produk tidak ditemukan.']);
                }
                if ((int) $product->stock_qty < 1) {
                    throw ValidationException::withMessages(['product_id' => 'Stok produk habis.']);
                }
                if (property_exists($product, 'is_active') && ! (bool) $product->is_active) {
                    throw ValidationException::withMessages(['product_id' => 'Produk sedang nonaktif dan belum dapat dibeli.']);
                }

                $safeQty = min($safeQty, (int) $product->stock_qty);
                $resolvedProductId = (int) $product->id;
                $affiliateReferralId = $this->affiliateReferral->referralIdForStoreProduct(
                    $request,
                    $request->user(),
                    $product
                );
            }

            $existing = DB::table('cart_items')
                ->where('user_id', $request->user()->id)
                ->where('product_type', $productType)
                ->where('product_id', $resolvedProductId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $newQty = min(((int) $existing->qty) + $safeQty, (int) $product->stock_qty);

                DB::table('cart_items')
                    ->where('id', $existing->id)
                    ->update(array_filter([
                        'qty' => $newQty,
                        'affiliate_referral_id' => ((int) ($existing->affiliate_referral_id ?? 0) <= 0 && $affiliateReferralId !== null)
                            ? $affiliateReferralId
                            : null,
                        'updated_at' => now(),
                    ], function ($value, $key) {
                        return ! ($key === 'affiliate_referral_id' && $value === null);
                    }, ARRAY_FILTER_USE_BOTH));
            } else {
                DB::table('cart_items')->insert([
                    'user_id' => $request->user()->id,
                    'product_type' => $productType,
                    'product_id' => $resolvedProductId,
                    'qty' => $safeQty,
                    'affiliate_referral_id' => $affiliateReferralId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($productType === 'store' && ($affiliateReferralId ?? 0) > 0) {
                $this->ensureAffiliateProductLock(
                    affiliateId: (int) $affiliateReferralId,
                    productId: (int) $resolvedProductId
                );
                $this->affiliateTracking->trackAddToCart(
                    affiliateUserId: (int) $affiliateReferralId,
                    actorUserId: (int) $request->user()->id,
                    productId: (int) $resolvedProductId,
                    sessionId: $request->hasSession() ? $request->session()->getId() : null
                );
            }

            $cartSummaryQuery = DB::table('cart_items')
                ->where('user_id', (int) $request->user()->id);

            return [
                'status' => 'Produk masuk ke keranjang.',
                'cart_summary' => [
                    'items' => (int) (clone $cartSummaryQuery)->count(),
                    'qty_total' => (int) (clone $cartSummaryQuery)->sum('qty'),
                ],
            ];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['status'],
                'cart_summary' => $result['cart_summary'] ?? null,
            ]);
        }

        return back()->with('status', $result['status']);
    }

    public function update(Request $request, int $cartItemId)
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $buyerId = (int) $request->user()->id;
        $requestedQty = (int) $data['qty'];

        $result = DB::transaction(function () use ($buyerId, $cartItemId, $requestedQty): array {
            $item = DB::table('cart_items')
                ->where('id', $cartItemId)
                ->where('user_id', $buyerId)
                ->lockForUpdate()
                ->first(['id', 'product_type', 'product_id']);

            if (! $item) {
                throw ValidationException::withMessages([
                    'cart' => 'Item keranjang tidak ditemukan.',
                ]);
            }

            $productType = (string) ($item->product_type ?? 'store');
            $productId = (int) ($item->product_id ?? 0);
            $stockQty = 0;
            $isFound = false;

            if ($productType === 'farmer') {
                if (Schema::hasTable('farmer_harvests')) {
                    $product = DB::table('farmer_harvests')
                        ->where('id', $productId)
                        ->lockForUpdate()
                        ->first(['id', 'stock_qty']);
                    if ($product) {
                        $isFound = true;
                        $stockQty = max(0, (int) ($product->stock_qty ?? 0));
                    }
                }
            } else {
                if (Schema::hasTable('store_products')) {
                    $product = DB::table('store_products')
                        ->where('id', $productId)
                        ->lockForUpdate()
                        ->first(['id', 'stock_qty']);
                    if ($product) {
                        $isFound = true;
                        $stockQty = max(0, (int) ($product->stock_qty ?? 0));
                    }
                }
            }

            if (! $isFound) {
                DB::table('cart_items')->where('id', (int) $item->id)->delete();
                throw ValidationException::withMessages([
                    'cart' => 'Produk pada item keranjang tidak ditemukan. Item dihapus otomatis.',
                ]);
            }

            if ($stockQty < 1) {
                throw ValidationException::withMessages([
                    'qty' => 'Stok produk saat ini habis. Hapus item dari keranjang atau pilih produk lain.',
                ]);
            }

            $safeQty = min($requestedQty, $stockQty);
            DB::table('cart_items')
                ->where('id', (int) $item->id)
                ->update([
                    'qty' => $safeQty,
                    'updated_at' => now(),
                ]);

            return [
                'qty' => $safeQty,
                'requested_qty' => $requestedQty,
            ];
        });

        $status = 'Jumlah item keranjang berhasil diperbarui.';
        if ((int) ($result['qty'] ?? 0) < (int) ($result['requested_qty'] ?? 0)) {
            $status = 'Jumlah disesuaikan dengan stok tersedia.';
        }

        return back()->with('status', $status);
    }

    public function destroy(Request $request, int $cartItemId)
    {
        $deleted = DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('user_id', (int) $request->user()->id)
            ->delete();

        if ($deleted <= 0) {
            return back()->withErrors([
                'cart' => 'Item keranjang tidak ditemukan.',
            ]);
        }

        return back()->with('status', 'Item berhasil dihapus dari keranjang.');
    }

    /**
     * Lock affiliate-product pair based on admin policy to drive cool-down visibility on affiliate workspace.
     */
    private function ensureAffiliateProductLock(int $affiliateId, int $productId): void
    {
        if (
            $affiliateId <= 0
            || $productId <= 0
            || ! Schema::hasTable('affiliate_locks')
        ) {
            return;
        }

        $policy = $this->affiliateLockPolicy->resolve();
        if (! (bool) ($policy['cooldown_enabled'] ?? true)) {
            return;
        }

        $lockDays = max(1, min(365, (int) ($policy['lock_days'] ?? 30)));
        $refreshOnRepromote = (bool) ($policy['refresh_on_repromote'] ?? false);
        $today = now()->toDateString();
        $activeLock = DB::table('affiliate_locks')
            ->where('affiliate_id', $affiliateId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first(['id', 'expiry_date']);

        if ($activeLock) {
            $stillActive = false;
            if (! empty($activeLock->expiry_date)) {
                try {
                    $stillActive = Carbon::parse((string) $activeLock->expiry_date)->toDateString() >= $today;
                } catch (\Throwable) {
                    $stillActive = false;
                }
            }

            if ($stillActive && ! $refreshOnRepromote) {
                return;
            }

            DB::table('affiliate_locks')
                ->where('id', (int) $activeLock->id)
                ->update([
                    'start_date' => $today,
                    'expiry_date' => now()->addDays($lockDays)->toDateString(),
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('affiliate_locks')->insert([
            'affiliate_id' => $affiliateId,
            'product_id' => $productId,
            'is_active' => true,
            'start_date' => $today,
            'expiry_date' => now()->addDays($lockDays)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
