<!doctype html>
<html lang="id" data-livewire-page="true">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/js/panel.js','resources/css/app.css','resources/css/admin.css'])
    @livewireStyles
</head>
<body
    class="adminlte-modern min-h-screen antialiased"
    x-data="{ adminSidebarOpen: false, adminProfileMenuOpen: false }"
    :class="{ 'admin-no-scroll': adminSidebarOpen }"
    @keydown.escape.window="adminSidebarOpen = false; adminProfileMenuOpen = false"
>
    @php
        $authUser = auth()->user();
        $avatarImageUrl = $authUser?->avatarImageUrl();
        $avatarInitial = $authUser?->avatarInitial() ?? 'U';
        $pendingProcurementNotificationCount = (int) ($pendingProcurementNotificationCount ?? 0);
        $pendingProcurementNotificationLabel = $pendingProcurementNotificationCount > 99
            ? '99+'
            : (string) $pendingProcurementNotificationCount;
        // CATATAN-AUDIT: Daftar menu sidebar admin dijaga terpusat di layout ini untuk konsistensi semua halaman admin.
        $adminSidebarGroups = [
            [
                'key' => 'dashboard',
                'title' => 'Dashboard',
                'route' => route('admin.dashboard'),
                'icon' => 'fa-solid fa-gauge-high',
                'children' => [],
            ],
            [
                'key' => 'marketplace',
                'title' => 'Marketplace',
                'route' => route('admin.modules.marketplace', ['section' => 'overview']),
                'icon' => 'fa-solid fa-store',
                'children' => [],
            ],
            [
                'key' => 'pengadaan',
                'title' => 'Menu Pengadaan',
                'route' => route('admin.modules.procurement', ['section' => 'stock']),
                'icon' => 'fa-solid fa-truck-ramp-box',
                'children' => [],
            ],
            [
                'key' => 'mode-requests',
                'title' => 'Permintaan Mode',
                'route' => route('admin.modeRequests.index'),
                'icon' => 'fa-solid fa-file-circle-check',
                'children' => [],
            ],
            [
                'key' => 'users',
                'title' => 'Manajemen User',
                'route' => route('admin.modules.users'),
                'icon' => 'fa-solid fa-users',
                'children' => [],
            ],
            [
                'key' => 'orders',
                'title' => 'Status Pesanan',
                'route' => route('admin.modules.orders'),
                'icon' => 'fa-solid fa-clipboard-check',
                'children' => [],
            ],
            [
                'key' => 'finance',
                'title' => 'Keuangan',
                'route' => route('admin.modules.finance'),
                'icon' => 'fa-solid fa-wallet',
                'children' => [],
            ],
            [
                'key' => 'weather',
                'title' => 'Notifikasi Cuaca',
                'route' => route('admin.modules.weather'),
                'icon' => 'fa-solid fa-cloud-sun-rain',
                'children' => [],
            ],
            [
                'key' => 'recommendation-rules',
                'title' => 'Rule Rekomendasi',
                'route' => route('admin.modules.recommendationRules'),
                'icon' => 'fa-solid fa-wand-magic-sparkles',
                'children' => [],
            ],
            [
                'key' => 'warehouse',
                'title' => 'Gudang',
                'route' => route('admin.modules.warehouse'),
                'icon' => 'fa-solid fa-warehouse',
                'children' => [],
            ],
            [
                'key' => 'reports',
                'title' => 'Laporan',
                'route' => route('admin.modules.reports'),
                'icon' => 'fa-solid fa-chart-column',
                'children' => [],
            ],
        ];
    @endphp

    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-24 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-sky-500/20 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-emerald-400/15 blur-3xl"></div>
    </div>

    <div class="admin-shell">
        <aside
            class="admin-sidebar border-r border-slate-800 bg-slate-950 text-slate-100"
            :class="{ 'is-open': adminSidebarOpen }"
        >
            <div class="admin-sidebar-brand flex h-20 items-center justify-between border-b border-slate-800 px-5">
                <div>
                    <p class="admin-brand-kicker text-[11px] uppercase tracking-[0.22em] text-slate-400">Control Panel</p>
                    <a href="{{ route('admin.dashboard') }}" class="admin-brand-title mt-1 inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-white">
                        <i class="fa-solid fa-shield-halved text-[11px] text-sky-300" aria-hidden="true"></i>
                        <span>ADMIN MARKETPLACE</span>
                    </a>
                </div>
                <button
                    type="button"
                    class="admin-mobile-only rounded border border-slate-700 px-2 py-1 text-[11px] font-semibold text-slate-300"
                    @click="adminSidebarOpen = false"
                >
                    <i class="fa-solid fa-xmark mr-1 text-[10px]" aria-hidden="true"></i>
                    Tutup
                </button>
            </div>

            <nav class="admin-sidebar-nav flex-1 space-y-2 overflow-y-auto px-3 py-5 text-sm">
                <p class="admin-sidebar-section-label px-2 pb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Navigasi Utama
                </p>
                @foreach($adminSidebarGroups as $group)
                    @php
                        $groupPath = ltrim((string) parse_url($group['route'], PHP_URL_PATH), '/');
                        $isGroupActive = request()->is($groupPath) || request()->is($groupPath . '/*');
                        $hasChildren = count($group['children']) > 0;
                        if (!$isGroupActive && $hasChildren) {
                            foreach ($group['children'] as $childCandidate) {
                                $childCandidatePath = ltrim((string) parse_url($childCandidate['route'], PHP_URL_PATH), '/');
                                if (request()->is($childCandidatePath) || request()->is($childCandidatePath . '/*')) {
                                    $isGroupActive = true;
                                    break;
                                }
                            }
                        }
                    @endphp

                    @if(!$hasChildren)
                        <a
                            href="{{ $group['route'] }}"
                            class="admin-nav-link flex items-center gap-3 rounded-xl border font-semibold transition {{ $isGroupActive ? 'border-sky-400/70 bg-sky-500/20 text-white shadow-lg shadow-sky-500/10' : 'border-slate-800 bg-slate-900 text-slate-200 hover:border-slate-600 hover:bg-slate-800 hover:text-white' }}"
                        >
                            <span class="admin-nav-icon inline-flex h-6 w-6 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-[10px] font-bold text-slate-300">
                                <i class="{{ $group['icon'] ?? 'fa-solid fa-circle' }} admin-nav-icon-glyph" aria-hidden="true"></i>
                            </span>
                            <span class="admin-nav-label">{{ $group['title'] }}</span>
                        </a>
                    @else
                        <div
                            x-data="{ open: {{ $isGroupActive ? 'true' : 'false' }} }"
                            class="rounded-xl border {{ $isGroupActive ? 'border-sky-400/60 bg-slate-900' : 'border-slate-800 bg-slate-900' }}"
                        >
                            <div class="flex items-center gap-1 p-1">
                                <a
                                    href="{{ $group['route'] }}"
                                    class="admin-nav-link flex flex-1 items-center gap-3 rounded-lg border transition {{ $isGroupActive ? 'border-sky-400/50 bg-sky-500/20 text-white' : 'border-slate-800 bg-slate-900 text-slate-100 hover:border-slate-600 hover:bg-slate-800' }}"
                                >
                                    <span class="admin-nav-icon inline-flex h-6 w-6 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-[10px] font-bold text-slate-300">
                                        <i class="{{ $group['icon'] ?? 'fa-solid fa-circle' }} admin-nav-icon-glyph" aria-hidden="true"></i>
                                    </span>
                                    <span class="admin-nav-label font-semibold">{{ $group['title'] }}</span>
                                </a>

                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-700 bg-slate-950 text-slate-300 transition hover:border-slate-500 hover:text-white"
                                    :aria-expanded="open.toString()"
                                    aria-label="Toggle submenu"
                                >
                                    <i class="fa-solid fa-chevron-right admin-nav-chevron text-[11px] transition-transform duration-200" :class="{ 'rotate-90': open }" aria-hidden="true"></i>
                                </button>
                            </div>

                            <div x-show="open" x-cloak class="admin-nav-children space-y-1.5 px-3 pb-3">
                                @foreach($group['children'] as $child)
                                    @php
                                        $childPath = ltrim((string) parse_url($child['route'], PHP_URL_PATH), '/');
                                        $childQueryRaw = (string) parse_url($child['route'], PHP_URL_QUERY);
                                        parse_str($childQueryRaw, $childQuery);
                                        $isChildActive = request()->is($childPath) || request()->is($childPath . '/*');
                                        if ($isChildActive && count($childQuery) > 0) {
                                            foreach ($childQuery as $queryKey => $queryValue) {
                                                if ((string) request()->query($queryKey) !== (string) $queryValue) {
                                                    $isChildActive = false;
                                                    break;
                                                }
                                            }
                                        }
                                    @endphp
                                    <a
                                        href="{{ $child['route'] }}"
                                        class="admin-nav-child flex items-center gap-2 rounded-lg border transition {{ $isChildActive ? 'border-sky-500/60 bg-sky-500/15 text-sky-100' : 'border-slate-800 bg-slate-950 text-slate-300 hover:border-slate-600 hover:bg-slate-800 hover:text-white' }}"
                                    >
                                        <span class="admin-nav-child-icon inline-flex shrink-0 items-center justify-center rounded-md border border-slate-700 bg-slate-900 text-slate-300">
                                            <i class="{{ $child['icon'] ?? 'fa-solid fa-angle-right' }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="leading-snug">{{ $child['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>

        </aside>

        <div class="admin-main">
            <header class="admin-topbar sticky top-0 z-30 border-b border-slate-200/70 bg-white/90 backdrop-blur">
                <div class="admin-topbar-inner mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="admin-mobile-only rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700"
                            @click="adminSidebarOpen = true"
                        >
                            <i class="fa-solid fa-bars mr-1 text-[10px]" aria-hidden="true"></i>
                            Menu
                        </button>
                        <div class="min-w-0">
                            <p class="admin-topbar-kicker inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <i class="fa-solid fa-sliders text-[10px]" aria-hidden="true"></i>
                                <span>Admin Panel</span>
                            </p>
                            <div class="admin-topbar-title inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <i class="fa-solid fa-table-columns text-[12px] text-slate-500" aria-hidden="true"></i>
                                <span class="truncate">{{ $header ?? 'Dashboard' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a
                            href="{{ route('admin.modules.procurement', ['section' => 'orders', 'status' => 'pending']) }}#order-mitra"
                            class="admin-topbar-link hidden items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:inline-flex"
                        >
                            <i class="fa-regular fa-bell text-[10px]" aria-hidden="true"></i>
                            <span>Notifikasi Pengadaan</span>
                            @if($pendingProcurementNotificationCount > 0)
                                <span
                                    data-testid="admin-procurement-notification-badge"
                                    data-pending-count="{{ $pendingProcurementNotificationCount }}"
                                    class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"
                                >
                                    {{ $pendingProcurementNotificationLabel }}
                                </span>
                            @endif
                        </a>

                        <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                            @click="adminProfileMenuOpen = !adminProfileMenuOpen"
                            @click.away="adminProfileMenuOpen = false"
                            :aria-expanded="adminProfileMenuOpen.toString()"
                            aria-haspopup="true"
                        >
                            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-slate-900 text-xs font-bold text-white">
                                @if($avatarImageUrl)
                                    <img src="{{ $avatarImageUrl }}" alt="Foto profil {{ $authUser?->name }}" class="h-full w-full object-cover">
                                @else
                                    {{ $avatarInitial }}
                                @endif
                            </span>
                            <span class="admin-topbar-profile-name max-w-[120px] truncate text-xs font-semibold">{{ $authUser?->name }}</span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-500" aria-hidden="true"></i>
                        </button>

                        <div
                            x-show="adminProfileMenuOpen"
                            x-cloak
                            class="absolute right-0 z-40 mt-2 w-48 rounded-xl border border-slate-200 bg-white py-2 shadow-xl shadow-slate-900/10"
                        >
                            <a href="{{ route('admin.profile') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
                                <i class="fa-regular fa-user text-[12px] text-slate-500" aria-hidden="true"></i>
                                <span>Profil</span>
                            </a>
                            <a href="{{ route('profile.location') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
                                <i class="fa-solid fa-location-dot text-[12px] text-slate-500" aria-hidden="true"></i>
                                <span>Set Lokasi</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">
                                    <i class="fa-solid fa-right-from-bracket text-[12px]" aria-hidden="true"></i>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    </div>
                </div>
            </header>

            <main class="py-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <div class="admin-overlay" :class="{ 'is-open': adminSidebarOpen }" @click="adminSidebarOpen = false"></div>

    @livewireScripts
</body>
</html>


