<x-seller-layout>
    <x-slot name="header">Tambah Produk Hasil Tani</x-slot>

    <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-3xl border border-slate-800/20 bg-gradient-to-r from-amber-700 via-amber-600 to-emerald-600 p-5 text-white shadow-xl md:p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs uppercase tracking-[0.2em] text-amber-100">Tambah Produk</p>
                    <h2 class="mt-1.5 text-2xl font-bold">Input Produk Hasil Tani Baru</h2>
                    <p class="mt-2 text-sm text-amber-50">
                        Setelah disimpan, produk langsung aktif di marketplace.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('seller.products.index') }}" class="inline-flex items-center rounded-lg border border-white/30 bg-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/25">
                        Kembali ke Daftar Produk
                    </a>
                </div>
            </div>
        </section>

        <section class="surface-card p-5">
            <form method="POST" action="{{ route('seller.products.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="product-name" class="mb-1 block text-sm font-medium text-slate-700">Nama Produk</label>
                        <input
                            id="product-name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"
                            placeholder="Contoh: Cabai Rawit 1kg"
                            required
                        >
                    </div>
                    <div>
                        <label for="product-price" class="mb-1 block text-sm font-medium text-slate-700">Harga (Rp)</label>
                        <input
                            id="product-price"
                            type="number"
                            name="price"
                            min="100"
                            step="1"
                            value="{{ old('price') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"
                            placeholder="Contoh: 25000"
                            required
                        >
                    </div>
                    <div>
                        <label for="product-stock" class="mb-1 block text-sm font-medium text-slate-700">Stok</label>
                        <input
                            id="product-stock"
                            type="number"
                            name="stock_qty"
                            min="1"
                            step="1"
                            value="{{ old('stock_qty') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"
                            placeholder="Contoh: 50"
                            required
                        >
                    </div>
                    <div>
                        <label for="product-harvest-date" class="mb-1 block text-sm font-medium text-slate-700">Tanggal Panen (Opsional)</label>
                        <input
                            id="product-harvest-date"
                            type="date"
                            name="harvest_date"
                            value="{{ old('harvest_date') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"
                        >
                    </div>
                </div>

                <div>
                    <label for="product-description" class="mb-1 block text-sm font-medium text-slate-700">Deskripsi (Opsional)</label>
                    <textarea
                        id="product-description"
                        name="description"
                        rows="4"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"
                        placeholder="Deskripsikan kualitas, kondisi, atau detail hasil panen."
                    >{{ old('description') }}</textarea>
                </div>

                <div>
                    <label for="product-image" class="mb-1 block text-sm font-medium text-slate-700">Gambar Produk (Opsional)</label>
                    <input
                        id="product-image"
                        type="file"
                        name="image"
                        accept="image/*"
                        class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-slate-700"
                    >
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a href="{{ route('seller.products.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                        Batal
                    </a>
                    <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        Simpan Produk
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-seller-layout>
