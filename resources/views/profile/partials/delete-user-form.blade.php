<section class="space-y-5">
    <header>
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Zona Berisiko</p>
        <h3 class="mt-1 text-xl font-bold text-slate-900">
            {{ __('Hapus Akun') }}
        </h3>

        <p class="mt-2 text-sm text-slate-600">
            {{ __('Jika akun dihapus, seluruh data yang terhubung akan dihapus permanen dan tidak bisa dipulihkan.') }}
        </p>
    </header>

    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
        {{ __('Sebelum menghapus akun, pastikan tidak ada transaksi aktif yang masih berjalan.') }}
    </div>

    <button
        type="button"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="inline-flex items-center rounded-xl border border-rose-700 bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800"
    >
        {{ __('Hapus Akun Permanen') }}
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-bold text-slate-900">
                {{ __('Konfirmasi Hapus Akun') }}
            </h2>

            <p class="mt-2 text-sm text-slate-600">
                {{ __('Tindakan ini tidak bisa dibatalkan. Masukkan password untuk melanjutkan proses hapus akun.') }}
            </p>

            <div class="mt-5">
                <x-input-label for="password" value="{{ __('Password') }}" class="text-slate-700" />
                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-2 block w-full rounded-xl border-slate-300 bg-white focus:border-rose-500 focus:ring-rose-500"
                    placeholder="{{ __('Password') }}"
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    x-on:click="$dispatch('close')"
                    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                >
                    {{ __('Batal') }}
                </button>

                <button
                    type="submit"
                    class="inline-flex items-center rounded-lg border border-rose-700 bg-rose-700 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-800"
                >
                    {{ __('Ya, Hapus Akun') }}
                </button>
            </div>
        </form>
    </x-modal>
</section>
