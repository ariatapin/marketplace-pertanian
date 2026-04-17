<x-mitra-layout>
    <x-slot name="header">{{ __('Tambah Produk Baru') }}</x-slot>

    @php
        $affiliateCommissionRange = $affiliateCommissionRange ?? ['min' => 0, 'max' => 100];
        $affiliateCommissionMin = number_format((float) ($affiliateCommissionRange['min'] ?? 0), 2, '.', '');
        $affiliateCommissionMax = number_format((float) ($affiliateCommissionRange['max'] ?? 100), 2, '.', '');
    @endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    <form action="{{ route('mitra.products.store') }}" method="POST" enctype="multipart/form-data" x-data="{ listingStatus: '{{ old('is_active', '1') }}' }">
                        @csrf

                        <div class="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-xs text-cyan-800">
                            Form ini khusus untuk <strong>produk buatan Mitra sendiri</strong>. Produk dari pengadaan Admin dikelola lewat alur pengadaan dan aktivasi jual.
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Nama Produk</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required placeholder="Contoh: Pupuk Organik Cair">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Deskripsi</label>
                            <textarea name="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="3">{{ old('description') }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Harga (Rp)</label>
                                <input type="number" name="price" value="{{ old('price') }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Satuan</label>
                                <select name="unit" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                    @foreach(($allowedUnits ?? []) as $unitValue => $unitLabel)
                                        <option value="{{ $unitValue }}" @selected(old('unit', 'kg') === $unitValue)>{{ $unitLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Stok Awal</label>
                                <input type="number" name="stock_qty" min="20" value="{{ old('stock_qty', 20) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                <p class="mt-1 text-[11px] text-slate-500">Minimal stok 20 untuk produk baru.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="rounded-lg border border-slate-200 p-3">
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Status Tampil di Landing Page</label>
                                <select name="is_active" x-model="listingStatus" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                    <option value="1" @selected(old('is_active', '1') === '1')>Aktif (Tampil di Landing Page)</option>
                                    <option value="0" @selected(old('is_active') === '0')>Nonaktif (Simpan di Inventory saja)</option>
                                </select>
                            </div>
                            <div class="rounded-lg border border-slate-200 p-3">
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <input type="hidden" name="is_affiliate_enabled" value="0">
                                    <input type="checkbox" name="is_affiliate_enabled" value="1" class="rounded border-slate-300" @checked((string) old('is_affiliate_enabled', '0') === '1')>
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
                                        value="{{ old('affiliate_commission', $affiliateCommissionMin) }}"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    >
                                    <p class="mt-1 text-[11px] text-slate-500">Batas komisi dari Admin: {{ rtrim(rtrim($affiliateCommissionMin, '0'), '.') }}% - {{ rtrim(rtrim($affiliateCommissionMax, '0'), '.') }}%.</p>
                                </div>
                                @if(($hasAffiliateExpireColumn ?? false))
                                    <div class="mt-2">
                                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal Berakhir Affiliate</label>
                                        <input type="date" name="affiliate_expire_date" min="{{ now()->toDateString() }}" value="{{ old('affiliate_expire_date') }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <p class="mt-1 text-[11px] text-slate-500">Wajib diisi saat affiliate diaktifkan.</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if(($hasGalleryTable ?? false))
                            <div class="mb-4 rounded-lg border border-slate-200 p-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Galeri Produk</label>
                                <input type="file" name="gallery_images[]" accept=".jpg,.jpeg,.png" multiple class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                <p class="mt-1 text-xs text-amber-700" x-show="listingStatus === '1'">
                                    Status aktif: minimal 3 gambar agar proses jual konsisten.
                                </p>
                                <p class="mt-1 text-xs text-slate-600" x-show="listingStatus !== '1'">
                                    Status nonaktif: minimal 1 gambar (bisa dilengkapi saat aktivasi jual).
                                </p>
                                @error('gallery_images')
                                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                                @enderror
                                @error('gallery_images.*')
                                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Foto Produk</label>
                                <input type="file" name="image" accept=".jpg,.jpeg,.png" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                <p class="mt-1 text-xs text-gray-500">Gambar produk wajib saat tambah produk baru.</p>
                            </div>
                        @endif

                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Simpan Produk
                            </button>
                            <a href="{{ route('mitra.products.index') }}" class="text-gray-500 hover:text-gray-800">Batal</a>
                        </div>
                    </form>
                    </div>
            </div>
        </div>
    </div>
</x-mitra-layout>
