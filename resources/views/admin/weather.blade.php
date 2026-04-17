<x-admin-layout>
    <x-slot name="header">
        {{ __('Modul Cuaca') }}
    </x-slot>

    <div data-testid="admin-weather-page" class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

        @if(! $canFetchFromApi)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                OpenWeather API key belum aktif. Isi <code class="font-semibold">OPENWEATHER_API_KEY</code> agar Status Cuaca Wilayah mengambil data terbaru dari OpenWeather.
            </div>
        @endif

        @if($apiWarnings->isNotEmpty())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $apiWarnings->first() }}
            </div>
        @endif

        @php
            $activePanel = $filters['panel'] ?? request()->query('panel', 'status');
            $isStatusPanel = $activePanel === 'status';
            $isNoticePanel = $activePanel === 'notice';
            $isAutomationPanel = $activePanel === 'automation';
        @endphp

        <section data-testid="admin-weather-tabs" class="rounded-xl border bg-white p-4">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.modules.weather', ['panel' => 'status']) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $isStatusPanel ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Status Cuaca Wilayah
                </a>
                <a href="{{ route('admin.modules.weather', ['panel' => 'notice']) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $isNoticePanel ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Manajemen Notifikasi Cuaca
                </a>
                <a href="{{ route('admin.modules.weather', ['panel' => 'automation']) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $isAutomationPanel ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    Otomatisasi Notifikasi
                </a>
            </div>
        </section>

        @if($isStatusPanel)
            <section data-testid="admin-weather-status-panel" class="rounded-xl border bg-white p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Status Cuaca pada Wilayah Tertentu</h3>
                        <p class="mt-1 text-sm text-slate-600">Data status ditarik dari OpenWeather per wilayah, dengan BMKG fallback saat data utama tidak valid.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.modules.weather') }}" class="grid grid-cols-1 gap-2 md:grid-cols-4">
                        <input type="hidden" name="panel" value="status">
                        <input
                            type="text"
                            name="status_q"
                            value="{{ $filters['status_q'] ?? '' }}"
                            class="rounded-md border-slate-300 text-sm"
                            placeholder="Cari kota/kabupaten/provinsi (multi kata)"
                        >
                        <select name="status_province_id" class="rounded-md border-slate-300 text-sm">
                            <option value="">Semua Provinsi</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}" @selected(($filters['status_province_id'] ?? '') == (string) $province->id)>
                                    {{ $province->name }}
                                </option>
                            @endforeach
                        </select>
                        <select name="status_city_id" class="rounded-md border-slate-300 text-sm">
                            <option value="">Semua Kota/Kabupaten</option>
                            @foreach($noticeCityTargets as $targetCity)
                                <option value="{{ $targetCity->city_id }}" @selected(($filters['status_city_id'] ?? '') == (string) $targetCity->city_id)>
                                    {{ $targetCity->label }} - {{ $targetCity->province_name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Tampilkan Status
                        </button>
                    </form>
                </div>

                @if(($filters['status_q'] ?? '') !== '')
                    <p class="mt-2 text-xs text-slate-500">
                        Kata kunci: <span class="font-semibold text-slate-700">{{ $filters['status_q'] }}</span>.
                        Ditemukan {{ number_format((int) $weatherTableRows->count()) }} wilayah.
                    </p>
                @endif

                <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Aman</p>
                        <p class="text-lg font-bold text-emerald-700">{{ number_format((int) ($summary['green'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Waspada</p>
                        <p class="text-lg font-bold text-amber-700">{{ number_format((int) ($summary['yellow'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Bahaya</p>
                        <p class="text-lg font-bold text-rose-700">{{ number_format((int) ($summary['red'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Tidak Diketahui</p>
                        <p class="text-lg font-bold text-slate-700">{{ number_format((int) ($summary['unknown'] ?? 0)) }}</p>
                    </div>
                </div>

                @if($statusFocus)
                    @php
                        $focusSeverity = strtolower((string) ($statusFocus['severity'] ?? 'unknown'));
                        $focusBadgeClass = match ($focusSeverity) {
                            'red' => 'border-rose-200 bg-rose-50 text-rose-700',
                            'yellow' => 'border-amber-200 bg-amber-50 text-amber-700',
                            'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            default => 'border-slate-200 bg-slate-100 text-slate-700',
                        };
                    @endphp
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">{{ $statusTargetLabel ?? 'Semua Wilayah' }}</p>
                            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase {{ $focusBadgeClass }}">
                                {{ $focusSeverity }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">{{ $statusFocus['message'] ?? 'Data cuaca belum tersedia.' }}</p>
                        <p class="mt-1 text-xs text-slate-500">Sumber: {{ $statusFocus['source_label'] ?? '-' }}</p>
                        <p class="mt-1 text-xs text-slate-500">Kode BMKG: {{ $statusFocus['bmkg_code_label'] ?? '-' }}</p>
                        <p class="mt-1 text-xs text-slate-500">Alasan: {{ $statusFocus['source_reason'] ?? '-' }}</p>
                    </div>
                @endif

                <div class="mt-4 overflow-x-auto">
                    @if($weatherTableRows->isEmpty())
                        <p class="text-sm text-slate-600">Belum ada data cuaca untuk filter wilayah ini.</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-slate-600">
                                    <th class="py-2 pr-4">Lokasi</th>
                                    <th class="py-2 pr-4">Total User</th>
                                    <th class="py-2 pr-4">Suhu</th>
                                    <th class="py-2 pr-4">Curah Hujan</th>
                                    <th class="py-2 pr-4">Kecepatan Angin</th>
                                    <th class="py-2 pr-4">Sumber Data</th>
                                    <th class="py-2 pr-4">Kode BMKG</th>
                                    <th class="py-2 pr-4">Alasan Sumber</th>
                                    <th class="py-2 pr-4">Update Terakhir</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($weatherTableRows as $row)
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4">
                                            <p class="font-medium text-slate-900">{{ $row['label'] }}</p>
                                            <p class="text-xs text-slate-600">{{ $row['province_name'] }}</p>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $row['total_users']) }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ $row['temp_label'] }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ $row['rain_label'] }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ $row['wind_label'] }}</td>
                                        <td class="py-3 pr-4">
                                            <span class="rounded px-2 py-1 text-xs font-semibold {{ $row['source_badge_class'] }}">{{ $row['source_label'] }}</span>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">{{ $row['bmkg_code_label'] ?? '-' }}</td>
                                        <td class="py-3 pr-4 text-xs {{ $row['source_reason_class'] ?? 'text-slate-600' }}">
                                            {{ $row['source_reason'] ?? '-' }}
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">
                                            <p>{{ $row['updated_at_label'] }}</p>
                                            <p class="text-xs text-slate-500">Valid: {{ $row['cache_expiry_label'] }}</p>
                                        </td>
                                        <td class="py-3">
                                            <span class="rounded px-2 py-1 text-xs font-semibold uppercase {{ $row['severity_badge_class'] }}">{{ $row['severity'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section data-testid="admin-weather-snapshot-panel" class="rounded-xl border bg-white p-6">
                <h3 class="text-base font-semibold text-slate-900">Snapshot Cache Terbaru</h3>
                @if($snapshotRows->isEmpty())
                    <p class="mt-3 text-sm text-slate-600">Belum ada cache cuaca tersimpan.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-slate-600">
                                    <th class="py-2 pr-4">Kind</th>
                                    <th class="py-2 pr-4">Location</th>
                                    <th class="py-2 pr-4">Fetched At</th>
                                    <th class="py-2">Valid Until</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($snapshotRows as $snapshot)
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4 uppercase text-slate-700">{{ $snapshot['kind'] }}</td>
                                        <td class="py-3 pr-4 text-slate-700">{{ $snapshot['location_label'] }}</td>
                                        <td class="py-3 pr-4 text-slate-600">{{ $snapshot['fetched_at_label'] }}</td>
                                        <td class="py-3 text-slate-600">{{ $snapshot['valid_until_label'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        @if($isNoticePanel)
            <section data-testid="admin-weather-notice-panel" class="rounded-xl border bg-white p-6">
                <h3 class="text-base font-semibold text-slate-900">Manajemen Notifikasi Cuaca</h3>
                <p class="mt-1 text-sm text-slate-600">Notifikasi manual admin khusus status waspada per wilayah tertentu.</p>
                @php
                    $hasAdvancedNoticeFilter = ($filters['notice_status'] ?? '') !== ''
                        || ($filters['notice_scope'] ?? '') !== ''
                        || ($filters['notice_severity'] ?? '') !== ''
                        || ($filters['notice_validity'] ?? '') !== ''
                        || ($filters['notice_province_id'] ?? '') !== ''
                        || ($filters['notice_city_id'] ?? '') !== '';
                @endphp

                <form method="GET" action="{{ route('admin.modules.weather') }}" class="mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <input type="hidden" name="panel" value="notice">
                    <div class="flex flex-col gap-2 md:flex-row">
                        <input
                            type="text"
                            name="notice_q"
                            value="{{ $filters['notice_q'] ?? '' }}"
                            class="w-full rounded-md border-slate-300 text-sm"
                            placeholder="Cari wilayah atau keadaan cuaca: semarang, waspada hujan, angin kencang"
                        >
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Cari
                        </button>
                        <a href="{{ route('admin.modules.weather', ['panel' => 'notice']) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Reset
                        </a>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.modules.weather', ['panel' => 'notice', 'notice_q' => 'waspada']) }}" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                            Waspada
                        </a>
                        <a href="{{ route('admin.modules.weather', ['panel' => 'notice', 'notice_q' => 'hujan lebat']) }}" class="rounded-md border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">
                            Hujan Lebat
                        </a>
                        <a href="{{ route('admin.modules.weather', ['panel' => 'notice', 'notice_q' => 'angin kencang']) }}" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                            Angin Kencang
                        </a>
                        <a href="{{ route('admin.modules.weather', ['panel' => 'notice', 'notice_q' => 'bahaya']) }}" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                            Bahaya
                        </a>
                        <a href="{{ route('admin.modules.weather', ['panel' => 'notice', 'notice_q' => 'aman']) }}" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                            Aman
                        </a>
                    </div>

                    <details class="rounded-lg border border-slate-200 bg-white p-3" @if($hasAdvancedNoticeFilter) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Filter Lanjutan</summary>
                        <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-5">
                            <select name="notice_status" class="rounded-md border-slate-300 text-sm">
                                <option value="">Semua Status</option>
                                <option value="active" @selected(($filters['notice_status'] ?? '') === 'active')>Aktif</option>
                                <option value="inactive" @selected(($filters['notice_status'] ?? '') === 'inactive')>Nonaktif</option>
                                <option value="expired" @selected(($filters['notice_status'] ?? '') === 'expired')>Expired</option>
                            </select>
                            <select name="notice_scope" class="rounded-md border-slate-300 text-sm">
                                <option value="">Semua Scope</option>
                                <option value="global" @selected(($filters['notice_scope'] ?? '') === 'global')>Global</option>
                                <option value="province" @selected(($filters['notice_scope'] ?? '') === 'province')>Provinsi</option>
                                <option value="city" @selected(($filters['notice_scope'] ?? '') === 'city')>Kota</option>
                            </select>
                            <select name="notice_severity" class="rounded-md border-slate-300 text-sm">
                                <option value="">Semua Severity</option>
                                <option value="green" @selected(($filters['notice_severity'] ?? '') === 'green')>Green</option>
                                <option value="yellow" @selected(($filters['notice_severity'] ?? '') === 'yellow')>Yellow</option>
                                <option value="red" @selected(($filters['notice_severity'] ?? '') === 'red')>Red</option>
                                <option value="unknown" @selected(($filters['notice_severity'] ?? '') === 'unknown')>Unknown</option>
                            </select>
                            <select name="notice_validity" class="rounded-md border-slate-300 text-sm">
                                <option value="">Semua Masa Berlaku</option>
                                <option value="expiring_24h" @selected(($filters['notice_validity'] ?? '') === 'expiring_24h')>Akan Berakhir &lt; 24 Jam</option>
                                <option value="expiring_72h" @selected(($filters['notice_validity'] ?? '') === 'expiring_72h')>Akan Berakhir &lt; 72 Jam</option>
                                <option value="no_expiry" @selected(($filters['notice_validity'] ?? '') === 'no_expiry')>Tanpa Batas Waktu</option>
                            </select>
                            <select name="notice_province_id" class="rounded-md border-slate-300 text-sm">
                                <option value="">Semua Provinsi</option>
                                @foreach($provinces as $province)
                                    <option value="{{ $province->id }}" @selected(($filters['notice_province_id'] ?? '') == (string) $province->id)>{{ $province->name }}</option>
                                @endforeach
                            </select>
                            <select name="notice_city_id" class="rounded-md border-slate-300 text-sm md:col-span-2 lg:col-span-2">
                                <option value="">Semua Kota/Kabupaten</option>
                                @foreach($noticeCityTargets as $targetCity)
                                    <option value="{{ $targetCity->city_id }}" @selected(($filters['notice_city_id'] ?? '') == (string) $targetCity->city_id)>{{ $targetCity->label }} - {{ $targetCity->province_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </details>
                </form>
                <p class="mt-2 text-xs text-slate-500">
                    Menampilkan {{ number_format((int) $noticeRows->count()) }} notifikasi.
                </p>

                @if(($filters['notice_q'] ?? '') !== '')
                    <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4">
                        <h4 class="text-sm font-semibold text-slate-900">Keadaan Cuaca dari Pencarian</h4>
                        <p class="mt-1 text-xs text-slate-600">Kata kunci: <span class="font-semibold">{{ $filters['notice_q'] }}</span></p>
                        @if($noticeWeatherMatches->isEmpty())
                            <p class="mt-2 text-sm text-slate-600">Tidak ditemukan kondisi cuaca yang cocok untuk kata kunci ini.</p>
                        @else
                            <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
                                @foreach($noticeWeatherMatches as $weather)
                                    @php
                                        $severity = strtolower((string) ($weather['severity'] ?? 'unknown'));
                                        $severityBadgeClass = match ($severity) {
                                            'red' => 'border-rose-200 bg-rose-50 text-rose-700',
                                            'yellow' => 'border-amber-200 bg-amber-50 text-amber-700',
                                            'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            default => 'border-slate-200 bg-slate-100 text-slate-700',
                                        };
                                    @endphp
                                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-slate-900">{{ $weather['label'] ?? '-' }}</p>
                                            <span class="inline-flex rounded-full border px-2 py-1 text-[11px] font-semibold uppercase {{ $severityBadgeClass }}">
                                                {{ $severity }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600">{{ $weather['province_name'] ?? '-' }}</p>
                                        <p class="mt-2 text-sm text-slate-700">{{ $weather['message'] ?? '-' }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                                            <span>Suhu: {{ ($weather['temp'] ?? null) === null ? '-' : $weather['temp'].' C' }}</span>
                                            <span>Hujan: {{ ($weather['rain'] ?? null) === null ? '-' : $weather['rain'].' mm' }}</span>
                                            <span>Angin: {{ ($weather['wind'] ?? null) === null ? '-' : $weather['wind'].' m/s' }}</span>
                                            <span>Sumber: {{ $weather['source_label'] ?? '-' }}</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                <form id="weather-notice-form" method="POST" action="{{ route('admin.modules.weather.notices.store') }}" class="mt-5 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-2 lg:grid-cols-6">
                    @csrf
                    <div data-field="scope">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Target</label>
                        <select id="notice-scope" name="scope" class="w-full rounded-md border-slate-300 text-sm">
                            <option value="global" @selected(old('scope') === 'global')>Semua Wilayah</option>
                            <option value="province" @selected(old('scope', 'province') === 'province')>Per Provinsi</option>
                            <option value="city" @selected(old('scope') === 'city')>Per Kota/Kabupaten</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500">Pilih target penerima notifikasi berdasarkan cakupan wilayah.</p>
                    </div>
                    <div data-field="severity">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Severity</label>
                        <select id="notice-severity" name="severity" class="w-full rounded-md border-slate-300 text-sm">
                            <option value="green" @selected(old('severity') === 'green')>Green (Info)</option>
                            <option value="yellow" @selected(old('severity', 'yellow') === 'yellow')>Yellow (Waspada)</option>
                            <option value="red" @selected(old('severity') === 'red')>Red (Bahaya)</option>
                        </select>
                    </div>
                    <div data-field="valid_until">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Berlaku Sampai</label>
                        <input
                            id="notice-valid-until"
                            type="datetime-local"
                            name="valid_until"
                            value="{{ old('valid_until', now()->addHours(12)->format('Y-m-d\\TH:i')) }}"
                            min="{{ now()->addMinutes(10)->format('Y-m-d\\TH:i') }}"
                            class="w-full rounded-md border-slate-300 text-sm"
                            required
                        >
                        <p class="mt-1 text-[11px] text-slate-500">Wajib diisi agar notifikasi wilayah punya masa aktif yang jelas.</p>
                    </div>
                    <div data-field="province">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Provinsi</label>
                        <select id="notice-province" name="province_id" class="w-full rounded-md border-slate-300 text-sm">
                            <option value="">Pilih Provinsi</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}" @selected((string) old('province_id') === (string) $province->id)>{{ $province->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2" data-field="city">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kota</label>
                        <select id="notice-city" name="city_id" class="w-full rounded-md border-slate-300 text-sm">
                            <option value="">Pilih Kota</option>
                            @foreach($noticeCityTargets as $targetCity)
                                <option
                                    value="{{ $targetCity->city_id }}"
                                    data-province-id="{{ $targetCity->province_id }}"
                                    @selected((string) old('city_id') === (string) $targetCity->city_id)
                                >{{ $targetCity->label }} - {{ $targetCity->province_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2 lg:col-span-6 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Estimasi Penerima</p>
                        <p id="notice-recipient-count" class="mt-1 text-lg font-bold text-emerald-800">0 akun</p>
                        <p id="notice-recipient-target" class="text-xs text-emerald-700">Target belum dipilih.</p>
                    </div>
                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Judul</label>
                        <input type="text" name="title" value="{{ old('title') }}" class="w-full rounded-md border-slate-300 text-sm" placeholder="Opsional">
                    </div>
                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Pesan</label>
                        <textarea name="message" rows="3" class="w-full rounded-md border-slate-300 text-sm" required>{{ old('message') }}</textarea>
                    </div>
                    <div class="flex items-end md:col-span-2 lg:col-span-1">
                        <button type="submit" class="w-full rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800">Kirim Notifikasi</button>
                    </div>
                </form>
                <script id="notice-recipient-stats" type="application/json">@json($noticeRecipientStats ?? ['global_total' => 0, 'province_totals' => [], 'city_totals' => []])</script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const form = document.getElementById('weather-notice-form');
                        if (!form || form.dataset.bound === '1') {
                            return;
                        }
                        form.dataset.bound = '1';

                        const scopeSelect = form.querySelector('#notice-scope');
                        const provinceSelect = form.querySelector('#notice-province');
                        const citySelect = form.querySelector('#notice-city');
                        const provinceField = form.querySelector('[data-field="province"]');
                        const cityField = form.querySelector('[data-field="city"]');
                        const recipientCountEl = form.querySelector('#notice-recipient-count');
                        const recipientTargetEl = form.querySelector('#notice-recipient-target');
                        const recipientStatsNode = document.getElementById('notice-recipient-stats');
                        const formatter = new Intl.NumberFormat('id-ID');
                        let recipientStats = { global_total: 0, province_totals: {}, city_totals: {} };

                        if (recipientStatsNode && recipientStatsNode.textContent) {
                            try {
                                const parsed = JSON.parse(recipientStatsNode.textContent);
                                if (parsed && typeof parsed === 'object') {
                                    recipientStats = {
                                        global_total: Number(parsed.global_total || 0),
                                        province_totals: parsed.province_totals || {},
                                        city_totals: parsed.city_totals || {},
                                    };
                                }
                            } catch (e) {
                                recipientStats = { global_total: 0, province_totals: {}, city_totals: {} };
                            }
                        }

                        if (!scopeSelect || !provinceSelect || !citySelect || !provinceField || !cityField) {
                            return;
                        }

                        const syncCityOptions = () => {
                            const provinceId = provinceSelect.value;
                            Array.from(citySelect.options).forEach((option, index) => {
                                if (index === 0) {
                                    return;
                                }

                                const optionProvinceId = option.getAttribute('data-province-id') || '';
                                const visible = provinceId === '' || optionProvinceId === provinceId;
                                option.hidden = !visible;
                                if (!visible && option.selected) {
                                    option.selected = false;
                                }
                            });
                        };

                        const selectedOptionText = (select) => {
                            if (!select) {
                                return '';
                            }
                            const selected = select.options[select.selectedIndex];
                            return selected ? (selected.textContent || '').trim() : '';
                        };

                        const updateRecipientEstimate = () => {
                            if (!recipientCountEl || !recipientTargetEl) {
                                return;
                            }

                            const scope = scopeSelect.value;
                            const provinceId = String(provinceSelect.value || '');
                            const cityId = String(citySelect.value || '');
                            const provinceTotals = recipientStats.province_totals || {};
                            const cityTotals = recipientStats.city_totals || {};

                            let total = 0;
                            let targetLabel = 'Target belum dipilih.';

                            if (scope === 'global') {
                                total = Number(recipientStats.global_total || 0);
                                targetLabel = 'Semua wilayah (seluruh akun non-admin).';
                            } else if (scope === 'province') {
                                total = Number(provinceTotals[provinceId] || 0);
                                const provinceText = selectedOptionText(provinceSelect);
                                targetLabel = provinceId !== '' && provinceText !== '' ? ('Provinsi ' + provinceText + '.') : 'Provinsi belum dipilih.';
                            } else if (scope === 'city') {
                                total = Number(cityTotals[cityId] || 0);
                                const cityText = selectedOptionText(citySelect);
                                targetLabel = cityId !== '' && cityText !== '' ? ('Kota/Kabupaten ' + cityText + '.') : 'Kota/Kabupaten belum dipilih.';
                            }

                            recipientCountEl.textContent = formatter.format(Math.max(0, total)) + ' akun';
                            recipientTargetEl.textContent = targetLabel;
                        };

                        const syncScopeFields = () => {
                            const scope = scopeSelect.value;

                            if (scope === 'global') {
                                provinceField.style.display = 'none';
                                cityField.style.display = 'none';
                                provinceSelect.required = false;
                                citySelect.required = false;
                                provinceSelect.value = '';
                                citySelect.value = '';
                            } else if (scope === 'province') {
                                provinceField.style.display = '';
                                cityField.style.display = 'none';
                                provinceSelect.required = true;
                                citySelect.required = false;
                                citySelect.value = '';
                            } else {
                                provinceField.style.display = '';
                                cityField.style.display = '';
                                provinceSelect.required = true;
                                citySelect.required = true;
                            }

                            syncCityOptions();
                            updateRecipientEstimate();
                        };

                        provinceSelect.addEventListener('change', () => {
                            syncCityOptions();
                            updateRecipientEstimate();
                        });
                        citySelect.addEventListener('change', () => {
                            const selectedOption = citySelect.options[citySelect.selectedIndex];
                            if (!selectedOption) {
                                updateRecipientEstimate();
                                return;
                            }

                            const optionProvinceId = selectedOption.getAttribute('data-province-id') || '';
                            if (optionProvinceId !== '') {
                                provinceSelect.value = optionProvinceId;
                                syncCityOptions();
                            }
                            updateRecipientEstimate();
                        });
                        scopeSelect.addEventListener('change', syncScopeFields);

                        syncScopeFields();
                        updateRecipientEstimate();
                    });
                </script>

                <div class="mt-4 space-y-2">
                    @forelse($noticeRows as $notice)
                        <article class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase {{ $notice['severity_badge_class'] }}">{{ $notice['severity'] }}</span>
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase {{ $notice['status_badge_class'] }}">{{ $notice['status_label'] }}</span>
                                <span class="text-xs font-semibold text-slate-600">{{ $notice['target_label'] }}</span>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $notice['title'] ?: 'Notifikasi Cuaca' }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $notice['message'] }}</p>
                            <div class="mt-2 grid grid-cols-1 gap-1 text-xs text-slate-600 md:grid-cols-3">
                                <p>Berlaku sampai: <span class="font-semibold text-slate-700">{{ $notice['valid_until_label'] }}</span></p>
                                <p>Dibuat: <span class="font-semibold text-slate-700">{{ $notice['created_at_label'] }}</span></p>
                                <p>Pembuat: <span class="font-semibold text-slate-700">{{ $notice['created_by_name'] !== '' ? $notice['created_by_name'] : '-' }}</span></p>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('admin.modules.weather.notices.toggle', ['noticeId' => $notice['id']]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                        {{ $notice['toggle_label'] }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.modules.weather.notices.destroy', ['noticeId' => $notice['id']]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100" onclick="return confirm('Hapus notifikasi cuaca ini?');">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-slate-600">Belum ada notifikasi cuaca yang dikirim admin.</p>
                    @endforelse
                </div>
            </section>
        @endif

        @if($isAutomationPanel)
            <section data-testid="admin-weather-automation-panel" class="rounded-xl border bg-white p-6">
                <h3 class="text-base font-semibold text-slate-900">Otomatisasi Notifikasi</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Menampilkan semua notifikasi otomatis yang dihasilkan engine <strong>Rule Rekomendasi</strong> (consumer, mitra, dan seller).
                </p>

                <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-6">
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Total</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format((int) ($recommendationSummary['total'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Belum Dibaca</p>
                        <p class="mt-1 text-lg font-bold text-amber-700">{{ number_format((int) ($recommendationSummary['unread'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Consumer</p>
                        <p class="mt-1 text-lg font-bold text-indigo-700">{{ number_format((int) ($recommendationSummary['consumer'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Mitra</p>
                        <p class="mt-1 text-lg font-bold text-emerald-700">{{ number_format((int) ($recommendationSummary['mitra'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Seller</p>
                        <p class="mt-1 text-lg font-bold text-amber-700">{{ number_format((int) ($recommendationSummary['seller'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg border bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Hari Ini</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format((int) ($recommendationSummary['today'] ?? 0)) }}</p>
                    </div>
                </div>

                <form method="GET" action="{{ route('admin.modules.weather') }}" class="mt-4 grid grid-cols-1 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-5">
                    <input type="hidden" name="panel" value="automation">
                    <input
                        type="text"
                        name="automation_q"
                        value="{{ $filters['automation_q'] ?? '' }}"
                        class="rounded-md border-slate-300 text-sm md:col-span-2"
                        placeholder="Cari judul, pesan, target, nama/email user"
                    >
                    <select name="automation_role_target" class="rounded-md border-slate-300 text-sm">
                        <option value="">Semua Target Role</option>
                        <option value="consumer" @selected(($filters['automation_role_target'] ?? '') === 'consumer')>Consumer</option>
                        <option value="mitra" @selected(($filters['automation_role_target'] ?? '') === 'mitra')>Mitra</option>
                        <option value="seller" @selected(($filters['automation_role_target'] ?? '') === 'seller')>Seller</option>
                    </select>
                    <select name="automation_read_status" class="rounded-md border-slate-300 text-sm">
                        <option value="">Semua Status Baca</option>
                        <option value="unread" @selected(($filters['automation_read_status'] ?? '') === 'unread')>Belum Dibaca</option>
                        <option value="read" @selected(($filters['automation_read_status'] ?? '') === 'read')>Sudah Dibaca</option>
                    </select>
                    <select name="automation_rule_key" class="rounded-md border-slate-300 text-sm">
                        <option value="">Semua Rule Key</option>
                        @foreach($recommendationRuleKeys as $ruleKey)
                            <option value="{{ $ruleKey }}" @selected(($filters['automation_rule_key'] ?? '') === (string) $ruleKey)>{{ $ruleKey }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Filter
                    </button>
                    <a href="{{ route('admin.modules.weather', ['panel' => 'automation']) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">
                        Reset
                    </a>
                </form>

                <p class="mt-3 text-xs text-slate-500">
                    Menampilkan {{ number_format((int) ($recommendationSummary['filtered'] ?? 0)) }} notifikasi otomatis.
                </p>

                <div class="mt-4 overflow-x-auto">
                    @if($recommendationNotifications->isEmpty())
                        <p class="text-sm text-slate-600">Belum ada notifikasi otomatis dari Rule Rekomendasi untuk filter ini.</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-slate-600">
                                    <th class="py-2 pr-4">Notifikasi</th>
                                    <th class="py-2 pr-4">Target</th>
                                    <th class="py-2 pr-4">Penerima</th>
                                    <th class="py-2 pr-4">Rule Key</th>
                                    <th class="py-2 pr-4">Kirim</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recommendationNotifications as $row)
                                    @php
                                        $statusClass = match (strtolower((string) ($row['status'] ?? 'unknown'))) {
                                            'red' => 'border-rose-200 bg-rose-50 text-rose-700',
                                            'yellow' => 'border-amber-200 bg-amber-50 text-amber-700',
                                            'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            default => 'border-slate-200 bg-slate-100 text-slate-700',
                                        };
                                        $targetRoleClass = match ((string) ($row['role_target'] ?? '')) {
                                            'mitra' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'seller' => 'border-amber-200 bg-amber-50 text-amber-700',
                                            default => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                        };
                                        $readClass = ($row['is_read'] ?? false)
                                            ? 'border-slate-200 bg-slate-100 text-slate-700'
                                            : 'border-amber-200 bg-amber-50 text-amber-700';
                                    @endphp
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4">
                                            <p class="font-semibold text-slate-900">{{ $row['title'] }}</p>
                                            <p class="mt-1 text-slate-700">{{ $row['message'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">Target wilayah: {{ $row['target_label'] }}</p>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <span class="inline-flex rounded-full border px-2 py-1 text-[11px] font-semibold uppercase {{ $targetRoleClass }}">
                                                {{ strtoupper((string) ($row['role_target'] ?? 'unknown')) }}
                                            </span>
                                            <div class="mt-1">
                                                <span class="inline-flex rounded-full border px-2 py-1 text-[11px] font-semibold uppercase {{ $statusClass }}">
                                                    {{ strtoupper((string) ($row['status'] ?? 'unknown')) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">
                                            <p class="font-semibold text-slate-900">{{ $row['recipient_name'] }}</p>
                                            <p class="text-xs text-slate-600">{{ $row['recipient_email'] }}</p>
                                        </td>
                                        <td class="py-3 pr-4 text-xs text-slate-700">{{ $row['rule_key'] }}</td>
                                        <td class="py-3 pr-4 text-xs text-slate-600">
                                            <p>Dikirim: {{ $row['sent_at'] }}</p>
                                            <p>Valid: {{ $row['valid_until'] }}</p>
                                            @if(!empty($row['action_label']) && !empty($row['action_url']))
                                                <p class="mt-1">{{ $row['action_label'] }}: <span class="text-slate-500">{{ $row['action_url'] }}</span></p>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="inline-flex rounded-full border px-2 py-1 text-[11px] font-semibold {{ $readClass }}">
                                                {{ ($row['is_read'] ?? false) ? 'Dibaca' : 'Belum Dibaca' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
        @endif
    </div>
</x-admin-layout>
