<x-admin-layout>
    {{-- CATATAN-AUDIT: Modul laporan berfokus pada laporan user/dispute, bukan ringkasan order operasional. --}}
    <x-slot name="header">
        {{ __('Modul Laporan') }}
    </x-slot>

    <div data-testid="admin-reports-page" class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <section data-testid="admin-reports-summary" class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Laporan User</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $summary['total_reports_label'] ?? '0' }}</p>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Pending</p>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ $summary['pending_reports_label'] ?? '0' }}</p>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Under Review</p>
                <p class="mt-2 text-2xl font-bold text-indigo-700">{{ $summary['under_review_reports_label'] ?? '0' }}</p>
            </div>
            <div class="rounded-xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Resolved</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $summary['resolved_reports_label'] ?? '0' }}</p>
            </div>
        </section>

        <section data-testid="admin-reports-filters" class="rounded-xl border bg-white p-6">
            <h3 class="text-base font-semibold text-slate-900">Filter Laporan Masuk</h3>
            <form method="GET" action="{{ route('admin.modules.reports') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-5">
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Semua</option>
                        <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                        <option value="under_review" @selected(($filters['status'] ?? '') === 'under_review')>Under Review</option>
                        <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>Resolved (Produk)</option>
                        <option value="resolved_buyer" @selected(($filters['status'] ?? '') === 'resolved_buyer')>Resolved Buyer</option>
                        <option value="resolved_seller" @selected(($filters['status'] ?? '') === 'resolved_seller')>Resolved Seller</option>
                        <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label for="category" class="mb-1 block text-sm font-medium text-slate-700">Jenis Laporan</label>
                    <select id="category" name="category" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Semua</option>
                        @foreach(($categoryOptions ?? collect()) as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ strtoupper($category) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="q" class="mb-1 block text-sm font-medium text-slate-700">Cari Pelapor (ID/Nama/Email)</label>
                    <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="contoh: 12 atau user@email.com" class="w-full rounded-md border-slate-300 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
                    <a href="{{ route('admin.modules.reports') }}" class="rounded-md border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </section>

        <section data-testid="admin-reports-table" class="rounded-xl border bg-white p-6">
            <h3 class="text-base font-semibold text-slate-900">Daftar Laporan Users</h3>

            <div class="mt-4 overflow-x-auto">
                @if($reportRows->isEmpty())
                    <p class="text-sm text-slate-600">Belum ada laporan dari users.</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-slate-600">
                                <th class="py-2 pr-4">ID Laporan</th>
                                <th class="py-2 pr-4">Pelapor</th>
                                <th class="py-2 pr-4">Jenis Laporan</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Order</th>
                                <th class="py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reportRows as $row)
                                <tr class="border-b last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">#{{ $row->id }}</td>
                                    <td class="py-3 pr-4">
                                        <p class="font-medium text-slate-900">{{ $row->reporter_name_label }}</p>
                                        <p class="text-xs text-slate-600">{{ $row->reporter_email_label }}</p>
                                    </td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $row->category_label }}</td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $row->status_label }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->order_id_label }}</td>
                                    <td class="py-3">
                                        <a href="{{ route('admin.modules.reports.show', ['reportId' => $row->id]) }}" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                            Lihat Detail
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $reportRows->links() }}
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <h3 class="text-base font-semibold text-slate-900">Laporan Produk Marketplace</h3>

            <div class="mt-4 overflow-x-auto">
                @if(($productReportRows ?? collect())->isEmpty())
                    <p class="text-sm text-slate-600">Belum ada laporan produk dari user.</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-slate-600">
                                <th class="py-2 pr-4">ID</th>
                                <th class="py-2 pr-4">Pelapor</th>
                                <th class="py-2 pr-4">Pemilik Produk</th>
                                <th class="py-2 pr-4">Produk</th>
                                <th class="py-2 pr-4">Kategori</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productReportRows as $row)
                                <tr class="border-b last:border-0">
                                    <td class="py-3 pr-4 font-semibold text-slate-900">#{{ $row->id }}</td>
                                    <td class="py-3 pr-4">
                                        <p class="font-medium text-slate-900">{{ $row->reporter_name_label }}</p>
                                        <p class="text-xs text-slate-600">{{ $row->reporter_email_label }}</p>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->reported_user_name_label }}</td>
                                    <td class="py-3 pr-4">
                                        <p class="font-medium text-slate-900">{{ $row->product_name_label }}</p>
                                        <p class="text-xs text-slate-600">{{ $row->product_type_label }} {{ $row->product_id_label }}</p>
                                    </td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $row->category_label }}</td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $row->status_label }}</td>
                                    <td class="py-3">
                                        <a href="{{ route('admin.modules.reports.products.show', ['productReportId' => $row->id]) }}" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                            Lihat Detail
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $productReportRows->links() }}
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-admin-layout>
