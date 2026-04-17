<x-mitra-layout>
    <x-slot name="header">{{ __('Edit Produk') }}</x-slot>

    @php
        $isAdminProcuredProduct = (bool) ($isAdminProcuredProduct ?? false);
        $affiliateLockActive = (bool) ($affiliateLockActive ?? false);
        $activeAffiliateLockCount = (int) ($activeAffiliateLockCount ?? 0);
        $affiliateLockMessage = trim((string) ($affiliateLockMessage ?? ''));
        $affiliateLockedForProduct = $affiliateLockActive && (bool) ($product->is_affiliate_enabled ?? false);
        $hasGalleryTable = (bool) ($hasGalleryTable ?? false);
        $hasAffiliateExpireColumn = (bool) ($hasAffiliateExpireColumn ?? false);
        $galleryPaths = collect($galleryPaths ?? [])
            ->map(fn ($path) => trim((string) $path))
            ->filter()
            ->values();
        $galleryCount = max(0, (int) ($galleryCount ?? $galleryPaths->count()));
        $remainingGallerySlots = max(0, (int) ($remainingGallerySlots ?? 0));
        $canUploadMoreGallery = $remainingGallerySlots > 0;
        $affiliateCommissionRange = $affiliateCommissionRange ?? ['min' => 0, 'max' => 100];
        $affiliateCommissionMin = number_format((float) ($affiliateCommissionRange['min'] ?? 0), 2, '.', '');
        $affiliateCommissionMax = number_format((float) ($affiliateCommissionRange['max'] ?? 100), 2, '.', '');
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($affiliateLockedForProduct)
                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            @if($affiliateLockMessage !== '')
                                {{ $affiliateLockMessage }}
                            @else
                                Produk ini sedang dipromosikan oleh {{ $activeAffiliateLockCount }} affiliate aktif.
                                Status affiliate dan status jual tidak bisa dimatikan sampai promosi affiliate selesai.
                            @endif
                        </div>
                    @endif

                    <form action="{{ route('mitra.products.update', $product->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Nama Produk</label>
                            <input type="text" name="name" value="{{ old('name', $product->name) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight" required>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Deskripsi</label>
                            <textarea name="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight" rows="3">{{ old('description', $product->description) }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Harga (Rp)</label>
                                <input type="number" name="price" value="{{ old('price', $product->price) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Satuan</label>
                                <select name="unit" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight" required>
                                    @foreach(($allowedUnits ?? []) as $unitValue => $unitLabel)
                                        <option value="{{ $unitValue }}" @selected(old('unit', $product->unit ?? 'kg') === $unitValue)>{{ $unitLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Stok</label>
                                <input type="number" name="stock_qty" value="{{ old('stock_qty', $product->stock_qty) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight" required>
                            </div>
                        </div>

                        @if($isAdminProcuredProduct)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-700">Status Jual Produk Pengadaan Admin</p>
                                <p class="mt-1 text-xs text-slate-600">
                                    Produk dari pengadaan Admin memakai jalur terpisah. Aktif/nonaktif jual dilakukan dari halaman inventori lewat tombol <strong>Proses Aktivasi Jual</strong> dan <strong>Nonaktifkan Jual</strong>.
                                </p>
                            </div>
                        @else
                            <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-3">
                                <p class="text-sm font-semibold text-cyan-800">Status Jual Produk Mitra Sendiri</p>
                                <p class="mt-1 text-xs text-cyan-800">
                                    Produk buatan Mitra bisa diaktifkan/nonaktifkan langsung dari form edit ini.
                                </p>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-4">
                            @if(! $isAdminProcuredProduct)
                                <div class="rounded-lg border border-slate-200 p-3">
                                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                        @if($affiliateLockedForProduct)
                                            <input type="hidden" name="is_active" value="{{ (bool) ($product->is_active ?? true) ? 1 : 0 }}">
                                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked((bool) ($product->is_active ?? true)) disabled>
                                        @else
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked((bool) old('is_active', $product->is_active ?? true))>
                                        @endif
                                        <span>Produk aktif dijual</span>
                                    </label>
                                    @if($affiliateLockedForProduct)
                                        <p class="mt-2 text-xs text-amber-700">{{ $affiliateLockMessage !== '' ? $affiliateLockMessage : 'Dikunci karena masih ada affiliate aktif memasarkan produk ini.' }}</p>
                                    @endif
                                </div>
                            @endif

                            <div class="rounded-lg border border-slate-200 p-3">
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    @if($affiliateLockedForProduct)
                                        <input type="hidden" name="is_affiliate_enabled" value="1">
                                        <input type="checkbox" name="is_affiliate_enabled" value="1" class="rounded border-slate-300" checked disabled>
                                    @else
                                        <input type="hidden" name="is_affiliate_enabled" value="0">
                                        <input type="checkbox" name="is_affiliate_enabled" value="1" class="rounded border-slate-300" @checked((bool) old('is_affiliate_enabled', $product->is_affiliate_enabled ?? false))>
                                    @endif
                                    <span>Aktifkan pemasaran affiliate</span>
                                </label>
                                <div class="mt-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Komisi Affiliate (%)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="{{ $affiliateCommissionMin }}"
                                        max="{{ $affiliateCommissionMax }}"
                                        name="affiliate_commission"
                                        value="{{ old('affiliate_commission', number_format((float) ($product->affiliate_commission ?? 0), 2, '.', '')) }}"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight"
                                    >
                                    <p class="mt-1 text-[11px] text-slate-500">Batas komisi dari Admin: {{ rtrim(rtrim($affiliateCommissionMin, '0'), '.') }}% - {{ rtrim(rtrim($affiliateCommissionMax, '0'), '.') }}%.</p>
                                </div>
                                @if($hasAffiliateExpireColumn)
                                    <div class="mt-2">
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal Berakhir Affiliate</label>
                                        <input
                                            type="date"
                                            name="affiliate_expire_date"
                                            min="{{ now()->toDateString() }}"
                                            value="{{ old('affiliate_expire_date', !empty($product->affiliate_expire_date) ? \Illuminate\Support\Carbon::parse($product->affiliate_expire_date)->format('Y-m-d') : '') }}"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight"
                                        >
                                        <p class="mt-1 text-[11px] text-slate-500">Wajib diisi saat affiliate diaktifkan.</p>
                                    </div>
                                @endif
                                @if($affiliateLockedForProduct)
                                    <p class="mt-2 text-xs text-amber-700">{{ $affiliateLockMessage !== '' ? $affiliateLockMessage : 'Affiliate tidak bisa dimatikan sampai tidak ada affiliate aktif pada produk ini.' }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <label class="block text-gray-700 text-sm font-bold">Galeri Produk</label>
                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600">
                                    {{ $galleryCount }}/5 gambar
                                </span>
                            </div>

                            @if($galleryPaths->isNotEmpty())
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                                    @foreach($galleryPaths as $galleryPath)
                                        <div class="aspect-square overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                            <img
                                                src="{{ asset('storage/' . ltrim($galleryPath, '/')) }}"
                                                alt="Galeri {{ $product->name }}"
                                                class="h-full w-full object-cover"
                                            >
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-slate-500">Belum ada gambar tersimpan untuk produk ini.</p>
                            @endif

                            @if($hasGalleryTable)
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Tambah Gambar Galeri</label>
                                    <input
                                        type="file"
                                        name="gallery_images[]"
                                        accept=".jpg,.jpeg,.png"
                                        multiple
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight"
                                        @disabled(! $canUploadMoreGallery)
                                    >
                                    @if($canUploadMoreGallery)
                                        <p class="text-xs text-gray-500 mt-1">Anda bisa menambah maksimal {{ $remainingGallerySlots }} gambar lagi (maks 5 gambar per produk).</p>
                                    @else
                                        <p class="text-xs text-amber-700 mt-1">Galeri sudah penuh (maksimal 5 gambar).</p>
                                    @endif
                                    @error('gallery_images')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                    @error('gallery_images.*')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @else
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Ganti Foto Produk</label>
                                    <input type="file" name="image" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight">
                                    <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengganti gambar.</p>
                                    @error('image')
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Update Produk</button>
                            <div class="flex items-center gap-4">
                                <a href="{{ route('mitra.products.stockHistory', $product->id) }}" class="text-slate-600 hover:text-slate-800">Riwayat Mutasi</a>
                                <a href="{{ route('mitra.products.index') }}" class="text-gray-500">Batal</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Mutasi Stok Terbaru</h3>
                        <a href="{{ route('mitra.products.stockHistory', $product->id) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Lihat semua</a>
                    </div>

                    @if(($mutations ?? collect())->isEmpty())
                        <p class="mt-3 text-sm text-gray-600">Belum ada mutasi stok untuk produk ini.</p>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left text-gray-600">
                                        <th class="py-2 pr-4">Waktu</th>
                                        <th class="py-2 pr-4">Tipe</th>
                                        <th class="py-2 pr-4">Before</th>
                                        <th class="py-2 pr-4">Delta</th>
                                        <th class="py-2 pr-4">After</th>
                                        <th class="py-2">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mutations as $m)
                                        <tr class="border-b last:border-0">
                                            <td class="py-3 pr-4 text-gray-700">{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d M Y H:i') }}</td>
                                            <td class="py-3 pr-4 uppercase text-gray-700">{{ $m->change_type }}</td>
                                            <td class="py-3 pr-4 text-gray-700">{{ number_format((int) $m->qty_before) }}</td>
                                            <td class="py-3 pr-4 text-gray-700">{{ (int) $m->qty_delta > 0 ? '+' : '' }}{{ number_format((int) $m->qty_delta) }}</td>
                                            <td class="py-3 pr-4 text-gray-700">{{ number_format((int) $m->qty_after) }}</td>
                                            <td class="py-3 text-gray-600">{{ $m->note ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-mitra-layout>
