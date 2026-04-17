<x-affiliate-layout>
    <x-slot name="header">Dipasarkan</x-slot>

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

        <section class="surface-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Workspace Affiliate</p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-900">Produk Dipasarkan</h2>
                    <p class="mt-1 text-sm text-slate-600">Daftar produk Mitra yang pernah Anda promosikan, termasuk lock/cool-down dan riwayat pemasaran.</p>
                </div>
                <a href="{{ route('landing', ['source' => 'affiliate']) }}" class="btn-ink">
                    Buka Produk Affiliate
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Produk Dipasarkan</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($marketingSummary['all_promoted_count'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Produk Laku</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format((int) ($marketingSummary['laku_promoted_count'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Hasil Filter</p>
                <p class="mt-2 text-2xl font-bold text-cyan-700">{{ number_format((int) ($marketingSummary['current_filter_count'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Produk Lock</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format((int) ($marketingSummary['cooldown_count'] ?? 0)) }}</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Riwayat Pemasaran</p>
                <p class="mt-2 text-2xl font-bold text-indigo-700">{{ number_format((int) ($marketingSummary['history_count'] ?? 0)) }}</p>
            </article>
        </section>

        <section class="surface-card p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-900">Produk Aktif Dipasarkan</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('affiliate.marketings', ['filter' => 'all']) }}"
                       class="rounded-lg border px-3 py-1.5 text-xs font-semibold {{ ($marketingFilter ?? 'all') === 'all' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                        Semua Produk
                    </a>
                    <a href="{{ route('affiliate.marketings', ['filter' => 'laku']) }}"
                       class="rounded-lg border px-3 py-1.5 text-xs font-semibold {{ ($marketingFilter ?? 'all') === 'laku' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                        Produk Laku
                    </a>
                </div>
            </div>

            @php
                $lockByProductId = collect($cooldownLocks ?? [])
                    ->keyBy(fn ($lock) => (int) ($lock->product_id ?? 0));
            @endphp
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-slate-600">
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3">Mitra</th>
                            <th class="px-4 py-3 text-right">Terjual</th>
                            <th class="px-4 py-3 text-right">Order</th>
                            <th class="px-4 py-3">Terakhir Laku</th>
                            <th class="px-4 py-3 text-right">Komisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($promotedProducts as $row)
                            @php
                                $lockMeta = $lockByProductId->get((int) ($row->id ?? 0));
                            @endphp
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $row->name }}</p>
                                    <p class="text-xs text-slate-500">Rp{{ number_format((float) ($row->price ?? 0), 0, ',', '.') }}</p>
                                    @if($lockMeta)
                                        <ul class="mt-2 space-y-0.5 text-xs text-amber-700">
                                            <li>Mulai lock: {{ \Illuminate\Support\Carbon::parse($lockMeta->start_date)->translatedFormat('d M Y') }}</li>
                                            <li>Sisa waktu: {{ number_format((int) ($lockMeta->days_left ?? 0)) }} hari</li>
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $row->mitra_name }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ number_format((int) ($row->total_sold_qty ?? 0)) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((int) ($row->total_sold_orders ?? 0)) }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if(!empty($row->last_sold_at))
                                        {{ \Illuminate\Support\Carbon::parse($row->last_sold_at)->translatedFormat('d M Y H:i') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-700">Rp{{ number_format((float) ($row->total_commission ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                                    Belum ada produk aktif pada filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Riwayat Pemasaran</h3>
                <p class="mt-1 text-sm text-slate-600">Produk yang pernah Anda pasarkan namun masa pemasarannya telah berakhir/ditutup Mitra.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200 text-left text-slate-600">
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3">Mitra</th>
                            <th class="px-4 py-3">Alasan</th>
                            <th class="px-4 py-3">Akhir Masa Affiliate</th>
                            <th class="px-4 py-3 text-right">Total Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($marketingHistories as $row)
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $row->name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row->mitra_name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row->status_reason }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if(!empty($row->affiliate_expire_date))
                                        {{ \Illuminate\Support\Carbon::parse($row->affiliate_expire_date)->translatedFormat('d M Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format((int) ($row->total_sold_orders ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                                    Belum ada riwayat pemasaran produk.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-affiliate-layout>
