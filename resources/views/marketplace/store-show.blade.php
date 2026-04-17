@extends('layouts.marketplace')

@section('title', ($store['store_name'] ?? 'Toko') . ' - Marketplace')
@section('pageTitle', 'Kunjungi Toko')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="surface-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                        {{ ($store['seller_type'] ?? 'seller') === 'mitra' ? 'Toko Mitra' : 'Toko Penjual' }}
                    </p>
                    <h2 class="mt-1 text-2xl font-extrabold text-slate-900">{{ $store['store_name'] ?? '-' }}</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Pemilik: <span class="font-semibold text-slate-800">{{ $store['seller_name'] ?? '-' }}</span>
                        @if(!empty($store['location_label']))
                            | Area: <span class="font-semibold text-slate-800">{{ $store['location_label'] }}</span>
                        @endif
                    </p>
                    <p class="mt-2 text-sm text-amber-700">
                        Rating:
                        @if((int) ($store['rating_total'] ?? 0) > 0)
                            <span class="font-semibold">
                                {{ number_format((float) ($store['rating_avg'] ?? 0), 1, ',', '.') }}/5
                                ({{ number_format((int) ($store['rating_total'] ?? 0)) }} ulasan)
                            </span>
                        @else
                            <span class="font-semibold">Belum ada ulasan</span>
                        @endif
                    </p>
                </div>

                <a href="{{ route('landing') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                    Kembali ke Marketplace
                </a>
            </div>
        </section>

        <section class="surface-card p-5">
            <h3 class="text-base font-bold text-slate-900">Produk Dijual / Aktif di Toko</h3>

            @if(($products ?? collect())->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Belum ada produk yang dapat ditampilkan dari toko ini.</p>
            @else
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($products as $item)
                        <article class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <img src="{{ $item['image_src'] }}" alt="{{ $item['name'] }}" class="h-32 w-full object-cover">
                            </div>
                            <h4 class="mt-2 line-clamp-2 text-sm font-bold text-slate-900">{{ $item['name'] }}</h4>
                            <p class="mt-1 line-clamp-2 text-xs text-slate-600">{{ \Illuminate\Support\Str::limit((string) ($item['description'] ?? ''), 80) }}</p>
                            <p class="mt-2 text-sm font-semibold text-emerald-700">{{ $item['price_label'] }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">Stok {{ $item['stock_label'] }}</p>
                            @if(!empty($item['can_buy']))
                                <a href="{{ route('marketplace.product.show', ['productType' => $item['product_type'], 'productId' => $item['id']]) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Beli
                                </a>
                            @else
                                <p class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-center text-[11px] font-semibold text-amber-700">
                                    Produk tidak tersedia untuk checkout
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
