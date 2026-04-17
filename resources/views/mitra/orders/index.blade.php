<x-mitra-layout>
    <x-slot name="header">{{ __('Pesanan Mitra') }}</x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @php
            $incomingCount = (int) ($summary['pending_payment'] ?? 0) + (int) ($summary['packed'] ?? 0);
            $shippingCount = (int) ($summary['shipped'] ?? 0);
            $historyCount = (int) ($summary['completed'] ?? 0);
        @endphp

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

        <section class="rounded-xl border bg-white p-4">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('mitra.orders.index', ['status' => 'pending_payment']) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold {{ ($filters['status'] ?? '') === 'pending_payment' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Konfirmasi Pembayaran
                </a>
                <a href="{{ route('mitra.orders.index', ['status' => 'packed']) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold {{ ($filters['status'] ?? '') === 'packed' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Siap Kirim
                </a>
                <a href="{{ route('mitra.orders.index', ['status' => 'shipped']) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold {{ ($filters['status'] ?? '') === 'shipped' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Dalam Pengiriman
                </a>
                <a href="{{ route('mitra.orders.index', ['status' => 'completed']) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold {{ ($filters['status'] ?? '') === 'completed' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Riwayat Pesanan
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Total Order</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($summary['total'] ?? 0) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Pesanan Masuk</p><p class="mt-2 text-3xl font-bold text-cyan-700">{{ number_format($incomingCount) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Siap Kirim</p><p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format($summary['packed'] ?? 0) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Dalam Pengiriman</p><p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($shippingCount) }}</p></article>
            <article class="surface-card p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Riwayat Selesai</p><p class="mt-2 text-3xl font-bold text-slate-700">{{ number_format($historyCount) }}</p></article>
        </section>

        <section class="surface-card p-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Daftar Order Customer</h3>
                    <p class="text-sm text-slate-600">Kelola order dari consumer. Setelah pembayaran diverifikasi, order otomatis masuk status packed.</p>
                </div>
                <a href="{{ route('mitra.dashboard') }}" class="link-ghost">Kembali Dashboard</a>
            </div>

            <form method="GET" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                <select name="status" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua status order</option>
                    @foreach(['pending_payment','paid','packed','shipped','completed','cancelled'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ strtoupper($status) }}</option>
                    @endforeach
                </select>
                <select name="payment_status" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua payment</option>
                    @foreach(['unpaid','paid','refunded','failed'] as $payment)
                        <option value="{{ $payment }}" @selected(($filters['payment_status'] ?? '') === $payment)>{{ strtoupper($payment) }}</option>
                    @endforeach
                </select>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari ID / nama buyer" class="rounded-lg border-slate-300 text-sm">
                <button type="submit" class="btn-ink">Filter</button>
            </form>

            @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->isEmpty())
                <p class="mt-4 text-sm text-slate-600">Belum ada order untuk filter saat ini.</p>
            @elseif($rows instanceof \Illuminate\Support\Collection && $rows->isEmpty())
                <p class="mt-4 text-sm text-slate-600">Belum ada order.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4">Order</th>
                                <th class="py-2 pr-4">Buyer</th>
                                <th class="py-2 pr-4">Sumber</th>
                                <th class="py-2 pr-4">Pembayaran</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Total</th>
                                <th class="py-2 pr-4">Resi</th>
                                <th class="py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @php
                                    $paymentMethodKey = strtolower(trim((string) ($row->payment_method ?? '')));
                                    $paymentMethodLabel = $paymentMethodKey === 'bank_transfer'
                                        ? 'TRANSFER BANK'
                                        : ($paymentMethodKey !== '' ? strtoupper(str_replace('_', ' ', $paymentMethodKey)) : '-');
                                    $isBankTransfer = $paymentMethodKey === 'bank_transfer';
                                @endphp
                                <tr class="border-b border-slate-100 align-top last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">
                                        <a href="{{ route('mitra.orders.show', ['orderId' => $row->id]) }}" class="text-cyan-700 hover:text-cyan-900">
                                            #{{ $row->id }}
                                        </a>
                                        <div class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y H:i') }}</div>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->buyer_name ?: '-' }}<div class="text-xs text-slate-500">{{ $row->buyer_email ?: '-' }}</div></td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->order_source }}</td>
                                    <td class="py-3 pr-4 text-slate-700">
                                        <div class="uppercase">{{ $row->payment_status }}</div>
                                        <div class="text-xs text-slate-500">
                                            {{ $paymentMethodLabel }}
                                            @if($row->paid_amount !== null)
                                                | Rp{{ number_format((float) $row->paid_amount, 0, ',', '.') }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $row->order_status }}</td>
                                    <td class="py-3 pr-4 text-slate-700">Rp{{ number_format((float) $row->total_amount, 0, ',', '.') }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->resi_number ?: '-' }}</td>
                                    <td class="py-3">
                                        <div class="flex flex-col gap-2">
                                            @if($row->order_status === 'pending_payment' && $row->payment_status === 'unpaid' && !empty($row->payment_proof_url) && $isBankTransfer)
                                                <form method="POST" action="{{ route('mitra.orders.markPaid', ['orderId' => $row->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Konfirmasi Pembayaran</button>
                                                </form>
                                            @elseif($row->order_status === 'pending_payment' && $row->payment_status === 'unpaid' && empty($row->payment_proof_url))
                                                <p class="text-xs font-semibold text-slate-600">Menunggu buyer upload bukti transfer.</p>
                                            @elseif($row->order_status === 'pending_payment' && $row->payment_status === 'unpaid' && !$isBankTransfer)
                                                <p class="text-xs font-semibold text-cyan-700">Menunggu proses pembayaran saldo otomatis.</p>
                                            @endif
                                            @if($row->order_status === 'paid')
                                                <p class="text-xs font-semibold text-amber-700">Status transisi, menunggu packed otomatis.</p>
                                            @endif
                                            @if($row->order_status === 'packed')
                                                <form method="POST" action="{{ route('mitra.orders.markShipped', ['orderId' => $row->id]) }}" class="space-y-1">
                                                    @csrf
                                                    <input type="text" name="resi_number" value="{{ $row->resi_number }}" placeholder="No Resi (opsional)" class="w-full rounded-lg border-slate-300 px-2 py-1 text-xs">
                                                    <button type="submit" class="rounded-lg bg-cyan-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-800">Kirim</button>
                                                </form>
                                            @endif
                                            <a href="{{ route('mitra.orders.show', ['orderId' => $row->id]) }}" class="text-xs font-semibold text-cyan-700 hover:text-cyan-900">Detail</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <div class="mt-4">{{ $rows->links() }}</div>
                @endif
            @endif
        </section>
    </div>
</x-mitra-layout>
