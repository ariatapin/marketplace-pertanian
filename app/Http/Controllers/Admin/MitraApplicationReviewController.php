<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MitraApplicationStatusNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MitraApplicationReviewController extends Controller
{
    public function review(Request $request, int $applicationId): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'notes' => ['nullable', 'string', 'max:800'],
        ]);

        $reviewedUserId = null;
        $decision = (string) $data['decision'];
        $notes = trim((string) ($data['notes'] ?? '')) ?: null;

        if (! Schema::hasTable('mitra_applications')) {
            return back()->withErrors([
                'mitra_review' => 'Tabel mitra_applications belum tersedia.',
            ]);
        }

        DB::transaction(function () use ($request, $applicationId, $decision, $notes, &$reviewedUserId) {
            $application = DB::table('mitra_applications')
                ->where('id', $applicationId)
                ->lockForUpdate()
                ->first();

            if (! $application) {
                throw ValidationException::withMessages([
                    'mitra_review' => 'Data pengajuan mitra tidak ditemukan.',
                ]);
            }

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages([
                    'mitra_review' => 'Hanya pengajuan berstatus pending yang dapat direview.',
                ]);
            }

            $reviewedUserId = (int) $application->user_id;

            DB::table('mitra_applications')
                ->where('id', $applicationId)
                ->update([
                    'status' => $decision,
                    'notes' => $notes,
                    'decided_by' => $request->user()?->id,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($decision === 'approved') {
                DB::table('users')
                    ->where('id', $application->user_id)
                    ->update([
                        'role' => 'mitra',
                        'updated_at' => now(),
                    ]);

                if (Schema::hasTable('consumer_profiles')) {
                    DB::table('consumer_profiles')
                        ->where('user_id', $application->user_id)
                        ->update([
                            'mode' => 'buyer',
                            'mode_status' => 'none',
                            'requested_mode' => null,
                            'updated_at' => now(),
                        ]);
                }

                if (Schema::hasTable('mitra_profiles')) {
                    $exists = DB::table('mitra_profiles')
                        ->where('user_id', $application->user_id)
                        ->exists();

                    $profilePayload = [
                        'store_name' => (string) ($application->full_name ?: 'Toko Mitra'),
                        'store_address' => (string) ($application->warehouse_address ?: '-'),
                        'region_id' => $application->region_id,
                        'is_active' => true,
                        'wallet_balance' => 0,
                        'updated_at' => now(),
                    ];

                    if ($exists) {
                        DB::table('mitra_profiles')
                            ->where('user_id', $application->user_id)
                            ->update($profilePayload);
                    } else {
                        DB::table('mitra_profiles')
                            ->insert($profilePayload + [
                                'user_id' => $application->user_id,
                                'created_at' => now(),
                            ]);
                    }
                }

            }
        });

        if ($reviewedUserId && Schema::hasTable('notifications')) {
            $reviewedUser = User::query()->find($reviewedUserId);
            if ($reviewedUser) {
                $title = $decision === 'approved'
                    ? 'Pengajuan Mitra Disetujui'
                    : 'Pengajuan Mitra Ditolak';
                $message = $decision === 'approved'
                    ? 'Selamat, pengajuan mitra Anda disetujui. Akun Anda sudah aktif sebagai mitra.'
                    : 'Pengajuan mitra Anda ditolak. Silakan cek catatan admin lalu ajukan ulang setelah perbaikan.';
                $actionUrl = $decision === 'approved'
                    ? route('mitra.dashboard')
                    : route('program.mitra.form') . '#mitra-application';
                $actionLabel = $decision === 'approved'
                    ? 'Buka Dashboard Mitra'
                    : 'Perbaiki Pengajuan';

                $reviewedUser->notify(new MitraApplicationStatusNotification(
                    status: $decision,
                    title: $title,
                    message: $message,
                    actionUrl: $actionUrl,
                    actionLabel: $actionLabel,
                    notes: $notes
                ));
            }
        }

        return back()->with('status', 'Review pengajuan mitra berhasil disimpan.');
    }
}
