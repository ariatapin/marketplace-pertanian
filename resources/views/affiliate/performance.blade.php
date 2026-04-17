<x-affiliate-layout>
    <x-slot name="header">Performa Saya</x-slot>

    @php
        $seriesRows = collect($performanceSeries['rows'] ?? collect());
        $clicksCount = (int) ($trackingSummary['clicks'] ?? 0);
        $checkoutCreatedCount = (int) ($trackingSummary['checkout_created'] ?? 0);
        $completedOrdersCount = (int) ($trackingSummary['completed_orders'] ?? 0);
        $conversionCompletedPercent = (float) ($trackingSummary['conversion_completed_percent'] ?? 0);

        $currentMonthPrefix = now()->format('Y-m');
        $trendRows = $seriesRows
            ->filter(function ($row) use ($currentMonthPrefix): bool {
                $bucketKey = (string) ($row['bucket_key'] ?? '');
                return $bucketKey !== '' && str_starts_with($bucketKey, $currentMonthPrefix);
            })
            ->values();
        $trendMiniRows = $trendRows->sortBy('bucket_key')->take(-7)->values();

        $monthCheckoutTotal = (int) $trendRows->sum(function ($row): int {
            return (int) ($row['checkout'] ?? 0);
        });
        $monthCompletedTotal = (int) $trendRows->sum(function ($row): int {
            return (int) ($row['completed'] ?? 0);
        });
        $monthConversion = $monthCheckoutTotal > 0
            ? round(($monthCompletedTotal / $monthCheckoutTotal) * 100, 2)
            : 0.0;

        $topProductRows = collect($topSellingProducts ?? collect())
            ->take(4)
            ->values();
    @endphp

    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        <section class="surface-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Analitik Affiliate</p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-900">Performa Link & Produk</h2>
                    <p class="mt-1 text-sm text-slate-600">Gunakan filter periode untuk melihat performa yang paling relevan.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2">
                    <a href="{{ route('affiliate.performance', ['period' => 'all']) }}"
                       class="rounded-lg border px-3 py-1.5 text-xs font-semibold {{ ($periodFilter ?? 'all') === 'all' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                        Semua
                    </a>
                    <a href="{{ route('affiliate.performance', ['period' => 'weekly']) }}"
                       class="rounded-lg border px-3 py-1.5 text-xs font-semibold {{ ($periodFilter ?? 'all') === 'weekly' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                        Mingguan
                    </a>
                    <a href="{{ route('affiliate.performance', ['period' => 'monthly']) }}"
                       class="rounded-lg border px-3 py-1.5 text-xs font-semibold {{ ($periodFilter ?? 'all') === 'monthly' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}">
                        Bulanan
                    </a>
                </div>
            </div>
            <div class="mt-3 text-xs text-slate-500">Periode aktif: <span class="font-semibold text-slate-700">{{ $periodLabel ?? '-' }}</span></div>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-4">
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Klik Link</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($clicksCount) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Checkout</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($checkoutCreatedCount) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Order Selesai</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($completedOrdersCount) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Konversi Selesai</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ number_format($conversionCompletedPercent, 2, ',', '.') }}%</p>
                </article>
            </div>
        </section>

        <section class="surface-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Trend Transaksi Bulan Ini</h3>
                    <p class="mt-1 text-sm text-slate-600">Hanya menampilkan data pada bulan berjalan.</p>
                </div>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ now()->translatedFormat('F Y') }}</span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Checkout Bulan Ini</p>
                    <p class="mt-1 text-xl font-bold text-indigo-700">{{ number_format($monthCheckoutTotal) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Selesai Bulan Ini</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700">{{ number_format($monthCompletedTotal) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Rasio Selesai</p>
                    <p class="mt-1 text-xl font-bold text-amber-700">{{ number_format($monthConversion, 2, ',', '.') }}%</p>
                </article>
            </div>

            @if($trendMiniRows->isEmpty())
                <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center text-sm text-slate-600">
                    Belum ada data transaksi pada bulan ini.
                </div>
            @else
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-2">Tanggal</th>
                                <th class="px-3 py-2">Checkout</th>
                                <th class="px-3 py-2">Selesai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                            @foreach($trendMiniRows as $row)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $row['label'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ number_format((int) ($row['checkout'] ?? 0)) }}</td>
                                    <td class="px-3 py-2">{{ number_format((int) ($row['completed'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="surface-card p-5">
            <h3 class="text-base font-semibold text-slate-900">Produk Paling Laku</h3>
            <p class="mt-1 text-sm text-slate-600">Top produk dari link affiliate Anda pada periode aktif.</p>

            @if($topProductRows->isEmpty())
                <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center text-sm text-slate-600">
                    Belum ada data produk terjual untuk periode ini.
                </div>
            @else
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach($topProductRows as $row)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <p class="text-sm font-semibold text-slate-900">{{ $row->product_name }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-1">{{ number_format((int) ($row->total_qty ?? 0)) }} unit</span>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-1">{{ number_format((int) ($row->total_orders ?? 0)) }} order</span>
                                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">Rp{{ number_format((float) ($row->total_commission ?? 0), 0, ',', '.') }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-affiliate-layout>
