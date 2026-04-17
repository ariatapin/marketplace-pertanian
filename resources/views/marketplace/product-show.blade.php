@extends('layouts.marketplace')

@section('title', ($product['name'] ?? 'Detail Produk') . ' - Marketplace')
@section('pageTitle', 'Detail Produk')

@section('content')
    @php
        $productType = (string) ($product['product_type'] ?? 'store');
        $stockQty = max(1, (int) ($product['stock_qty'] ?? 1));
        $storeProfile = is_array($storeProfile ?? null) ? $storeProfile : [];
        $storeAvatarUrl = trim((string) ($storeProfile['avatar_url'] ?? ($product['seller_avatar_url'] ?? '')));
        $storeDisplayName = (string) ($storeProfile['store_name'] ?? ($product['store_name'] ?? ($product['seller_name'] ?? '-')));
        $storeLocation = (string) ($storeProfile['location_label'] ?? ($product['seller_location_label'] ?? ''));
        $storeProductsTotal = (int) ($storeProfile['products_total'] ?? 0);
        $storeRatingTotal = (int) ($storeProfile['rating_total'] ?? ($product['seller_rating_total'] ?? 0));
        $storeRatingAvg = (float) ($storeProfile['rating_avg'] ?? ($product['seller_rating_avg'] ?? 0));
        $storeJoinedLabel = (string) ($storeProfile['joined_label'] ?? 'Belum tersedia');
        $storeChatWhatsappUrl = trim((string) ($storeProfile['chat_whatsapp_url'] ?? ''));
        $storeInitial = strtoupper(substr(trim((string) ($product['seller_name'] ?? $storeDisplayName)), 0, 1));
        $sourceContext = strtolower(trim((string) request()->query('source', '')));
        $showBackToCart = $sourceContext === 'cart';
        if ($storeInitial === '') {
            $storeInitial = 'T';
        }

        $paymentMethodMeta = collect(is_array($checkoutPaymentMethodMeta ?? null) ? $checkoutPaymentMethodMeta : [])
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['method'] ?? '')) !== '')
            ->values()
            ->all();
        $walletBalanceAmount = max(0, (float) ($walletBalance ?? 0));
        $walletBalanceLabel = 'Rp' . number_format($walletBalanceAmount, 0, ',', '.');

        $paymentMethodOptions = [];
        $saldoMethodCode = '';
        foreach ($paymentMethodMeta as $meta) {
            $method = trim((string) ($meta['method'] ?? ''));
            if ($method === '') {
                continue;
            }

            $option = [
                'method' => $method,
                'label' => trim((string) ($meta['label'] ?? strtoupper($method))),
                'kind' => strtolower(trim((string) ($meta['kind'] ?? 'wallet'))),
            ];

            if ($saldoMethodCode === '' && $option['kind'] === 'wallet') {
                $saldoMethodCode = $method;
            }

            $paymentMethodOptions[] = $option;
        }

        if ($saldoMethodCode === '' && ! empty($paymentMethodOptions)) {
            $saldoMethodCode = (string) ($paymentMethodOptions[0]['method'] ?? '');
        }

        $transferMethodOptions = $paymentMethodOptions;
        $hasTransferOptions = ! empty($transferMethodOptions);
        $canUseSaldoOption = $saldoMethodCode !== '' && ($walletBalanceAmount > 0 || ! $hasTransferOptions);

        $transferMethodCodes = array_values(array_map(
            fn (array $item): string => (string) ($item['method'] ?? ''),
            $transferMethodOptions
        ));

        $transferSubmitMethodCode = '';
        foreach ($transferMethodOptions as $transferOption) {
            if ((string) ($transferOption['method'] ?? '') === 'bank_transfer') {
                $transferSubmitMethodCode = 'bank_transfer';
                break;
            }
        }
        if ($transferSubmitMethodCode === '') {
            $transferSubmitMethodCode = (string) ($transferMethodCodes[0] ?? '');
        }

        $defaultTransferMethodCode = trim((string) ($defaultCheckoutPaymentMethod ?? ''));
        if ($defaultTransferMethodCode === '' || ! in_array($defaultTransferMethodCode, $transferMethodCodes, true)) {
            $defaultTransferMethodCode = (string) ($transferMethodCodes[0] ?? '');
        }

        $defaultPaymentGroup = $canUseSaldoOption ? 'saldo' : 'transfer';
        if ($defaultPaymentGroup === 'transfer' && $defaultTransferMethodCode === '' && $canUseSaldoOption) {
            $defaultPaymentGroup = 'saldo';
        }

        $initialPaymentMethod = '';
        if ($defaultPaymentGroup === 'saldo' && $canUseSaldoOption) {
            $initialPaymentMethod = $saldoMethodCode;
        } elseif ($transferSubmitMethodCode !== '') {
            $initialPaymentMethod = $transferSubmitMethodCode;
        } else {
            $initialPaymentMethod = $saldoMethodCode;
        }

        $sellerReviewItems = collect($sellerReviews ?? collect())
            ->filter(fn ($item) => is_array($item))
            ->values();
        $sellerReviewPages = $sellerReviewItems->chunk(10)->values();
        $sellerReviewPageCount = $sellerReviewPages->count();
        $authRedirectUrl = url()->full();
    @endphp
    <style>
        .mk-product-detail-grid {
            display: block;
        }

        @media (min-width: 1100px) {
            .mk-product-detail-grid {
                display: grid;
                grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr);
            }

            .mk-product-detail-gallery {
                border-right: 1px solid #e2e8f0;
                border-bottom: 0 !important;
            }
        }
    </style>

    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        @auth
            @if($showBackToCart && $role === 'consumer')
                <div>
                    <a href="{{ route('cart.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                        <i class="fa-solid fa-arrow-left mr-2 text-xs" aria-hidden="true"></i>
                        Kembali ke Keranjang
                    </a>
                </div>
            @endif
        @endauth

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section
            class="surface-card overflow-hidden p-0"
            x-data="{
                activeImage: {{ \Illuminate\Support\Js::from(($product['gallery_images'][0] ?? $product['image_src'] ?? null)) }},
                showReportForm: false,
                qty: 1,
                paymentGroup: {{ \Illuminate\Support\Js::from($defaultPaymentGroup) }},
                paymentMethod: {{ \Illuminate\Support\Js::from($initialPaymentMethod) }},
                saldoMethod: {{ \Illuminate\Support\Js::from($saldoMethodCode) }},
                transferMethod: {{ \Illuminate\Support\Js::from($defaultTransferMethodCode) }},
                transferSubmitMethod: {{ \Illuminate\Support\Js::from($transferSubmitMethodCode) }},
                canUseSaldo: {{ \Illuminate\Support\Js::from($canUseSaldoOption) }},
                unitPrice: {{ \Illuminate\Support\Js::from((float) ($product['price'] ?? 0)) }},
                formatRupiah(value) {
                    const safe = Number(value || 0);
                    return 'Rp' + new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(safe)));
                },
                totalPrice() {
                    return Math.max(1, Number(this.qty || 1)) * Number(this.unitPrice || 0);
                },
                syncPaymentMethod() {
                    if (this.paymentGroup === 'saldo' && this.saldoMethod && this.canUseSaldo) {
                        this.paymentMethod = this.saldoMethod;
                        return;
                    }

                    if (this.paymentGroup === 'transfer' && (this.transferSubmitMethod || this.transferMethod)) {
                        this.paymentMethod = this.transferSubmitMethod || this.transferMethod;
                        return;
                    }

                    if (this.saldoMethod && this.canUseSaldo) {
                        this.paymentGroup = 'saldo';
                        this.paymentMethod = this.saldoMethod;
                        return;
                    }

                    if (this.transferMethod) {
                        this.paymentGroup = 'transfer';
                        this.paymentMethod = this.transferMethod;
                        return;
                    }

                    this.paymentMethod = this.saldoMethod || this.transferMethod || '';
                }
            }"
            x-init="syncPaymentMethod()"
        >
            <div class="mk-product-detail-grid">
                <aside class="mk-product-detail-gallery border-b border-slate-200 p-4 sm:p-5">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <img
                            :src="activeImage || {{ \Illuminate\Support\Js::from($product['image_src'] ?? asset('images/product-placeholder.svg')) }}"
                            alt="{{ $product['name'] ?? 'Produk' }}"
                            class="w-full object-cover"
                            style="height: clamp(240px, 34vw, 380px);"
                        >
                    </div>

                    @if(!empty($product['gallery_images']) && count($product['gallery_images']) > 1)
                        <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                            @foreach($product['gallery_images'] as $galleryImage)
                                <button
                                    type="button"
                                    class="h-14 w-14 flex-none overflow-hidden rounded-md border border-slate-200 bg-white sm:h-16 sm:w-16"
                                    :class="activeImage === {{ \Illuminate\Support\Js::from($galleryImage) }} ? 'ring-2 ring-emerald-400' : ''"
                                    @click="activeImage = {{ \Illuminate\Support\Js::from($galleryImage) }}"
                                >
                                    <img src="{{ $galleryImage }}" alt="Galeri {{ $product['name'] }}" class="h-full w-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </aside>

                <div class="space-y-3 p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Marketplace Produk</p>
                            <h1 class="mt-1 text-2xl font-extrabold leading-tight text-slate-900">{{ $product['name'] ?? 'Produk' }}</h1>
                        </div>

                        @auth
                            @if($role === 'consumer')
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100"
                                    @click="showReportForm = !showReportForm"
                                >
                                    Laporkan
                                </button>
                            @endif
                        @else
                            <a
                                href="{{ route('login', ['redirect' => $authRedirectUrl]) }}"
                                class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100"
                            >
                                Laporkan
                            </a>
                        @endauth
                    </div>

                    @auth
                        @if($role === 'consumer')
                            <form
                                method="POST"
                                action="{{ route('marketplace.product.report', ['productType' => $product['product_type'], 'productId' => $product['id']]) }}"
                                x-cloak
                                x-show="showReportForm"
                                class="rounded-xl border border-rose-200 bg-rose-50/60 p-3"
                            >
                                @csrf
                                <p class="text-sm font-semibold text-rose-800">Laporkan Produk ke Admin</p>
                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <label class="md:col-span-1">
                                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-rose-700">Kategori</span>
                                        <select name="category" class="w-full rounded-md border-rose-200 bg-white text-sm text-slate-700" required>
                                            <option value="fraud">Penipuan</option>
                                            <option value="fake_product">Produk Palsu</option>
                                            <option value="misleading_info">Informasi Menyesatkan</option>
                                            <option value="spam">Spam</option>
                                            <option value="other">Lainnya</option>
                                        </select>
                                    </label>
                                    <label class="md:col-span-2">
                                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-rose-700">Deskripsi Laporan</span>
                                        <textarea name="description" rows="2" class="w-full rounded-md border-rose-200 bg-white text-sm text-slate-700" placeholder="Jelaskan alasan laporan secara singkat dan jelas." required></textarea>
                                    </label>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="submit" class="rounded-md bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">
                                        Kirim Laporan
                                    </button>
                                </div>
                            </form>
                        @endif
                    @endauth

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Harga</p>
                        @if($role === 'consumer' && ($canConsumerCheckoutMarketplace ?? false) && !empty($product['can_buy']))
                            <p class="mt-1 text-4xl font-black leading-none text-slate-900" x-text="formatRupiah(totalPrice())">{{ $product['price_label'] ?? 'Rp0' }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Harga satuan: <span class="font-semibold text-slate-700">{{ $product['price_label'] ?? 'Rp0' }}</span>
                            </p>

                            <div class="mt-4 border-t border-slate-200 pt-3">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:items-start">
                                <div>
                                    <div class="mb-2 flex items-center justify-between">
                                        <label for="qty" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kuantitas</label>
                                        <p class="text-[11px] text-slate-500">
                                            Maks <span class="font-semibold text-slate-700">{{ $stockQty }}</span>
                                        </p>
                                    </div>
                                    <div class="grid grid-cols-[42px_1fr_42px] gap-2">
                                        <button type="button" class="rounded-md border border-slate-300 bg-white text-lg font-bold text-slate-700 hover:bg-slate-100" @click="qty = Math.max(1, qty - 1)">-</button>
                                        <input id="qty" type="number" min="1" max="{{ $stockQty }}" x-model.number="qty" @input="qty = Math.min({{ $stockQty }}, Math.max(1, qty || 1))" class="w-full rounded-md border border-slate-300 px-2 py-2 text-center text-sm focus:border-emerald-400 focus:outline-none">
                                        <button type="button" class="rounded-md border border-slate-300 bg-white text-lg font-bold text-slate-700 hover:bg-slate-100" @click="qty = Math.min({{ $stockQty }}, qty + 1)">+</button>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="block">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Metode Pembayaran</span>
                                        <select
                                            x-model="paymentGroup"
                                            @change="syncPaymentMethod()"
                                            class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm focus:border-emerald-400 focus:outline-none"
                                        >
                                            @if($saldoMethodCode !== '')
                                                <option value="saldo" @disabled(! $canUseSaldoOption)>
                                                    Saldo ({{ $walletBalanceLabel }})
                                                </option>
                                            @endif
                                            @if($hasTransferOptions)
                                                <option value="transfer">Transfer</option>
                                            @endif
                                        </select>
                                    </label>

                                    @if($hasTransferOptions)
                                        <label class="block" x-show="paymentGroup === 'transfer'" x-cloak>
                                            <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Channel Transfer</span>
                                            <select
                                                x-model="transferMethod"
                                                @change="syncPaymentMethod()"
                                                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm focus:border-emerald-400 focus:outline-none"
                                            >
                                                @foreach($transferMethodOptions as $transferOption)
                                                    <option value="{{ (string) ($transferOption['method'] ?? '') }}">
                                                        {{ (string) ($transferOption['label'] ?? strtoupper((string) ($transferOption['method'] ?? ''))) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif

                                    @if($saldoMethodCode !== '' && ! $canUseSaldoOption && $hasTransferOptions)
                                        <p class="text-[11px] font-medium text-amber-700">
                                            Saldo belum tersedia. Gunakan opsi Transfer.
                                        </p>
                                    @endif
                                </div>
                                </div>
                            </div>
                        @else
                            <p class="mt-1 text-4xl font-black leading-none text-slate-900">{{ $product['price_label'] ?? 'Rp0' }}</p>
                        @endif
                    </div>

                    <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-3 text-sm">
                        <div class="grid grid-cols-[108px_1fr] gap-2">
                            <span class="font-semibold text-slate-500">Pengiriman</span>
                            <span class="text-slate-700">Diatur oleh penjual dari wilayah {{ $product['seller_location_label'] ?? 'lokasi toko' }}.</span>
                        </div>
                        <div class="grid grid-cols-[108px_1fr] gap-2">
                            <span class="font-semibold text-slate-500">Ketersediaan</span>
                            <span class="text-slate-700">{{ !empty($product['can_buy']) ? 'Siap dibeli' : 'Stok habis / tidak aktif' }}</span>
                        </div>
                    </div>

                    @auth
                        @if($role === 'consumer')
                            @if(($canConsumerCheckoutMarketplace ?? false) && !empty($product['can_buy']))
                                <form method="POST" action="{{ route('cart.store') }}" enctype="multipart/form-data" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ (int) ($product['id'] ?? 0) }}">
                                    <input type="hidden" name="product_type" value="{{ $productType }}">
                                    <input type="hidden" name="qty" :value="Math.min({{ $stockQty }}, Math.max(1, qty || 1))">
                                    <input type="hidden" name="payment_method" :value="paymentMethod">

                                    <label class="block" x-show="paymentGroup === 'transfer' && paymentMethod === 'bank_transfer'" x-cloak>
                                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Upload Bukti Transfer (untuk Beli Sekarang)</span>
                                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf,.webp" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm focus:border-emerald-400 focus:outline-none" :required="paymentGroup === 'transfer' && paymentMethod === 'bank_transfer'">
                                    </label>

                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <button type="submit" name="buy_now" value="0" formnovalidate class="w-full rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:order-1">
                                            Masukkan Keranjang
                                        </button>
                                        <button type="submit" name="buy_now" value="1" class="w-full rounded-md bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 sm:order-2">
                                            Beli Sekarang
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                    {{ (string) (($checkoutModeMeta['helper'] ?? null) ?: 'Checkout sementara tidak tersedia untuk akun ini.') }}
                                </div>
                            @endif
                        @else
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                Akun ini tidak dapat checkout di marketplace.
                            </div>
                        @endif
                    @else
                        <div class="space-y-2">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <a href="{{ route('login', ['redirect' => $authRedirectUrl]) }}" class="w-full rounded-md border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                Login
                            </a>
                            <a href="{{ route('register', ['redirect' => $authRedirectUrl]) }}" class="w-full rounded-md bg-emerald-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-emerald-700">
                                Daftar
                            </a>
                            </div>
                        </div>
                    @endauth
                </div>
            </div>

            <div class="border-t border-slate-200 p-4 sm:p-5">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold text-slate-900">Deskripsi Produk</h3>
                    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $product['description_long'] ?? '-' }}</p>
                </div>
            </div>
        </section>

        <section class="surface-card p-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 lg:items-center">
                    <div class="flex items-start gap-4 lg:border-r lg:border-slate-200 lg:pr-6">
                    <div class="relative">
                        <div class="h-20 w-20 overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                            @if($storeAvatarUrl !== '')
                                <img src="{{ $storeAvatarUrl }}" alt="Logo {{ $storeDisplayName }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-2xl font-bold text-slate-500">{{ $storeInitial }}</div>
                            @endif
                        </div>
                        <span class="absolute -bottom-1 left-1 rounded-md bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Toko</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h3 class="mt-1 truncate text-2xl font-bold text-slate-900">{{ $storeDisplayName }}</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            @if($storeLocation !== '')
                                Aktif di area {{ $storeLocation }}
                            @else
                                Aktif di marketplace
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-slate-700">
                            Rating:
                            <span class="font-semibold">
                                {{ $storeRatingTotal > 0 ? number_format($storeRatingAvg, 1, ',', '.') . '/5' : '-' }}
                            </span>
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if($storeChatWhatsappUrl !== '')
                                <a
                                    href="{{ $storeChatWhatsappUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100"
                                    aria-label="Chat via WhatsApp"
                                    title="Chat via WhatsApp"
                                >
                                    <i class="fa-brands fa-whatsapp text-lg" aria-hidden="true"></i>
                                </a>
                            @else
                                <button type="button" disabled class="inline-flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-lg border border-slate-300 bg-slate-100 text-slate-400" aria-label="Nomor WhatsApp belum tersedia" title="Nomor WhatsApp belum tersedia">
                                    <i class="fa-brands fa-whatsapp text-lg" aria-hidden="true"></i>
                                </button>
                            @endif
                            <a href="{{ $storeUrl }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                Kunjungi Toko
                            </a>
                        </div>
                    </div>
                    </div>

                    <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Produk</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ number_format($storeProductsTotal) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bergabung</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ $storeJoinedLabel }}</p>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-5 border-t border-slate-200 pt-4"
                    x-data="{
                        reviewSide: 0,
                        totalSides: {{ (int) $sellerReviewPageCount }},
                        nextSide() {
                            if (this.reviewSide < this.totalSides - 1) {
                                this.reviewSide += 1;
                            }
                        },
                        prevSide() {
                            if (this.reviewSide > 0) {
                                this.reviewSide -= 1;
                            }
                        }
                    }"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-bold text-slate-900">Ulasan Toko</h3>
                        @if($sellerReviewPageCount > 1)
                            <div class="inline-flex items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="prevSide()"
                                    :disabled="reviewSide === 0"
                                >
                                    Sebelumnya
                                </button>
                                <span class="text-xs font-semibold text-slate-500">
                                    Sisi <span x-text="reviewSide + 1"></span> / {{ $sellerReviewPageCount }}
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="nextSide()"
                                    :disabled="reviewSide >= totalSides - 1"
                                >
                                    Berikutnya
                                </button>
                            </div>
                        @endif
                    </div>

                    @if($sellerReviewItems->isEmpty())
                        <p class="mt-3 text-sm text-slate-600">Belum ada ulasan untuk toko ini.</p>
                    @else
                        <div class="mt-4 space-y-3">
                            @foreach($sellerReviewPages as $pageIndex => $reviewPage)
                                <div x-cloak x-show="reviewSide === {{ $pageIndex }}" class="space-y-3">
                                    @foreach($reviewPage as $review)
                                        <article class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-sm font-semibold text-slate-900">{{ (string) ($review['reviewer_name'] ?? 'User') }}</p>
                                                <p class="text-xs font-semibold text-amber-700">{{ (int) ($review['score'] ?? 0) }}/5</p>
                                            </div>
                                            <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ (string) ($review['review'] ?? '-') }}</p>
                                            <p class="mt-1 text-[11px] text-slate-500">{{ (string) ($review['created_at_label'] ?? '-') }}</p>
                                        </article>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="surface-card p-5">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-bold text-slate-900">Produk Lain dari Toko Ini</h3>
                @if(!($relatedProducts ?? collect())->isEmpty())
                    <a href="{{ $storeUrl }}" class="text-xs font-semibold text-indigo-700 hover:text-indigo-900">Lihat Semua</a>
                @endif
            </div>

            @if(($relatedProducts ?? collect())->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Belum ada produk lain yang aktif dari toko ini.</p>
            @else
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($relatedProducts as $relatedProduct)
                        <article class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <img src="{{ $relatedProduct['image_src'] }}" alt="{{ $relatedProduct['name'] }}" class="h-32 w-full object-cover">
                            </div>
                            <h4 class="mt-2 line-clamp-2 text-sm font-bold text-slate-900">{{ $relatedProduct['name'] }}</h4>
                            <p class="mt-1 text-sm font-semibold text-emerald-700">{{ $relatedProduct['price_label'] }}</p>
                            <a href="{{ route('marketplace.product.show', ['productType' => $relatedProduct['product_type'], 'productId' => $relatedProduct['id']]) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                Beli
                            </a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
