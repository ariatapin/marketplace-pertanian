<x-mitra-layout>
    {{-- CATATAN-AUDIT: Dashboard mitra dipadatkan; kartu cuaca + notifikasi disatukan lewat komponen WeatherWidget. --}}
    <x-slot name="header">{{ __('Dashboard Mitra Toko') }}</x-slot>

    @php
        $customerOrders = (int) ($metrics['customer_orders'] ?? 0);
        $procurementOrders = (int) ($metrics['procurement_orders'] ?? 0);
        $lowStockCount = (int) ($metrics['low_stock_products'] ?? 0);
        $outOfStockCount = (int) ($metrics['out_of_stock_products'] ?? 0);
        $myProducts = (int) ($metrics['my_products'] ?? 0);
        $activeProducts = (int) ($metrics['active_products'] ?? 0);
        $totalStock = (int) ($metrics['total_stock'] ?? 0);
        $inventoryValue = (float) ($metrics['inventory_value'] ?? 0);

        $priorityTotal = $customerOrders + $lowStockCount + $outOfStockCount;
        $priorityLabel = $priorityTotal > 0 ? 'Butuh tindak lanjut' : 'Semua aman';
        $priorityClass = $priorityTotal > 0 ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900';
        $operationalPulse = $customerOrders > 0
            ? 'Order customer perlu diproses segera.'
            : 'Belum ada order customer baru saat ini.';
        $weatherUnreadCount = (int) ($weatherNotificationUnreadCount ?? 0);
    @endphp

    <div data-testid="mitra-dashboard-page" class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        <section data-testid="mitra-dashboard-hero" class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
            <article class="mitra-hero-card rounded-3xl border border-slate-800/30 p-5 text-white shadow-xl md:p-6">
                <div class="relative z-10">
                    <p class="text-xs uppercase tracking-[0.2em] text-cyan-100">Mitra Command Center</p>
                    <h2 class="mt-1.5 text-2xl font-bold">Ringkasan Operasional</h2>
                    <p class="mt-2 text-sm text-slate-100">
                        Pantau order, stok, dan pengadaan dalam satu tampilan ringkas.
                    </p>
                    <p class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-cyan-100">
                        <i class="fa-solid fa-wave-square text-[10px]" aria-hidden="true"></i>
                        <span>{{ $operationalPulse }}</span>
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $priorityClass }}">
                            {{ $priorityLabel }}
                        </span>
                        <a href="{{ route('mitra.orders.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-300 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-emerald-200">
                            <i class="fa-solid fa-receipt text-xs" aria-hidden="true"></i>
                            <span>Proses Order</span>
                        </a>
                        <a href="{{ route('mitra.procurement.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-900 hover:bg-cyan-100">
                            <i class="fa-solid fa-cart-flatbed text-xs" aria-hidden="true"></i>
                            <span>Buka Pengadaan</span>
                        </a>
                    </div>
                    <p class="mt-2 text-xs font-semibold text-cyan-100">
                        Pembayaran saldo otomatis masuk packing. Transfer bank cukup sekali verifikasi, lalu langsung packing.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                        <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-cyan-100">Order Customer</p>
                            <p class="mt-1 text-lg font-bold text-white">{{ number_format($customerOrders) }}</p>
                        </div>
                        <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-cyan-100">Order Pengadaan</p>
                            <p class="mt-1 text-lg font-bold text-white">{{ number_format($procurementOrders) }}</p>
                        </div>
                        <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-cyan-100">Stok Menipis + Habis</p>
                            <p class="mt-1 text-lg font-bold text-white">{{ number_format($lowStockCount + $outOfStockCount) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-emerald-200/45 bg-emerald-500/15 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-emerald-100">Total Uang Mitra (Demo Aktif)</p>
                        <p class="mt-1 text-xl font-bold text-emerald-100">
                            Rp{{ number_format((float) ($demoMitraBalance ?? 0), 0, ',', '.') }}
                        </p>
                        <p class="mt-2 text-xs text-emerald-100/90">Topup demo tersedia di halaman Profil.</p>
                    </div>
                </div>
            </article>

            <article id="cuaca-lokasi-mitra" class="surface-card p-5">
                <div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">Info Lokasi</p>
                        <h3 class="mt-1 text-base font-semibold text-slate-900">Cuaca Lokasi Mitra</h3>
                    </div>
                </div>

                <div class="mt-3">
                    <x-weather-widget
                        compact
                        :notifications="$weatherNotifications ?? collect()"
                        :unread-count="$weatherUnreadCount"
                        :mark-read-redirect="route('mitra.dashboard') . '#cuaca-lokasi-mitra'"
                    />
                </div>
            </article>
        </section>

        <section class="surface-card overflow-hidden p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Pesanan Masuk</h3>
                    <p class="mt-1 text-sm text-slate-600">Fokus proses: transfer diverifikasi lalu auto packing, langkah akhir tinggal kirim.</p>
                </div>
                <a href="{{ route('mitra.orders.index') }}" class="link-ghost">Buka semua pesanan</a>
            </div>

            @if(($incomingOrders ?? collect())->isEmpty())
                <p class="mt-4 text-sm text-slate-600">Belum ada pesanan masuk yang perlu diproses.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4">Order</th>
                                <th class="py-2 pr-4">Buyer</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Pembayaran</th>
                                <th class="py-2 pr-4">Total</th>
                                <th class="py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($incomingOrders as $order)
                                @php
                                    $paymentMethod = strtolower(trim((string) ($order->payment_method ?? '')));
                                    $canVerifyTransfer = (string) ($order->order_status ?? '') === 'pending_payment'
                                        && (string) ($order->payment_status ?? '') === 'unpaid'
                                        && !empty($order->payment_proof_url)
                                        && $paymentMethod === 'bank_transfer';
                                    $canShip = (string) ($order->order_status ?? '') === 'packed';
                                @endphp
                                <tr class="border-b border-slate-100 align-top last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">
                                        <a href="{{ route('mitra.orders.show', ['orderId' => $order->id]) }}" class="text-cyan-700 hover:text-cyan-900">
                                            #{{ $order->id }}
                                        </a>
                                        <div class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($order->updated_at)->format('d M Y H:i') }}</div>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-700">
                                        <p class="font-semibold text-slate-900">{{ $order->buyer_name ?: '-' }}</p>
                                        <p class="text-xs text-slate-500">{{ $order->buyer_email ?: '-' }}</p>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold uppercase text-slate-700">
                                            {{ strtoupper((string) $order->order_status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-700">
                                        <p class="text-xs uppercase">{{ (string) ($order->payment_status ?? '-') }}</p>
                                        <p class="text-xs text-slate-500">{{ $paymentMethod === 'bank_transfer' ? 'TRANSFER BANK' : strtoupper(str_replace('_', ' ', $paymentMethod)) }}</p>
                                    </td>
                                    <td class="py-3 pr-4 font-semibold text-slate-900">Rp{{ number_format((float) ($order->total_amount ?? 0), 0, ',', '.') }}</td>
                                    <td class="py-3">
                                        <div class="flex flex-col gap-2">
                                            @if($canVerifyTransfer)
                                                <form method="POST" action="{{ route('mitra.orders.markPaid', ['orderId' => $order->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                        Konfirmasi Pembayaran
                                                    </button>
                                                </form>
                                            @endif
                                            @if($canShip)
                                                <form method="POST" action="{{ route('mitra.orders.markShipped', ['orderId' => $order->id]) }}" class="space-y-1">
                                                    @csrf
                                                    <input type="text" name="resi_number" value="{{ $order->resi_number ?? '' }}" placeholder="No Resi (opsional)" class="w-full rounded-lg border-slate-300 px-2 py-1 text-xs">
                                                    <button type="submit" class="rounded-lg bg-cyan-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-800">
                                                        Kirim
                                                    </button>
                                                </form>
                                            @endif
                                            <a href="{{ route('mitra.orders.show', ['orderId' => $order->id]) }}" class="text-xs font-semibold text-cyan-700 hover:text-cyan-900">
                                                Detail
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section data-testid="mitra-dashboard-metrics" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Rating Akun Mitra</p>
                @if((int) (($ratingSummary['total_reviews'] ?? 0)) > 0)
                    <p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format((float) ($ratingSummary['average_score'] ?? 0), 1, ',', '.') }}/5</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format((int) ($ratingSummary['total_reviews'] ?? 0)) }} ulasan buyer</p>
                @else
                    <p class="mt-2 text-3xl font-bold text-slate-900">0/5</p>
                    <p class="mt-1 text-xs text-slate-500">Belum ada ulasan buyer.</p>
                @endif
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Produk Saya</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($myProducts) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Produk Aktif</p>
                <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($activeProducts) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Stok</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($totalStock) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Nilai Inventori</p>
                <p class="mt-2 text-2xl font-bold text-cyan-700">Rp{{ number_format($inventoryValue, 0, ',', '.') }}</p>
            </article>
        </section>

        <section data-testid="mitra-dashboard-products">
            <div class="surface-card overflow-hidden p-5">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900">Produk Terbaru</h3>
                    <a href="{{ route('mitra.products.index') }}" class="link-ghost">Lihat semua</a>
                </div>

                @if($recentProducts->isEmpty())
                    <p class="mt-4 text-sm text-slate-600">Belum ada produk. Mulai tambah produk untuk toko Anda.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-slate-600">
                                    <th class="py-2 pr-4">Produk</th>
                                    <th class="py-2 pr-4">Harga</th>
                                    <th class="py-2 pr-4">Stok</th>
                                    <th class="py-2">Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentProducts as $product)
                                    @php
                                        $unitLabel = strtolower(trim((string) ($product->unit ?? 'kg')));
                                        if ($unitLabel === '') {
                                            $unitLabel = 'kg';
                                        }
                                    @endphp
                                    <tr class="border-b border-slate-100 last:border-0">
                                        <td class="py-3 pr-4 font-medium text-slate-900">{{ $product->name }}</td>
                                        <td class="py-3 pr-4 text-slate-700">Rp{{ number_format((float) $product->price, 0, ',', '.') }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $product->stock_qty) }} {{ $unitLabel }}</td>
                                        <td class="py-3 text-slate-600">{{ \Illuminate\Support\Carbon::parse($product->updated_at)->format('d M Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-mitra-layout>
