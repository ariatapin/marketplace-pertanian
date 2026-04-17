<x-admin-layout>
    <x-slot name="header">
        {{ __('Profil Admin') }}
    </x-slot>

    @php
        $user = $user ?? auth()->user();
        $avatarImageUrl = $user?->avatarImageUrl();
        $avatarInitial = $user?->avatarInitial() ?? 'U';
        $profileLocationLabel = (string) ($profileLocationLabel ?? 'Belum diset');
        $profileHasLocationSet = (bool) ($profileHasLocationSet ?? false);
        $status = (string) session('status', '');
        $statusMessage = match ($status) {
            'admin-profile-updated' => 'Informasi akun admin berhasil diperbarui.',
            'admin-avatar-updated' => 'Foto profil admin berhasil diperbarui.',
            'admin-avatar-removed' => 'Foto profil admin berhasil dihapus.',
            'admin-ops-updated' => 'Kontak operasional admin berhasil diperbarui.',
            'password-updated' => 'Password admin berhasil diperbarui.',
            default => $status,
        };
        $displayWalletBalance = (float) (session('topup_balance') ?? ($walletBalance ?? 0));
    @endphp

    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if($statusMessage !== '')
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ $statusMessage }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('topup_success'))
            <div
                x-data="{ show: true }"
                x-show="show"
                x-transition
                x-init="setTimeout(() => show = false, 3000)"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"
            >
                {{ session('topup_message', 'Topup Berhasil') }}
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="inline-flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-xl font-bold text-slate-700">
                        @if($avatarImageUrl)
                            <img src="{{ $avatarImageUrl }}" alt="Foto profil {{ $user?->name }}" class="h-full w-full object-cover">
                        @else
                            <span>{{ $avatarInitial }}</span>
                        @endif
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $user?->name }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $user?->email }}</p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">Role: {{ $user?->role }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.profile.avatar.update') }}" enctype="multipart/form-data">
                        @csrf
                        <label class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <i class="fa-regular fa-image mr-2 text-[11px]" aria-hidden="true"></i>
                            Ganti Foto
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="this.form.submit()">
                        </label>
                    </form>

                    @if(! empty($user?->avatar_path))
                        <form method="POST" action="{{ route('admin.profile.avatar.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                <i class="fa-regular fa-trash-can mr-2 text-[11px]" aria-hidden="true"></i>
                                Hapus Foto
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-sky-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Lokasi Akun Admin</h3>
                    <p class="mt-1 text-sm text-slate-600">Lokasi digunakan untuk sinkronisasi fitur cuaca dan data operasional wilayah.</p>
                    <p class="mt-2 text-sm font-semibold text-sky-700">{{ $profileLocationLabel }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $profileHasLocationSet ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                        {{ $profileHasLocationSet ? 'Lokasi Aktif' : 'Belum Diset' }}
                    </span>
                    <a href="{{ route('profile.location') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Set Lokasi
                    </a>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Topup Saldo Demo</h3>
                    <p class="mt-1 text-sm text-slate-600">Topup demo langsung aktif, tanpa proses konfirmasi manual.</p>
                </div>
                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">
                    Saldo: Rp{{ number_format($displayWalletBalance, 0, ',', '.') }}
                </span>
            </div>
            <form method="POST" action="{{ route('wallet.demo-topup') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[220px_auto] sm:items-end">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <label class="text-sm font-semibold text-slate-700">
                    Nominal Topup
                    <input type="number" name="amount" min="1000" step="1000" value="250000" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                    Topup Demo
                </button>
            </form>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Informasi Akun Admin</h3>
                <p class="mt-1 text-sm text-slate-600">Perbarui identitas admin untuk login dan kontak internal.</p>

                <form method="POST" action="{{ route('admin.profile.account.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="admin-name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</label>
                        <input id="admin-name" type="text" name="name" value="{{ old('name', $user?->name) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                        @error('name')
                            <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="admin-email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
                        <input id="admin-email" type="email" name="email" value="{{ old('email', $user?->email) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                        @error('email')
                            <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="admin-phone" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">No. Telepon</label>
                        <input id="admin-phone" type="text" name="phone_number" value="{{ old('phone_number', $user?->phone_number) }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Contoh: 0812xxxxxxx">
                        @error('phone_number')
                            <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Simpan Informasi Akun
                    </button>
                </form>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Keamanan Admin</h3>
                <p class="mt-1 text-sm text-slate-600">Ganti password admin secara berkala untuk menjaga keamanan sistem.</p>

                <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="admin-current-password" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Password Saat Ini</label>
                        <input id="admin-current-password" type="password" name="current_password" class="w-full rounded-lg border-slate-300 text-sm" autocomplete="current-password" required>
                        @if($errors->updatePassword->has('current_password'))
                            <p class="mt-1 text-xs font-semibold text-rose-600">{{ $errors->updatePassword->first('current_password') }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="admin-new-password" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Password Baru</label>
                        <input id="admin-new-password" type="password" name="password" class="w-full rounded-lg border-slate-300 text-sm" autocomplete="new-password" required>
                        @if($errors->updatePassword->has('password'))
                            <p class="mt-1 text-xs font-semibold text-rose-600">{{ $errors->updatePassword->first('password') }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="admin-new-password-confirmation" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Konfirmasi Password Baru</label>
                        <input id="admin-new-password-confirmation" type="password" name="password_confirmation" class="w-full rounded-lg border-slate-300 text-sm" autocomplete="new-password" required>
                        @if($errors->updatePassword->has('password_confirmation'))
                            <p class="mt-1 text-xs font-semibold text-rose-600">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                        @endif
                    </div>

                    <button type="submit" class="inline-flex items-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                        Simpan Password
                    </button>
                </form>
            </section>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Kontak Operasional Marketplace</h3>
            <p class="mt-1 text-sm text-slate-600">Data ini digunakan sebagai identitas kontak operasional admin.</p>

            <form method="POST" action="{{ route('admin.profile.ops.update') }}" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label for="platform-name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Platform</label>
                    <input
                        id="platform-name"
                        type="text"
                        name="platform_name"
                        value="{{ old('platform_name', $adminProfile->platform_name ?? config('app.name')) }}"
                        class="w-full rounded-lg border-slate-300 text-sm"
                        required
                    >
                    @error('platform_name')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="default-courier" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Kurir Default</label>
                    <input
                        id="default-courier"
                        type="text"
                        name="default_courier"
                        value="{{ old('default_courier', $adminProfile->default_courier ?? '') }}"
                        class="w-full rounded-lg border-slate-300 text-sm"
                        placeholder="Contoh: JNE / SiCepat / J&T"
                    >
                    @error('default_courier')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="support-email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Email Support</label>
                    <input
                        id="support-email"
                        type="email"
                        name="support_email"
                        value="{{ old('support_email', $adminProfile->support_email ?? '') }}"
                        class="w-full rounded-lg border-slate-300 text-sm"
                        placeholder="support@tokotani.com"
                    >
                    @error('support_email')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="support-phone" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Telepon Support</label>
                    <input
                        id="support-phone"
                        type="text"
                        name="support_phone"
                        value="{{ old('support_phone', $adminProfile->support_phone ?? '') }}"
                        class="w-full rounded-lg border-slate-300 text-sm"
                        placeholder="08xxxxxxxxxx"
                    >
                    @error('support_phone')
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Simpan Kontak Operasional
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-admin-layout>
