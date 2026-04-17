<?php

namespace App\Services\Automation;

use App\Http\Controllers\AdminWithdrawController;
use App\Http\Controllers\AdminProcurementController;
use App\Http\Controllers\Mitra\StoreProductController;
use App\Http\Controllers\MitraProcurementController;
use App\Http\Controllers\P2PSellerPaymentController;
use App\Http\Controllers\SellerOrderStatusController;
use App\Http\Controllers\WithdrawController;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\CheckoutSplitService;
use App\Services\ConsumerPurchasePolicyService;
use App\Services\DemoWalletTopupService;
use App\Services\FeatureFlagService;
use App\Services\Location\LocationResolver;
use App\Services\Mitra\MitraOrderWorkflowService;
use App\Services\OrderShipmentService;
use App\Services\PaymentMethodService;
use App\Services\Recommendation\RuleBasedRecommendationService;
use App\Services\SettlementService;
use App\Services\WithdrawBankAccountService;
use App\Services\Weather\WeatherAlertEngine;
use App\Services\Weather\WeatherService;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use App\Support\OrderStatusHistoryLogger;
use App\Support\OrderStatusTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RoleAutomationCycleService
{
    private const MAX_MITRA_PRODUCT_ACTIVATION = 4;
    private const MAX_SELLER_PRODUCT_FIX = 10;
    private const MAX_CONSUMER_CHECKOUT = 8;
    private const MAX_MITRA_ORDER_ACTION = 10;
    private const MAX_SELLER_ORDER_ACTION = 10;
    private const MAX_MITRA_PROCUREMENT_CREATE = 4;
    private const MAX_ADMIN_PROCUREMENT_ACTION = 16;
    private const MAX_ADMIN_WEATHER_NOTICE_ACTION = 8;
    private const MAX_MITRA_WITHDRAW_REQUEST = 6;
    private const MAX_SELLER_WITHDRAW_REQUEST = 6;
    private const MAX_AFFILIATE_WITHDRAW_REQUEST = 6;
    private const MAX_ADMIN_WITHDRAW_ACTION = 18;
    private const MAX_MITRA_PROCUREMENT_CONFIRM = 10;
    private const MAX_CONSUMER_CONFIRM = 16;
    private const MIN_MITRA_GALLERY = 3;
    private const MAX_MITRA_GALLERY = 5;
    private const AUTO_WEATHER_NOTICE_PREFIX = '[AUTO][ADMIN][WEATHER]';

    private string $cycleKey = '';

    /** @var array<int, array{severity:string,location_label:string,admin_notice:?string}> */
    private array $weatherCache = [];

    /** @var array<int, array<int, string>> */
    private array $recommendationCache = [];

    /** @var array<int, string> */
    private array $imagePoolCache = [];

    private bool $weatherResolved = false;

    private ?WeatherService $weatherService = null;

    public function __construct(
        private readonly FeatureFlagService $featureFlags,
        private readonly RuleBasedRecommendationService $recommendations,
        private readonly LocationResolver $locationResolver,
        private readonly WeatherAlertEngine $weatherAlertEngine,
        private readonly CheckoutSplitService $checkoutSplitService,
        private readonly ConsumerPurchasePolicyService $consumerPurchasePolicy,
        private readonly PaymentMethodService $paymentMethods,
        private readonly DemoWalletTopupService $demoWalletTopupService,
        private readonly WithdrawBankAccountService $withdrawBankAccounts,
        private readonly MitraOrderWorkflowService $mitraOrderWorkflow,
        private readonly OrderStatusTransition $orderStatusTransition,
        private readonly OrderStatusHistoryLogger $orderStatusHistoryLogger,
        private readonly OrderShipmentService $orderShipmentService,
        private readonly SettlementService $settlementService
    ) {
    }

    public function run(bool $force = false): array
    {
        $this->cycleKey = now()->format('YmdHi');
        $summary = $this->initializeSummary($force);

        if (! $force && ! $this->featureFlags->isEnabled('automation_role_cycle', false)) {
            $summary['skipped'] = true;
            $summary['reason'] = 'Feature flag automation_role_cycle nonaktif.';
            $summary['finished_at'] = now()->toDateTimeString();
            return $summary;
        }

        $this->syncRecommendations($summary);
        $imagePool = $this->loadImagePool();
        $summary['image_pool_count'] = count($imagePool);

        $this->runSafely($summary['admin'], fn () => $this->autoAdminWeatherNoticeWorkflow($summary['admin']));
        $this->runSafely($summary['mitra'], fn () => $this->autoActivateMitraProducts($imagePool, $summary['mitra']));
        $this->runSafely($summary['seller'], fn () => $this->autoHydrateSellerProducts($imagePool, $summary['seller']));
        $this->runSafely($summary['consumer'], fn () => $this->autoConsumerCheckout($summary['consumer']));
        $this->runSafely($summary['mitra'], fn () => $this->autoMitraOrderWorkflow($summary['mitra']));
        $this->runSafely($summary['seller'], fn () => $this->autoSellerOrderWorkflow($summary['seller']));
        $this->runSafely($summary['mitra'], fn () => $this->autoMitraProcurement($summary['mitra']));
        $this->runSafely($summary['admin'], fn () => $this->autoAdminProcurementWorkflow($summary['admin']));
        $this->runSafely($summary['mitra'], fn () => $this->autoMitraProcurementConfirm($summary['mitra']));
        $this->runSafely($summary['consumer'], fn () => $this->autoConsumerConfirmReceived($summary['consumer']));
        $this->runSafely($summary['mitra'], fn () => $this->autoMitraWithdrawRequests($summary['mitra']));
        $this->runSafely($summary['seller'], fn () => $this->autoSellerWithdrawRequests($summary['seller']));
        $this->runSafely($summary['consumer'], fn () => $this->autoAffiliateWithdrawRequests($summary['consumer']));
        $this->runSafely($summary['admin'], fn () => $this->autoAdminWithdrawWorkflow($summary['admin']));

        $summary['finished_at'] = now()->toDateTimeString();

        return $summary;
    }

    private function syncRecommendations(array &$summary): void
    {
        try {
            $result = $this->recommendations->syncForRoles(['consumer', 'mitra', 'seller'], 200);
            $summary['recommendation'] = [
                'processed' => (int) ($result['processed'] ?? 0),
                'dispatched' => (int) ($result['dispatched'] ?? 0),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $summary['recommendation'] = [
                'processed' => 0,
                'dispatched' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function autoActivateMitraProducts(array $imagePool, array &$bucket): void
    {
        if (
            ! Schema::hasTable('store_products')
            || ! Schema::hasColumn('store_products', 'is_active')
            || ! Schema::hasColumn('store_products', 'source_admin_product_id')
        ) {
            return;
        }

        $products = StoreProduct::query()
            ->where('is_active', false)
            ->whereNotNull('source_admin_product_id')
            ->orderBy('id')
            ->limit(self::MAX_MITRA_PRODUCT_ACTIVATION)
            ->get();

        foreach ($products as $product) {
            $mitra = User::query()->find((int) $product->mitra_id);
            if (! $mitra || ! $mitra->isMitra()) {
                continue;
            }

            try {
                $currentGallery = $this->storeProductGallery((int) $product->id, (string) ($product->image_url ?? ''));
                if (count($currentGallery) < self::MIN_MITRA_GALLERY) {
                    if (! Schema::hasTable('store_product_images')) {
                        continue;
                    }

                    $need = self::MIN_MITRA_GALLERY - count($currentGallery);
                    $additional = $this->pickRandomImages($imagePool, $need, $currentGallery);
                    if (count($additional) < $need) {
                        $this->appendError($bucket, "Produk #{$product->id}: gambar unik tidak cukup.");
                        continue;
                    }
                    $this->appendStoreGallery((int) $product->id, $additional);
                }

                $fresh = StoreProduct::query()->find((int) $product->id);
                if (! $fresh) {
                    continue;
                }

                $request = $this->makeRequest($mitra, [
                    'name' => trim((string) ($fresh->name ?? 'Produk Mitra')),
                    'description' => $this->safeDescription((string) ($fresh->description ?? '')),
                    'price' => max(
                        (float) ($fresh->price ?? 0),
                        $this->minimumAdminSourcePrice((int) ($fresh->source_admin_product_id ?? 0), (float) ($fresh->price ?? 0))
                    ),
                    'unit' => $this->normalizeUnit((string) ($fresh->unit ?? 'kg')),
                    'stock_qty' => max(20, (int) ($fresh->stock_qty ?? 0)),
                    'is_affiliate_enabled' => 0,
                    'affiliate_commission' => 0,
                ]);

                $this->runAs($mitra, function () use ($request, $fresh) {
                    app(StoreProductController::class)->activateListing($request, $fresh);
                });

                $bucket['activated_products'] = (int) ($bucket['activated_products'] ?? 0) + 1;
            } catch (ValidationException $e) {
                $this->appendError($bucket, "Produk #{$product->id}: " . $e->getMessage());
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Produk #{$product->id}: " . $e->getMessage());
            }
        }
    }

    private function autoHydrateSellerProducts(array $imagePool, array &$bucket): void
    {
        if (! Schema::hasTable('farmer_harvests')) {
            return;
        }

        $missing = DB::table('farmer_harvests')
            ->where('status', 'approved')
            ->where(function ($query) {
                $query->whereNull('image_url')
                    ->orWhere('image_url', '');
            })
            ->orderBy('id')
            ->limit(self::MAX_SELLER_PRODUCT_FIX)
            ->get(['id']);

        foreach ($missing as $row) {
            $image = $this->pickSingleImage($imagePool);
            if ($image === null) {
                break;
            }
            DB::table('farmer_harvests')
                ->where('id', (int) $row->id)
                ->update(['image_url' => $image, 'updated_at' => now()]);
            $bucket['hydrated_products'] = (int) ($bucket['hydrated_products'] ?? 0) + 1;
        }

        foreach ($this->sellerUsers(20) as $seller) {
            if ((int) ($bucket['created_products'] ?? 0) >= self::MAX_SELLER_PRODUCT_FIX) {
                break;
            }
            $count = (int) DB::table('farmer_harvests')
                ->where('farmer_id', (int) $seller->id)
                ->count();
            if ($count > 0) {
                continue;
            }

            DB::table('farmer_harvests')->insert([
                'farmer_id' => (int) $seller->id,
                'name' => collect(['Cabai Segar', 'Tomat Organik', 'Jagung Panen'])->random(),
                'description' => 'Produk otomatis simulasi seller.',
                'price' => random_int(12000, 75000),
                'stock_qty' => random_int(30, 120),
                'harvest_date' => now()->toDateString(),
                'image_url' => $this->pickSingleImage($imagePool),
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $bucket['created_products'] = (int) ($bucket['created_products'] ?? 0) + 1;
        }
    }

    private function autoConsumerCheckout(array &$bucket): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $consumers = User::query()
            ->whereNormalizedRole('consumer')
            ->orderBy('id')
            ->limit(40)
            ->get();

        foreach ($consumers as $consumer) {
            if ((int) ($bucket['created_orders'] ?? 0) >= self::MAX_CONSUMER_CHECKOUT) {
                break;
            }

            try {
                $weather = $this->weatherContext($consumer);
                $hints = $this->recommendationHints((int) $consumer->id);
                $mode = $this->consumerPurchasePolicy->resolveMode($consumer);
                $preferredType = $this->preferredCheckoutType((string) $weather['severity'], (string) ($weather['admin_notice'] ?? ''), $hints);

                $candidate = $this->pickCheckoutCandidate($consumer, $preferredType, $hints)
                    ?? $this->pickCheckoutCandidate($consumer, $preferredType === 'store' ? 'farmer' : 'store', $hints);
                if (! $candidate) {
                    continue;
                }

                $qty = $this->resolveCheckoutQty((int) $candidate['stock_qty'], (string) $weather['severity'], (string) ($weather['admin_notice'] ?? ''));
                if ($qty <= 0) {
                    continue;
                }

                $requiredAmount = round(((float) $candidate['price']) * $qty, 2);
                $paymentMethod = $this->resolvePaymentMethod($consumer, $mode, $requiredAmount);
                if ($paymentMethod === null) {
                    continue;
                }

                $orderIds = $this->checkoutSplitService->checkoutBuyNow(
                    (int) $consumer->id,
                    [
                        'product_type' => (string) $candidate['type'],
                        'product_id' => (int) $candidate['id'],
                        'qty' => $qty,
                    ],
                    $paymentMethod
                );
                $bucket['created_orders'] = (int) ($bucket['created_orders'] ?? 0) + count($orderIds);
            } catch (ValidationException $e) {
                $this->appendError($bucket, "Consumer #{$consumer->id}: " . $e->getMessage());
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Consumer #{$consumer->id}: " . $e->getMessage());
            }
        }
    }

    private function autoMitraOrderWorkflow(array &$bucket): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $orders = DB::table('orders')
            ->where('order_source', 'store_online')
            ->whereIn('order_status', ['pending_payment', 'paid', 'packed'])
            ->orderBy('id')
            ->limit(80)
            ->get(['id', 'seller_id', 'order_status', 'payment_status', 'payment_method', 'payment_proof_url']);

        $mitraMap = User::query()
            ->whereIn('id', $orders->pluck('seller_id')->unique()->values()->all())
            ->get()
            ->keyBy('id');

        foreach ($orders as $order) {
            if ((int) ($bucket['order_actions'] ?? 0) >= self::MAX_MITRA_ORDER_ACTION) {
                break;
            }
            $mitra = $mitraMap->get((int) $order->seller_id);
            if (! $mitra || ! $mitra->isMitra()) {
                continue;
            }

            try {
                $status = strtolower(trim((string) $order->order_status));
                $paymentStatus = strtolower(trim((string) $order->payment_status));
                $paymentMethod = strtolower(trim((string) $order->payment_method));

                if ($status === 'pending_payment' && $paymentStatus === 'unpaid' && $paymentMethod === 'bank_transfer' && trim((string) $order->payment_proof_url) !== '') {
                    $this->mitraOrderWorkflow->markPaid($mitra, (int) $order->id);
                    $bucket['order_actions'] = (int) ($bucket['order_actions'] ?? 0) + 1;
                    continue;
                }
                if ($status === 'paid' && $paymentStatus === 'paid') {
                    $this->mitraOrderWorkflow->markPacked($mitra, (int) $order->id);
                    $bucket['order_actions'] = (int) ($bucket['order_actions'] ?? 0) + 1;
                    continue;
                }
                if ($status === 'packed' && $paymentStatus === 'paid') {
                    $this->mitraOrderWorkflow->markShipped($mitra, (int) $order->id, $this->tracking('MTR', (int) $order->id));
                    $bucket['order_actions'] = (int) ($bucket['order_actions'] ?? 0) + 1;
                }
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Order Mitra #{$order->id}: " . $e->getMessage());
            }
        }
    }

    private function autoSellerOrderWorkflow(array &$bucket): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $orders = DB::table('orders')
            ->where('order_source', 'farmer_p2p')
            ->whereIn('order_status', ['pending_payment', 'paid', 'packed'])
            ->orderBy('id')
            ->limit(80)
            ->get(['id', 'seller_id', 'order_status', 'payment_status']);

        $sellerMap = User::query()
            ->whereIn('id', $orders->pluck('seller_id')->unique()->values()->all())
            ->get()
            ->keyBy('id');

        foreach ($orders as $order) {
            if ((int) ($bucket['order_actions'] ?? 0) >= self::MAX_SELLER_ORDER_ACTION) {
                break;
            }
            $seller = $sellerMap->get((int) $order->seller_id);
            if (! $seller) {
                continue;
            }

            try {
                $status = strtolower(trim((string) $order->order_status));
                $paymentStatus = strtolower(trim((string) $order->payment_status));
                if ($status === 'pending_payment' && $paymentStatus === 'unpaid') {
                    $request = $this->makeRequest($seller, []);
                    $this->runAs($seller, fn () => app(P2PSellerPaymentController::class)->confirmCash($request, (int) $order->id));
                    $bucket['order_actions'] = (int) ($bucket['order_actions'] ?? 0) + 1;
                    continue;
                }
                if ($status === 'paid' && $paymentStatus === 'paid') {
                    $request = $this->makeRequest($seller, []);
                    $this->runAs($seller, fn () => app(SellerOrderStatusController::class)->markPacked($request, (int) $order->id));
                    $bucket['order_actions'] = (int) ($bucket['order_actions'] ?? 0) + 1;
                    continue;
                }
                if ($status === 'packed' && $paymentStatus === 'paid') {
                    $request = $this->makeRequest($seller, ['resi_number' => $this->tracking('SLR', (int) $order->id)]);
                    $this->runAs($seller, fn () => app(SellerOrderStatusController::class)->markShipped($request, (int) $order->id));
                    $bucket['order_actions'] = (int) ($bucket['order_actions'] ?? 0) + 1;
                }
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Order Seller #{$order->id}: " . $e->getMessage());
            }
        }
    }

    private function autoMitraProcurement(array &$bucket): void
    {
        if (! Schema::hasTable('admin_products') || ! Schema::hasTable('admin_orders')) {
            return;
        }

        $mitras = User::query()
            ->whereNormalizedRole('mitra')
            ->orderBy('id')
            ->limit(30)
            ->get();

        foreach ($mitras as $mitra) {
            if ((int) ($bucket['created_procurements'] ?? 0) >= self::MAX_MITRA_PROCUREMENT_CREATE) {
                break;
            }

            try {
                $openCount = (int) DB::table('admin_orders')
                    ->where('mitra_id', (int) $mitra->id)
                    ->whereIn('status', ['pending', 'approved', 'processing', 'shipped'])
                    ->count();
                if ($openCount >= 3) {
                    continue;
                }

                $weather = $this->weatherContext($mitra);
                $product = $this->pickAdminProcurementCandidate($this->recommendationHints((int) $mitra->id));
                if (! $product) {
                    continue;
                }

                $qty = $this->resolveProcurementQty(
                    (int) $product['stock_qty'],
                    (int) $product['min_order_qty'],
                    (string) $weather['severity'],
                    (string) ($weather['admin_notice'] ?? '')
                );
                if ($qty <= 0) {
                    continue;
                }

                $createRequest = $this->makeRequest($mitra, [
                    'items' => [[
                        'admin_product_id' => (int) $product['id'],
                        'qty' => $qty,
                        'selected' => true,
                    ]],
                    'notes' => 'Auto procurement berdasarkan lokasi/cuaca/rekomendasi.',
                ]);

                $createResponse = $this->runAs($mitra, fn () => app(MitraProcurementController::class)->createOrder($createRequest));
                $data = $this->extractData($createResponse);
                $orderId = (int) ($data['admin_order_id'] ?? 0);
                $totalAmount = round((float) ($data['total_amount'] ?? 0), 2);
                if ($orderId <= 0) {
                    $orderId = (int) DB::table('admin_orders')->where('mitra_id', (int) $mitra->id)->orderByDesc('id')->value('id');
                }
                if ($totalAmount <= 0 && $orderId > 0) {
                    $totalAmount = round((float) (DB::table('admin_orders')->where('id', $orderId)->value('total_amount') ?? 0), 2);
                }
                if ($orderId <= 0 || $totalAmount <= 0) {
                    continue;
                }

                $bucket['created_procurements'] = (int) ($bucket['created_procurements'] ?? 0) + 1;

                $paid = false;
                if ($this->ensureWallet($mitra, $totalAmount, "procurement:{$orderId}")) {
                    try {
                        $walletRequest = $this->makeRequest($mitra, [
                            'payment_method' => 'wallet',
                            'paid_amount' => $totalAmount,
                            'payment_note' => 'Auto bayar wallet.',
                        ]);
                        $walletResponse = $this->runAs($mitra, fn () => app(MitraProcurementController::class)->submitPayment($walletRequest, $orderId));
                        $paid = strtolower((string) ($this->extractData($walletResponse)['payment_status'] ?? '')) === 'paid';
                    } catch (\Throwable) {
                        $paid = false;
                    }
                }

                if (! $paid) {
                    $transferRequest = $this->makeRequest($mitra, [
                        'payment_method' => 'bank_transfer',
                        'paid_amount' => $totalAmount,
                        'payment_note' => 'Auto transfer menunggu verifikasi.',
                    ]);
                    $this->runAs($mitra, fn () => app(MitraProcurementController::class)->submitPayment($transferRequest, $orderId));
                }

                $status = strtolower((string) (DB::table('admin_orders')->where('id', $orderId)->value('payment_status') ?? ''));
                if ($status === 'paid') {
                    $bucket['paid_procurements'] = (int) ($bucket['paid_procurements'] ?? 0) + 1;
                }
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Procurement Mitra #{$mitra->id}: " . $e->getMessage());
            }
        }
    }

    private function autoAdminProcurementWorkflow(array &$bucket): void
    {
        if (! Schema::hasTable('admin_orders')) {
            return;
        }

        $admin = User::query()->whereNormalizedRole('admin')->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        if (Schema::hasColumn('admin_orders', 'payment_status')) {
            $pendingPay = DB::table('admin_orders')
                ->where('payment_status', 'pending_verification')
                ->where('status', '<>', 'cancelled')
                ->orderBy('id')
                ->limit(self::MAX_ADMIN_PROCUREMENT_ACTION)
                ->pluck('id');

            foreach ($pendingPay as $id) {
                if ((int) ($bucket['procurement_actions'] ?? 0) >= self::MAX_ADMIN_PROCUREMENT_ACTION) {
                    break;
                }
                try {
                    $request = $this->makeRequest($admin, [
                        'payment_status' => 'paid',
                        'payment_note' => 'Auto verifikasi admin.',
                    ]);
                    $this->runAs($admin, fn () => app(AdminProcurementController::class)->setPaymentStatus($request, (int) $id));
                    $bucket['procurement_actions'] = (int) ($bucket['procurement_actions'] ?? 0) + 1;
                } catch (\Throwable $e) {
                    $this->appendError($bucket, "Admin verify #{$id}: " . $e->getMessage());
                }
            }
        }

        $transitions = [
            ['from' => 'pending', 'to' => 'approved', 'requires_paid' => false],
            ['from' => 'approved', 'to' => 'processing', 'requires_paid' => true],
            ['from' => 'processing', 'to' => 'shipped', 'requires_paid' => true],
        ];

        foreach ($transitions as $transition) {
            $query = DB::table('admin_orders')
                ->where('status', $transition['from']);
            if (($transition['requires_paid'] ?? false) && Schema::hasColumn('admin_orders', 'payment_status')) {
                $query->where('payment_status', 'paid');
            }
            $ids = $query->orderBy('id')->limit(self::MAX_ADMIN_PROCUREMENT_ACTION)->pluck('id');
            foreach ($ids as $id) {
                if ((int) ($bucket['procurement_actions'] ?? 0) >= self::MAX_ADMIN_PROCUREMENT_ACTION) {
                    break 2;
                }
                try {
                    $request = $this->makeRequest($admin, [
                        'status' => (string) $transition['to'],
                        'note' => 'Auto status admin cycle.',
                    ]);
                    $this->runAs($admin, fn () => app(AdminProcurementController::class)->setOrderStatus($request, (int) $id));
                    $bucket['procurement_actions'] = (int) ($bucket['procurement_actions'] ?? 0) + 1;
                } catch (\Throwable $e) {
                    $this->appendError($bucket, "Admin status #{$id}: " . $e->getMessage());
                }
            }
        }
    }

    private function autoAdminWeatherNoticeWorkflow(array &$bucket): void
    {
        if (
            ! Schema::hasTable('admin_weather_notices')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('notifications')
        ) {
            return;
        }

        $admin = User::query()->whereNormalizedRole('admin')->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        if (Schema::hasColumn('admin_weather_notices', 'valid_until')) {
            DB::table('admin_weather_notices')
                ->where('is_active', true)
                ->whereNotNull('valid_until')
                ->where('valid_until', '<', now())
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }

        $manualNotices = DB::table('admin_weather_notices')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('title')
                    ->orWhereRaw('UPPER(title) NOT LIKE ?', [self::AUTO_WEATHER_NOTICE_PREFIX . '%']);
            })
            ->orderByDesc('updated_at')
            ->limit(self::MAX_ADMIN_WEATHER_NOTICE_ACTION)
            ->get(['id', 'scope', 'province_id', 'city_id', 'severity', 'title', 'message', 'valid_until']);

        foreach ($manualNotices as $notice) {
            $sent = $this->dispatchWeatherNoticeNotification([
                'notice_id' => (int) $notice->id,
                'scope' => (string) ($notice->scope ?? 'global'),
                'province_id' => $notice->province_id ? (int) $notice->province_id : null,
                'city_id' => $notice->city_id ? (int) $notice->city_id : null,
                'severity' => (string) ($notice->severity ?? 'unknown'),
                'title' => (string) ($notice->title ?? ''),
                'message' => (string) ($notice->message ?? ''),
                'valid_until' => $notice->valid_until,
            ]);

            if ($sent > 0) {
                $bucket['weather_notice_actions'] = (int) ($bucket['weather_notice_actions'] ?? 0) + 1;
                $bucket['manual_notice_redispatches'] = (int) ($bucket['manual_notice_redispatches'] ?? 0) + 1;
                $bucket['weather_notifications_sent'] = (int) ($bucket['weather_notifications_sent'] ?? 0) + $sent;
            }
        }

        $signal = $this->resolveAutomationWeatherSignal();
        if (! in_array((string) ($signal['severity'] ?? 'unknown'), ['yellow', 'red'], true)) {
            return;
        }

        $noticeResult = $this->upsertAutomationWeatherNotice($admin, $signal);
        if (! $noticeResult) {
            return;
        }

        if ((bool) ($noticeResult['created'] ?? false)) {
            $bucket['auto_weather_notices'] = (int) ($bucket['auto_weather_notices'] ?? 0) + 1;
        }

        $sent = $this->dispatchWeatherNoticeNotification((array) ($noticeResult['notice'] ?? []), ['consumer', 'seller', 'affiliate', 'mitra']);
        if ($sent > 0) {
            $bucket['weather_notice_actions'] = (int) ($bucket['weather_notice_actions'] ?? 0) + 1;
            $bucket['weather_notifications_sent'] = (int) ($bucket['weather_notifications_sent'] ?? 0) + $sent;
        }
    }

    private function resolveAutomationWeatherSignal(): array
    {
        $signal = [
            'severity' => 'unknown',
            'message' => 'Pantau kondisi cuaca operasional.',
            'valid_until' => null,
        ];

        if (! Schema::hasTable('weather_snapshots')) {
            return $signal;
        }

        $query = DB::table('weather_snapshots')
            ->orderByDesc('fetched_at')
            ->limit(30);

        if (Schema::hasColumn('weather_snapshots', 'kind')) {
            $query->where('kind', 'forecast');
        }
        if (Schema::hasColumn('weather_snapshots', 'valid_until')) {
            $query->where('valid_until', '>=', now()->subHours(6));
        }

        $rows = $query->get(['payload', 'valid_until']);

        foreach ($rows as $row) {
            $forecast = $this->decodeData($row->payload ?? null);
            $alert = $this->weatherAlertEngine->evaluateForecast(is_array($forecast) ? $forecast : []);
            $severity = strtolower(trim((string) ($alert['severity'] ?? 'unknown')));

            if ($this->weatherSeverityRank($severity) < 1) {
                continue;
            }

            if ($this->weatherSeverityRank($severity) >= $this->weatherSeverityRank((string) $signal['severity'])) {
                $signal['severity'] = $severity;
                $signal['message'] = trim((string) ($alert['message'] ?? '')) !== ''
                    ? (string) $alert['message']
                    : (string) $signal['message'];
                $signal['valid_until'] = $alert['valid_until'] ?? $row->valid_until;
            }

            if ($severity === 'red') {
                break;
            }
        }

        return $signal;
    }

    private function upsertAutomationWeatherNotice(User $admin, array $signal): ?array
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return null;
        }

        $severity = strtolower(trim((string) ($signal['severity'] ?? 'unknown')));
        if (! in_array($severity, ['yellow', 'red'], true)) {
            return null;
        }

        $title = $severity === 'red'
            ? self::AUTO_WEATHER_NOTICE_PREFIX . ' SIAGA TINGGI'
            : self::AUTO_WEATHER_NOTICE_PREFIX . ' WASPADA';

        $message = trim((string) ($signal['message'] ?? ''));
        if ($message === '') {
            $message = $severity === 'red'
                ? 'Admin menetapkan status siaga tinggi. Mitra diminta prioritaskan pengadaan penting dan sesuaikan jadwal kirim.'
                : 'Admin menetapkan status waspada cuaca. Mitra diminta cek stok, pengadaan, dan kesiapan pengiriman.';
        }
        $message .= ' [Auto Admin Cycle]';

        $validUntil = $signal['valid_until'] ?? null;
        try {
            $validUntil = Carbon::parse((string) $validUntil)->toDateTimeString();
        } catch (\Throwable) {
            $validUntil = now()->addHours($severity === 'red' ? 6 : 8)->toDateTimeString();
        }

        $existing = DB::table('admin_weather_notices')
            ->where('scope', 'global')
            ->where('is_active', true)
            ->where('severity', $severity)
            ->where('title', $title)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now()->addMinutes(30));
            })
            ->orderByDesc('id')
            ->first(['id']);

        $created = false;
        $noticeId = 0;

        if ($existing) {
            $noticeId = (int) $existing->id;
            DB::table('admin_weather_notices')
                ->where('id', $noticeId)
                ->update([
                    'message' => $message,
                    'valid_until' => $validUntil,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        } else {
            $created = true;
            $noticeId = (int) DB::table('admin_weather_notices')->insertGetId([
                'scope' => 'global',
                'province_id' => null,
                'city_id' => null,
                'district_id' => null,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'valid_until' => $validUntil,
                'is_active' => true,
                'created_by' => (int) $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'created' => $created,
            'notice' => [
                'notice_id' => $noticeId,
                'scope' => 'global',
                'province_id' => null,
                'city_id' => null,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'valid_until' => $validUntil,
            ],
        ];
    }

    /**
     * @param  array<int, string>|null  $audienceRoles
     */
    private function dispatchWeatherNoticeNotification(array $noticePayload, ?array $audienceRoles = null): int
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasTable('users')) {
            return 0;
        }

        $scope = (string) ($noticePayload['scope'] ?? 'global');
        $provinceId = isset($noticePayload['province_id']) ? (int) ($noticePayload['province_id'] ?? 0) : null;
        $cityId = isset($noticePayload['city_id']) ? (int) ($noticePayload['city_id'] ?? 0) : null;
        $severity = strtolower(trim((string) ($noticePayload['severity'] ?? 'unknown')));
        $title = trim((string) ($noticePayload['title'] ?? ''));
        $message = trim((string) ($noticePayload['message'] ?? ''));
        $validUntil = $noticePayload['valid_until'] ?? null;
        $noticeId = isset($noticePayload['notice_id']) ? (int) ($noticePayload['notice_id'] ?? 0) : null;
        if ($message === '') {
            return 0;
        }

        $roles = $this->normalizeAudienceRoles($audienceRoles ?? (array) ($noticePayload['audience_roles'] ?? []));
        $dispatchKey = $this->buildWeatherDispatchKey($noticePayload, $roles);

        $query = User::query();
        if (! empty($roles)) {
            $query->whereInNormalizedRoles($roles);
        } else {
            $query->whereRaw('LOWER(TRIM(role)) <> ?', [User::normalizeRoleValue('admin')]);
        }

        if ($scope === 'city' && $cityId) {
            $query->where('city_id', $cityId);
        } elseif ($scope === 'province' && $provinceId) {
            $query->where('province_id', $provinceId);
        }

        $statusTitle = $title !== '' ? $title : $this->defaultWeatherNoticeTitle($severity);
        $targetLabel = $this->resolveWeatherNoticeTargetLabel($scope, $provinceId, $cityId);
        $actionUrl = $this->landingWeatherActionUrl();

        $validUntilLabel = null;
        if (! empty($validUntil)) {
            try {
                $validUntilLabel = Carbon::parse((string) $validUntil)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $validUntilLabel = null;
            }
        }

        $sentCount = 0;
        $query->orderBy('id')->chunkById(200, function ($users) use (
            &$sentCount,
            $severity,
            $statusTitle,
            $message,
            $actionUrl,
            $scope,
            $targetLabel,
            $validUntilLabel,
            $noticeId,
            $dispatchKey
        ) {
            $userIds = $users->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $alreadyDispatched = DB::table('notifications')
                ->where('type', AdminWeatherNoticeNotification::class)
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $userIds)
                ->where('data', 'like', '%"dispatch_key":"' . $dispatchKey . '"%')
                ->pluck('notifiable_id')
                ->map(fn ($id) => (int) $id)
                ->flip();

            foreach ($users as $user) {
                if (isset($alreadyDispatched[(int) $user->id])) {
                    continue;
                }

                $user->notify(new AdminWeatherNoticeNotification(
                    severity: $severity,
                    title: $statusTitle,
                    message: $message,
                    actionUrl: $actionUrl,
                    actionLabel: 'Buka Cuaca & Lokasi',
                    scope: $scope,
                    targetLabel: $targetLabel,
                    validUntil: $validUntilLabel,
                    noticeId: $noticeId,
                    dispatchKey: $dispatchKey
                ));
                $sentCount++;
            }
        });

        return $sentCount;
    }

    private function buildWeatherDispatchKey(array $noticePayload, array $audienceRoles = []): string
    {
        $validUntil = $noticePayload['valid_until'] ?? null;
        $normalizedValidUntil = null;
        if (! empty($validUntil)) {
            try {
                $normalizedValidUntil = Carbon::parse((string) $validUntil)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $normalizedValidUntil = (string) $validUntil;
            }
        }

        return sha1(json_encode([
            'notice_id' => isset($noticePayload['notice_id']) ? (int) ($noticePayload['notice_id'] ?? 0) : 0,
            'scope' => (string) ($noticePayload['scope'] ?? 'global'),
            'province_id' => isset($noticePayload['province_id']) ? (int) ($noticePayload['province_id'] ?? 0) : null,
            'city_id' => isset($noticePayload['city_id']) ? (int) ($noticePayload['city_id'] ?? 0) : null,
            'severity' => strtolower(trim((string) ($noticePayload['severity'] ?? 'unknown'))),
            'title' => trim((string) ($noticePayload['title'] ?? '')),
            'message' => trim((string) ($noticePayload['message'] ?? '')),
            'valid_until' => $normalizedValidUntil,
            'audience_roles' => $this->normalizeAudienceRoles($audienceRoles),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function normalizeAudienceRoles(array $roles): array
    {
        $allowed = ['consumer', 'seller', 'farmer_seller', 'affiliate', 'mitra', 'admin'];

        return collect($roles)
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter(fn ($role) => in_array($role, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function defaultWeatherNoticeTitle(string $severity): string
    {
        return match (strtolower($severity)) {
            'red' => 'Siaga Tinggi Cuaca',
            'yellow' => 'Waspada Cuaca',
            'green' => 'Info Cuaca Stabil',
            default => 'Info Cuaca Operasional',
        };
    }

    private function resolveWeatherNoticeTargetLabel(string $scope, ?int $provinceId, ?int $cityId): string
    {
        if ($scope === 'city' && $cityId && Schema::hasTable('cities')) {
            $city = DB::table('cities')
                ->leftJoin('provinces', 'provinces.id', '=', 'cities.province_id')
                ->where('cities.id', $cityId)
                ->first([
                    'cities.name as city_name',
                    'cities.type as city_type',
                    'provinces.name as province_name',
                ]);

            if ($city) {
                $cityLabel = trim(((string) ($city->city_type ?? '')) . ' ' . ((string) ($city->city_name ?? '')));
                $provinceLabel = trim((string) ($city->province_name ?? ''));
                if ($cityLabel !== '' && $provinceLabel !== '') {
                    return $cityLabel . ', ' . $provinceLabel;
                }
                if ($cityLabel !== '') {
                    return $cityLabel;
                }
            }
        }

        if ($scope === 'province' && $provinceId && Schema::hasTable('provinces')) {
            $provinceName = DB::table('provinces')
                ->where('id', $provinceId)
                ->value('name');
            if (filled($provinceName)) {
                return 'Provinsi ' . $provinceName;
            }
        }

        return 'Semua lokasi';
    }

    private function landingWeatherActionUrl(): string
    {
        try {
            return route('landing') . '#fitur-cuaca';
        } catch (\Throwable) {
            return '/';
        }
    }

    private function weatherSeverityRank(string $severity): int
    {
        return match (strtolower(trim($severity))) {
            'red' => 3,
            'yellow' => 2,
            'green' => 1,
            default => 0,
        };
    }

    private function autoMitraProcurementConfirm(array &$bucket): void
    {
        if (! Schema::hasTable('admin_orders')) {
            return;
        }

        $query = DB::table('admin_orders')->where('status', 'shipped');
        if (Schema::hasColumn('admin_orders', 'payment_status')) {
            $query->where('payment_status', 'paid');
        }
        $orders = $query
            ->orderBy('id')
            ->limit(self::MAX_MITRA_PROCUREMENT_CONFIRM)
            ->get(['id', 'mitra_id']);

        $mitraMap = User::query()
            ->whereIn('id', $orders->pluck('mitra_id')->unique()->values()->all())
            ->get()
            ->keyBy('id');

        foreach ($orders as $order) {
            $mitra = $mitraMap->get((int) $order->mitra_id);
            if (! $mitra || ! $mitra->isMitra()) {
                continue;
            }
            try {
                $request = $this->makeRequest($mitra, ['note' => 'Auto confirm received procurement.']);
                $this->runAs($mitra, fn () => app(MitraProcurementController::class)->confirmReceived($request, (int) $order->id));
                $bucket['confirmed_procurements'] = (int) ($bucket['confirmed_procurements'] ?? 0) + 1;
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Mitra confirm #{$order->id}: " . $e->getMessage());
            }
        }
    }

    private function autoConsumerConfirmReceived(array &$bucket): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $orders = DB::table('orders')
            ->join('users as buyer', 'buyer.id', '=', 'orders.buyer_id')
            ->whereRaw('LOWER(TRIM(buyer.role)) = ?', [User::normalizeRoleValue('consumer')])
            ->where('orders.order_status', 'shipped')
            ->where('orders.payment_status', 'paid')
            ->orderBy('orders.id')
            ->limit(self::MAX_CONSUMER_CONFIRM)
            ->get(['orders.id', 'orders.buyer_id']);

        $buyers = User::query()
            ->whereIn('id', $orders->pluck('buyer_id')->unique()->values()->all())
            ->get()
            ->keyBy('id');

        foreach ($orders as $order) {
            $buyer = $buyers->get((int) $order->buyer_id);
            if (! $buyer || ! $buyer->isConsumer()) {
                continue;
            }
            try {
                $this->confirmOrderAsBuyer($buyer, (int) $order->id);
                $bucket['confirmed_orders'] = (int) ($bucket['confirmed_orders'] ?? 0) + 1;
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Consumer confirm #{$order->id}: " . $e->getMessage());
            }
        }
    }

    private function confirmOrderAsBuyer(User $buyer, int $orderId): void
    {
        DB::transaction(function () use ($buyer, $orderId) {
            $order = DB::table('orders')
                ->where('id', $orderId)
                ->where('buyer_id', (int) $buyer->id)
                ->lockForUpdate()
                ->first(['id', 'order_status', 'payment_status']);
            if (! $order || (string) $order->payment_status !== 'paid' || (string) $order->order_status !== 'shipped') {
                return;
            }

            $this->orderStatusTransition->assertTransition((string) $order->order_status, 'completed');
            $payload = [
                'order_status' => 'completed',
                'shipping_status' => 'delivered',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('orders', 'completed_at')) {
                $payload['completed_at'] = now();
            }
            DB::table('orders')->where('id', $orderId)->update($payload);
            $this->orderShipmentService->markDelivered($orderId);
            $this->orderStatusHistoryLogger->log(
                orderId: $orderId,
                fromStatus: 'shipped',
                toStatus: 'completed',
                actorUserId: (int) $buyer->id,
                actorRole: (string) $buyer->role,
                note: 'Auto confirm received role cycle.'
            );
            $this->settlementService->settleIfEligible($orderId);
        });
    }

    private function autoMitraWithdrawRequests(array &$bucket): void
    {
        $this->autoSubmitWithdrawForUsers(
            users: $this->mitraUsers(30),
            limit: self::MAX_MITRA_WITHDRAW_REQUEST,
            scope: 'mitra',
            bucket: $bucket
        );
    }

    private function autoSellerWithdrawRequests(array &$bucket): void
    {
        $this->autoSubmitWithdrawForUsers(
            users: $this->sellerUsers(30),
            limit: self::MAX_SELLER_WITHDRAW_REQUEST,
            scope: 'seller',
            bucket: $bucket
        );
    }

    private function autoAffiliateWithdrawRequests(array &$bucket): void
    {
        $this->autoSubmitWithdrawForUsers(
            users: $this->affiliateUsers(30),
            limit: self::MAX_AFFILIATE_WITHDRAW_REQUEST,
            scope: 'affiliate',
            bucket: $bucket,
            counterKey: 'affiliate_withdraw_requests'
        );
    }

    private function autoSubmitWithdrawForUsers(
        Collection $users,
        int $limit,
        string $scope,
        array &$bucket,
        string $counterKey = 'withdraw_requests'
    ): void {
        if ($limit <= 0 || $users->isEmpty() || ! Schema::hasTable('withdraw_requests')) {
            return;
        }

        $minWithdraw = $this->resolveMinWithdrawAmount();
        foreach ($users as $user) {
            if ((int) ($bucket[$counterKey] ?? 0) >= $limit) {
                break;
            }

            $userId = (int) ($user->id ?? 0);
            if ($userId <= 0 || $this->hasOpenWithdrawRequest($userId)) {
                continue;
            }

            $bankSnapshot = $this->withdrawBankAccounts->snapshot($userId);
            if (! (bool) ($bankSnapshot['complete'] ?? false)) {
                continue;
            }

            $amount = $this->suggestWithdrawAmount($userId, $minWithdraw);
            if ($amount <= 0) {
                continue;
            }

            try {
                $request = $this->makeRequest($user, [
                    'amount' => $amount,
                ]);
                $response = $this->runAs($user, fn () => app(WithdrawController::class)->requestWithdraw($request));
                $data = $this->extractData($response);
                if ((bool) ($data['idempotency_hit'] ?? false)) {
                    $bucket['withdraw_idempotency_hits'] = (int) ($bucket['withdraw_idempotency_hits'] ?? 0) + 1;
                    continue;
                }
                if ((int) ($data['withdraw_request_id'] ?? 0) > 0) {
                    $bucket[$counterKey] = (int) ($bucket[$counterKey] ?? 0) + 1;
                }
            } catch (ValidationException $e) {
                $this->appendError($bucket, strtoupper($scope) . " withdraw #{$userId}: " . $e->getMessage());
            } catch (\Throwable $e) {
                $this->appendError($bucket, strtoupper($scope) . " withdraw #{$userId}: " . $e->getMessage());
            }
        }
    }

    private function autoAdminWithdrawWorkflow(array &$bucket): void
    {
        if (! Schema::hasTable('withdraw_requests')) {
            return;
        }

        $admin = User::query()->whereNormalizedRole('admin')->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        $pendingIds = DB::table('withdraw_requests')
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(self::MAX_ADMIN_WITHDRAW_ACTION)
            ->pluck('id');

        foreach ($pendingIds as $withdrawId) {
            if ((int) ($bucket['withdraw_approved'] ?? 0) >= self::MAX_ADMIN_WITHDRAW_ACTION) {
                break;
            }

            try {
                $request = $this->makeRequest($admin, []);
                $this->runAs($admin, fn () => app(AdminWithdrawController::class)->approve($request, (int) $withdrawId));
                $bucket['withdraw_approved'] = (int) ($bucket['withdraw_approved'] ?? 0) + 1;
                $bucket['withdraw_actions'] = (int) ($bucket['withdraw_actions'] ?? 0) + 1;
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Admin approve withdraw #{$withdrawId}: " . $e->getMessage());
            }
        }

        if (! (bool) config('finance.demo_mode', true)) {
            return;
        }

        $approvedRows = DB::table('withdraw_requests')
            ->where('status', 'approved')
            ->orderBy('id')
            ->limit(self::MAX_ADMIN_WITHDRAW_ACTION)
            ->get(['id', 'amount']);

        foreach ($approvedRows as $row) {
            if ((int) ($bucket['withdraw_paid'] ?? 0) >= self::MAX_ADMIN_WITHDRAW_ACTION) {
                break;
            }

            $withdrawId = (int) ($row->id ?? 0);
            $amount = round((float) ($row->amount ?? 0), 2);
            if ($withdrawId <= 0 || $amount <= 0) {
                continue;
            }

            if (! $this->ensureWallet($admin, $amount, "withdraw:paid:{$withdrawId}")) {
                $this->appendError($bucket, "Admin payout withdraw #{$withdrawId}: saldo admin tidak cukup.");
                continue;
            }

            try {
                $request = $this->makeRequest($admin, [
                    'transfer_reference' => "AUTO-WD-{$this->cycleKey}-{$withdrawId}",
                ]);
                $this->runAs($admin, fn () => app(AdminWithdrawController::class)->markPaid($request, $withdrawId));
                $bucket['withdraw_paid'] = (int) ($bucket['withdraw_paid'] ?? 0) + 1;
                $bucket['withdraw_actions'] = (int) ($bucket['withdraw_actions'] ?? 0) + 1;
            } catch (\Throwable $e) {
                $this->appendError($bucket, "Admin mark paid withdraw #{$withdrawId}: " . $e->getMessage());
            }
        }
    }

    private function resolveMinWithdrawAmount(): float
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('admin_profiles')) {
            return 1.0;
        }

        $adminId = (int) (User::query()
            ->whereNormalizedRole('admin')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($adminId <= 0) {
            return 1.0;
        }

        $configured = DB::table('admin_profiles')
            ->where('user_id', $adminId)
            ->value('min_withdraw_amount');

        return max(1.0, round((float) ($configured ?? 1), 2));
    }

    private function hasOpenWithdrawRequest(int $userId): bool
    {
        if ($userId <= 0 || ! Schema::hasTable('withdraw_requests')) {
            return false;
        }

        return DB::table('withdraw_requests')
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
    }

    private function suggestWithdrawAmount(int $userId, float $minWithdraw): float
    {
        $available = $this->walletAvailable($userId);
        $minWithdraw = max(1.0, round($minWithdraw, 2));
        if ($available < $minWithdraw) {
            return 0.0;
        }

        $minInt = max(1, (int) ceil($minWithdraw));
        $availableInt = (int) floor($available);
        if ($availableInt < $minInt) {
            return 0.0;
        }

        $upperInt = min($availableInt, max($minInt, (int) floor($available * 0.55)));
        if ($upperInt < $minInt) {
            return 0.0;
        }

        return round((float) random_int($minInt, $upperInt), 2);
    }

    private function resolvePaymentMethod(User $consumer, string $mode, float $requiredAmount): ?string
    {
        $options = collect($this->consumerPurchasePolicy->checkoutOptions($consumer));
        if ($options->isEmpty()) {
            return null;
        }

        $wallet = $options->pluck('method')->map(fn ($m) => (string) $m)->first(fn ($m) => $this->paymentMethods->kind($m) === 'wallet');
        $bank = $options->pluck('method')->map(fn ($m) => (string) $m)->first(fn ($m) => $this->paymentMethods->kind($m) === 'bank');

        if ($mode === 'farmer_seller') {
            return $bank ?: $wallet;
        }

        if ($wallet && $this->ensureWallet($consumer, $requiredAmount, 'checkout')) {
            return $wallet;
        }

        return $bank ?: $wallet;
    }

    private function ensureWallet(User $user, float $requiredAmount, string $scope): bool
    {
        $requiredAmount = round($requiredAmount, 2);
        if ($requiredAmount <= 0) {
            return true;
        }

        $available = $this->walletAvailable((int) $user->id);
        if ($available >= $requiredAmount) {
            return true;
        }
        if (! (bool) config('finance.demo_mode', true)) {
            return false;
        }

        $topupAmount = max(50000.0, round(($requiredAmount - $available) + 15000, 2));
        try {
            $this->demoWalletTopupService->topup($user, $topupAmount, "automation:{$this->cycleKey}:{$scope}:{$user->id}");
        } catch (\Throwable) {
            return false;
        }

        return $this->walletAvailable((int) $user->id) >= $requiredAmount;
    }

    private function walletAvailable(int $userId): float
    {
        if ($userId <= 0 || ! Schema::hasTable('wallet_transactions')) {
            return 0.0;
        }

        $balance = (float) DB::table('wallet_transactions')->where('wallet_id', $userId)->sum('amount');
        $reserved = 0.0;
        if (Schema::hasTable('withdraw_requests')) {
            $reserved = (float) DB::table('withdraw_requests')
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved'])
                ->sum('amount');
        }

        return round(max(0.0, $balance - $reserved), 2);
    }

    private function weatherContext(User $user): array
    {
        $userId = (int) $user->id;
        if (isset($this->weatherCache[$userId])) {
            return $this->weatherCache[$userId];
        }

        $context = [
            'severity' => 'unknown',
            'location_label' => 'Lokasi akun',
            'admin_notice' => $this->activeNoticeSeverity($user),
        ];

        $loc = $this->locationResolver->forUser($user);
        $context['location_label'] = trim((string) ($loc['label'] ?? 'Lokasi akun')) ?: 'Lokasi akun';

        try {
            $weatherService = $this->resolveWeatherService();
            if ($weatherService) {
                $forecast = $weatherService->forecast(
                    (string) ($loc['type'] ?? 'custom'),
                    (int) ($loc['id'] ?? 0),
                    (float) ($loc['lat'] ?? 0),
                    (float) ($loc['lng'] ?? 0)
                );
                $alert = $this->weatherAlertEngine->evaluateForecast(is_array($forecast) ? $forecast : []);
                $context['severity'] = strtolower(trim((string) ($alert['severity'] ?? 'unknown')));
            }
        } catch (\Throwable) {
        }

        $this->weatherCache[$userId] = $context;
        return $context;
    }

    private function resolveWeatherService(): ?WeatherService
    {
        if ($this->weatherResolved) {
            return $this->weatherService;
        }

        $this->weatherResolved = true;

        if (app()->environment('testing') && ! filled(config('weather.openweather.key'))) {
            $this->weatherService = null;
            return null;
        }

        try {
            $this->weatherService = app(WeatherService::class);
        } catch (\Throwable) {
            $this->weatherService = null;
        }

        return $this->weatherService;
    }

    private function activeNoticeSeverity(User $user): ?string
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return null;
        }

        $rows = DB::table('admin_weather_notices')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->where(function ($query) use ($user) {
                $query->where('scope', 'global');
                if (! empty($user->province_id)) {
                    $query->orWhere(function ($sub) use ($user) {
                        $sub->where('scope', 'province')
                            ->where('province_id', (int) $user->province_id);
                    });
                }
                if (! empty($user->city_id)) {
                    $query->orWhere(function ($sub) use ($user) {
                        $sub->where('scope', 'city')
                            ->where('city_id', (int) $user->city_id);
                    });
                }
            })
            ->pluck('severity')
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->filter(fn ($v) => in_array($v, ['green', 'yellow', 'red'], true))
            ->values();

        if ($rows->contains('red')) {
            return 'red';
        }
        if ($rows->contains('yellow')) {
            return 'yellow';
        }

        return $rows->contains('green') ? 'green' : null;
    }

    private function recommendationHints(int $userId): array
    {
        if (isset($this->recommendationCache[$userId])) {
            return $this->recommendationCache[$userId];
        }
        if (! Schema::hasTable('notifications')) {
            $this->recommendationCache[$userId] = [];
            return [];
        }

        $row = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->where(function ($query) {
                $query->where('type', BehaviorRecommendationNotification::class)
                    ->orWhere('data', 'like', '%"category":"behavior_recommendation"%');
            })
            ->orderByDesc('created_at')
            ->first(['data']);

        if (! $row) {
            $this->recommendationCache[$userId] = [];
            return [];
        }

        $decoded = $this->decodeData($row->data ?? null);
        $text = strtolower(trim(
            ((string) ($decoded['title'] ?? ''))
            . ' '
            . ((string) ($decoded['message'] ?? ''))
            . ' '
            . ((string) ($decoded['rule_key'] ?? ''))
        ));

        $dictionary = ['pupuk', 'pestisida', 'bibit', 'benih', 'beras', 'jagung', 'cabai', 'tomat', 'sayur', 'organik'];
        $hints = collect($dictionary)
            ->filter(fn ($keyword) => str_contains($text, $keyword))
            ->values()
            ->all();

        $this->recommendationCache[$userId] = $hints;
        return $hints;
    }

    private function decodeData(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_object($raw)) {
            return (array) $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function pickCheckoutCandidate(User $consumer, string $type, array $hints): ?array
    {
        return $type === 'farmer'
            ? $this->pickFarmer($consumer, $hints)
            : $this->pickStore($consumer, $hints);
    }

    private function pickStore(User $consumer, array $hints): ?array
    {
        if (! Schema::hasTable('store_products')) {
            return null;
        }

        $query = DB::table('store_products')
            ->where('stock_qty', '>', 0)
            ->where('mitra_id', '<>', (int) $consumer->id);

        if (Schema::hasColumn('store_products', 'is_active')) {
            $query->where('is_active', true);
        }

        $this->applyKeywords($query, $hints, ['name', 'description']);
        $row = $query->orderByRaw($this->randomExpr())->first(['id', 'name', 'price', 'stock_qty']);

        if (! $row) {
            $row = DB::table('store_products')
                ->where('stock_qty', '>', 0)
                ->when(Schema::hasColumn('store_products', 'is_active'), fn ($builder) => $builder->where('is_active', true))
                ->where('mitra_id', '<>', (int) $consumer->id)
                ->orderByRaw($this->randomExpr())
                ->first(['id', 'name', 'price', 'stock_qty']);
        }

        return $row
            ? ['type' => 'store', 'id' => (int) $row->id, 'price' => (float) $row->price, 'stock_qty' => (int) $row->stock_qty]
            : null;
    }

    private function pickFarmer(User $consumer, array $hints): ?array
    {
        if (! Schema::hasTable('farmer_harvests')) {
            return null;
        }

        $query = DB::table('farmer_harvests')
            ->where('stock_qty', '>', 0)
            ->where('status', 'approved')
            ->where('farmer_id', '<>', (int) $consumer->id);
        $this->applyKeywords($query, $hints, ['name', 'description']);
        $row = $query->orderByRaw($this->randomExpr())->first(['id', 'name', 'price', 'stock_qty']);

        if (! $row) {
            $row = DB::table('farmer_harvests')
                ->where('stock_qty', '>', 0)
                ->where('status', 'approved')
                ->where('farmer_id', '<>', (int) $consumer->id)
                ->orderByRaw($this->randomExpr())
                ->first(['id', 'name', 'price', 'stock_qty']);
        }

        return $row
            ? ['type' => 'farmer', 'id' => (int) $row->id, 'price' => (float) $row->price, 'stock_qty' => (int) $row->stock_qty]
            : null;
    }

    private function pickAdminProcurementCandidate(array $hints): ?array
    {
        $query = DB::table('admin_products')
            ->where('is_active', true)
            ->whereColumn('stock_qty', '>=', 'min_order_qty');

        $this->applyKeywords($query, $hints, ['name', 'description']);
        $row = $query->orderByRaw($this->randomExpr())->first(['id', 'price', 'min_order_qty', 'stock_qty']);

        if (! $row) {
            $row = DB::table('admin_products')
                ->where('is_active', true)
                ->whereColumn('stock_qty', '>=', 'min_order_qty')
                ->orderByRaw($this->randomExpr())
                ->first(['id', 'price', 'min_order_qty', 'stock_qty']);
        }

        return $row
            ? ['id' => (int) $row->id, 'price' => (float) $row->price, 'min_order_qty' => (int) $row->min_order_qty, 'stock_qty' => (int) $row->stock_qty]
            : null;
    }

    private function preferredCheckoutType(string $severity, string $notice, array $hints): string
    {
        $hintText = strtolower(implode(' ', $hints));
        if (str_contains($hintText, 'pupuk') || str_contains($hintText, 'pestisida') || strtolower($notice) === 'red') {
            return 'store';
        }

        return strtolower($severity) === 'green' ? 'farmer' : 'store';
    }

    private function resolveCheckoutQty(int $stockQty, string $severity, string $notice): int
    {
        if ($stockQty <= 0) {
            return 0;
        }

        $maxQty = match (strtolower($severity)) {
            'red' => 2,
            'yellow' => 3,
            default => 4,
        };

        if (strtolower($notice) === 'red') {
            $maxQty = 1;
        }

        return random_int(1, max(1, min($stockQty, $maxQty)));
    }

    private function resolveProcurementQty(int $stockQty, int $minOrderQty, string $severity, string $notice): int
    {
        $stockQty = max(0, $stockQty);
        $minOrderQty = max(1, $minOrderQty);
        if ($stockQty < $minOrderQty) {
            return 0;
        }

        $multiplier = match (strtolower($severity)) {
            'red' => 1,
            'yellow' => 2,
            default => 3,
        };
        if (in_array(strtolower($notice), ['yellow', 'red'], true)) {
            $multiplier++;
        }

        return min($stockQty, max($minOrderQty, $minOrderQty * $multiplier));
    }

    private function safeDescription(string $description): string
    {
        $description = trim($description);
        return mb_strlen($description) >= 10
            ? $description
            : 'Produk pengadaan mitra siap dipasarkan untuk simulasi otomatis.';
    }

    private function minimumAdminSourcePrice(int $sourceAdminProductId, float $fallback): float
    {
        if ($sourceAdminProductId <= 0 || ! Schema::hasTable('admin_products')) {
            return max(0, $fallback);
        }
        $sourcePrice = DB::table('admin_products')
            ->where('id', $sourceAdminProductId)
            ->value('price');

        return $sourcePrice !== null ? max(0, (float) $sourcePrice + 1000.0) : max(0, $fallback);
    }

    private function normalizeUnit(string $unit): string
    {
        $unit = strtolower(trim($unit));
        if ($unit === 'lt') {
            $unit = 'liter';
        }

        return in_array($unit, ['kg', 'gram', 'liter', 'ml', 'pcs', 'pack', 'ikat'], true) ? $unit : 'kg';
    }

    private function mitraUsers(int $limit): Collection
    {
        return User::query()
            ->whereNormalizedRole('mitra')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    private function affiliateUsers(int $limit): Collection
    {
        if (! Schema::hasTable('consumer_profiles')) {
            return collect();
        }

        return User::query()
            ->whereNormalizedRole('consumer')
            ->whereExists(function ($profileQuery) {
                $profileQuery->selectRaw('1')
                    ->from('consumer_profiles')
                    ->whereColumn('consumer_profiles.user_id', 'users.id')
                    ->where('consumer_profiles.mode', 'affiliate')
                    ->where('consumer_profiles.mode_status', 'approved');
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    private function sellerUsers(int $limit): Collection
    {
        return User::query()
            ->where(function ($query) {
                $query->whereInNormalizedRoles(['seller', 'farmer_seller']);
                if (Schema::hasTable('consumer_profiles')) {
                    $query->orWhere(function ($consumerQuery) {
                        $consumerQuery->whereNormalizedRole('consumer')
                            ->whereExists(function ($profileQuery) {
                                $profileQuery->selectRaw('1')
                                    ->from('consumer_profiles')
                                    ->whereColumn('consumer_profiles.user_id', 'users.id')
                                    ->where('consumer_profiles.mode', 'farmer_seller')
                                    ->where('consumer_profiles.mode_status', 'approved');
                            });
                    });
                }
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    private function loadImagePool(): array
    {
        if (! empty($this->imagePoolCache)) {
            return $this->imagePoolCache;
        }

        $pool = collect();
        if (Schema::hasTable('store_products') && Schema::hasColumn('store_products', 'image_url')) {
            $pool = $pool->merge(DB::table('store_products')->whereNotNull('image_url')->pluck('image_url')->all());
        }
        if (Schema::hasTable('store_product_images')) {
            $pool = $pool->merge(DB::table('store_product_images')->whereNotNull('image_url')->pluck('image_url')->all());
        }
        if (Schema::hasTable('farmer_harvests') && Schema::hasColumn('farmer_harvests', 'image_url')) {
            $pool = $pool->merge(DB::table('farmer_harvests')->whereNotNull('image_url')->pluck('image_url')->all());
        }
        if (Schema::hasTable('marketplace_announcements') && Schema::hasColumn('marketplace_announcements', 'image_url')) {
            $pool = $pool->merge(DB::table('marketplace_announcements')->whereNotNull('image_url')->pluck('image_url')->all());
        }

        $this->imagePoolCache = $pool
            ->map(fn ($path) => trim((string) $path))
            ->filter(fn ($path) => $path !== '')
            ->unique()
            ->values()
            ->all();

        return $this->imagePoolCache;
    }

    private function pickSingleImage(array $pool): ?string
    {
        return empty($pool) ? null : (string) collect($pool)->random();
    }

    private function pickRandomImages(array $pool, int $limit, array $exclude = []): array
    {
        if ($limit <= 0 || empty($pool)) {
            return [];
        }
        $excluded = collect($exclude)->map(fn ($v) => trim((string) $v))->filter()->flip();
        return collect($pool)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '' && ! isset($excluded[$v]))
            ->unique()
            ->shuffle()
            ->take($limit)
            ->values()
            ->all();
    }

    private function storeProductGallery(int $productId, string $primary): array
    {
        $paths = [];
        $primary = trim($primary);
        if ($primary !== '') {
            $paths[] = $primary;
        }

        if (Schema::hasTable('store_product_images')) {
            $gallery = DB::table('store_product_images')
                ->where('store_product_id', $productId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('image_url')
                ->map(fn ($path) => trim((string) $path))
                ->filter()
                ->values()
                ->all();
            $paths = array_merge($paths, $gallery);
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function appendStoreGallery(int $productId, array $paths): void
    {
        if (! Schema::hasTable('store_product_images') || empty($paths)) {
            return;
        }

        $existing = DB::table('store_product_images')
            ->where('store_product_id', $productId)
            ->pluck('image_url')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->flip();
        $maxSort = (int) (DB::table('store_product_images')->where('store_product_id', $productId)->max('sort_order') ?? 0);

        $rows = [];
        foreach ($paths as $path) {
            $path = trim((string) $path);
            if ($path === '' || isset($existing[$path])) {
                continue;
            }
            $maxSort++;
            $rows[] = [
                'store_product_id' => $productId,
                'image_url' => $path,
                'sort_order' => $maxSort,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($rows) >= self::MAX_MITRA_GALLERY) {
                break;
            }
        }
        if (! empty($rows)) {
            DB::table('store_product_images')->insert($rows);
        }
    }

    private function applyKeywords($query, array $keywords, array $columns): void
    {
        $tokens = collect($keywords)
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->filter()
            ->unique()
            ->values();
        if ($tokens->isEmpty() || empty($columns)) {
            return;
        }

        $query->where(function ($outer) use ($tokens, $columns) {
            foreach ($tokens as $token) {
                $outer->orWhere(function ($tokenQuery) use ($token, $columns) {
                    foreach ($columns as $index => $column) {
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $tokenQuery->{$method}('LOWER(COALESCE(' . $column . ", '')) LIKE ?", ['%' . $token . '%']);
                    }
                });
            }
        });
    }

    private function randomExpr(): string
    {
        $driver = strtolower((string) DB::getDriverName());
        return in_array($driver, ['pgsql', 'sqlite'], true) ? 'RANDOM()' : 'RAND()';
    }

    private function makeRequest(User $user, array $payload): Request
    {
        $request = Request::create('/automation/role-cycle', 'POST', $payload, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ]);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function runAs(User $user, callable $callback): mixed
    {
        $guard = auth();
        $previousUser = $guard->user();

        $guard->setUser($user);

        try {
            return $callback();
        } finally {
            $guard->setUser($previousUser);
        }
    }

    private function extractData(mixed $response): array
    {
        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            $data = is_array($payload) ? ($payload['data'] ?? []) : [];
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function tracking(string $prefix, int $orderId): string
    {
        return strtoupper($prefix) . '-' . now()->format('YmdHis') . '-' . $orderId;
    }

    private function runSafely(array &$bucket, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            $this->appendError($bucket, $e->getMessage());
        }
    }

    private function appendError(array &$bucket, string $message): void
    {
        $bucket['errors'] = collect((array) ($bucket['errors'] ?? []))
            ->push($message)
            ->take(-20)
            ->values()
            ->all();
    }

    private function initializeSummary(bool $force): array
    {
        return [
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'cycle_key' => $this->cycleKey,
            'force_mode' => $force,
            'skipped' => false,
            'reason' => null,
            'recommendation' => [
                'processed' => 0,
                'dispatched' => 0,
                'error' => null,
            ],
            'image_pool_count' => 0,
            'consumer' => [
                'created_orders' => 0,
                'confirmed_orders' => 0,
                'affiliate_withdraw_requests' => 0,
                'withdraw_idempotency_hits' => 0,
                'errors' => [],
            ],
            'mitra' => [
                'activated_products' => 0,
                'created_procurements' => 0,
                'paid_procurements' => 0,
                'confirmed_procurements' => 0,
                'order_actions' => 0,
                'withdraw_requests' => 0,
                'withdraw_idempotency_hits' => 0,
                'errors' => [],
            ],
            'seller' => [
                'hydrated_products' => 0,
                'created_products' => 0,
                'order_actions' => 0,
                'withdraw_requests' => 0,
                'withdraw_idempotency_hits' => 0,
                'errors' => [],
            ],
            'admin' => [
                'procurement_actions' => 0,
                'weather_notice_actions' => 0,
                'auto_weather_notices' => 0,
                'manual_notice_redispatches' => 0,
                'weather_notifications_sent' => 0,
                'withdraw_actions' => 0,
                'withdraw_approved' => 0,
                'withdraw_paid' => 0,
                'errors' => [],
            ],
        ];
    }
}
