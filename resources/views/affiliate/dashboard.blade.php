<x-affiliate-layout>
    <x-slot name="header">Dashboard Affiliate</x-slot>

    <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section
            class="rounded-3xl border border-slate-800/20 bg-gradient-to-r from-emerald-700 via-emerald-600 to-cyan-600 p-5 text-white shadow-xl md:p-6"
            x-data="{
                copied: false,
                copyLink() {
                    if (!this.$refs.affiliateLink) return;
                    const value = this.$refs.affiliateLink.value || '';
                    if (!navigator.clipboard || !navigator.clipboard.writeText) {
                        this.$refs.affiliateLink.focus();
                        this.$refs.affiliateLink.select();
                        document.execCommand('copy');
                        this.copied = true;
                        setTimeout(() => this.copied = false, 1800);
                        return;
                    }
                    navigator.clipboard.writeText(value).then(() => {
                        this.copied = true;
                        setTimeout(() => this.copied = false, 1800);
                    });
                }
            }"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs uppercase tracking-[0.2em] text-emerald-100">Affiliate Control</p>
                    <h2 class="mt-1.5 text-2xl font-bold">Kelola Komisi & Monitoring Reward</h2>
                    <p class="mt-2 text-sm text-emerald-50">
                        Akun Anda tetap role consumer, tetapi mode affiliate aktif untuk mengelola komisi dan reward.
                    </p>
                </div>

                @if(!empty($affiliateReferralLink))
                    <div class="w-full rounded-xl border border-white/30 bg-white/10 p-3 lg:mt-1 lg:w-[460px]">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100">Link Referral Affiliate</p>
                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto]">
                            <input
                                x-ref="affiliateLink"
                                type="text"
                                readonly
                                value="{{ $affiliateReferralLink }}"
                                class="w-full rounded-lg border border-white/30 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-emerald-100"
                            >
                            <button
                                type="button"
                                class="rounded-lg border border-white/30 bg-white/15 px-3 py-2 text-xs font-semibold text-white hover:bg-white/25"
                                @click="copyLink()"
                            >
                                Salin Link
                            </button>
                        </div>
                        <p x-show="copied" x-cloak class="mt-2 text-xs font-semibold text-emerald-100">Link referral berhasil disalin.</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Saldo Wallet</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Rp{{ number_format((float) $summary['balance'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Topup demo tersedia di halaman Profil.</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Komisi</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">Rp{{ number_format((float) $summary['total_commission'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ number_format((int) $summary['commission_count']) }} transaksi komisi</p>
            </article>
            <article class="surface-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Konversi Selesai</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format((float) ($trackingSummary['conversion_completed_percent'] ?? 0), 2, ',', '.') }}%</p>
                <p class="mt-1 text-xs text-slate-500">Checkout: {{ number_format((float) ($trackingSummary['conversion_checkout_percent'] ?? 0), 2, ',', '.') }}%</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-[1.15fr_0.85fr]">
            <article class="surface-card p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">Produk Aktif Dipasarkan</h3>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-600">5 terbaru</span>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse($recentActivePromotedProducts as $product)
                        <article class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $product->product_name }}</p>
                                <div class="shrink-0 text-right text-[11px] text-slate-500">
                                    <p>Aktif sejak {{ \Illuminate\Support\Carbon::parse($product->start_date)->translatedFormat('d M Y') }}</p>
                                    <p>Sisa {{ number_format((int) ($product->days_left ?? 0)) }} hari</p>
                                </div>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-600">Mitra: {{ $product->mitra_name }}</p>
                        </article>
                    @empty
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-600">
                            Belum ada produk aktif dipasarkan.
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="surface-card p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">Informasi Cuaca</h3>
                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $weatherSummary['severity_badge_class'] ?? 'border-emerald-200 bg-emerald-100 text-emerald-700' }}">
                        {{ $weatherSummary['severity_label'] ?? 'NORMAL' }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-slate-600">{{ $weatherSummary['location_label'] ?? 'Lokasi belum diset' }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $weatherSummary['message'] ?? 'Cuaca relatif aman.' }}</p>
                @if(!empty($weatherSummary['valid_until_label']))
                    <p class="mt-1 text-[11px] text-slate-500">Valid hingga {{ $weatherSummary['valid_until_label'] }}</p>
                @endif
                <div class="mt-3 grid grid-cols-3 gap-2 text-[11px]">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-2">
                        <p class="text-slate-500">Suhu</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $weatherSummary['temperature_label'] ?? '-' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-2">
                        <p class="text-slate-500">Kelembapan</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $weatherSummary['humidity_label'] ?? '-' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-2">
                        <p class="text-slate-500">Angin</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $weatherSummary['wind_label'] ?? '-' }}</p>
                    </div>
                </div>
            </article>
        </section>
    </div>
</x-affiliate-layout>
