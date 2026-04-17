<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\StoreProduct;
use App\Services\AffiliateCommissionPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class StoreProductController extends Controller
{
    private const REACTIVATION_LOCK_DAYS = 7;
    private const MIN_GALLERY_IMAGES_FOR_ACTIVATION = 3;
    private const MAX_GALLERY_IMAGES = 5;
    private const MIN_ADMIN_SOURCE_MARGIN = 1000;
    private const ALLOWED_UNITS = [
        'kg',
        'gram',
        'liter',
        'ml',
        'pcs',
        'pack',
        'ikat',
    ];

    public function __construct(
        protected AffiliateCommissionPolicyService $affiliateCommissionPolicy
    )
    {
        $this->authorizeResource(StoreProduct::class, 'product');
    }

    private function hasActiveColumn(): bool
    {
        return Schema::hasColumn('store_products', 'is_active');
    }

    private function hasSourceColumn(): bool
    {
        return Schema::hasColumn('store_products', 'source_admin_product_id');
    }

    /**
     * @return array{
     *   is_enforced:bool,
     *   base_price:float,
     *   min_price:float
     * }
     */
    private function adminSourcePricingContext(StoreProduct $product): array
    {
        if (! $this->hasSourceColumn() || ! Schema::hasTable('admin_products')) {
            return [
                'is_enforced' => false,
                'base_price' => 0,
                'min_price' => 0,
            ];
        }

        $sourceAdminProductId = (int) ($product->source_admin_product_id ?? 0);
        if ($sourceAdminProductId <= 0) {
            return [
                'is_enforced' => false,
                'base_price' => 0,
                'min_price' => 0,
            ];
        }

        $basePriceRaw = DB::table('admin_products')
            ->where('id', $sourceAdminProductId)
            ->value('price');

        if ($basePriceRaw === null) {
            return [
                'is_enforced' => false,
                'base_price' => 0,
                'min_price' => 0,
            ];
        }

        $basePrice = (float) $basePriceRaw;

        return [
            'is_enforced' => true,
            'base_price' => $basePrice,
            'min_price' => $basePrice + self::MIN_ADMIN_SOURCE_MARGIN,
        ];
    }

    private function enforceAdminSourceMinimumPrice(StoreProduct $product, float $requestedPrice): void
    {
        $pricingContext = $this->adminSourcePricingContext($product);
        if (! (bool) ($pricingContext['is_enforced'] ?? false)) {
            return;
        }

        $minimumPrice = (float) ($pricingContext['min_price'] ?? 0);
        if ($requestedPrice >= $minimumPrice) {
            return;
        }

        $basePrice = (float) ($pricingContext['base_price'] ?? 0);
        $basePriceLabel = number_format($basePrice, 0, ',', '.');
        $minimumPriceLabel = number_format($minimumPrice, 0, ',', '.');
        $marginLabel = number_format(self::MIN_ADMIN_SOURCE_MARGIN, 0, ',', '.');

        throw ValidationException::withMessages([
            'price' => "Harga jual minimal produk pengadaan Admin adalah Rp {$minimumPriceLabel} (harga Admin Rp {$basePriceLabel} + margin minimal Rp {$marginLabel}).",
        ]);
    }

    private function hasReactivationColumn(): bool
    {
        return Schema::hasColumn('store_products', 'reactivation_available_at');
    }

    private function hasUnitColumn(): bool
    {
        return Schema::hasColumn('store_products', 'unit');
    }

    private function hasGalleryTable(): bool
    {
        return Schema::hasTable('store_product_images');
    }

    private function hasAffiliateLocksTable(): bool
    {
        return Schema::hasTable('affiliate_locks');
    }

    private function hasAffiliateExpireColumn(): bool
    {
        return Schema::hasColumn('store_products', 'affiliate_expire_date');
    }

    /**
     * @return array<int, string>
     */
    private function affiliateExpiryRules(bool $required): array
    {
        $rules = ['nullable', 'date', 'after_or_equal:today'];

        if ($required) {
            array_unshift($rules, 'required');
        }

        return $rules;
    }

    /**
     * @return array{min:float,max:float}
     */
    private function affiliateCommissionRange(): array
    {
        return $this->affiliateCommissionPolicy->resolveRange();
    }

    /**
     * @return array<int, string>
     */
    private function affiliateCommissionRules(bool $required, array $range, bool $enforceRangeWhenOptional = false): array
    {
        if (! $required && ! $enforceRangeWhenOptional) {
            return ['nullable', 'numeric'];
        }

        return array_merge(
            [$required ? 'required' : 'nullable'],
            $this->affiliateCommissionPolicy->valueRules($range)
        );
    }

    /**
     * @return array<string, string>
     */
    private function affiliateCommissionMessages(array $range): array
    {
        $minLabel = $this->affiliateCommissionPolicy->formatPercent((float) ($range['min'] ?? 0));
        $maxLabel = $this->affiliateCommissionPolicy->formatPercent((float) ($range['max'] ?? 100));

        return [
            'affiliate_commission.required' => 'Komisi affiliate wajib diisi saat affiliate diaktifkan.',
            'affiliate_commission.min' => "Komisi affiliate minimal {$minLabel}% sesuai batas Admin.",
            'affiliate_commission.max' => "Komisi affiliate maksimal {$maxLabel}% sesuai batas Admin.",
        ];
    }

    private function assertAffiliateCommissionInRange(float $commission, array $range): void
    {
        $min = (float) ($range['min'] ?? 0);
        $max = (float) ($range['max'] ?? 100);

        if ($commission < $min || $commission > $max) {
            $minLabel = $this->affiliateCommissionPolicy->formatPercent($min);
            $maxLabel = $this->affiliateCommissionPolicy->formatPercent($max);

            throw ValidationException::withMessages([
                'affiliate_commission' => "Komisi affiliate harus di antara {$minLabel}% sampai {$maxLabel}% sesuai batas Admin.",
            ]);
        }
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isAffiliateContractLocked(?string $contractExpireDate): bool
    {
        if ($contractExpireDate === null) {
            return false;
        }

        return $contractExpireDate >= now()->toDateString();
    }

    private function composeAffiliateLockMessage(int $activeAffiliateLockCount, ?string $contractExpireDate, bool $affiliateEnabled): string
    {
        $segments = [];

        if ($activeAffiliateLockCount > 0) {
            $segments[] = "masih ada {$activeAffiliateLockCount} affiliate aktif memasarkan produk ini";
        }

        if ($affiliateEnabled && $this->isAffiliateContractLocked($contractExpireDate)) {
            $segments[] = 'masa lock affiliate berlaku sampai ' . Carbon::parse($contractExpireDate)->translatedFormat('d M Y');
        }

        if (empty($segments)) {
            return '';
        }

        return ucfirst(implode(' dan ', $segments)) . '.';
    }

    /**
     * @return array{
     *   active_count:int,
     *   contract_expire_date:?string,
     *   contract_locked:bool,
     *   is_locked:bool,
     *   message:string
     * }
     */
    private function affiliateLockContext(StoreProduct $product): array
    {
        $activeAffiliateLockCount = $this->activeAffiliateLockCount((int) $product->id);
        $affiliateEnabled = (bool) ($product->is_affiliate_enabled ?? false);
        $contractExpireDate = $this->hasAffiliateExpireColumn()
            ? $this->normalizeDateValue($product->affiliate_expire_date ?? null)
            : null;
        $contractLocked = $affiliateEnabled && $this->isAffiliateContractLocked($contractExpireDate);
        $isLocked = $activeAffiliateLockCount > 0 || $contractLocked;

        return [
            'active_count' => $activeAffiliateLockCount,
            'contract_expire_date' => $contractExpireDate,
            'contract_locked' => $contractLocked,
            'is_locked' => $isLocked,
            'message' => $this->composeAffiliateLockMessage($activeAffiliateLockCount, $contractExpireDate, $affiliateEnabled),
        ];
    }

    private function releaseExpiredAffiliateLocks(?int $productId = null): void
    {
        if (! $this->hasAffiliateLocksTable()) {
            return;
        }

        $query = DB::table('affiliate_locks')
            ->where('is_active', true)
            ->whereDate('expiry_date', '<', now()->toDateString());

        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        $query->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    private function normalizeUnit(?string $rawUnit): string
    {
        $unit = strtolower(trim((string) $rawUnit));
        if ($unit === 'lt') {
            return 'liter';
        }

        return in_array($unit, self::ALLOWED_UNITS, true) ? $unit : 'kg';
    }

    private function unitOptions(): array
    {
        return [
            'kg' => 'kg',
            'gram' => 'gram',
            'liter' => 'liter (lt)',
            'ml' => 'ml',
            'pcs' => 'pcs',
            'pack' => 'pack',
            'ikat' => 'ikat',
        ];
    }

    private function activeAffiliateLockCount(int $productId): int
    {
        if (! $this->hasAffiliateLocksTable()) {
            return 0;
        }

        return (int) DB::table('affiliate_locks')
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->count();
    }

    private function galleryState(StoreProduct $product): array
    {
        $primary = trim((string) ($product->image_url ?? ''));
        $gallery = [];
        if ($primary !== '') {
            $gallery[] = $primary;
        }

        if ($this->hasGalleryTable()) {
            $additional = DB::table('store_product_images')
                ->where('store_product_id', $product->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('image_url')
                ->map(fn ($path) => trim((string) $path))
                ->filter()
                ->values()
                ->all();

            foreach ($additional as $path) {
                $gallery[] = $path;
            }
        }

        $gallery = array_values(array_unique(array_filter($gallery)));

        return [
            'paths' => $gallery,
            'count' => count($gallery),
        ];
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return array<int, string>
     */
    private function storeGalleryFiles(array $files): array
    {
        $paths = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $file->store('products', 'public');
            }
        }

        return $paths;
    }

    /**
     * @param array<int, string> $storedPaths
     */
    private function appendGalleryImages(StoreProduct $product, array $storedPaths): void
    {
        if (! $this->hasGalleryTable() || empty($storedPaths)) {
            return;
        }

        $maxSortOrder = (int) (DB::table('store_product_images')
            ->where('store_product_id', $product->id)
            ->max('sort_order') ?? 0);

        $rows = [];
        foreach ($storedPaths as $path) {
            $maxSortOrder++;
            $rows[] = [
                'store_product_id' => (int) $product->id,
                'image_url' => (string) $path,
                'sort_order' => $maxSortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('store_product_images')->insert($rows);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedFiles(Request $request, string $field): array
    {
        $files = $request->file($field, []);
        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
    }

    public function index(Request $request)
    {
        $this->releaseExpiredAffiliateLocks();

        $mitraId = Auth::id();
        $keyword = trim($request->string('q')->toString());
        $stockStatus = $request->string('stock')->toString();
        $listingStatus = $request->string('listing')->toString();
        $sourceType = $request->string('source')->toString();
        $hasActiveColumn = $this->hasActiveColumn();
        $hasSourceColumn = $this->hasSourceColumn();
        $hasReactivationColumn = $this->hasReactivationColumn();
        $hasAffiliateExpireColumn = $this->hasAffiliateExpireColumn();
        $affiliateCommissionRange = $this->affiliateCommissionRange();

        if (! in_array($sourceType, ['admin', 'self'], true)) {
            $sourceType = '';
        }

        $base = StoreProduct::query()->where('mitra_id', $mitraId);
        $summary = [
            'total' => (clone $base)->count(),
            'in_stock' => (clone $base)->where('stock_qty', '>', 10)->count(),
            'low_stock' => (clone $base)->whereBetween('stock_qty', [1, 10])->count(),
            'out_of_stock' => (clone $base)->where('stock_qty', '<=', 0)->count(),
            'active_listing' => $hasActiveColumn ? (clone $base)->where('is_active', true)->count() : (clone $base)->count(),
            'inactive_listing' => $hasActiveColumn ? (clone $base)->where('is_active', false)->count() : 0,
            'from_admin' => 0,
            'self_created' => (clone $base)->count(),
            'inactive_admin_procured' => 0,
        ];

        if ($hasSourceColumn) {
            $summary['from_admin'] = (clone $base)->whereNotNull('source_admin_product_id')->count();
            $summary['self_created'] = (clone $base)->whereNull('source_admin_product_id')->count();

            $inactiveAdminProcured = (clone $base)->whereNotNull('source_admin_product_id');
            if ($hasActiveColumn) {
                $inactiveAdminProcured->where('is_active', false);
            }
            $summary['inactive_admin_procured'] = $inactiveAdminProcured->count();
        }

        $activationProductsQuery = StoreProduct::query()
            ->where('mitra_id', $mitraId)
            ->orderByDesc('updated_at');

        if ($keyword !== '') {
            $activationProductsQuery->where(function ($sub) use ($keyword) {
                $sub->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if ($stockStatus === 'in_stock') {
            $activationProductsQuery->where('stock_qty', '>', 10);
        } elseif ($stockStatus === 'low_stock') {
            $activationProductsQuery->whereBetween('stock_qty', [1, 10]);
        } elseif ($stockStatus === 'out_of_stock') {
            $activationProductsQuery->where('stock_qty', '<=', 0);
        }

        if ($hasActiveColumn) {
            if ($listingStatus === 'active') {
                $activationProductsQuery->where('is_active', true);
            } elseif ($listingStatus === 'inactive') {
                $activationProductsQuery->where('is_active', false);
            }
        }

        if ($hasSourceColumn) {
            if ($sourceType === 'self') {
                $activationProductsQuery->whereNull('source_admin_product_id');
            } elseif ($sourceType === 'admin') {
                $activationProductsQuery->whereNotNull('source_admin_product_id');
            }
        }

        $activationProducts = $activationProductsQuery
            ->limit(40)
            ->get();

        $affiliateLockCounts = collect();
        if ($activationProducts->isNotEmpty() && $this->hasAffiliateLocksTable()) {
            $affiliateLockCounts = DB::table('affiliate_locks')
                ->select('product_id', DB::raw('COUNT(*) as total_lock'))
                ->whereIn('product_id', $activationProducts->pluck('id'))
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('expiry_date', '>=', now()->toDateString())
                ->groupBy('product_id')
                ->pluck('total_lock', 'product_id');
        }

        if ($activationProducts->isNotEmpty()) {
            if ($this->hasGalleryTable()) {
                $galleryRows = DB::table('store_product_images')
                    ->whereIn('store_product_id', $activationProducts->pluck('id'))
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

                $activationProducts = $activationProducts->map(function ($product) use ($galleryByProduct, $affiliateLockCounts, $hasAffiliateExpireColumn) {
                    $primary = trim((string) ($product->image_url ?? ''));
                    $paths = [];
                    if ($primary !== '') {
                        $paths[] = $primary;
                    }

                    foreach (($galleryByProduct->get($product->id, [])) as $galleryPath) {
                        $paths[] = (string) $galleryPath;
                    }

                    $affiliateEnabled = (bool) ($product->is_affiliate_enabled ?? false);
                    $activeAffiliateLockCount = (int) ($affiliateLockCounts[$product->id] ?? 0);
                    $contractExpireDate = $hasAffiliateExpireColumn
                        ? $this->normalizeDateValue($product->affiliate_expire_date ?? null)
                        : null;
                    $contractLocked = $affiliateEnabled && $this->isAffiliateContractLocked($contractExpireDate);

                    $product->gallery_count = count(array_values(array_unique(array_filter($paths))));
                    $product->affiliate_lock_count = $activeAffiliateLockCount;
                    $product->affiliate_contract_expire_date = $contractExpireDate;
                    $product->affiliate_contract_locked = $contractLocked;
                    $product->affiliate_locked = $activeAffiliateLockCount > 0 || $contractLocked;
                    $product->affiliate_lock_message = $this->composeAffiliateLockMessage(
                        $activeAffiliateLockCount,
                        $contractExpireDate,
                        $affiliateEnabled
                    );

                    return $product;
                });
            } else {
                $activationProducts = $activationProducts->map(function ($product) use ($affiliateLockCounts, $hasAffiliateExpireColumn) {
                    $affiliateEnabled = (bool) ($product->is_affiliate_enabled ?? false);
                    $activeAffiliateLockCount = (int) ($affiliateLockCounts[$product->id] ?? 0);
                    $contractExpireDate = $hasAffiliateExpireColumn
                        ? $this->normalizeDateValue($product->affiliate_expire_date ?? null)
                        : null;
                    $contractLocked = $affiliateEnabled && $this->isAffiliateContractLocked($contractExpireDate);

                    $product->gallery_count = trim((string) ($product->image_url ?? '')) !== '' ? 1 : 0;
                    $product->affiliate_lock_count = $activeAffiliateLockCount;
                    $product->affiliate_contract_expire_date = $contractExpireDate;
                    $product->affiliate_contract_locked = $contractLocked;
                    $product->affiliate_locked = $activeAffiliateLockCount > 0 || $contractLocked;
                    $product->affiliate_lock_message = $this->composeAffiliateLockMessage(
                        $activeAffiliateLockCount,
                        $contractExpireDate,
                        $affiliateEnabled
                    );
                    return $product;
                });
            }
        }

        return view('mitra.products.index', [
            'activationProducts' => $activationProducts,
            'summary' => $summary,
            'hasSourceColumn' => $hasSourceColumn,
            'hasReactivationColumn' => $hasReactivationColumn,
            'hasAffiliateExpireColumn' => $hasAffiliateExpireColumn,
            'affiliateCommissionRange' => $affiliateCommissionRange,
            'allowedUnits' => $this->unitOptions(),
            'filters' => [
                'q' => $keyword,
                'stock' => $stockStatus,
                'listing' => $listingStatus,
                'source' => $sourceType,
            ],
        ]);
    }

    public function create()
    {
        return view('mitra.products.create', [
            'allowedUnits' => $this->unitOptions(),
            'hasAffiliateExpireColumn' => $this->hasAffiliateExpireColumn(),
            'hasGalleryTable' => $this->hasGalleryTable(),
            'affiliateCommissionRange' => $this->affiliateCommissionRange(),
        ]);
    }

    public function store(Request $request)
    {
        $hasGalleryTable = $this->hasGalleryTable();
        $hasAffiliateExpireColumn = $this->hasAffiliateExpireColumn();
        $isAffiliateEnabled = (bool) $request->boolean('is_affiliate_enabled');
        $affiliateCommissionRange = $this->affiliateCommissionRange();
        $requestedActiveStatus = (bool) $request->boolean('is_active', true);
        $affiliateExpireRules = $hasAffiliateExpireColumn
            ? $this->affiliateExpiryRules($isAffiliateEnabled)
            : ['nullable'];
        $minimumGalleryForCreate = $requestedActiveStatus
            ? self::MIN_GALLERY_IMAGES_FOR_ACTIVATION
            : 1;
        $galleryImagesRules = $hasGalleryTable
            ? ['required', 'array', 'min:' . $minimumGalleryForCreate, 'max:' . self::MAX_GALLERY_IMAGES]
            : ['nullable'];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:1',
            'unit' => ['required', 'string', Rule::in(self::ALLOWED_UNITS)],
            'stock_qty' => 'required|integer|min:20',
            'is_active' => 'required|boolean',
            'is_affiliate_enabled' => 'nullable|boolean',
            'affiliate_commission' => $this->affiliateCommissionRules($isAffiliateEnabled, $affiliateCommissionRange),
            'affiliate_expire_date' => $affiliateExpireRules,
            'image' => $hasGalleryTable ? 'nullable|image|mimes:jpeg,png,jpg|max:4096' : 'required|image|mimes:jpeg,png,jpg|max:4096',
            'gallery_images' => $galleryImagesRules,
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg|max:4096',
            'source_admin_product_id' => $this->hasSourceColumn() ? ['prohibited'] : ['nullable'],
        ], array_merge([
            'affiliate_expire_date.required' => 'Tanggal berakhir affiliate wajib diisi saat affiliate diaktifkan.',
            'affiliate_expire_date.after_or_equal' => 'Tanggal berakhir affiliate minimal hari ini.',
            'stock_qty.min' => 'Minimal stok produk baru adalah 20.',
            'gallery_images.required' => 'Galeri produk wajib diisi.',
            'gallery_images.min' => $requestedActiveStatus
                ? 'Produk aktif di landing page wajib memiliki minimal 3 gambar.'
                : 'Galeri produk minimal 1 gambar.',
            'gallery_images.max' => 'Maksimal 5 gambar per produk.',
            'source_admin_product_id.prohibited' => 'Produk Mitra yang ditambah manual tidak boleh memakai referensi pengadaan Admin.',
        ], $this->affiliateCommissionMessages($affiliateCommissionRange)));

        $imagePath = null;
        $additionalGalleryPaths = [];

        if ($hasGalleryTable) {
            $galleryFiles = $this->uploadedFiles($request, 'gallery_images');
            $storedGalleryPaths = $this->storeGalleryFiles($galleryFiles);
            $imagePath = array_shift($storedGalleryPaths);
            $additionalGalleryPaths = $storedGalleryPaths;
        } elseif ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $affiliateExpireDate = null;
        if ($hasAffiliateExpireColumn && $isAffiliateEnabled) {
            $affiliateExpireDate = (string) $request->date('affiliate_expire_date')->toDateString();
        }

        $affiliateCommission = $isAffiliateEnabled
            ? (float) ($validated['affiliate_commission'] ?? 0)
            : 0;
        if ($isAffiliateEnabled) {
            $this->assertAffiliateCommissionInRange($affiliateCommission, $affiliateCommissionRange);
        }

        $payload = [
            'mitra_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock_qty' => $request->stock_qty,
            'image_url' => $imagePath,
            'is_affiliate_enabled' => $isAffiliateEnabled,
            'affiliate_commission' => $affiliateCommission,
        ];

        if ($hasAffiliateExpireColumn) {
            $payload['affiliate_expire_date'] = $affiliateExpireDate;
        }

        if ($this->hasUnitColumn()) {
            $payload['unit'] = $this->normalizeUnit($request->string('unit')->toString());
        }

        if ($this->hasSourceColumn()) {
            // Produk dari form tambah adalah produk Mitra sendiri.
            $payload['source_admin_product_id'] = null;
        }

        if (Schema::hasColumn('store_products', 'is_active')) {
            $payload['is_active'] = $requestedActiveStatus;
        }

        $product = StoreProduct::create($payload);

        if ($hasGalleryTable && ! empty($additionalGalleryPaths)) {
            $this->appendGalleryImages($product, $additionalGalleryPaths);
        }

        $this->logStockMutation(
            $product,
            0,
            (int) $product->stock_qty,
            'create',
            'Inisialisasi stok awal produk'
        );

        return redirect()->route('mitra.products.index')->with('success', 'Produk berhasil ditambahkan!');
    }

    public function show(StoreProduct $product)
    {
        abort(404);
    }

    public function edit(StoreProduct $product)
    {
        $this->releaseExpiredAffiliateLocks((int) $product->id);

        $mutations = collect();
        if (Schema::hasTable('store_product_stock_mutations')) {
            $mutations = DB::table('store_product_stock_mutations')
                ->where('store_product_id', $product->id)
                ->orderByDesc('id')
                ->limit(15)
                ->get();
        }

        $affiliateLockContext = $this->affiliateLockContext($product);
        $galleryState = $this->galleryState($product);
        $galleryCount = (int) ($galleryState['count'] ?? 0);
        $remainingGallerySlots = max(0, self::MAX_GALLERY_IMAGES - $galleryCount);
        $affiliateCommissionRange = $this->affiliateCommissionRange();

        return view('mitra.products.edit', [
            'product' => $product,
            'mutations' => $mutations,
            'allowedUnits' => $this->unitOptions(),
            'isAdminProcuredProduct' => $this->hasSourceColumn() && ! empty($product->source_admin_product_id),
            'affiliateLockActive' => (bool) ($affiliateLockContext['is_locked'] ?? false),
            'activeAffiliateLockCount' => (int) ($affiliateLockContext['active_count'] ?? 0),
            'affiliateLockMessage' => (string) ($affiliateLockContext['message'] ?? ''),
            'affiliateContractExpireDate' => $affiliateLockContext['contract_expire_date'] ?? null,
            'hasGalleryTable' => $this->hasGalleryTable(),
            'hasAffiliateExpireColumn' => $this->hasAffiliateExpireColumn(),
            'galleryPaths' => $galleryState['paths'] ?? [],
            'galleryCount' => $galleryCount,
            'remainingGallerySlots' => $remainingGallerySlots,
            'affiliateCommissionRange' => $affiliateCommissionRange,
        ]);
    }

    public function update(Request $request, StoreProduct $product)
    {
        $this->releaseExpiredAffiliateLocks((int) $product->id);

        $isAdminProcuredProduct = $this->hasSourceColumn()
            && ! empty($product->source_admin_product_id);

        $oldStock = (int) $product->stock_qty;
        $existingGalleryCount = (int) ($this->galleryState($product)['count'] ?? 0);
        $maxAdditionalGallery = $this->hasGalleryTable()
            ? max(0, self::MAX_GALLERY_IMAGES - $existingGalleryCount)
            : 0;
        $hasAffiliateExpireColumn = $this->hasAffiliateExpireColumn();
        $requestedAffiliateEnabled = (bool) $request->boolean('is_affiliate_enabled');
        $affiliateCommissionRange = $this->affiliateCommissionRange();
        $affiliateLockContext = $this->affiliateLockContext($product);
        $affiliateLockActive = (bool) ($affiliateLockContext['is_locked'] ?? false);
        $affiliateLockMessage = (string) ($affiliateLockContext['message'] ?? 'Status affiliate produk masih terkunci.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:1',
            'unit' => ['required', 'string', Rule::in(self::ALLOWED_UNITS)],
            'stock_qty' => 'required|integer|min:0',
            'is_active' => $isAdminProcuredProduct ? ['prohibited'] : ['nullable', 'boolean'],
            'is_affiliate_enabled' => 'nullable|boolean',
            'affiliate_commission' => $this->affiliateCommissionRules($requestedAffiliateEnabled, $affiliateCommissionRange),
            'affiliate_expire_date' => $hasAffiliateExpireColumn
                ? $this->affiliateExpiryRules($requestedAffiliateEnabled)
                : ['nullable'],
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:4096',
            'gallery_images' => ['nullable', 'array', 'max:' . $maxAdditionalGallery],
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg|max:4096',
        ], array_merge([
            'affiliate_expire_date.required' => 'Tanggal berakhir affiliate wajib diisi saat affiliate diaktifkan.',
            'affiliate_expire_date.after_or_equal' => 'Tanggal berakhir affiliate minimal hari ini.',
            'is_active.prohibited' => 'Produk pengadaan Admin tidak bisa diaktif/nonaktif dari form Edit. Gunakan Proses Aktivasi Jual atau Nonaktifkan Jual.',
        ], $this->affiliateCommissionMessages($affiliateCommissionRange)));

        $this->enforceAdminSourceMinimumPrice($product, (float) ($validated['price'] ?? 0));

        $affiliateExpireDate = null;
        if ($hasAffiliateExpireColumn && $requestedAffiliateEnabled) {
            $affiliateExpireDate = (string) $request->date('affiliate_expire_date')->toDateString();
        }

        $affiliateCommission = $requestedAffiliateEnabled
            ? (float) ($validated['affiliate_commission'] ?? 0)
            : 0;
        if ($requestedAffiliateEnabled) {
            $this->assertAffiliateCommissionInRange($affiliateCommission, $affiliateCommissionRange);
        }

        if ($affiliateLockActive && (bool) $product->is_affiliate_enabled && ! $requestedAffiliateEnabled) {
            throw ValidationException::withMessages([
                'is_affiliate_enabled' => 'Affiliate tidak bisa dinonaktifkan. ' . $affiliateLockMessage,
            ]);
        }

        $requestedActiveStatus = (bool) $request->boolean('is_active', (bool) ($product->is_active ?? false));
        if (
            ! $isAdminProcuredProduct
            && $request->has('is_active')
            && $affiliateLockActive
            && $this->hasActiveColumn()
            && (bool) $product->is_active
            && ! $requestedActiveStatus
        ) {
            throw ValidationException::withMessages([
                'is_active' => 'Produk tidak bisa dinonaktifkan. ' . $affiliateLockMessage,
            ]);
        }

        if ($request->hasFile('image')) {
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }
            $imagePath = $request->file('image')->store('products', 'public');
            $product->image_url = $imagePath;
        }

        $payload = [
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock_qty' => $request->stock_qty,
            'is_affiliate_enabled' => $requestedAffiliateEnabled,
            'affiliate_commission' => $affiliateCommission,
        ];

        if ($hasAffiliateExpireColumn) {
            $payload['affiliate_expire_date'] = $affiliateExpireDate;
        }

        if ($this->hasUnitColumn()) {
            $payload['unit'] = $this->normalizeUnit($request->string('unit')->toString());
        }

        if (Schema::hasColumn('store_products', 'is_active') && ! $isAdminProcuredProduct && $request->has('is_active')) {
            $payload['is_active'] = $requestedActiveStatus;
        }

        $product->update($payload);

        if ($this->hasGalleryTable()) {
            $additionalImages = $this->uploadedFiles($request, 'gallery_images');
            if (! empty($additionalImages)) {
                $additionalPaths = $this->storeGalleryFiles($additionalImages);
                $this->appendGalleryImages($product->fresh(), $additionalPaths);
            }
        }

        $newStock = (int) $request->stock_qty;
        $delta = $newStock - $oldStock;
        if ($delta !== 0) {
            $this->logStockMutation(
                $product->fresh(),
                $oldStock,
                $delta,
                'edit',
                'Perubahan stok dari form edit produk'
            );
        }

        return redirect()->route('mitra.products.index')->with('success', 'Produk berhasil diperbarui!');
    }

    public function destroy(StoreProduct $product)
    {
        $this->releaseExpiredAffiliateLocks((int) $product->id);
        $affiliateLockContext = $this->affiliateLockContext($product);
        if ((bool) ($affiliateLockContext['is_locked'] ?? false)) {
            return back()->withErrors([
                'product' => 'Produk tidak bisa dihapus. ' . ((string) ($affiliateLockContext['message'] ?? 'Status affiliate produk masih terkunci.')),
            ]);
        }

        $galleryState = $this->galleryState($product);
        foreach (($galleryState['paths'] ?? []) as $path) {
            Storage::disk('public')->delete((string) $path);
        }

        if ($this->hasGalleryTable()) {
            DB::table('store_product_images')
                ->where('store_product_id', $product->id)
                ->delete();
        }

        $product->delete();

        return redirect()->route('mitra.products.index')->with('success', 'Produk berhasil dihapus!');
    }

    public function adjustStock(Request $request, StoreProduct $product)
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'delta' => 'required|integer|not_in:0|min:-100000|max:100000',
            'note' => 'nullable|string|max:255',
        ]);

        $beforeStock = (int) $product->stock_qty;
        $delta = (int) $data['delta'];
        $newStock = $beforeStock + $delta;
        if ($newStock < 0) {
            throw ValidationException::withMessages([
                'delta' => 'Penyesuaian stok membuat stok menjadi minus.',
            ]);
        }

        $product->update(['stock_qty' => $newStock]);
        $note = $data['note'] ?? null;
        $this->logStockMutation(
            $product->fresh(),
            $beforeStock,
            $delta,
            'adjust',
            $note ?: 'Penyesuaian stok cepat dari halaman inventori'
        );

        return back()->with('success', 'Stok produk berhasil diperbarui.');
    }

    public function stockHistory(StoreProduct $product)
    {
        $this->authorize('view', $product);

        $mutations = collect();
        if (Schema::hasTable('store_product_stock_mutations')) {
            $mutations = DB::table('store_product_stock_mutations')
                ->where('store_product_id', $product->id)
                ->orderByDesc('id')
                ->paginate(20);
        }

        return view('mitra.products.stock-history', [
            'product' => $product,
            'mutations' => $mutations,
        ]);
    }

    public function toggleActive(Request $request, StoreProduct $product)
    {
        $this->authorize('update', $product);
        $this->releaseExpiredAffiliateLocks((int) $product->id);

        if (! $this->hasActiveColumn()) {
            return back()->withErrors(['product' => 'Kolom status jual belum tersedia. Jalankan migration terbaru.']);
        }

        if (! (bool) $product->is_active) {
            return back()->withErrors([
                'product' => 'Produk nonaktif harus diaktifkan lewat Proses Aktivasi Jual.',
            ]);
        }

        $affiliateLockContext = $this->affiliateLockContext($product);
        if ((bool) ($affiliateLockContext['is_locked'] ?? false)) {
            return back()->withErrors([
                'product' => 'Produk tidak bisa dinonaktifkan. ' . ((string) ($affiliateLockContext['message'] ?? 'Status affiliate produk masih terkunci.')),
            ]);
        }

        $payload = [
            'is_active' => false,
        ];
        $reactivationAt = null;
        if ($this->hasReactivationColumn()) {
            $reactivationAt = now()->addDays(self::REACTIVATION_LOCK_DAYS);
            $payload['reactivation_available_at'] = $reactivationAt;
        }

        $product->update($payload);

        $message = 'Produk dinonaktifkan.';
        if ($reactivationAt !== null) {
            $message .= ' Produk baru bisa diaktifkan kembali pada '
                . $reactivationAt->translatedFormat('d M Y H:i')
                . '.';
        }

        return back()->with('success', $message);
    }

    public function activateListing(Request $request, StoreProduct $product)
    {
        $this->authorize('update', $product);
        $this->releaseExpiredAffiliateLocks((int) $product->id);

        if (! $this->hasActiveColumn()) {
            return back()->withErrors(['product' => 'Kolom status jual belum tersedia. Jalankan migration terbaru.']);
        }

        if ((bool) $product->is_active) {
            return back()->withErrors(['product' => 'Produk sudah aktif untuk dijual.']);
        }

        if ($this->hasSourceColumn() && empty($product->source_admin_product_id)) {
            throw ValidationException::withMessages([
                'product' => 'Proses Aktivasi Jual hanya untuk produk hasil pengadaan Admin. Produk buatan Mitra bisa diaktifkan dari form Edit Produk.',
            ]);
        }

        if ($this->hasReactivationColumn() && ! empty($product->reactivation_available_at)) {
            $reactivationAt = \Illuminate\Support\Carbon::parse($product->reactivation_available_at);
            if ($reactivationAt->isFuture()) {
                throw ValidationException::withMessages([
                    'product' => 'Produk ini sedang masa jeda nonaktif dan baru bisa diaktifkan kembali pada '
                        . $reactivationAt->translatedFormat('d M Y H:i')
                        . '.',
                ]);
            }
        }

        if (! $this->hasGalleryTable()) {
            return back()->withErrors([
                'images' => 'Fitur galeri produk belum tersedia. Jalankan migration terbaru.',
            ]);
        }

        $galleryState = $this->galleryState($product);
        $existingGalleryCount = (int) ($galleryState['count'] ?? 0);
        if ($existingGalleryCount > self::MAX_GALLERY_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'Galeri produk saat ini melebihi batas maksimal 5 gambar. Rapikan galeri terlebih dahulu.',
            ]);
        }

        $requiredAdditional = max(0, self::MIN_GALLERY_IMAGES_FOR_ACTIVATION - $existingGalleryCount);
        $remainingSlots = max(0, self::MAX_GALLERY_IMAGES - $existingGalleryCount);
        $imagesRule = ['nullable', 'array', 'max:' . $remainingSlots];
        if ($requiredAdditional > 0) {
            $imagesRule = ['required', 'array', 'min:' . $requiredAdditional, 'max:' . $remainingSlots];
        }
        $hasAffiliateExpireColumn = $this->hasAffiliateExpireColumn();
        $requestedAffiliateEnabled = (bool) $request->boolean('is_affiliate_enabled');
        $affiliateCommissionRange = $this->affiliateCommissionRange();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|min:10|max:2000',
            'price' => 'required|numeric|min:1',
            'unit' => ['required', 'string', Rule::in(self::ALLOWED_UNITS)],
            'stock_qty' => 'required|integer|min:20',
            'is_affiliate_enabled' => ['required', Rule::in(['0', '1', 0, 1, true, false])],
            'affiliate_commission' => $this->affiliateCommissionRules($requestedAffiliateEnabled, $affiliateCommissionRange),
            'affiliate_expire_date' => $hasAffiliateExpireColumn
                ? $this->affiliateExpiryRules($requestedAffiliateEnabled)
                : ['nullable'],
            'images' => $imagesRule,
            'images.*' => 'image|mimes:jpeg,png,jpg|max:4096',
        ], array_merge([
            'images.required' => "Galeri minimal harus berisi " . self::MIN_GALLERY_IMAGES_FOR_ACTIVATION . " gambar sebelum produk bisa diaktifkan.",
            'images.min' => "Tambahkan minimal {$requiredAdditional} gambar lagi agar total galeri mencapai " . self::MIN_GALLERY_IMAGES_FOR_ACTIVATION . ".",
            'images.max' => "Maksimal tambahan gambar saat ini hanya {$remainingSlots} agar total galeri tidak lebih dari " . self::MAX_GALLERY_IMAGES . '.',
            'stock_qty.min' => 'Minimal stok untuk aktivasi jual adalah 20.',
            'affiliate_expire_date.required' => 'Tanggal berakhir affiliate wajib diisi saat affiliate diaktifkan.',
            'affiliate_expire_date.after_or_equal' => 'Tanggal berakhir affiliate minimal hari ini.',
        ], $this->affiliateCommissionMessages($affiliateCommissionRange)));

        $this->enforceAdminSourceMinimumPrice($product, (float) ($data['price'] ?? 0));

        $isAffiliateEnabled = $requestedAffiliateEnabled;
        $affiliateExpireDate = null;
        if ($hasAffiliateExpireColumn && $isAffiliateEnabled) {
            $affiliateExpireDate = (string) $request->date('affiliate_expire_date')->toDateString();
        }

        $affiliateCommission = $isAffiliateEnabled
            ? (float) ($data['affiliate_commission'] ?? 0)
            : 0;
        if ($isAffiliateEnabled) {
            $this->assertAffiliateCommissionInRange($affiliateCommission, $affiliateCommissionRange);
        }

        $affiliateLockContext = $this->affiliateLockContext($product);
        if ((bool) ($affiliateLockContext['is_locked'] ?? false) && (bool) ($product->is_affiliate_enabled ?? false) && ! $isAffiliateEnabled) {
            throw ValidationException::withMessages([
                'is_affiliate_enabled' => 'Affiliate tidak bisa dinonaktifkan. ' . ((string) ($affiliateLockContext['message'] ?? 'Status affiliate produk masih terkunci.')),
            ]);
        }

        $oldStock = (int) $product->stock_qty;
        $newGalleryFiles = $this->uploadedFiles($request, 'images');
        $newGalleryPaths = $this->storeGalleryFiles($newGalleryFiles);

        $payload = [
            'name' => trim((string) $data['name']),
            'description' => trim((string) $data['description']),
            'price' => (float) $data['price'],
            'stock_qty' => (int) $data['stock_qty'],
            'is_affiliate_enabled' => $isAffiliateEnabled,
            'affiliate_commission' => $affiliateCommission,
            'is_active' => true,
        ];

        if ($hasAffiliateExpireColumn) {
            $payload['affiliate_expire_date'] = $affiliateExpireDate;
        }

        if (empty($product->image_url) && ! empty($newGalleryPaths)) {
            $payload['image_url'] = array_shift($newGalleryPaths);
        } elseif (empty($product->image_url) && ! empty($galleryState['paths'])) {
            $payload['image_url'] = (string) $galleryState['paths'][0];
        }

        if ($this->hasUnitColumn()) {
            $payload['unit'] = $this->normalizeUnit((string) $data['unit']);
        }

        if ($this->hasReactivationColumn()) {
            $payload['reactivation_available_at'] = null;
        }

        $product->update($payload);
        if (! empty($newGalleryPaths)) {
            $this->appendGalleryImages($product->fresh(), $newGalleryPaths);
        }

        $finalGalleryCount = (int) ($this->galleryState($product->fresh())['count'] ?? 0);
        if (
            $finalGalleryCount < self::MIN_GALLERY_IMAGES_FOR_ACTIVATION
            || $finalGalleryCount > self::MAX_GALLERY_IMAGES
        ) {
            throw ValidationException::withMessages([
                'images' => 'Produk wajib memiliki total galeri 3 sampai 5 gambar sebelum aktif jual.',
            ]);
        }

        $newStock = (int) $payload['stock_qty'];
        $delta = $newStock - $oldStock;
        if ($delta !== 0) {
            $this->logStockMutation(
                $product->fresh(),
                $oldStock,
                $delta,
                'activation',
                'Aktivasi jual produk dari inventori mitra'
            );
        }

        return back()->with('success', 'Produk berhasil diaktifkan untuk dijual.');
    }

    public function updateMarketplaceSettings(Request $request, StoreProduct $product)
    {
        $this->authorize('update', $product);
        $this->releaseExpiredAffiliateLocks((int) $product->id);

        $hasAffiliateExpireColumn = $this->hasAffiliateExpireColumn();
        $affiliateCommissionRange = $this->affiliateCommissionRange();
        $currentAffiliateExpireDate = null;
        if ($hasAffiliateExpireColumn && ! empty($product->affiliate_expire_date)) {
            $currentAffiliateExpireDate = \Illuminate\Support\Carbon::parse($product->affiliate_expire_date)->toDateString();
        }

        $requestedAffiliateEnabled = $request->has('is_affiliate_enabled')
            ? (bool) $request->boolean('is_affiliate_enabled')
            : (bool) ($product->is_affiliate_enabled ?? false);
        $mustRequireAffiliateExpireDate = $hasAffiliateExpireColumn
            && $requestedAffiliateEnabled
            && ($request->has('affiliate_expire_date') || empty($currentAffiliateExpireDate));

        $data = $request->validate([
            'price' => 'nullable|numeric|min:1',
            'is_affiliate_enabled' => 'nullable|boolean',
            'affiliate_commission' => $this->affiliateCommissionRules(
                false,
                $affiliateCommissionRange,
                $requestedAffiliateEnabled
            ),
            'affiliate_expire_date' => $hasAffiliateExpireColumn
                ? $this->affiliateExpiryRules($mustRequireAffiliateExpireDate)
                : ['nullable'],
        ], array_merge([
            'affiliate_expire_date.required' => 'Tanggal berakhir affiliate wajib diisi saat affiliate diaktifkan.',
            'affiliate_expire_date.after_or_equal' => 'Tanggal berakhir affiliate minimal hari ini.',
        ], $this->affiliateCommissionMessages($affiliateCommissionRange)));

        $isAffiliateEnabled = $request->has('is_affiliate_enabled')
            ? (bool) ($data['is_affiliate_enabled'] ?? false)
            : (bool) ($product->is_affiliate_enabled ?? false);

        $affiliateExpireDate = $currentAffiliateExpireDate;
        if ($hasAffiliateExpireColumn) {
            if ($isAffiliateEnabled) {
                if ($request->filled('affiliate_expire_date')) {
                    $affiliateExpireDate = (string) $request->date('affiliate_expire_date')->toDateString();
                }
            } else {
                $affiliateExpireDate = null;
            }
        }

        $affiliateLockContext = $this->affiliateLockContext($product);
        if ((bool) ($affiliateLockContext['is_locked'] ?? false) && (bool) ($product->is_affiliate_enabled ?? false) && ! $isAffiliateEnabled) {
            throw ValidationException::withMessages([
                'is_affiliate_enabled' => 'Affiliate tidak bisa dinonaktifkan. ' . ((string) ($affiliateLockContext['message'] ?? 'Status affiliate produk masih terkunci.')),
            ]);
        }

        if ($hasAffiliateExpireColumn && $isAffiliateEnabled && empty($affiliateExpireDate)) {
            throw ValidationException::withMessages([
                'affiliate_expire_date' => 'Tanggal berakhir affiliate wajib diisi saat affiliate diaktifkan.',
            ]);
        }

        $affiliateCommission = $isAffiliateEnabled
            ? (float) ($data['affiliate_commission'] ?? ($product->affiliate_commission ?? 0))
            : 0;
        if ($isAffiliateEnabled) {
            $this->assertAffiliateCommissionInRange($affiliateCommission, $affiliateCommissionRange);
        }

        if (array_key_exists('price', $data) && $data['price'] !== null) {
            $this->enforceAdminSourceMinimumPrice($product, (float) $data['price']);
        }

        $payload = [
            'is_affiliate_enabled' => $isAffiliateEnabled,
            'affiliate_commission' => $affiliateCommission,
        ];

        if ($hasAffiliateExpireColumn) {
            $payload['affiliate_expire_date'] = $affiliateExpireDate;
        }

        if (array_key_exists('price', $data) && $data['price'] !== null) {
            $payload['price'] = (float) $data['price'];
        }

        $product->update($payload);

        return back()->with('success', 'Pengaturan produk marketplace berhasil disimpan.');
    }

    private function logStockMutation(StoreProduct $product, int $qtyBefore, int $qtyDelta, string $changeType, ?string $note = null): void
    {
        if (! Schema::hasTable('store_product_stock_mutations')) {
            return;
        }

        DB::table('store_product_stock_mutations')->insert([
            'mitra_id' => (int) $product->mitra_id,
            'store_product_id' => (int) $product->id,
            'product_name' => (string) $product->name,
            'change_type' => $changeType,
            'qty_before' => $qtyBefore,
            'qty_delta' => $qtyDelta,
            'qty_after' => $qtyBefore + $qtyDelta,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
