<x-mitra-layout>
    <x-slot name="header">{{ __('Beli Stok Mitra') }}</x-slot>

    <div data-testid="mitra-procurement-page" class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @php
            $activeSection = request()->query('section', 'catalog');
            $hasPaymentColumns = (bool) ($hasProcurementPaymentColumns ?? false);
            $activeProcurementRows = collect($myProcurements ?? [])->filter(function ($order) {
                $status = strtolower(trim((string) ($order->status ?? '')));
                return in_array($status, ['pending', 'approved', 'processing', 'shipped'], true);
            });
            $paidInvoiceRows = collect($myProcurements ?? [])->filter(function ($order) use ($hasPaymentColumns) {
                $status = strtolower(trim((string) ($order->status ?? '')));
                $paymentStatus = strtolower(trim((string) ($order->payment_status ?? '')));
                if ($hasPaymentColumns) {
                    return $status === 'delivered'
                        && $paymentStatus === 'paid';
                }

                return $status === 'delivered';
            });
        @endphp

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

        <section class="rounded-xl border bg-white p-4">
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('mitra.procurement.index', ['section' => 'catalog']) }}#katalog-gudang"
                    class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $activeSection === 'catalog' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}"
                >
                    Katalog Gudang Admin
                </a>
                <a
                    href="{{ route('mitra.procurement.index', ['section' => 'history']) }}#riwayat-pembelian"
                    class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $activeSection === 'history' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}"
                >
                    Riwayat Pembelian
                </a>
            </div>
        </section>

        @if($activeSection !== 'history')
            <section id="katalog-gudang" data-testid="mitra-procurement-catalog" class="surface-card p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Katalog Pengadaan Admin</h3>
                    <p class="mt-1 text-sm text-slate-600">Mitra melakukan pembelian dan pembayaran produk pengadaan yang diinput oleh Admin dari menu Pengadaan.</p>
                </div>
                <a href="{{ route('mitra.dashboard') }}" class="link-ghost">Kembali ke Dashboard Mitra</a>
            </div>

            @if($adminProducts->isEmpty())
                <p class="mt-4 text-sm text-slate-600">Belum ada produk pengadaan aktif dari admin.</p>
            @else
                <form method="POST" action="{{ route('mitra.procurement.createOrder') }}" class="mt-4 rounded-2xl border border-cyan-200 bg-cyan-50/60 p-4">
                    @csrf
                    <h4 class="text-sm font-semibold text-slate-900">Buat Pembelian Multi-Item</h4>
                    <p class="mt-1 text-xs text-slate-600">Pilih produk, tentukan qty pembelian, lalu lanjutkan ke proses verifikasi pembayaran admin.</p>

                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-cyan-200 text-left text-slate-600">
                                    <th class="py-2 pr-4">Pilih</th>
                                    <th class="py-2 pr-4">Produk</th>
                                    <th class="py-2 pr-4">Gudang</th>
                                    <th class="py-2 pr-4">Harga</th>
                                    <th class="py-2 pr-4">Stok</th>
                                    <th class="py-2 pr-4">Min</th>
                                    <th class="py-2">Qty Beli</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($adminProducts as $idx => $product)
                                    @php
                                        $unitLabel = trim((string) ($product->unit ?? '')) !== '' ? strtolower((string) $product->unit) : 'kg';
                                    @endphp
                                    <tr class="border-b border-cyan-100 last:border-0">
                                        <td class="py-3 pr-4">
                                            <input type="hidden" name="items[{{ $idx }}][selected]" value="0">
                                            <input type="checkbox" name="items[{{ $idx }}][selected]" value="1" class="rounded border-slate-300">
                                            <input type="hidden" name="items[{{ $idx }}][admin_product_id]" value="{{ $product->id }}">
                                        </td>
                                        <td class="py-3 pr-4">
                                            <p class="font-medium text-slate-900">{{ $product->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $product->description ?: 'Tanpa deskripsi' }}</p>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">
                                            @if(!empty($product->warehouse_name))
                                                <span class="font-semibold">{{ $product->warehouse_code }}</span> - {{ $product->warehouse_name }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">Rp{{ number_format((float) $product->price, 0, ',', '.') }} / {{ $unitLabel }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $product->stock_qty) }} {{ $unitLabel }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $product->min_order_qty) }} {{ $unitLabel }}</td>
                                        <td class="py-3">
                                            <input
                                                type="number"
                                                name="items[{{ $idx }}][qty]"
                                                min="1"
                                                value="{{ max(1, (int) $product->min_order_qty) }}"
                                                class="w-24 rounded-lg border-slate-300 text-sm"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-end">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Catatan (opsional)</label>
                            <input type="text" name="notes" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Catatan pengadaan">
                        </div>
                        <button type="submit" class="btn-ink">Beli Produk Terpilih</button>
                    </div>
                </form>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($adminProducts as $product)
                        @php
                            $unitLabel = trim((string) ($product->unit ?? '')) !== '' ? strtolower((string) $product->unit) : 'kg';
                        @endphp
                        <article class="surface-card-soft p-4">
                            <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $product->description ?: 'Tanpa deskripsi' }}</p>
                            <div class="mt-3 space-y-1 text-sm text-slate-700">
                                <p>Harga: <span class="font-semibold">Rp{{ number_format((float) $product->price, 0, ',', '.') }} / {{ $unitLabel }}</span></p>
                                <p>Stok admin: <span class="font-semibold">{{ number_format((int) $product->stock_qty) }} {{ $unitLabel }}</span></p>
                                <p>Min order: <span class="font-semibold">{{ number_format((int) $product->min_order_qty) }} {{ $unitLabel }}</span></p>
                                <p>Gudang: <span class="font-semibold">{{ !empty($product->warehouse_name) ? (($product->warehouse_code ?? '-') . ' - ' . $product->warehouse_name) : '-' }}</span></p>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
            </section>
        @endif

        <section id="order-aktif" data-testid="mitra-procurement-active-orders" class="surface-card p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Order Pengadaan Aktif</h3>
                    <p class="text-sm text-slate-600">
                        Daftar order yang masih berjalan dan perlu aksi lanjutan.
                    </p>
                </div>
                <span class="rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold text-cyan-800">
                    {{ number_format((int) $activeProcurementRows->count()) }} order aktif
                </span>
            </div>

            @if($activeProcurementRows->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Tidak ada order aktif saat ini.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4">Order</th>
                                <th class="py-2 pr-4">Status</th>
                                @if($hasPaymentColumns)
                                    <th class="py-2 pr-4">Pembayaran</th>
                                @endif
                                <th class="py-2 pr-4">Line Item</th>
                                <th class="py-2 pr-4">Total Qty</th>
                                <th class="py-2 pr-4">Total</th>
                                <th class="py-2 pr-4">Tanggal</th>
                                <th class="py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeProcurementRows as $order)
                                @php
                                    $statusValue = strtolower(trim((string) ($order->status ?? '')));
                                    $paymentStatusValue = strtolower(trim((string) ($order->payment_status ?? '')));
                                    $canConfirmReceived = $statusValue === 'shipped' && (! $hasPaymentColumns || $paymentStatusValue === 'paid');
                                    $paymentStatusLabel = match ($paymentStatusValue) {
                                        'unpaid' => 'Belum Bayar',
                                        'pending_verification' => 'Menunggu Verifikasi',
                                        'paid' => 'Lunas',
                                        'rejected' => 'Ditolak',
                                        default => '-',
                                    };
                                @endphp
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">#{{ $order->id }}</td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $order->status }}</td>
                                    @if($hasPaymentColumns)
                                        <td class="py-3 pr-4 text-slate-700">{{ $paymentStatusLabel }}</td>
                                    @endif
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) ($order->line_count ?? 0)) }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) ($order->total_qty ?? 0)) }}</td>
                                    <td class="py-3 pr-4 text-slate-700">Rp{{ number_format((float) $order->total_amount, 0, ',', '.') }}</td>
                                    <td class="py-3 pr-4 text-slate-600">{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y H:i') }}</td>
                                    <td class="py-3 text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <a href="{{ route('mitra.procurement.show', ['orderId' => $order->id]) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                Detail
                                            </a>
                                            @if($canConfirmReceived)
                                                <form method="POST" action="{{ route('mitra.procurement.confirmReceived', ['orderId' => $order->id]) }}" onsubmit="return confirm('Konfirmasi bahwa barang sudah diterima?');">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                        Konfirmasi Diterima
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section id="riwayat-pembelian" data-testid="mitra-procurement-history" class="surface-card p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Riwayat Order Pengadaan Saya</h3>
                    <p class="text-sm text-slate-600">
                        Daftar order pengadaan yang sudah selesai (barang diterima).
                    </p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {{ number_format((int) $paidInvoiceRows->count()) }} order selesai
                </span>
            </div>
            @if($paidInvoiceRows->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Belum ada order pengadaan selesai.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4">Order</th>
                                <th class="py-2 pr-4">Status</th>
                                @if($hasPaymentColumns)
                                    <th class="py-2 pr-4">Pembayaran</th>
                                @endif
                                <th class="py-2 pr-4">Line Item</th>
                                <th class="py-2 pr-4">Total Qty</th>
                                <th class="py-2 pr-4">Total</th>
                                <th class="py-2 pr-4">Catatan</th>
                                <th class="py-2">Tanggal</th>
                                <th class="py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paidInvoiceRows as $order)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">#{{ $order->id }}</td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $order->status }}</td>
                                    @if($hasPaymentColumns)
                                        @php
                                            $paymentStatusLabel = match (strtolower(trim((string) ($order->payment_status ?? '')))) {
                                                'unpaid' => 'Belum Bayar',
                                                'pending_verification' => 'Menunggu Verifikasi',
                                                'paid' => 'Lunas',
                                                'rejected' => 'Ditolak',
                                                default => '-',
                                            };
                                        @endphp
                                        <td class="py-3 pr-4 text-slate-700">{{ $paymentStatusLabel }}</td>
                                    @endif
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) ($order->line_count ?? 0)) }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) ($order->total_qty ?? 0)) }}</td>
                                    <td class="py-3 pr-4 text-slate-700">Rp{{ number_format((float) $order->total_amount, 0, ',', '.') }}</td>
                                    <td class="py-3 pr-4 text-slate-600">{{ $order->notes ?: '-' }}</td>
                                    <td class="py-3 text-slate-600">{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y H:i') }}</td>
                                    <td class="py-3 text-right">
                                        <a href="{{ route('mitra.procurement.show', ['orderId' => $order->id]) }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">
                                            Lihat Detail
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-mitra-layout>
