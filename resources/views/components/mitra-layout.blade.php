<!doctype html>
<html lang="id" data-livewire-page="true">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/js/panel.js','resources/css/app.css','resources/css/mitra.css'])
    @livewireStyles
</head>
<body
    class="adminlte-modern min-h-screen antialiased"
    x-data="{ mitraSidebarOpen: false, mitraProfileMenuOpen: false }"
    :class="{ 'mitra-no-scroll': mitraSidebarOpen }"
    @keydown.escape.window="mitraSidebarOpen = false; mitraProfileMenuOpen = false"
>
    @php
        $authUser = auth()->user();
        $avatarImageUrl = $authUser?->avatarImageUrl();
        $avatarInitial = $authUser?->avatarInitial() ?? 'U';
        $mitraSidebarItems = [
            [
                'title' => 'Dashboard',
                'route' => route('mitra.dashboard'),
                'icon' => 'fa-solid fa-gauge-high',
                'active' => request()->routeIs('mitra.dashboard'),
            ],
            [
                'title' => 'Toko Saya',
                'route' => route('mitra.products.index'),
                'icon' => 'fa-solid fa-boxes-stacked',
                'active' => request()->routeIs('mitra.products.*'),
            ],
            [
                'title' => 'Beli Stok',
                'route' => route('mitra.procurement.index'),
                'icon' => 'fa-solid fa-truck-ramp-box',
                'active' => request()->routeIs('mitra.procurement.*'),
            ],
            [
                'title' => 'Pesanan',
                'route' => route('mitra.orders.index'),
                'icon' => 'fa-solid fa-receipt',
                'active' => request()->routeIs('mitra.orders.*'),
            ],
            [
                'title' => 'Keuangan',
                'route' => route('mitra.finance'),
                'icon' => 'fa-solid fa-chart-line',
                'active' => request()->routeIs('mitra.finance'),
            ],
            [
                'title' => 'Data Affiliate',
                'route' => route('mitra.affiliates'),
                'icon' => 'fa-solid fa-user-group',
                'active' => request()->routeIs('mitra.affiliates'),
            ],
        ];
    @endphp

    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-24 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-cyan-500/20 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-emerald-400/15 blur-3xl"></div>
    </div>

    <div class="mitra-shell">
        <aside
            class="mitra-sidebar border-r border-slate-800 bg-slate-950 text-slate-100"
            :class="{ 'is-open': mitraSidebarOpen }"
        >
            <div class="mitra-sidebar-brand flex h-20 items-center justify-between border-b border-slate-800 px-5">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-400">Mitra Workspace</p>
                    <a href="{{ route('mitra.dashboard') }}" class="mitra-brand-title mt-1 inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-white">
                        <i class="fa-solid fa-shop text-[11px] text-cyan-300" aria-hidden="true"></i>
                        <span>MITRA DASHBOARD</span>
                    </a>
                </div>
                <button
                    type="button"
                    class="mitra-mobile-only rounded border border-slate-700 px-2 py-1 text-[11px] font-semibold text-slate-300"
                    @click="mitraSidebarOpen = false"
                >
                    <i class="fa-solid fa-xmark mr-1 text-[10px]" aria-hidden="true"></i>
                    Tutup
                </button>
            </div>

            <nav class="mitra-sidebar-nav flex-1 space-y-2 overflow-y-auto px-3 py-5 text-sm">
                @foreach($mitraSidebarItems as $item)
                    <a
                        href="{{ $item['route'] }}"
                        class="mitra-nav-link flex items-center gap-3 rounded-xl border font-semibold transition {{ $item['active'] ? 'border-cyan-400/70 bg-cyan-500/20 text-white shadow-lg shadow-cyan-500/10' : 'border-slate-800 bg-slate-900 text-slate-200 hover:border-slate-600 hover:bg-slate-800 hover:text-white' }}"
                    >
                        <span class="mitra-nav-icon inline-flex h-6 w-6 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-[10px] font-bold text-slate-300">
                            <i class="{{ $item['icon'] ?? 'fa-solid fa-circle' }} mitra-nav-icon-glyph" aria-hidden="true"></i>
                        </span>
                        <span class="mitra-nav-label">{{ $item['title'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="mitra-main">
            <header class="mitra-topbar sticky top-0 z-30 border-b border-slate-200/70 bg-white/90 backdrop-blur">
                <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="mitra-mobile-only rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700"
                            @click="mitraSidebarOpen = true"
                        >
                            <i class="fa-solid fa-bars mr-1 text-[10px]" aria-hidden="true"></i>
                            Menu
                        </button>
                        <div>
                            <p class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <i class="fa-solid fa-warehouse text-[10px]" aria-hidden="true"></i>
                                <span>Mitra Panel</span>
                            </p>
                            <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <i class="fa-solid fa-chart-pie text-[12px] text-slate-500" aria-hidden="true"></i>
                                <span>{{ $header ?? 'Dashboard Mitra' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                            @click="mitraProfileMenuOpen = !mitraProfileMenuOpen"
                            @click.away="mitraProfileMenuOpen = false"
                            :aria-expanded="mitraProfileMenuOpen.toString()"
                            aria-haspopup="true"
                        >
                            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-slate-900 text-xs font-bold text-white">
                                @if($avatarImageUrl)
                                    <img src="{{ $avatarImageUrl }}" alt="Foto profil {{ $authUser?->name }}" class="h-full w-full object-cover">
                                @else
                                    {{ $avatarInitial }}
                                @endif
                            </span>
                            <span class="mitra-topbar-profile-name max-w-[120px] truncate text-xs font-semibold">{{ $authUser?->name }}</span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-500" aria-hidden="true"></i>
                        </button>

                        <div
                            x-show="mitraProfileMenuOpen"
                            x-cloak
                            class="absolute right-0 z-40 mt-2 w-52 rounded-xl border border-slate-200 bg-white py-2 shadow-xl shadow-slate-900/10"
                        >
                            <div class="border-b border-slate-100 px-3 pb-2 pt-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $authUser?->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $authUser?->email }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}" class="mt-1 flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
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
            </header>

            <main class="py-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <div class="mitra-overlay" :class="{ 'is-open': mitraSidebarOpen }" @click="mitraSidebarOpen = false"></div>

    @livewireScripts
</body>
</html>


