<x-admin-layout>
    <x-slot name="header">
        {{ __('Modul Keuangan') }}
    </x-slot>

    <div data-testid="admin-finance-page" class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-xl border bg-white p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Ringkasan Keuangan</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Pantau saldo tersedia, laba kotor/bersih, arus transaksi, dan antrian withdraw.
                        Periode aktif: {{ data_get($periodMeta ?? [], 'label', '-') }} ({{ data_get($periodMeta ?? [], 'range_label', '-') }}).
                    </p>
                </div>
                <form method="GET" action="{{ route('admin.modules.finance') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="section" value="{{ $filters['section'] }}">
                    <select name="period" class="rounded-md border-slate-300 text-sm">
                        <option value="daily" @selected(($filters['period'] ?? 'daily') === 'daily')>Harian</option>
                        <option value="weekly" @selected(($filters['period'] ?? 'daily') === 'weekly')>Mingguan</option>
                        <option value="monthly" @selected(($filters['period'] ?? 'daily') === 'monthly')>Bulanan</option>
                    </select>
                    <select name="window" class="rounded-md border-slate-300 text-sm">
                        @foreach(data_get($periodMeta ?? [], 'window_options', [7, 14, 30]) as $windowOption)
                            <option value="{{ $windowOption }}" @selected((int) ($filters['window'] ?? 0) === (int) $windowOption)>
                                {{ $windowOption }} {{ ($filters['period'] ?? 'daily') === 'daily' ? 'hari' : (($filters['period'] ?? 'daily') === 'weekly' ? 'minggu' : 'bulan') }}
                            </option>
                        @endforeach
                    </select>
                    <select name="chart_mode" class="rounded-md border-slate-300 text-sm">
                        <option value="nominal" @selected(($filters['chart_mode'] ?? 'nominal') === 'nominal')>Grafik Nominal</option>
                        <option value="count" @selected(($filters['chart_mode'] ?? 'nominal') === 'count')>Grafik Jumlah Transaksi</option>
                    </select>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Terapkan</button>
                </form>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Saldo Admin Tersedia</p>
                <p class="mt-1 text-xl font-bold text-emerald-700">{{ $currency($summary['admin_wallet_balance'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-slate-600">Total saldo real-time yang siap digunakan sebagai sumber pencairan.</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Laba Kotor (Periode)</p>
                <p class="mt-1 text-xl font-bold text-emerald-700">{{ $currency($summary['gross_profit'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-slate-600">Akumulasi pemasukan bruto pada periode filter.</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Laba Bersih (Periode)</p>
                <p class="mt-1 text-xl font-bold {{ (float) ($summary['net_wallet_flow'] ?? 0) >= 0 ? 'text-indigo-700' : 'text-rose-700' }}">
                    {{ $currency($summary['net_wallet_flow'] ?? 0) }}
                </p>
                <p class="mt-1 text-xs text-slate-600">Laba kotor dikurangi total transaksi keluar pada periode filter.</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Transaksi Masuk (Periode)</p>
                <p class="mt-1 text-xl font-bold text-emerald-700">{{ number_format((int) ($summary['transaction_in_count'] ?? 0)) }} trx</p>
                <p class="mt-1 text-xs text-slate-600">Nominal: {{ $currency($summary['total_wallet_in'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Transaksi Keluar (Periode)</p>
                <p class="mt-1 text-xl font-bold text-rose-700">{{ number_format((int) ($summary['transaction_out_count'] ?? 0)) }} trx</p>
                <p class="mt-1 text-xs text-slate-600">Nominal: {{ $currency($summary['total_wallet_out'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Antrian Withdraw Aktif</p>
                <p class="mt-1 text-xl font-bold text-amber-700">{{ number_format((int) ($summary['pending_withdraw_count'] ?? 0)) }}</p>
                <p class="mt-1 text-xs text-slate-600">Aktif: {{ $currency($summary['pending_withdraw_amount'] ?? 0) }}</p>
                <p class="text-xs text-slate-500">
                    Periode: {{ number_format((int) ($summary['period_pending_withdraw_count'] ?? 0)) }} request
                    ({{ $currency($summary['period_pending_withdraw_amount'] ?? 0) }})
                </p>
            </div>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <h3 class="text-base font-semibold text-slate-900">Aksi Modul Keuangan</h3>
            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                <a href="{{ route('admin.modules.finance', ['section' => 'overview', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) }}"
                   class="rounded-lg border px-4 py-3 text-sm font-semibold {{ ($filters['section'] ?? 'overview') === 'overview' ? 'border-slate-800 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Dashboard Keuangan
                </a>
                <a href="{{ route('admin.modules.finance', ['section' => 'affiliate', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) }}"
                   class="rounded-lg border px-4 py-3 text-sm font-semibold {{ ($filters['section'] ?? '') === 'affiliate' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Manajemen Komisi Affiliate
                </a>
                <a href="{{ route('admin.modules.finance', ['section' => 'withdraw', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) }}"
                   class="rounded-lg border px-4 py-3 text-sm font-semibold {{ ($filters['section'] ?? '') === 'withdraw' ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Pencairan
                </a>
                <a href="{{ route('admin.modules.finance', ['section' => 'transfer', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) }}"
                   class="rounded-lg border px-4 py-3 text-sm font-semibold {{ ($filters['section'] ?? '') === 'transfer' ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Monitoring Transfer
                </a>
            </div>
        </section>

        @if(($filters['section'] ?? 'overview') === 'overview')
            <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="rounded-xl border bg-white p-6">
                    <h3 class="text-base font-semibold text-slate-900">
                        Grafik Arus Dana ({{ data_get($periodMeta ?? [], 'label', ucfirst($filters['period'] ?? 'daily')) }})
                    </h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Mode: {{ ($filters['chart_mode'] ?? 'nominal') === 'count' ? 'Jumlah transaksi' : 'Nominal rupiah' }}
                    </p>
                    <div class="mt-4">
                        @if($incomeChartRows->isEmpty())
                            <p class="text-sm text-slate-600">Belum ada data arus dana untuk periode ini.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($incomeChartRows as $row)
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                        <div class="mb-2 flex items-center justify-between text-xs text-slate-600">
                                            <span class="font-semibold text-slate-700">{{ $row['bucket'] }}</span>
                                            @if(($filters['chart_mode'] ?? 'nominal') === 'count')
                                                <span>Masuk {{ number_format((int) ($row['count_in'] ?? 0)) }} / Keluar {{ number_format((int) ($row['count_out'] ?? 0)) }}</span>
                                            @else
                                                <span>Masuk {{ $currency($row['total_in'] ?? 0) }} / Keluar {{ $currency($row['total_out'] ?? 0) }}</span>
                                            @endif
                                        </div>

                                        @if(($filters['chart_mode'] ?? 'nominal') === 'count')
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="mb-1 flex justify-between text-[11px] text-slate-600">
                                                        <span>Transaksi Masuk</span>
                                                        <span>{{ number_format((int) ($row['count_in'] ?? 0)) }} trx</span>
                                                    </div>
                                                    <div class="h-2 rounded bg-slate-200">
                                                        <div class="h-2 rounded bg-emerald-500" style="width: {{ (float) ($row['width_count_in'] ?? 0) }}%"></div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="mb-1 flex justify-between text-[11px] text-slate-600">
                                                        <span>Transaksi Keluar</span>
                                                        <span>{{ number_format((int) ($row['count_out'] ?? 0)) }} trx</span>
                                                    </div>
                                                    <div class="h-2 rounded bg-slate-200">
                                                        <div class="h-2 rounded bg-rose-500" style="width: {{ (float) ($row['width_count_out'] ?? 0) }}%"></div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="mb-1 flex justify-between text-[11px] text-slate-600">
                                                        <span>Selisih</span>
                                                        <span>{{ number_format((int) ($row['delta_count'] ?? 0)) }} trx</span>
                                                    </div>
                                                    <div class="h-2 rounded bg-slate-200">
                                                        <div class="h-2 rounded {{ (int) ($row['delta_count'] ?? 0) >= 0 ? 'bg-sky-500' : 'bg-amber-500' }}" style="width: {{ (float) ($row['width_delta_count'] ?? 0) }}%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="space-y-2">
                                                <div>
                                                    <div class="mb-1 flex justify-between text-[11px] text-slate-600">
                                                        <span>Pemasukan</span>
                                                        <span>{{ $currency($row['total_in'] ?? 0) }}</span>
                                                    </div>
                                                    <div class="h-2 rounded bg-slate-200">
                                                        <div class="h-2 rounded bg-emerald-500" style="width: {{ (float) ($row['width_in'] ?? 0) }}%"></div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="mb-1 flex justify-between text-[11px] text-slate-600">
                                                        <span>Pengeluaran</span>
                                                        <span>{{ $currency($row['total_out'] ?? 0) }}</span>
                                                    </div>
                                                    <div class="h-2 rounded bg-slate-200">
                                                        <div class="h-2 rounded bg-rose-500" style="width: {{ (float) ($row['width_out'] ?? 0) }}%"></div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="mb-1 flex justify-between text-[11px] text-slate-600">
                                                        <span>Net</span>
                                                        <span>{{ $currency($row['net'] ?? 0) }}</span>
                                                    </div>
                                                    <div class="h-2 rounded bg-slate-200">
                                                        <div class="h-2 rounded {{ (float) ($row['net'] ?? 0) >= 0 ? 'bg-indigo-500' : 'bg-amber-500' }}" style="width: {{ (float) ($row['width_net'] ?? 0) }}%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6">
                    <h3 class="text-base font-semibold text-slate-900">Perbandingan Ringkas</h3>
                    <div class="mt-4 space-y-3">
                        @foreach($comparisonBars as $item)
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs text-slate-600">
                                    <span>{{ $item['label'] }}</span>
                                    @if(($item['unit'] ?? 'currency') === 'trx')
                                        <span>{{ number_format((int) ($item['amount'] ?? 0)) }} trx</span>
                                    @else
                                        <span>{{ $currency($item['amount']) }}</span>
                                    @endif
                                </div>
                                <div class="h-2 rounded bg-slate-100">
                                    <div class="h-2 rounded {{ $item['color'] }}" style="width: {{ (float) ($item['width_percent'] ?? 0) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if(($filters['section'] ?? '') === 'affiliate')
            <section class="rounded-xl border bg-white p-6">
                <h3 class="text-base font-semibold text-slate-900">Manajemen Komisi Affiliate</h3>
                <p class="mt-2 text-sm text-slate-600">Admin menetapkan batas komisi global per produk. Mitra wajib memilih komisi affiliate di dalam rentang ini.</p>

                <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                    <p class="text-sm font-semibold text-indigo-900">Aturan Batas Komisi Affiliate</p>
                    <p class="mt-1 text-xs text-indigo-700">Pengaturan rentang komisi dipindahkan ke Marketplace > Ringkasan Marketplace > Aturan Lock Affiliate.</p>
                    <a href="{{ route('admin.modules.marketplace', ['section' => 'overview']) }}#affiliate-lock-policy" class="mt-3 inline-flex rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800">
                        Buka Pengaturan Komisi di Marketplace
                    </a>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4 xl:grid-cols-8">
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Total Produk</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format((int) ($affiliateCommissionSummary['total_products'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Produk Affiliate Aktif</p>
                        <p class="mt-1 text-lg font-bold text-emerald-700">{{ number_format((int) ($affiliateCommissionSummary['affiliate_enabled_products'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Batas Min Admin</p>
                        <p class="mt-1 text-lg font-bold text-indigo-700">{{ number_format((float) ($affiliateCommissionSummary['configured_min_percent'] ?? 0), 2, ',', '.') }}%</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Batas Max Admin</p>
                        <p class="mt-1 text-lg font-bold text-indigo-700">{{ number_format((float) ($affiliateCommissionSummary['configured_max_percent'] ?? 100), 2, ',', '.') }}%</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Komisi Min Produk</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format((float) ($affiliateCommissionSummary['min_commission_percent'] ?? 0), 2, ',', '.') }}%</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Komisi Max Produk</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format((float) ($affiliateCommissionSummary['max_commission_percent'] ?? 0), 2, ',', '.') }}%</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Rata-rata Komisi</p>
                        <p class="mt-1 text-lg font-bold text-indigo-700">{{ number_format((float) ($affiliateCommissionSummary['avg_commission_percent'] ?? 0), 2, ',', '.') }}%</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Di Luar Batas</p>
                        <p class="mt-1 text-lg font-bold text-rose-700">{{ number_format((int) ($affiliateCommissionSummary['out_of_range_products'] ?? 0)) }}</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    @if(($affiliateCommissionRows ?? collect())->isEmpty())
                        <p class="text-sm text-slate-600">Belum ada produk mitra untuk dipantau komisi affiliate-nya.</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-slate-600">
                                    <th class="py-2 pr-4">Produk</th>
                                    <th class="py-2 pr-4">Mitra</th>
                                    <th class="py-2 pr-4">Harga</th>
                                    <th class="py-2 pr-4">Stok</th>
                                    <th class="py-2 pr-4">Status Jual</th>
                                    <th class="py-2 pr-4">Affiliate</th>
                                    <th class="py-2">Komisi</th>
                                    <th class="py-2">Validasi Range</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($affiliateCommissionRows as $row)
                                    @php
                                        $commissionValue = (float) ($row->affiliate_commission ?? 0);
                                        $isOutOfRange = (bool) $row->is_affiliate_enabled
                                            && (
                                                $commissionValue < (float) ($affiliateCommissionRange['min'] ?? 0)
                                                || $commissionValue > (float) ($affiliateCommissionRange['max'] ?? 100)
                                            );
                                    @endphp
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4 font-medium text-slate-900">{{ $row->name }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ $row->mitra_name ?: '-' }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ $currency($row->price) }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $row->stock_qty) }}</td>
                                        <td class="py-3 pr-4">
                                            @php
                                                $isSellActive = (bool) ($row->is_active ?? true);
                                            @endphp
                                            <span class="rounded px-2 py-1 text-xs font-semibold {{ $isSellActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                                {{ $isSellActive ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <span class="rounded px-2 py-1 text-xs font-semibold {{ (bool) $row->is_affiliate_enabled ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700' }}">
                                                {{ (bool) $row->is_affiliate_enabled ? 'Diaktifkan' : 'Off' }}
                                            </span>
                                        </td>
                                        <td class="py-3 font-semibold text-slate-900">
                                            {{ number_format((float) $row->affiliate_commission, 2, ',', '.') }}%
                                        </td>
                                        <td class="py-3">
                                            @if($isOutOfRange)
                                                <span class="rounded px-2 py-1 text-xs font-semibold bg-rose-100 text-rose-700">Di luar batas</span>
                                            @else
                                                <span class="rounded px-2 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">Sesuai range</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
        @endif

        @if(($filters['section'] ?? '') === 'transfer')
            <section class="rounded-xl border bg-white p-6 space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Monitoring Pembayaran Transfer & E-Wallet</h3>
                    <p class="mt-1 text-sm text-slate-600">Monitor bukti pembayaran, status verifikasi seller, dan progres order bank/e-wallet.</p>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Menunggu Verifikasi</p>
                        <p class="text-lg font-bold text-amber-700">{{ number_format((int) ($transferSummary['waiting_verification'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Terverifikasi</p>
                        <p class="text-lg font-bold text-emerald-700">{{ number_format((int) ($transferSummary['verified'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Dengan Bukti</p>
                        <p class="text-lg font-bold text-sky-700">{{ number_format((int) ($transferSummary['with_proof'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Belum Upload Bukti</p>
                        <p class="text-lg font-bold text-slate-700">{{ number_format((int) ($transferSummary['without_proof'] ?? 0)) }}</p>
                    </div>
                </div>

                <form method="GET" action="{{ route('admin.modules.finance') }}" class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <input type="hidden" name="section" value="transfer">
                    <input type="hidden" name="period" value="{{ $filters['period'] }}">
                    <input type="hidden" name="window" value="{{ $filters['window'] }}">
                    <input type="hidden" name="chart_mode" value="{{ $filters['chart_mode'] }}">
                    <div>
                        <label for="transfer_state" class="mb-1 block text-sm font-medium text-slate-700">Status Transfer</label>
                        <select id="transfer_state" name="transfer_state" class="w-full rounded-md border-slate-300 text-sm">
                            <option value="">Semua</option>
                            <option value="waiting" @selected(($filters['transfer_state'] ?? '') === 'waiting')>Menunggu Verifikasi</option>
                            <option value="verified" @selected(($filters['transfer_state'] ?? '') === 'verified')>Terverifikasi</option>
                            <option value="no_proof" @selected(($filters['transfer_state'] ?? '') === 'no_proof')>Belum Upload Bukti</option>
                        </select>
                    </div>
                    <div>
                        <label for="transfer_method" class="mb-1 block text-sm font-medium text-slate-700">Metode</label>
                        <select id="transfer_method" name="transfer_method" class="w-full rounded-md border-slate-300 text-sm">
                            <option value="">Semua</option>
                            @foreach($transferMethodOptions as $method)
                                <option value="{{ $method['method'] }}" @selected(($filters['transfer_method'] ?? '') === $method['method'])>{{ $method['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="transfer_q" class="mb-1 block text-sm font-medium text-slate-700">Cari Order / Buyer / Seller</label>
                        <input id="transfer_q" name="transfer_q" type="text" value="{{ $filters['transfer_q'] ?? '' }}" placeholder="contoh: 101 atau buyer@email.com" class="w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                        <a href="{{ route('admin.modules.finance', ['section' => 'transfer', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) }}" class="rounded-md border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    @if($transferRows->isEmpty())
                        <p class="text-sm text-slate-600">Belum ada order transfer untuk filter saat ini.</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-slate-600">
                                    <th class="py-2 pr-4">Order</th>
                                    <th class="py-2 pr-4">Metode</th>
                                    <th class="py-2 pr-4">Buyer</th>
                                    <th class="py-2 pr-4">Seller</th>
                                    <th class="py-2 pr-4">Nominal</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Bukti</th>
                                    <th class="py-2">Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transferRows as $row)
                                    @php
                                        $isWaiting = $row->payment_status === 'unpaid' && $row->order_status === 'pending_payment' && !empty($row->payment_proof_url);
                                        $isVerified = $row->payment_status === 'paid';
                                        $statusLabel = $isWaiting ? 'Menunggu Verifikasi' : ($isVerified ? 'Terverifikasi' : 'Belum Bayar');
                                    @endphp
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4 font-semibold text-slate-900">#{{ $row->id }}</td>
                                        <td class="py-3 pr-4">
                                            <span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                                {{ $paymentMethodMap[$row->payment_method] ?? strtoupper(str_replace('_', ' ', (string) $row->payment_method)) }}
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <p class="font-medium text-slate-900">{{ $row->buyer_name }}</p>
                                            <p class="text-xs text-slate-600">{{ $row->buyer_email }}</p>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <p class="font-medium text-slate-900">{{ $row->seller_name }}</p>
                                            <p class="text-xs text-slate-600">{{ $row->seller_email }}</p>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <p class="font-semibold text-slate-900">{{ $currency($row->total_amount) }}</p>
                                            <p class="text-xs text-slate-600">Transfer: {{ $row->paid_amount !== null ? $currency($row->paid_amount) : '-' }}</p>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <span class="rounded px-2 py-1 text-xs font-semibold {{ $isWaiting ? 'bg-amber-100 text-amber-800' : ($isVerified ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700') }}">
                                                {{ $statusLabel }}
                                            </span>
                                            <p class="mt-1 text-xs text-slate-500 uppercase">{{ $row->payment_status }} / {{ $row->order_status }}</p>
                                        </td>
                                        <td class="py-3 pr-4">
                                            @if($row->payment_proof_url)
                                                <a href="{{ asset($row->payment_proof_url) }}" target="_blank" class="text-xs font-semibold text-indigo-700 hover:text-indigo-900">
                                                    Lihat Bukti
                                                </a>
                                            @else
                                                <span class="text-xs text-slate-500">Belum ada</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-slate-600">
                                            {{ $row->payment_submitted_at ? \Illuminate\Support\Carbon::parse($row->payment_submitted_at)->format('d M Y H:i') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-4">
                            {{ $transferRows->links() }}
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if(($filters['section'] ?? '') === 'withdraw' || ($filters['role'] ?? '') !== '')
            <section data-testid="admin-finance-withdraw" class="rounded-xl border bg-white p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Pencairan Per Role</h3>
                        <p class="mt-1 text-sm font-medium text-slate-700">Antrian Withdraw</p>
                        <p class="mt-1 text-sm text-slate-600">Tombol role hanya tampil jika masih ada antrian pencairan aktif.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($roleWithdrawSummary as $roleRow)
                            <a
                                href="{{ route('admin.modules.finance.withdraw.role', ['role' => $roleRow['role'], 'section' => 'withdraw', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) }}"
                                class="rounded-md border px-3 py-2 text-xs font-semibold {{ ($filters['role'] ?? '') === $roleRow['role'] ? 'border-amber-500 bg-amber-100 text-amber-800' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}"
                            >
                                {{ $roleRow['label'] }} ({{ $roleRow['total_request'] }})
                            </a>
                        @endforeach
                        @if(($filters['role'] ?? '') !== '')
                            <a href="{{ route('admin.modules.finance', ['section' => 'withdraw', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) }}" class="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Semua Role
                            </a>
                        @endif
                    </div>
                </div>

                <form method="GET" action="{{ ($filters['role'] ?? '') !== '' ? route('admin.modules.finance.withdraw.role', ['role' => $filters['role']]) : route('admin.modules.finance') }}"
                      class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                    <input type="hidden" name="section" value="withdraw">
                    <input type="hidden" name="period" value="{{ $filters['period'] }}">
                    <input type="hidden" name="window" value="{{ $filters['window'] }}">
                    <input type="hidden" name="chart_mode" value="{{ $filters['chart_mode'] }}">
                    <div>
                        <label for="withdraw_status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                        <select id="withdraw_status" name="withdraw_status" class="w-full rounded-md border-slate-300 text-sm">
                            <option value="">Semua</option>
                            <option value="pending" @selected(($filters['withdraw_status'] ?? '') === 'pending')>Pending</option>
                            <option value="approved" @selected(($filters['withdraw_status'] ?? '') === 'approved')>Approved</option>
                            <option value="paid" @selected(($filters['withdraw_status'] ?? '') === 'paid')>Paid</option>
                            <option value="rejected" @selected(($filters['withdraw_status'] ?? '') === 'rejected')>Rejected</option>
                            <option value="cancelled" @selected(($filters['withdraw_status'] ?? '') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="q" class="mb-1 block text-sm font-medium text-slate-700">Cari User / Email / ID Withdraw</label>
                        <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="contoh: 18 atau user@email.com" class="w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                        <a href="{{ ($filters['role'] ?? '') !== '' ? route('admin.modules.finance.withdraw.role', ['role' => $filters['role'], 'section' => 'withdraw', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) : route('admin.modules.finance', ['section' => 'withdraw', 'period' => $filters['period'], 'window' => $filters['window'], 'chart_mode' => $filters['chart_mode']]) }}"
                           class="rounded-md border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Reset
                        </a>
                    </div>
                </form>

                <div class="mt-4 overflow-x-auto">
                    @if($withdrawRows->isEmpty())
                        <p class="text-sm text-slate-600">Tidak ada antrian pencairan untuk filter saat ini.</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-slate-600">
                                    <th class="py-2 pr-4">ID</th>
                                    <th class="py-2 pr-4">User</th>
                                    <th class="py-2 pr-4">Role</th>
                                    <th class="py-2 pr-4">Nominal</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Diproses Oleh</th>
                                    <th class="py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($withdrawRows as $row)
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4 font-semibold text-slate-900">#{{ $row->id }}</td>
                                        <td class="py-3 pr-4">
                                            <p class="font-medium text-slate-900">{{ $row->user_name }}</p>
                                            <p class="text-xs text-slate-600">{{ $row->user_email }}</p>
                                            <p class="text-xs text-slate-500">Rekening: {{ $row->account_holder ?: '-' }}</p>
                                        </td>
                                        <td class="py-3 pr-4 uppercase text-slate-700">{{ str_replace('_', ' ', $row->user_role) }}</td>
                                        <td class="py-3 pr-4">{{ $currency($row->amount) }}</td>
                                        <td class="py-3 pr-4 uppercase">{{ $row->status }}</td>
                                        <td class="py-3 pr-4 text-slate-600">
                                            <p>{{ $row->processed_by_name ?: '-' }}</p>
                                            @if($row->status === 'paid')
                                                <p class="text-xs text-slate-500">Paid: {{ $row->paid_by_name ?: '-' }}</p>
                                                <p class="text-xs text-slate-500">
                                                    {{ $row->paid_at ? \Illuminate\Support\Carbon::parse($row->paid_at)->format('d M Y H:i') : '-' }}
                                                </p>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            @if($row->status === 'pending')
                                                <div class="flex flex-wrap gap-2">
                                                    <form method="POST" action="{{ route('admin.withdraws.approve', ['withdrawId' => $row->id]) }}">
                                                        @csrf
                                                        <button type="submit" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.withdraws.reject', ['withdrawId' => $row->id]) }}">
                                                        @csrf
                                                        <button type="submit" class="rounded bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Reject</button>
                                                    </form>
                                                </div>
                                            @elseif($row->status === 'approved')
                                                <form method="POST" action="{{ route('admin.withdraws.paid', ['withdrawId' => $row->id]) }}" class="space-y-2">
                                                    @csrf
                                                    <input
                                                        type="text"
                                                        name="transfer_reference"
                                                        placeholder="Ref transfer (wajib)"
                                                        class="w-full rounded border border-slate-300 px-2 py-1 text-xs"
                                                        required
                                                    >
                                                    <input
                                                        type="text"
                                                        name="transfer_proof_url"
                                                        placeholder="URL bukti transfer (opsional)"
                                                        class="w-full rounded border border-slate-300 px-2 py-1 text-xs"
                                                    >
                                                    <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Mark Paid</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-slate-500">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-4">
                            {{ $withdrawRows->links() }}
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>
</x-admin-layout>
