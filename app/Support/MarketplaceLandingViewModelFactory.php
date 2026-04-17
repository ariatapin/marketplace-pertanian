<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MarketplaceLandingViewModelFactory
{
    public function make(?User $user, array $payload): array
    {
        // CATATAN-AUDIT: Factory landing menyatukan state guest/consumer/seller/affiliate agar UI shell tetap konsisten.
        $role = $payload['currentRole'] ?? ($user?->role);
        $isActiveConsumerDashboard = (bool) ($payload['isActiveConsumerDashboard'] ?? false);
        $summary = array_merge([
            'mode' => 'buyer',
            'mode_status' => 'none',
            'requested_mode' => null,
            'location_label' => 'Belum diset',
            'total_orders' => 0,
            'active_orders' => 0,
            'completed_orders' => 0,
        ], (array) ($payload['consumerSummary'] ?? []));

        $marketStats = array_merge([
            'in_stock_products' => 0,
            'active_sellers' => 0,
            'average_price' => 0,
        ], (array) ($payload['marketStats'] ?? []));

        $searchKeyword = trim((string) ($payload['searchKeyword'] ?? ''));
        $canUseAffiliateProductFilter = (bool) ($payload['canUseAffiliateProductFilter'] ?? false);
        $affiliateSelfReferralCode = trim((string) ($payload['affiliateSelfReferralCode'] ?? ''));
        $focusProductId = max(0, (int) ($payload['focusProductId'] ?? 0));
        $productSource = strtolower(trim((string) ($payload['productSource'] ?? 'all')));
        $allowedSources = ['all', 'mitra', 'seller'];
        if ($canUseAffiliateProductFilter) {
            $allowedSources[] = 'affiliate';
        }
        if (! in_array($productSource, $allowedSources, true)) {
            $productSource = 'all';
        }
        $affiliateReadyOnly = $canUseAffiliateProductFilter
            && $productSource === 'affiliate'
            && (bool) ($payload['affiliateReadyOnly'] ?? false);
        $affiliateReadyCount = max(0, (int) ($payload['affiliateReadyCount'] ?? 0));
        $unreadNotifications = (int) ($payload['unreadNotifications'] ?? 0);
        $viewerLocationLabel = trim((string) ($payload['viewerLocationLabel'] ?? ''));
        $geoSortApplied = (bool) ($payload['geoSortApplied'] ?? false);
        $weatherAlert = array_merge([
            'severity' => 'green',
            'message' => 'Cuaca relatif aman.',
        ], (array) ($payload['weatherAlert'] ?? []));

        $cartSummary = array_merge([
            'items' => 0,
            'estimated_total' => 0,
        ], (array) ($payload['cartSummary'] ?? []));

        $mitraSubmission = array_merge([
            'open' => false,
            'title' => 'Pengajuan Mitra Ditutup',
            'message' => 'Admin belum membuka pengajuan mitra.',
            'cta_label' => null,
            'cta_url' => null,
        ], (array) ($payload['mitraSubmission'] ?? []));
        $activeAffiliateReferral = $payload['activeAffiliateReferral'] ?? null;
        if (! is_array($activeAffiliateReferral)) {
            $activeAffiliateReferral = null;
        }

        $featuredProducts = $payload['featuredProducts'] ?? collect();
        if (! $featuredProducts instanceof Collection) {
            $featuredProducts = collect($featuredProducts);
        }

        $featuredProductCards = $featuredProducts->map(function ($product) use ($featuredProducts, $canUseAffiliateProductFilter, $affiliateSelfReferralCode, $focusProductId): array {
            $productType = strtolower(trim((string) data_get($product, 'product_type', 'store')));
            if (! in_array($productType, ['store', 'farmer'], true)) {
                $productType = 'store';
            }
            $sellerType = strtolower(trim((string) data_get($product, 'seller_kind', $productType === 'farmer' ? 'seller' : 'mitra')));
            if (! in_array($sellerType, ['mitra', 'seller'], true)) {
                $sellerType = $productType === 'farmer' ? 'seller' : 'mitra';
            }

            $stockValue = max(0, (int) data_get($product, 'stock_qty', 0));
            $imageSrc = $this->resolveImageSource((string) data_get($product, 'image_url', ''));
            $productId = (int) data_get($product, 'id', 0);
            $sellerId = (int) data_get($product, 'seller_id', data_get($product, 'mitra_id', 0));
            $unitLabel = strtolower(trim((string) data_get($product, 'unit', 'kg')));
            if ($unitLabel === 'lt') {
                $unitLabel = 'liter';
            }
            if ($unitLabel === '') {
                $unitLabel = 'kg';
            }
            $distanceRaw = data_get($product, 'distance_km');
            $distanceKm = is_numeric($distanceRaw) ? (float) $distanceRaw : null;
            $distanceLabel = null;
            if ($distanceKm !== null) {
                if ($distanceKm < 1) {
                    $distanceLabel = '< 1 km';
                } else {
                    $distanceLabel = number_format($distanceKm, $distanceKm < 10 ? 1 : 0, ',', '.') . ' km';
                }
            }

            $cityName = trim((string) data_get($product, 'seller_city_name', data_get($product, 'mitra_city_name', '')));
            $cityType = trim((string) data_get($product, 'seller_city_type', data_get($product, 'mitra_city_type', '')));
            $sellerLocationLabel = $cityName !== ''
                ? trim(($cityType !== '' ? $cityType . ' ' : '') . $cityName)
                : null;

            $productGalleryImages = collect(data_get($product, 'gallery_images', []))
                ->map(fn ($path) => $this->resolveImageSource((string) $path))
                ->filter()
                ->unique()
                ->values();

            $relatedImages = $featuredProducts
                ->filter(function ($candidate) use ($productId, $productType, $sellerId) {
                    return (int) data_get($candidate, 'id', 0) !== $productId
                        && strtolower((string) data_get($candidate, 'product_type', 'store')) === $productType
                        && (int) data_get($candidate, 'seller_id', data_get($candidate, 'mitra_id', 0)) === $sellerId;
                })
                ->map(fn ($candidate) => $this->resolveImageSource((string) data_get($candidate, 'image_url', '')))
                ->filter()
                ->unique()
                ->take(6)
                ->values();

            $galleryImages = collect();
            if ($imageSrc !== '') {
                $galleryImages->push($imageSrc);
            }
            foreach ($productGalleryImages as $galleryImage) {
                $galleryImages->push($galleryImage);
            }
            foreach ($relatedImages as $relatedImage) {
                $galleryImages->push($relatedImage);
            }
            $galleryImages = $galleryImages->filter()->unique()->values();

            $sellerName = trim((string) data_get($product, 'seller_name', data_get($product, 'mitra_name', '')));
            if ($sellerName === '') {
                $sellerName = $sellerType === 'seller' ? 'Penjual Hasil Tani' : 'Mitra Toko';
            }
            $sellerLabel = $sellerType === 'seller' ? 'Penjual' : 'Mitra';
            $sellerRatingAvg = max(0, min(5, (float) data_get($product, 'seller_rating_avg', 0)));
            $sellerRatingTotal = max(0, (int) data_get($product, 'seller_rating_total', 0));
            $isAffiliateProduct = $productType === 'store' && (bool) data_get($product, 'is_affiliate_enabled', false);
            $affiliateExpireDate = trim((string) data_get($product, 'affiliate_expire_date', ''));
            if ($isAffiliateProduct && $affiliateExpireDate !== '') {
                try {
                    $isAffiliateProduct = Carbon::parse($affiliateExpireDate)->endOfDay()->greaterThanOrEqualTo(now());
                } catch (\Throwable) {
                    $isAffiliateProduct = false;
                }
            }
            $affiliateShareUrl = null;
            if ($canUseAffiliateProductFilter && $isAffiliateProduct && $affiliateSelfReferralCode !== '') {
                $affiliateShareUrl = route('marketplace.product.show', [
                    'productType' => $productType,
                    'productId' => $productId,
                    'ref' => $affiliateSelfReferralCode,
                    'source' => 'affiliate',
                ]);
            }

            return [
                'id' => $productId,
                'product_type' => $productType,
                'name' => (string) data_get($product, 'name', ''),
                'description' => (string) (data_get($product, 'description') ?: 'Produk marketplace tersedia untuk kebutuhan tani.'),
                'price' => (float) data_get($product, 'price', 0),
                'price_label' => 'Rp' . number_format((float) data_get($product, 'price', 0), 0, ',', '.'),
                'stock_qty' => $stockValue,
                'unit' => $unitLabel,
                'stock_label' => number_format($stockValue, 0, ',', '.') . ' ' . $unitLabel,
                'seller_type' => $sellerType,
                'seller_label' => $sellerLabel,
                'seller_name' => $sellerName,
                'seller_id' => $sellerId,
                'mitra_name' => $sellerName,
                'mitra_id' => $sellerId,
                'seller_rating_avg' => $sellerRatingAvg,
                'seller_rating_total' => $sellerRatingTotal,
                'image_src' => $imageSrc,
                'gallery_images' => $galleryImages->all(),
                'distance_km' => $distanceKm,
                'distance_label' => $distanceLabel,
                'seller_location_label' => $sellerLocationLabel,
                'is_affiliate_product' => $isAffiliateProduct,
                'show_affiliate_badge' => $canUseAffiliateProductFilter && $isAffiliateProduct,
                'is_marketed_by_affiliate' => (bool) data_get($product, 'is_marketed_by_affiliate', false),
                'affiliate_share_url' => $affiliateShareUrl,
                'is_focus_product' => $focusProductId > 0 && $productId === $focusProductId,
            ];
        })->values();

        $heroAnnouncements = $payload['heroAnnouncements'] ?? collect();
        if (! $heroAnnouncements instanceof Collection) {
            $heroAnnouncements = collect($heroAnnouncements);
        }

        $heroAnnouncementCards = $heroAnnouncements->map(function ($announcement): array {
            $type = strtolower((string) data_get($announcement, 'type', 'info'));
            $meta = $this->announcementTypeMeta($type);
            $ctaUrl = trim((string) data_get($announcement, 'cta_url', ''));
            $imageSrc = $this->resolveImageSource((string) data_get($announcement, 'image_url', ''));

            return [
                'id' => (int) data_get($announcement, 'id', 0),
                'type' => $type,
                'type_label' => $meta['label'],
                'type_class' => $meta['class'],
                'title' => (string) data_get($announcement, 'title', ''),
                'message' => (string) data_get($announcement, 'message', ''),
                'image_src' => $imageSrc,
                'cta_label' => trim((string) data_get($announcement, 'cta_label', '')),
                'cta_url' => $ctaUrl,
                'is_external_cta' => Str::startsWith($ctaUrl, ['http://', 'https://']),
            ];
        })->values();

        $weatherLocationLabel = 'Atur lokasi agar prediksi cuaca lebih akurat';
        if ($viewerLocationLabel !== '') {
            $weatherLocationLabel = $viewerLocationLabel;
        } elseif ($isActiveConsumerDashboard && ! empty($summary['location_label'])) {
            $weatherLocationLabel = (string) $summary['location_label'];
        } elseif ($user) {
            $weatherLocationLabel = 'Lokasi akun aktif';
        }

        $weatherSeverity = strtolower((string) ($weatherAlert['severity'] ?? 'green'));
        $weatherBadgeClass = match ($weatherSeverity) {
            'red' => 'bg-rose-100 text-rose-700',
            'yellow' => 'bg-amber-100 text-amber-700',
            default => 'bg-emerald-100 text-emerald-700',
        };
        $weatherSeverityLabel = strtoupper($weatherSeverity);

        $searchSuggestions = $featuredProductCards
            ->pluck('name')
            ->filter()
            ->take(7)
            ->map(fn ($name) => Str::limit((string) $name, 18))
            ->values();
        $sourceTabs = [
            ['key' => 'all', 'label' => 'Semua Produk'],
            ['key' => 'seller', 'label' => 'Produk Petani'],
            ['key' => 'mitra', 'label' => 'Produk Mitra'],
        ];
        if ($canUseAffiliateProductFilter) {
            $sourceTabs[] = ['key' => 'affiliate', 'label' => 'Produk Affiliate'];
        }

        $accountMenu = null;
        if ($user) {
            $name = (string) ($user->name ?? 'User');
            $accountMenu = [
                'name' => $name,
                'email' => (string) ($user->email ?? '-'),
                'avatar_url' => $user->avatarImageUrl(),
                'avatar_initial' => $user->avatarInitial(),
                'notification_count' => $unreadNotifications,
            ];
        }

        return [
            'role' => $role,
            'isActiveConsumerDashboard' => $isActiveConsumerDashboard,
            'consumerSummary' => $summary,
            'marketStats' => $marketStats,
            'featuredProductCards' => $featuredProductCards,
            'featuredProductsCount' => $featuredProductCards->count(),
            'searchKeyword' => $searchKeyword,
            'productSource' => $productSource,
            'affiliateReadyOnly' => $affiliateReadyOnly,
            'affiliateReadyCount' => $affiliateReadyCount,
            'sourceTabs' => $sourceTabs,
            'canUseAffiliateProductFilter' => $canUseAffiliateProductFilter,
            'cartSummary' => $cartSummary,
            'heroAnnouncementCards' => $heroAnnouncementCards,
            'mitraSubmission' => $mitraSubmission,
            'activeAffiliateReferral' => $activeAffiliateReferral,
            'unreadNotifications' => $unreadNotifications,
            'searchSuggestions' => $searchSuggestions,
            'weatherLocationLabel' => $weatherLocationLabel,
            'geoSortApplied' => $geoSortApplied,
            'weatherAlertMessage' => (string) ($weatherAlert['message'] ?? 'Cuaca relatif aman.'),
            'weatherAlertSeverityLabel' => $weatherSeverityLabel,
            'weatherAlertBadgeClass' => $weatherBadgeClass,
            'accountMenu' => $accountMenu,
            'cartTarget' => $user
                // CATATAN-AUDIT: Consumer diarahkan ke keranjang, non-consumer ke dashboard role, guest ke modal login landing.
                ? (($role === 'consumer') ? route('cart.index') : route('dashboard'))
                : route('landing', ['auth' => 'login']),
        ];
    }

    private function announcementTypeMeta(string $type): array
    {
        return match ($type) {
            'banner' => [
                'label' => 'BANNER',
                'class' => 'border-cyan-200/60 bg-cyan-100/20 text-cyan-50',
            ],
            'promo' => [
                'label' => 'PROMO',
                'class' => 'border-amber-200/60 bg-amber-100/20 text-amber-50',
            ],
            default => [
                'label' => 'INFO',
                'class' => 'border-emerald-200/60 bg-emerald-100/20 text-emerald-50',
            ],
        };
    }

    private function resolveImageSource(string $image): string
    {
        $image = trim($image);
        if ($image === '') {
            return '';
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        if (Str::startsWith($image, ['/storage/', 'storage/'])) {
            $relativePath = ltrim(Str::replaceFirst('storage/', '', ltrim($image, '/')), '/');
            if ($relativePath !== '' && Storage::disk('public')->exists($relativePath)) {
                return asset('storage/' . $relativePath);
            }

            return $this->defaultImageSource();
        }

        if (Storage::disk('public')->exists(ltrim($image, '/'))) {
            return asset('storage/' . ltrim($image, '/'));
        }

        if (Str::startsWith($image, '/')) {
            $publicPath = public_path(ltrim($image, '/'));
            if (is_file($publicPath)) {
                return $image;
            }
        }

        return $this->defaultImageSource();
    }

    private function defaultImageSource(): string
    {
        return asset('images/product-placeholder.svg');
    }
}
