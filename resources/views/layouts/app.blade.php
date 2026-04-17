<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/js/marketplace.js','resources/css/app.css','resources/css/marketplace.css'])
    @livewireStyles
</head>
@php
    $user = auth()->user();
    $role = $user?->role;
    $avatarImageUrl = $user?->avatarImageUrl();
    $avatarInitial = $user?->avatarInitial() ?? 'U';
    $isProfileRoute = request()->routeIs('profile.*');
    $hideWorkspaceNavbar = request()->routeIs('profile.*');
    $roleLabel = match ($role) {
        'mitra' => 'Mitra Workspace',
        'admin' => 'Admin Access',
        default => 'Marketplace Workspace',
    };
    $roleBadgeClass = match ($role) {
        'mitra' => 'border-emerald-300/60 bg-emerald-500/15 text-emerald-100',
        'admin' => 'border-sky-300/60 bg-sky-500/15 text-sky-100',
        default => 'border-amber-300/60 bg-amber-500/15 text-amber-100',
    };
    $primaryLinks = match ($role) {
        'mitra' => [
            ['label' => 'Dashboard Mitra', 'route' => route('mitra.dashboard'), 'active' => request()->routeIs('mitra.dashboard')],
            ['label' => 'Produk Saya', 'route' => route('mitra.products.index'), 'active' => request()->routeIs('mitra.products.*')],
            ['label' => 'Pengadaan Mitra', 'route' => route('mitra.procurement.index'), 'active' => request()->routeIs('mitra.procurement.*')],
            ['label' => 'Order Masuk', 'route' => route('mitra.orders.index'), 'active' => request()->routeIs('mitra.orders.*')],
        ],
        'admin' => [
            ['label' => 'Dashboard Admin', 'route' => route('admin.dashboard'), 'active' => request()->routeIs('admin.*')],
            ['label' => 'Marketplace', 'route' => route('landing'), 'active' => request()->routeIs('landing')],
        ],
        default => [
            ['label' => 'Marketplace', 'route' => route('landing'), 'active' => request()->routeIs('landing')],
            ['label' => 'Pesanan Saya', 'route' => route('orders.mine'), 'active' => request()->routeIs('orders.mine*')],
            ['label' => 'Akun', 'route' => route('account.show'), 'active' => request()->routeIs('account.*')],
            ['label' => 'Dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
        ],
    };
@endphp
<body class="app-shell min-h-screen bg-slate-100 text-slate-900">
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute left-0 top-0 h-[32rem] w-[32rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-cyan-400/20 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-[24rem] w-[24rem] translate-x-1/3 translate-y-1/4 rounded-full bg-emerald-400/15 blur-3xl"></div>
    </div>

    <div class="min-h-screen">
        @auth
            @unless($hideWorkspaceNavbar)
                <header x-data="{ open: false, menuOpen: false }" class="sticky top-0 z-40 border-b border-slate-200/70 bg-slate-950 text-white shadow-lg shadow-slate-900/10">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div class="flex h-16 items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/20 bg-white/10 text-sm lg:hidden"
                                    @click="open = !open"
                                >
                                    =
                                </button>

                                <div>
                                    <a href="{{ route('landing') }}" class="block text-sm font-semibold tracking-wide text-white">
                                        {{ config('app.name', 'Marketplace') }}
                                    </a>
                                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-300">{{ $roleLabel }}</p>
                                </div>
                            </div>

                            <nav class="hidden items-center gap-1 lg:flex">
                                @foreach($primaryLinks as $link)
                                    <a
                                        href="{{ $link['route'] }}"
                                        class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ $link['active'] ? 'bg-white/15 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}"
                                    >
                                        {{ $link['label'] }}
                                    </a>
                                @endforeach
                            </nav>

                            <div class="relative">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-white/15"
                                    @click="menuOpen = !menuOpen"
                                    @click.away="menuOpen = false"
                                >
                                    <span class="inline-flex h-7 w-7 items-center justify-center overflow-hidden rounded-full bg-white/20 text-[11px] font-bold">
                                        @if($avatarImageUrl)
                                            <img src="{{ $avatarImageUrl }}" alt="Foto profil {{ $user->name }}" class="h-full w-full object-cover">
                                        @else
                                            {{ $avatarInitial }}
                                        @endif
                                    </span>
                                    <span class="hidden max-w-[100px] truncate sm:block">{{ $user->name }}</span>
                                </button>

                                <div
                                    x-show="menuOpen"
                                    x-cloak
                                    class="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-2 text-slate-700 shadow-xl"
                                >
                                    <div class="border-b border-slate-100 px-3 pb-2 pt-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                    </div>
                                    <a href="{{ route('profile.edit') }}" class="mt-1 block px-3 py-2 text-sm hover:bg-slate-100">Profile</a>
                                    <a href="{{ route('profile.location') }}" class="block px-3 py-2 text-sm hover:bg-slate-100">Set Lokasi</a>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">
                                            Log Out
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div x-show="open" x-cloak class="space-y-1 border-t border-white/10 py-3 lg:hidden">
                            @foreach($primaryLinks as $link)
                                <a
                                    href="{{ $link['route'] }}"
                                    class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ $link['active'] ? 'bg-white/15 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}"
                                >
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </header>
            @endunless
        @endauth

        @if(isset($header))
            <section class="border-b border-slate-200/70 bg-white/70">
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    @if($isProfileRoute)
                        <div class="w-full">
                            {{ $header }}
                        </div>
                    @else
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-lg font-semibold text-slate-900">
                                {{ $header }}
                            </div>
                            @auth
                                <span class="hidden rounded-full border px-3 py-1 text-xs font-semibold sm:inline-flex {{ $roleBadgeClass }}">
                                    {{ strtoupper($role ?? 'user') }}
                                </span>
                            @endauth
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <main class="pb-10 pt-6">
            {{ $slot ?? '' }}
        </main>
    </div>

    @auth
        @if (request()->routeIs('admin.dashboard'))
            <form id="back-logout-form" method="POST" action="{{ route('logout') }}" class="hidden" data-back-logout-guard="true">
                @csrf
            </form>
        @endif
    @endauth

    @livewireScripts
</body>
</html>


