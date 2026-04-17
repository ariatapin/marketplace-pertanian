@php
    $componentCompact = (bool) ($compact ?? false);
    $componentMinimal = (bool) ($minimal ?? false);
    $viewer = auth()->user();
    $canOpenNotificationCenter = $viewer && ! $viewer->isMitra();
    $noticeRows = collect($notifications ?? [])
        ->filter(fn ($row) => is_array($row))
        ->values();
    $noticeUnreadCount = (int) ($unreadCount ?? 0);
    $noticeStatusLabelMap = [
        'red' => 'Siaga Tinggi',
        'yellow' => 'Waspada',
        'green' => 'Normal',
        'unknown' => 'Info',
    ];
    $latestNotice = $noticeRows->first();
@endphp

<section class="{{ $componentCompact ? 'space-y-3' : 'rounded-2xl border border-slate-200 bg-white p-4 shadow-sm' }}">
    @if(!$componentCompact)
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-slate-900">Cuaca & Alert Operasional</h3>
            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $severityBadgeClass }}">
                {{ $severityLabel }}
            </span>
        </div>

        <p class="mt-2 text-sm text-slate-700">{{ $alertMessage }}</p>
        @if($validUntilLabel)
            <p class="mt-1 text-[11px] text-slate-500">Valid hingga {{ $validUntilLabel }}</p>
        @endif
    @endif

    <div class="{{ $componentCompact ? 'grid grid-cols-2 gap-2 text-xs sm:grid-cols-4' : 'mt-3 grid grid-cols-2 gap-2 text-xs' }}">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
            <p class="text-slate-500">Lokasi</p>
            <p class="mt-1 font-semibold text-slate-900">{{ $locationLabel }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
            <p class="text-slate-500">Suhu</p>
            <p class="mt-1 font-semibold text-slate-900">{{ $tempLabel }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
            <p class="text-slate-500">Kelembapan</p>
            <p class="mt-1 font-semibold text-slate-900">{{ $humidityLabel }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-2.5">
            <p class="text-slate-500">Angin</p>
            <p class="mt-1 font-semibold text-slate-900">{{ $windLabel }}</p>
        </div>
    </div>

    @if(!$componentMinimal)
        <div class="rounded-xl border px-3 py-2.5 {{ $severityPanelClass }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Aksi Operasional Disarankan</p>
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $severityBadgeClass }}">
                    {{ $severityLabel }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-800">{{ $opsAction }}</p>
            @if($componentCompact && is_array($latestNotice))
                <div class="mt-2 rounded-lg border border-slate-200/80 bg-white/80 px-2.5 py-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-700">Notifikasi cuaca terbaru</p>
                    <p class="mt-1 text-xs font-semibold text-slate-900">{{ trim((string) ($latestNotice['title'] ?? 'Notifikasi')) }}</p>
                    <p class="mt-1 text-xs text-slate-700">{{ trim((string) ($latestNotice['message'] ?? '-')) }}</p>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-800">Sinkron Notifikasi & Rekomendasi</p>
                @if($noticeRows->isNotEmpty() || $noticeUnreadCount > 0)
                    <span class="rounded-full bg-cyan-900 px-2.5 py-1 text-[11px] font-semibold text-white">
                        {{ number_format($noticeUnreadCount) }} belum dibaca
                    </span>
                @endif
            </div>
            <p class="mt-1 text-sm font-semibold text-cyan-900">{{ $adminSyncNote }}</p>
            @if($adminNoticeTitle !== '')
                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-cyan-900">{{ $adminNoticeTitle }}</p>
            @endif
            <p class="mt-1 text-xs text-cyan-800">{{ $adminNoticeMessage }}</p>
            @if($adminNoticeTimeLabel)
                <p class="mt-1 text-[11px] text-cyan-700">Update admin: {{ $adminNoticeTimeLabel }}</p>
            @endif

            @if($noticeRows->isNotEmpty())
                <ul class="mt-3 space-y-2">
                    @foreach($noticeRows->take(3) as $notice)
                        @php
                            $statusKey = strtolower((string) ($notice['status'] ?? 'unknown'));
                            $statusLabel = $noticeStatusLabelMap[$statusKey] ?? $noticeStatusLabelMap['unknown'];
                        @endphp
                        <li class="rounded-lg border border-cyan-200 bg-white px-2.5 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase {{ $notice['badge_class'] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                    {{ $statusLabel }}
                                </span>
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700">
                                    {{ strtoupper((string) ($notice['type_label'] ?? 'Notifikasi')) }}
                                </span>
                                <p class="text-[11px] font-semibold text-slate-700">{{ trim((string) ($notice['target_label'] ?? 'Wilayah Mitra')) }}</p>
                                <p class="ml-auto text-[10px] text-slate-500">{{ trim((string) ($notice['sent_at_label'] ?? '-')) }}</p>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-900">{{ trim((string) ($notice['title'] ?? 'Notifikasi Cuaca')) }}</p>
                            <p class="mt-1 text-xs text-slate-700">{{ trim((string) ($notice['message'] ?? '-')) }}</p>
                            @if(!empty($notice['valid_until_label']))
                                <p class="mt-1 text-[10px] text-slate-500">Berlaku hingga {{ $notice['valid_until_label'] }}</p>
                            @endif
                            @if(!empty($notice['is_unread']) && !empty($notice['id']))
                                <form method="POST" action="{{ route('notifications.read', (string) $notice['id']) }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $notice['filter_type'] ?? 'all' }}">
                                    @if(!empty($markReadRedirect))
                                        <input type="hidden" name="redirect_to" value="{{ $markReadRedirect }}">
                                    @endif
                                    <button type="submit" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">
                                        Tandai Dibaca
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if($canOpenNotificationCenter)
                    <a
                        href="{{ route('notifications.index', ['type' => 'all']) }}"
                        class="mt-2 inline-flex items-center rounded-md border border-cyan-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-cyan-800 hover:bg-cyan-100"
                    >
                        Lihat semua notifikasi
                    </a>
                @endif
            @endif
        </div>
    @else
        <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-800">Notifikasi Cuaca & Rekomendasi</p>
                @if($noticeRows->isNotEmpty() || $noticeUnreadCount > 0)
                    <span class="rounded-full bg-cyan-900 px-2.5 py-1 text-[11px] font-semibold text-white">
                        {{ number_format($noticeUnreadCount) }} belum dibaca
                    </span>
                @endif
            </div>

            @if($noticeRows->isNotEmpty())
                <ul class="mt-3 space-y-2">
                    @foreach($noticeRows->take(3) as $notice)
                        @php
                            $statusKey = strtolower((string) ($notice['status'] ?? 'unknown'));
                            $statusLabel = $noticeStatusLabelMap[$statusKey] ?? $noticeStatusLabelMap['unknown'];
                        @endphp
                        <li class="rounded-lg border border-cyan-200 bg-white px-2.5 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase {{ $notice['badge_class'] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                    {{ $statusLabel }}
                                </span>
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700">
                                    {{ strtoupper((string) ($notice['type_label'] ?? 'Notifikasi')) }}
                                </span>
                                <p class="text-[11px] font-semibold text-slate-700">{{ trim((string) ($notice['target_label'] ?? 'Wilayah Akun')) }}</p>
                                <p class="ml-auto text-[10px] text-slate-500">{{ trim((string) ($notice['sent_at_label'] ?? '-')) }}</p>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-900">{{ trim((string) ($notice['title'] ?? 'Notifikasi Cuaca')) }}</p>
                            <p class="mt-1 text-xs text-slate-700">{{ trim((string) ($notice['message'] ?? '-')) }}</p>
                            @if(!empty($notice['valid_until_label']))
                                <p class="mt-1 text-[10px] text-slate-500">Berlaku hingga {{ $notice['valid_until_label'] }}</p>
                            @endif
                            @if(!empty($notice['is_unread']) && !empty($notice['id']))
                                <form method="POST" action="{{ route('notifications.read', (string) $notice['id']) }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $notice['filter_type'] ?? 'all' }}">
                                    @if(!empty($markReadRedirect))
                                        <input type="hidden" name="redirect_to" value="{{ $markReadRedirect }}">
                                    @endif
                                    <button type="submit" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">
                                        Tandai Dibaca
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-xs text-cyan-800">Belum ada notifikasi cuaca atau rekomendasi untuk lokasi ini.</p>
            @endif

            @if($canOpenNotificationCenter)
                <a
                    href="{{ route('notifications.index', ['type' => 'all']) }}"
                    class="mt-2 inline-flex items-center rounded-md border border-cyan-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-cyan-800 hover:bg-cyan-100"
                >
                    Lihat semua notifikasi
                </a>
            @endif
        </div>
    @endif

    <div class="flex items-center justify-between gap-2 text-[11px]">
        <div class="inline-flex items-center gap-2">
            <span class="text-slate-500">Sumber:</span>
            <span class="inline-flex items-center rounded-full border px-2 py-0.5 font-semibold {{ $sourceBadgeClass ?? 'border-cyan-200 bg-cyan-100 text-cyan-700' }}">
                {{ $sourceLabel ?? 'OpenWeather' }}
            </span>
        </div>
        <p class="text-slate-500">Koordinat: {{ $coordinatesLabel }}</p>
    </div>
    @if(($sourceCode ?? 'openweather') === 'bmkg_fallback')
        <p class="text-[11px] font-semibold text-indigo-700">
            Fallback aktif: data cuaca sementara menggunakan BMKG.
        </p>
    @endif
</section>
