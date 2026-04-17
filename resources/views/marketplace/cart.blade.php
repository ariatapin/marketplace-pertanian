@extends('layouts.marketplace')

@section('title', 'Keranjang Saya')
@section('pageTitle', 'Keranjang Saya')

@section('content')
    <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
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

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Baris Item</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format((int) $summary['line_count']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Total Qty</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format((int) $summary['qty_total']) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">Siap Checkout</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format((int) $summary['checkout_line_count']) }}</p>
                </div>
                <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-cyan-700">Estimasi Total</p>
                    <p class="mt-1 text-2xl font-bold text-cyan-800">Rp{{ number_format((float) $summary['estimated_total'], 0, ',', '.') }}</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                {{ $checkoutPaymentHelper ?? ($checkoutModeMeta['helper'] ?? 'Pilih metode pembayaran aktif untuk melanjutkan checkout.') }}
            </p>
            @if(!($canCheckoutByMode ?? false))
                <p class="mt-2 text-xs font-semibold text-amber-700">
                    {{ $checkoutRestrictionMessage ?? 'Checkout belum tersedia untuk akun ini.' }}
                </p>
            @endif
        </section>

        @if($items->isEmpty())
            <section class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-600">
                Keranjang masih kosong. Tambahkan produk dari marketplace untuk mulai checkout.
                <div class="mt-3">
                    <a href="{{ route('landing') }}" class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Kembali ke Marketplace
                    </a>
                </div>
            </section>
        @else
            @php
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

                $defaultTransferMethodCode = trim((string) ($defaultPaymentMethod ?? ''));
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
                } else {
                    $initialPaymentMethod = $defaultTransferMethodCode !== ''
                        ? $defaultTransferMethodCode
                        : $saldoMethodCode;
                }
            @endphp
            <div
                x-data="{
                    selectedCount: 0,
                    paymentGroup: {{ \Illuminate\Support\Js::from($defaultPaymentGroup) }},
                    paymentMethod: {{ \Illuminate\Support\Js::from($initialPaymentMethod) }},
                    saldoMethod: {{ \Illuminate\Support\Js::from($saldoMethodCode) }},
                    transferMethod: {{ \Illuminate\Support\Js::from($defaultTransferMethodCode) }},
                    canUseSaldo: {{ \Illuminate\Support\Js::from($canUseSaldoOption) }},
                    toggleAll(checked) {
                        document.querySelectorAll('input[data-cart-select=\'1\']').forEach((input) => {
                            if (!input.disabled) input.checked = checked;
                        });
                        this.syncSelection();
                    },
                    syncSelection() {
                        this.selectedCount = document.querySelectorAll('input[data-cart-select=\'1\']:checked').length;
                    },
                    syncPaymentMethod() {
                        if (this.paymentGroup === 'saldo' && this.saldoMethod && this.canUseSaldo) {
                            this.paymentMethod = this.saldoMethod;
                            return;
                        }

                        if (this.paymentGroup === 'transfer' && this.transferMethod) {
                            this.paymentMethod = this.transferMethod;
                            return;
                        }

                        if (this.saldoMethod && this.canUseSaldo) {
                            this.paymentGroup = 'saldo';
                            this.paymentMethod = this.saldoMethod;
                            return;
                        }

                        this.paymentMethod = this.transferMethod || this.saldoMethod || '';
                    }
                }"
                x-init="syncSelection(); syncPaymentMethod();"
                class="space-y-4"
            >
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-left text-slate-500">
                                    <th class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            @change="toggleAll($event.target.checked)"
                                            @checked($checkoutItems->isNotEmpty())
                                            @disabled(!($canCheckoutByMode ?? false))
                                        >
                                    </th>
                                    <th class="px-4 py-3">Produk</th>
                                    <th class="px-4 py-3">Penjual</th>
                                    <th class="px-4 py-3">Qty</th>
                                    <th class="px-4 py-3">Harga</th>
                                    <th class="px-4 py-3">Subtotal</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr class="border-b border-slate-100 align-top last:border-0">
                                        <td class="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                name="cart_item_ids[]"
                                                value="{{ (int) $item['id'] }}"
                                                form="checkout-form"
                                                data-cart-select="1"
                                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                @change="syncSelection()"
                                                @checked((bool) $item['can_checkout'])
                                                @disabled(! (bool) $item['can_checkout'])
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-start gap-3">
                                                @if((bool) ($item['can_open_product'] ?? false))
                                                    <a href="{{ $item['product_detail_url'] }}" class="h-14 w-14 flex-none overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                                        <img src="{{ $item['product_image_src'] }}" alt="{{ $item['product_name'] }}" class="h-full w-full object-cover">
                                                    </a>
                                                @else
                                                    <div class="h-14 w-14 flex-none overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                                        <img src="{{ $item['product_image_src'] }}" alt="{{ $item['product_name'] }}" class="h-full w-full object-cover">
                                                    </div>
                                                @endif

                                                <div class="min-w-0">
                                                    @if((bool) ($item['can_open_product'] ?? false))
                                                        <a href="{{ $item['product_detail_url'] }}" class="font-semibold text-slate-900 hover:text-emerald-700">
                                                            {{ $item['product_name'] }}
                                                        </a>
                                                    @else
                                                        <p class="font-semibold text-slate-900">{{ $item['product_name'] }}</p>
                                                    @endif

                                                    @if(!empty($item['warning']))
                                                        <p class="mt-1 text-xs text-amber-700">{{ $item['warning'] }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-700">{{ $item['seller_label'] }}: {{ $item['seller_name'] }}</td>
                                        <td class="px-4 py-3 text-slate-700">
                                            @if((bool) ($item['can_update_qty'] ?? false))
                                                <form method="POST" action="{{ route('cart.update', ['cartItemId' => (int) $item['id']]) }}" class="inline-flex items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input
                                                        type="number"
                                                        name="qty"
                                                        min="1"
                                                        max="{{ max(1, (int) ($item['stock_qty'] ?? 1)) }}"
                                                        value="{{ (int) $item['qty'] }}"
                                                        class="w-16 rounded-md border-slate-300 px-2 py-1 text-center text-xs"
                                                        required
                                                    >
                                                    <button type="submit" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">
                                                        Update
                                                    </button>
                                                </form>
                                            @else
                                                {{ number_format((int) $item['qty']) }}
                                            @endif
                                            @if((int) $item['effective_qty'] !== (int) $item['qty'])
                                                <p class="mt-1 text-xs text-amber-700">Disesuaikan ke {{ number_format((int) $item['effective_qty']) }} (stok tersedia)</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-slate-700">{{ $item['price_label'] }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $item['line_total_label'] }}</td>
                                        <td class="px-4 py-3">
                                            @if($item['can_checkout'])
                                                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                    Siap
                                                </span>
                                            @else
                                                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                                    Perlu Tindakan
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <form method="POST" action="{{ route('cart.destroy', ['cartItemId' => (int) $item['id']]) }}" onsubmit="return confirm('Hapus item ini dari keranjang?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex rounded-md border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                    Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    @if(!($canCheckoutByMode ?? false))
                        <p class="text-sm text-amber-700">
                            {{ $checkoutRestrictionMessage ?? 'Checkout belum tersedia untuk akun ini.' }}
                        </p>
                    @elseif($checkoutItems->isEmpty())
                        <p class="text-sm text-amber-700">
                            Belum ada item yang valid untuk checkout. Periksa status item di tabel keranjang.
                        </p>
                    @else
                        <form id="checkout-form" method="POST" action="{{ route('checkout') }}">
                            @csrf
                            <input type="hidden" name="selection_required" value="1">
                            <input type="hidden" name="payment_method" :value="paymentMethod">

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                <label class="text-sm font-semibold text-slate-700">
                                    Metode Pembayaran
                                    <select
                                        x-model="paymentGroup"
                                        @change="syncPaymentMethod()"
                                        class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
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

                                    @if($hasTransferOptions)
                                        <select
                                            x-cloak
                                            x-show="paymentGroup === 'transfer'"
                                            x-model="transferMethod"
                                            @change="syncPaymentMethod()"
                                            class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        >
                                            @foreach($transferMethodOptions as $transferOption)
                                                <option value="{{ (string) ($transferOption['method'] ?? '') }}">
                                                    {{ (string) ($transferOption['label'] ?? strtoupper((string) ($transferOption['method'] ?? ''))) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif

                                    <p class="mt-1 text-xs font-medium text-slate-500">
                                        Pilih satu atau semua item, lalu checkout. Metode saldo akan langsung terpotong otomatis.
                                    </p>
                                    @if($saldoMethodCode !== '' && ! $canUseSaldoOption && $hasTransferOptions)
                                        <p class="mt-1 text-xs font-semibold text-amber-700">
                                            Saldo belum tersedia. Gunakan opsi Transfer.
                                        </p>
                                    @endif
                                    <p class="mt-1 text-xs font-semibold text-emerald-700">
                                        Item terpilih: <span x-text="selectedCount"></span>
                                    </p>
                                </label>
                                <button
                                    type="submit"
                                    :disabled="selectedCount === 0 || !paymentMethod"
                                    class="inline-flex justify-center rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Checkout Item Terpilih
                                </button>
                            </div>
                        </form>
                    @endif
                </section>
            </div>
        @endif
    </div>
@endsection
