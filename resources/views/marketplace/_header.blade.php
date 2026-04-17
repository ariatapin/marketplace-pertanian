<header class="border-b border-emerald-300/50 bg-gradient-to-r from-emerald-700 via-green-700 to-teal-700 text-white shadow-md">
    <div class="border-b border-white/20 bg-emerald-900/30">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm md:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('landing') }}" class="inline-flex h-10 items-center rounded-full border border-transparent px-4 font-semibold hover:bg-white/15">Promo Musim Panen</a>
                <a href="{{ route('landing') }}" class="inline-flex h-10 items-center rounded-full border border-transparent px-4 font-semibold hover:bg-white/15">Bantuan</a>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                @auth
                    @php
                        $notificationCount = (int) ($accountMenu['notification_count'] ?? 0);
                    @endphp

                    @if($role === 'consumer')
                        <a href="{{ route('orders.mine') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-white/35 bg-white/15 px-3.5 font-semibold hover:bg-white/25">
                            <i class="fa-solid fa-receipt text-xs" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Pesanan Saya</span>
                        </a>
                    @endif

                    <a href="{{ route('notifications.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-white/35 bg-white/15 px-3.5 font-semibold hover:bg-white/25">
                        <i class="fa-regular fa-bell text-xs" aria-hidden="true"></i>
                        <span class="hidden sm:inline">Notifikasi</span>
                        @if($notificationCount > 0)
                            <span class="inline-flex min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-bold text-white">
                                {{ $notificationCount > 99 ? '99+' : $notificationCount }}
                            </span>
                        @endif
                    </a>

                    <div class="relative">
                        <button
                            type="button"
                            class="inline-flex h-10 min-w-[132px] items-center justify-center gap-2 rounded-xl border border-white/35 bg-white/15 px-3.5 font-semibold hover:bg-white/25"
                            @click="landingAccountOpen = !landingAccountOpen"
                            @click.away="landingAccountOpen = false"
                            :aria-expanded="landingAccountOpen.toString()"
                        >
                            <span class="inline-flex h-7 w-7 overflow-hidden rounded-full border border-white/40 bg-white/15">
                                @if(!empty($accountMenu['avatar_url']))
                                    <img src="{{ $accountMenu['avatar_url'] }}" alt="{{ $accountMenu['name'] }}" class="h-full w-full object-cover">
                                @else
                                    <span class="inline-flex h-full w-full items-center justify-center text-[11px] font-bold text-white">
                                        {{ $accountMenu['avatar_initial'] ?? 'U' }}
                                    </span>
                                @endif
                            </span>
                            <span>Akun</span>
                            <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                        </button>

                        <div
                            x-show="landingAccountOpen"
                            x-cloak
                            class="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-xl border border-emerald-100 bg-white py-1.5 text-slate-700 shadow-xl"
                        >
                            <div class="border-b border-emerald-100 px-3 py-2.5">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $accountMenu['name'] ?? 'User' }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $accountMenu['email'] ?? '-' }}</p>
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
                    <button
                        type="button"
                        class="inline-flex h-12 min-w-[132px] items-center justify-center rounded-xl border border-white/35 bg-white/15 px-5 text-base font-semibold leading-none hover:bg-white/25"
                        @click="openAuth('login')"
                    >
                        Login
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-12 min-w-[132px] items-center justify-center rounded-xl border border-amber-200 bg-amber-100 px-5 text-base font-semibold leading-none text-amber-900 hover:bg-amber-200"
                        @click="openAuth('register')"
                    >
                        Daftar
                    </button>
                @endauth
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 pb-6 pt-5 md:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-[auto_1fr_auto] lg:items-center lg:gap-2">
            <a href="{{ route('landing') }}" class="inline-flex items-center gap-3">
                <img src="{{ asset('Logo.png') }}" alt="Logo Toko Tani" class="h-20 w-20 object-contain">
                <div>
                    <p class="text-xl font-extrabold leading-tight">Toko Tani</p>
                </div>
            </a>

            <form method="GET" action="{{ route('landing') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]">
                <input type="hidden" name="source" value="{{ $productSource ?? 'all' }}">
                @if(($productSource ?? 'all') === 'affiliate' && !empty($affiliateReadyOnly))
                    <input type="hidden" name="ready_marketing" value="1">
                @endif
                <input
                    type="text"
                    name="q"
                    value="{{ $searchKeyword }}"
                    placeholder="Cari produk, deskripsi, atau nama penjual/mitra"
                    class="h-12 rounded-xl border border-emerald-100/60 bg-white px-4 text-base text-slate-800 placeholder:text-slate-400 focus:border-emerald-300 focus:outline-none"
                >
                <button type="submit" class="inline-flex h-12 min-w-[124px] items-center justify-center gap-2 rounded-xl border border-green-900 bg-green-900 px-4 text-base font-semibold text-white shadow-md hover:bg-green-800">
                    <i class="fa-solid fa-magnifying-glass text-xs" aria-hidden="true"></i>
                    <span>Cari</span>
                </button>
            </form>

            @auth
                <a href="{{ $cartTarget }}" class="inline-flex h-12 min-w-[86px] items-center justify-center gap-2 rounded-xl border border-white/40 bg-white/15 px-4 text-base font-semibold hover:bg-white/25">
                    <i class="fa-solid fa-basket-shopping text-xs" aria-hidden="true"></i>
                    <span
                        data-cart-count="1"
                        x-text="(Number.isFinite(Number(cartItemCount)) ? new Intl.NumberFormat('id-ID').format(Math.max(0, Number(cartItemCount))) : '{{ number_format((int) ($cartSummary['items'] ?? 0)) }}')"
                    >{{ number_format((int) ($cartSummary['items'] ?? 0)) }}</span>
                </a>
            @else
                <button
                    type="button"
                    class="inline-flex h-12 min-w-[86px] items-center justify-center gap-2 rounded-xl border border-white/40 bg-white/15 px-4 text-base font-semibold hover:bg-white/25"
                    @click="openAuth('login')"
                >
                    <i class="fa-solid fa-basket-shopping text-xs" aria-hidden="true"></i>
                    <span
                        data-cart-count="1"
                        x-text="(Number.isFinite(Number(cartItemCount)) ? new Intl.NumberFormat('id-ID').format(Math.max(0, Number(cartItemCount))) : '{{ number_format((int) ($cartSummary['items'] ?? 0)) }}')"
                    >{{ number_format((int) ($cartSummary['items'] ?? 0)) }}</span>
                </button>
            @endauth
        </div>

        @if(!empty($activeAffiliateReferral) && !empty($canUseAffiliateProductFilter))
            @php
                $releaseReferralParams = [];
                if (!empty($searchKeyword)) {
                    $releaseReferralParams['q'] = $searchKeyword;
                }
                if (!empty($productSource) && $productSource !== 'all') {
                    $releaseReferralParams['source'] = $productSource;
                }
                if (!empty($affiliateReadyOnly) && ($productSource ?? 'all') === 'affiliate') {
                    $releaseReferralParams['ready_marketing'] = 1;
                }
                $releaseReferralParams['clear_ref'] = 1;
            @endphp
            <div class="mt-2 flex flex-wrap items-center gap-2 rounded-xl border border-cyan-100/45 bg-cyan-100/15 px-3 py-2 text-xs">
                <span class="inline-flex items-center rounded-full border border-cyan-100/55 bg-cyan-100/20 px-2.5 py-1 font-semibold text-cyan-50">
                    Link Affiliate Aktif
                </span>
                <span class="text-cyan-50/95">
                    Belanja via referral:
                    <strong class="font-semibold text-white">{{ $activeAffiliateReferral['name'] ?? 'Affiliate' }}</strong>
                </span>
                <a href="{{ route('landing', $releaseReferralParams) }}" class="inline-flex items-center rounded-full border border-white/35 bg-white/15 px-2.5 py-1 font-semibold text-white hover:bg-white/25">
                    Lepas Referral
                </a>
            </div>
        @endif

        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="animate-fade-up rounded-2xl border border-white/25 bg-white/10 p-5 backdrop-blur">
                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-semibold text-emerald-50/95 sm:text-sm">
                    <span>Belanja Transparan</span>
                    <span aria-hidden="true" class="text-white/45">|</span>
                    <span>Mitra Terverifikasi</span>
                    <span aria-hidden="true" class="text-white/45">|</span>
                    <span>Pembayaran Bank & Dompet Digital</span>
                </div>

                @php
                    $showMitraActivation = is_array($mitraSubmission ?? null);
                    $announcementCount = count($heroAnnouncementCards ?? []);
                    $mitraSubmissionOpen = (bool) ($mitraSubmission['open'] ?? false);
                @endphp
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                        :class="heroPanel === 'content'
                            ? 'border-white/70 bg-white/30 text-white'
                            : 'border-white/35 bg-white/10 text-emerald-50 hover:bg-white/20'"
                        @click="selectHeroPanel('content')"
                    >
                        Konten & Promo
                    </button>
                    @if($showMitraActivation)
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                            :class="heroPanel === 'mitra'
                                ? 'border-white/70 bg-white/30 text-white'
                                : 'border-white/35 bg-white/10 text-emerald-50 hover:bg-white/20'"
                            @click="selectHeroPanel('mitra')"
                        >
                            Pengajuan Mitra
                        </button>
                    @endif
                </div>

                <div class="mt-4">
                    @if($showMitraActivation)
                        <article
                            x-show="heroPanel === 'mitra'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-250"
                            x-transition:enter-start="opacity-0 translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="rounded-xl border p-3.5 {{ $mitraSubmissionOpen ? 'border-emerald-200/70 bg-emerald-100/20' : 'border-amber-200/70 bg-amber-100/20' }}"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-50">Pengajuan Mitra Pengadaan Admin</p>
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold {{ $mitraSubmissionOpen ? 'border-emerald-100/70 bg-emerald-100/30 text-emerald-50' : 'border-amber-100/70 bg-amber-100/35 text-amber-100' }}">
                                    {{ $mitraSubmissionOpen ? 'DIBUKA' : 'DITUTUP' }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm font-bold text-white">{{ $mitraSubmission['title'] }}</p>
                            <p class="mt-1 text-xs text-emerald-50/95">{{ $mitraSubmission['message'] }}</p>
                            <p class="mt-2 text-[11px] font-medium text-emerald-50/85">
                                Jalur ini khusus Mitra B2B pengadaan admin, terpisah dari aktivasi mode Penjual/Affiliate.
                            </p>
                            @if(!empty($mitraSubmission['cta_label']) && !empty($mitraSubmission['cta_url']))
                                <a href="{{ $mitraSubmission['cta_url'] }}" class="mt-3 inline-flex items-center rounded-lg border border-white/35 bg-white/20 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/30">
                                    {{ $mitraSubmission['cta_label'] }}
                                </a>
                            @endif
                        </article>
                    @endif

                    <section
                        @if($showMitraActivation)
                            x-show="heroPanel === 'content'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-250"
                            x-transition:enter-start="opacity-0 translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                        @endif
                        class="rounded-xl border border-white/25 bg-white/10 p-3"
                        @if($announcementCount > 1)
                            @mouseenter="stopHeroAnnouncementLoop()"
                            @mouseleave="startHeroAnnouncementLoop()"
                        @endif
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-50">Konten & Promo</p>
                                @if($announcementCount > 1)
                                    <span class="rounded-full border border-white/30 bg-white/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-50" x-text="`Konten ${heroAnnouncementIndex + 1}/{{ $announcementCount }}`"></span>
                                @endif
                            </div>
                            @if($announcementCount > 1)
                                <div class="flex items-center gap-1">
                                    <button type="button" class="rounded-md border border-white/30 bg-white/10 px-2 py-1 text-[10px] font-semibold text-white hover:bg-white/20" @click="prevHeroAnnouncement()">
                                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="rounded-md border border-white/30 bg-white/10 px-2 py-1 text-[10px] font-semibold text-white hover:bg-white/20" @click="nextHeroAnnouncement()">
                                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            @endif
                        </div>

                        @if($announcementCount > 0)
                            <div class="mt-2 min-h-[162px]">
                                @foreach($heroAnnouncementCards as $index => $announcement)
                                    <article
                                        x-show="heroAnnouncementIndex === {{ $index }}"
                                        x-transition:enter="transition ease-out duration-300"
                                        x-transition:enter-start="opacity-0 translate-y-2"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        x-transition:leave="transition ease-in duration-250"
                                        x-transition:leave-start="opacity-100 translate-y-0"
                                        x-transition:leave-end="opacity-0 -translate-y-2"
                                        class="rounded-lg border border-white/20 bg-white/10 p-3"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-semibold tracking-wide {{ $announcement['type_class'] }}">{{ $announcement['type_label'] }}</span>
                                            <span class="text-[10px] font-semibold text-emerald-50/80">Info Admin</span>
                                        </div>
                                        @if(!empty($announcement['image_src']))
                                            <div class="mt-2 overflow-hidden rounded-lg border border-white/25 bg-white/10">
                                                <img src="{{ $announcement['image_src'] }}" alt="{{ $announcement['title'] }}" class="h-24 w-full object-cover">
                                            </div>
                                        @endif
                                        <p class="mt-2 text-sm font-bold text-white">{{ $announcement['title'] }}</p>
                                        <p class="mt-1 text-xs text-emerald-50/95">{{ $announcement['message'] }}</p>
                                        @if(!empty($announcement['cta_label']) && !empty($announcement['cta_url']))
                                            <a
                                                href="{{ $announcement['cta_url'] }}"
                                                class="mt-2 inline-flex items-center rounded-lg border border-white/30 bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-white/25"
                                                @if($announcement['is_external_cta']) target="_blank" rel="noopener noreferrer" @endif
                                            >
                                                {{ $announcement['cta_label'] }}
                                            </a>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <article class="mt-2 rounded-lg border border-white/20 bg-white/10 p-3">
                                <p class="text-sm font-bold text-white">Belum Ada Promo Aktif</p>
                                <p class="mt-1 text-xs text-emerald-50/90">Admin dapat menambahkan promo, banner, atau informasi baru dari menu Marketplace Admin.</p>
                            </article>
                        @endif

                        @if($announcementCount > 1)
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                @foreach($heroAnnouncementCards as $index => $announcement)
                                    <button
                                        type="button"
                                        class="h-2.5 w-2.5 rounded-full border border-white/40 transition"
                                        :class="heroAnnouncementIndex === {{ $index }} ? 'bg-white' : 'bg-white/20'"
                                        @click="heroAnnouncementIndex = {{ $index }}"
                                        aria-label="Buka konten {{ $index + 1 }}"
                                    ></button>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>
            </div>

            <div id="fitur-cuaca" class="animate-fade-up-delay rounded-2xl border border-cyan-200 bg-white p-5 shadow-lg shadow-cyan-100">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Fitur Unggulan</p>
                <h2 class="mt-1 text-lg font-bold text-slate-900">Cuaca & Lokasi</h2>
                <p class="mt-1 text-xs text-slate-600">Prediksi cuaca membantu keputusan pembelian, stok, dan distribusi harian.</p>
                <p class="mt-2 inline-flex rounded-full bg-cyan-100 px-3 py-1 text-[11px] font-semibold text-cyan-800">{{ $weatherLocationLabel }}</p>
                <div class="mt-3">
                    <x-weather-widget
                        :minimal="true"
                        :notifications="$weatherNotifications ?? collect()"
                        :unread-count="(int) ($weatherNotificationUnreadCount ?? 0)"
                        :mark-read-redirect="route('landing') . '#fitur-cuaca'"
                    />
                </div>
            </div>
        </div>
    </div>
</header>
