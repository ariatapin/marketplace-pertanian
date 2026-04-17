<x-mitra-layout>
    <x-slot name="header">{{ __('Detail Order Mitra') }}</x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @php
                $paymentMethodKey = strtolower(trim((string) ($order->payment_method ?? '')));
                $paymentMethodLabel = $paymentMethodKey === 'bank_transfer'
                    ? 'Transfer Bank'
                    : ($paymentMethodKey !== '' ? strtoupper(str_replace('_', ' ', $paymentMethodKey)) : '-');
                $isBankTransfer = $paymentMethodKey === 'bank_transfer';
            @endphp

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

            <div class="rounded-xl border bg-white p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Order #{{ $order->id }}</h3>
                        <p class="text-sm text-gray-600">Buyer: {{ $order->buyer_name ?: '-' }} ({{ $order->buyer_email ?: '-' }})</p>
                        <p class="text-sm text-gray-600">Tanggal: {{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y H:i') }}</p>
                    </div>
                    <a href="{{ route('mitra.orders.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Kembali ke daftar order</a>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                    <div class="rounded border p-3"><p class="text-gray-500">Sumber</p><p class="font-semibold text-gray-900 uppercase">{{ $order->order_source }}</p></div>
                    <div class="rounded border p-3">
                        <p class="text-gray-500">Payment</p>
                        <p class="font-semibold text-gray-900 uppercase">{{ $order->payment_status }}</p>
                        <p class="text-xs text-gray-500">{{ $paymentMethodLabel }}</p>
                    </div>
                    <div class="rounded border p-3"><p class="text-gray-500">Order Status</p><p class="font-semibold text-gray-900 uppercase">{{ $order->order_status }}</p></div>
                    <div class="rounded border p-3"><p class="text-gray-500">Shipping</p><p class="font-semibold text-gray-900 uppercase">{{ $order->shipping_status }}</p></div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                    <div class="rounded border p-3"><p class="text-gray-500">Total Order</p><p class="text-lg font-semibold text-gray-900">Rp{{ number_format((float) $order->total_amount, 0, ',', '.') }}</p></div>
                    <div class="rounded border p-3"><p class="text-gray-500">Nominal Transfer</p><p class="text-lg font-semibold text-gray-900">{{ $order->paid_amount !== null ? 'Rp' . number_format((float) $order->paid_amount, 0, ',', '.') : '-' }}</p></div>
                    <div class="rounded border p-3"><p class="text-gray-500">Total Qty Item</p><p class="text-lg font-semibold text-gray-900">{{ number_format($itemsTotalQty) }}</p></div>
                    <div class="rounded border p-3"><p class="text-gray-500">No Resi</p><p class="text-lg font-semibold text-gray-900">{{ $order->resi_number ?: '-' }}</p></div>
                </div>

                @if($order->payment_proof_url)
                    <div class="mt-3 text-sm">
                        <a href="{{ asset($order->payment_proof_url) }}" target="_blank" class="font-semibold text-indigo-600 hover:text-indigo-700">
                            Lihat bukti transfer buyer
                        </a>
                        @if($order->payment_submitted_at)
                            <p class="mt-1 text-xs text-gray-500">
                                Diupload pada {{ \Illuminate\Support\Carbon::parse($order->payment_submitted_at)->format('d M Y H:i') }}
                            </p>
                        @endif
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap gap-2">
                    @if($order->order_status === 'pending_payment' && $order->payment_status === 'unpaid' && !empty($order->payment_proof_url) && $isBankTransfer)
                        <form method="POST" action="{{ route('mitra.orders.markPaid', ['orderId' => $order->id]) }}">
                            @csrf
                            <button type="submit" class="rounded bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Konfirmasi Pembayaran</button>
                        </form>
                    @elseif($order->order_status === 'pending_payment' && $order->payment_status === 'unpaid' && empty($order->payment_proof_url))
                        <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">
                            Menunggu buyer upload bukti transfer.
                        </div>
                    @elseif($order->order_status === 'pending_payment' && $order->payment_status === 'unpaid' && !$isBankTransfer)
                        <div class="rounded border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-700">
                            Menunggu proses pembayaran saldo otomatis.
                        </div>
                    @endif
                    @if($order->order_status === 'paid')
                        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                            Status transisi, menunggu packed otomatis.
                        </div>
                    @endif

                    @if($order->order_status === 'packed')
                        <form method="POST" action="{{ route('mitra.orders.markShipped', ['orderId' => $order->id]) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="text" name="resi_number" value="{{ $order->resi_number }}" placeholder="No Resi (opsional)" class="rounded border-gray-300 px-2 py-1 text-sm">
                            <button type="submit" class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Kirim</button>
                        </form>
                    @endif
                </div>

                <div class="mt-6 rounded-xl border border-gray-200 p-4">
                    <h4 class="text-sm font-semibold text-gray-900">Timeline Status Order</h4>
                    @if($statusHistory->isEmpty())
                        <p class="mt-2 text-sm text-gray-600">Belum ada riwayat transisi status untuk order ini.</p>
                    @else
                        <div class="mt-3 space-y-3">
                            @foreach($statusHistory as $history)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                                    <p class="font-medium text-gray-900">
                                        {{ strtoupper($history->from_status ?: 'INIT') }} -> {{ strtoupper($history->to_status) }}
                                    </p>
                                    <p class="mt-1 text-gray-700">{{ $history->note ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $history->actor_name ?: 'System' }} ({{ strtoupper($history->actor_role ?: '-') }})
                                        - {{ \Illuminate\Support\Carbon::parse($history->created_at)->format('d M Y H:i') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border bg-white p-6">
                <h3 class="text-base font-semibold text-gray-900">Item Order</h3>
                @if($items->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">Belum ada item tercatat untuk order ini.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-600">
                                    <th class="py-2 pr-4">Produk</th>
                                    <th class="py-2 pr-4">Qty</th>
                                    <th class="py-2 pr-4">Harga/Unit</th>
                                    <th class="py-2">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4 text-gray-900">{{ $item->product_name }}</td>
                                        <td class="py-3 pr-4 text-gray-700">{{ number_format((int) $item->qty) }}</td>
                                        <td class="py-3 pr-4 text-gray-700">Rp{{ number_format((float) $item->price_per_unit, 0, ',', '.') }}</td>
                                        <td class="py-3 text-gray-700">Rp{{ number_format(((float) $item->price_per_unit) * ((int) $item->qty), 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-sm text-gray-700">
                        <p>Total item amount: <span class="font-semibold">Rp{{ number_format($itemsTotalAmount, 0, ',', '.') }}</span></p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-mitra-layout>
