<x-admin-layout>
    {{-- CATATAN-AUDIT: Halaman ini hanya monitoring order selesai customer->mitra (read-only dari sisi admin). --}}
    <x-slot name="header">
        {{ __('Status Pesanan') }}
    </x-slot>

    <div data-testid="admin-orders-page" class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
        <section data-testid="admin-orders-overview" class="rounded-xl border bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Status Pesanan Selesai</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Halaman ini hanya menampilkan ringkasan order customer ke Mitra yang sudah selesai.
                    </p>
                </div>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                    Read-Only Monitoring
                </span>
            </div>
        </section>

        <section data-testid="admin-orders-summary" class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Order Selesai</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format((int) ($summary['total_completed_orders'] ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Via Affiliate</p>
                <p class="mt-1 text-xl font-bold text-emerald-700">{{ number_format((int) ($summary['completed_affiliate_orders'] ?? 0)) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Tanpa Affiliate</p>
                <p class="mt-1 text-xl font-bold text-amber-700">{{ number_format((int) ($summary['completed_non_affiliate_orders'] ?? 0)) }}</p>
            </div>
        </section>

        <section data-testid="admin-orders-filters" class="rounded-xl border bg-white p-6">
            <form method="GET" action="{{ route('admin.modules.orders') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label for="affiliate_source" class="mb-1 block text-sm font-medium text-slate-700">Sumber Affiliate</label>
                    <select id="affiliate_source" name="affiliate_source" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Semua</option>
                        <option value="affiliate" @selected(($filters['affiliate_source'] ?? '') === 'affiliate')>Via Affiliate</option>
                        <option value="non_affiliate" @selected(($filters['affiliate_source'] ?? '') === 'non_affiliate')>Tanpa Affiliate</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="q" class="mb-1 block text-sm font-medium text-slate-700">Cari Customer</label>
                    <input
                        id="q"
                        name="q"
                        type="text"
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="id / nama / email customer"
                        class="w-full rounded-md border-slate-300 text-sm"
                    >
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('admin.modules.orders') }}" class="rounded-md border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </section>

        <section data-testid="admin-orders-table" class="rounded-xl border bg-white p-6">
            @if($rows->isEmpty())
                <p class="text-sm text-slate-600">Belum ada data order selesai customer ke Mitra.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-slate-600">
                                <th class="py-2 pr-4">Customer</th>
                                <th class="py-2 pr-4">Total Selesai</th>
                                <th class="py-2 pr-4">Via Affiliate</th>
                                <th class="py-2 pr-4">Tanpa Affiliate</th>
                                <th class="py-2 pr-4">Total Nilai</th>
                                <th class="py-2">Selesai Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr class="border-b last:border-0">
                                    <td class="py-3 pr-4">
                                        <p class="font-medium text-slate-900">{{ $row->buyer_name_label }}</p>
                                        <p class="text-xs text-slate-600">ID: {{ $row->buyer_id }} • {{ $row->buyer_email_label }}</p>
                                    </td>
                                    <td class="py-3 pr-4 font-semibold text-slate-900">{{ $row->total_completed_orders_label }}</td>
                                    <td class="py-3 pr-4 font-semibold text-emerald-700">{{ $row->affiliate_completed_orders_label }}</td>
                                    <td class="py-3 pr-4 font-semibold text-amber-700">{{ $row->non_affiliate_completed_orders_label }}</td>
                                    <td class="py-3 pr-4 text-slate-900">{{ $row->total_completed_amount_label }}</td>
                                    <td class="py-3 text-slate-600">{{ $row->last_completed_at_label }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        </section>
    </div>
</x-admin-layout>
