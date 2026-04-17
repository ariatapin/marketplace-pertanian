<x-seller-layout>
    <x-slot name="header">Produk Hasil Tani</x-slot>

    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3000)"
                x-show="show"
                x-transition.opacity
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
            >
                {{ session('status') }}
            </div>
        @endif

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
                    <p class="text-xs uppercase tracking-[0.2em] text-amber-100">Produk Penjual</p>
                    <h2 class="mt-1.5 text-2xl font-bold">Daftar Produk Hasil Tani Saya</h2>
                    <p class="mt-2 text-sm text-amber-50">
                        Semua produk yang Anda tambahkan langsung aktif di marketplace.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('seller.dashboard') }}" class="inline-flex items-center rounded-lg border border-white/30 bg-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/25">
                        Kembali ke Dashboard
                    </a>
                    <a href="{{ route('seller.products.create') }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                        Tambah Produk
                    </a>
                </div>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Daftar Produk Hasil Tani</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            Menampilkan semua produk yang sudah Anda tambahkan.
                            Total produk: <span class="font-semibold text-slate-700">{{ number_format((int) ($summary['total'] ?? 0)) }}</span>,
                            total stok: <span class="font-semibold text-slate-700">{{ number_format((int) ($summary['total_stock'] ?? 0)) }}</span>.
                        </p>
                    </div>
                    <form method="GET" action="{{ route('seller.products.index') }}" class="grid w-full grid-cols-1 gap-2 sm:grid-cols-[1fr_auto] lg:max-w-xl">
                        <input
                            type="text"
                            name="q"
                            value="{{ $filters['q'] ?? '' }}"
                            placeholder="Cari nama/deskripsi produk"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"
                        >
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Cari
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-slate-600">
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3 text-right">Harga</th>
                            <th class="px-4 py-3 text-right">Stok</th>
                            <th class="px-4 py-3">Update</th>
                            <th class="px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $imagePath = trim((string) ($product->image_url ?? ''));
                                $imageSrc = '';
                                if ($imagePath !== '') {
                                    if (\Illuminate\Support\Str::startsWith($imagePath, ['http://', 'https://', '/storage/', 'storage/'])) {
                                        $imageSrc = $imagePath;
                                    } else {
                                        $imageSrc = asset('storage/' . ltrim($imagePath, '/'));
                                    }
                                }
                            @endphp
                            <tr class="border-b border-slate-100 align-top last:border-0">
                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-3">
                                        <div class="h-14 w-14 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                            @if($imageSrc !== '')
                                                <img src="{{ $imageSrc }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-[10px] font-semibold text-slate-500">No Img</div>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit((string) ($product->description ?? '-'), 90) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Panen: {{ $product->harvest_date ? \Illuminate\Support\Carbon::parse($product->harvest_date)->format('d M Y') : '-' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">Rp{{ number_format((float) $product->price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((int) $product->stock_qty) }}</td>
                                <td class="px-4 py-3 text-xs text-slate-600">{{ \Illuminate\Support\Carbon::parse($product->updated_at)->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3" x-data="{ openEdit: false }">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                            @click="openEdit = true"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('seller.products.destroy', $product->id) }}" onsubmit="return confirm('Hapus produk ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">Hapus</button>
                                        </form>
                                    </div>

                                    <div
                                        x-cloak
                                        x-show="openEdit"
                                        x-transition.opacity
                                        class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/60 p-4"
                                        @click.self="openEdit = false"
                                    >
                                        <div class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                                            <div class="mb-3 flex items-center justify-between">
                                                <h4 class="text-sm font-semibold text-slate-900">Edit Produk</h4>
                                                <button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700 hover:bg-slate-100" @click="openEdit = false">
                                                    Tutup
                                                </button>
                                            </div>
                                            <form method="POST" action="{{ route('seller.products.update', $product->id) }}" enctype="multipart/form-data" class="space-y-3">
                                                @csrf
                                                @method('PATCH')
                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                    <input type="text" name="name" value="{{ $product->name }}" class="rounded-md border border-slate-300 px-2.5 py-2 text-xs focus:border-amber-400 focus:outline-none" required>
                                                    <input type="number" name="price" min="100" step="1" value="{{ (int) $product->price }}" class="rounded-md border border-slate-300 px-2.5 py-2 text-xs focus:border-amber-400 focus:outline-none" required>
                                                    <input type="number" name="stock_qty" min="1" step="1" value="{{ (int) $product->stock_qty }}" class="rounded-md border border-slate-300 px-2.5 py-2 text-xs focus:border-amber-400 focus:outline-none" required>
                                                    <input type="date" name="harvest_date" value="{{ $product->harvest_date ? \Illuminate\Support\Carbon::parse($product->harvest_date)->format('Y-m-d') : '' }}" class="rounded-md border border-slate-300 px-2.5 py-2 text-xs focus:border-amber-400 focus:outline-none">
                                                </div>
                                                <textarea name="description" rows="3" class="w-full rounded-md border border-slate-300 px-2.5 py-2 text-xs focus:border-amber-400 focus:outline-none">{{ $product->description }}</textarea>
                                                <input type="file" name="image" accept="image/*" class="block w-full rounded-md border border-slate-300 px-2.5 py-2 text-xs file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-slate-700">
                                                <label class="inline-flex items-center gap-1 text-xs text-slate-600">
                                                    <input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                                    Hapus gambar saat ini
                                                </label>
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    <button type="button" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" @click="openEdit = false">Batal</button>
                                                    <button type="submit" class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                    Belum ada produk. Klik tombol Tambah Produk untuk mulai jualan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($products, 'links'))
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $products->links() }}
                </div>
            @endif
        </section>
    </div>
</x-seller-layout>
