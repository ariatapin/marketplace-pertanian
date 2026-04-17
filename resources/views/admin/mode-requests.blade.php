<x-admin-layout>
    <x-slot name="header">
        {{ __('Manajemen Pengajuan Consumer & Mitra') }}
    </x-slot>

    <div data-testid="admin-mode-requests-page" class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white border rounded-lg p-6">
                <form method="GET" action="{{ route('admin.modeRequests.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Semua</option>
                            <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                            <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved</option>
                            <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
                            <option value="none" @selected(($filters['status'] ?? '') === 'none')>None</option>
                        </select>
                    </div>

                    <div>
                        <label for="mode" class="mb-1 block text-sm font-medium text-gray-700">Mode Diminta</label>
                        <select id="mode" name="mode" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Semua</option>
                            <option value="affiliate" @selected(($filters['mode'] ?? '') === 'affiliate')>Affiliate</option>
                            <option value="farmer_seller" @selected(($filters['mode'] ?? '') === 'farmer_seller')>Farmer Seller</option>
                        </select>
                    </div>

                    <div>
                        <label for="q" class="mb-1 block text-sm font-medium text-gray-700">Cari User</label>
                        <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}" placeholder="nama / email" class="w-full rounded-md border-gray-300 text-sm">
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Filter
                        </button>
                        <a href="{{ route('admin.modeRequests.index') }}" class="rounded-md border px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div data-testid="admin-mode-requests-mitra-section" class="mt-6 bg-white border rounded-lg p-6">
                @if(session('status'))
                    <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if($rows->isEmpty())
                    <p class="text-sm text-gray-600">Data pengajuan belum tersedia.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-600">
                                    <th class="py-2 pr-4">User</th>
                                    <th class="py-2 pr-4">Email</th>
                                    <th class="py-2 pr-4">Mode Saat Ini</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Mode Diminta</th>
                                    <th class="py-2 pr-4">Update</th>
                                    <th class="py-2">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4 font-medium text-gray-900">{{ $row->name }}</td>
                                        <td class="py-3 pr-4 text-gray-700">{{ $row->email }}</td>
                                        <td class="py-3 pr-4 uppercase text-gray-700">{{ $row->mode }}</td>
                                        <td class="py-3 pr-4">
                                            <span class="inline-flex rounded px-2 py-1 text-xs font-semibold uppercase {{ $row->status_color }}">
                                                {{ $row->mode_status }}
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4 uppercase text-gray-700">{{ $row->requested_mode ?? '-' }}</td>
                                        <td class="py-3 pr-4 text-gray-600">{{ $row->updated_at_label }}</td>
                                        <td class="py-3">
                                            @if($row->can_review)
                                                <div class="flex items-center gap-2">
                                                    <form method="POST" action="{{ route('admin.mode.approve', ['userId' => $row->user_id]) }}">
                                                        @csrf
                                                        <input type="hidden" name="mode" value="{{ $row->requested_mode }}">
                                                        <button type="submit" class="rounded bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.mode.reject', ['userId' => $row->user_id]) }}">
                                                        @csrf
                                                        <button type="submit" class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-500">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $rows->links() }}
                    </div>
                @endif
            </div>

            <div class="mt-6 bg-white border rounded-lg p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Pengajuan Mitra (Buka Toko)</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Review kelengkapan dokumen, kapasitas gudang, dan detail usaha sebelum approve role mitra.
                        </p>
                    </div>
                </div>

                <form method="GET" action="{{ route('admin.modeRequests.index') }}" class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label for="mitra_status" class="mb-1 block text-sm font-medium text-gray-700">Status Pengajuan Mitra</label>
                        <select id="mitra_status" name="mitra_status" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Semua</option>
                            <option value="draft" @selected(($filters['mitra_status'] ?? '') === 'draft')>Draft</option>
                            <option value="pending" @selected(($filters['mitra_status'] ?? '') === 'pending')>Pending</option>
                            <option value="approved" @selected(($filters['mitra_status'] ?? '') === 'approved')>Approved</option>
                            <option value="rejected" @selected(($filters['mitra_status'] ?? '') === 'rejected')>Rejected</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="mitra_q" class="mb-1 block text-sm font-medium text-gray-700">Cari User / Nama Pengajuan</label>
                        <input id="mitra_q" name="mitra_q" type="text" value="{{ $filters['mitra_q'] ?? '' }}" placeholder="nama / email / nama pengajuan" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Filter
                        </button>
                        <a href="{{ route('admin.modeRequests.index') }}" class="rounded-md border px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Reset
                        </a>
                    </div>
                </form>

                @if($mitraRows->isEmpty())
                    <p class="mt-4 text-sm text-gray-600">Belum ada data pengajuan mitra.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-600">
                                    <th class="py-2 pr-4">User</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Data Usaha</th>
                                    <th class="py-2 pr-4">Dokumen</th>
                                    <th class="py-2 pr-4">Update</th>
                                    <th class="py-2">Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mitraRows as $row)
                                    <tr class="border-b last:border-0 align-top">
                                        <td class="py-3 pr-4">
                                            <p class="font-semibold text-gray-900">{{ $row->user_name_label }}</p>
                                            <p class="text-xs text-gray-600">{{ $row->user_email_label }}</p>
                                            <p class="mt-1 text-xs text-gray-500">Role saat ini: {{ $row->user_role_label }}</p>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <span class="inline-flex rounded px-2 py-1 text-xs font-semibold uppercase {{ $row->status_color }}">
                                                {{ $row->status }}
                                            </span>
                                            @if($row->notes)
                                                <p class="mt-1 text-xs text-gray-600">Catatan: {{ $row->notes }}</p>
                                            @endif
                                        </td>
                                        <td class="py-3 pr-4 text-xs text-gray-700">
                                            <p><span class="font-semibold">Nama Pengajuan:</span> {{ $row->full_name }}</p>
                                            <p><span class="font-semibold">Email:</span> {{ $row->email }}</p>
                                            <p><span class="font-semibold">Alamat Gudang:</span> {{ $row->warehouse_address !== '' ? $row->warehouse_address : '-' }}</p>
                                            <p><span class="font-semibold">Kapasitas:</span> {{ $row->warehouse_capacity_label }}</p>
                                            <p><span class="font-semibold">Produk:</span> {{ $row->products_managed !== '' ? $row->products_managed : '-' }}</p>
                                        </td>
                                        <td class="py-3 pr-4 text-xs">
                                            <div class="space-y-1">
                                                @foreach($row->docs as $doc)
                                                    <div>
                                                        <span class="text-gray-500">{{ $doc['label'] }}:</span>
                                                        @if($doc['exists'])
                                                            <a href="{{ $doc['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-700 hover:text-indigo-900">Lihat</a>
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="py-3 pr-4 text-xs text-gray-600">
                                            <p>Updated: {{ $row->updated_at_label }}</p>
                                            @if($row->has_submitted_at)
                                                <p>Submitted: {{ $row->submitted_at_label }}</p>
                                            @endif
                                            @if($row->has_decided_at)
                                                <p>Decided: {{ $row->decided_at_label }}</p>
                                            @endif
                                            @if($row->decided_by_name !== '')
                                                <p>By: {{ $row->decided_by_name }}</p>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            @if($row->can_review)
                                                <form method="POST" action="{{ route('admin.mitraApplications.review', ['applicationId' => $row->id]) }}" class="space-y-2">
                                                    @csrf
                                                    <select name="decision" class="w-full rounded border-gray-300 text-xs">
                                                        <option value="approved">Approve</option>
                                                        <option value="rejected">Reject</option>
                                                    </select>
                                                    <textarea name="notes" rows="2" class="w-full rounded border-gray-300 text-xs" placeholder="Catatan review (opsional)"></textarea>
                                                    <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                                        Simpan Review
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-500">Tidak ada aksi</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $mitraRows->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
