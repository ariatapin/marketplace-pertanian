<x-mitra-layout>
    <x-slot name="header">{{ __('Keuangan Mitra') }}</x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Penjualan Kotor</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">Rp{{ number_format((float) ($summary['gross_sales'] ?? 0), 0, ',', '.') }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Biaya Beli Stok</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">Rp{{ number_format((float) ($summary['procurement_cost'] ?? 0), 0, ',', '.') }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Komisi Affiliate</p>
                <p class="mt-2 text-2xl font-bold text-indigo-700">Rp{{ number_format((float) ($summary['affiliate_commission'] ?? 0), 0, ',', '.') }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Laba / Rugi</p>
                <p class="mt-2 text-2xl font-bold {{ (float) ($summary['net_profit'] ?? 0) >= 0 ? 'text-cyan-700' : 'text-rose-700' }}">
                    Rp{{ number_format((float) ($summary['net_profit'] ?? 0), 0, ',', '.') }}
                </p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Saldo Wallet</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Rp{{ number_format((float) ($walletSummary['balance'] ?? 0), 0, ',', '.') }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Dana Ter-reservasi Withdraw</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">Rp{{ number_format((float) ($walletSummary['reserved_withdraw_amount'] ?? 0), 0, ',', '.') }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Saldo Tersedia</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">Rp{{ number_format((float) ($walletSummary['available_balance'] ?? 0), 0, ',', '.') }}</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <article class="surface-card p-6">
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-slate-900">Rekening Payout Mitra</h3>
                    <p class="text-sm text-slate-600">Data rekening ini dipakai untuk pencairan withdraw.</p>
                </div>

                <form method="POST" action="{{ route('mitra.finance.bank.update') }}" class="space-y-3">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="bank_name" class="mb-1 block text-sm font-medium text-slate-700">Nama Bank</label>
                        <input
                            id="bank_name"
                            name="bank_name"
                            type="text"
                            value="{{ old('bank_name', (string) ($bankProfile?->bank_name ?? '')) }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="Contoh: BCA"
                        >
                    </div>

                    <div>
                        <label for="account_number" class="mb-1 block text-sm font-medium text-slate-700">Nomor Rekening</label>
                        <input
                            id="account_number"
                            name="account_number"
                            type="text"
                            value="{{ old('account_number', (string) ($bankProfile?->account_number ?? '')) }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="Contoh: 1234567890"
                        >
                    </div>

                    <div>
                        <label for="account_holder" class="mb-1 block text-sm font-medium text-slate-700">Nama Pemilik Rekening</label>
                        <input
                            id="account_holder"
                            name="account_holder"
                            type="text"
                            value="{{ old('account_holder', (string) ($bankProfile?->account_holder ?? '')) }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="Sesuai buku tabungan"
                        >
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-1">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ ($isBankProfileComplete ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ ($isBankProfileComplete ?? false) ? 'Rekening Lengkap' : 'Rekening Belum Lengkap' }}
                        </span>
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Simpan Rekening
                        </button>
                    </div>
                </form>
            </article>

            <article class="surface-card p-6">
                <div class="mb-4">
                    <h3 class="text-base font-semibold text-slate-900">Ajukan Withdraw</h3>
                    <p class="text-sm text-slate-600">Request akan masuk antrean verifikasi admin finance.</p>
                </div>

                <form method="POST" action="{{ route('wallet.withdraw.request') }}" class="space-y-3">
                    @csrf

                    <div>
                        <label for="withdraw_amount" class="mb-1 block text-sm font-medium text-slate-700">Nominal Withdraw</label>
                        <input
                            id="withdraw_amount"
                            name="amount"
                            type="number"
                            min="1"
                            step="100"
                            value="{{ old('amount') }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="Contoh: 50000"
                        >
                    </div>

                    <p class="text-xs text-slate-500">
                        Saldo tersedia saat ini:
                        <span class="font-semibold text-slate-700">Rp{{ number_format((float) ($walletSummary['available_balance'] ?? 0), 0, ',', '.') }}</span>
                    </p>

                    <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Kirim Permintaan Withdraw
                    </button>
                </form>
            </article>
        </section>

        <section class="surface-card p-6">
            <h3 class="text-base font-semibold text-slate-900">Riwayat Permintaan Withdraw</h3>
            @if($withdrawRequests->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Belum ada request withdraw.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4">ID</th>
                                <th class="py-2 pr-4">Nominal</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Rekening</th>
                                <th class="py-2 pr-4">Ref Transfer</th>
                                <th class="py-2">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($withdrawRequests as $row)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">#{{ $row->id }}</td>
                                    <td class="py-3 pr-4 font-semibold text-slate-900">Rp{{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                                    <td class="py-3 pr-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{
                                            $row->status === 'paid' ? 'bg-emerald-100 text-emerald-700' :
                                            ($row->status === 'rejected' ? 'bg-rose-100 text-rose-700' :
                                            ($row->status === 'approved' ? 'bg-cyan-100 text-cyan-700' : 'bg-amber-100 text-amber-700'))
                                        }}">
                                            {{ strtoupper((string) $row->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-700">
                                        {{ $row->bank_name ?: '-' }} / {{ $row->account_number ?: '-' }}<br>
                                        <span class="text-xs text-slate-500">{{ $row->account_holder ?: '-' }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">{{ $row->transfer_reference ?: '-' }}</td>
                                    <td class="py-3 text-slate-600">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-xl border bg-white p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Rekap Komisi Affiliate</h3>
                    <p class="text-sm text-slate-600">Lihat performa affiliate dan kontribusi produk.</p>
                </div>
                <a href="{{ route('mitra.affiliates') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Buka Data Affiliate
                </a>
            </div>
        </section>

        <section class="surface-card p-6">
            <h3 class="text-base font-semibold text-slate-900">Riwayat Transaksi Wallet Mitra</h3>
            @if($recentTransactions->isEmpty())
                <p class="mt-3 text-sm text-slate-600">Belum ada transaksi wallet.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4">ID</th>
                                <th class="py-2 pr-4">Jenis</th>
                                <th class="py-2 pr-4">Nominal</th>
                                <th class="py-2 pr-4">Referensi Order</th>
                                <th class="py-2 pr-4">Catatan</th>
                                <th class="py-2">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentTransactions as $row)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">#{{ $row->id }}</td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ str_replace('_', ' ', (string) $row->transaction_type) }}</td>
                                    <td class="py-3 pr-4 font-semibold {{ (float) $row->amount >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                        Rp{{ number_format((float) $row->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->reference_order_id ? '#' . $row->reference_order_id : '-' }}</td>
                                    <td class="py-3 pr-4 text-slate-600">{{ $row->description ?: '-' }}</td>
                                    <td class="py-3 text-slate-600">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-mitra-layout>
