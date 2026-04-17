<x-admin-layout>
    {{-- CATATAN-AUDIT: Panel pusat pengaturan rule rekomendasi perilaku (consumer + mitra + seller). --}}
    <x-slot name="header">
        {{ __('Rule Rekomendasi') }}
    </x-slot>

    @php
        $consumerSettings = (array) ($consumerRule->settings ?? []);
        $mitraSettings = (array) ($mitraRule->settings ?? []);
        $sellerSettings = (array) ($sellerRule->settings ?? []);
    @endphp

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

        <section class="rounded-xl border bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Rule-Based Time Triggered Recommendation</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Kelola rule perilaku untuk consumer, mitra, dan seller. Rule aktif dipakai langsung oleh engine rekomendasi.
                    </p>
                </div>
                <a
                    href="{{ route('admin.modules.weather', ['panel' => 'automation']) }}"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                >
                    Buka Notifikasi Cuaca
                </a>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-lg border bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Sync Scheduler</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ ($syncConfig['enabled'] ?? false) ? 'Aktif' : 'Nonaktif' }}</p>
                </div>
                <div class="rounded-lg border bg-slate-50 p-3 md:col-span-2">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Cron</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $syncConfig['cron'] ?? '-' }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.modules.recommendationRules.syncNow') }}" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                @csrf
                <p class="text-sm font-semibold text-slate-900">Sinkronisasi Manual Sekarang</p>
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-5">
                    <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                        <input type="checkbox" name="roles[]" value="consumer" class="rounded border-slate-300 text-slate-900" checked>
                        <span>Consumer</span>
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                        <input type="checkbox" name="roles[]" value="mitra" class="rounded border-slate-300 text-slate-900" checked>
                        <span>Mitra</span>
                    </label>
                    <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                        <input type="checkbox" name="roles[]" value="seller" class="rounded border-slate-300 text-slate-900" checked>
                        <span>Seller</span>
                    </label>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Chunk</label>
                        <input type="number" name="chunk" min="20" max="1000" value="{{ old('chunk', config('recommendation.sync.chunk', 200)) }}" class="w-full rounded-md border-slate-300 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Jalankan Sync
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <h3 class="text-base font-semibold text-slate-900">Rule Seller</h3>
            <p class="mt-1 text-sm text-slate-600">Trigger prediksi permintaan produk petani dari performa order P2P seller dan kondisi cuaca panen.</p>

            <form method="POST" action="{{ route('admin.modules.recommendationRules.update', ['ruleId' => $sellerRule->id]) }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Nama Rule</label>
                    <input type="text" name="name" value="{{ old('name', $sellerRule->name) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Status</label>
                    <label class="inline-flex h-[42px] w-full items-center gap-2 rounded-md border border-slate-300 px-3 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-slate-900" @checked(old('is_active', $sellerRule->is_active ? '1' : '0') === '1')>
                        <span>Aktifkan rule</span>
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Deskripsi</label>
                    <textarea name="description" rows="2" class="w-full rounded-md border-slate-300 text-sm">{{ old('description', $sellerRule->description) }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Lookback Penjualan (hari)</label>
                    <input type="number" name="lookback_days" min="1" max="30" value="{{ old('lookback_days', (int) ($sellerSettings['lookback_days'] ?? 7)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Min Order Paid</label>
                    <input type="number" name="min_paid_orders" min="1" max="10000" value="{{ old('min_paid_orders', (int) ($sellerSettings['min_paid_orders'] ?? 5)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Min Total Qty</label>
                    <input type="number" name="min_total_qty" min="1" max="1000000" value="{{ old('min_total_qty', (int) ($sellerSettings['min_total_qty'] ?? 10)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Target Window (hari)</label>
                    <input type="text" name="target_window_days" value="{{ old('target_window_days', (string) ($sellerSettings['target_window_days'] ?? '3-5')) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Severity Cuaca Diizinkan</label>
                    <input
                        type="text"
                        name="allowed_weather_severities"
                        value="{{ old('allowed_weather_severities', implode(',', (array) ($sellerSettings['allowed_weather_severities'] ?? ['green','yellow']))) }}"
                        class="w-full rounded-md border-slate-300 text-sm"
                        placeholder="green,yellow"
                        required
                    >
                </div>
                <div></div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Suhu Min Panen</label>
                    <input type="number" step="0.1" name="harvest_temp_min" min="-10" max="60" value="{{ old('harvest_temp_min', (float) ($sellerSettings['harvest_temp_min'] ?? 20)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Suhu Max Panen</label>
                    <input type="number" step="0.1" name="harvest_temp_max" min="-10" max="60" value="{{ old('harvest_temp_max', (float) ($sellerSettings['harvest_temp_max'] ?? 34)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Kelembapan Min Panen</label>
                    <input type="number" name="harvest_humidity_min" min="1" max="100" value="{{ old('harvest_humidity_min', (int) ($sellerSettings['harvest_humidity_min'] ?? 50)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Kelembapan Max Panen</label>
                    <input type="number" name="harvest_humidity_max" min="1" max="100" value="{{ old('harvest_humidity_max', (int) ($sellerSettings['harvest_humidity_max'] ?? 95)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Simpan Rule Seller
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <h3 class="text-base font-semibold text-slate-900">Rule Consumer</h3>
            <p class="mt-1 text-sm text-slate-600">Trigger rekomendasi penyemprotan berdasarkan histori pembelian, cuaca cerah, dan kelembapan.</p>

            <form method="POST" action="{{ route('admin.modules.recommendationRules.update', ['ruleId' => $consumerRule->id]) }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Nama Rule</label>
                    <input type="text" name="name" value="{{ old('name', $consumerRule->name) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Status</label>
                    <label class="inline-flex h-[42px] w-full items-center gap-2 rounded-md border border-slate-300 px-3 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-slate-900" @checked(old('is_active', $consumerRule->is_active ? '1' : '0') === '1')>
                        <span>Aktifkan rule</span>
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Deskripsi</label>
                    <textarea name="description" rows="2" class="w-full rounded-md border-slate-300 text-sm">{{ old('description', $consumerRule->description) }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Keyword Produk</label>
                    <input
                        type="text"
                        name="product_keywords"
                        value="{{ old('product_keywords', implode(',', (array) ($consumerSettings['product_keywords'] ?? ['pupuk']))) }}"
                        class="w-full rounded-md border-slate-300 text-sm"
                        placeholder="pupuk,kompos"
                        required
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Keyword Cuaca Cerah</label>
                    <input
                        type="text"
                        name="clear_keywords"
                        value="{{ old('clear_keywords', implode(',', (array) ($consumerSettings['clear_keywords'] ?? ['clear','cerah','sunny']))) }}"
                        class="w-full rounded-md border-slate-300 text-sm"
                        placeholder="clear,cerah,sunny"
                        required
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Trigger Setelah Beli (hari)</label>
                    <input type="number" name="trigger_days_after_purchase" min="1" max="30" value="{{ old('trigger_days_after_purchase', (int) ($consumerSettings['trigger_days_after_purchase'] ?? 7)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Window Trigger (hari)</label>
                    <input type="number" name="trigger_window_days" min="1" max="30" value="{{ old('trigger_window_days', (int) ($consumerSettings['trigger_window_days'] ?? 7)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Lookback Order (hari)</label>
                    <input type="number" name="lookback_days" min="7" max="180" value="{{ old('lookback_days', (int) ($consumerSettings['lookback_days'] ?? 45)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Minimal Kelembapan (%)</label>
                    <input type="number" name="humidity_min" min="1" max="100" value="{{ old('humidity_min', (int) ($consumerSettings['humidity_min'] ?? 70)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Simpan Rule Consumer
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <h3 class="text-base font-semibold text-slate-900">Rule Mitra</h3>
            <p class="mt-1 text-sm text-slate-600">Trigger prediksi demand mitra dari tren pembelian consumer dan kondisi cuaca vegetatif.</p>

            <form method="POST" action="{{ route('admin.modules.recommendationRules.update', ['ruleId' => $mitraRule->id]) }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Nama Rule</label>
                    <input type="text" name="name" value="{{ old('name', $mitraRule->name) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Status</label>
                    <label class="inline-flex h-[42px] w-full items-center gap-2 rounded-md border border-slate-300 px-3 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-slate-900" @checked(old('is_active', $mitraRule->is_active ? '1' : '0') === '1')>
                        <span>Aktifkan rule</span>
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Deskripsi</label>
                    <textarea name="description" rows="2" class="w-full rounded-md border-slate-300 text-sm">{{ old('description', $mitraRule->description) }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Keyword Produk</label>
                    <input
                        type="text"
                        name="product_keywords"
                        value="{{ old('product_keywords', implode(',', (array) ($mitraSettings['product_keywords'] ?? ['pupuk']))) }}"
                        class="w-full rounded-md border-slate-300 text-sm"
                        placeholder="pupuk,kompos"
                        required
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Severity Cuaca Diizinkan</label>
                    <input
                        type="text"
                        name="allowed_weather_severities"
                        value="{{ old('allowed_weather_severities', implode(',', (array) ($mitraSettings['allowed_weather_severities'] ?? ['green','yellow']))) }}"
                        class="w-full rounded-md border-slate-300 text-sm"
                        placeholder="green,yellow"
                        required
                    >
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Lookback Demand (hari)</label>
                    <input type="number" name="lookback_days" min="1" max="30" value="{{ old('lookback_days', (int) ($mitraSettings['lookback_days'] ?? 7)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Min Distinct Buyer</label>
                    <input type="number" name="min_distinct_buyers" min="1" max="10000" value="{{ old('min_distinct_buyers', (int) ($mitraSettings['min_distinct_buyers'] ?? 20)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Target Window (hari)</label>
                    <input type="text" name="target_window_days" value="{{ old('target_window_days', (string) ($mitraSettings['target_window_days'] ?? '7-10')) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div></div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Suhu Min Vegetatif</label>
                    <input type="number" step="0.1" name="vegetative_temp_min" min="-10" max="60" value="{{ old('vegetative_temp_min', (float) ($mitraSettings['vegetative_temp_min'] ?? 20)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Suhu Max Vegetatif</label>
                    <input type="number" step="0.1" name="vegetative_temp_max" min="-10" max="60" value="{{ old('vegetative_temp_max', (float) ($mitraSettings['vegetative_temp_max'] ?? 33)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Kelembapan Min Vegetatif</label>
                    <input type="number" name="vegetative_humidity_min" min="1" max="100" value="{{ old('vegetative_humidity_min', (int) ($mitraSettings['vegetative_humidity_min'] ?? 55)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-wide text-slate-500">Kelembapan Max Vegetatif</label>
                    <input type="number" name="vegetative_humidity_max" min="1" max="100" value="{{ old('vegetative_humidity_max', (int) ($mitraSettings['vegetative_humidity_max'] ?? 95)) }}" class="w-full rounded-md border-slate-300 text-sm" required>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Simpan Rule Mitra
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-admin-layout>
