<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminUsersViewModelFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsersPageController extends Controller
{
    public function __construct(
        protected AdminUsersViewModelFactory $usersViewModelFactory
    ) {}

    public function __invoke(Request $request)
    {
        $userId = trim($request->string('user_id')->toString());
        $email = trim($request->string('email')->toString());
        $role = $request->string('role')->toString();
        $modeStatus = $request->string('mode_status')->toString();
        $suspension = $request->string('suspension')->toString();

        $rows = collect();
        $summary = [
            'total_users' => 0,
            'total_admin' => 0,
            'total_mitra' => 0,
            'total_consumer' => 0,
            'pending_mode' => 0,
            'suspended_users' => 0,
            'blocked_users' => 0,
        ];

        if (Schema::hasTable('users')) {
            $hasSuspensionColumns = Schema::hasColumn('users', 'is_suspended')
                && Schema::hasColumn('users', 'suspended_at')
                && Schema::hasColumn('users', 'suspension_note');

            $summary['total_users'] = DB::table('users')->count();
            $summary['total_admin'] = User::query()->whereNormalizedRole('admin')->count();
            $summary['total_mitra'] = User::query()->whereNormalizedRole('mitra')->count();
            $summary['total_consumer'] = User::query()->whereNormalizedRole('consumer')->count();
            if ($hasSuspensionColumns) {
                $summary['blocked_users'] = DB::table('users')
                    ->where('is_suspended', true)
                    ->where('suspension_note', 'like', '[BLOCKED]%')
                    ->count();
                $summary['suspended_users'] = DB::table('users')
                    ->where('is_suspended', true)
                    ->where(function ($sub) {
                        $sub->whereNull('suspension_note')
                            ->orWhere('suspension_note', 'not like', '[BLOCKED]%');
                    })
                    ->count();
            }

            if (Schema::hasTable('consumer_profiles')) {
                $summary['pending_mode'] = DB::table('consumer_profiles')
                    ->where('mode_status', 'pending')
                    ->count();
            }

            $query = DB::table('users')
                ->leftJoin('consumer_profiles', 'consumer_profiles.user_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.role',
                    'users.phone_number',
                    'consumer_profiles.mode',
                    'consumer_profiles.mode_status',
                    'consumer_profiles.requested_mode',
                    'users.created_at'
                )
                ->orderBy('users.id');

            if ($hasSuspensionColumns) {
                $query->addSelect(
                    'users.is_suspended',
                    'users.suspended_at',
                    'users.suspension_note'
                );
            }

            if (in_array($role, ['admin', 'mitra', 'consumer'], true)) {
                $query->whereRaw('LOWER(TRIM(users.role)) = ?', [User::normalizeRoleValue($role)]);
            }

            if (in_array($modeStatus, ['none', 'pending', 'approved', 'rejected'], true)) {
                $query->where('consumer_profiles.mode_status', $modeStatus);
            }

            if ($userId !== '' && ctype_digit($userId)) {
                $query->where('users.id', (int) $userId);
            }

            if ($email !== '') {
                $query->where('users.email', 'like', "%{$email}%");
            }

            if ($suspension === 'suspended' && $hasSuspensionColumns) {
                $query->where('users.is_suspended', true)
                    ->where(function ($sub) {
                        $sub->whereNull('users.suspension_note')
                            ->orWhere('users.suspension_note', 'not like', '[BLOCKED]%');
                    });
            }

            if ($suspension === 'blocked' && $hasSuspensionColumns) {
                $query->where('users.is_suspended', true)
                    ->where('users.suspension_note', 'like', '[BLOCKED]%');
            }

            if ($suspension === 'active' && $hasSuspensionColumns) {
                $query->where('users.is_suspended', false);
            }

            $rows = $query->paginate(20)->withQueryString();
        }

        [
            'summary' => $summary,
            'rows' => $rows,
        ] = $this->usersViewModelFactory->make(
            summary: $summary,
            rows: $rows
        );

        return view('admin.users', [
            'rows' => $rows,
            'filters' => [
                'user_id' => $userId,
                'email' => $email,
                'role' => $role,
                'mode_status' => $modeStatus,
                'suspension' => $suspension,
            ],
            'summary' => $summary,
        ]);
    }

    public function suspend(Request $request, int $userId)
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_suspended')) {
            return back()->withErrors(['users' => 'Fitur suspend belum tersedia. Jalankan migration terbaru.']);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        $target = DB::table('users')
            ->where('id', $userId)
            ->first(['id', 'name', 'role', 'is_suspended']);

        if (! $target) {
            return back()->withErrors(['users' => 'User tidak ditemukan.']);
        }

        if ((int) $target->id === (int) $actor->id) {
            return back()->withErrors(['users' => 'Admin tidak dapat mensuspend akun sendiri.']);
        }

        $note = trim((string) ($data['note'] ?? ''));
        $suspensionNote = $note !== '' ? "[SUSPEND] {$note}" : '[SUSPEND] Suspend oleh admin.';

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'is_suspended' => true,
                'suspended_at' => now(),
                'suspension_note' => $suspensionNote,
                'updated_at' => now(),
            ]);

        return back()->with('status', "User {$target->name} berhasil disuspend.");
    }

    public function block(Request $request, int $userId)
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_suspended')) {
            return back()->withErrors(['users' => 'Fitur blokir belum tersedia. Jalankan migration terbaru.']);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        $target = DB::table('users')
            ->where('id', $userId)
            ->first(['id', 'name']);

        if (! $target) {
            return back()->withErrors(['users' => 'User tidak ditemukan.']);
        }

        if ((int) $target->id === (int) $actor->id) {
            return back()->withErrors(['users' => 'Admin tidak dapat memblokir akun sendiri.']);
        }

        $note = trim((string) ($data['note'] ?? ''));
        $blockNote = $note !== '' ? "[BLOCKED] {$note}" : '[BLOCKED] Diblokir oleh admin.';

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'is_suspended' => true,
                'suspended_at' => now(),
                'suspension_note' => $blockNote,
                'updated_at' => now(),
            ]);

        return back()->with('status', "User {$target->name} berhasil diblokir.");
    }

    public function activate(Request $request, int $userId)
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_suspended')) {
            return back()->withErrors(['users' => 'Fitur suspend belum tersedia. Jalankan migration terbaru.']);
        }

        $target = DB::table('users')
            ->where('id', $userId)
            ->first(['id', 'name', 'is_suspended']);

        if (! $target) {
            return back()->withErrors(['users' => 'User tidak ditemukan.']);
        }

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'is_suspended' => false,
                'suspended_at' => null,
                'suspension_note' => null,
                'updated_at' => now(),
            ]);

        return back()->with('status', "Akun {$target->name} diaktifkan kembali.");
    }
}
