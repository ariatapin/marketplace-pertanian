<?php

namespace App\Http\Controllers;

use App\Http\Requests\WithdrawBankAccountUpdateRequest;
use App\Models\User;
use App\Services\AffiliateReferralService;
use App\Services\CartSanitizerService;
use App\Services\ConsumerPurchasePolicyService;
use App\Services\FeatureFlagService;
use App\Services\Location\LocationResolver;
use App\Services\AffiliateReferralTrackingService;
use App\Services\Recommendation\RuleBasedRecommendationService;
use App\Services\RoleAccessService;
use App\Services\WithdrawBankAccountService;
use App\Services\UserRatingService;
use App\Services\WalletService;
use App\Services\Weather\WeatherAlertEngine;
use App\Services\Weather\WeatherService;
use App\Support\AdminWeatherNoticeNotification;
use App\Support\BehaviorRecommendationNotification;
use App\Support\MarketplaceLandingViewModelFactory;
use App\Support\RoleRedirector;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class AppPageController extends Controller
{
    public function __construct(
        protected FeatureFlagService $featureFlags,
        protected AffiliateReferralService $affiliateReferral,
        protected AffiliateReferralTrackingService $affiliateTracking,
        protected MarketplaceLandingViewModelFactory $landingViewModelFactory,
        protected ConsumerPurchasePolicyService $consumerPurchasePolicy,
        protected RoleAccessService $roleAccess,
        protected CartSanitizerService $cartSanitizer,
        protected WalletService $walletService,
        protected WithdrawBankAccountService $withdrawBankAccounts,
        protected UserRatingService $userRatings,
        protected LocationResolver $location,
        protected WeatherService $weather,
        protected WeatherAlertEngine $weatherAlertEngine,
        protected RuleBasedRecommendationService $recommendationService
    ) {}

    public function landing(Request $request)
    {
        // CATATAN-AUDIT: Landing adalah shell utama marketplace; admin/mitra diarahkan ke dashboard role masing-masing.
        $user = $request->user();
        $userRole = strtolower(trim((string) ($user?->role ?? '')));

        if ($user && $userRole === 'admin') {
            return redirect()->to('/admin/dashboard');
        }

        $this->affiliateReferral->captureFromRequest($request);
        $activeAffiliateReferral = $this->affiliateReferral->currentReferral($request, $user);
        $canUseAffiliateProductFilter = $user ? $this->roleAccess->canAccessAffiliate($user) : false;
        if ($request->query('ref') && ! empty($activeAffiliateReferral['id'])) {
            $this->affiliateTracking->trackClick(
                $request,
                (int) $activeAffiliateReferral['id'],
                $user ? (int) $user->id : null
            );
        }

        $keyword = trim($request->string('q')->toString());
        $focusProductId = max(0, (int) $request->integer('focus_product', 0));
        $productSource = $this->resolveProductSource(
            $request->string('source')->toString(),
            $canUseAffiliateProductFilter
        );
        $affiliateReadyOnly = $canUseAffiliateProductFilter
            && $productSource === 'affiliate'
            && $request->boolean('ready_marketing');
        $affiliateSelfReferralCode = null;
        if ($user && $canUseAffiliateProductFilter) {
            $affiliateSelfReferralCode = $this->affiliateReferral->encodeReferralCode((int) $user->id);
        }
        $viewerLocation = $this->location->forUser($user);
        $weatherAlert = $this->resolveWeatherAlert($viewerLocation, $user);
        [
            'marketStats' => $marketStats,
            'featuredProducts' => $featuredProducts,
            'geoSortApplied' => $geoSortApplied,
            'affiliateReadyCount' => $affiliateReadyCount,
        ] = $this->marketplaceSnapshot(
            $keyword,
            $viewerLocation,
            (string) ($weatherAlert['severity'] ?? 'green'),
            $productSource,
            $canUseAffiliateProductFilter,
            $focusProductId,
            ($user && $canUseAffiliateProductFilter && $productSource === 'affiliate') ? (int) $user->id : 0,
            $affiliateReadyOnly
        );
        $heroAnnouncements = $this->landingAnnouncements();
        $mitraSubmission = $this->mitraSubmissionState($user);
        if ($user && in_array($userRole, ['consumer', 'mitra'], true)) {
            try {
                // CATATAN-AUDIT: Sinkronisasi rekomendasi dipicu saat halaman utama dibuka agar feed selalu segar.
                $this->recommendationService->syncForUser($user);
            } catch (\Throwable $e) {
                // Silent fallback: landing tetap render walau sinkron rekomendasi gagal.
            }
        }
        $unreadNotifications = $this->unreadNotificationCount($user);
        [$weatherNotifications, $weatherNotificationUnreadCount] = $this->buildWeatherNotificationFeed($user);

        $isActiveConsumerDashboard = false;
        $consumerSummary = null;
        if ($user && $userRole === 'consumer') {
            $isActiveConsumerDashboard = Gate::forUser($user)->allows('access-consumer-active-dashboard');
            if ($isActiveConsumerDashboard) {
                $consumerSummary = $this->buildConsumerSummary($user);
            }
        }

        $cartSummary = $user && $userRole === 'consumer'
            ? $this->buildCartSummary($user->id)
            : ['items' => 0, 'estimated_total' => 0];
        $consumerDemoBalance = $this->resolveConsumerDemoBalance($user);

        $viewData = $this->landingViewModelFactory->make($user, [
            'currentRole' => $userRole !== '' ? $userRole : null,
            'isActiveConsumerDashboard' => $isActiveConsumerDashboard,
            'consumerSummary' => $consumerSummary,
            'marketStats' => $marketStats,
            'featuredProducts' => $featuredProducts,
            'searchKeyword' => $keyword,
            'productSource' => $productSource,
            'cartSummary' => $cartSummary,
            'heroAnnouncements' => $heroAnnouncements,
            'mitraSubmission' => $mitraSubmission,
            'canUseAffiliateProductFilter' => $canUseAffiliateProductFilter,
            'unreadNotifications' => $unreadNotifications,
            'activeAffiliateReferral' => $activeAffiliateReferral,
            'viewerLocationLabel' => (string) ($viewerLocation['label'] ?? 'Lokasi belum diset'),
            'geoSortApplied' => $geoSortApplied,
            'weatherAlert' => $weatherAlert,
            'affiliateSelfReferralCode' => $affiliateSelfReferralCode,
            'focusProductId' => $focusProductId,
            'affiliateReadyOnly' => $affiliateReadyOnly,
            'affiliateReadyCount' => $affiliateReadyCount,
        ]);

        $checkoutPaymentMethods = [];
        $defaultCheckoutPaymentMethod = null;
        $canConsumerCheckoutMarketplace = false;
        $checkoutModeMeta = [
            'mode' => 'buyer',
            'label' => 'Buyer',
            'can_checkout' => false,
            'helper' => 'Mode buyer: checkout tersedia untuk semua metode pembayaran aktif.',
        ];

        if ($user && $userRole === 'consumer') {
            $canConsumerCheckoutMarketplace = $this->consumerPurchasePolicy->canCheckout($user);
            $checkoutModeMeta = $this->consumerPurchasePolicy->modeMeta($user);
            $checkoutPaymentMethods = $this->consumerPurchasePolicy->checkoutOptions($user);
            if ($canConsumerCheckoutMarketplace && ! empty($checkoutPaymentMethods)) {
                $defaultCheckoutPaymentMethod = $this->consumerPurchasePolicy->defaultCheckoutMethod($user);
            }
        }

        $pagePayload = array_merge($viewData, [
            'checkoutPaymentMethods' => $checkoutPaymentMethods,
            'defaultCheckoutPaymentMethod' => $defaultCheckoutPaymentMethod,
            'checkoutModeMeta' => $checkoutModeMeta,
            'canConsumerCheckoutMarketplace' => $canConsumerCheckoutMarketplace,
            'consumerDemoBalance' => $consumerDemoBalance,
            'weatherNotifications' => $weatherNotifications,
            'weatherNotificationUnreadCount' => $weatherNotificationUnreadCount,
        ]);

        if ($request->boolean('partial_products')) {
            return response()->json([
                'html' => view('marketplace._products-panel', $pagePayload)->render(),
            ]);
        }

        return view('marketplace.home', $pagePayload);
    }

    public function dashboard()
    {
        // CATATAN-AUDIT: Endpoint /dashboard hanya untuk consumer aktif; role lain dipindah via RoleRedirector.
        $user = request()->user();
        $this->affiliateReferral->captureFromRequest(request());
        $activeAffiliateReferral = $this->affiliateReferral->currentReferral(request(), $user);
        $canUseAffiliateProductFilter = $user ? $this->roleAccess->canAccessAffiliate($user) : false;
        $affiliateSelfReferralCode = null;
        if ($canUseAffiliateProductFilter) {
            $affiliateSelfReferralCode = $this->affiliateReferral->encodeReferralCode((int) $user->id);
        }
        if (request()->query('ref') && ! empty($activeAffiliateReferral['id'])) {
            $this->affiliateTracking->trackClick(
                request(),
                (int) $activeAffiliateReferral['id'],
                $user ? (int) $user->id : null
            );
        }
        $focusProductId = max(0, (int) request()->integer('focus_product', 0));
        $productSource = $this->resolveProductSource(
            (string) request()->query('source', 'all'),
            $canUseAffiliateProductFilter
        );
        $affiliateReadyOnly = $canUseAffiliateProductFilter
            && $productSource === 'affiliate'
            && request()->boolean('ready_marketing');
        $target = app(RoleRedirector::class)->pathFor($user);

        if ($target !== '/dashboard') {
            return redirect()->to($target);
        }

        if (! Gate::forUser($user)->allows('access-consumer-active-dashboard')) {
            return redirect()->route('landing');
        }

        $viewerLocation = $this->location->forUser($user);
        $weatherAlert = $this->resolveWeatherAlert($viewerLocation, $user);
        [
            'marketStats' => $marketStats,
            'featuredProducts' => $featuredProducts,
            'geoSortApplied' => $geoSortApplied,
            'affiliateReadyCount' => $affiliateReadyCount,
        ] = $this->marketplaceSnapshot(
            '',
            $viewerLocation,
            (string) ($weatherAlert['severity'] ?? 'green'),
            $productSource,
            $canUseAffiliateProductFilter,
            $focusProductId,
            ($canUseAffiliateProductFilter && $productSource === 'affiliate') ? (int) $user->id : 0,
            $affiliateReadyOnly
        );
        $summary = $this->buildConsumerSummary($user);
        $cartSummary = $this->buildCartSummary($user->id);
        $consumerDemoBalance = $this->resolveConsumerDemoBalance($user);
        $heroAnnouncements = $this->landingAnnouncements();
        $mitraSubmission = $this->mitraSubmissionState($user);
        try {
            // CATATAN-AUDIT: Consumer dashboard juga memicu sinkronisasi rekomendasi berbasis perilaku.
            $this->recommendationService->syncForUser($user);
        } catch (\Throwable $e) {
            // Silent fallback: dashboard tetap render walau sinkron rekomendasi gagal.
        }
        $unreadNotifications = $this->unreadNotificationCount($user);
        [$weatherNotifications, $weatherNotificationUnreadCount] = $this->buildWeatherNotificationFeed($user);

        $viewData = $this->landingViewModelFactory->make($user, [
            'currentRole' => strtolower(trim((string) $user->role)),
            'isActiveConsumerDashboard' => true,
            'consumerSummary' => $summary,
            'marketStats' => $marketStats,
            'featuredProducts' => $featuredProducts,
            'searchKeyword' => '',
            'productSource' => $productSource,
            'cartSummary' => $cartSummary,
            'heroAnnouncements' => $heroAnnouncements,
            'mitraSubmission' => $mitraSubmission,
            'canUseAffiliateProductFilter' => $canUseAffiliateProductFilter,
            'unreadNotifications' => $unreadNotifications,
            'activeAffiliateReferral' => $activeAffiliateReferral,
            'viewerLocationLabel' => (string) ($viewerLocation['label'] ?? 'Lokasi belum diset'),
            'geoSortApplied' => $geoSortApplied,
            'weatherAlert' => $weatherAlert,
            'affiliateSelfReferralCode' => $affiliateSelfReferralCode,
            'focusProductId' => $focusProductId,
            'affiliateReadyOnly' => $affiliateReadyOnly,
            'affiliateReadyCount' => $affiliateReadyCount,
        ]);

        $canConsumerCheckoutMarketplace = $this->consumerPurchasePolicy->canCheckout($user);
        $checkoutPaymentMethods = $this->consumerPurchasePolicy->checkoutOptions($user);
        $defaultCheckoutPaymentMethod = null;
        if ($canConsumerCheckoutMarketplace && ! empty($checkoutPaymentMethods)) {
            $defaultCheckoutPaymentMethod = $this->consumerPurchasePolicy->defaultCheckoutMethod($user);
        }

        return view('marketplace.home', array_merge($viewData, [
            'checkoutPaymentMethods' => $checkoutPaymentMethods,
            'defaultCheckoutPaymentMethod' => $defaultCheckoutPaymentMethod,
            'checkoutModeMeta' => $this->consumerPurchasePolicy->modeMeta($user),
            'canConsumerCheckoutMarketplace' => $canConsumerCheckoutMarketplace,
            'consumerDemoBalance' => $consumerDemoBalance,
            'weatherNotifications' => $weatherNotifications,
            'weatherNotificationUnreadCount' => $weatherNotificationUnreadCount,
        ]));
    }

    public function account(Request $request)
    {
        $user = $request->user();
        $bankSnapshot = $this->withdrawBankAccounts->snapshot((int) $user->id);
        $bankProfile = (object) [
            'bank_name' => $bankSnapshot['bank_name'],
            'account_number' => $bankSnapshot['account_number'],
            'account_holder' => $bankSnapshot['account_holder'],
            'updated_at' => $bankSnapshot['updated_at'] ?? null,
        ];
        $isBankProfileComplete = (bool) ($bankSnapshot['complete'] ?? false);

        return view('profile.account', [
            'user' => $user,
            'bankProfile' => $bankProfile,
            'isBankProfileComplete' => $isBankProfileComplete,
            'notificationCount' => (int) $user->unreadNotifications()->count(),
        ]);
    }

    public function updateAccountBank(WithdrawBankAccountUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->withdrawBankAccounts->hasStorage()) {
            return redirect()->route('account.show')
                ->with('error', 'Tabel rekening belum tersedia. Hubungi admin.');
        }

        $data = $request->validated();

        $normalizedBank = $this->withdrawBankAccounts->normalizeInput(
            $data['bank_name'] ?? null,
            $data['account_number'] ?? null,
            $data['account_holder'] ?? null
        );

        $this->withdrawBankAccounts->upsert(
            (int) $user->id,
            $normalizedBank['bank_name'],
            $normalizedBank['account_number'],
            $normalizedBank['account_holder']
        );

        $message = ((int) ($normalizedBank['filled_count'] ?? 0)) === 0
            ? 'Data rekening berhasil dikosongkan.'
            : 'Data rekening berhasil disimpan.';

        return redirect()->route('account.show')->with('status', $message);
    }

    public function locationForm(Request $request)
    {
        $user = $request->user();
        $resolvedLocation = $this->location->forUser($user);
        $hasLocationSet = (int) ($user?->province_id ?? 0) > 0 && (int) ($user?->city_id ?? 0) > 0;

        $backUrl = $user?->isAdmin()
            ? route('admin.profile')
            : route('profile.edit');

        return view('profile.location', [
            'backUrl' => $backUrl,
            'currentLocationLabel' => (string) ($resolvedLocation['label'] ?? 'Belum diset'),
            'hasLocationSet' => $hasLocationSet,
        ]);
    }

    public function saveLocation(Request $request)
    {
        $data = $request->validate([
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
        ]);

        $city = DB::table('cities')
            ->select('id', 'province_id', 'lat', 'lng')
            ->where('id', (int) $data['city_id'])
            ->first();

        if (! $city) {
            return back()
                ->withErrors(['city_id' => 'Kota tidak ditemukan.'])
                ->withInput();
        }

        if ((int) $city->province_id !== (int) $data['province_id']) {
            return back()
                ->withErrors(['city_id' => 'Kota tidak sesuai dengan provinsi yang dipilih.'])
                ->withInput();
        }

        $districtId = isset($data['district_id']) && $data['district_id'] !== null
            ? (int) $data['district_id']
            : null;

        if ($districtId !== null) {
            $districtMatchesCity = DB::table('districts')
                ->where('id', $districtId)
                ->where('city_id', (int) $city->id)
                ->exists();

            if (! $districtMatchesCity) {
                return back()
                    ->withErrors(['district_id' => 'Kecamatan tidak sesuai dengan kota yang dipilih.'])
                    ->withInput();
            }
        }

        $request->user()->update([
            'province_id' => (int) $city->province_id,
            'city_id' => (int) $city->id,
            'district_id' => $districtId,
            'lat' => $city->lat,
            'lng' => $city->lng,
        ]);

        return back()->with('status', 'Lokasi berhasil disimpan.');
    }

    private function defaultConsumerSummary(): array
    {
        return [
            'mode' => 'buyer',
            'mode_status' => 'none',
            'requested_mode' => null,
            'location_label' => 'Belum diset',
            'total_orders' => 0,
            'active_orders' => 0,
            'completed_orders' => 0,
        ];
    }

    private function buildConsumerSummary(User $user): array
    {
        $summary = $this->defaultConsumerSummary();

        if (Schema::hasTable('consumer_profiles')) {
            $profile = DB::table('consumer_profiles')
                ->where('user_id', $user->id)
                ->first();

            if ($profile) {
                $summary['mode'] = $profile->mode ?? 'buyer';
                $summary['mode_status'] = $profile->mode_status ?? 'none';
                $summary['requested_mode'] = $profile->requested_mode;
            }
        }

        $loc = $this->location->forUser($user);
        $summary['location_label'] = $loc['label'] ?? 'Belum diset';

        if (Schema::hasTable('orders')) {
            $orderQuery = DB::table('orders')->where('buyer_id', $user->id);
            $summary['total_orders'] = (clone $orderQuery)->count();
            $summary['active_orders'] = (clone $orderQuery)
                ->whereIn('order_status', ['pending_payment', 'paid', 'packed', 'shipped'])
                ->count();
            $summary['completed_orders'] = (clone $orderQuery)
                ->where('order_status', 'completed')
                ->count();
        }

        return $summary;
    }

    private function marketplaceSnapshot(
        string $keyword = '',
        ?array $viewerLocation = null,
        string $weatherSeverity = 'green',
        string $productSource = 'all',
        bool $canUseAffiliateProductFilter = false,
        int $focusProductId = 0,
        int $affiliateUserId = 0,
        bool $affiliateReadyOnly = false
    ): array
    {
        $productSource = $this->resolveProductSource($productSource, $canUseAffiliateProductFilter);
        $keyword = trim($keyword);
        $keywordLike = null;
        if ($keyword !== '') {
            $keywordLike = '%' . mb_strtolower($keyword, 'UTF-8') . '%';
        }

        $marketStats = [
            'total_products' => 0,
            'in_stock_products' => 0,
            'active_sellers' => 0,
            'average_price' => 0,
        ];
        $featuredProducts = collect();
        $geoSortApplied = false;
        $affiliateReadyCount = 0;

        $hasStoreProducts = Schema::hasTable('store_products');
        $hasFarmerHarvests = Schema::hasTable('farmer_harvests');

        if (! $hasStoreProducts && ! $hasFarmerHarvests) {
            return [
                'marketStats' => $marketStats,
                'featuredProducts' => $featuredProducts,
                'geoSortApplied' => $geoSortApplied,
                'affiliateReadyCount' => $affiliateReadyCount,
            ];
        }

        $totalProducts = 0;
        $inStockProducts = 0;
        $totalPrice = 0.0;
        $sellerIds = collect();

        if ($hasStoreProducts) {
            $storeProducts = DB::table('store_products');
            if (Schema::hasColumn('store_products', 'is_active')) {
                $storeProducts->where('is_active', true);
            }

            $totalProducts += (int) (clone $storeProducts)->count();
            $inStockProducts += (int) (clone $storeProducts)->where('stock_qty', '>', 0)->count();
            $totalPrice += (float) ((clone $storeProducts)->sum('price') ?? 0);
            $sellerIds = $sellerIds->merge(
                (clone $storeProducts)
                    ->whereNotNull('mitra_id')
                    ->distinct()
                    ->pluck('mitra_id')
            );
        }

        if ($hasFarmerHarvests) {
            $farmerProducts = DB::table('farmer_harvests')
                ->where('status', 'approved');

            $totalProducts += (int) (clone $farmerProducts)->count();
            $inStockProducts += (int) (clone $farmerProducts)->where('stock_qty', '>', 0)->count();
            $totalPrice += (float) ((clone $farmerProducts)->sum('price') ?? 0);
            $sellerIds = $sellerIds->merge(
                (clone $farmerProducts)
                    ->whereNotNull('farmer_id')
                    ->distinct()
                    ->pluck('farmer_id')
            );
        }

        $marketStats['total_products'] = $totalProducts;
        $marketStats['in_stock_products'] = $inStockProducts;
        $marketStats['active_sellers'] = $sellerIds
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->count();
        $marketStats['average_price'] = $totalProducts > 0
            ? ($totalPrice / $totalProducts)
            : 0;

        $viewerLat = is_array($viewerLocation) ? (float) ($viewerLocation['lat'] ?? 0) : 0;
        $viewerLng = is_array($viewerLocation) ? (float) ($viewerLocation['lng'] ?? 0) : 0;
        $hasViewerCoordinate = is_array($viewerLocation)
            && array_key_exists('lat', $viewerLocation)
            && array_key_exists('lng', $viewerLocation);
        $geoSortApplied = $hasViewerCoordinate;

        $distanceExpression = "CASE
                WHEN %s.lat IS NULL OR %s.lng IS NULL THEN NULL
                ELSE (
                    6371 * acos(
                        LEAST(
                            1,
                            GREATEST(
                                -1,
                                cos(radians(?))
                                * cos(radians(%s.lat))
                                * cos(radians(%s.lng) - radians(?))
                                + sin(radians(?)) * sin(radians(%s.lat))
                            )
                        )
                    )
                )
            END";

        $featuredCollections = collect();

        $hasAffiliateEnabledColumn = $hasStoreProducts && Schema::hasColumn('store_products', 'is_affiliate_enabled');
        $hasAffiliateExpireColumn = $hasStoreProducts && Schema::hasColumn('store_products', 'affiliate_expire_date');
        $isAffiliateOnlySource = $productSource === 'affiliate';
        $affiliateMarketedLookup = [];
        if ($isAffiliateOnlySource && $affiliateUserId > 0) {
            $readyProductIds = $this->resolveAffiliateReadyProductIds($affiliateUserId);
            $affiliateReadyCount = count($readyProductIds);
            $affiliateMarketedLookup = array_fill_keys(
                $readyProductIds,
                true
            );
        }

        if ($hasStoreProducts && in_array($productSource, ['all', 'mitra', 'affiliate'], true)) {
            $storeQuery = DB::table('store_products')
                ->leftJoin('users as mitra', 'mitra.id', '=', 'store_products.mitra_id')
                ->leftJoin('cities as seller_city', 'seller_city.id', '=', 'mitra.city_id')
                ->where('store_products.stock_qty', '>', 0);

            if (Schema::hasColumn('store_products', 'is_active')) {
                $storeQuery->where('store_products.is_active', true);
            }

            if ($isAffiliateOnlySource) {
                if (! $hasAffiliateEnabledColumn) {
                    $storeQuery->whereRaw('1 = 0');
                } else {
                    $storeQuery->where('store_products.is_affiliate_enabled', true);
                }

                if ($hasAffiliateExpireColumn) {
                    $storeQuery->where(function ($query) {
                        $query->whereNull('store_products.affiliate_expire_date')
                            ->orWhereDate('store_products.affiliate_expire_date', '>=', today()->toDateString());
                    });
                }
            }

            if ($keywordLike !== null) {
                $storeQuery->where(function ($query) use ($keywordLike) {
                    $query->whereRaw('LOWER(store_products.name) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(store_products.description) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(mitra.name) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(seller_city.name) LIKE ?', [$keywordLike]);
                });
            }

            $storeSelect = [
                'store_products.id',
                'store_products.name',
                'store_products.description',
                'store_products.price',
                'store_products.stock_qty',
                'store_products.image_url',
                'store_products.updated_at',
                'store_products.mitra_id as seller_id',
                'mitra.name as seller_name',
                'mitra.email as seller_email',
                'seller_city.name as seller_city_name',
                'seller_city.type as seller_city_type',
                DB::raw("'store' as product_type"),
                DB::raw("'mitra' as seller_kind"),
            ];
            if (Schema::hasColumn('store_products', 'unit')) {
                $storeSelect[] = 'store_products.unit';
            } else {
                $storeSelect[] = DB::raw("'kg' as unit");
            }
            if ($hasAffiliateEnabledColumn) {
                $storeSelect[] = 'store_products.is_affiliate_enabled';
            } else {
                $storeSelect[] = DB::raw('false as is_affiliate_enabled');
            }
            if ($hasAffiliateExpireColumn) {
                $storeSelect[] = 'store_products.affiliate_expire_date';
            } else {
                $storeSelect[] = DB::raw('NULL as affiliate_expire_date');
            }
            $storeQuery->addSelect($storeSelect);

            if ($hasViewerCoordinate) {
                $storeQuery->selectRaw(
                    sprintf($distanceExpression, 'mitra', 'mitra', 'mitra', 'mitra', 'mitra') . ' as distance_km',
                    [$viewerLat, $viewerLng, $viewerLat]
                );
            } else {
                $storeQuery->selectRaw('NULL as distance_km');
            }

            $storeRows = $storeQuery->get();
            if ($isAffiliateOnlySource) {
                $storeRows = $storeRows
                    ->map(function ($row) use ($affiliateMarketedLookup) {
                        $row->is_marketed_by_affiliate = isset($affiliateMarketedLookup[(int) ($row->id ?? 0)]);
                        return $row;
                    })
                    ->values();

                if ($affiliateReadyOnly) {
                    $storeRows = $storeRows
                        ->filter(fn ($row) => (bool) ($row->is_marketed_by_affiliate ?? false))
                        ->values();
                }
            }

            $featuredCollections = $featuredCollections->merge($storeRows);
        }

        if ($hasFarmerHarvests && in_array($productSource, ['all', 'seller'], true)) {
            $farmerQuery = DB::table('farmer_harvests')
                ->leftJoin('users as seller', 'seller.id', '=', 'farmer_harvests.farmer_id')
                ->leftJoin('cities as seller_city', 'seller_city.id', '=', 'seller.city_id')
                ->where('farmer_harvests.status', 'approved')
                ->where('farmer_harvests.stock_qty', '>', 0);

            if ($keywordLike !== null) {
                $farmerQuery->where(function ($query) use ($keywordLike) {
                    $query->whereRaw('LOWER(farmer_harvests.name) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(farmer_harvests.description) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(seller.name) LIKE ?', [$keywordLike])
                        ->orWhereRaw('LOWER(seller_city.name) LIKE ?', [$keywordLike]);
                });
            }

            $farmerQuery->addSelect([
                'farmer_harvests.id',
                'farmer_harvests.name',
                'farmer_harvests.description',
                'farmer_harvests.price',
                'farmer_harvests.stock_qty',
                'farmer_harvests.image_url',
                'farmer_harvests.updated_at',
                'farmer_harvests.farmer_id as seller_id',
                'seller.name as seller_name',
                'seller.email as seller_email',
                'seller_city.name as seller_city_name',
                'seller_city.type as seller_city_type',
                DB::raw("'farmer' as product_type"),
                DB::raw("'seller' as seller_kind"),
                DB::raw("'kg' as unit"),
            ]);

            if ($hasViewerCoordinate) {
                $farmerQuery->selectRaw(
                    sprintf($distanceExpression, 'seller', 'seller', 'seller', 'seller', 'seller') . ' as distance_km',
                    [$viewerLat, $viewerLng, $viewerLat]
                );
            } else {
                $farmerQuery->selectRaw('NULL as distance_km');
            }

            $featuredCollections = $featuredCollections->merge($farmerQuery->get());
        }

        if ($featuredCollections->isEmpty()) {
            return [
                'marketStats' => $marketStats,
                'featuredProducts' => collect(),
                'geoSortApplied' => $geoSortApplied,
                'affiliateReadyCount' => $affiliateReadyCount,
            ];
        }

        $applyStockPriority = in_array($weatherSeverity, ['yellow', 'red'], true);
        $featuredProducts = $featuredCollections->sort(function ($a, $b) use ($hasViewerCoordinate, $applyStockPriority) {
            if ($hasViewerCoordinate) {
                $aDistance = is_numeric($a->distance_km ?? null) ? (float) $a->distance_km : null;
                $bDistance = is_numeric($b->distance_km ?? null) ? (float) $b->distance_km : null;

                if ($aDistance === null && $bDistance !== null) {
                    return 1;
                }
                if ($aDistance !== null && $bDistance === null) {
                    return -1;
                }
                if ($aDistance !== null && $bDistance !== null) {
                    $distanceCompare = $aDistance <=> $bDistance;
                    if ($distanceCompare !== 0) {
                        return $distanceCompare;
                    }
                }
            }

            if ($applyStockPriority) {
                $stockCompare = ((int) ($b->stock_qty ?? 0)) <=> ((int) ($a->stock_qty ?? 0));
                if ($stockCompare !== 0) {
                    return $stockCompare;
                }
            }

            $aTimestamp = optional($a->updated_at)->getTimestamp() ?? strtotime((string) $a->updated_at) ?: 0;
            $bTimestamp = optional($b->updated_at)->getTimestamp() ?? strtotime((string) $b->updated_at) ?: 0;

            return $bTimestamp <=> $aTimestamp;
        })->values();

        if ($focusProductId > 0) {
            $focusedProduct = $featuredCollections->first(function ($product) use ($focusProductId) {
                return (int) ($product->id ?? 0) === $focusProductId
                    && strtolower((string) ($product->product_type ?? 'store')) === 'store';
            });

            if ($focusedProduct) {
                $featuredProducts = $featuredProducts
                    ->reject(fn ($product) => (int) ($product->id ?? 0) === $focusProductId)
                    ->prepend($focusedProduct)
                    ->values();
            }
        }

        if (Schema::hasTable('store_product_images')) {
            $storeProductIds = $featuredProducts
                ->filter(fn ($product) => (string) ($product->product_type ?? '') === 'store')
                ->pluck('id');

            if ($storeProductIds->isNotEmpty()) {
                $galleryRows = DB::table('store_product_images')
                    ->whereIn('store_product_id', $storeProductIds)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['store_product_id', 'image_url']);

                $galleryByProduct = $galleryRows
                    ->groupBy('store_product_id')
                    ->map(function ($rows) {
                        return $rows->pluck('image_url')
                            ->map(fn ($path) => trim((string) $path))
                            ->filter()
                            ->values()
                            ->all();
                    });

                $featuredProducts = $featuredProducts->map(function ($product) use ($galleryByProduct) {
                    if ((string) ($product->product_type ?? '') !== 'store') {
                        $product->gallery_images = [];
                        return $product;
                    }
                    $product->gallery_images = $galleryByProduct->get($product->id, []);
                    return $product;
                });
            } else {
                $featuredProducts = $featuredProducts->map(function ($product) {
                    $product->gallery_images = [];
                    return $product;
                });
            }
        } else {
            $featuredProducts = $featuredProducts->map(function ($product) {
                $product->gallery_images = [];
                return $product;
            });
        }

        $ratingSummaries = $this->userRatings->summariesForUsers(
            $featuredProducts
                ->pluck('seller_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );
        $featuredProducts = $featuredProducts->map(function ($product) use ($ratingSummaries) {
            $sellerId = (int) ($product->seller_id ?? 0);
            $summary = $ratingSummaries->get($sellerId, [
                'average_score' => 0.0,
                'total_reviews' => 0,
            ]);
            $product->seller_rating_avg = (float) ($summary['average_score'] ?? 0);
            $product->seller_rating_total = (int) ($summary['total_reviews'] ?? 0);

            return $product;
        });

        return [
            'marketStats' => $marketStats,
            'featuredProducts' => $featuredProducts,
            'geoSortApplied' => $geoSortApplied,
            'affiliateReadyCount' => $affiliateReadyCount,
        ];
    }

    /**
     * Normalisasi source produk landing berdasarkan role/mode user.
     */
    private function resolveProductSource(string $requestedSource, bool $allowAffiliateSource = false): string
    {
        $source = strtolower(trim($requestedSource));
        $allowedSources = ['all', 'mitra', 'seller'];
        if ($allowAffiliateSource) {
            $allowedSources[] = 'affiliate';
        }

        if (! in_array($source, $allowedSources, true)) {
            return 'all';
        }

        return $source;
    }

    /**
     * Ambil daftar produk store yang sudah aktif dipasarkan affiliate (lock aktif + valid).
     *
     * @return array<int, int>
     */
    private function resolveAffiliateReadyProductIds(int $affiliateUserId): array
    {
        if (
            $affiliateUserId <= 0
            || ! Schema::hasTable('affiliate_locks')
            || ! Schema::hasTable('store_products')
        ) {
            return [];
        }

        $today = now()->toDateString();
        $query = DB::table('affiliate_locks')
            ->join('store_products', 'store_products.id', '=', 'affiliate_locks.product_id')
            ->where('affiliate_locks.affiliate_id', $affiliateUserId)
            ->where('affiliate_locks.is_active', true)
            ->whereDate('affiliate_locks.expiry_date', '>=', $today)
            ->where('store_products.is_affiliate_enabled', true);

        if (Schema::hasColumn('store_products', 'is_active')) {
            $query->where('store_products.is_active', true);
        }

        if (Schema::hasColumn('store_products', 'affiliate_expire_date')) {
            $query->where(function ($builder) use ($today) {
                $builder->whereNull('store_products.affiliate_expire_date')
                    ->orWhereDate('store_products.affiliate_expire_date', '>=', $today);
            });
        }

        return $query
            ->distinct()
            ->pluck('affiliate_locks.product_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn ($id): bool => $id > 0)
            ->values()
            ->all();
    }

    private function resolveWeatherAlert(array $location, ?User $user = null): array
    {
        $fallbackAlert = [
            'severity' => 'green',
            'type' => 'normal',
            'message' => 'Cuaca relatif aman.',
            'valid_until' => null,
        ];

        $forecastAlert = $fallbackAlert;
        try {
            $forecast = $this->weather->forecast(
                (string) ($location['type'] ?? 'custom'),
                (int) ($location['id'] ?? 1),
                (float) ($location['lat'] ?? 0),
                (float) ($location['lng'] ?? 0)
            );

            $alert = $this->weatherAlertEngine->evaluateForecast($forecast);

            if (is_array($alert)) {
                $forecastAlert = array_merge($fallbackAlert, $alert);
            }
        } catch (\Throwable $e) {
            // Silent fallback so marketplace remains available.
        }

        $adminNotice = $this->resolveActiveAdminWeatherNotice($location, $user);
        if (! $adminNotice) {
            return $forecastAlert;
        }

        $noticeSeverity = strtolower(trim((string) ($adminNotice->severity ?? 'unknown')));
        if (! in_array($noticeSeverity, ['green', 'yellow', 'red', 'unknown'], true)) {
            $noticeSeverity = 'unknown';
        }

        $noticeTitle = trim((string) ($adminNotice->title ?? ''));
        $noticeMessage = trim((string) ($adminNotice->message ?? ''));
        $mergedMessage = trim($noticeTitle . ($noticeTitle !== '' && $noticeMessage !== '' ? '. ' : '') . $noticeMessage);
        if ($mergedMessage === '') {
            $mergedMessage = 'Ada pembaruan cuaca dari admin.';
        }

        return [
            'severity' => $noticeSeverity,
            'type' => 'admin_notice',
            'message' => $mergedMessage,
            'valid_until' => $adminNotice->valid_until,
        ];
    }

    private function resolveActiveAdminWeatherNotice(array $location, ?User $user): ?object
    {
        if (! Schema::hasTable('admin_weather_notices')) {
            return null;
        }

        $cityId = (int) ($user?->city_id ?? 0);
        $provinceId = (int) ($user?->province_id ?? 0);

        if ($cityId <= 0 && (string) ($location['type'] ?? '') === 'city') {
            $cityId = (int) ($location['id'] ?? 0);
        }

        if ($provinceId <= 0 && $cityId > 0 && Schema::hasTable('cities')) {
            $provinceId = (int) (DB::table('cities')->where('id', $cityId)->value('province_id') ?? 0);
        }

        $noticeQuery = DB::table('admin_weather_notices')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            });

        $noticeQuery->where(function ($query) use ($cityId, $provinceId) {
            if ($cityId > 0) {
                $query->orWhere('city_id', $cityId);
            }

            if ($provinceId > 0) {
                $query->orWhere(function ($sub) use ($provinceId) {
                    $sub->whereNull('city_id')
                        ->where('province_id', $provinceId);
                });
            }

            $query->orWhere(function ($sub) {
                $sub->whereNull('province_id')
                    ->whereNull('city_id')
                    ->whereNull('district_id');
            });
        });

        return $noticeQuery
            ->orderByRaw("CASE
                WHEN city_id IS NOT NULL THEN 0
                WHEN province_id IS NOT NULL THEN 1
                ELSE 2
            END")
            ->orderByDesc('created_at')
            ->first([
                'severity',
                'title',
                'message',
                'valid_until',
            ]);
    }

    private function buildCartSummary(int $buyerId): array
    {
        if (! Schema::hasTable('cart_items')) {
            return ['items' => 0, 'estimated_total' => 0];
        }

        $this->cartSanitizer->sanitize($buyerId);

        $items = DB::table('cart_items')
            ->where('user_id', $buyerId)
            ->count();

        $storeTotal = DB::table('cart_items')
            ->join('store_products', function ($join) {
                $join->on('store_products.id', '=', 'cart_items.product_id')
                    ->where('cart_items.product_type', '=', 'store');
            })
            ->where('cart_items.user_id', $buyerId)
            ->sum(DB::raw('cart_items.qty * store_products.price'));

        $farmerTotal = 0;
        if (Schema::hasTable('farmer_harvests')) {
            $farmerTotal = DB::table('cart_items')
                ->join('farmer_harvests', function ($join) {
                    $join->on('farmer_harvests.id', '=', 'cart_items.product_id')
                        ->where('cart_items.product_type', '=', 'farmer');
                })
                ->where('cart_items.user_id', $buyerId)
                ->sum(DB::raw('cart_items.qty * farmer_harvests.price'));
        }

        return [
            'items' => (int) $items,
            'estimated_total' => (float) ($storeTotal + $farmerTotal),
        ];
    }

    private function mitraSubmissionState(?User $user): array
    {
        $isOpen = $this->featureFlags->isEnabled('accept_mitra', false);
        $description = $this->featureFlags->description('accept_mitra');

        $message = $description
            ?: ($isOpen
                ? 'Admin sedang membuka pengajuan mitra B2B untuk consumer yang ingin bergabung sebagai mitra pengadaan.'
                : 'Pengajuan mitra sementara ditutup. Tunggu pengumuman admin untuk periode pendaftaran berikutnya.');

        $ctaLabel = null;
        $ctaUrl = null;

        if ($isOpen) {
            if (! $user) {
                $ctaLabel = 'Login untuk Daftar Mitra';
                $ctaUrl = route('landing', ['auth' => 'login']);
            } elseif ($user->isConsumer()) {
                $ctaLabel = 'Daftar Mitra B2B';
                $ctaUrl = URL::temporarySignedRoute('program.mitra.entry', now()->addMinutes(20));
            } else {
                $ctaLabel = 'Buka Dashboard';
                $ctaUrl = route('dashboard');
            }
        }

        return [
            'open' => $isOpen,
            'title' => $isOpen ? 'Pengajuan Mitra Pengadaan Admin Dibuka' : 'Pengajuan Mitra Pengadaan Admin Ditutup',
            'message' => $message,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
        ];
    }

    private function landingAnnouncements()
    {
        if (! Schema::hasTable('marketplace_announcements')) {
            return collect();
        }

        $selects = [
            'id',
            'type',
            'title',
            'message',
            'cta_label',
            'cta_url',
        ];
        if (Schema::hasColumn('marketplace_announcements', 'image_url')) {
            $selects[] = 'image_url';
        }

        return DB::table('marketplace_announcements')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get($selects);
    }

    private function unreadNotificationCount(?User $user): int
    {
        if (! $user || ! Schema::hasTable('notifications')) {
            return 0;
        }

        return (int) $user->unreadNotifications()->count();
    }

    /**
     * @return array{0:\Illuminate\Support\Collection<int, array<string,mixed>>,1:int}
     */
    private function buildWeatherNotificationFeed(?User $user): array
    {
        if (! $user || ! Schema::hasTable('notifications')) {
            return [collect(), 0];
        }

        $query = $user->notifications()
            ->where(function ($innerQuery) {
                $innerQuery->whereIn('type', [
                    AdminWeatherNoticeNotification::class,
                    BehaviorRecommendationNotification::class,
                ])->orWhere('data', 'like', '%"category":"behavior_recommendation"%');
            })
            ->latest();

        $unreadCount = (int) (clone $query)
            ->whereNull('read_at')
            ->count();

        $rows = $query
            ->limit(4)
            ->get()
            ->map(fn ($notification) => $this->formatWeatherNotificationRow($notification))
            ->values();

        return [$rows, $unreadCount];
    }

    /**
     * @return array<string,mixed>
     */
    private function formatWeatherNotificationRow(object $notification): array
    {
        $typeClass = (string) data_get($notification, 'type', '');
        $isRecommendation = $typeClass === BehaviorRecommendationNotification::class
            || (string) data_get($notification, 'data.category', '') === 'behavior_recommendation';
        $status = strtolower(trim((string) data_get($notification, 'data.status', 'unknown')));
        $badgeClass = match ($status) {
            'red' => 'border-rose-200 bg-rose-100 text-rose-700',
            'yellow' => 'border-amber-200 bg-amber-100 text-amber-700',
            'green' => 'border-emerald-200 bg-emerald-100 text-emerald-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };

        $sentAtLabel = '-';
        $sentAtRaw = data_get($notification, 'data.sent_at') ?: data_get($notification, 'created_at');
        if ($sentAtRaw) {
            try {
                $sentAtLabel = Carbon::parse($sentAtRaw)->diffForHumans();
            } catch (\Throwable $e) {
                $sentAtLabel = '-';
            }
        }

        $validUntilLabel = null;
        $validUntilRaw = data_get($notification, 'data.valid_until');
        if ($validUntilRaw) {
            try {
                $validUntilLabel = Carbon::parse($validUntilRaw)->translatedFormat('d M Y H:i');
            } catch (\Throwable $e) {
                $validUntilLabel = null;
            }
        }

        return [
            'id' => (string) data_get($notification, 'id'),
            'type' => $isRecommendation ? 'recommendation' : 'weather',
            'type_label' => $isRecommendation ? 'Rekomendasi' : 'Cuaca',
            'filter_type' => $isRecommendation ? 'recommendation' : 'weather',
            'status' => $status,
            'badge_class' => $badgeClass,
            'title' => trim((string) data_get(
                $notification,
                'data.title',
                $isRecommendation ? 'Rekomendasi Operasional' : 'Notifikasi Cuaca'
            )),
            'message' => trim((string) data_get(
                $notification,
                'data.message',
                $isRecommendation
                    ? 'Ada rekomendasi operasional baru untuk akun Anda.'
                    : 'Ada pembaruan cuaca untuk wilayah Anda.'
            )),
            'target_label' => trim((string) data_get($notification, 'data.target_label', 'Wilayah Akun')),
            'sent_at_label' => $sentAtLabel,
            'valid_until_label' => $validUntilLabel,
            'is_unread' => is_null(data_get($notification, 'read_at')),
        ];
    }

    private function resolveConsumerDemoBalance(?User $user): float
    {
        if (! $user || ! $user->isConsumer()) {
            return 0.0;
        }

        if (
            app()->environment('local')
            && str_ends_with((string) ($user->email ?? ''), '@demo.test')
        ) {
            app(\App\Support\DemoUserProvisioner::class)->ensureUsers();
        }

        if (! Schema::hasTable('wallet_transactions')) {
            return 0.0;
        }

        return $this->walletService->getBalance((int) $user->id);
    }
}
