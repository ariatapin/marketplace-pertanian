<main class="mx-auto max-w-7xl px-4 py-6 md:px-6 lg:px-8">
    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any() && !(auth()->guest() && in_array(old('auth_form'), ['login', 'register'], true)))
        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="mt-4 grid grid-cols-1 gap-5 {{ $isActiveConsumerDashboard ? 'lg:grid-cols-[1.5fr_0.78fr]' : '' }}">
        <div class="space-y-5">
            <div id="marketplace-products-panel">
                @include('marketplace._products-panel')
            </div>

            <div class="surface-card p-5">
                <h2 class="text-lg font-bold text-slate-900">Alur Belanja Cepat</h2>
                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">1. Pilih Produk</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Cari kebutuhan tani dari mitra aktif.</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">2. Checkout</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Pilih metode pembayaran saat checkout. Metode saldo diproses otomatis, transfer bank lanjut upload bukti transfer.</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">3. Pantau Order</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Upload bukti pembayaran dan monitor status pesanan.</p>
                    </article>
                </div>
            </div>
        </div>

        @if($isActiveConsumerDashboard)
            <aside class="space-y-4">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <h3 class="text-sm font-bold text-emerald-900">Ringkasan Consumer Aktif</h3>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="text-emerald-800/80">Mode</p>
                            <p class="font-semibold text-emerald-900">{{ strtoupper($consumerSummary['mode'] ?? 'buyer') }}</p>
                        </div>
                        <div>
                            <p class="text-emerald-800/80">Status</p>
                            <p class="font-semibold text-emerald-900">{{ strtoupper($consumerSummary['mode_status'] ?? 'none') }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-emerald-800/80">Lokasi</p>
                            <p class="font-semibold text-emerald-900">{{ $consumerSummary['location_label'] ?? 'Belum diset' }}</p>
                        </div>
                        <div>
                            <p class="text-emerald-800/80">Total Order</p>
                            <p class="font-semibold text-emerald-900">{{ number_format((int) ($consumerSummary['total_orders'] ?? 0)) }}</p>
                        </div>
                        <div>
                            <p class="text-emerald-800/80">Order Aktif</p>
                            <p class="font-semibold text-emerald-900">{{ number_format((int) ($consumerSummary['active_orders'] ?? 0)) }}</p>
                        </div>
                        <div>
                            <p class="text-emerald-800/80">Uang Demo</p>
                            <p class="font-semibold text-emerald-900">Rp{{ number_format((float) ($consumerDemoBalance ?? 0), 0, ',', '.') }}</p>
                        </div>
                    </div>
                    @php
                        $activeMode = (string) ($consumerSummary['mode'] ?? 'buyer');
                        $activeModeStatus = (string) ($consumerSummary['mode_status'] ?? 'none');
                        $modeDashboardUrl = null;
                        $modeDashboardLabel = null;

                        if ($activeMode === 'affiliate' && $activeModeStatus === 'approved') {
                            $modeDashboardUrl = route('affiliate.dashboard');
                            $modeDashboardLabel = 'Masuk Dashboard Affiliate';
                        } elseif ($activeMode === 'farmer_seller' && $activeModeStatus === 'approved') {
                            $modeDashboardUrl = route('seller.dashboard');
                            $modeDashboardLabel = 'Masuk Dashboard Penjual';
                        }
                    @endphp
                    @if($modeDashboardUrl && $modeDashboardLabel)
                        <a
                            href="{{ $modeDashboardUrl }}"
                            class="mt-4 inline-flex items-center justify-center rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800"
                        >
                            {{ $modeDashboardLabel }}
                        </a>
                    @endif
                </div>
            </aside>
        @endif
    </section>
</main>
