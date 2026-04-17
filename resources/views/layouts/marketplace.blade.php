<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/js/marketplace.js', 'resources/css/app.css', 'resources/css/marketplace.css'])
    @livewireStyles
</head>
@php
    $user = auth()->user();
    $role = $user?->role;
    $notificationCount = (int) ($notificationCount ?? 0);
    $avatarImageUrl = $user?->avatarImageUrl();
    $avatarInitial = $user?->avatarInitial() ?? 'U';
    $authRedirectUrl = url()->full();
@endphp
<body
    class="app-shell min-h-screen text-slate-900"
    x-data="{ marketplaceAccountOpen: false }"
    @keydown.escape.window="marketplaceAccountOpen = false"
>
    <header class="border-b border-emerald-300/50 bg-gradient-to-r from-emerald-700 via-green-700 to-teal-700 text-white shadow-md">
        <div class="border-b border-white/20 bg-emerald-900/30">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm md:px-6 lg:px-8">
                <div class="flex flex-wrap items-center gap-2.5">
                    <a
                        href="{{ route('landing') }}"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border px-3.5 font-semibold transition {{ request()->routeIs('landing') ? 'border-white/40 bg-white/20 text-white' : 'border-white/25 bg-white/10 text-white hover:bg-white/20' }}"
                    >
                        <i class="fa-solid fa-store text-xs" aria-hidden="true"></i>
                        <span>Marketplace</span>
                    </a>

                    @auth
                        @if($role === 'consumer')
                            <a
                                href="{{ route('orders.mine') }}"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border px-3.5 font-semibold transition {{ request()->routeIs('orders.mine*') ? 'border-white/40 bg-white/20 text-white' : 'border-white/25 bg-white/10 text-white hover:bg-white/20' }}"
                            >
                                <i class="fa-solid fa-receipt text-xs" aria-hidden="true"></i>
                                <span>Pesanan Saya</span>
                            </a>
                        @endif

                        <a
                            href="{{ route('notifications.index') }}"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border px-3.5 font-semibold transition {{ request()->routeIs('notifications.index*') ? 'border-white/40 bg-white/20 text-white' : 'border-white/25 bg-white/10 text-white hover:bg-white/20' }}"
                        >
                            <i class="fa-regular fa-bell text-xs" aria-hidden="true"></i>
                            <span>Notifikasi</span>
                            @if($notificationCount > 0)
                                <span class="inline-flex min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold text-white">
                                    {{ $notificationCount > 99 ? '99+' : $notificationCount }}
                                </span>
                            @endif
                        </a>
                    @endauth
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    @auth
                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex h-10 min-w-[132px] items-center justify-center gap-2 rounded-xl border border-white/35 bg-white/15 px-3.5 font-semibold hover:bg-white/25"
                                @click="marketplaceAccountOpen = !marketplaceAccountOpen"
                                @click.away="marketplaceAccountOpen = false"
                                :aria-expanded="marketplaceAccountOpen.toString()"
                            >
                                <span class="inline-flex h-7 w-7 items-center justify-center overflow-hidden rounded-full border border-white/40 bg-white/15 text-[11px] font-bold text-white">
                                    @if($avatarImageUrl)
                                        <img src="{{ $avatarImageUrl }}" alt="Foto profil {{ $user?->name }}" class="h-full w-full object-cover">
                                    @else
                                        {{ $avatarInitial }}
                                    @endif
                                </span>
                                <span class="max-w-[120px] truncate">{{ $user?->name }}</span>
                                <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                            </button>

                            <div
                                x-show="marketplaceAccountOpen"
                                x-cloak
                                class="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-xl border border-emerald-100 bg-white py-1.5 text-slate-700 shadow-xl"
                            >
                                <div class="border-b border-emerald-100 px-3 py-2.5">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $user?->name ?? 'User' }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $user?->email ?? '-' }}</p>
                                </div>
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-3 py-2 text-sm font-semibold hover:bg-emerald-50">
                                    <i class="fa-regular fa-user text-xs text-slate-500" aria-hidden="true"></i>
                                    <span>Profile</span>
                                </a>
                                <a href="{{ route('account.show') }}" class="flex items-center gap-2 px-3 py-2 text-sm font-semibold hover:bg-emerald-50">
                                    <i class="fa-solid fa-building-columns text-xs text-slate-500" aria-hidden="true"></i>
                                    <span>Rekening</span>
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                        <i class="fa-solid fa-right-from-bracket text-xs" aria-hidden="true"></i>
                                        <span>Logout</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a
                            href="{{ route('login', ['redirect' => $authRedirectUrl]) }}"
                            class="inline-flex h-10 min-w-[110px] items-center justify-center rounded-xl border border-white/35 bg-white/15 px-4 text-sm font-semibold leading-none text-white hover:bg-white/25"
                        >
                            Login
                        </a>
                        <a
                            href="{{ route('register', ['redirect' => $authRedirectUrl]) }}"
                            class="inline-flex h-10 min-w-[110px] items-center justify-center rounded-xl border border-amber-200 bg-amber-100 px-4 text-sm font-semibold leading-none text-amber-900 hover:bg-amber-200"
                        >
                            Daftar
                        </a>
                    @endauth
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-4 md:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="inline-flex items-center gap-3">
                <img src="{{ asset('Logo.png') }}" alt="Logo Toko Tani" class="h-14 w-14 object-contain">
                <div>
                    <p class="text-xl font-extrabold leading-tight">Toko Tani</p>
                </div>
            </a>
        </div>
    </header>

    @hasSection('pageTitle')
        <section class="border-b border-slate-200/70 bg-white/80">
            <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-bold text-slate-900">@yield('pageTitle')</h1>
            </div>
        </section>
    @endif

    <main class="py-6">
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>


