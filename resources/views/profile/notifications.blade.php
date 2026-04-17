@extends('layouts.marketplace')

@section('title', 'Notifikasi Akun')
@section('pageTitle', 'Notifikasi Akun')

@section('content')
    @php
        $notifications = $notifications ?? collect();
        $statusFilter = $statusFilter ?? 'all';
        $typeFilter = $typeFilter ?? 'all';
        $notificationCounts = array_merge([
            'all' => 0,
            'unread' => 0,
            'read' => 0,
        ], $notificationCounts ?? []);
        $notificationTypeCounts = array_merge([
            'all' => 0,
            'payment' => 0,
            'weather' => 0,
            'recommendation' => 0,
            'mitra_application' => 0,
            'system' => 0,
        ], $notificationTypeCounts ?? []);
        $hasUnread = (int) ($notificationCounts['unread'] ?? 0) > 0;
        $filterMap = [
            'all' => 'Semua',
            'unread' => 'Belum Dibaca',
            'read' => 'Sudah Dibaca',
        ];
        $typeFilterMap = [
            'all' => 'Semua Tipe',
            'payment' => 'Pembayaran',
            'weather' => 'Cuaca & Lokasi',
            'recommendation' => 'Rekomendasi',
            'mitra_application' => 'Pengajuan Mitra',
            'system' => 'Sistem',
        ];
        $summaryCards = [
            [
                'key' => 'all',
                'label' => 'Total Notifikasi',
                'class' => 'border-slate-200 bg-white text-slate-900',
                'count_class' => 'text-slate-900',
            ],
            [
                'key' => 'unread',
                'label' => 'Belum Dibaca',
                'class' => 'border-indigo-200 bg-indigo-50 text-indigo-900',
                'count_class' => 'text-indigo-700',
            ],
            [
                'key' => 'read',
                'label' => 'Sudah Dibaca',
                'class' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                'count_class' => 'text-emerald-700',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
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

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                @foreach($summaryCards as $card)
                    @php
                        $cardCount = (int) ($notificationCounts[$card['key']] ?? 0);
                    @endphp
                    <article class="rounded-xl border p-4 {{ $card['class'] }}">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em]">{{ $card['label'] }}</p>
                        <p class="mt-1 text-3xl font-extrabold leading-none {{ $card['count_class'] }}">
                            {{ number_format($cardCount) }}
                        </p>
                    </article>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-sm text-slate-700">
                    Gunakan notifikasi untuk pantau status order, pembaruan cuaca, dan update pengajuan akun.
                </p>
                @if($hasUnread)
                    <form method="POST" action="{{ route('notifications.readAll') }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $statusFilter }}">
                        <input type="hidden" name="type" value="{{ $typeFilter }}">
                        <button type="submit" class="inline-flex items-center rounded-lg border border-indigo-700 bg-indigo-700 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-800">
                            Tandai Semua Dibaca ({{ number_format((int) $notificationCounts['unread']) }})
                        </button>
                    </form>
                @else
                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Semua notifikasi sudah dibaca
                    </span>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($filterMap as $filterKey => $label)
                    @php
                        $isActive = $statusFilter === $filterKey;
                        $count = (int) ($notificationCounts[$filterKey] ?? 0);
                    @endphp
                    <a
                        href="{{ route('notifications.index', ['status' => $filterKey, 'type' => $typeFilter]) }}"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold {{ $isActive ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' }}"
                    >
                        <span>{{ $label }}</span>
                        <span class="inline-flex min-w-[22px] items-center justify-center rounded-full border px-1.5 py-0.5 text-[10px] {{ $isActive ? 'border-white/40 bg-white/15 text-white' : 'border-slate-300 bg-slate-100 text-slate-600' }}">
                            {{ number_format($count) }}
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($typeFilterMap as $typeKey => $typeLabel)
                    @php
                        $isTypeActive = $typeFilter === $typeKey;
                        $typeCount = (int) ($notificationTypeCounts[$typeKey] ?? 0);
                    @endphp
                    <a
                        href="{{ route('notifications.index', ['status' => $statusFilter, 'type' => $typeKey]) }}"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold {{ $isTypeActive ? 'border-cyan-700 bg-cyan-700 text-white' : 'border-cyan-300 bg-cyan-50 text-cyan-800 hover:bg-cyan-100' }}"
                    >
                        <span>{{ $typeLabel }}</span>
                        <span class="inline-flex min-w-[22px] items-center justify-center rounded-full border px-1.5 py-0.5 text-[10px] {{ $isTypeActive ? 'border-white/40 bg-white/15 text-white' : 'border-cyan-300 bg-white text-cyan-700' }}">
                            {{ number_format($typeCount) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="space-y-3">
            @forelse($notifications as $notification)
                @php
                    $payload = (array) ($notification->data ?? []);
                    $notifStatus = strtolower(trim((string) ($payload['status'] ?? 'info')));
                    $notifTitle = trim((string) ($payload['title'] ?? '')) ?: 'Pembaruan Sistem';
                    $notifMessage = trim((string) ($payload['message'] ?? 'Tidak ada detail tambahan untuk notifikasi ini.'));
                    $notifNotes = trim((string) ($payload['notes'] ?? ''));
                    $notifActionUrl = trim((string) ($payload['action_url'] ?? ''));
                    $notifActionLabel = trim((string) ($payload['action_label'] ?? 'Buka Detail'));
                    $isMitraApplicationNotice = $notification->type === \App\Support\MitraApplicationStatusNotification::class;
                    $isWeatherNotice = $notification->type === \App\Support\AdminWeatherNoticeNotification::class;
                    $isRecommendationNotice = $notification->type === \App\Support\BehaviorRecommendationNotification::class;
                    $isPaymentNotice = $notification->type === \App\Support\PaymentOrderStatusNotification::class;
                    $notifTypeLabel = $isMitraApplicationNotice
                        ? 'Pengajuan Mitra'
                        : ($isWeatherNotice
                            ? 'Cuaca & Lokasi'
                            : ($isRecommendationNotice ? 'Rekomendasi' : ($isPaymentNotice ? 'Pembayaran Order' : 'Sistem')));
                    $notifStatusLabel = strtoupper($notifStatus);
                    $notifStatusClass = match ($notifStatus) {
                        'approved', 'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'rejected', 'red' => 'border-rose-200 bg-rose-50 text-rose-700',
                        'pending', 'yellow' => 'border-amber-200 bg-amber-50 text-amber-700',
                        default => 'border-slate-200 bg-slate-100 text-slate-700',
                    };
                    $notifTargetLabel = trim((string) ($payload['target_label'] ?? ''));
                    $notifScopeLabel = strtoupper(trim((string) ($payload['scope'] ?? '-')));
                    $notifValidUntil = trim((string) ($payload['valid_until'] ?? ''));
                    $isExternal = \Illuminate\Support\Str::startsWith($notifActionUrl, ['http://', 'https://']);
                    $internalActionPath = '';
                    if ($notifActionUrl !== '') {
                        if (\Illuminate\Support\Str::startsWith($notifActionUrl, '/') && ! \Illuminate\Support\Str::startsWith($notifActionUrl, '//')) {
                            $internalActionPath = $notifActionUrl;
                        } elseif (filter_var($notifActionUrl, FILTER_VALIDATE_URL)) {
                            $actionHost = strtolower((string) parse_url($notifActionUrl, PHP_URL_HOST));
                            $appHost = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));
                            if ($actionHost !== '' && $appHost !== '' && $actionHost === $appHost) {
                                $actionPath = (string) parse_url($notifActionUrl, PHP_URL_PATH);
                                if ($actionPath === '') {
                                    $actionPath = '/';
                                }
                                $actionQuery = trim((string) parse_url($notifActionUrl, PHP_URL_QUERY));
                                $internalActionPath = $actionQuery !== '' ? "{$actionPath}?{$actionQuery}" : $actionPath;
                            }
                        }
                    }
                @endphp

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-2.5 py-1 text-[11px] font-semibold text-cyan-700">
                                {{ $notifTypeLabel }}
                            </span>
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $notifStatusClass }}">
                                {{ $notifStatusLabel }}
                            </span>
                            @if(is_null($notification->read_at))
                                <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700">
                                    Baru
                                </span>
                            @endif
                        </div>
                        <span class="text-xs text-slate-500">
                            {{ optional($notification->created_at)->diffForHumans() }}
                        </span>
                    </div>

                    <h3 class="mt-3 text-base font-bold text-slate-900">{{ $notifTitle }}</h3>
                    <p class="mt-1 text-sm text-slate-700">{{ $notifMessage }}</p>

                    @if($isWeatherNotice)
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-slate-600">
                            @if($notifTargetLabel !== '')
                                <span class="rounded-full border border-cyan-200 bg-cyan-50 px-2 py-1 font-semibold text-cyan-700">
                                    Target: {{ $notifTargetLabel }}
                                </span>
                            @endif
                            @if($notifScopeLabel !== '-')
                                <span class="rounded-full border border-slate-200 bg-slate-100 px-2 py-1 font-semibold">
                                    Scope: {{ $notifScopeLabel }}
                                </span>
                            @endif
                            @if($notifValidUntil !== '')
                                <span class="rounded-full border border-slate-200 bg-slate-100 px-2 py-1 font-semibold">
                                    Valid sampai: {{ $notifValidUntil }}
                                </span>
                            @endif
                        </div>
                    @endif

                    @if($notifNotes !== '')
                        <p class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Catatan admin: {{ $notifNotes }}
                        </p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if($notifActionUrl !== '')
                            @if(is_null($notification->read_at) && $internalActionPath !== '')
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $statusFilter }}">
                                    <input type="hidden" name="type" value="{{ $typeFilter }}">
                                    <input type="hidden" name="redirect_to" value="{{ $internalActionPath }}">
                                    <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                        {{ $notifActionLabel }}
                                    </button>
                                </form>
                            @else
                                <a
                                    href="{{ $notifActionUrl }}"
                                    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    @if($isExternal) target="_blank" rel="noopener noreferrer" @endif
                                >
                                    {{ $notifActionLabel }}
                                </a>
                            @endif
                        @endif

                        @if(is_null($notification->read_at))
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $statusFilter }}">
                                <input type="hidden" name="type" value="{{ $typeFilter }}">
                                <button type="submit" class="inline-flex items-center rounded-lg border border-indigo-700 bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-800">
                                    Tandai Dibaca
                                </button>
                            </form>
                        @else
                            <span class="inline-flex rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                                Sudah Dibaca
                            </span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-600">
                    Tidak ada notifikasi untuk filter ini.
                </div>
            @endforelse
        </section>

        @if(method_exists($notifications, 'links') && $notifications->hasPages())
            <div class="pt-1">
                {{ $notifications->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
