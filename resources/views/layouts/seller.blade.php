<!doctype html>
<html lang="id" data-livewire-page="true">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/js/panel.js','resources/css/app.css'])
    <style>
        .seller-shell-sidebar {
            width: 16rem;
            transform: translateX(-100%);
            transition: transform 0.2s ease;
        }
        .seller-shell-sidebar.is-open {
            transform: translateX(0);
        }
        .seller-shell-content {
            margin-left: 0;
        }
        @media (min-width: 1024px) {
            .seller-shell-sidebar {
                transform: translateX(0) !important;
            }
            .seller-shell-content {
                margin-left: 16rem;
            }
        }
    </style>
    @livewireStyles
</head>
@php
    $user = auth()->user();
    $avatarImageUrl = $user?->avatarImageUrl();
    $avatarInitial = $user?->avatarInitial() ?? 'U';
    $navItems = [
        [
            'label' => 'Dashboard',
            'route' => route('seller.dashboard'),
            'active' => request()->routeIs('seller.dashboard'),
            'icon' => 'fa-gauge-high',
            'visible' => true,
        ],
        [
            'label' => 'Produk Hasil Tani',
            'route' => route('seller.products.index'),
            'active' => request()->routeIs('seller.products.*'),
            'icon' => 'fa-seedling',
            'visible' => true,
        ],
        [
            'label' => 'Order Pembeli',
            'route' => route('seller.orders.index'),
            'active' => request()->routeIs('seller.orders.index'),
            'icon' => 'fa-truck',
            'visible' => true,
        ],
        [
            'label' => 'Marketplace',
            'route' => route('landing'),
            'active' => request()->routeIs('landing'),
            'icon' => 'fa-store',
            'visible' => true,
        ],
    ];
@endphp
<body
    class="min-h-screen bg-slate-100 text-slate-900 lg:overflow-auto"
    x-data="{
        sellerSidebarOpen: false,
        sellerProfileOpen: false
    }"
    :class="{ 'overflow-hidden': sellerSidebarOpen }"
    @keydown.escape.window="sellerSidebarOpen = false; sellerProfileOpen = false"
>
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-20 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-amber-400/20 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-emerald-400/15 blur-3xl"></div>
    </div>

    <div class="min-h-screen">
        <aside
            class="seller-shell-sidebar fixed inset-y-0 left-0 z-40 flex flex-col border-r border-slate-800 bg-slate-950 text-slate-100 shadow-[8px_0_28px_rgba(2,6,23,0.45)] lg:h-dvh"
            :class="{ 'is-open': sellerSidebarOpen }"
        >
            <div class="flex h-16 items-center justify-between border-b border-slate-800 px-4">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-slate-400">Mode Penjual</p>
                    <p class="text-sm font-semibold text-white">Seller Dashboard</p>
                </div>
                <button type="button" class="rounded border border-slate-700 px-2 py-1 text-xs lg:hidden" @click="sellerSidebarOpen = false">Tutup</button>
            </div>

            <nav class="flex-1 space-y-1.5 overflow-y-auto overflow-x-hidden p-3 text-sm">
                @foreach($navItems as $item)
                    @if(! $item['visible'])
                        @continue
                    @endif
                    <a
                        href="{{ $item['route'] }}"
                        class="flex items-center gap-2 rounded-lg border px-3 py-2 font-semibold transition {{ $item['active'] ? 'border-amber-500/40 bg-amber-500/20 text-white' : 'border-slate-800 bg-slate-900 text-slate-200 hover:border-slate-600 hover:bg-slate-800' }}"
                    >
                        <i class="fa-solid {{ $item['icon'] }} text-[11px]" aria-hidden="true"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="seller-shell-content min-h-screen">
            <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/90 backdrop-blur shadow-[0_6px_18px_rgba(15,23,42,0.06)]">
                <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 lg:hidden" @click="sellerSidebarOpen = true">
                            <i class="fa-solid fa-bars mr-1 text-[10px]" aria-hidden="true"></i>Menu
                        </button>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Seller Workspace</p>
                            <p class="text-sm font-semibold text-slate-900">{{ $header ?? 'Dashboard Penjual' }}</p>
                        </div>
                    </div>

                    <div class="relative ml-auto">
                        <button type="button" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-slate-700 shadow-sm hover:bg-slate-50" @click="sellerProfileOpen = !sellerProfileOpen" @click.away="sellerProfileOpen = false">
                            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-slate-900 text-xs font-bold text-white">
                                @if($avatarImageUrl)
                                    <img src="{{ $avatarImageUrl }}" alt="Foto profil {{ $user?->name }}" class="h-full w-full object-cover">
                                @else
                                    {{ $avatarInitial }}
                                @endif
                            </span>
                            <span class="hidden max-w-[130px] truncate text-xs font-semibold sm:block">{{ $user?->name }}</span>
                        </button>
                        <div x-show="sellerProfileOpen" x-cloak class="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl">
                            <div class="border-b border-slate-100 px-3 pb-2 pt-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $user?->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $user?->email }}</p>
                            </div>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                <i class="fa-regular fa-user text-xs text-slate-500" aria-hidden="true"></i>
                                <span>Profile</span>
                            </a>
                            <a href="{{ route('account.show') }}" class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
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
                </div>
            </header>

            <main class="py-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <div
        x-cloak
        x-show="sellerSidebarOpen"
        class="fixed inset-0 z-30 bg-slate-950/60 lg:hidden"
        @click="sellerSidebarOpen = false"
    ></div>
    @livewireScripts
</body>
</html>


