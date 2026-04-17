<section>
    <header>
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Keamanan Akun</p>
        <h3 class="mt-1 text-xl font-bold text-slate-900">
            {{ __('Perbarui Password') }}
        </h3>

        <p class="mt-2 text-sm text-slate-600">
            {{ __('Gunakan password yang kuat agar akun tetap aman saat bertransaksi.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Password Saat Ini')" class="text-slate-700" />
            <x-text-input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="mt-2 block w-full rounded-xl border-slate-300 bg-white focus:border-cyan-500 focus:ring-cyan-500"
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('Password Baru')" class="text-slate-700" />
            <x-text-input
                id="update_password_password"
                name="password"
                type="password"
                class="mt-2 block w-full rounded-xl border-slate-300 bg-white focus:border-cyan-500 focus:ring-cyan-500"
                autocomplete="new-password"
            />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Konfirmasi Password Baru')" class="text-slate-700" />
            <x-text-input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="password"
                class="mt-2 block w-full rounded-xl border-slate-300 bg-white focus:border-cyan-500 focus:ring-cyan-500"
                autocomplete="new-password"
            />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                class="inline-flex items-center rounded-xl border border-cyan-700 bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800"
            >
                {{ __('Simpan Password') }}
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-semibold text-cyan-700"
                >{{ __('Tersimpan.') }}</p>
            @endif
        </div>
    </form>
</section>
