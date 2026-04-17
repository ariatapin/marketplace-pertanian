<x-admin-layout>
    <x-slot name="header">
        {{ __('Dashboard Admin') }}
    </x-slot>

    <div class="py-4">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <section class="surface-card overflow-hidden">
                <div class="admin-dash-hero-panel rounded-2xl px-6 py-6 sm:px-7">
                    <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.24em] text-slate-300">Admin Operations</p>
                            <h2 class="mt-2 text-2xl font-semibold tracking-tight text-white">Selamat datang, {{ $loggedInUserName }}</h2>
                            <p class="mt-2 max-w-2xl text-sm text-slate-200">
                                Fokuskan eksekusi hari ini pada pengajuan mode consumer, order mitra aktif, dan monitoring pengadaan mitra.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full border border-slate-600 bg-slate-900/70 px-3 py-1.5 text-slate-100">Tanggal: {{ $todayLabel }}</span>
                                <span class="rounded-full border border-slate-600 bg-slate-900/70 px-3 py-1.5 text-slate-100">Total indikator: {{ number_format($adminChartTotal) }}</span>
                            </div>
                        </div>
                        <div class="min-w-[260px] rounded-xl border border-emerald-300/35 bg-emerald-500/10 px-4 py-3 text-right">
                            <p class="text-[11px] uppercase tracking-[0.2em] text-emerald-100">Total Uang Admin (Demo Aktif)</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-100">
                                Rp{{ number_format((float) ($demoAdminBalance ?? 0), 0, ',', '.') }}
                            </p>
                            <p class="mt-1 text-xs text-emerald-100/90">Saldo ini dipakai sebagai sumber pencairan withdraw.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($metricCards as $card)
                    <div class="admin-kpi-card {{ $card['accent_class'] }} surface-card p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold {{ $card['value_class'] }}">{{ number_format($card['value']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $card['helper'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="surface-card p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold text-slate-900">Grafik Aktivitas Admin</h3>
                    <span class="text-xs text-slate-500">Snapshot metrik prioritas untuk keputusan operasional</span>
                </div>
                <div class="admin-chart-layout mt-4">
                    <div class="admin-chart-canvas">
                        <div class="admin-bar-grid">
                            @foreach($adminChartBars as $bar)
                                <div class="admin-bar-col">
                                    <div class="admin-bar-track">
                                        <div class="admin-bar-fill {{ $bar['height_class'] }} {{ $bar['fill_class'] }}"></div>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $bar['short'] }}</p>
                                        <p class="text-base font-bold text-slate-900">{{ number_format($bar['value']) }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $bar['ratio'] }}%</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach($adminChartBars as $bar)
                                <div class="inline-flex items-center gap-2 text-xs text-slate-600">
                                    <span class="h-2 w-2 rounded-full {{ $bar['fill_class'] }}"></span>
                                    <span>{{ $bar['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="admin-priority-card">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Prioritas Tertinggi</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">{{ $topPriority['label'] }}</p>
                        <p class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($topPriority['value']) }}</p>
                        <p class="mt-4 text-sm text-slate-600">
                            Fokuskan eksekusi pada indikator dengan volume tertinggi agar SLA admin tetap terjaga.
                        </p>
                        <div class="mt-4 space-y-2 text-sm">
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span class="text-slate-600">Pending consumer</span>
                                <span class="font-semibold text-slate-900">{{ number_format($totalPendingMode) }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span class="text-slate-600">Order mitra aktif</span>
                                <span class="font-semibold text-slate-900">{{ number_format($totalPendingProcurement) }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <span class="text-slate-600">Order aktif marketplace</span>
                                <span class="font-semibold text-slate-900">{{ number_format($totalActiveOrders) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start">
                <div class="surface-card p-5">
                    <h3 class="text-lg font-semibold text-slate-900">Status Sistem</h3>
                    <div class="mt-3 space-y-3">
                        @foreach($statusRows as $row)
                            <div>
                                <div class="mb-1 flex items-center justify-between text-sm">
                                    <span class="text-slate-600">{{ $row['label'] }}</span>
                                    <span class="admin-status-chip {{ $row['chip_class'] }}">
                                        {{ number_format($row['value']) }}{{ $row['suffix'] }}
                                    </span>
                                </div>
                                <div class="admin-status-track">
                                    <div class="admin-status-fill {{ $row['width_class'] }} {{ $row['fill_class'] }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 border-t border-slate-200 pt-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <h4 class="text-base font-semibold text-slate-900">Pengajuan Aktivasi Consumer Terbaru</h4>
                            <span class="chip-stat border-amber-200 bg-amber-100 text-amber-800">{{ number_format($totalPendingMode) }} pending</span>
                        </div>

                        @if(session('status'))
                            <div class="mt-3 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if($pendingModeRequests->isEmpty())
                            <div class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                                Belum ada pengajuan affiliate atau penjual.
                            </div>
                        @else
                            <div class="mt-3 space-y-2">
                                @foreach($pendingModeRequests as $row)
                                    @php
                                        $requestedModeLabel = str_replace('_', ' ', (string) $row->requested_mode);
                                        $requestedModeClass = (string) $row->requested_mode === 'affiliate'
                                            ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                                    @endphp
                                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">{{ $row->name }}</p>
                                                <p class="text-xs text-slate-600">{{ $row->email }}</p>
                                            </div>
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase {{ $requestedModeClass }}">
                                                {{ $requestedModeLabel }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs text-slate-500">
                                            Masuk: {{ \Illuminate\Support\Carbon::parse($row->updated_at)->format('d M Y H:i') }}
                                        </p>

                                        <details class="mt-2">
                                            <summary class="cursor-pointer text-xs font-semibold text-slate-700 hover:text-slate-900">
                                                Detail
                                            </summary>
                                            <div class="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <div class="grid grid-cols-1 gap-1 text-xs text-slate-700">
                                                    <p><span class="font-semibold">User:</span> {{ $row->name }}</p>
                                                    <p><span class="font-semibold">Email:</span> {{ $row->email }}</p>
                                                    <p><span class="font-semibold">Mode diminta:</span> {{ $requestedModeLabel }}</p>
                                                </div>

                                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                                    <form method="POST" action="{{ route('admin.mode.approve', ['userId' => $row->user_id]) }}">
                                                        @csrf
                                                        <input type="hidden" name="mode" value="{{ $row->requested_mode }}">
                                                        <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700">
                                                            Approve
                                                        </button>
                                                    </form>

                                                    <form method="POST" action="{{ route('admin.mode.reject', ['userId' => $row->user_id]) }}">
                                                        @csrf
                                                        <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="surface-card p-5">
                    @php
                        $weather = array_merge([
                            'sync_status_label' => 'Belum sinkron',
                            'sync_status_class' => 'border-slate-200 bg-slate-100 text-slate-700',
                            'source_label' => 'Belum ada data cuaca',
                            'source_hint' => 'Jalankan Status Cuaca Wilayah untuk sinkron data terbaru.',
                            'openweather_count' => 0,
                            'bmkg_fallback_count' => 0,
                            'city_covered_count' => 0,
                            'stale_count' => 0,
                            'latest_sync_label' => '-',
                            'cache_valid_until_label' => '-',
                            'severity' => ['green' => 0, 'yellow' => 0, 'red' => 0, 'unknown' => 0],
                            'priority_regions' => 0,
                            'active_notices_total' => 0,
                            'active_notices_global' => 0,
                            'active_notices_region' => 0,
                        ], (array) ($weatherDashboard ?? []));
                    @endphp
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">Cuaca Nasional & Lokasi</h3>
                        <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $weather['sync_status_class'] }}">
                            {{ $weather['sync_status_label'] }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">Ringkasan sinkron cuaca, prioritas wilayah, dan status notifikasi cuaca dari modul operasional.</p>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">Wilayah Tersinkron</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ number_format((int) $weather['city_covered_count']) }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">Prioritas Wilayah</p>
                            <p class="mt-1 text-base font-semibold text-rose-700">{{ number_format((int) $weather['priority_regions']) }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">Notifikasi Aktif</p>
                            <p class="mt-1 text-base font-semibold text-emerald-700">{{ number_format((int) $weather['active_notices_total']) }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">Snapshot Expired</p>
                            <p class="mt-1 text-base font-semibold {{ (int) $weather['stale_count'] > 0 ? 'text-amber-700' : 'text-slate-900' }}">
                                {{ number_format((int) $weather['stale_count']) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">Aman {{ number_format((int) data_get($weather, 'severity.green', 0)) }}</span>
                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">Waspada {{ number_format((int) data_get($weather, 'severity.yellow', 0)) }}</span>
                        <span class="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 font-semibold text-rose-700">Bahaya {{ number_format((int) data_get($weather, 'severity.red', 0)) }}</span>
                        <span class="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">Unknown {{ number_format((int) data_get($weather, 'severity.unknown', 0)) }}</span>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">Sumber data</p>
                            <p class="mt-0.5 font-semibold text-slate-900">{{ $weather['source_label'] }}</p>
                            <p class="mt-1 text-[11px] text-slate-600">{{ $weather['source_hint'] }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">OpenWeather / BMKG</p>
                            <p class="mt-0.5 font-semibold text-slate-900">
                                <span class="text-cyan-700">{{ number_format((int) $weather['openweather_count']) }}</span>
                                /
                                <span class="text-indigo-700">{{ number_format((int) $weather['bmkg_fallback_count']) }}</span>
                            </p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">Notifikasi Global / Wilayah</p>
                            <p class="mt-0.5 font-semibold text-slate-900">{{ number_format((int) $weather['active_notices_global']) }} / {{ number_format((int) $weather['active_notices_region']) }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-slate-500">Sinkron / Cache</p>
                            <p class="mt-0.5 font-semibold text-slate-900">{{ $weather['latest_sync_label'] }}</p>
                            <p class="mt-1 text-[11px] text-slate-600">Valid min: {{ $weather['cache_valid_until_label'] }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('admin.modules.weather', ['panel' => 'status']) }}" class="inline-flex rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                            Status Cuaca Wilayah
                        </a>
                        <a href="{{ route('admin.modules.weather', ['panel' => 'notice']) }}" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                            Manajemen Notifikasi Cuaca
                        </a>
                    </div>
                </div>
            </section>

        </div>
    </div>
</x-admin-layout>
