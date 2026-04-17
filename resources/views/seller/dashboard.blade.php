<x-seller-layout>
    <x-slot name="header">Dashboard Penjual</x-slot>

    <style>
        .seller-withdraw-order-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .seller-withdraw-card {
            max-width: 100%;
        }

        .seller-withdraw-mini-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        @media (min-width: 640px) {
            .seller-withdraw-mini-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (min-width: 1024px) {
            .seller-withdraw-order-grid {
                grid-template-columns: minmax(0, 0.78fr) minmax(0, 1.22fr);
            }

            .seller-withdraw-card {
                max-width: 560px;
            }

        }
    </style>

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

        <section class="rounded-3xl border border-slate-800/20 bg-gradient-to-r from-amber-700 via-amber-600 to-emerald-600 p-5 text-white shadow-xl md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs uppercase tracking-[0.2em] text-amber-100">Seller Control</p>
                    <h2 class="mt-1.5 text-2xl font-bold">Pantau Order & Kelola Produk Hasil Tani</h2>
                    <p class="mt-2 text-sm text-amber-50">
                        Marketplace tetap bisa dipakai sambil memantau order pembeli di mode penjual.
                    </p>
                </div>
                <div class="w-full space-y-3 lg:w-auto lg:min-w-[340px]">
                    <div class="rounded-xl border border-white/25 bg-white/15 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100">Saldo Wallet</p>
                        <p class="mt-1 text-2xl font-extrabold text-white">Rp{{ number_format((float) $walletBalance, 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-amber-50">Topup demo tersedia di halaman Profil.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('seller.products.index') }}" class="inline-flex items-center rounded-lg border border-white/30 bg-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/25">
                            Kelola Produk
                        </a>
                        <a href="{{ route('landing', ['source' => 'seller']) }}" class="inline-flex items-center rounded-lg border border-white/30 bg-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/25">
                            Produk Petani di Marketplace
                        </a>
                        <a href="{{ route('notifications.index') }}" class="inline-flex items-center rounded-lg border border-white/30 bg-white/15 px-4 py-2 text-sm font-semibold text-white hover:bg-white/25">
                            Notifikasi
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Pending Payment</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) $orderCounts['pending_payment']) }}</p>
            </article>
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Paid</p>
                <p class="mt-2 text-2xl font-bold text-cyan-700">{{ number_format((int) $orderCounts['paid']) }}</p>
            </article>
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Packed</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format((int) $orderCounts['packed']) }}</p>
            </article>
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Shipped</p>
                <p class="mt-2 text-2xl font-bold text-indigo-700">{{ number_format((int) $orderCounts['shipped']) }}</p>
            </article>
            <article class="surface-card p-4 xl:col-span-1">
                <p class="text-xs uppercase tracking-wide text-slate-500">Completed</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format((int) $orderCounts['completed']) }}</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Produk Saya</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($productSummary['total'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Stok</p>
                <p class="mt-2 text-2xl font-bold text-cyan-700">{{ number_format((int) ($productSummary['total_stock'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Rating Akun Penjual</p>
                @if((int) ($ratingSummary['total_reviews'] ?? 0) > 0)
                    <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format((float) ($ratingSummary['average_score'] ?? 0), 1, ',', '.') }}/5</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format((int) ($ratingSummary['total_reviews'] ?? 0)) }} ulasan buyer</p>
                @else
                    <p class="mt-2 text-2xl font-bold text-slate-900">0/5</p>
                    <p class="mt-1 text-xs text-slate-500">Belum ada ulasan buyer.</p>
                @endif
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <article class="surface-card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Produk Hasil Tani Terbaru</h3>
                            <p class="mt-1 text-sm text-slate-600">Produk milik Anda yang tercatat di sistem marketplace.</p>
                        </div>
                        <a href="{{ route('seller.products.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                            Kelola Produk
                        </a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="px-4 py-3">Produk</th>
                                <th class="px-4 py-3 text-right">Harga</th>
                                <th class="px-4 py-3 text-right">Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentProducts as $product)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                                        <p class="text-xs text-slate-500">Update {{ \Illuminate\Support\Carbon::parse($product->updated_at)->format('d M Y H:i') }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900">Rp{{ number_format((float) $product->price, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-slate-700">{{ number_format((int) $product->stock_qty) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-slate-500">Belum ada produk hasil tani. Tambahkan produk baru dari menu Kelola Produk.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article id="seller-notifications" class="surface-card p-5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Notifikasi Dashboard Penjual</h3>
                        <p class="mt-1 text-sm text-slate-600">Hanya menampilkan notifikasi order dari pembeli dan notifikasi yang dikirim admin.</p>
                    </div>
                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                        {{ number_format((int) ($dashboardNotificationUnreadCount ?? 0)) }} belum dibaca
                    </span>
                </div>
                <div class="mt-3 space-y-2.5">
                    @forelse($dashboardNotifications as $item)
                        @php
                            $notificationStatus = strtolower((string) ($item['status'] ?? 'info'));
                            $notificationClass = match ($notificationStatus) {
                                'red' => 'border-rose-200 bg-rose-50 text-rose-700',
                                'approved', 'green', 'paid', 'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'yellow' => 'border-amber-200 bg-amber-50 text-amber-700',
                                'pending', 'waiting_verification' => 'border-amber-200 bg-amber-50 text-amber-700',
                                default => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            };
                            $channel = strtolower((string) ($item['channel'] ?? 'admin'));
                            $channelClass = $channel === 'order'
                                ? 'border-cyan-200 bg-cyan-50 text-cyan-700'
                                : 'border-indigo-200 bg-indigo-50 text-indigo-700';
                        @endphp
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $channelClass }}">
                                        {{ (string) ($item['channel_label'] ?? 'Notifikasi') }}
                                    </span>
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold uppercase {{ $notificationClass }}">
                                        {{ strtoupper($notificationStatus) }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500">
                                    {{ (string) ($item['created_at_label'] ?? '-') }}
                                </p>
                            </div>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ (string) ($item['title'] ?? 'Notifikasi') }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ (string) ($item['message'] ?? '-') }}</p>
                            @if(!empty($item['action_url']))
                                <a href="{{ $item['action_url'] }}" class="mt-2 inline-flex rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                    {{ (string) (($item['action_label'] ?? '') !== '' ? $item['action_label'] : 'Lihat Detail') }}
                                </a>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-sm text-slate-600">
                            Belum ada notifikasi order pembeli atau notifikasi admin untuk dashboard penjual.
                        </div>
                    @endforelse
                </div>
                <div class="mt-3">
                    <a href="{{ route('notifications.index') }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                        Buka Notifikasi Marketplace
                    </a>
                </div>
            </article>
        </section>

        <section class="seller-withdraw-order-grid lg:items-start">
            <article class="surface-card seller-withdraw-card p-4">
                <h3 class="text-base font-semibold text-slate-900">Withdraw Cepat</h3>
                <p class="mt-1 text-xs text-slate-600">Ajukan pencairan hasil penjualan langsung dari dashboard.</p>

                <div class="seller-withdraw-mini-grid mt-2.5">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Saldo Tersedia</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Rp{{ number_format((float) $walletBalance, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Minimal Withdraw</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Rp{{ number_format((float) $minWithdraw, 0, ',', '.') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('wallet.withdraw.request') }}" class="mt-3 space-y-2.5">
                    @csrf
                    <div>
                        <label for="seller-withdraw-amount" class="mb-1 block text-sm font-medium text-slate-700">Nominal Withdraw</label>
                        <input
                            id="seller-withdraw-amount"
                            type="number"
                            name="amount"
                            min="1"
                            step="1"
                            value="{{ old('amount') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-amber-400 focus:outline-none"
                            placeholder="Contoh: 150000"
                        >
                    </div>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg px-4 py-1.5 text-sm font-semibold text-white {{ $withdrawAllowed ? 'bg-amber-600 hover:bg-amber-700' : 'bg-slate-400 cursor-not-allowed' }}"
                        {{ $withdrawAllowed ? '' : 'disabled' }}
                    >
                        Ajukan Withdraw
                    </button>
                </form>

                @if(! $withdrawAllowed)
                    <p class="mt-3 text-xs text-rose-700">{{ $withdrawPolicyMessage }}</p>
                @endif
            </article>

            <article id="seller-order-latest" class="surface-card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-slate-900">Order Terbaru</h3>
                        @if((bool) ($hasMoreBuyerOrders ?? false))
                            <a
                                href="{{ route('seller.orders.index') }}"
                                class="inline-flex rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            >
                                Order Lainnya
                            </a>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-600">Proses cepat 3 order terbaru dari pembeli.</p>
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
                            @forelse(($recentOrders ?? collect())->take(3) as $row)
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
                                        Belum ada order masuk untuk akun penjual.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</x-seller-layout>
