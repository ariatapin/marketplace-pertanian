<x-admin-layout>
    <x-slot name="header">
        {{ __('Modul Gudang') }}
    </x-slot>

    @php
        $provinceOptions = collect($provinces ?? [])->map(fn ($province) => [
            'id' => (int) $province->id,
            'name' => (string) $province->name,
        ])->values();
        $cityOptions = collect($allCities ?? [])->map(fn ($city) => [
            'id' => (int) $city->id,
            'province_id' => (int) $city->province_id,
            'name' => (string) $city->name,
            'type' => (string) ($city->type ?? ''),
            'province_name' => (string) ($city->province_name ?? ''),
        ])->values();
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8"
        x-data="{
            editOpen: false,
            editAction: '',
            provinces: @js($provinceOptions),
            cities: @js($cityOptions),
            form: {
                id: null,
                code: '',
                name: '',
                province_id: '',
                city_id: '',
                address: '',
                notes: '',
                is_active: true,
            },
            openEdit(warehouse) {
                this.form.id = warehouse.id ?? null;
                this.form.code = warehouse.code ?? '';
                this.form.name = warehouse.name ?? '';
                this.form.province_id = warehouse.province_id ? String(warehouse.province_id) : '';
                this.form.city_id = warehouse.city_id ? String(warehouse.city_id) : '';
                this.form.address = warehouse.address ?? '';
                this.form.notes = warehouse.notes ?? '';
                this.form.is_active = !!warehouse.is_active;
                this.editAction = warehouse.update_url ?? '';
                this.editOpen = true;
            },
            closeEdit() {
                this.editOpen = false;
                this.editAction = '';
            },
            filteredCities() {
                if (!this.form.province_id) return [];
                return this.cities.filter((city) => String(city.province_id) === String(this.form.province_id));
            },
            ensureCityInProvince() {
                const cityExists = this.filteredCities().some((city) => String(city.id) === String(this.form.city_id));
                if (!cityExists) this.form.city_id = '';
            },
            cityDisplay(city) {
                const typeLabel = city.type ? `${city.type} ` : '';
                return `${typeLabel}${city.name}`;
            }
        }"
        @keydown.escape.window="closeEdit()"
    >
        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Gudang</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($summary['total_warehouses'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Gudang Aktif</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format((int) ($summary['active_warehouses'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Stok Gudang</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($summary['stock_gudang'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Barang Masuk</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format((int) ($summary['barang_masuk'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Barang Keluar</p>
                <p class="mt-2 text-2xl font-bold text-rose-700">{{ number_format((int) ($summary['barang_keluar'] ?? 0)) }}</p>
            </div>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Tambah Gudang</h3>
                    <p class="mt-1 text-sm text-slate-600">Gudang ditambahkan manual oleh admin. Tidak dibuat otomatis berdasarkan wilayah.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                    Manajemen Gudang Terpusat
                </span>
            </div>

            <form
                method="POST"
                action="{{ route('admin.modules.warehouse.store') }}"
                x-data="regionPicker({
                    provinceId: {{ (int) old('province_id', 0) }},
                    cityId: {{ (int) old('city_id', 0) }},
                    districtId: 0,
                })"
                x-init="init()"
                class="mt-5 space-y-4"
            >
                @csrf

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kode Gudang (opsional)</label>
                        <input
                            type="text"
                            name="code"
                            value="{{ old('code') }}"
                            placeholder="Contoh: GDG-JKT-01"
                            class="w-full rounded-lg border-slate-300 text-sm"
                        >
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Gudang</label>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="Contoh: Gudang Utama Jakarta Timur"
                            class="w-full rounded-lg border-slate-300 text-sm"
                            required
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Provinsi</label>
                        <select
                            name="province_id"
                            x-model="provinceId"
                            @change="onProvinceChange()"
                            class="w-full rounded-lg border-slate-300 text-sm"
                            required
                        >
                            <option value="">Pilih Provinsi</option>
                            <template x-for="p in provinces" :key="p.id">
                                <option :value="p.id" x-text="p.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kota / Kabupaten</label>
                        <select
                            name="city_id"
                            x-model="cityId"
                            @change="onCityChange()"
                            :disabled="!provinceId"
                            class="w-full rounded-lg border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500"
                            required
                        >
                            <option value="">Pilih Kota/Kabupaten</option>
                            <template x-for="c in cities" :key="c.id">
                                <option :value="c.id" x-text="(c.type ? (c.type + ' ') : '') + c.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat Gudang</label>
                        <textarea
                            name="address"
                            rows="2"
                            class="w-full rounded-lg border-slate-300 text-sm"
                            placeholder="Alamat lengkap gudang"
                        >{{ old('address') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan (opsional)</label>
                        <textarea
                            name="notes"
                            rows="2"
                            class="w-full rounded-lg border-slate-300 text-sm"
                            placeholder="Catatan operasional gudang"
                        >{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600" @checked((string) old('is_active', '1') === '1')>
                        <span>Aktifkan gudang ini</span>
                    </label>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Simpan Gudang
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Daftar Gudang</h3>
                    <p class="mt-1 text-sm text-slate-600">Gunakan filter untuk mencari gudang sesuai kode, nama, dan wilayah.</p>
                </div>
                <form method="GET" action="{{ route('admin.modules.warehouse') }}" class="grid grid-cols-1 gap-2 md:grid-cols-5">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Kode / nama / wilayah" class="rounded-md border-slate-300 text-sm md:col-span-2">
                    <select name="province_id" class="rounded-md border-slate-300 text-sm">
                        <option value="">Semua Provinsi</option>
                        @foreach($provinces as $province)
                            <option value="{{ $province->id }}" @selected(($filters['province_id'] ?? '') === (string) $province->id)>{{ $province->name }}</option>
                        @endforeach
                    </select>
                    <select name="city_id" class="rounded-md border-slate-300 text-sm">
                        <option value="">Semua Kota/Kab</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}" @selected(($filters['city_id'] ?? '') === (string) $city->id)>{{ trim(($city->type ? $city->type . ' ' : '') . $city->name) }}</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2">
                        <select name="status" class="rounded-md border-slate-300 text-sm">
                            <option value="">Semua Status</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                        </select>
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Cari
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-4 overflow-x-auto">
                @if($warehouseLocations->isEmpty())
                    <p class="text-sm text-slate-600">Belum ada data gudang. Tambahkan gudang pertama dari form di atas.</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-slate-600">
                                <th class="py-2 pr-4">Kode</th>
                                <th class="py-2 pr-4">Nama Gudang</th>
                                <th class="py-2 pr-4">Wilayah</th>
                                <th class="py-2 pr-4">Alamat</th>
                                <th class="py-2 pr-4">Produk</th>
                                <th class="py-2 pr-4">Stok</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Dibuat</th>
                                <th class="py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($warehouseLocations as $warehouse)
                                @php
                                    $cityLabel = trim((string) (($warehouse->city_type ? $warehouse->city_type . ' ' : '') . ($warehouse->city_name ?? '')));
                                    $provinceLabel = trim((string) ($warehouse->province_name ?? ''));
                                    $regionLabel = trim(($cityLabel !== '' ? $cityLabel : '-') . ' / ' . ($provinceLabel !== '' ? $provinceLabel : '-'));
                                    $isActiveWarehouse = (bool) $warehouse->is_active;
                                    $lockedToDeactivate = $isActiveWarehouse && (((int) ($warehouse->active_product_count ?? 0)) > 0 || ((int) ($warehouse->total_stock ?? 0)) > 0);
                                @endphp
                                <tr class="border-b last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">{{ $warehouse->code }}</td>
                                    <td class="py-3 pr-4 text-slate-800">{{ $warehouse->name }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $regionLabel }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $warehouse->address ?: '-' }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) ($warehouse->product_count ?? 0)) }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) ($warehouse->total_stock ?? 0)) }}</td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex rounded px-2 py-1 text-xs font-semibold {{ $isActiveWarehouse ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                            {{ $isActiveWarehouse ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">{{ \Illuminate\Support\Carbon::parse($warehouse->created_at)->format('d M Y') }}</td>
                                    <td class="py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                @click="openEdit(@js([
                                                    'id' => (int) $warehouse->id,
                                                    'code' => (string) $warehouse->code,
                                                    'name' => (string) $warehouse->name,
                                                    'province_id' => $warehouse->province_id ? (int) $warehouse->province_id : null,
                                                    'city_id' => $warehouse->city_id ? (int) $warehouse->city_id : null,
                                                    'address' => (string) ($warehouse->address ?? ''),
                                                    'notes' => (string) ($warehouse->notes ?? ''),
                                                    'is_active' => (bool) $warehouse->is_active,
                                                    'update_url' => route('admin.modules.warehouse.update', ['warehouseId' => $warehouse->id]),
                                                ]))"
                                            >
                                                Edit
                                            </button>

                                            <form
                                                method="POST"
                                                action="{{ route('admin.modules.warehouse.toggleActive', ['warehouseId' => $warehouse->id]) }}"
                                                onsubmit="return confirm('{{ $isActiveWarehouse ? 'Nonaktifkan gudang ini?' : 'Aktifkan kembali gudang ini?' }}');"
                                            >
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $isActiveWarehouse ? 'bg-amber-600 text-white hover:bg-amber-700' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                                                    {{ $lockedToDeactivate ? 'disabled' : '' }}
                                                >
                                                    {{ $isActiveWarehouse ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        </div>
                                        @if($lockedToDeactivate)
                                            <p class="mt-1 text-[11px] font-semibold text-amber-700">
                                                Tidak bisa nonaktif: masih ada produk aktif atau stok di gudang ini.
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if(method_exists($warehouseLocations, 'links'))
                <div class="mt-4">
                    {{ $warehouseLocations->links() }}
                </div>
            @endif
        </section>

        <div
            x-show="editOpen"
            x-cloak
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/50 px-4 py-6"
            @click.self="closeEdit()"
        >
            <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Edit Gudang</h3>
                        <p class="text-xs text-slate-500">Perbarui data gudang dan status aktif.</p>
                    </div>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeEdit()">
                        Tutup
                    </button>
                </div>

                <form method="POST" :action="editAction" class="space-y-4 p-5">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kode Gudang</label>
                            <input type="text" name="code" x-model="form.code" class="w-full rounded-lg border-slate-300 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Gudang</label>
                            <input type="text" name="name" x-model="form.name" class="w-full rounded-lg border-slate-300 text-sm" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Provinsi</label>
                            <select
                                name="province_id"
                                x-model="form.province_id"
                                @change="ensureCityInProvince()"
                                class="w-full rounded-lg border-slate-300 text-sm"
                                required
                            >
                                <option value="">Pilih Provinsi</option>
                                <template x-for="province in provinces" :key="province.id">
                                    <option :value="String(province.id)" x-text="province.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kota / Kabupaten</label>
                            <select
                                name="city_id"
                                x-model="form.city_id"
                                :disabled="!form.province_id"
                                class="w-full rounded-lg border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500"
                                required
                            >
                                <option value="">Pilih Kota/Kabupaten</option>
                                <template x-for="city in filteredCities()" :key="city.id">
                                    <option :value="String(city.id)" x-text="cityDisplay(city)"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat Gudang</label>
                            <textarea name="address" rows="2" x-model="form.address" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</label>
                            <textarea name="notes" rows="2" x-model="form.notes" class="w-full rounded-lg border-slate-300 text-sm"></textarea>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600" x-model="form.is_active">
                            <span>Gudang aktif</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>

