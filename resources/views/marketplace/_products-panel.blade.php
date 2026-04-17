@php
    $activeSource = (string) ($productSource ?? 'all');
    $isAffiliateReadyOnly = (bool) ($affiliateReadyOnly ?? false);
    $affiliateReadyCountValue = max(0, (int) ($affiliateReadyCount ?? 0));
    $affiliateReadyLabel = 'Siap Dipasarkan (' . number_format($affiliateReadyCountValue, 0, ',', '.') . ')';
    $sourceTabs = is_array($sourceTabs ?? null)
        ? $sourceTabs
        : [
            ['key' => 'all', 'label' => 'Semua Produk'],
            ['key' => 'seller', 'label' => 'Produk Petani'],
            ['key' => 'mitra', 'label' => 'Produk Mitra'],
        ];
    $affiliateAllParams = [];
    if (($searchKeyword ?? '') !== '') {
        $affiliateAllParams['q'] = $searchKeyword;
    }
    $affiliateAllParams['source'] = 'affiliate';
    $affiliateReadyParams = $affiliateAllParams;
    $affiliateReadyParams['ready_marketing'] = 1;
    $affiliateAllUrl = route('landing', $affiliateAllParams);
    $affiliateReadyUrl = route('landing', $affiliateReadyParams);
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap justify-center gap-3">
        @foreach($sourceTabs as $tab)
            @php
                $isActiveTab = $activeSource === $tab['key'];
                $tabParams = [];
                if ($searchKeyword !== '') {
                    $tabParams['q'] = $searchKeyword;
                }
                if ($tab['key'] !== 'all') {
                    $tabParams['source'] = $tab['key'];
                }
                if ($tab['key'] === 'affiliate' && $isAffiliateReadyOnly) {
                    $tabParams['ready_marketing'] = 1;
                }
                $tabUrl = route('landing', $tabParams);
            @endphp
            <a
                href="{{ $tabUrl }}"
                class="inline-flex items-center rounded-full border px-5 py-2 text-sm font-bold transition sm:text-base {{ $isActiveTab ? 'border-emerald-300 bg-emerald-100 text-emerald-800 shadow-sm' : 'border-slate-300 bg-white text-slate-700 hover:border-emerald-200 hover:text-emerald-700' }}"
                @click.prevent="switchMarketplaceSource({{ \Illuminate\Support\Js::from($tabUrl) }})"
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    @if($canUseAffiliateProductFilter && $activeSource === 'affiliate')
        <div class="flex flex-wrap items-center justify-center gap-2">
            <a
                href="{{ $affiliateAllUrl }}"
                class="inline-flex items-center rounded-full border px-3.5 py-1.5 text-xs font-semibold transition {{ $isAffiliateReadyOnly ? 'border-slate-300 bg-white text-slate-700 hover:border-emerald-200 hover:text-emerald-700' : 'border-emerald-300 bg-emerald-100 text-emerald-800' }}"
                @click.prevent="switchMarketplaceSource({{ \Illuminate\Support\Js::from($affiliateAllUrl) }})"
            >
                Semua Produk Affiliate
            </a>
            <a
                href="{{ $affiliateReadyUrl }}"
                class="inline-flex items-center rounded-full border px-3.5 py-1.5 text-xs font-semibold transition {{ $isAffiliateReadyOnly ? 'border-emerald-300 bg-emerald-100 text-emerald-800' : 'border-slate-300 bg-white text-slate-700 hover:border-emerald-200 hover:text-emerald-700' }}"
                @click.prevent="switchMarketplaceSource({{ \Illuminate\Support\Js::from($affiliateReadyUrl) }})"
            >
                {{ $affiliateReadyLabel }}
            </a>
        </div>
    @endif

    <div class="surface-card p-5">
        <div class="flex flex-col gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Produk</h2>
                <p class="text-sm text-slate-600">Gabungan produk Mitra dan Petani hasil tani yang siap dibeli sekarang.</p>
            </div>
        </div>
        <div
            x-cloak
            x-show="cartNoticeMessage !== ''"
            x-transition.opacity.duration.200ms
            class="mt-3 rounded-lg border px-3 py-2 text-sm font-semibold"
            :class="cartNoticeType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
        >
            <span x-text="cartNoticeMessage"></span>
        </div>

        @if((int) $featuredProductsCount < 1)
            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">
                Produk belum tersedia untuk filter saat ini. Coba ubah kata kunci pencarian.
            </div>
        @else
            <div class="mt-4 flex flex-wrap justify-center gap-3">
                @foreach($featuredProductCards as $product)
                    @php
                        $productDetailUrl = route('marketplace.product.show', [
                            'productType' => (string) ($product['product_type'] ?? 'store'),
                            'productId' => (int) ($product['id'] ?? 0),
                        ]);
                        $productCartKey = (string) ($product['product_type'] ?? 'store') . ':' . (int) ($product['id'] ?? 0);
                    @endphp
                    <article
                        class="group rounded-xl border bg-white p-3 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md {{ !empty($product['is_focus_product']) ? 'border-emerald-300 ring-1 ring-emerald-200' : 'border-slate-200' }}"
                        style="width: 220px;"
                    >
                        <div class="overflow-hidden rounded-lg border border-emerald-100 bg-gradient-to-br from-emerald-100 to-cyan-100" style="height: 132px;">
                            <a href="{{ $productDetailUrl }}" class="block h-full w-full">
                                @if(!empty($product['image_src']))
                                    <img src="{{ $product['image_src'] }}" alt="{{ $product['name'] }}" class="block transition duration-300 group-hover:scale-[1.03]" style="width: 100%; height: 100%; object-fit: cover; object-position: center;">
                                @else
                                    <div class="flex h-full items-center justify-center px-2 text-center text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                        {{ \Illuminate\Support\Str::limit($product['name'], 32) }}
                                    </div>
                                @endif
                            </a>
                        </div>
                        <h3 class="mt-2 line-clamp-2 text-sm font-bold text-slate-900">
                            <a href="{{ $productDetailUrl }}" class="hover:text-emerald-700">
                                {{ $product['name'] }}
                            </a>
                        </h3>
                        <p class="mt-1 line-clamp-2 min-h-[32px] text-[11px] text-slate-600">{{ \Illuminate\Support\Str::limit($product['description'], 64) }}</p>
                        <p class="mt-2 text-base font-bold text-emerald-700">{{ $product['price_label'] }}</p>
                        @if(!empty($product['show_affiliate_badge']))
                            <p class="mt-1 text-[10px] font-semibold text-cyan-700">Affiliate Aktif</p>
                        @endif
                        @if(!empty($product['is_marketed_by_affiliate']))
                            <p class="mt-1 text-[10px] font-semibold text-emerald-700">Sudah Dipasarkan</p>
                        @endif
                        @if($canUseAffiliateProductFilter && ($activeSource ?? 'all') === 'affiliate' && !empty($product['affiliate_share_url']))
                            <div class="mt-2 space-y-1" @click.stop>
                                @if(!empty($product['is_marketed_by_affiliate']))
                                    <a
                                        href="{{ $affiliateReadyUrl }}"
                                        class="inline-flex w-full items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100"
                                    >
                                        Siap Dipasarkan
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('affiliate.marketings.promote') }}">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ (int) ($product['id'] ?? 0) }}">
                                        <input type="hidden" name="redirect_to" value="{{ $affiliateReadyUrl }}">
                                        <button
                                            type="submit"
                                            class="inline-flex w-full items-center justify-center rounded-md border border-cyan-200 bg-cyan-50 px-2 py-1 text-[11px] font-semibold text-cyan-700 hover:bg-cyan-100"
                                        >
                                            Siap Dipasarkan
                                        </button>
                                    </form>
                                @endif
                                <button
                                    type="button"
                                    class="inline-flex w-full items-center justify-center rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                                    @click.stop="copyAffiliateLink({{ \Illuminate\Support\Js::from($product['affiliate_share_url']) }}, {{ (int) ($product['id'] ?? 0) }})"
                                >
                                    Salin Link Produk Affiliate
                                </button>
                                <p
                                    x-cloak
                                    x-show="affiliateCopiedProductId === {{ (int) ($product['id'] ?? 0) }}"
                                    class="text-[10px] font-semibold text-emerald-700"
                                >
                                    Link produk affiliate disalin.
                                </p>
                            </div>
                        @endif
                        <div class="mt-3 grid grid-cols-[44px_1fr] gap-2">
                            <button
                                type="button"
                                class="inline-flex h-9 w-11 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                                @click.prevent.stop="addProductToCart({
                                    productId: {{ (int) ($product['id'] ?? 0) }},
                                    productType: {{ \Illuminate\Support\Js::from((string) ($product['product_type'] ?? 'store')) }},
                                    qty: 1
                                })"
                                :disabled="addingProductKey === {{ \Illuminate\Support\Js::from($productCartKey) }}"
                                title="Masukkan ke Keranjang"
                                aria-label="Masukkan ke Keranjang"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current" aria-hidden="true">
                                    <path d="M7 4a1 1 0 1 1 2 0v1h6V4a1 1 0 1 1 2 0v1h2a1 1 0 0 1 1 1l-1.2 12.1A2 2 0 0 1 16.81 20H7.19a2 2 0 0 1-1.99-1.9L4 6a1 1 0 0 1 1-1h2V4Zm0 3H6.1l1 10.9h9.8l1-10.9H17v1a1 1 0 1 1-2 0V7H9v1a1 1 0 1 1-2 0V7Z"/>
                                </svg>
                            </button>
                            <a href="{{ $productDetailUrl }}" class="inline-flex h-9 items-center justify-center rounded-md bg-emerald-600 px-2 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                Beli
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
