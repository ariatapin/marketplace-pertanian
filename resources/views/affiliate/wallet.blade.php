<x-affiliate-layout>
    <x-slot name="header">Dompet Saya</x-slot>

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

        <section class="surface-card p-5">
            <h2 class="text-2xl font-bold text-slate-900">Dompet Saya</h2>
            <p class="mt-1 text-sm text-slate-600">Ringkasan keuangan affiliate, mutasi wallet, dan proses withdraw dalam satu halaman.</p>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Saldo Wallet</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Rp{{ number_format((float) ($summary['balance'] ?? 0), 0, ',', '.') }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Saldo Tersedia</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">Rp{{ number_format((float) ($summary['available_balance'] ?? 0), 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Ditahan: Rp{{ number_format((float) ($summary['reserved_withdraw_amount'] ?? 0), 0, ',', '.') }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Komisi</p>
                <p class="mt-2 text-2xl font-bold text-cyan-700">Rp{{ number_format((float) ($summary['total_commission'] ?? 0), 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ number_format((int) ($summary['commission_count'] ?? 0)) }} transaksi komisi</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Withdraw Pending</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format((int) ($summary['pending_withdraw_count'] ?? 0)) }}</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <article class="surface-card p-5">
                <h3 class="text-base font-semibold text-slate-900">Withdraw Cepat</h3>
                <p class="mt-1 text-sm text-slate-600">Ajukan pencairan langsung dari halaman Dompet.</p>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Saldo Tersedia</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Rp{{ number_format((float) ($summary['available_balance'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Minimal Withdraw</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Rp{{ number_format((float) ($minWithdraw ?? 0), 0, ',', '.') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('wallet.withdraw.request') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="affiliate-wallet-withdraw-amount" class="mb-1 block text-sm font-medium text-slate-700">Nominal Withdraw</label>
                        <input
                            id="affiliate-wallet-withdraw-amount"
                            type="number"
                            name="amount"
                            min="1"
                            step="1"
                            value="{{ old('amount') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none"
                            placeholder="Contoh: 100000"
                        >
                    </div>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-white {{ $withdrawAllowed ? 'bg-emerald-600 hover:bg-emerald-700' : 'cursor-not-allowed bg-slate-400' }}"
                        {{ $withdrawAllowed ? '' : 'disabled' }}
                    >
                        Ajukan Withdraw
                    </button>
                </form>

                @if(! $withdrawAllowed)
                    <p class="mt-3 text-xs text-rose-700">{{ $withdrawPolicyMessage }}</p>
                @endif
            </article>

            <article class="surface-card p-5">
                <h3 class="text-base font-semibold text-slate-900">Riwayat Komisi Terbaru</h3>
                <div class="mt-3 space-y-2">
                    @forelse($recentCommissions as $row)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $row->description ?: 'Komisi affiliate' }}</p>
                                <span class="text-sm font-semibold text-emerald-700">Rp{{ number_format((float) ($row->amount ?? 0), 0, ',', '.') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($row->created_at)->translatedFormat('d M Y H:i') }}
                                @if(!empty($row->reference_order_id))
                                    · Order #{{ $row->reference_order_id }}
                                @endif
                            </p>
                        </article>
                    @empty
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Belum ada transaksi komisi.
                        </div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Mutasi Wallet</h3>
                <p class="mt-1 text-sm text-slate-600">Semua pergerakan saldo wallet affiliate.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-slate-600">
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Deskripsi</th>
                            <th class="px-4 py-3">Referensi</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($walletTransactions as $row)
                            @php
                                $amount = (float) ($row->amount ?? 0);
                            @endphp
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="px-4 py-3 text-slate-700">{{ \Illuminate\Support\Carbon::parse($row->created_at)->translatedFormat('d M Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                        {{ strtoupper((string) ($row->transaction_type ?? '-')) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $row->description ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if(!empty($row->reference_order_id))
                                        Order #{{ $row->reference_order_id }}
                                    @elseif(!empty($row->reference_withdraw_id))
                                        Withdraw #{{ $row->reference_withdraw_id }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold {{ $amount >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $amount >= 0 ? '+' : '-' }}Rp{{ number_format(abs($amount), 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                    Belum ada mutasi wallet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Riwayat Withdraw</h3>
                <p class="mt-1 text-sm text-slate-600">Status pengajuan pencairan Anda.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-slate-600">
                            <th class="px-4 py-3">Request</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Diproses</th>
                            <th class="px-4 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($withdrawHistories as $row)
                            @php
                                $status = strtolower(trim((string) ($row->status ?? 'pending')));
                                $statusClass = match ($status) {
                                    'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                    'approved' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
                                    'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
                                    'cancelled' => 'border-slate-200 bg-slate-100 text-slate-700',
                                    default => 'border-amber-200 bg-amber-50 text-amber-700',
                                };
                            @endphp
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="px-4 py-3 text-slate-700">#{{ $row->id }} · {{ \Illuminate\Support\Carbon::parse($row->created_at)->translatedFormat('d M Y H:i') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">Rp{{ number_format((float) ($row->amount ?? 0), 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold uppercase {{ $statusClass }}">
                                        {{ $status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if(!empty($row->processed_at))
                                        {{ \Illuminate\Support\Carbon::parse($row->processed_at)->translatedFormat('d M Y H:i') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $row->notes ?: (($row->transfer_reference ?? '') !== '' ? 'Ref: ' . $row->transfer_reference : '-') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                    Belum ada riwayat withdraw.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-affiliate-layout>
