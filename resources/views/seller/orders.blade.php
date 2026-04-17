<x-seller-layout>
    <x-slot name="header">Order Pembeli</x-slot>

    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
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

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Pending Payment</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($orderCounts['pending_payment'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Paid</p>
                <p class="mt-2 text-2xl font-bold text-cyan-700">{{ number_format((int) ($orderCounts['paid'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Packed</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format((int) ($orderCounts['packed'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Shipped</p>
                <p class="mt-2 text-2xl font-bold text-indigo-700">{{ number_format((int) ($orderCounts['shipped'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Completed</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format((int) ($orderCounts['completed'] ?? 0)) }}</p>
            </article>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Semua Order Pembeli</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Total order: {{ number_format((int) ($allBuyerOrdersCount ?? 0)) }}
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-slate-600">
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Buyer</th>
                            <th class="px-4 py-3">Order Status</th>
                            <th class="px-4 py-3">Payment Status</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Update</th>
                            <th class="px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($ordersPaginator ?? collect()) as $row)
                            @php
                                $orderStatus = strtolower((string) ($row->order_status ?? ''));
                                $orderStatusClass = match ($orderStatus) {
                                    'pending_payment' => 'border-amber-200 bg-amber-50 text-amber-700',
                                    'paid' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
                                    'packed' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                    'shipped' => 'border-violet-200 bg-violet-50 text-violet-700',
                                    'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    default => 'border-slate-200 bg-slate-50 text-slate-700',
                                };
                                $paymentStatus = strtolower((string) ($row->payment_status ?? ''));
                                $paymentStatusClass = match ($paymentStatus) {
                                    'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'waiting_verification' => 'border-amber-200 bg-amber-50 text-amber-700',
                                    'unpaid' => 'border-slate-200 bg-slate-50 text-slate-700',
                                    default => 'border-slate-200 bg-slate-50 text-slate-700',
                                };
                                $paymentMethod = strtolower(trim((string) ($row->payment_method ?? '')));
                                $updatedLabel = \Illuminate\Support\Carbon::parse($row->updated_at)->format('d M Y H:i');
                                $isCodPending = $orderStatus === 'pending_payment'
                                    && $paymentStatus === 'unpaid'
                                    && in_array($paymentMethod, ['cash', 'cod'], true);
                                $canMarkPacked = $orderStatus === 'paid';
                                $canMarkShipped = $orderStatus === 'packed';
                            @endphp
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="px-4 py-3 text-slate-700">
                                    <p class="font-semibold text-slate-900">#{{ (int) ($row->id ?? 0) }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    <p class="font-semibold text-slate-900">{{ (string) (($row->buyer_name ?? '') !== '' ? $row->buyer_name : ('User #' . (int) ($row->buyer_id ?? 0))) }}</p>
                                    <p class="text-xs text-slate-500">{{ (string) (($row->buyer_email ?? '') !== '' ? $row->buyer_email : '-') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold uppercase {{ $orderStatusClass }}">
                                        {{ strtoupper((string) ($row->order_status ?? '-')) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold uppercase {{ $paymentStatusClass }}">
                                        {{ strtoupper((string) ($row->payment_status ?? '-')) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                    Rp{{ number_format((float) ($row->total_amount ?? 0), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $updatedLabel }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($isCodPending)
                                        <form method="POST" action="{{ route('seller.orders.confirmCash', ['orderId' => (int) ($row->id ?? 0)]) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-100">
                                                Konfirmasi COD
                                            </button>
                                        </form>
                                    @elseif($canMarkPacked)
                                        <form method="POST" action="{{ route('seller.orders.markPacked', ['orderId' => (int) ($row->id ?? 0)]) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100">
                                                Tandai Packed
                                            </button>
                                        </form>
                                    @elseif($canMarkShipped)
                                        <form method="POST" action="{{ route('seller.orders.markShipped', ['orderId' => (int) ($row->id ?? 0)]) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex rounded-md border border-violet-200 bg-violet-50 px-2 py-1 text-[11px] font-semibold text-violet-700 hover:bg-violet-100">
                                                Tandai Shipped
                                            </button>
                                        </form>
                                    @elseif($orderStatus === 'pending_payment')
                                        <a href="{{ route('notifications.index', ['type' => 'payment', 'status' => 'unread']) }}" class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">
                                            Cek Pembayaran
                                        </a>
                                    @elseif(in_array($orderStatus, ['shipped', 'completed'], true))
                                        <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-500">
                                            Tidak ada aksi
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-500">
                                            Lihat detail
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                    Belum ada order pembeli yang masuk.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($ordersPaginator)
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $ordersPaginator->links() }}
                </div>
            @endif
        </section>
    </div>
</x-seller-layout>

