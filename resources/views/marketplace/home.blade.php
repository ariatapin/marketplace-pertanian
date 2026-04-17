<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Marketplace') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/marketplace.js', 'resources/css/app.css', 'resources/css/marketplace.css'])
    @livewireStyles
</head>
@php
    $guestAuthParam = request()->query('auth');
    $authModeFromQuery = $guestAuthParam === 'register' ? 'register' : 'login';
    $shouldOpenAuthFromQuery = auth()->guest() && in_array($guestAuthParam, ['login', 'register'], true);
    $initialAuthModalOpen = auth()->check()
        ? false
        : ($errors->any() || $shouldOpenAuthFromQuery);
    $initialAuthMode = old('auth_form');
    if (! in_array($initialAuthMode, ['login', 'register'], true)) {
        $initialAuthMode = $authModeFromQuery;
    }
    $heroAnnouncementCount = count($heroAnnouncementCards ?? []);
    $heroMitraSubmissionOpen = (bool) ($mitraSubmission['open'] ?? false);
@endphp
<body
    class="app-shell min-h-screen text-slate-900"
    x-data="{
            landingAccountOpen: false,
            authModalOpen: {{ $initialAuthModalOpen ? 'true' : 'false' }},
            authMode: '{{ $initialAuthMode === 'register' ? 'register' : 'login' }}',
            heroAnnouncementCount: {{ (int) $heroAnnouncementCount }},
            mitraSubmissionOpen: {{ $heroMitraSubmissionOpen ? 'true' : 'false' }},
            heroPanel: {{ $heroMitraSubmissionOpen ? '\'mitra\'' : '\'content\'' }},
            heroAnnouncementIndex: 0,
            heroAnnouncementTimer: null,
            sourceSwitchLoading: false,
            affiliateCopiedProductId: null,
            cartNoticeMessage: '',
            cartNoticeType: 'success',
            cartNoticeTimer: null,
            addingProductKey: null,
            isAuthenticated: {{ auth()->check() ? 'true' : 'false' }},
            cartItemCount: {{ (int) ($cartSummary['items'] ?? 0) }},
            cartStoreUrl: '{{ auth()->check() ? route('cart.store') : '' }}',
            loginUrl: '{{ route('landing', ['auth' => 'login']) }}',
            init() {
                this.startHeroAnnouncementLoop();
            },
            openAuth(mode) {
                this.authMode = mode === 'register' ? 'register' : 'login';
                this.authModalOpen = true;
                this.landingAccountOpen = false;
            },
            closeAuth() {
                this.authModalOpen = false;
            },
            selectHeroPanel(panel) {
                this.heroPanel = panel === 'mitra' ? 'mitra' : 'content';
            },
            async switchMarketplaceSource(url) {
                const targetUrl = (url || '').toString().trim();
                if (targetUrl === '' || this.sourceSwitchLoading) {
                    return;
                }
                const targetPanel = document.getElementById('marketplace-products-panel');
                if (!targetPanel) {
                    window.location.href = targetUrl;
                    return;
                }
                this.sourceSwitchLoading = true;
                try {
                    const requestUrl = new URL(targetUrl, window.location.origin);
                    requestUrl.searchParams.set('partial_products', '1');
                    const response = await fetch(requestUrl.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                    });
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    const payload = await response.json();
                    const html = (payload && typeof payload.html === 'string') ? payload.html : '';
                    if (html === '') {
                        throw new Error('Empty payload');
                    }
                    targetPanel.innerHTML = html;
                    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                        window.Alpine.initTree(targetPanel);
                    }
                    window.history.replaceState({}, '', targetUrl);
                } catch (error) {
                    window.location.href = targetUrl;
                } finally {
                    this.sourceSwitchLoading = false;
                }
            },
            copyAffiliateLink(url, productId) {
                const value = (url || '').toString().trim();
                if (!value) return;
                const done = () => {
                    this.affiliateCopiedProductId = Number(productId || 0);
                    setTimeout(() => {
                        if (this.affiliateCopiedProductId === Number(productId || 0)) {
                            this.affiliateCopiedProductId = null;
                        }
                    }, 2200);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done).catch(() => this.copyAffiliateLinkFallback(value, done));
                    return;
                }
                this.copyAffiliateLinkFallback(value, done);
            },
            copyAffiliateLinkFallback(value, done) {
                const input = document.createElement('input');
                input.value = value;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                done();
            },
            showCartNotice(message, type = 'success') {
                this.cartNoticeMessage = (message || '').toString().trim();
                this.cartNoticeType = type === 'error' ? 'error' : 'success';
                if (this.cartNoticeTimer) {
                    clearTimeout(this.cartNoticeTimer);
                }
                this.cartNoticeTimer = setTimeout(() => {
                    this.cartNoticeMessage = '';
                    this.cartNoticeTimer = null;
                }, 3000);
            },
            setCartSummary(summary = {}) {
                const nextCount = Number(summary.items);
                if (Number.isFinite(nextCount) && nextCount >= 0) {
                    this.cartItemCount = nextCount;
                }
            },
            formatCartCount() {
                const count = Math.max(0, Number(this.cartItemCount || 0));
                return new Intl.NumberFormat('id-ID').format(count);
            },
            async addProductToCart(payload = {}) {
                const productId = Number(payload.productId || 0);
                const productType = (payload.productType || 'store').toString() === 'farmer' ? 'farmer' : 'store';
                const qty = Math.max(1, Number(payload.qty || 1));
                const productKey = `${productType}:${productId}`;

                if (productId <= 0 || this.addingProductKey === productKey) return;
                if (!this.isAuthenticated) {
                    this.openAuth('login');
                    return;
                }
                if (!this.cartStoreUrl) {
                    this.showCartNotice('Aksi keranjang belum tersedia.', 'error');
                    return;
                }

                this.addingProductKey = productKey;
                try {
                    const csrfToken = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
                    const response = await fetch(this.cartStoreUrl, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            product_id: productId,
                            product_type: productType,
                            qty,
                        }),
                    });

                    const contentType = (response.headers.get('content-type') || '').toLowerCase();
                    if (!contentType.includes('application/json')) {
                        if (response.redirected && response.url) {
                            window.location.href = response.url;
                            return;
                        }
                        this.showCartNotice('Sesi login berakhir. Silakan login kembali.', 'error');
                        return;
                    }

                    const payloadResponse = await response.json();

                    if (!response.ok) {
                        let errorMessage = 'Produk gagal ditambahkan ke keranjang.';
                        const validationError = payloadResponse?.errors ? Object.values(payloadResponse.errors)[0]?.[0] : null;
                        errorMessage = payloadResponse?.message || validationError || errorMessage;
                        this.showCartNotice(errorMessage, 'error');
                        return;
                    }

                    this.setCartSummary(payloadResponse?.cart_summary || payloadResponse?.cartSummary || {});
                    this.showCartNotice(payloadResponse?.message || 'Produk berhasil dimasukkan ke keranjang.', 'success');
                } catch (_error) {
                    this.showCartNotice('Terjadi kendala jaringan saat menambah ke keranjang.', 'error');
                } finally {
                    this.addingProductKey = null;
                }
            },
            startHeroAnnouncementLoop() {
                this.stopHeroAnnouncementLoop();
                if (this.heroAnnouncementCount <= 1) {
                    this.heroAnnouncementIndex = 0;
                    return;
                }
                this.heroAnnouncementTimer = setInterval(() => {
                    this.nextHeroAnnouncement();
                }, 5000);
            },
            stopHeroAnnouncementLoop() {
                if (this.heroAnnouncementTimer) {
                    clearInterval(this.heroAnnouncementTimer);
                    this.heroAnnouncementTimer = null;
                }
            },
            nextHeroAnnouncement() {
                if (this.heroAnnouncementCount <= 1) {
                    this.heroAnnouncementIndex = 0;
                    return;
                }
                this.heroAnnouncementIndex = (this.heroAnnouncementIndex + 1) % this.heroAnnouncementCount;
            },
            prevHeroAnnouncement() {
                if (this.heroAnnouncementCount <= 1) {
                    this.heroAnnouncementIndex = 0;
                    return;
                }
                this.heroAnnouncementIndex = (this.heroAnnouncementIndex - 1 + this.heroAnnouncementCount) % this.heroAnnouncementCount;
            },
        }"
>
    @include('marketplace._header')
    @include('marketplace._main-content')
    @include('marketplace._auth-modal')

    @livewireScripts
</body>
</html>

