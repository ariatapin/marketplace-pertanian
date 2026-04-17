<x-mitra-layout>
    <x-slot name="header">{{ __('Toko Saya') }}</x-slot>

    @php
        $affiliateCommissionRange = $affiliateCommissionRange ?? ['min' => 0, 'max' => 100];
        $affiliateCommissionMin = number_format((float) ($affiliateCommissionRange['min'] ?? 0), 2, '.', '');
        $affiliateCommissionMax = number_format((float) ($affiliateCommissionRange['max'] ?? 100), 2, '.', '');
        $affiliateCommissionMinLabel = rtrim(rtrim($affiliateCommissionMin, '0'), '.');
        $affiliateCommissionMaxLabel = rtrim(rtrim($affiliateCommissionMax, '0'), '.');
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Total Produk</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format((int)($summary['total'] ?? 0)) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Dari Pengadaan Admin</p><p class="mt-2 text-3xl font-bold text-indigo-700">{{ number_format((int)($summary['from_admin'] ?? 0)) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Produk Buatan Mitra</p><p class="mt-2 text-3xl font-bold text-cyan-700">{{ number_format((int)($summary['self_created'] ?? 0)) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Pengadaan Belum Aktif</p><p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format((int)($summary['inactive_admin_procured'] ?? 0)) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Stok Aman</p><p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format((int)($summary['in_stock'] ?? 0)) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Stok Menipis</p><p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format((int)($summary['low_stock'] ?? 0)) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Jual Aktif</p><p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format((int)($summary['active_listing'] ?? 0)) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Jual Nonaktif</p><p class="mt-2 text-3xl font-bold text-slate-700">{{ number_format((int)($summary['inactive_listing'] ?? 0)) }}</p></article>
        </section>

        <section
            class="surface-card p-6"
            x-data="{
                activationModalOpen: false,
                activateBaseUrl: @js(url('/mitra/products')),
                formAction: '',
                currentImage: '',
                existingGalleryCount: 0,
                minGalleryRequired: 3,
                maxGalleryAllowed: 5,
                selectedImagePreviews: [],
                selectedImageObjectUrls: [],
                menuProductId: null,
                affiliateLockedCount: 0,
                affiliateLocked: false,
                affiliateLockMessage: '',
                affiliateContractExpireDate: '',
                affiliateCommissionMin: {{ $affiliateCommissionMin }},
                affiliateCommissionMax: {{ $affiliateCommissionMax }},
                form: {
                    name: '',
                    description: '',
                    price: '',
                    unit: 'kg',
                    stock_qty: '',
                    is_affiliate_enabled: '0',
                    affiliate_commission: '{{ $affiliateCommissionMin }}',
                    affiliate_expire_date: ''
                },
                openActivation(payload) {
                    this.formAction = this.activateBaseUrl + '/' + payload.id + '/activate-listing';
                    this.form.name = payload.name ?? '';
                    this.form.description = payload.description ?? '';
                    this.form.price = payload.price ?? '';
                    this.form.unit = payload.unit ?? 'kg';
                    this.form.stock_qty = payload.stock_qty ?? '';
                    this.form.is_affiliate_enabled = payload.is_affiliate_enabled ? '1' : '0';
                    const incomingCommission = payload.affiliate_commission ?? '';
                    this.form.affiliate_commission = incomingCommission === ''
                        ? this.affiliateCommissionMin.toString()
                        : incomingCommission;
                    this.form.affiliate_expire_date = payload.affiliate_expire_date ?? '';
                    this.currentImage = payload.image_url ?? '';
                    this.affiliateLockedCount = Number(payload.affiliate_lock_count ?? 0);
                    this.affiliateLocked = Boolean(payload.affiliate_locked ?? false);
                    this.affiliateLockMessage = payload.affiliate_lock_message ?? '';
                    this.affiliateContractExpireDate = payload.affiliate_contract_expire_date ?? '';
                    if (this.affiliateLocked && this.form.is_affiliate_enabled !== '1') {
                        this.form.is_affiliate_enabled = '1';
                    }
                    this.existingGalleryCount = Number(payload.gallery_count ?? (payload.image_url ? 1 : 0));
                    this.clearSelectedImagePreview();
                    this.closeActionMenu();
                    this.activationModalOpen = true;
                },
                openActivationFromDataset(dataset) {
                    this.openActivation({
                        id: Number(dataset.productId ?? 0),
                        name: dataset.productName ?? '',
                        description: dataset.productDescription ?? '',
                        price: dataset.productPrice ?? '',
                        unit: dataset.productUnit ?? 'kg',
                        stock_qty: dataset.productStockQty ?? '',
                        is_affiliate_enabled: (dataset.affiliateEnabled ?? '0') === '1',
                        affiliate_commission: dataset.affiliateCommission ?? '{{ $affiliateCommissionMin }}',
                        affiliate_expire_date: dataset.affiliateExpireDate ?? '',
                        affiliate_lock_count: Number(dataset.affiliateLockCount ?? 0),
                        affiliate_locked: (dataset.affiliateLocked ?? '0') === '1',
                        affiliate_lock_message: dataset.affiliateLockMessage ?? '',
                        affiliate_contract_expire_date: dataset.affiliateContractExpireDate ?? '',
                        gallery_count: Number(dataset.galleryCount ?? 0),
                        image_url: dataset.productImageUrl ?? ''
                    });
                },
                minAdditionalRequired() {
                    return Math.max(0, this.minGalleryRequired - this.existingGalleryCount);
                },
                remainingSlots() {
                    return Math.max(0, this.maxGalleryAllowed - this.existingGalleryCount);
                },
                onImageSelected(event) {
                    const files = Array.from(event?.target?.files ?? []);
                    if (files.length === 0) {
                        this.clearSelectedImagePreview();
                        return;
                    }

                    this.clearSelectedImagePreview();
                    files.forEach((file) => {
                        const objectUrl = URL.createObjectURL(file);
                        this.selectedImageObjectUrls.push(objectUrl);
                        this.selectedImagePreviews.push(objectUrl);
                    });
                },
                clearSelectedImagePreview() {
                    this.selectedImageObjectUrls.forEach((url) => URL.revokeObjectURL(url));
                    this.selectedImageObjectUrls = [];
                    this.selectedImagePreviews = [];
                },
                closeActivation() {
                    this.clearSelectedImagePreview();
                    this.activationModalOpen = false;
                },
                toggleActionMenu(productId) {
                    this.menuProductId = this.menuProductId === productId ? null : productId;
                },
                closeActionMenu() {
                    this.menuProductId = null;
                }
            }"
        >
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Inventori Produk Mitra</h3>
                    <p class="text-sm text-slate-600">Fokus halaman ini khusus aktivasi jual produk hasil pengadaan admin.</p>
                </div>
                <a href="{{ route('mitra.products.create') }}" class="btn-mint">+ Tambah Produk Baru</a>
            </div>

            <form method="GET" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-6">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama/deskripsi produk" class="rounded-lg border-slate-300 text-sm">
                <select name="stock" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua status stok</option>
                    <option value="in_stock" @selected(($filters['stock'] ?? '') === 'in_stock')>Stok Aman (>10)</option>
                    <option value="low_stock" @selected(($filters['stock'] ?? '') === 'low_stock')>Stok Menipis (1-10)</option>
                    <option value="out_of_stock" @selected(($filters['stock'] ?? '') === 'out_of_stock')>Stok Habis (0)</option>
                </select>
                <select name="source" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua sumber produk</option>
                    <option value="admin" @selected(($filters['source'] ?? '') === 'admin')>Hasil Pengadaan Admin</option>
                    <option value="self" @selected(($filters['source'] ?? '') === 'self')>Buatan Mitra</option>
                </select>
                <select name="listing" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua status jual</option>
                    <option value="active" @selected(($filters['listing'] ?? '') === 'active')>Jual Aktif</option>
                    <option value="inactive" @selected(($filters['listing'] ?? '') === 'inactive')>Jual Nonaktif</option>
                </select>
                <div class="hidden md:block"></div>
                <button type="submit" class="btn-ink">Filter</button>
            </form>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-900">Proses Aktivasi Jual</p>
                    <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ number_format((int) (($activationProducts ?? collect())->count())) }} produk
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-600">
                    Semua field pada popup aktivasi wajib diisi. Produk hanya bisa aktif jika total galeri berisi 3 sampai 5 gambar.
                    Komisi affiliate harus mengikuti batas Admin: {{ $affiliateCommissionMinLabel }}% - {{ $affiliateCommissionMaxLabel }}%.
                </p>

                @if(($activationProducts ?? collect())->isEmpty())
                    <p class="mt-3 text-sm text-slate-600">Tidak ada produk untuk diproses pada filter saat ini.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-slate-600">
                                    <th class="py-2 pr-4">Produk</th>
                                    <th class="py-2 pr-4">Harga</th>
                                    <th class="py-2 pr-4">Stok</th>
                                    <th class="py-2 pr-4">Status Jual</th>
                                    <th class="py-2 pr-4">Affiliate</th>
                                    <th class="py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activationProducts as $product)
                                    @php
                                        $isActive = (bool) ($product->is_active ?? true);
                                        $reactivationAt = (!empty($product->reactivation_available_at) && ($hasReactivationColumn ?? false))
                                            ? \Illuminate\Support\Carbon::parse($product->reactivation_available_at)
                                            : null;
                                        $isLocked = ! $isActive && ($reactivationAt?->isFuture() ?? false);
                                        $lockLabel = $reactivationAt?->translatedFormat('d M Y H:i');
                                        $affiliateLockCount = (int) ($product->affiliate_lock_count ?? 0);
                                        $affiliateContractExpireDate = !empty($product->affiliate_contract_expire_date)
                                            ? \Illuminate\Support\Carbon::parse($product->affiliate_contract_expire_date)
                                            : null;
                                        $affiliateLockedForProduct = (bool) ($product->affiliate_locked ?? false);
                                        $affiliateLockMessage = trim((string) ($product->affiliate_lock_message ?? ''));
                                        $isAdminProcuredProduct = (bool) ($hasSourceColumn ?? false)
                                            ? !empty($product->source_admin_product_id)
                                            : false;
                                        $unitLabel = strtolower(trim((string) ($product->unit ?? 'kg')));
                                        if ($unitLabel === '') {
                                            $unitLabel = 'kg';
                                        }
                                    @endphp
                                    <tr class="border-b border-slate-100 align-top last:border-0">
                                        <td class="py-3 pr-4">
                                            <div class="flex items-center gap-3">
                                                <div class="h-12 w-12 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                                    @if(!empty($product->image_url))
                                                        <img class="h-12 w-12 object-cover" src="{{ asset('storage/' . $product->image_url) }}" alt="{{ $product->name }}">
                                                    @else
                                                        <div class="flex h-12 w-12 items-center justify-center text-[10px] text-slate-500">No Img</div>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="font-medium text-slate-900">{{ $product->name }}</p>
                                                    <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit((string) $product->description, 60) }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">Rp {{ number_format((float) $product->price, 0, ',', '.') }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $product->stock_qty) }} {{ $unitLabel }}</td>
                                        <td class="py-3 pr-4">
                                            @if($isActive)
                                                <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Aktif</span>
                                            @else
                                                <span class="rounded-full bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-700">Nonaktif</span>
                                                @if($isLocked)
                                                    <p class="mt-1 text-[11px] text-rose-700">Bisa aktif lagi: {{ $lockLabel }}</p>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">
                                            @if((bool) ($product->is_affiliate_enabled ?? false))
                                                <span>Aktif</span>
                                                @if($affiliateContractExpireDate)
                                                    <p class="mt-1 text-[11px] text-slate-600">
                                                        Exp: {{ $affiliateContractExpireDate->translatedFormat('d M Y') }}
                                                    </p>
                                                @endif
                                                @if($affiliateLockedForProduct)
                                                    <p class="mt-1 text-[11px] text-amber-700">
                                                        {{ $affiliateLockMessage !== '' ? $affiliateLockMessage : 'Terkunci affiliate.' }}
                                                    </p>
                                                @endif
                                            @else
                                                <span>Tidak Aktif</span>
                                            @endif
                                        </td>
                                        <td class="relative overflow-visible py-3">
                                            <div class="flex items-center gap-2">
                                                @if($isActive && $affiliateLockedForProduct)
                                                    <button
                                                        type="button"
                                                        disabled
                                                        class="cursor-not-allowed rounded border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800"
                                                        title="Produk sedang dipromosikan affiliate dan tidak bisa dinonaktifkan."
                                                    >
                                                        Terkunci Affiliate
                                                    </button>
                                                @elseif($isActive)
                                                    <form action="{{ route('mitra.products.toggleActive', $product->id) }}" method="POST" onsubmit="return confirm('Nonaktifkan produk ini? Setelah nonaktif, produk baru bisa diaktifkan kembali setelah 1 minggu.');">
                                                        @csrf
                                                        <button type="submit" class="rounded border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100">
                                                            Nonaktifkan Jual
                                                        </button>
                                                    </form>
                                                @elseif($isLocked)
                                                    <button type="button" disabled class="cursor-not-allowed rounded border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500">
                                                        Terkunci 1 Minggu
                                                    </button>
                                                @elseif(! $isAdminProcuredProduct)
                                                    <a
                                                        href="{{ route('mitra.products.edit', $product->id) }}"
                                                        class="rounded border border-cyan-300 bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-100"
                                                    >
                                                        Aktifkan via Edit
                                                    </a>
                                                @else
                                                    <button
                                                        type="button"
                                                        class="rounded border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                                                        data-product-id="{{ (int) $product->id }}"
                                                        data-product-name="{{ (string) $product->name }}"
                                                        data-product-description="{{ (string) ($product->description ?? '') }}"
                                                        data-product-price="{{ (float) $product->price }}"
                                                        data-product-unit="{{ $unitLabel }}"
                                                        data-product-stock-qty="{{ (int) $product->stock_qty }}"
                                                        data-affiliate-lock-count="{{ $affiliateLockCount }}"
                                                        data-gallery-count="{{ (int) ($product->gallery_count ?? (empty($product->image_url) ? 0 : 1)) }}"
                                                        data-affiliate-enabled="{{ (bool) ($product->is_affiliate_enabled ?? false) ? '1' : '0' }}"
                                                        data-affiliate-commission="{{ number_format((float) ($product->affiliate_commission ?? 0), 2, '.', '') }}"
                                                        data-affiliate-expire-date="{{ !empty($product->affiliate_expire_date) ? \Illuminate\Support\Carbon::parse($product->affiliate_expire_date)->format('Y-m-d') : '' }}"
                                                        data-affiliate-locked="{{ $affiliateLockedForProduct ? '1' : '0' }}"
                                                        data-affiliate-lock-message="{{ $affiliateLockMessage }}"
                                                        data-affiliate-contract-expire-date="{{ $affiliateContractExpireDate?->format('Y-m-d') ?? '' }}"
                                                        data-product-image-url="{{ !empty($product->image_url) ? asset('storage/' . $product->image_url) : '' }}"
                                                        @click="openActivationFromDataset($el.dataset)"
                                                    >
                                                        Aktifkan Jual
                                                    </button>
                                                @endif

                                                <div class="relative">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 bg-white text-sm text-slate-600 hover:bg-slate-50"
                                                        @click.stop="toggleActionMenu({{ (int) $product->id }})"
                                                        aria-label="Aksi produk"
                                                    >
                                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                                    </button>

                                                    <div
                                                        x-cloak
                                                        x-show="menuProductId === {{ (int) $product->id }}"
                                                        x-transition
                                                        @click.outside="closeActionMenu()"
                                                        class="absolute right-0 top-full z-50 mt-1 w-32 rounded-lg border border-slate-200 bg-white p-1.5 shadow-xl"
                                                    >
                                                        <a
                                                            href="{{ route('mitra.products.edit', $product->id) }}"
                                                            class="block rounded-md px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                                            @click="closeActionMenu()"
                                                        >
                                                            Edit
                                                        </a>
                                                        <form action="{{ route('mitra.products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Hapus produk ini?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="submit"
                                                                class="block w-full rounded-md px-3 py-2 text-left text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                                                @click="closeActionMenu()"
                                                            >
                                                                Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div
                x-cloak
                x-show="activationModalOpen"
                class="fixed inset-0 z-50 flex items-start justify-center p-3 pt-6 sm:items-center sm:p-4"
                style="background-color: rgba(2, 6, 23, 0.55);"
                x-transition.opacity
            >
                <div class="w-full rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden" style="max-width: 760px; max-height: 88vh;" @click.outside="closeActivation()">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div>
                            <h4 class="text-base font-semibold text-slate-900">Proses Aktivasi Jual</h4>
                            <p class="text-xs text-slate-600">Lengkapi semua data wajib sebelum produk diaktifkan.</p>
                        </div>
                        <button type="button" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="closeActivation()">Tutup</button>
                    </div>

                    <form method="POST" :action="formAction" enctype="multipart/form-data" class="space-y-3 overflow-y-auto px-4 py-3" style="max-height: calc(88vh - 72px);">
                        @csrf
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Nama Produk</label>
                                <input type="text" name="name" x-model="form.name" class="w-full rounded-lg border-slate-300 text-sm" required>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Harga Jual</label>
                                <input type="number" name="price" x-model="form.price" min="1" step="0.01" class="w-full rounded-lg border-slate-300 text-sm" required>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Satuan</label>
                                <select name="unit" x-model="form.unit" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    @foreach(($allowedUnits ?? []) as $unitValue => $unitText)
                                        <option value="{{ $unitValue }}">{{ $unitText }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Stok Dijual</label>
                                <input type="number" name="stock_qty" x-model="form.stock_qty" min="20" step="1" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <p class="mt-1 text-[11px] text-slate-500">Minimal stok untuk aktivasi jual: 20.</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Affiliate</label>
                                <select
                                    name="is_affiliate_enabled"
                                    x-model="form.is_affiliate_enabled"
                                    class="w-full rounded-lg border-slate-300 text-sm"
                                    required
                                    @change="if (form.is_affiliate_enabled === '0') form.affiliate_expire_date = ''"
                                >
                                    <option value="1">Aktif untuk affiliate</option>
                                    <option value="0" :disabled="affiliateLocked">Tidak aktif untuk affiliate</option>
                                </select>
                                <p class="mt-1 text-[11px] text-amber-700" x-show="affiliateLocked" x-text="affiliateLockMessage !== '' ? affiliateLockMessage : 'Affiliate sedang terkunci untuk produk ini.'">
                                </p>
                                <p class="mt-1 text-[11px] text-slate-500" x-show="affiliateContractExpireDate !== ''">
                                    Exp lock: <span x-text="affiliateContractExpireDate"></span>
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Komisi Affiliate (%)</label>
                                <input
                                    type="number"
                                    name="affiliate_commission"
                                    x-model="form.affiliate_commission"
                                    :min="affiliateCommissionMin"
                                    :max="affiliateCommissionMax"
                                    step="0.01"
                                    class="w-full rounded-lg border-slate-300 text-sm"
                                    :required="form.is_affiliate_enabled === '1'"
                                    :disabled="form.is_affiliate_enabled !== '1'"
                                >
                                <p class="mt-1 text-[11px] text-slate-500">
                                    Batas komisi dari Admin: {{ $affiliateCommissionMinLabel }}% - {{ $affiliateCommissionMaxLabel }}%.
                                </p>
                            </div>
                            @if(($hasAffiliateExpireColumn ?? false))
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">Tanggal Berakhir Affiliate</label>
                                    <input
                                        type="date"
                                        name="affiliate_expire_date"
                                        min="{{ now()->toDateString() }}"
                                        x-model="form.affiliate_expire_date"
                                        class="w-full rounded-lg border-slate-300 text-sm"
                                        :required="form.is_affiliate_enabled === '1'"
                                        :disabled="form.is_affiliate_enabled !== '1'"
                                    >
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        Wajib diisi jika affiliate aktif.
                                    </p>
                                </div>
                            @endif
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Tambah Gambar Galeri</label>
                                <input type="file" name="images[]" accept=".jpg,.jpeg,.png" class="w-full rounded-lg border-slate-300 text-sm" multiple @change="onImageSelected($event)" :disabled="remainingSlots() === 0">
                                <p class="mt-1 text-[11px] text-slate-500">
                                    Galeri saat ini <strong x-text="existingGalleryCount"></strong>/<span x-text="maxGalleryAllowed"></span>.
                                    Minimal total <span x-text="minGalleryRequired"></span> gambar, maksimal <span x-text="maxGalleryAllowed"></span>.
                                </p>
                                <p class="mt-1 text-[11px] text-amber-700" x-show="minAdditionalRequired() > 0">
                                    Tambahkan minimal <strong x-text="minAdditionalRequired()"></strong> gambar lagi sebelum Aktifkan Jual.
                                </p>
                                <p class="mt-1 text-[11px] text-slate-500" x-show="remainingSlots() === 0">
                                    Kuota galeri sudah penuh (5 gambar). Tidak bisa upload gambar tambahan.
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">Deskripsi Produk</label>
                            <textarea name="description" x-model="form.description" rows="3" class="w-full rounded-lg border-slate-300 text-sm" required></textarea>
                        </div>

                        <template x-if="currentImage">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-semibold text-slate-700">Gambar saat ini:</p>
                                <img :src="currentImage" alt="Gambar produk saat ini" class="mt-2 h-20 w-20 rounded-lg object-cover">
                            </div>
                        </template>

                        <template x-if="selectedImagePreviews.length > 0">
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                                <p class="text-xs font-semibold text-emerald-700">
                                    Preview gambar yang dipilih:
                                    <span x-text="selectedImagePreviews.length"></span> file
                                </p>
                                <div class="mt-2 grid grid-cols-3 gap-2 md:grid-cols-4">
                                    <template x-for="(preview, idx) in selectedImagePreviews" :key="`${preview}-${idx}`">
                                        <img :src="preview" alt="Preview upload gambar produk" class="h-16 w-full rounded-lg object-cover">
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeActivation()">Batal</button>
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Aktifkan Jual</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</x-mitra-layout>
