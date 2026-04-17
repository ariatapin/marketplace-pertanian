<x-mitra-layout>
    <x-slot name="header">{{ __('Detail Order Pengadaan Mitra') }}</x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('procurement_received_notice'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => { show = false }, 3000)"
                    x-show="show"
                    x-transition.opacity.duration.300ms
                    class="fixed right-4 top-4 z-50 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-lg"
                >
                    {{ session('procurement_received_notice') }}
                </div>
            @endif

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

            @php
                $hasProcurementPaymentColumns = (bool) ($hasPaymentColumns ?? false);
                $orderStatusValue = strtolower(trim((string) ($order->status ?? '')));
                $paymentStatusValue = strtolower(trim((string) ($order->payment_status ?? '')));
            @endphp

            <div class="rounded-xl border bg-white p-6">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Order #{{ $order->id }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Dibuat {{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y H:i') }}
                        </p>
                    </div>
                    <a href="{{ route('mitra.procurement.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">
                        Kembali ke Pengadaan
                    </a>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="rounded-lg border p-3">
                        <p class="text-xs text-gray-500">Status</p>
                        <p class="mt-1 font-semibold uppercase text-gray-900">{{ $order->status }}</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-xs text-gray-500">Total Nilai</p>
                        <p class="mt-1 font-semibold text-gray-900">Rp{{ number_format((float) $order->total_amount, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-lg border p-3 md:col-span-2">
                        <p class="text-xs text-gray-500">Catatan</p>
                        <p class="mt-1 text-sm text-gray-700">{{ $order->notes ?: '-' }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-900">Settlement Stok Toko</p>
                        @if($stockSettlement)
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Settled</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Belum Settled</span>
                        @endif
                    </div>
                    @if($stockSettlement)
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="rounded-lg border bg-white p-3">
                                <p class="text-xs text-slate-500">Line Item Masuk</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format((int) $stockSettlement->line_count) }}</p>
                            </div>
                            <div class="rounded-lg border bg-white p-3">
                                <p class="text-xs text-slate-500">Total Qty Masuk</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format((int) $stockSettlement->total_qty) }}</p>
                            </div>
                            <div class="rounded-lg border bg-white p-3">
                                <p class="text-xs text-slate-500">Waktu Settlement</p>
                                <p class="mt-1 text-sm text-slate-700">{{ \Illuminate\Support\Carbon::parse($stockSettlement->settled_at)->format('d M Y H:i') }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    oleh {{ $stockSettlement->settled_by_name ?: 'System' }} ({{ strtoupper($stockSettlement->settled_by_role ?: '-') }})
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="mt-2 text-xs text-slate-600">
                            Stok produk toko akan bertambah otomatis setelah Mitra menekan tombol <strong>Konfirmasi Diterima</strong> pada status SHIPPED.
                        </p>
                    @endif
                </div>

                @if($hasProcurementPaymentColumns)
                    @php
                        $paymentStatus = $paymentStatusValue;
                        $paymentStatusMeta = match ($paymentStatus) {
                            'unpaid' => ['Belum Bayar', 'bg-slate-100 text-slate-700'],
                            'pending_verification' => ['Menunggu Verifikasi', 'bg-amber-100 text-amber-800'],
                            'paid' => ['Lunas', 'bg-emerald-100 text-emerald-800'],
                            'rejected' => ['Ditolak', 'bg-rose-100 text-rose-800'],
                            default => ['-', 'bg-slate-100 text-slate-700'],
                        };
                        $paymentMethodKey = strtolower(trim((string) ($order->payment_method ?? '')));
                        $paymentMethodLabel = match ($paymentMethodKey) {
                            'bank_transfer' => 'Bank Transfer',
                            'wallet' => 'Saldo Wallet',
                            default => ($paymentMethodKey !== '' ? strtoupper(str_replace('_', ' ', $paymentMethodKey)) : '-'),
                        };
                        $walletSummaryData = $walletSummary ?? [
                            'balance' => 0,
                            'reserved_withdraw_amount' => 0,
                            'available_balance' => 0,
                        ];
                    @endphp

                    <div class="mt-4 rounded-lg border border-cyan-200 bg-cyan-50/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">Pembayaran Pengadaan</p>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $paymentStatusMeta[1] }}">{{ $paymentStatusMeta[0] }}</span>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4">
                            <div class="rounded-lg border bg-white p-3">
                                <p class="text-xs text-slate-500">Metode</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $paymentMethodLabel }}</p>
                            </div>
                            <div class="rounded-lg border bg-white p-3">
                                <p class="text-xs text-slate-500">Nominal</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ isset($order->paid_amount) && $order->paid_amount !== null ? 'Rp' . number_format((float) $order->paid_amount, 0, ',', '.') : '-' }}
                                </p>
                            </div>
                            <div class="rounded-lg border bg-white p-3">
                                <p class="text-xs text-slate-500">Diajukan</p>
                                <p class="mt-1 text-sm text-slate-700">
                                    {{ !empty($order->payment_submitted_at) ? \Illuminate\Support\Carbon::parse($order->payment_submitted_at)->format('d M Y H:i') : '-' }}
                                </p>
                            </div>
                            <div class="rounded-lg border bg-white p-3">
                                <p class="text-xs text-slate-500">Verifikasi</p>
                                <p class="mt-1 text-sm text-slate-700">
                                    {{ !empty($order->payment_verified_at) ? \Illuminate\Support\Carbon::parse($order->payment_verified_at)->format('d M Y H:i') : '-' }}
                                </p>
                            </div>
                        </div>

                        @if($paymentMethodKey !== '' && ! in_array($paymentMethodKey, ['bank_transfer', 'wallet'], true))
                            <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                                Metode pembayaran ini berasal dari data lama dan tidak sesuai aturan terbaru (transfer bank atau saldo wallet).
                            </div>
                        @endif

                        @if(!empty($order->payment_proof_url))
                            <a
                                href="{{ asset('storage/' . $order->payment_proof_url) }}"
                                target="_blank"
                                rel="noopener"
                                class="mt-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Lihat Bukti Pembayaran
                            </a>
                        @endif

                        <p class="mt-3 text-sm text-slate-700">
                            Catatan pembayaran: {{ !empty($order->payment_note) ? $order->payment_note : '-' }}
                        </p>

                        @if(in_array($paymentStatus, ['unpaid', 'rejected'], true) && in_array($orderStatusValue, ['pending', 'approved', 'processing'], true))
                            <form id="pembayaran-pengadaan" method="POST" action="{{ route('mitra.procurement.submitPayment', ['orderId' => $order->id]) }}" enctype="multipart/form-data" class="mt-4 rounded-lg border border-cyan-200 bg-white p-4" x-data="{ paymentMethod: '{{ old('payment_method', 'bank_transfer') }}' }">
                                @csrf
                                <p class="text-sm font-semibold text-slate-900">Ajukan Pembayaran ke Admin</p>
                                <p class="mt-1 text-xs text-slate-600">Mitra dapat membayar lewat transfer bank (menunggu verifikasi admin) atau saldo wallet (otomatis terkonfirmasi jika saldo cukup).</p>
                                <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                    Saldo wallet tersedia:
                                    <span class="font-semibold text-slate-900">Rp{{ number_format((float) ($walletSummaryData['available_balance'] ?? 0), 0, ',', '.') }}</span>
                                    (saldo total Rp{{ number_format((float) ($walletSummaryData['balance'] ?? 0), 0, ',', '.') }},
                                    reserve withdraw Rp{{ number_format((float) ($walletSummaryData['reserved_withdraw_amount'] ?? 0), 0, ',', '.') }}).
                                </div>
                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Metode Pembayaran</label>
                                        <select name="payment_method" x-model="paymentMethod" class="w-full rounded-md border-slate-300 text-sm" required>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="wallet">Saldo Wallet</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Nominal Dibayar</label>
                                        <input
                                            type="number"
                                            name="paid_amount"
                                            min="1"
                                            step="0.01"
                                            value="{{ old('paid_amount', (float) ($order->total_amount ?? 0)) }}"
                                            class="w-full rounded-md border-slate-300 text-sm"
                                            required
                                            x-bind:readonly="paymentMethod === 'wallet'"
                                            x-bind:class="paymentMethod === 'wallet' ? 'bg-slate-100' : ''"
                                        >
                                        <p x-show="paymentMethod === 'wallet'" class="mt-1 text-xs text-slate-500">
                                            Pembayaran saldo harus sama dengan total tagihan order.
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Catatan Pembayaran (opsional)</label>
                                        <input
                                            type="text"
                                            name="payment_note"
                                            maxlength="255"
                                            value="{{ old('payment_note') }}"
                                            class="w-full rounded-md border-slate-300 text-sm"
                                            placeholder="Contoh: transfer via BCA a.n. toko mitra"
                                        >
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Bukti Pembayaran</label>
                                        <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" class="w-full rounded-md border-slate-300 text-sm" x-bind:disabled="paymentMethod === 'wallet'">
                                        <p x-show="paymentMethod === 'wallet'" class="mt-1 text-xs text-slate-500">Tidak perlu upload bukti untuk pembayaran saldo.</p>
                                    </div>
                                </div>
                                <button type="submit" class="mt-3 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                    Proses Pembayaran
                                </button>
                            </form>
                        @elseif($paymentStatus === 'pending_verification')
                            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Menunggu Verifikasi Admin</p>
                                <p class="mt-1 text-sm text-amber-800">Pembayaran sudah dikirim. Tunggu konfirmasi admin sebelum order dikirim.</p>
                            </div>
                        @elseif($paymentStatus === 'paid')
                            <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">Pembayaran Terkonfirmasi</p>
                                <p class="mt-1 text-sm text-emerald-800">Invoice sudah lunas. Admin dapat melanjutkan proses pengiriman.</p>
                            </div>
                        @endif
                    </div>
                @endif

                @if($orderStatusValue === 'shipped' && (! $hasProcurementPaymentColumns || $paymentStatusValue === 'paid'))
                    <form method="POST" action="{{ route('mitra.procurement.confirmReceived', ['orderId' => $order->id]) }}" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        @csrf
                        <p class="text-sm font-semibold text-emerald-900">Konfirmasi Barang Diterima</p>
                        <p class="mt-1 text-xs text-emerald-800">Klik konfirmasi setelah barang dari admin sudah diterima lengkap. Status order akan selesai.</p>
                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto] md:items-end">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-emerald-800">Catatan (opsional)</label>
                                <input type="text" name="note" maxlength="255" class="w-full rounded-md border-emerald-200 text-sm" placeholder="Contoh: barang diterima sesuai invoice">
                            </div>
                            <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                Konfirmasi Diterima
                            </button>
                        </div>
                    </form>
                @elseif($orderStatusValue === 'delivered')
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">Order Selesai</p>
                        <p class="mt-1 text-sm text-emerald-800">Barang sudah dikonfirmasi diterima. Riwayat pembelian tersimpan di sisi Mitra dan Admin.</p>
                    </div>
                @endif

                @if(in_array($orderStatusValue, ['pending', 'approved'], true))
                    <form method="POST" action="{{ route('mitra.procurement.cancel', ['orderId' => $order->id]) }}" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4">
                        @csrf
                        <p class="text-sm font-semibold text-rose-800">Batalkan Order</p>
                        <p class="mt-1 text-xs text-rose-700">Hanya boleh dibatalkan saat status pending atau approved. Stok akan dikembalikan ke admin.</p>
                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto] md:items-end">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-rose-700">Alasan (opsional)</label>
                                <input type="text" name="note" class="w-full rounded-md border-rose-200 text-sm" placeholder="Contoh: kebutuhan ditunda">
                            </div>
                            <button type="submit" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">
                                Batalkan PO
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="rounded-xl border bg-white p-6">
                <h3 class="text-base font-semibold text-gray-900">Item Pengadaan</h3>
                @if($items->isEmpty())
                    <p class="mt-3 text-sm text-gray-600">Belum ada item pada order ini.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-600">
                                    <th class="py-2 pr-4">Produk</th>
                                    <th class="py-2 pr-4">Qty</th>
                                    <th class="py-2 pr-4">Harga</th>
                                    <th class="py-2">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    @php
                                        $unitLabel = trim((string) ($item->unit ?? '')) !== '' ? strtolower((string) $item->unit) : 'kg';
                                    @endphp
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4 text-gray-900">{{ $item->product_name }}</td>
                                        <td class="py-3 pr-4 text-gray-700">{{ number_format((int) $item->qty) }} {{ $unitLabel }}</td>
                                        <td class="py-3 pr-4 text-gray-700">Rp{{ number_format((float) $item->price_per_unit, 0, ',', '.') }} / {{ $unitLabel }}</td>
                                        <td class="py-3 text-gray-700">Rp{{ number_format((float) $item->price_per_unit * (int) $item->qty, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border bg-white p-6">
                <h3 class="text-base font-semibold text-gray-900">Timeline Status</h3>
                @if($statusHistory->isEmpty())
                    <p class="mt-3 text-sm text-gray-600">Belum ada histori perubahan status.</p>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach($statusHistory as $history)
                            <div class="rounded-lg border p-3">
                                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ strtoupper($history->from_status ?: 'NEW') }} -> {{ strtoupper($history->to_status) }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ \Illuminate\Support\Carbon::parse($history->created_at)->format('d M Y H:i') }}
                                    </p>
                                </div>
                                <p class="mt-1 text-sm text-gray-600">
                                    Oleh: {{ $history->actor_name ?: 'Sistem' }} ({{ strtoupper($history->actor_role ?: 'SYSTEM') }})
                                </p>
                                <p class="mt-1 text-sm text-gray-600">{{ $history->note ?: '-' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-mitra-layout>
