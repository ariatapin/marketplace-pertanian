<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Lokasi</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/app.js', 'resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    @php
        $backUrl = $backUrl ?? route('profile.edit');
        $currentLocationLabel = $currentLocationLabel ?? 'Belum diset';
        $hasLocationSet = (bool) ($hasLocationSet ?? false);
    @endphp
    <main class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Set Lokasi (Wilayah)</h1>
                <p class="mt-1 text-sm text-slate-600">Atur provinsi, kota, dan kecamatan agar prediksi cuaca lebih akurat.</p>
                <p class="mt-1 text-xs font-semibold {{ $hasLocationSet ? 'text-emerald-700' : 'text-amber-700' }}">
                    Lokasi aktif: {{ $currentLocationLabel }}
                </p>
            </div>
            <a href="{{ $backUrl }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Kembali ke Profil
            </a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('profile.location.save') }}"
            x-data="regionPicker({
                provinceId: {{ (int) old('province_id', (int) (auth()->user()->province_id ?? 0)) }},
                cityId: {{ (int) old('city_id', (int) (auth()->user()->city_id ?? 0)) }},
                districtId: {{ (int) old('district_id', (int) (auth()->user()->district_id ?? 0)) }},
            })"
            x-init="init()"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
        >
            @csrf

            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">Provinsi</label>
                    <select
                        name="province_id"
                        x-model="provinceId"
                        @change="onProvinceChange()"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                        <option value="">Pilih Provinsi</option>
                        <template x-for="p in provinces" :key="p.id">
                            <option :value="p.id" x-text="p.name"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">Kota / Kabupaten</label>
                    <select
                        name="city_id"
                        x-model="cityId"
                        @change="onCityChange()"
                        :disabled="!provinceId"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-slate-100 disabled:text-slate-500"
                    >
                        <option value="">Pilih Kota/Kab</option>
                        <template x-for="c in cities" :key="c.id">
                            <option :value="c.id" x-text="(c.type ? (c.type + ' ') : '') + c.name"></option>
                        </template>
                    </select>

                    <p class="mt-1.5 text-xs text-slate-500" x-show="selectedCityLatLng">
                        Lat/Lng (dari City): <span x-text="selectedCityLatLng"></span>
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700">Kecamatan (opsional)</label>
                    <select
                        name="district_id"
                        x-model="districtId"
                        :disabled="!cityId"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-slate-100 disabled:text-slate-500"
                    >
                        <option value="">(Opsional) Pilih Kecamatan</option>
                        <template x-for="d in districts" :key="d.id">
                            <option :value="d.id" x-text="d.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Simpan Lokasi
                </button>
            </div>
        </form>
    </main>
</body>
</html>

