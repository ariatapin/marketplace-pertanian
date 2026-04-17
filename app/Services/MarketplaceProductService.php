<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketplaceProductService
{
    public function __construct(
        protected UserRatingService $userRatings
    ) {}

    public function normalizeProductType(string $rawType): ?string
    {
        $type = strtolower(trim($rawType));

        return match ($type) {
            'store', 'mitra' => 'store',
            'farmer', 'seller', 'petani' => 'farmer',
            default => null,
        };
    }

    public function normalizeSellerType(string $rawType): ?string
    {
        $type = strtolower(trim($rawType));

        return match ($type) {
            'mitra', 'store' => 'mitra',
            'seller', 'farmer', 'petani' => 'seller',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProduct(string $rawProductType, int $productId): ?array
    {
        $productType = $this->normalizeProductType($rawProductType);
        if ($productType === null || $productId < 1) {
            return null;
        }

        return $productType === 'store'
            ? $this->findStoreProduct($productId)
            : $this->findFarmerProduct($productId);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function productsBySeller(string $rawSellerType, int $sellerId, ?int $excludeProductId = null): Collection
    {
        $sellerType = $this->normalizeSellerType($rawSellerType);
        if ($sellerType === null || $sellerId < 1) {
            return collect();
        }

        return $sellerType === 'mitra'
            ? $this->storeProductsByMitra($sellerId, $excludeProductId)
            : $this->farmerProductsBySeller($sellerId, $excludeProductId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sellerProfile(string $rawSellerType, int $sellerId): ?array
    {
        $sellerType = $this->normalizeSellerType($rawSellerType);
        if ($sellerType === null || $sellerId < 1) {
            return null;
        }

        $row = DB::table('users as seller')
            ->leftJoin('cities as city', 'city.id', '=', 'seller.city_id')
            ->where('seller.id', $sellerId)
            ->select([
                'seller.id',
                'seller.name',
                'seller.email',
                'seller.phone_number',
                'seller.avatar_path',
                'seller.google_avatar',
                'seller.created_at as seller_created_at',
                'city.name as city_name',
                'city.type as city_type',
            ])
            ->first();

        if (! $row) {
            return null;
        }

        $storeName = null;
        if ($sellerType === 'mitra' && Schema::hasTable('mitra_profiles')) {
            $storeName = trim((string) (DB::table('mitra_profiles')
                ->where('user_id', $sellerId)
                ->value('store_name') ?? ''));
        }

        if ($storeName === null || $storeName === '') {
            $storeName = 'Toko ' . trim((string) ($row->name ?? 'Marketplace'));
        }

        $rating = $this->userRatings->summaryForUser($sellerId);
        $location = $this->cityLabel((string) ($row->city_type ?? ''), (string) ($row->city_name ?? ''));
        $productsTotal = $this->countProductsBySeller($sellerType, $sellerId);
        $chatPhoneNumber = trim((string) ($row->phone_number ?? ''));

        return [
            'seller_id' => $sellerId,
            'seller_type' => $sellerType,
            'seller_name' => (string) ($row->name ?? 'User'),
            'store_name' => $storeName,
            'location_label' => $location,
            'rating_avg' => (float) ($rating['average_score'] ?? 0),
            'rating_total' => (int) ($rating['total_reviews'] ?? 0),
            'products_total' => $productsTotal,
            'chat_phone_number' => $chatPhoneNumber,
            'chat_whatsapp_url' => $this->buildWhatsappChatUrl($chatPhoneNumber, $storeName),
            'avatar_url' => $this->resolveSellerAvatarSource(
                (string) ($row->avatar_path ?? ''),
                (string) ($row->google_avatar ?? '')
            ),
            'joined_label' => $this->formatJoinedLabel((string) ($row->seller_created_at ?? '')),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function sellerReviews(int $sellerId, int $limit = 120): Collection
    {
        if ($sellerId < 1 || ! $this->userRatings->isAvailable()) {
            return collect();
        }

        $safeLimit = max(1, min(200, $limit));

        return DB::table('user_ratings')
            ->join('users as buyer', 'buyer.id', '=', 'user_ratings.buyer_id')
            ->where('user_ratings.rated_user_id', $sellerId)
            ->whereNotNull('user_ratings.review')
            ->where('user_ratings.review', '<>', '')
            ->orderByDesc('user_ratings.id')
            ->limit($safeLimit)
            ->get([
                'user_ratings.id',
                'user_ratings.score',
                'user_ratings.review',
                'user_ratings.created_at',
                'buyer.name as reviewer_name',
            ])
            ->map(function ($row): array {
                $reviewerName = trim((string) ($row->reviewer_name ?? 'User'));
                if ($reviewerName === '') {
                    $reviewerName = 'User';
                }

                return [
                    'id' => (int) ($row->id ?? 0),
                    'score' => max(1, min(5, (int) ($row->score ?? 0))),
                    'review' => trim((string) ($row->review ?? '')),
                    'reviewer_name' => $reviewerName,
                    'created_at_label' => $this->formatReviewDate((string) ($row->created_at ?? '')),
                ];
            })
            ->filter(fn (array $item): bool => $item['review'] !== '')
            ->values();
    }

    public function countProductsBySeller(string $rawSellerType, int $sellerId): int
    {
        $sellerType = $this->normalizeSellerType($rawSellerType);
        if ($sellerType === null || $sellerId < 1) {
            return 0;
        }

        if ($sellerType === 'mitra') {
            if (! Schema::hasTable('store_products')) {
                return 0;
            }

            $query = DB::table('store_products')->where('mitra_id', $sellerId);
            if (Schema::hasColumn('store_products', 'is_active')) {
                $query->where('is_active', true);
            }

            return (int) $query->count();
        }

        if (! Schema::hasTable('farmer_harvests')) {
            return 0;
        }

        return (int) DB::table('farmer_harvests')
            ->where('farmer_id', $sellerId)
            ->where('status', 'approved')
            ->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStoreProduct(int $productId): ?array
    {
        if (! Schema::hasTable('store_products')) {
            return null;
        }

        $query = DB::table('store_products')
            ->join('users as seller', 'seller.id', '=', 'store_products.mitra_id')
            ->leftJoin('cities as city', 'city.id', '=', 'seller.city_id');

        if (Schema::hasTable('mitra_profiles')) {
            $query->leftJoin('mitra_profiles', 'mitra_profiles.user_id', '=', 'seller.id');
        }

        $selects = [
            'store_products.id',
            'store_products.name',
            'store_products.description',
            'store_products.price',
            'store_products.stock_qty',
            'store_products.image_url',
            'store_products.updated_at',
            'store_products.mitra_id as seller_id',
            'seller.name as seller_name',
            'seller.email as seller_email',
            'seller.avatar_path as seller_avatar_path',
            'seller.google_avatar as seller_google_avatar',
            'city.name as city_name',
            'city.type as city_type',
        ];

        if (Schema::hasColumn('store_products', 'unit')) {
            $selects[] = 'store_products.unit';
        } else {
            $selects[] = DB::raw("'kg' as unit");
        }

        if (Schema::hasColumn('store_products', 'is_active')) {
            $selects[] = 'store_products.is_active';
        } else {
            $selects[] = DB::raw('true as is_active');
        }

        if (Schema::hasTable('mitra_profiles')) {
            $selects[] = 'mitra_profiles.store_name';
        } else {
            $selects[] = DB::raw('NULL as store_name');
        }

        $row = $query
            ->where('store_products.id', $productId)
            ->first($selects);

        if (! $row) {
            return null;
        }

        $galleryImages = $this->storeGalleryImages($productId);
        $storeName = trim((string) ($row->store_name ?? ''));
        if ($storeName === '') {
            $storeName = 'Toko ' . trim((string) ($row->seller_name ?? 'Mitra'));
        }

        return $this->mapProductRow(
            row: $row,
            productType: 'store',
            sellerType: 'mitra',
            storeName: $storeName,
            galleryImages: $galleryImages
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findFarmerProduct(int $productId): ?array
    {
        if (! Schema::hasTable('farmer_harvests')) {
            return null;
        }

        $row = DB::table('farmer_harvests')
            ->join('users as seller', 'seller.id', '=', 'farmer_harvests.farmer_id')
            ->leftJoin('cities as city', 'city.id', '=', 'seller.city_id')
            ->where('farmer_harvests.id', $productId)
            ->first([
                'farmer_harvests.id',
                'farmer_harvests.name',
                'farmer_harvests.description',
                'farmer_harvests.price',
                'farmer_harvests.stock_qty',
                'farmer_harvests.image_url',
                'farmer_harvests.status',
                'farmer_harvests.updated_at',
                'farmer_harvests.farmer_id as seller_id',
                'seller.name as seller_name',
                'seller.email as seller_email',
                'seller.avatar_path as seller_avatar_path',
                'seller.google_avatar as seller_google_avatar',
                'city.name as city_name',
                'city.type as city_type',
            ]);

        if (! $row) {
            return null;
        }

        $storeName = 'Toko ' . trim((string) ($row->seller_name ?? 'Penjual'));

        return $this->mapProductRow(
            row: $row,
            productType: 'farmer',
            sellerType: 'seller',
            storeName: $storeName,
            galleryImages: []
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function storeProductsByMitra(int $mitraId, ?int $excludeProductId = null): Collection
    {
        if (! Schema::hasTable('store_products')) {
            return collect();
        }

        $query = DB::table('store_products')
            ->join('users as seller', 'seller.id', '=', 'store_products.mitra_id')
            ->leftJoin('cities as city', 'city.id', '=', 'seller.city_id');

        if (Schema::hasTable('mitra_profiles')) {
            $query->leftJoin('mitra_profiles', 'mitra_profiles.user_id', '=', 'seller.id');
        }

        $query->where('store_products.mitra_id', $mitraId);
        if (Schema::hasColumn('store_products', 'is_active')) {
            // Hanya tampilkan listing yang diaktifkan Mitra.
            $query->where('store_products.is_active', true);
        }
        if ($excludeProductId && $excludeProductId > 0) {
            $query->where('store_products.id', '<>', $excludeProductId);
        }

        $selects = [
            'store_products.id',
            'store_products.name',
            'store_products.description',
            'store_products.price',
            'store_products.stock_qty',
            'store_products.image_url',
            'store_products.updated_at',
            'store_products.mitra_id as seller_id',
            'seller.name as seller_name',
            'seller.email as seller_email',
            'seller.avatar_path as seller_avatar_path',
            'seller.google_avatar as seller_google_avatar',
            'city.name as city_name',
            'city.type as city_type',
        ];

        if (Schema::hasColumn('store_products', 'unit')) {
            $selects[] = 'store_products.unit';
        } else {
            $selects[] = DB::raw("'kg' as unit");
        }

        if (Schema::hasColumn('store_products', 'is_active')) {
            $selects[] = 'store_products.is_active';
        } else {
            $selects[] = DB::raw('true as is_active');
        }

        if (Schema::hasTable('mitra_profiles')) {
            $selects[] = 'mitra_profiles.store_name';
        } else {
            $selects[] = DB::raw('NULL as store_name');
        }

        return $query
            ->orderByDesc('store_products.updated_at')
            ->limit(60)
            ->get($selects)
            ->map(function ($row) {
                $storeName = trim((string) ($row->store_name ?? ''));
                if ($storeName === '') {
                    $storeName = 'Toko ' . trim((string) ($row->seller_name ?? 'Mitra'));
                }

                return $this->mapProductRow(
                    row: $row,
                    productType: 'store',
                    sellerType: 'mitra',
                    storeName: $storeName,
                    galleryImages: []
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function farmerProductsBySeller(int $sellerId, ?int $excludeProductId = null): Collection
    {
        if (! Schema::hasTable('farmer_harvests')) {
            return collect();
        }

        $query = DB::table('farmer_harvests')
            ->join('users as seller', 'seller.id', '=', 'farmer_harvests.farmer_id')
            ->leftJoin('cities as city', 'city.id', '=', 'seller.city_id')
            ->where('farmer_harvests.farmer_id', $sellerId)
            ->where('farmer_harvests.status', 'approved');

        if ($excludeProductId && $excludeProductId > 0) {
            $query->where('farmer_harvests.id', '<>', $excludeProductId);
        }

        return $query
            ->orderByDesc('farmer_harvests.updated_at')
            ->limit(60)
            ->get([
                'farmer_harvests.id',
                'farmer_harvests.name',
                'farmer_harvests.description',
                'farmer_harvests.price',
                'farmer_harvests.stock_qty',
                'farmer_harvests.image_url',
                'farmer_harvests.status',
                'farmer_harvests.updated_at',
                'farmer_harvests.farmer_id as seller_id',
                'seller.name as seller_name',
                'seller.email as seller_email',
                'seller.avatar_path as seller_avatar_path',
                'seller.google_avatar as seller_google_avatar',
                'city.name as city_name',
                'city.type as city_type',
            ])
            ->map(function ($row) {
                return $this->mapProductRow(
                    row: $row,
                    productType: 'farmer',
                    sellerType: 'seller',
                    storeName: 'Toko ' . trim((string) ($row->seller_name ?? 'Penjual')),
                    galleryImages: []
                );
            })
            ->values();
    }

    /**
     * @param  array<int, string>  $galleryImages
     * @return array<string, mixed>
     */
    private function mapProductRow(object $row, string $productType, string $sellerType, string $storeName, array $galleryImages): array
    {
        $stockQty = max(0, (int) ($row->stock_qty ?? 0));
        $unit = strtolower(trim((string) ($row->unit ?? 'kg')));
        if ($unit === '') {
            $unit = 'kg';
        }

        $isActive = $productType === 'store'
            ? (bool) ($row->is_active ?? true)
            : strtolower(trim((string) ($row->status ?? 'approved'))) === 'approved';

        $sellerId = (int) ($row->seller_id ?? 0);
        $rating = $this->userRatings->summaryForUser($sellerId);
        $primaryImage = $this->resolveImageSource((string) ($row->image_url ?? ''));
        $gallery = collect([$primaryImage])
            ->merge($galleryImages)
            ->filter(fn ($image) => trim((string) $image) !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'id' => (int) ($row->id ?? 0),
            'product_type' => $productType,
            'seller_type' => $sellerType,
            'seller_id' => $sellerId,
            'seller_name' => trim((string) ($row->seller_name ?? 'User')),
            'seller_avatar_url' => $this->resolveSellerAvatarSource(
                (string) ($row->seller_avatar_path ?? ''),
                (string) ($row->seller_google_avatar ?? '')
            ),
            'store_name' => trim($storeName) !== '' ? trim($storeName) : 'Toko Marketplace',
            'seller_location_label' => $this->cityLabel((string) ($row->city_type ?? ''), (string) ($row->city_name ?? '')),
            'name' => trim((string) ($row->name ?? 'Produk')),
            'description' => trim((string) ($row->description ?? '')),
            'description_long' => trim((string) ($row->description ?? '')) !== ''
                ? trim((string) ($row->description ?? ''))
                : 'Deskripsi produk belum diisi oleh pemilik toko.',
            'price' => (float) ($row->price ?? 0),
            'price_label' => 'Rp' . number_format((float) ($row->price ?? 0), 0, ',', '.'),
            'stock_qty' => $stockQty,
            'stock_label' => number_format($stockQty, 0, ',', '.') . ' ' . $unit,
            'unit' => $unit,
            'is_active' => $isActive,
            'can_buy' => $isActive && $stockQty > 0,
            'seller_rating_avg' => (float) ($rating['average_score'] ?? 0),
            'seller_rating_total' => (int) ($rating['total_reviews'] ?? 0),
            'image_src' => $primaryImage,
            'gallery_images' => $gallery,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function storeGalleryImages(int $productId): array
    {
        if ($productId < 1 || ! Schema::hasTable('store_product_images')) {
            return [];
        }

        return DB::table('store_product_images')
            ->where('store_product_id', $productId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('image_url')
            ->map(fn ($path) => $this->resolveImageSource((string) $path))
            ->filter(fn ($path) => trim((string) $path) !== '')
            ->values()
            ->all();
    }

    private function cityLabel(string $cityType, string $cityName): ?string
    {
        $cityName = trim($cityName);
        if ($cityName === '') {
            return null;
        }

        return trim((trim($cityType) !== '' ? trim($cityType) . ' ' : '') . $cityName);
    }

    private function formatReviewDate(string $rawDate): string
    {
        $rawDate = trim($rawDate);
        if ($rawDate === '') {
            return '-';
        }

        try {
            return Carbon::parse($rawDate)->format('d M Y');
        } catch (\Throwable) {
            return '-';
        }
    }

    private function resolveImageSource(string $image): string
    {
        $image = trim($image);
        if ($image === '') {
            return $this->defaultImageSource();
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

    private function resolveSellerAvatarSource(string $avatarPath, string $googleAvatar): ?string
    {
        $avatarPath = trim($avatarPath);
        if ($avatarPath !== '') {
            if (Str::startsWith($avatarPath, ['http://', 'https://'])) {
                return $avatarPath;
            }

            if (Str::startsWith($avatarPath, ['/storage/', 'storage/'])) {
                return asset(ltrim($avatarPath, '/'));
            }

            if (Storage::disk('public')->exists(ltrim($avatarPath, '/'))) {
                return asset('storage/' . ltrim($avatarPath, '/'));
            }
        }

        $googleAvatar = trim($googleAvatar);
        if ($googleAvatar !== '') {
            return $googleAvatar;
        }

        return null;
    }

    private function formatJoinedLabel(string $joinedAt): string
    {
        $joinedAt = trim($joinedAt);
        if ($joinedAt === '') {
            return 'Belum tersedia';
        }

        try {
            $joined = Carbon::parse($joinedAt);
        } catch (\Throwable) {
            return 'Belum tersedia';
        }

        $years = $joined->diffInYears(now());
        if ($years > 0) {
            return $years . ' tahun lalu';
        }

        $months = $joined->diffInMonths(now());
        if ($months > 0) {
            return $months . ' bulan lalu';
        }

        $days = max(1, $joined->diffInDays(now()));

        return $days . ' hari lalu';
    }

    private function buildWhatsappChatUrl(string $phoneNumber, string $storeName): ?string
    {
        $normalized = $this->normalizeWhatsappPhone($phoneNumber);
        if ($normalized === null) {
            return null;
        }

        $storeName = trim($storeName);
        $message = $storeName !== ''
            ? 'Halo ' . $storeName . ', saya ingin tanya produk di marketplace.'
            : 'Halo, saya ingin tanya produk di marketplace.';

        return 'https://wa.me/' . $normalized . '?text=' . rawurlencode($message);
    }

    private function normalizeWhatsappPhone(string $phoneNumber): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', trim($phoneNumber));
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (Str::startsWith($digits, '+')) {
            $digits = ltrim($digits, '+');
        } elseif (Str::startsWith($digits, '0')) {
            $digits = '62' . ltrim($digits, '0');
        }

        $digits = preg_replace('/[^0-9]/', '', $digits);
        if (! is_string($digits) || $digits === '' || strlen($digits) < 9) {
            return null;
        }

        return $digits;
    }
}
