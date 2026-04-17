@extends('layouts.marketplace')

@section('title', 'Program Mitra B2B')
@section('pageTitle', 'Program Mitra B2B')

@section('content')
    @php
        $user = $user ?? auth()->user();
        $mitraApplication = $mitraApplication ?? null;
        $mitraSubmissionOpen = (bool) ($mitraSubmissionOpen ?? false);
        $mitraSubmissionNote = trim((string) ($mitraSubmissionNote ?? ''));
        $mitraNotifications = $mitraNotifications ?? collect();
        $unreadMitraNotificationCount = (int) ($unreadMitraNotificationCount ?? 0);
        $mitraModeWarning = is_array($mitraModeWarning ?? null) ? $mitraModeWarning : ['has_active_mode' => false];
        $mitraApplicationStatus = strtolower((string) ($mitraApplication?->status ?? 'none'));
        $canEditMitraApplication = in_array($mitraApplicationStatus, ['none', 'draft', 'rejected'], true);
        $mitraStatusBadgeClass = match ($mitraApplicationStatus) {
            'pending' => 'border-amber-200 bg-amber-50 text-amber-700',
            'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
            'draft' => 'border-slate-200 bg-slate-100 text-slate-700',
            default => 'border-slate-200 bg-slate-100 text-slate-700',
        };
        $statusLabel = match ($mitraApplicationStatus) {
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'draft' => 'Draft',
            default => 'Belum Ada Pengajuan',
        };
    @endphp

    <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
        <section class="surface-card p-5 sm:p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Program Mitra B2B</p>
                    <h2 class="mt-1 text-2xl font-extrabold leading-tight text-slate-900">Pengajuan Mitra Pengadaan Admin</h2>
                    <p class="mt-2 text-sm text-slate-600">Flow ini terpisah dari pengajuan Penjual/Affiliate di halaman profil agar jalur B2B dan P2P tetap jelas.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $mitraSubmissionOpen ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
                        {{ $mitraSubmissionOpen ? 'DIBUKA' : 'DITUTUP' }}
                    </span>
                    <a
                        href="{{ route('landing') }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        <i class="fa-solid fa-store text-[10px]" aria-hidden="true"></i>
                        <span>Kembali ke Marketplace</span>
                    </a>
                </div>
            </div>
            @if($mitraSubmissionNote !== '')
                <div class="mt-4 rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
                    {{ $mitraSubmissionNote }}
                </div>
            @endif
        </section>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section id="mitra-notifications" class="surface-card border border-indigo-200 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-700">Notifikasi Pengajuan Mitra</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Update Status dari Admin</h3>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('notifications.index', ['type' => 'mitra_application']) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                        Buka Notification Center
                    </a>
                    @if($unreadMitraNotificationCount > 0)
                        <form method="POST" action="{{ route('profile.notifications.read') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg border border-indigo-700 bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-800">
                                Tandai Dibaca ({{ number_format($unreadMitraNotificationCount) }})
                            </button>
                        </form>
                    @else
                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            Tidak ada notifikasi baru
                        </span>
                    @endif
                </div>
            </div>

            @if($mitraNotifications->isEmpty())
                <div class="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Belum ada notifikasi status pengajuan mitra.
                </div>
            @else
                <div class="mt-4 space-y-3">
                    @foreach($mitraNotifications as $notification)
                        @php
                            $payload = (array) ($notification->data ?? []);
                            $notifStatus = strtolower((string) ($payload['status'] ?? 'info'));
                            $notifBadge = match ($notifStatus) {
                                'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
                                'pending' => 'border-amber-200 bg-amber-50 text-amber-700',
                                default => 'border-slate-200 bg-slate-100 text-slate-700',
                            };
                            $notifTitle = (string) ($payload['title'] ?? 'Update Pengajuan Mitra');
                            $notifMessage = (string) ($payload['message'] ?? 'Ada update terbaru untuk pengajuan mitra Anda.');
                            $notifActionUrl = (string) ($payload['action_url'] ?? (route('program.mitra.form') . '#mitra-application'));
                            $notifActionLabel = (string) ($payload['action_label'] ?? 'Lihat Detail');
                            $notifNotes = trim((string) ($payload['notes'] ?? ''));
                        @endphp
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase {{ $notifBadge }}">
                                        {{ $notifStatus }}
                                    </span>
                                    @if(is_null($notification->read_at))
                                        <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">
                                            Baru
                                        </span>
                                    @endif
                                </div>
                                <span class="text-[11px] text-slate-500">{{ optional($notification->created_at)->diffForHumans() }}</span>
                            </div>
                            <p class="mt-2 text-sm font-bold text-slate-900">{{ $notifTitle }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ $notifMessage }}</p>
                            @if($notifNotes !== '')
                                <p class="mt-1 text-xs text-slate-600">Catatan admin: {{ $notifNotes }}</p>
                            @endif
                            <a href="{{ $notifActionUrl }}" class="mt-2 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                {{ $notifActionLabel }}
                            </a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section id="mitra-application" class="surface-card border border-cyan-200 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Pengajuan Mitra B2B</p>
                    <h3 class="mt-1 text-xl font-bold text-slate-900">Form Mitra Pengadaan Admin</h3>
                    <p class="mt-1 text-sm text-slate-600">Admin membuka pengajuan ini dari banner/promo marketplace, bukan dari menu mode Penjual/Affiliate.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $mitraStatusBadgeClass }}">
                        {{ $statusLabel }}
                    </span>
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $mitraSubmissionOpen ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
                        {{ $mitraSubmissionOpen ? 'DIBUKA' : 'DITUTUP' }}
                    </span>
                </div>
            </div>

            <div class="mt-3 rounded-xl border px-4 py-3 text-sm {{ $mitraSubmissionOpen ? 'border-cyan-200 bg-cyan-50 text-cyan-800' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
                <p class="font-semibold">
                    {{ $mitraSubmissionOpen ? 'Pengajuan Mitra B2B sedang dibuka admin melalui promo/banner marketplace.' : 'Pengajuan Mitra B2B sedang ditutup admin.' }}
                </p>
                @if($mitraSubmissionNote !== '')
                    <p class="mt-1">{{ $mitraSubmissionNote }}</p>
                @endif
            </div>

            @if($mitraApplication?->notes)
                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <p class="font-semibold">Catatan Admin:</p>
                    <p class="mt-1">{{ $mitraApplication->notes }}</p>
                </div>
            @endif

            @if(! $mitraSubmissionOpen)
                <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    Pengajuan mitra B2B sedang ditutup oleh admin. Form tidak dapat disubmit saat ini.
                </div>
            @endif

            @if((bool) ($mitraModeWarning['has_active_mode'] ?? false))
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">Perhatian sebelum kirim pengajuan</p>
                    <p class="mt-1">{{ $mitraModeWarning['message'] ?? '' }}</p>
                </div>
            @endif

            @if($mitraApplicationStatus === 'approved')
                <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    Pengajuan sudah disetujui. Akun Anda telah diaktifkan sebagai mitra.
                    <a href="{{ route('mitra.dashboard') }}" class="ml-2 font-semibold underline">Buka Dashboard Mitra</a>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('program.mitra.storeOrSubmit') }}"
                enctype="multipart/form-data"
                class="mt-5 space-y-4"
            >
                @csrf

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="mitra-full-name" class="mb-1 block text-sm font-semibold text-slate-700">Nama Lengkap</label>
                        <input id="mitra-full-name" type="text" name="full_name" value="{{ old('full_name', $mitraApplication?->full_name ?? $user?->name) }}" class="w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }} required>
                    </div>
                    <div>
                        <label for="mitra-email" class="mb-1 block text-sm font-semibold text-slate-700">Email</label>
                        <input id="mitra-email" type="email" name="email" value="{{ old('email', $mitraApplication?->email ?? $user?->email) }}" class="w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }} required>
                    </div>
                    <div>
                        <label for="mitra-region-id" class="mb-1 block text-sm font-semibold text-slate-700">Region ID (opsional)</label>
                        <input id="mitra-region-id" type="number" name="region_id" value="{{ old('region_id', $mitraApplication?->region_id) }}" class="w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }}>
                    </div>
                    <div>
                        <label for="mitra-warehouse-capacity" class="mb-1 block text-sm font-semibold text-slate-700">Kapasitas Gudang (kg)</label>
                        <input id="mitra-warehouse-capacity" type="number" min="1" name="warehouse_capacity" value="{{ old('warehouse_capacity', $mitraApplication?->warehouse_capacity) }}" class="w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }}>
                    </div>
                    <div class="md:col-span-2">
                        <label for="mitra-warehouse-address" class="mb-1 block text-sm font-semibold text-slate-700">Alamat Gudang</label>
                        <textarea id="mitra-warehouse-address" name="warehouse_address" rows="2" class="w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }}>{{ old('warehouse_address', $mitraApplication?->warehouse_address) }}</textarea>
                    </div>
                    <div>
                        <label for="mitra-warehouse-lat" class="mb-1 block text-sm font-semibold text-slate-700">Latitude Gudang</label>
                        <input id="mitra-warehouse-lat" type="text" name="warehouse_lat" value="{{ old('warehouse_lat', $mitraApplication?->warehouse_lat) }}" class="w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }}>
                    </div>
                    <div>
                        <label for="mitra-warehouse-lng" class="mb-1 block text-sm font-semibold text-slate-700">Longitude Gudang</label>
                        <input id="mitra-warehouse-lng" type="text" name="warehouse_lng" value="{{ old('warehouse_lng', $mitraApplication?->warehouse_lng) }}" class="w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }}>
                    </div>
                    <div class="md:col-span-2">
                        <label for="mitra-products-managed" class="mb-1 block text-sm font-semibold text-slate-700">Produk yang Dikelola</label>
                        <textarea id="mitra-products-managed" name="products_managed" rows="2" class="w-full rounded-lg border-slate-300 px-3 py-2.5 text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }}>{{ old('products_managed', $mitraApplication?->products_managed) }}</textarea>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @php
                        $docConfigs = [
                            ['label' => 'KTP', 'field' => 'ktp_file', 'column' => 'ktp_url'],
                            ['label' => 'NPWP', 'field' => 'npwp_file', 'column' => 'npwp_url'],
                            ['label' => 'NIB', 'field' => 'nib_file', 'column' => 'nib_url'],
                            ['label' => 'Foto Bangunan Gudang', 'field' => 'warehouse_photo_file', 'column' => 'warehouse_building_photo_url'],
                            ['label' => 'Sertifikasi (opsional)', 'field' => 'certification_file', 'column' => 'special_certification_url'],
                        ];
                    @endphp
                    @foreach($docConfigs as $doc)
                        @php
                            $value = trim((string) ($mitraApplication?->{$doc['column']} ?? ''));
                            $url = '';
                            if ($value !== '') {
                                if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://', '/storage/', 'storage/'])) {
                                    $url = $value;
                                } else {
                                    $url = asset('storage/' . ltrim($value, '/'));
                                }
                            }
                        @endphp
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <label class="mb-1 block text-sm font-semibold text-slate-700">{{ $doc['label'] }}</label>
                            <input type="file" name="{{ $doc['field'] }}" class="w-full rounded-lg border-slate-300 bg-white text-sm" {{ $canEditMitraApplication ? '' : 'disabled' }}>
                            @if($url !== '')
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-xs font-semibold text-indigo-700 hover:text-indigo-900">
                                    Lihat dokumen tersimpan
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($canEditMitraApplication)
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" name="action" value="draft" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            Simpan Draft
                        </button>
                        <button
                            type="submit"
                            name="action"
                            value="submit"
                            class="inline-flex items-center rounded-lg border px-4 py-2 text-sm font-semibold {{ $mitraSubmissionOpen ? 'border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800' : 'cursor-not-allowed border-slate-300 bg-slate-200 text-slate-500' }}"
                            {{ $mitraSubmissionOpen ? '' : 'disabled' }}
                        >
                            Kirim Pengajuan
                        </button>
                    </div>
                @else
                    <p class="text-sm font-semibold text-slate-600">
                        Form dikunci karena status pengajuan saat ini {{ strtoupper($mitraApplicationStatus) }}.
                    </p>
                @endif
            </form>
        </section>
    </div>
@endsection
