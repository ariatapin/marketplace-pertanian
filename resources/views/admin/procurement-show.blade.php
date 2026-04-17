<x-admin-layout>
    <x-slot name="header">
        {{ __('Detail Order Pengadaan') }}
    </x-slot>

    <div data-testid="admin-procurement-order-detail-page" class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div data-testid="admin-procurement-order-audit-log" class="rounded-xl border bg-white p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Order #{{ $order->id }}</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Mitra: {{ $order->mitra_name ?: '-' }} ({{ $order->mitra_email ?: '-' }})
                    </p>
                </div>
                <a href="{{ route('admin.modules.procurement') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">
                    Kembali ke Modul Pengadaan
                </a>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-slate-500">Status Saat Ini</p>
                    <p class="mt-1 font-semibold uppercase text-slate-900">{{ $order->status }}</p>
                </div>
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-slate-500">Total Nilai</p>
                    <p class="mt-1 font-semibold text-slate-900">Rp{{ number_format((float) $order->total_amount, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-slate-500">Dibuat</p>
                    <p class="mt-1 text-sm text-slate-700">{{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y H:i') }}</p>
                </div>
                <div class="rounded-lg border p-3">
                    <p class="text-xs text-slate-500">Updated</p>
                    <p class="mt-1 text-sm text-slate-700">{{ \Illuminate\Support\Carbon::parse($order->updated_at)->format('d M Y H:i') }}</p>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50/40 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-900">Settlement Stok Pengadaan</p>
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
                        Settlement stok akan otomatis berjalan saat status order berpindah ke <strong>DELIVERED</strong>.
                    </p>
                @endif
            </div>

            <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-sm font-semibold text-indigo-900">Aksi Status Admin</p>
                @php
                    $allStatuses = ['pending', 'approved', 'processing', 'shipped', 'cancelled'];
                    $statusLabels = [
                        'pending' => 'Pending',
                        'approved' => 'Approve',
                        'processing' => 'Proses',
                        'shipped' => 'Kirim',
                        'cancelled' => 'Cancel',
                    ];
                    $nextTargets = collect($allowedStatusTargets)
                        ->reject(fn ($target) => $target === $order->status)
                        ->values()
                        ->all();
                    $hasActionableTransition = count($nextTargets) > 0;
                @endphp

                @if($hasActionableTransition)
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($nextTargets as $target)
                            <form method="POST" action="{{ route('admin.procurement.orders.status', ['adminOrderId' => $order->id]) }}" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="{{ $target }}">
                                <input type="hidden" name="note" value="Aksi cepat admin dari detail order pengadaan.">
                                <button
                                    type="submit"
                                    class="rounded px-3 py-1.5 text-xs font-semibold
                                    {{ match($target) {
                                        'approved' => 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
                                        'processing' => 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200',
                                        'shipped' => 'bg-sky-100 text-sky-800 hover:bg-sky-200',
                                        'delivered' => 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
                                        'cancelled' => 'bg-rose-100 text-rose-800 hover:bg-rose-200',
                                        default => 'bg-slate-100 text-slate-800 hover:bg-slate-200',
                                    } }}"
                                >
                                    {{ $statusLabels[$target] ?? strtoupper($target) }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.procurement.orders.status', ['adminOrderId' => $order->id]) }}" class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                    @csrf
                    <select name="status" class="rounded-md border-slate-300 text-sm">
                        @foreach($allStatuses as $statusOption)
                            <option
                                value="{{ $statusOption }}"
                                @selected($order->status === $statusOption)
                                @disabled(!in_array($statusOption, $allowedStatusTargets, true))
                            >
                                {{ strtoupper($statusOption) }}
                            </option>
                        @endforeach
                    </select>
                    <input
                        type="text"
                        name="note"
                        maxlength="255"
                        placeholder="Catatan admin (opsional)"
                        class="rounded-md border-slate-300 text-sm md:col-span-1"
                    >
                    <button
                        type="submit"
                        @disabled(!$hasActionableTransition)
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                    >
                        Update Status
                    </button>
                </form>

                @if(!$hasActionableTransition)
                    <p class="mt-2 text-xs text-slate-600">Order sudah berada pada status final. Tidak ada transisi lanjutan.</p>
                @endif

                <p class="mt-2 text-xs text-slate-600">
                    Transisi yang diizinkan:
                    {{ collect($allowedStatusTargets)->map(fn ($status) => strtoupper($status))->implode(', ') ?: '-' }}
                </p>
                @if((string) $order->status === 'shipped')
                    <p class="mt-2 text-xs text-amber-700">
                        Menunggu konfirmasi <strong>Mitra</strong> untuk status delivered (barang diterima).
                    </p>
                @endif
            </div>

            @if($hasPaymentColumns ?? false)
                @php
                    $paymentStatus = (string) ($order->payment_status ?? '');
                    $paymentStatusMeta = match ($paymentStatus) {
                        'unpaid' => ['Belum Bayar', 'bg-slate-100 text-slate-700'],
                        'pending_verification' => ['Menunggu Verifikasi', 'bg-amber-100 text-amber-800'],
                        'paid' => ['Lunas', 'bg-emerald-100 text-emerald-800'],
                        'rejected' => ['Ditolak', 'bg-rose-100 text-rose-800'],
                        default => ['-', 'bg-slate-100 text-slate-700'],
                    };
                    $paymentMethodLabel = match ((string) ($order->payment_method ?? '')) {
                        'bank_transfer' => 'Bank Transfer',
                        'gopay' => 'GoPay',
                        'ovo' => 'OVO',
                        'dana' => 'DANA',
                        'linkaja' => 'LinkAja',
                        'shopeepay' => 'ShopeePay',
                        'other_wallet' => 'E-Wallet Lainnya',
                        default => '-',
                    };
                @endphp
                <div class="mt-4 rounded-lg border border-cyan-200 bg-cyan-50/40 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-900">Status Pembayaran Pengadaan</p>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $paymentStatusMeta[1] }}">{{ $paymentStatusMeta[0] }}</span>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="rounded-lg border bg-white p-3">
                            <p class="text-xs text-slate-500">Metode</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $paymentMethodLabel }}</p>
                        </div>
                        <div class="rounded-lg border bg-white p-3">
                            <p class="text-xs text-slate-500">Nominal Dibayar</p>
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
                            <p class="text-xs text-slate-500">Diverifikasi</p>
                            <p class="mt-1 text-sm text-slate-700">
                                {{ !empty($order->payment_verified_at) ? \Illuminate\Support\Carbon::parse($order->payment_verified_at)->format('d M Y H:i') : '-' }}
                            </p>
                            @if(!empty($order->payment_verified_by_name))
                                <p class="mt-1 text-xs text-slate-500">oleh {{ $order->payment_verified_by_name }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto] md:items-center">
                        <p class="text-sm text-slate-700">
                            Catatan pembayaran: {{ !empty($order->payment_note) ? $order->payment_note : '-' }}
                        </p>
                        @if(!empty($order->payment_proof_url))
                            <a
                                href="{{ asset('storage/' . $order->payment_proof_url) }}"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Lihat Bukti Pembayaran
                            </a>
                        @endif
                    </div>

                    @if($paymentStatus === 'pending_verification')
                        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Aksi Verifikasi Admin</p>
                            <p class="mt-1 text-xs text-amber-800">Setujui jika bukti valid. Tolak jika nominal/metode tidak sesuai.</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.procurement.orders.paymentStatus', ['adminOrderId' => $order->id]) }}">
                                    @csrf
                                    <input type="hidden" name="payment_status" value="paid">
                                    <input type="hidden" name="payment_note" value="Pembayaran diverifikasi dari halaman detail order pengadaan.">
                                    <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                        Verifikasi Pembayaran
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.procurement.orders.paymentStatus', ['adminOrderId' => $order->id]) }}">
                                    @csrf
                                    <input type="hidden" name="payment_status" value="rejected">
                                    <input type="hidden" name="payment_note" value="Pembayaran ditolak dari halaman detail order pengadaan.">
                                    <button type="submit" class="rounded-md bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">
                                        Tolak Pembayaran
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-4 rounded-lg border p-3">
                <p class="text-xs text-slate-500">Catatan Order</p>
                <p class="mt-1 text-sm text-slate-700">{{ $order->notes ?: '-' }}</p>
            </div>
        </div>

        <div class="rounded-xl border bg-white p-6">
            <h3 class="text-base font-semibold text-slate-900">Item Pengadaan</h3>
            @if($items->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Belum ada item pada order ini.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-slate-600">
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
                                        <td class="py-3 pr-4 text-slate-900">{{ $item->product_name }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $item->qty) }} {{ $unitLabel }}</td>
                                        <td class="py-3 pr-4 text-slate-700">Rp{{ number_format((float) $item->price_per_unit, 0, ',', '.') }} / {{ $unitLabel }}</td>
                                        <td class="py-3 text-slate-700">Rp{{ number_format((float) $item->price_per_unit * (int) $item->qty, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="rounded-xl border bg-white p-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Audit Log Status</h3>
                    <p class="mt-1 text-sm text-slate-600">Filter histori berdasarkan actor, status, dan rentang tanggal.</p>
                </div>
                <form method="GET" action="{{ route('admin.procurement.orders.show', ['adminOrderId' => $order->id]) }}" class="grid grid-cols-1 gap-2 md:grid-cols-5">
                    <input
                        type="text"
                        name="history_actor"
                        value="{{ $historyFilters['actor'] ?? '' }}"
                        placeholder="Actor"
                        class="rounded-md border-slate-300 text-xs"
                    >
                    <select name="history_status" class="rounded-md border-slate-300 text-xs">
                        <option value="">Semua Status</option>
                        @foreach(['pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled'] as $historyStatusOption)
                            <option value="{{ $historyStatusOption }}" @selected(($historyFilters['status'] ?? '') === $historyStatusOption)>
                                {{ strtoupper($historyStatusOption) }}
                            </option>
                        @endforeach
                    </select>
                    <input
                        type="date"
                        name="history_date_from"
                        value="{{ $historyFilters['date_from'] ?? '' }}"
                        class="rounded-md border-slate-300 text-xs"
                    >
                    <input
                        type="date"
                        name="history_date_to"
                        value="{{ $historyFilters['date_to'] ?? '' }}"
                        class="rounded-md border-slate-300 text-xs"
                    >
                    <div class="flex items-center gap-2">
                        <button type="submit" class="rounded bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            Filter
                        </button>
                        <a
                            href="{{ route('admin.procurement.orders.show', ['adminOrderId' => $order->id]) }}"
                            class="rounded border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <p class="mt-3 text-xs text-slate-500">Menampilkan {{ number_format($statusHistory->count()) }} histori.</p>

            @if($statusHistory->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Tidak ada histori yang cocok dengan filter saat ini.</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach($statusHistory as $history)
                        <div class="rounded-lg border p-3">
                            <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ strtoupper($history->from_status ?: 'NEW') }} -> {{ strtoupper($history->to_status) }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ \Illuminate\Support\Carbon::parse($history->created_at)->format('d M Y H:i') }}
                                </p>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">
                                Actor: {{ $history->actor_name ?: ('User #' . ($history->actor_user_id ?? '-')) }} ({{ strtoupper($history->actor_role ?: 'SYSTEM') }})
                            </p>
                            <p class="mt-1 text-sm text-slate-600">{{ $history->note ?: '-' }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
