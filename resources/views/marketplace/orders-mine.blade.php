@extends('layouts.marketplace')

@section('title', 'Pesanan Saya')
@section('pageTitle', 'Pesanan Saya')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
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

        @if($orders->isEmpty())
            <section class="surface-card p-6">
                <p class="text-sm text-slate-600">Belum ada order. Tambahkan produk dari landing page lalu checkout.</p>
                <a href="{{ route('landing') }}" class="mt-3 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Kembali ke Marketplace
                </a>
            </section>
        @else
            @foreach($orders as $order)
                @php
                    $items = $itemsByOrder->get($order->id, collect());
                    $dispute = $disputesByOrder->get($order->id);
                    $rating = ($ratingsByOrder ?? collect())->get($order->id);
                    $ratingMeta = ($ratingMetaByOrder ?? collect())->get($order->id, [
                        'can_rate' => false,
                        'is_expired' => false,
                        'deadline_at_label' => null,
                        'window_days' => 7,
                    ]);
                    $statusMeta = match ((string) $order->order_status) {
                        'pending_payment' => ['label' => 'Menunggu Pembayaran', 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
                        'paid' => ['label' => 'Dibayar', 'class' => 'border-sky-200 bg-sky-50 text-sky-700'],
                        'shipped' => ['label' => 'Dikirim', 'class' => 'border-cyan-200 bg-cyan-50 text-cyan-700'],
                        'cancelled' => ['label' => 'Ditolak', 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
                        'completed' => ['label' => 'Selesai', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                        'packed' => ['label' => 'Dikemas', 'class' => 'border-indigo-200 bg-indigo-50 text-indigo-700'],
                        default => ['label' => 'Diproses', 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
                    };
                    $orderPaymentMethod = strtolower(trim((string) ($order->payment_method ?? '')));
                    $orderPaymentMethod = $orderPaymentMethod !== '' ? $orderPaymentMethod : 'bank_transfer';
                    $paymentMethodLabel = $orderPaymentMethod === 'bank_transfer'
                        ? 'Transfer Bank'
                        : strtoupper(str_replace('_', ' ', $orderPaymentMethod));
                    $isCompletedPaid = (string) $order->order_status === 'completed'
                        && (string) ($order->payment_status ?? '') === 'paid';
                    $canCreateDispute = in_array((string) $order->order_status, ['packed', 'shipped', 'completed'], true)
                        && (string) ($order->payment_status ?? '') === 'paid'
                        && ! $dispute;
                    $ratingAlreadySubmitted = $rating !== null;
                    $canRateNow = (bool) ($ratingMeta['can_rate'] ?? false);
                    $showRatingAction = $isCompletedPaid && $canRateNow && ! $ratingAlreadySubmitted;
                    $showDisputeAction = $canCreateDispute || (bool) $dispute;
                    $showActionPanelCard = $showRatingAction
                        || $showDisputeAction
                        || ($isCompletedPaid && $ratingAlreadySubmitted)
                        || ($isCompletedPaid && !empty($ratingMeta['is_expired']));
                    $oldOrderId = (int) old('_order_id', 0);
                    $oldOrderAction = trim((string) old('_order_action', ''));
                    $initialActionPanel = ($oldOrderId === (int) $order->id && in_array($oldOrderAction, ['rating', 'dispute'], true))
                        ? $oldOrderAction
                        : null;
                @endphp
                <article class="surface-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Order #{{ $order->id }}</h3>
                            <p class="text-xs text-slate-500">
                                Dibuat {{ \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y H:i') }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600">
                                Seller: <strong>{{ $order->seller_name ?: '-' }}</strong> ({{ $order->seller_email ?: '-' }})
                            </p>
                            @if(!empty($order->resi_number))
                                <p class="mt-1 text-xs text-slate-500">No. Resi: {{ $order->resi_number }}</p>
                            @endif
                        </div>
                        <div class="text-right text-sm">
                            <p class="font-semibold text-slate-900">Rp{{ number_format((float) $order->total_amount, 0, ',', '.') }}</p>
                            <span class="mt-1 inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusMeta['class'] }}">
                                {{ $statusMeta['label'] }}
                            </span>
                        </div>
                    </div>

                    @if($items->isNotEmpty())
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-left text-slate-500">
                                        <th class="py-2 pr-4">Item</th>
                                        <th class="py-2 pr-4">Qty</th>
                                        <th class="py-2">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                        <tr class="border-b border-slate-100 last:border-0">
                                            <td class="py-2 pr-4 text-slate-800">{{ $item->product_name }}</td>
                                            <td class="py-2 pr-4 text-slate-700">{{ number_format((int) $item->qty) }}</td>
                                            <td class="py-2 text-slate-700">Rp{{ number_format(((int) $item->qty) * ((float) $item->price_per_unit), 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if((string) $order->order_status === 'pending_payment' && (string) ($order->payment_status ?? 'unpaid') === 'unpaid')
                        <div class="mt-4 rounded-xl border border-cyan-200 bg-cyan-50/40 p-4">
                            <p class="text-sm font-semibold text-slate-900">Pembayaran Order</p>
                            <p class="mt-1 text-xs text-slate-600">Metode: {{ $paymentMethodLabel }}</p>

                            @if($orderPaymentMethod !== 'bank_transfer')
                                <p class="mt-2 text-xs text-cyan-700">Pembayaran saldo sedang diproses otomatis oleh sistem.</p>
                            @elseif(!empty($order->payment_proof_url))
                                <p class="mt-2 text-xs text-emerald-700">Bukti pembayaran sudah dikirim. Menunggu verifikasi seller.</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <a href="{{ asset($order->payment_proof_url) }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        Lihat Bukti
                                    </a>
                                    @if(!empty($order->payment_submitted_at))
                                        <span class="text-xs text-slate-500">
                                            Dikirim {{ \Illuminate\Support\Carbon::parse($order->payment_submitted_at)->format('d M Y H:i') }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                <form method="POST" action="{{ route('orders.transfer-proof', ['orderId' => $order->id]) }}" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[180px_1fr_auto] md:items-end">
                                    @csrf
                                    <input type="hidden" name="payment_method" value="{{ $orderPaymentMethod }}">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Nominal Transfer</label>
                                        <input
                                            type="number"
                                            name="paid_amount"
                                            min="1"
                                            step="0.01"
                                            value="{{ (float) $order->total_amount }}"
                                            class="w-full rounded-lg border-slate-300 text-sm"
                                            required
                                        >
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700">Upload Bukti Transfer</label>
                                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf,.webp" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    </div>
                                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                        Kirim Bukti
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif

                    @if((string) $order->order_status === 'shipped' && (string) ($order->payment_status ?? '') === 'paid')
                        <form method="POST" action="{{ route('orders.confirm-received', ['orderId' => $order->id]) }}" class="mt-4" onsubmit="return confirm('Konfirmasi bahwa pesanan ini sudah diterima?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                                Konfirmasi Diterima
                            </button>
                        </form>
                    @endif

                    @if($showActionPanelCard)
                        <div
                            class="mt-4 rounded-xl border border-amber-200 bg-amber-50/40 p-4"
                            x-data="{ actionPanel: {{ \Illuminate\Support\Js::from($initialActionPanel) }} }"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                @if($showRatingAction)
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                                        @click="actionPanel = actionPanel === 'rating' ? null : 'rating'"
                                    >
                                        Rating Penjual / Mitra
                                    </button>
                                @endif
                                @if($showDisputeAction)
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                                        @click="actionPanel = actionPanel === 'dispute' ? null : 'dispute'"
                                    >
                                        {{ $dispute ? 'Lihat Sengketa' : 'Ajukan Sengketa' }}
                                    </button>
                                @endif
                            </div>

                            @if($isCompletedPaid && $ratingAlreadySubmitted)
                                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    Rating tersimpan: {{ number_format((int) ($rating->score ?? 0)) }}/5.
                                    Penilaian hanya bisa dikirim satu kali dan tidak dapat diperbarui.
                                </div>
                            @elseif($isCompletedPaid && !empty($ratingMeta['is_expired']))
                                <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    Masa rating sudah berakhir. Rating hanya bisa dikirim maksimal {{ (int) ($ratingMeta['window_days'] ?? 7) }} hari setelah barang diterima.
                                </div>
                            @endif

                            @if($showRatingAction)
                                <div
                                    class="mt-3 rounded-xl border border-amber-200 bg-white p-4"
                                    x-cloak
                                    x-show="actionPanel === 'rating'"
                                    x-data="ratingCountdown(
                                        {{ \Illuminate\Support\Js::from((string) ($ratingMeta['deadline_at_iso'] ?? '')) }},
                                        {{ \Illuminate\Support\Js::from((string) ($ratingMeta['time_left_label'] ?? '-')) }}
                                    )"
                                    x-init="start()"
                                >
                                    <p class="text-sm font-semibold text-slate-900">Rating Penjual / Mitra</p>
                                    <p class="mt-1 text-xs text-slate-600">
                                        Rating ini mempengaruhi reputasi user penjual/mitra, bukan produk tertentu.
                                        @if(!empty($ratingMeta['deadline_at_label']))
                                            Batas rating: {{ $ratingMeta['deadline_at_label'] }} ({{ (int) ($ratingMeta['window_days'] ?? 7) }} hari).
                                        @endif
                                    </p>
                                    <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                                        Sisa waktu rating: <span x-text="label"></span>
                                    </div>

                                    <form method="POST" action="{{ route('orders.rating.store', ['orderId' => $order->id]) }}" class="mt-3 space-y-3">
                                        @csrf
                                        <input type="hidden" name="_order_id" value="{{ (int) $order->id }}">
                                        <input type="hidden" name="_order_action" value="rating">
                                        <div class="flex flex-wrap gap-2">
                                            @for($score = 5; $score >= 1; $score--)
                                                <label class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                    <input
                                                        type="radio"
                                                        name="score"
                                                        value="{{ $score }}"
                                                        class="rounded border-slate-300 text-amber-600"
                                                        :disabled="expired"
                                                        @checked((int) old('score') === $score)
                                                        required
                                                    >
                                                    <span>{{ $score }} bintang</span>
                                                </label>
                                            @endfor
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-slate-700">Ulasan (Opsional)</label>
                                            <textarea
                                                name="review"
                                                rows="2"
                                                class="w-full rounded-lg border-slate-300 text-sm"
                                                placeholder="Contoh: Pengiriman cepat dan komunikasi jelas."
                                                :disabled="expired"
                                            >{{ old('review') }}</textarea>
                                        </div>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                            :class="expired ? 'bg-slate-400 cursor-not-allowed' : 'bg-amber-700 hover:bg-amber-800'"
                                            :disabled="expired"
                                        >
                                            <span x-show="!expired">Kirim Rating</span>
                                            <span x-cloak x-show="expired">Masa Rating Berakhir</span>
                                        </button>
                                    </form>

                                    <div x-cloak x-show="expired" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        Masa rating sudah berakhir. Rating hanya bisa dikirim maksimal {{ (int) ($ratingMeta['window_days'] ?? 7) }} hari setelah barang diterima.
                                    </div>
                                </div>
                            @endif

                            @if($showDisputeAction)
                                @if($dispute)
                                    @php
                                        $disputeStatusMeta = match ((string) $dispute->status) {
                                            'pending' => ['label' => 'Sengketa Pending', 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
                                            'under_review' => ['label' => 'Sengketa Ditinjau', 'class' => 'border-indigo-200 bg-indigo-50 text-indigo-700'],
                                            'resolved_buyer' => ['label' => 'Sengketa Menang Buyer', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                                            'resolved_seller' => ['label' => 'Sengketa Menang Seller', 'class' => 'border-slate-200 bg-slate-50 text-slate-700'],
                                            'cancelled' => ['label' => 'Sengketa Ditutup', 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
                                            default => ['label' => 'Sengketa', 'class' => 'border-slate-200 bg-slate-50 text-slate-700'],
                                        };
                                    @endphp
                                    <div class="mt-3 rounded-xl border p-4 {{ $disputeStatusMeta['class'] }}" x-cloak x-show="actionPanel === 'dispute'">
                                        <p class="text-sm font-semibold">{{ $disputeStatusMeta['label'] }} (#{{ $dispute->id }})</p>
                                        <p class="mt-1 text-xs uppercase">Kategori: {{ strtoupper((string) ($dispute->category ?? 'other')) }}</p>
                                    </div>
                                @elseif($canCreateDispute)
                                    <div class="mt-3 rounded-xl border border-amber-200 bg-white p-4" x-cloak x-show="actionPanel === 'dispute'">
                                        <p class="text-sm font-semibold text-slate-900">Ajukan Sengketa</p>
                                        <p class="mt-1 text-xs text-slate-600">Gunakan fitur ini jika ada masalah order (barang rusak/tidak sesuai/tidak diterima).</p>
                                        <form method="POST" action="{{ route('orders.disputes.store', ['orderId' => $order->id]) }}" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            @csrf
                                            <input type="hidden" name="_order_id" value="{{ (int) $order->id }}">
                                            <input type="hidden" name="_order_action" value="dispute">
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-slate-700">Kategori</label>
                                                <select name="category" class="w-full rounded-lg border-slate-300 text-sm" required>
                                                    <option value="wrong_item">Barang Tidak Sesuai</option>
                                                    <option value="damaged">Barang Rusak</option>
                                                    <option value="not_received">Barang Belum Diterima</option>
                                                    <option value="delayed_shipping">Pengiriman Terlambat</option>
                                                    <option value="other">Lainnya</option>
                                                </select>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="mb-1 block text-xs font-medium text-slate-700">Deskripsi Masalah</label>
                                                <textarea name="description" rows="2" class="w-full rounded-lg border-slate-300 text-sm" required></textarea>
                                            </div>
                                            <div class="md:col-span-3">
                                                <button type="submit" class="inline-flex items-center rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">
                                                    Kirim Sengketa
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach

            <div>
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    @once
        <script>
            function ratingCountdown(deadlineIso, initialLabel) {
                return {
                    label: initialLabel || '-',
                    expired: false,
                    timerId: null,
                    start() {
                        if (!deadlineIso) {
                            return;
                        }

                        const deadline = new Date(deadlineIso).getTime();
                        if (Number.isNaN(deadline)) {
                            return;
                        }

                        const render = () => {
                            const now = Date.now();
                            let diff = Math.floor((deadline - now) / 1000);
                            if (diff <= 0) {
                                this.label = 'Waktu habis';
                                this.expired = true;
                                if (this.timerId) {
                                    clearInterval(this.timerId);
                                    this.timerId = null;
                                }
                                return;
                            }
                            this.expired = false;

                            const days = Math.floor(diff / 86400);
                            diff %= 86400;
                            const hours = Math.floor(diff / 3600);
                            diff %= 3600;
                            const minutes = Math.floor(diff / 60);
                            const seconds = diff % 60;

                            const parts = [];
                            if (days > 0) {
                                parts.push(`${days} hari`);
                            }
                            if (hours > 0 || days > 0) {
                                parts.push(`${hours} jam`);
                            }
                            parts.push(`${minutes} menit`);
                            parts.push(`${seconds} detik`);

                            this.label = parts.join(' ');
                        };

                        render();
                        this.timerId = window.setInterval(render, 1000);
                    },
                };
            }
        </script>
    @endonce
@endsection
