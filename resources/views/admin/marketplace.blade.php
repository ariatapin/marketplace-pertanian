<x-admin-layout>
    {{-- CATATAN-AUDIT: Modul Marketplace admin difokuskan ke ringkasan konten/promo + kontrol pengajuan mitra (bukan CRUD produk). --}}
    <x-slot name="header">
        {{ __('Marketplace') }}
    </x-slot>

    <div data-testid="admin-marketplace-page" class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @php
            $currentSection = $activeSection ?? request()->query('section', 'overview');
            $isOverviewSection = $currentSection === 'overview';
            $isContentSection = $currentSection === 'content';
        @endphp

        <section data-testid="admin-marketplace-tabs" class="rounded-xl border bg-white p-4">
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('admin.modules.marketplace', ['section' => 'overview']) }}"
                    class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $isOverviewSection ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}"
                >
                    Ringkasan Marketplace
                </a>
                <a
                    href="{{ route('admin.modules.marketplace', ['section' => 'content']) }}"
                    class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $isContentSection ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}"
                >
                    Konten & Promo
                </a>
            </div>
        </section>

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

        <section data-testid="admin-marketplace-core-status" class="rounded-xl border bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status Inti Marketplace</p>
            <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-7">
                <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                    <p class="text-[11px] text-slate-500">Total Konten</p>
                    <p class="mt-0.5 text-lg font-bold text-slate-900">{{ number_format($summary['announcements_total'] ?? 0) }}</p>
                </article>
                <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                    <p class="text-[11px] text-slate-500">Konten Aktif</p>
                    <p class="mt-0.5 text-lg font-bold text-emerald-700">{{ number_format($summary['announcements_active'] ?? 0) }}</p>
                </article>
                <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                    <p class="text-[11px] text-slate-500">Promo Aktif</p>
                    <p class="mt-0.5 text-lg font-bold text-amber-700">{{ number_format($summary['promos_active'] ?? 0) }}</p>
                </article>
                <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                    <p class="text-[11px] text-slate-500">Banner Aktif</p>
                    <p class="mt-0.5 text-lg font-bold text-cyan-700">{{ number_format($summary['banners_active'] ?? 0) }}</p>
                </article>
                <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                    <p class="text-[11px] text-slate-500">Pengajuan Mitra Pending</p>
                    <p class="mt-0.5 text-lg font-bold text-indigo-700">{{ number_format($summary['mitra_pending'] ?? 0) }}</p>
                </article>
                <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                    <p class="text-[11px] text-slate-500">Status Pengajuan Mitra</p>
                    <p class="mt-0.5 text-sm font-bold {{ ($summary['mitra_submission_open'] ?? false) ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ ($summary['mitra_submission_open'] ?? false) ? 'DIBUKA' : 'DITUTUP' }}
                    </p>
                </article>
                <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                    <p class="text-[11px] text-slate-500">Otomasi Role 10 Menit</p>
                    <p class="mt-0.5 text-sm font-bold {{ ($summary['role_automation_enabled'] ?? false) ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ ($summary['role_automation_enabled'] ?? false) ? 'AKTIF' : 'NONAKTIF' }}
                    </p>
                </article>
            </div>
        </section>

        @if($isOverviewSection)
            <section class="rounded-xl border bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Tracking Performa Link Affiliate</h3>
                        <p class="mt-1 text-sm text-slate-600">Ringkasan funnel link affiliate dari klik sampai order selesai.</p>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                        Conv. selesai: {{ number_format((float) ($affiliateTrackingSummary['conversion_completed_percent'] ?? 0), 2, ',', '.') }}%
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 md:grid-cols-4">
                    <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                        <p class="text-[11px] text-slate-500">Total Klik</p>
                        <p class="mt-0.5 text-lg font-bold text-slate-900">{{ number_format((int) ($affiliateTrackingSummary['total_clicks'] ?? 0)) }}</p>
                    </article>
                    <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                        <p class="text-[11px] text-slate-500">Add to Cart</p>
                        <p class="mt-0.5 text-lg font-bold text-cyan-700">{{ number_format((int) ($affiliateTrackingSummary['total_add_to_cart'] ?? 0)) }}</p>
                    </article>
                    <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                        <p class="text-[11px] text-slate-500">Checkout</p>
                        <p class="mt-0.5 text-lg font-bold text-indigo-700">{{ number_format((int) ($affiliateTrackingSummary['total_checkout_created'] ?? 0)) }}</p>
                    </article>
                    <article class="rounded-lg border bg-slate-50 px-3 py-2.5">
                        <p class="text-[11px] text-slate-500">Order Selesai</p>
                        <p class="mt-0.5 text-lg font-bold text-emerald-700">{{ number_format((int) ($affiliateTrackingSummary['total_completed_orders'] ?? 0)) }}</p>
                    </article>
                </div>

                <div class="mt-2 text-xs text-slate-500">
                    Conv. checkout:
                    <span class="font-semibold text-slate-700">{{ number_format((float) ($affiliateTrackingSummary['conversion_checkout_percent'] ?? 0), 2, ',', '.') }}%</span>
                </div>

                <div class="mt-4 rounded-lg border border-slate-200">
                    <div class="border-b border-slate-200 px-3 py-2">
                        <p class="text-sm font-semibold text-slate-900">Top Affiliate (Order Selesai)</p>
                    </div>

                    @if(($topAffiliateTracking ?? collect())->isEmpty())
                        <div class="px-3 py-3 text-sm text-slate-600">Belum ada performa affiliate yang tercatat.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left text-slate-600">
                                        <th class="px-3 py-2 pr-4">Affiliate</th>
                                        <th class="px-3 py-2 pr-4">Email</th>
                                        <th class="px-3 py-2 pr-4">Order Selesai</th>
                                        <th class="px-3 py-2">Total Komisi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($topAffiliateTracking ?? collect()) as $row)
                                        <tr class="border-b last:border-0">
                                            <td class="px-3 py-2 pr-4 font-semibold text-slate-900">{{ $row['name'] }}</td>
                                            <td class="px-3 py-2 pr-4 text-slate-700">{{ $row['email'] }}</td>
                                            <td class="px-3 py-2 pr-4 font-semibold text-emerald-700">{{ number_format((int) ($row['completed_orders'] ?? 0)) }}</td>
                                            <td class="px-3 py-2 font-semibold text-slate-900">{{ $row['total_commission_label'] ?? 'Rp0' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <article id="affiliate-lock-policy" class="rounded-xl border bg-white p-5">
                    @php
                        $lockPolicyEnabled = (bool) old('cooldown_enabled', (bool) ($affiliateLockPolicy['cooldown_enabled'] ?? true));
                        $lockPolicyDays = max(1, min(365, (int) old('lock_days', (int) ($affiliateLockPolicy['lock_days'] ?? 30))));
                        $lockPolicyRefreshOnRepromote = (bool) old('refresh_on_repromote', (bool) ($affiliateLockPolicy['refresh_on_repromote'] ?? false));
                        $affiliateCommissionMinPercent = max(0, min(100, (float) old('affiliate_commission_min_percent', (float) ($affiliateCommissionRange['min'] ?? 0))));
                        $affiliateCommissionMaxPercent = max($affiliateCommissionMinPercent, min(100, (float) old('affiliate_commission_max_percent', (float) ($affiliateCommissionRange['max'] ?? 100))));
                    @endphp

                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Aturan Lock Affiliate</h3>
                            <p class="mt-1 text-sm text-slate-600">Pengaturan ini langsung dipakai pada sisi affiliate untuk lock/cool-down produk marketplace.</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $lockPolicyEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
                            {{ $lockPolicyEnabled ? 'AKTIF' : 'NONAKTIF' }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] text-slate-500">Durasi Lock Saat Ini</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($lockPolicyDays) }} hari</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] text-slate-500">Refresh Saat Re-Promote</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ $lockPolicyRefreshOnRepromote ? 'Ya, lock di-reset ulang' : 'Tidak, gunakan lock aktif' }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.modules.marketplace.affiliateLockPolicy.update') }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                            <input type="hidden" name="cooldown_enabled" value="0">
                            <input type="checkbox" name="cooldown_enabled" value="1" class="rounded border-slate-300 text-emerald-600" @checked($lockPolicyEnabled)>
                            <span>Aktifkan lock produk affiliate</span>
                        </label>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="affiliate-lock-days" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Durasi Lock (Hari)</label>
                                <input
                                    id="affiliate-lock-days"
                                    type="number"
                                    name="lock_days"
                                    min="1"
                                    max="365"
                                    value="{{ $lockPolicyDays }}"
                                    class="w-full rounded-lg border-slate-300 text-sm"
                                    required
                                >
                                <p class="mt-1 text-xs text-slate-500">Rentang pengaturan: 1 sampai 365 hari.</p>
                            </div>
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                <input type="hidden" name="refresh_on_repromote" value="0">
                                <input type="checkbox" name="refresh_on_repromote" value="1" class="rounded border-slate-300 text-emerald-600" @checked($lockPolicyRefreshOnRepromote)>
                                <span>Reset ulang durasi lock saat produk dipromosikan kembali</span>
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                Simpan Aturan Lock
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-3">
                        <p class="text-sm font-semibold text-indigo-900">Batas Komisi Affiliate Global</p>
                        <p class="mt-1 text-xs text-indigo-700">Rentang ini dipakai Mitra saat mengaktifkan affiliate pada produk.</p>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-lg border border-indigo-200 bg-white px-3 py-2">
                                <p class="text-[11px] text-slate-500">Komisi Minimal Aktif</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($affiliateCommissionMinPercent, 2, ',', '.') }}%</p>
                            </div>
                            <div class="rounded-lg border border-indigo-200 bg-white px-3 py-2">
                                <p class="text-[11px] text-slate-500">Komisi Maksimal Aktif</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ number_format($affiliateCommissionMaxPercent, 2, ',', '.') }}%</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.modules.marketplace.affiliateCommissionRange.update') }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @csrf
                            <div>
                                <label for="marketplace-affiliate-commission-min" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-indigo-800">Komisi Minimal (%)</label>
                                <input
                                    id="marketplace-affiliate-commission-min"
                                    type="number"
                                    name="affiliate_commission_min_percent"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="{{ number_format($affiliateCommissionMinPercent, 2, '.', '') }}"
                                    class="w-full rounded-lg border-indigo-200 text-sm"
                                    required
                                >
                            </div>
                            <div>
                                <label for="marketplace-affiliate-commission-max" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-indigo-800">Komisi Maksimal (%)</label>
                                <input
                                    id="marketplace-affiliate-commission-max"
                                    type="number"
                                    name="affiliate_commission_max_percent"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="{{ number_format($affiliateCommissionMaxPercent, 2, '.', '') }}"
                                    class="w-full rounded-lg border-indigo-200 text-sm"
                                    required
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800">
                                    Simpan Batas Komisi
                                </button>
                            </div>
                        </form>
                    </div>
                </article>
                <article class="rounded-xl border bg-white p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Informasi Pengajuan Mitra</h3>
                            <p class="mt-1 text-sm text-slate-600">Akses pengajuan mitra dibuka dari Marketplace, sedangkan proses approval tetap melalui menu Permintaan Mode.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $mitraFlag['status_class'] ?? 'border-slate-200 bg-slate-50 text-slate-700' }}">
                            {{ $mitraFlag['status_label'] ?? 'CLOSED' }}
                        </span>
                    </div>

                    <div class="mt-3 space-y-1 text-sm text-slate-700">
                        <p>Pending: <span class="font-semibold">{{ number_format((int) ($summary['mitra_pending'] ?? 0)) }}</span></p>
                        <p>Approved: <span class="font-semibold">{{ number_format((int) ($summary['mitra_approved'] ?? 0)) }}</span></p>
                        <p>Rejected: <span class="font-semibold">{{ number_format((int) ($summary['mitra_rejected'] ?? 0)) }}</span></p>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.mitraSubmission.update') }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                            <input type="hidden" name="is_enabled" value="0">
                            <input type="checkbox" name="is_enabled" value="1" class="rounded border-slate-300 text-emerald-600" @checked(old('is_enabled', (bool) ($mitraFlag['is_enabled'] ?? false)))>
                            <span>Buka Pengajuan Mitra</span>
                        </label>
                        <div>
                            <label for="mitra-description-overview" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Pesan Pengumuman</label>
                            <textarea
                                id="mitra-description-overview"
                                name="description"
                                rows="2"
                                class="w-full rounded-lg border-slate-300 text-sm"
                                placeholder="Contoh: Pengajuan mitra dibuka sampai 30 Maret 2026."
                            >{{ old('description', $mitraFlag['description'] ?? '') }}</textarea>
                        </div>
                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Simpan Status Pengajuan
                        </button>
                    </form>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('admin.modeRequests.index') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Buka Permintaan Mode
                        </a>
                    </div>

                    <div class="mt-5 border-t border-slate-200 pt-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">Kontrol Otomasi Aktivitas Role</p>
                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $automationFlag['status_class'] ?? 'border-slate-200 bg-slate-50 text-slate-700' }}">
                                {{ $automationFlag['status_label'] ?? 'NONAKTIF' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">
                            Toggle ini dipakai sebagai saklar global agar auto-process role (setiap 10 menit) bisa dihentikan saat diperlukan.
                        </p>

                        <form method="POST" action="{{ route('admin.settings.automation.update') }}" class="mt-3 space-y-3">
                            @csrf
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                <input type="hidden" name="automation_enabled" value="0">
                                <input type="checkbox" name="automation_enabled" value="1" class="rounded border-slate-300 text-emerald-600" @checked(old('automation_enabled', (bool) ($automationFlag['is_enabled'] ?? false)))>
                                <span>Aktifkan Otomasi Role Tiap 10 Menit</span>
                            </label>
                            <div>
                                <label for="automation-description-overview" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan Admin</label>
                                <textarea
                                    id="automation-description-overview"
                                    name="automation_description"
                                    rows="2"
                                    class="w-full rounded-lg border-slate-300 text-sm"
                                    placeholder="Contoh: Nonaktif saat maintenance agar tidak trigger auto-activity."
                                >{{ old('automation_description', $automationFlag['description'] ?? '') }}</textarea>
                            </div>
                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                Simpan Status Otomasi
                            </button>
                        </form>
                    </div>
                </article>
            </section>

            <section class="rounded-xl border bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Ringkasan Konten Terbaru</h3>
                <p class="mt-1 text-sm text-slate-600">Daftar ini menampilkan konten aktif/nonaktif yang digunakan pada area konten landing page.</p>
                @if((int) $announcementRowsCount < 1)
                    <p class="mt-3 text-sm text-slate-600">Belum ada konten marketplace.</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-slate-600">
                                    <th class="py-2 pr-4">Tipe</th>
                                    <th class="py-2 pr-4">Judul</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2">Jadwal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($announcementRowsView as $row)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2 pr-4">{{ $row['type_label'] }}</td>
                                        <td class="py-2 pr-4">{{ $row['title'] }}</td>
                                        <td class="py-2 pr-4">{{ $row['status_label'] }}</td>
                                        <td class="py-2">{{ $row['window_label'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @elseif($isContentSection)
            <section data-testid="admin-marketplace-content" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Manajemen Konten & Promo Landing</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Tambahkan banner, promo, atau informasi untuk ditampilkan pada landing page home.
                </p>

                <form method="POST" action="{{ route('admin.settings.announcements.store') }}" enctype="multipart/form-data" class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-6">
                    @csrf
                    <div>
                        <label for="new-type" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tipe</label>
                        <select id="new-type" name="type" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="banner" @selected(old('type') === 'banner')>Banner</option>
                            <option value="promo" @selected(old('type') === 'promo')>Promo</option>
                            <option value="info" @selected(old('type', 'info') === 'info')>Info</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label for="new-title" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Judul</label>
                        <input id="new-title" type="text" name="title" value="{{ old('title') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Contoh: Promo Panen Pekan Ini" required>
                    </div>

                    <div class="md:col-span-3 lg:col-span-2">
                        <label for="new-message" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Pesan</label>
                        <textarea id="new-message" name="message" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Deskripsi singkat promo atau informasi." required>{{ old('message') }}</textarea>
                    </div>
                    <div>
                        <label for="new-image" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Gambar</label>
                        <input id="new-image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border-slate-300 text-sm file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-slate-700">
                    </div>

                    <div>
                        <label for="new-sort-order" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Urutan</label>
                        <input id="new-sort-order" type="number" min="0" max="99" name="sort_order" value="{{ old('sort_order', 0) }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <div>
                        <label for="new-cta-label" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Label Tombol</label>
                        <input id="new-cta-label" type="text" name="cta_label" value="{{ old('cta_label') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Contoh: Lihat Detail">
                    </div>

                    <div>
                        <label for="new-cta-url" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Link Tombol</label>
                        <input id="new-cta-url" type="text" name="cta_url" value="{{ old('cta_url') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="/profile atau https://...">
                    </div>

                    <div>
                        <label for="new-starts-at" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Mulai Tayang</label>
                        <input id="new-starts-at" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <div>
                        <label for="new-ends-at" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Akhir Tayang</label>
                        <input id="new-ends-at" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600" @checked(old('is_active', true))>
                        <span>Aktif</span>
                    </label>

                    <div class="md:col-span-2 lg:col-span-6">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            Tambah Konten Landing
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Daftar Konten Saat Ini</h3>
                <p class="mt-1 text-sm text-slate-600">Konten aktif akan tampil di hero landing sesuai urutan dan periode waktu.</p>

                @if(($announcementRows ?? collect())->isEmpty())
                    <div class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                        Belum ada konten landing. Tambahkan banner atau promo di form atas.
                    </div>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach($announcementRows as $item)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $item['type_badge_class'] }}">
                                        {{ $item['type'] }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $item['status_badge_class'] }}">
                                        {{ $item['status_label'] }}
                                    </span>
                                </div>

                                <form method="POST" action="{{ route('admin.settings.announcements.update', ['announcementId' => $item['id']]) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-6">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Tipe</label>
                                        <select name="type" class="w-full rounded-lg border-slate-300 text-sm">
                                            <option value="banner" @selected($item['type'] === 'banner')>Banner</option>
                                            <option value="promo" @selected($item['type'] === 'promo')>Promo</option>
                                            <option value="info" @selected($item['type'] === 'info')>Info</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Judul</label>
                                        <input type="text" name="title" value="{{ $item['title'] }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    </div>
                                    <div class="md:col-span-3 lg:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Pesan</label>
                                        <textarea name="message" rows="2" class="w-full rounded-lg border-slate-300 text-sm" required>{{ $item['message'] }}</textarea>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Gambar</label>
                                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-lg border-slate-300 text-sm file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-slate-700">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Urutan</label>
                                        <input type="number" name="sort_order" min="0" max="99" value="{{ $item['sort_order'] }}" class="w-full rounded-lg border-slate-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Label Tombol</label>
                                        <input type="text" name="cta_label" value="{{ $item['cta_label'] }}" class="w-full rounded-lg border-slate-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Link Tombol</label>
                                        <input type="text" name="cta_url" value="{{ $item['cta_url'] }}" class="w-full rounded-lg border-slate-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Mulai Tayang</label>
                                        <input type="datetime-local" name="starts_at" value="{{ $item['starts_at_input'] }}" class="w-full rounded-lg border-slate-300 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Akhir Tayang</label>
                                        <input type="datetime-local" name="ends_at" value="{{ $item['ends_at_input'] }}" class="w-full rounded-lg border-slate-300 text-sm">
                                    </div>
                                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600" @checked((bool) $item['is_active'])>
                                        <span>Aktif</span>
                                    </label>
                                    @if(!empty($item['image_src']))
                                        <label class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
                                            <input type="checkbox" name="remove_image" value="1" class="rounded border-rose-300 text-rose-600">
                                            <span>Hapus Gambar</span>
                                        </label>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-2 md:col-span-2 lg:col-span-6">
                                        @if(!empty($item['image_src']))
                                            <img src="{{ $item['image_src'] }}" alt="{{ $item['title'] }}" class="h-16 w-24 rounded-lg border border-slate-200 object-cover">
                                        @endif
                                        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                            Simpan Perubahan
                                        </button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('admin.settings.announcements.destroy', ['announcementId' => $item['id']]) }}" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100" onclick="return confirm('Hapus konten ini dari landing?');">
                                        Hapus Konten
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-admin-layout>
