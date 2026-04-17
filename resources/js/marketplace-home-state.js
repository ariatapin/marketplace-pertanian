window.marketplaceHomeState = function marketplaceHomeState(config = {}) {
    return {
        landingAccountOpen: false,
        authModalOpen: Boolean(config.authModalOpen),
        authMode: config.authMode === 'register' ? 'register' : 'login',
        heroAnnouncementCount: Math.max(0, Number(config.heroAnnouncementCount || 0)),
        mitraSubmissionOpen: Boolean(config.mitraSubmissionOpen),
        heroPanel: Boolean(config.mitraSubmissionOpen) ? 'mitra' : 'content',
        heroAnnouncementIndex: 0,
        heroAnnouncementTimer: null,
        sourceSwitchLoading: false,
        affiliateCopiedProductId: null,
        cartNoticeMessage: '',
        cartNoticeType: 'success',
        cartNoticeTimer: null,
        addingProductKey: null,
        isAuthenticated: Boolean(config.isAuthenticated),
        cartItemCount: Math.max(0, Number(config.cartItemCount || 0)),
        cartStoreUrl: (config.cartStoreUrl || '').toString(),
        loginUrl: (config.loginUrl || '').toString(),

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

                const response = await window.fetch(requestUrl.toString(), {
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
            if (!value) {
                return;
            }

            const markCopied = () => {
                this.affiliateCopiedProductId = Number(productId || 0);
                window.setTimeout(() => {
                    if (this.affiliateCopiedProductId === Number(productId || 0)) {
                        this.affiliateCopiedProductId = null;
                    }
                }, 2200);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value)
                    .then(markCopied)
                    .catch(() => this.copyAffiliateLinkFallback(value, markCopied));
                return;
            }

            this.copyAffiliateLinkFallback(value, markCopied);
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
                window.clearTimeout(this.cartNoticeTimer);
            }

            this.cartNoticeTimer = window.setTimeout(() => {
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

            if (productId <= 0 || this.addingProductKey === productKey) {
                return;
            }

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
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const response = await window.fetch(this.cartStoreUrl, {
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

            this.heroAnnouncementTimer = window.setInterval(() => {
                this.nextHeroAnnouncement();
            }, 5000);
        },

        stopHeroAnnouncementLoop() {
            if (this.heroAnnouncementTimer) {
                window.clearInterval(this.heroAnnouncementTimer);
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
    };
};
