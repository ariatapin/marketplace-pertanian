<x-admin-layout>
    <x-slot name="header">
        {{ __('Modul Users') }}
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-7 gap-3">
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Total User</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($summary['total_users'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Admin</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($summary['total_admin'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Mitra</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($summary['total_mitra'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Consumer</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($summary['total_consumer'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Mode Pending</p>
                <p class="mt-1 text-xl font-bold text-amber-700">{{ number_format($summary['pending_mode'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Akun Suspend</p>
                <p class="mt-1 text-xl font-bold text-rose-700">{{ number_format($summary['suspended_users'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <p class="text-xs text-slate-500">Akun Blokir</p>
                <p class="mt-1 text-xl font-bold text-rose-800">{{ number_format($summary['blocked_users'] ?? 0) }}</p>
            </div>
        </div>

        <div class="rounded-xl border bg-white p-6">
            <form method="GET" action="{{ route('admin.modules.users') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <div>
                    <label for="user_id" class="mb-1 block text-sm font-medium text-slate-700">Cari ID User</label>
                    <input id="user_id" name="user_id" type="text" value="{{ $filters['user_id'] ?? '' }}" placeholder="contoh: 15" class="w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Cari Email</label>
                    <input id="email" name="email" type="text" value="{{ $filters['email'] ?? '' }}" placeholder="nama@email.com" class="w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label for="role" class="mb-1 block text-sm font-medium text-slate-700">Role</label>
                    <select id="role" name="role" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Semua</option>
                        <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
                        <option value="mitra" @selected(($filters['role'] ?? '') === 'mitra')>Mitra</option>
                        <option value="consumer" @selected(($filters['role'] ?? '') === 'consumer')>Consumer</option>
                    </select>
                </div>
                <div>
                    <label for="mode_status" class="mb-1 block text-sm font-medium text-slate-700">Status Mode Consumer</label>
                    <select id="mode_status" name="mode_status" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Semua</option>
                        <option value="none" @selected(($filters['mode_status'] ?? '') === 'none')>None</option>
                        <option value="pending" @selected(($filters['mode_status'] ?? '') === 'pending')>Pending</option>
                        <option value="approved" @selected(($filters['mode_status'] ?? '') === 'approved')>Approved</option>
                        <option value="rejected" @selected(($filters['mode_status'] ?? '') === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div>
                    <label for="suspension" class="mb-1 block text-sm font-medium text-slate-700">Status Akun</label>
                    <select id="suspension" name="suspension" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="">Semua</option>
                        <option value="active" @selected(($filters['suspension'] ?? '') === 'active')>Aktif</option>
                        <option value="suspended" @selected(($filters['suspension'] ?? '') === 'suspended')>Suspend</option>
                        <option value="blocked" @selected(($filters['suspension'] ?? '') === 'blocked')>Blokir</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Cari User
                    </button>
                    <a href="{{ route('admin.modules.users') }}" class="rounded-md border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bg-white p-6">
            @if($rows->isEmpty())
                <p class="text-sm text-slate-600">Belum ada data user.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-slate-600">
                                <th class="py-2 pr-4">ID</th>
                                <th class="py-2 pr-4">Nama</th>
                                <th class="py-2 pr-4">Email</th>
                                <th class="py-2 pr-4">Role</th>
                                <th class="py-2 pr-4">No. HP</th>
                                <th class="py-2 pr-4">Mode</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Requested Mode</th>
                                <th class="py-2 pr-4">Status Akun</th>
                                <th class="py-2 pr-4">Terdaftar</th>
                                <th class="py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @php($isCurrentAdmin = (int) $row->id === (int) auth()->id())
                                <tr class="border-b last:border-0">
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->id }}</td>
                                    <td class="py-3 pr-4 font-medium text-slate-900">{{ $row->name }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->email }}</td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $row->role }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ $row->phone_label }}</td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $row->mode_label }}</td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex rounded px-2 py-1 text-xs font-semibold uppercase {{ $row->mode_status_color }}">
                                            {{ $row->mode_status_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 uppercase text-slate-700">{{ $row->requested_mode_label }}</td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex rounded px-2 py-1 text-xs font-semibold {{ $row->suspension_badge_class }}">
                                            {{ $row->suspension_badge_label }}
                                        </span>
                                        @if($row->is_suspended)
                                            <p class="mt-1 text-xs text-rose-700">Sejak {{ $row->suspended_at_label }}</p>
                                            <p class="text-xs text-slate-500">{{ $row->suspension_note_label }}</p>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 text-slate-600">{{ $row->created_at_label }}</td>
                                    <td class="py-3">
                                        @if($isCurrentAdmin)
                                            <span class="inline-flex rounded bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                                Akun Anda
                                            </span>
                                        @elseif($row->is_suspended)
                                            <form method="POST" action="{{ route('admin.modules.users.activate', ['userId' => $row->id]) }}">
                                                @csrf
                                                <button type="submit" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                    Aktifkan
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.modules.users.suspend', ['userId' => $row->id]) }}" class="space-y-1">
                                                @csrf
                                                <input type="text" name="note" maxlength="255" placeholder="Alasan suspend (opsional)" class="w-44 rounded border-slate-300 px-2 py-1 text-xs">
                                                <div class="flex flex-wrap gap-1">
                                                    <button type="submit" class="rounded bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                                        Suspend
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        formaction="{{ route('admin.modules.users.block', ['userId' => $row->id]) }}"
                                                        formmethod="POST"
                                                        class="rounded bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-800"
                                                    >
                                                        Blokir
                                                    </button>
                                                </div>
                                            </form>
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
    </div>
</x-admin-layout>
