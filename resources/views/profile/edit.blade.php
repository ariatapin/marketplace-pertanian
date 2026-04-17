<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Pengaturan Akun</p>
                <h2 class="text-2xl font-extrabold leading-tight text-slate-900">
                    {{ __('Profil Saya') }}
                </h2>
            </div>
            <a
                href="{{ route('landing') }}"
                class="ml-auto inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100"
            >
                <i class="fa-solid fa-store text-xs" aria-hidden="true"></i>
                <span>Kembali ke Marketplace</span>
            </a>
        </div>
    </x-slot>

    @php
        $user = auth()->user();
        $consumerProfile = $consumerProfile ?? null;
        $avatarImageUrl = $user?->avatarImageUrl();
        $avatarInitial = $user?->avatarInitial() ?? 'U';

        $mode = $consumerProfile?->mode ?? 'buyer';
        $modeStatus = $consumerProfile?->mode_status ?? 'none';
        $requestedMode = $consumerProfile?->requested_mode;
        $affiliateActive = $mode === 'affiliate' && $modeStatus === 'approved';
        $sellerActive = $mode === 'farmer_seller' && $modeStatus === 'approved';
        $hasPending = $modeStatus === 'pending';
        $pendingAffiliate = $hasPending && $requestedMode === 'affiliate';
        $pendingSeller = $hasPending && $requestedMode === 'farmer_seller';
        $canRequestAffiliate = ! $affiliateActive && ! $sellerActive && ! $hasPending;
        $canRequestSeller = ! $sellerActive && ! $affiliateActive && ! $hasPending;
        $profileLocationLabel = (string) ($profileLocationLabel ?? 'Belum diset');
        $profileHasLocationSet = (bool) ($profileHasLocationSet ?? false);

        $showGlobalStatus = session('status') && ! in_array(session('status'), ['profile-updated', 'password-updated'], true);
        $displayWalletBalance = (float) (session('topup_balance') ?? ($walletBalance ?? 0));
    @endphp

    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="surface-card overflow-hidden border border-emerald-200/80 bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-600 p-0 text-white shadow-sm">
            <div class="relative p-5 sm:p-6">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.22),transparent_48%)]"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="inline-flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-white/40 bg-white/15 text-2xl font-bold ring-2 ring-white/35">
                            @if($avatarImageUrl)
                                <img src="{{ $avatarImageUrl }}" alt="Foto profil {{ $user?->name }}" class="h-full w-full object-cover">
                            @else
                                <span>{{ $avatarInitial }}</span>
                            @endif
                        </span>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-emerald-100/95">Profil Akun</p>
                            <p class="text-2xl font-extrabold leading-tight">{{ $user?->name }}</p>
                            <p class="text-sm text-emerald-50/95">{{ $user?->email }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data">
                            @csrf
                            <label class="inline-flex cursor-pointer items-center rounded-xl border border-white/35 bg-white/15 px-4 py-2 text-xs font-semibold text-white transition hover:bg-white/25">
                                <i class="fa-regular fa-image mr-2 text-[11px]"></i>
                                Ganti Foto
                                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="this.form.submit()">
                            </label>
                        </form>

                        @if(! empty($user?->avatar_path))
                            <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center rounded-xl border border-rose-200/90 bg-rose-100 px-4 py-2 text-xs font-semibold text-rose-800 transition hover:bg-rose-200">
                                    <i class="fa-regular fa-trash-can mr-2 text-[11px]"></i>
                                    Hapus Foto
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="relative mt-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border border-white/35 bg-white/15 px-3 py-1 text-xs font-semibold text-white">
                        Lokasi: {{ $profileLocationLabel }}
                    </span>
                    <a
                        href="{{ route('profile.location') }}"
                        class="inline-flex items-center rounded-xl border border-white/35 bg-white/15 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/25"
                    >
                        Set Lokasi
                    </a>
                </div>
            </div>
        </section>

        @if($showGlobalStatus)
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if(session('topup_success'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-transition
                x-init="setTimeout(() => show = false, 3000)"
                class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"
            >
                {{ session('topup_message', 'Topup Berhasil') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section class="surface-card border border-emerald-200 p-5 sm:p-6">
                @include('profile.partials.update-profile-information-form')
            </section>

            <section class="surface-card border border-cyan-200 p-5 sm:p-6">
                @include('profile.partials.update-password-form')
            </section>
        </div>

        <section class="surface-card mt-6 border border-emerald-200 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Saldo Demo</p>
                    <h3 class="mt-1 text-xl font-bold text-slate-900">Topup Cepat</h3>
                    <p class="mt-1 text-sm text-slate-600">Topup ini bersifat demo dan langsung masuk tanpa konfirmasi admin.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800">
                    Saldo: Rp{{ number_format($displayWalletBalance, 0, ',', '.') }}
                </span>
            </div>
            <form method="POST" action="{{ route('wallet.demo-topup') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[200px_auto] sm:items-end">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <label class="text-sm font-semibold text-slate-700">
                    Nominal Topup
                    <input
                        type="number"
                        name="amount"
                        min="1000"
                        step="1000"
                        value="100000"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                </label>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                >
                    Topup Demo
                </button>
            </form>
        </section>

        <section class="surface-card mt-6 border border-sky-200 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Wilayah Akun</p>
                    <h3 class="mt-1 text-xl font-bold text-slate-900">Lokasi Consumer, Mitra, Seller, dan Affiliate</h3>
                    <p class="mt-1 text-sm text-slate-600">Lokasi akun dipakai untuk notifikasi cuaca, prioritas produk terdekat, dan distribusi operasional.</p>
                </div>
                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $profileHasLocationSet ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                    {{ $profileHasLocationSet ? 'Lokasi Sudah Diatur' : 'Lokasi Belum Diatur' }}
                </span>
            </div>

            <p class="mt-3 text-sm font-semibold text-slate-700">
                Lokasi aktif: <span class="text-sky-700">{{ $profileLocationLabel }}</span>
            </p>

            <form
                method="POST"
                action="{{ route('profile.location.save') }}"
                x-data="regionPicker({
                    provinceId: {{ (int) old('province_id', (int) ($user?->province_id ?? 0)) }},
                    cityId: {{ (int) old('city_id', (int) ($user?->city_id ?? 0)) }},
                    districtId: {{ (int) old('district_id', (int) ($user?->district_id ?? 0)) }},
                })"
                x-init="init()"
                class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2"
            >
                @csrf

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Provinsi</label>
                    <select
                        name="province_id"
                        x-model="provinceId"
                        @change="onProvinceChange()"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        required
                    >
                        <option value="">Pilih Provinsi</option>
                        <template x-for="p in provinces" :key="p.id">
                            <option :value="p.id" x-text="p.name"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kota / Kabupaten</label>
                    <select
                        name="city_id"
                        x-model="cityId"
                        @change="onCityChange()"
                        :disabled="!provinceId"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500"
                        required
                    >
                        <option value="">Pilih Kota/Kabupaten</option>
                        <template x-for="c in cities" :key="c.id">
                            <option :value="c.id" x-text="(c.type ? (c.type + ' ') : '') + c.name"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kecamatan (opsional)</label>
                    <select
                        name="district_id"
                        x-model="districtId"
                        :disabled="!cityId"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:bg-slate-100 disabled:text-slate-500"
                    >
                        <option value="">Pilih Kecamatan</option>
                        <template x-for="d in districts" :key="d.id">
                            <option :value="d.id" x-text="d.name"></option>
                        </template>
                    </select>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Koordinat Kota</p>
                    <p class="mt-1 text-sm font-semibold text-slate-700" x-text="selectedCityLatLng || '-'"></p>
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl border border-sky-700 bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800"
                    >
                        Simpan Lokasi
                    </button>
                    <a
                        href="{{ route('profile.location') }}"
                        class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Buka Halaman Lokasi Penuh
                    </a>
                </div>
            </form>
        </section>

        @if(($user?->role ?? null) === 'consumer')
            <section id="mode-consumer" class="surface-card mt-6 border border-amber-200 p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Mode Consumer P2P</p>
                        <h3 class="mt-1 text-xl font-bold text-slate-900">Pengajuan Affiliate dan Penjual Hasil Tani</h3>
                    </div>
                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                        Satu fitur aktif dalam satu waktu
                    </span>
                </div>

                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Jalur ini khusus <strong>P2P marketplace</strong>: pilih <strong>Affiliate</strong> atau <strong>Penjual hasil tani</strong>. Jika satu jalur aktif atau pending, jalur lain otomatis dinonaktifkan.
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <article class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
                        <h4 class="text-base font-bold text-slate-900">Ajukan Affiliate</h4>
                        <p class="mt-1 text-sm text-slate-600">Dapatkan komisi dari promosi produk marketplace.</p>
                        <form method="POST" action="{{ route('profile.requestAffiliate') }}" class="mt-3">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold {{ $canRequestAffiliate ? 'border-indigo-700 bg-indigo-700 text-white hover:bg-indigo-800' : 'cursor-not-allowed border-slate-300 bg-slate-200 text-slate-500' }}"
                                {{ $canRequestAffiliate ? '' : 'disabled' }}
                            >
                                Ajukan Affiliate
                            </button>
                        </form>
                        @if($affiliateActive)
                            <p class="mt-2 text-xs font-semibold text-emerald-700">Mode affiliate sudah aktif.</p>
                        @elseif($pendingAffiliate)
                            <p class="mt-2 text-xs font-semibold text-amber-700">Pengajuan affiliate sedang diproses.</p>
                        @elseif($sellerActive)
                            <p class="mt-2 text-xs font-semibold text-rose-700">Tidak tersedia karena mode penjual sudah aktif.</p>
                        @elseif($pendingSeller)
                            <p class="mt-2 text-xs font-semibold text-rose-700">Tidak tersedia karena pengajuan penjual masih pending.</p>
                        @endif
                    </article>

                    <article class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                        <h4 class="text-base font-bold text-slate-900">Ajukan Penjual</h4>
                        <p class="mt-1 text-sm text-slate-600">Khusus penjualan hasil tani di marketplace P2P.</p>
                        <form method="POST" action="{{ route('profile.requestFarmerSeller') }}" class="mt-3">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold {{ $canRequestSeller ? 'border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800' : 'cursor-not-allowed border-slate-300 bg-slate-200 text-slate-500' }}"
                                {{ $canRequestSeller ? '' : 'disabled' }}
                            >
                                Ajukan Penjual P2P
                            </button>
                        </form>
                        @if($sellerActive)
                            <p class="mt-2 text-xs font-semibold text-emerald-700">Mode penjual sudah aktif.</p>
                        @elseif($pendingSeller)
                            <p class="mt-2 text-xs font-semibold text-amber-700">Pengajuan penjual sedang diproses.</p>
                        @elseif($affiliateActive)
                            <p class="mt-2 text-xs font-semibold text-rose-700">Tidak tersedia karena mode affiliate sudah aktif.</p>
                        @elseif($pendingAffiliate)
                            <p class="mt-2 text-xs font-semibold text-rose-700">Tidak tersedia karena pengajuan affiliate masih pending.</p>
                        @endif
                    </article>
                </div>
            </section>

        @endif

        <section class="surface-card mt-6 border border-rose-200 p-5 sm:p-6">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-app-layout>
