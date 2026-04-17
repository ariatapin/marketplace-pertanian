<x-admin-layout>
    <x-slot name="header">
        {{ __('Detail Laporan Produk') }}
    </x-slot>

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Laporan Produk #{{ $report->id }}</h2>
                <a href="{{ route('admin.modules.reports') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Kembali ke daftar</a>
            </div>
            <p class="mt-2 text-sm text-slate-600">Kategori: <span class="font-semibold uppercase">{{ $report->category_label }}</span></p>
            <p class="mt-1 text-sm text-slate-600">Status: <span class="font-semibold uppercase">{{ $report->status_label }}</span></p>
        </section>

        <section class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Informasi Pelapor</h3>
            <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-center">
                <img
                    src="{{ $report->reporter_photo_url }}"
                    alt="Foto Profil Pelapor"
                    class="h-16 w-16 rounded-full object-cover ring-2 ring-slate-200"
                >
                <div class="space-y-1 text-sm">
                    <p class="text-slate-900"><span class="font-semibold">Nama:</span> {{ $report->reporter_name_label }}</p>
                    <p class="text-slate-700"><span class="font-semibold">Email:</span> {{ $report->reporter_email_label }}</p>
                    <p class="text-slate-700"><span class="font-semibold">ID Pelapor:</span> {{ $report->reporter_id_label }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Detail Laporan</h3>
            <div class="mt-4 space-y-2 text-sm text-slate-700">
                <p><span class="font-semibold text-slate-900">Produk:</span> {{ $report->product_name_label }} ({{ $report->product_type_label }} {{ $report->product_id_label }})</p>
                <p><span class="font-semibold text-slate-900">Pemilik Produk:</span> {{ $report->reported_user_name_label }} ({{ $report->reported_user_email_label }})</p>
                <p><span class="font-semibold text-slate-900">Dibuat:</span> {{ $report->created_at_label }}</p>
                <p><span class="font-semibold text-slate-900">Ditangani oleh:</span> {{ $report->handler_name_label }}</p>
                <p><span class="font-semibold text-slate-900">Waktu Penanganan:</span> {{ $report->handled_at_label }}</p>
                <p><span class="font-semibold text-slate-900">Catatan Penanganan:</span> {{ $report->resolution_notes_label }}</p>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="font-semibold text-slate-900">Isi Laporan</p>
                    <p class="mt-2 leading-relaxed">{{ $report->description_label }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Aksi Review Admin</h3>
            <form method="POST" action="{{ route('admin.modules.reports.products.review', ['productReportId' => $report->id]) }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 text-sm" required>
                        <option value="pending" @selected($report->status_label === 'PENDING')>Pending</option>
                        <option value="under_review" @selected($report->status_label === 'UNDER_REVIEW')>Under Review</option>
                        <option value="resolved" @selected($report->status_label === 'RESOLVED')>Resolved</option>
                        <option value="cancelled" @selected($report->status_label === 'CANCELLED')>Cancelled</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="resolution_notes" class="mb-1 block text-sm font-medium text-slate-700">Catatan</label>
                    <textarea id="resolution_notes" name="resolution_notes" rows="3" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Simpan Review
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-admin-layout>
