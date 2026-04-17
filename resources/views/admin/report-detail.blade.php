<x-admin-layout>
    <x-slot name="header">
        {{ __('Detail Laporan') }}
    </x-slot>

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <section class="rounded-xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Laporan #{{ $report->id }}</h2>
                <a href="{{ route('admin.modules.reports') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Kembali ke daftar</a>
            </div>
            <p class="mt-2 text-sm text-slate-600">Jenis: <span class="font-semibold uppercase">{{ $report->category_label }}</span></p>
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
                <p><span class="font-semibold text-slate-900">Order:</span> {{ $report->order_id_label }}</p>
                <p><span class="font-semibold text-slate-900">Dibuat:</span> {{ $report->created_at_label }}</p>
                <p><span class="font-semibold text-slate-900">Ditangani oleh:</span> {{ $report->handler_name_label }}</p>
                <p><span class="font-semibold text-slate-900">Waktu Penanganan:</span> {{ $report->handled_at_label }}</p>
                <p><span class="font-semibold text-slate-900">Resolusi:</span> {{ $report->resolution_label }}</p>
                <p><span class="font-semibold text-slate-900">Catatan Resolusi:</span> {{ $report->resolution_notes_label }}</p>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="font-semibold text-slate-900">Isi Laporan</p>
                    <p class="mt-2 leading-relaxed">{{ $report->description_label }}</p>
                </div>
                @if(!empty($report->evidence_urls))
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <p class="font-semibold text-slate-900">Bukti URL</p>
                        <ul class="mt-2 space-y-2">
                            @foreach($report->evidence_urls as $url)
                                <li class="text-xs">
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-indigo-700 hover:text-indigo-900">{{ $url }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Aksi Review Admin</h3>
            <form method="POST" action="{{ route('admin.modules.reports.review', ['reportId' => $report->id]) }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                @csrf
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 text-sm" required>
                        <option value="under_review">Under Review</option>
                        <option value="resolved_buyer">Resolved Buyer</option>
                        <option value="resolved_seller">Resolved Seller</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label for="resolution" class="mb-1 block text-sm font-medium text-slate-700">Resolusi</label>
                    <select id="resolution" name="resolution" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Pilih Resolusi</option>
                        <option value="refund_full">Refund Full</option>
                        <option value="refund_partial">Refund Partial</option>
                        <option value="release_to_seller">Release to Seller</option>
                    </select>
                </div>
                <div>
                    <label for="refund_amount" class="mb-1 block text-sm font-medium text-slate-700">Nominal Refund (opsional)</label>
                    <input id="refund_amount" name="refund_amount" type="number" min="1" step="0.01" class="w-full rounded-md border-slate-300 text-sm" placeholder="Contoh: 25000">
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

        <section class="rounded-xl border bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Informasi Refund</h3>
            @if(!$refund)
                <p class="mt-3 text-sm text-slate-600">Belum ada data refund untuk order ini.</p>
            @else
                <div class="mt-3 space-y-2 text-sm text-slate-700">
                    <p><span class="font-semibold text-slate-900">Refund ID:</span> #{{ $refund->id }}</p>
                    <p><span class="font-semibold text-slate-900">Status:</span> {{ strtoupper((string) ($refund->status ?? '-')) }}</p>
                    <p><span class="font-semibold text-slate-900">Nominal:</span> Rp{{ number_format((float) ($refund->amount ?? 0), 0, ',', '.') }}</p>
                    <p><span class="font-semibold text-slate-900">Alasan:</span> {{ (string) ($refund->reason ?? '-') }}</p>
                    <p><span class="font-semibold text-slate-900">Diproses Oleh:</span> {{ (string) ($refund->processed_by_name ?? '-') }}</p>
                </div>

                @if(in_array((string) ($refund->status ?? ''), ['approved', 'pending'], true))
                    <form method="POST" action="{{ route('admin.modules.refunds.paid', ['refundId' => $refund->id]) }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                        @csrf
                        <div>
                            <label for="refund_reference" class="mb-1 block text-sm font-medium text-slate-700">Referensi Refund</label>
                            <input id="refund_reference" name="refund_reference" type="text" class="w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div>
                            <label for="refund_proof_url" class="mb-1 block text-sm font-medium text-slate-700">URL Bukti Refund</label>
                            <input id="refund_proof_url" name="refund_proof_url" type="text" class="w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Catatan</label>
                            <textarea id="notes" name="notes" rows="2" class="w-full rounded-md border-slate-300 text-sm"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                                Tandai Refund Paid
                            </button>
                        </div>
                    </form>
                @endif
            @endif
        </section>
    </div>
</x-admin-layout>
