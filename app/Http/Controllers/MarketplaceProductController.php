<?php

namespace App\Http\Controllers;

use App\Services\AffiliateReferralService;
use App\Services\AffiliateReferralTrackingService;
use App\Services\CartSanitizerService;
use App\Services\ConsumerPurchasePolicyService;
use App\Services\MarketplaceProductService;
use App\Services\PaymentMethodService;
use App\Services\RoleAccessService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketplaceProductController extends Controller
{
    public function __construct(
        protected MarketplaceProductService $catalog,
        protected AffiliateReferralService $affiliateReferral,
        protected AffiliateReferralTrackingService $affiliateTracking,
        protected ConsumerPurchasePolicyService $consumerPurchasePolicy,
        protected PaymentMethodService $paymentMethods,
        protected RoleAccessService $roleAccess,
        protected CartSanitizerService $cartSanitizer,
        protected WalletService $walletService
    ) {}

    public function show(Request $request, string $productType, int $productId)
    {
        $this->affiliateReferral->captureFromRequest($request);

        $normalizedProductType = $this->catalog->normalizeProductType($productType);
        abort_if($normalizedProductType === null, 404);

        $product = $this->catalog->findProduct($normalizedProductType, $productId);
        abort_if(! $product, 404);

        $user = $request->user();
        $activeAffiliateReferral = $this->affiliateReferral->currentReferral($request, $user);
        if ($request->query('ref') && ! empty($activeAffiliateReferral['id'])) {
            $this->affiliateTracking->trackClick(
                $request,
                (int) $activeAffiliateReferral['id'],
                $user ? (int) $user->id : null
            );
        }
        $role = strtolower(trim((string) ($user?->role ?? 'guest')));
        $notificationCount = $user && Schema::hasTable('notifications')
            ? (int) $user->unreadNotifications()->count()
            : 0;

        $canConsumerCheckoutMarketplace = false;
        $checkoutModeMeta = [
            'mode' => 'buyer',
            'label' => 'Buyer',
            'can_checkout' => false,
            'helper' => 'Login sebagai consumer untuk checkout.',
        ];
        $checkoutPaymentMethods = [];
        $checkoutPaymentMethodMeta = [];
        $defaultCheckoutPaymentMethod = null;
        $walletBalance = 0.0;
        $hasWalletBalance = false;

        if ($user && $role === 'consumer') {
            $canConsumerCheckoutMarketplace = $this->consumerPurchasePolicy->canCheckout($user);
            $checkoutModeMeta = $this->consumerPurchasePolicy->modeMeta($user);
            $resolvedMode = $this->consumerPurchasePolicy->resolveMode($user);
            $checkoutPaymentMethodMeta = $this->paymentMethods->methodsForConsumerMode($resolvedMode);
            $checkoutPaymentMethods = collect($checkoutPaymentMethodMeta)
                ->map(function (array $item): array {
                    $kind = strtolower(trim((string) ($item['kind'] ?? 'wallet')));

                    return [
                        'method' => (string) ($item['method'] ?? ''),
                        'label' => (string) ($item['label'] ?? ''),
                        'helper' => $kind === 'bank'
                            ? 'Upload bukti transfer bank'
                            : 'Upload bukti pembayaran e-wallet',
                    ];
                })
                ->values()
                ->all();
            if ($canConsumerCheckoutMarketplace && ! empty($checkoutPaymentMethods)) {
                $defaultCheckoutPaymentMethod = $this->consumerPurchasePolicy->defaultCheckoutMethod($user);
            }

            if (Schema::hasTable('wallet_transactions')) {
                $walletBalance = max(0, (float) $this->walletService->getBalance((int) $user->id));
                $hasWalletBalance = $walletBalance > 0;
            }
        }

        $storeUrl = route('marketplace.store.show', [
            'sellerType' => $product['seller_type'],
            'sellerId' => $product['seller_id'],
        ]);
        $storeProfile = $this->catalog->sellerProfile($product['seller_type'], (int) $product['seller_id']);

        $relatedProducts = $this->catalog
            ->productsBySeller($product['seller_type'], (int) $product['seller_id'], (int) $product['id'])
            ->filter(function (array $item) use ($product): bool {
                return ! (
                    (int) ($item['id'] ?? 0) === (int) ($product['id'] ?? 0)
                    && (string) ($item['product_type'] ?? '') === (string) ($product['product_type'] ?? '')
                );
            })
            ->unique(fn (array $item): string => (string) ($item['product_type'] ?? '') . ':' . (int) ($item['id'] ?? 0))
            ->values()
            ->take(8)
            ->values();
        $sellerReviews = $this->catalog->sellerReviews((int) ($product['seller_id'] ?? 0));

        return view('marketplace.product-show', [
            'notificationCount' => $notificationCount,
            'product' => $product,
            'storeUrl' => $storeUrl,
            'storeProfile' => $storeProfile,
            'relatedProducts' => $relatedProducts,
            'sellerReviews' => $sellerReviews,
            'canConsumerCheckoutMarketplace' => $canConsumerCheckoutMarketplace,
            'checkoutModeMeta' => $checkoutModeMeta,
            'checkoutPaymentMethods' => $checkoutPaymentMethods,
            'checkoutPaymentMethodMeta' => $checkoutPaymentMethodMeta,
            'defaultCheckoutPaymentMethod' => $defaultCheckoutPaymentMethod,
            'walletBalance' => $walletBalance,
            'hasWalletBalance' => $hasWalletBalance,
            'canUseAffiliateProductFilter' => $user ? $this->roleAccess->canAccessAffiliate($user) : false,
            'role' => $role,
        ]);
    }

    public function showStore(Request $request, string $sellerType, int $sellerId)
    {
        $this->affiliateReferral->captureFromRequest($request);

        $normalizedSellerType = $this->catalog->normalizeSellerType($sellerType);
        abort_if($normalizedSellerType === null, 404);

        $store = $this->catalog->sellerProfile($normalizedSellerType, $sellerId);
        abort_if(! $store, 404);

        $products = $this->catalog
            ->productsBySeller($normalizedSellerType, $sellerId)
            ->values();

        $user = $request->user();
        $notificationCount = $user && Schema::hasTable('notifications')
            ? (int) $user->unreadNotifications()->count()
            : 0;

        return view('marketplace.store-show', [
            'notificationCount' => $notificationCount,
            'store' => $store,
            'products' => $products,
        ]);
    }

    public function report(Request $request, string $productType, int $productId): RedirectResponse
    {
        $this->authorize('access-consumer');
        abort_unless(Schema::hasTable('product_reports'), 404);

        $normalizedProductType = $this->catalog->normalizeProductType($productType);
        abort_if($normalizedProductType === null, 404);

        $product = $this->catalog->findProduct($normalizedProductType, $productId);
        abort_if(! $product, 404);

        $data = $request->validate([
            'category' => ['required', 'string', 'in:fraud,fake_product,misleading_info,spam,other'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $reporterId = (int) $request->user()->id;
        $reportedUserId = (int) ($product['seller_id'] ?? 0);

        if ($reportedUserId < 1) {
            return back()->withErrors(['report' => 'Pemilik produk tidak valid untuk dilaporkan.']);
        }

        if ($reporterId === $reportedUserId) {
            return back()->withErrors(['report' => 'Anda tidak dapat melaporkan produk milik akun sendiri.']);
        }

        $hasActiveReport = DB::table('product_reports')
            ->where('reporter_id', $reporterId)
            ->where('product_type', $normalizedProductType)
            ->where('product_id', $productId)
            ->whereIn('status', ['pending', 'under_review'])
            ->exists();

        if ($hasActiveReport) {
            return back()->withErrors(['report' => 'Laporan aktif untuk produk ini sudah pernah Anda kirim.']);
        }

        DB::table('product_reports')->insert([
            'product_type' => $normalizedProductType,
            'product_id' => $productId,
            'product_name' => (string) ($product['name'] ?? ''),
            'reported_user_id' => $reportedUserId,
            'reporter_id' => $reporterId,
            'category' => strtolower((string) $data['category']),
            'description' => trim((string) $data['description']),
            'status' => 'pending',
            'handled_by' => null,
            'handled_at' => null,
            'resolution_notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Laporan produk berhasil dikirim ke Admin.');
    }
}
