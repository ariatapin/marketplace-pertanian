<section>
    <header>
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Identitas Akun</p>
        <h3 class="mt-1 text-xl font-bold text-slate-900">
            {{ __('Informasi Profil') }}
        </h3>
        <p class="mt-2 text-sm text-slate-600">
            {{ __('Perbarui nama dan email agar akun tetap sinkron dengan aktivitas marketplace.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Nama')" class="text-slate-700" />
            <x-text-input
                id="name"
                name="name"
                type="text"
                class="mt-2 block w-full rounded-xl border-slate-300 bg-white focus:border-emerald-500 focus:ring-emerald-500"
                :value="old('name', $user->name)"
                required
                autofocus
                autocomplete="name"
            />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" class="text-slate-700" />
            <x-text-input
                id="email"
                name="email"
                type="email"
                class="mt-2 block w-full rounded-xl border-slate-300 bg-white focus:border-emerald-500 focus:ring-emerald-500"
                :value="old('email', $user->email)"
                required
                autocomplete="username"
            />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-sm text-amber-800">
                        {{ __('Alamat email kamu belum terverifikasi.') }}
                    </p>
                    <button
                        form="send-verification"
                        class="mt-2 inline-flex items-center rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                    >
                        {{ __('Kirim Ulang Verifikasi') }}
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-semibold text-emerald-700">
                            {{ __('Link verifikasi baru sudah dikirim ke email kamu.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                class="inline-flex items-center rounded-xl border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
            >
                {{ __('Simpan Perubahan') }}
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-semibold text-emerald-700"
                >{{ __('Tersimpan.') }}</p>
            @endif
        </div>
    </form>
</section>
