@guest
    <div
        x-show="authModalOpen"
        x-cloak
        class="mk-auth-overlay fixed inset-0 z-50 flex items-center justify-center bg-slate-950/65 px-4 py-6"
        @keydown.escape.window="closeAuth()"
    >
        <div class="mk-auth-panel w-full max-w-[640px] max-h-[calc(100vh-2.4rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="mk-auth-head">
                <div>
                    <h3 class="mk-auth-title" x-text="authMode === 'login' ? 'Login Marketplace' : 'Daftar Akun Baru'"></h3>
                    <p class="mk-auth-subtitle" x-text="authMode === 'login' ? 'Masuk untuk checkout dan pantau pesanan.' : 'Buat akun baru untuk mulai belanja dan berjualan.'"></p>
                </div>
                <button type="button" class="mk-auth-close inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label="Tutup" @click="closeAuth()">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="mk-auth-tabs">
                <button
                    type="button"
                    class="mk-auth-tab"
                    :class="{ 'is-active': authMode === 'login' }"
                    @click="authMode = 'login'"
                >
                    Login
                </button>
                <button
                    type="button"
                    class="mk-auth-tab"
                    :class="{ 'is-active': authMode === 'register' }"
                    @click="authMode = 'register'"
                >
                    Daftar
                </button>
            </div>

            <div class="mk-auth-content overflow-y-auto px-5 py-4">
                @if($errors->any())
                    <div class="mk-auth-error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mk-auth-form" x-show="authMode === 'login'">
                    @csrf
                    <input type="hidden" name="auth_form" value="login">
                    <label class="mk-auth-label">
                        Email
                        <input type="email" name="email" value="{{ old('auth_form') === 'login' ? old('email') : '' }}" class="mk-auth-input" required>
                    </label>
                    <label class="mk-auth-label">
                        Password
                        <input type="password" name="password" class="mk-auth-input" required>
                    </label>
                    <label class="mk-auth-remember">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span>Ingat saya</span>
                    </label>
                    <button type="submit" class="mk-auth-submit">
                        Login
                    </button>

                    <div class="mk-auth-divider">
                        <span></span>
                        <em>atau</em>
                        <span></span>
                    </div>

                    <a
                        href="{{ route('auth.google.redirect') }}"
                        class="mk-auth-google"
                    >
                        <i class="fa-brands fa-google text-base text-rose-500" aria-hidden="true"></i>
                        <span>Login dengan Google</span>
                    </a>
                </form>

                <form
                    method="POST"
                    action="{{ route('register') }}"
                    class="mk-auth-form"
                    x-show="authMode === 'register'"
                    x-data="regionPicker({
                        provinceId: {{ old('auth_form') === 'register' ? (int) old('province_id', 0) : 0 }},
                        cityId: {{ old('auth_form') === 'register' ? (int) old('city_id', 0) : 0 }},
                        districtId: {{ old('auth_form') === 'register' ? (int) old('district_id', 0) : 0 }},
                    })"
                    x-init="init()"
                >
                    @csrf
                    <input type="hidden" name="auth_form" value="register">
                    <label class="mk-auth-label">
                        Nama Lengkap
                        <input type="text" name="name" value="{{ old('auth_form') === 'register' ? old('name') : '' }}" class="mk-auth-input" required>
                    </label>
                    <label class="mk-auth-label">
                        Email
                        <input type="email" name="email" value="{{ old('auth_form') === 'register' ? old('email') : '' }}" class="mk-auth-input" required>
                    </label>
                    <label class="mk-auth-label">
                        Nomor HP
                        <input type="text" name="phone_number" value="{{ old('auth_form') === 'register' ? old('phone_number') : '' }}" class="mk-auth-input" required>
                    </label>
                    <label class="mk-auth-label">
                        Provinsi
                        <select
                            name="province_id"
                            x-model="provinceId"
                            @change="onProvinceChange()"
                            class="mk-auth-input"
                            required
                        >
                            <option value="">Pilih Provinsi</option>
                            <template x-for="province in provinces" :key="province.id">
                                <option :value="province.id" x-text="province.name"></option>
                            </template>
                        </select>
                    </label>
                    <label class="mk-auth-label">
                        Kota/Kabupaten
                        <select
                            name="city_id"
                            x-model="cityId"
                            @change="onCityChange()"
                            :disabled="!provinceId"
                            class="mk-auth-input disabled:bg-slate-100 disabled:text-slate-500"
                            required
                        >
                            <option value="">Pilih Kota/Kabupaten</option>
                            <template x-for="city in cities" :key="city.id">
                                <option :value="city.id" x-text="(city.type ? (city.type + ' ') : '') + city.name"></option>
                            </template>
                        </select>
                    </label>
                    <label class="mk-auth-label">
                        Kecamatan (Opsional)
                        <select
                            name="district_id"
                            x-model="districtId"
                            :disabled="!cityId"
                            class="mk-auth-input disabled:bg-slate-100 disabled:text-slate-500"
                        >
                            <option value="">Pilih Kecamatan</option>
                            <template x-for="district in districts" :key="district.id">
                                <option :value="district.id" x-text="district.name"></option>
                            </template>
                        </select>
                        <span class="mt-1 text-[11px] text-slate-500" x-show="selectedCityLatLng">
                            Koordinat kota: <span x-text="selectedCityLatLng"></span>
                        </span>
                    </label>
                    <label class="mk-auth-label">
                        Password
                        <input type="password" name="password" class="mk-auth-input" required>
                    </label>
                    <label class="mk-auth-label">
                        Konfirmasi Password
                        <input type="password" name="password_confirmation" class="mk-auth-input" required>
                    </label>
                    <button type="submit" class="mk-auth-submit mk-auth-submit-alt">
                        Daftar Akun
                    </button>

                    <div class="mk-auth-divider">
                        <span></span>
                        <em>atau</em>
                        <span></span>
                    </div>

                    <a
                        href="{{ route('auth.google.redirect') }}"
                        class="mk-auth-google"
                    >
                        <i class="fa-brands fa-google text-base text-rose-500" aria-hidden="true"></i>
                        <span>Lanjut dengan Google</span>
                    </a>
                </form>
            </div>
        </div>
    </div>
@endguest
