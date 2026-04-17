<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumerModeService
{
    public function __construct(
        protected AffiliateAutoApprovalService $autoApproval
    ) {}

    /**
     * Rule:
     * - hanya consumer role
     * - tidak boleh punya pending
     * - tidak boleh sudah approved di mode affiliate/farmer_seller lalu request mode lain
     */
    public function requestAffiliate(User $user): array
    {
        $this->ensureConsumerRole($user);

        return DB::transaction(function () use ($user) {
            $profile = $this->getOrCreateConsumerProfile($user);

            $this->ensureCanRequest($profile, 'affiliate');

            // Auto approve jika eligible
            if ($this->autoApproval->isEligible($user)) {
                DB::table('consumer_profiles')->where('user_id', $user->id)->update([
                    'mode' => 'affiliate',
                    'mode_status' => 'approved',
                    'requested_mode' => null,
                    'updated_at' => now(),
                ]);

                return ['status' => 'approved', 'mode' => 'affiliate'];
            }

            // else pending admin
            DB::table('consumer_profiles')->where('user_id', $user->id)->update([
                'requested_mode' => 'affiliate',
                'mode_status' => 'pending',
                'updated_at' => now(),
            ]);

            // (opsional) insert ke affiliate_applications sesuai tabel kamu jika ingin
            // tapi core switching cukup di consumer_profiles

            return ['status' => 'pending', 'mode' => 'buyer'];
        });
    }

    public function requestFarmerSeller(User $user): array
    {
        $this->ensureConsumerRole($user);

        return DB::transaction(function () use ($user) {
            $profile = $this->getOrCreateConsumerProfile($user);

            $this->ensureCanRequest($profile, 'farmer_seller');

            DB::table('consumer_profiles')->where('user_id', $user->id)->update([
                'requested_mode' => 'farmer_seller',
                'mode_status' => 'pending',
                'updated_at' => now(),
            ]);

            return ['status' => 'pending', 'mode' => 'buyer'];
        });
    }

    public function approveMode(User $admin, int $userId, string $mode): void
    {
        if (!$admin->isAdmin()) abort(403);

        if (!in_array($mode, ['affiliate', 'farmer_seller'], true)) {
            throw ValidationException::withMessages(['mode' => 'Mode tidak valid']);
        }

        DB::transaction(function () use ($userId, $mode) {
            $profile = DB::table('consumer_profiles')->where('user_id', $userId)->lockForUpdate()->first();
            if (!$profile) {
                throw ValidationException::withMessages(['user' => 'Consumer profile tidak ditemukan']);
            }

            if ($profile->mode_status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Tidak ada pengajuan pending']);
            }

            if ($profile->requested_mode !== $mode) {
                throw ValidationException::withMessages(['requested_mode' => 'Requested mode tidak cocok']);
            }

            DB::table('consumer_profiles')->where('user_id', $userId)->update([
                'mode' => $mode,
                'mode_status' => 'approved',
                'requested_mode' => null,
                'updated_at' => now(),
            ]);
        });
    }

    public function rejectMode(User $admin, int $userId): void
    {
        if (!$admin->isAdmin()) abort(403);

        DB::transaction(function () use ($userId) {
            $profile = DB::table('consumer_profiles')->where('user_id', $userId)->lockForUpdate()->first();
            if (!$profile) {
                throw ValidationException::withMessages(['user' => 'Consumer profile tidak ditemukan']);
            }

            if ($profile->mode_status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Tidak ada pengajuan pending']);
            }

            DB::table('consumer_profiles')->where('user_id', $userId)->update([
                'mode_status' => 'rejected',
                'requested_mode' => null,
                'updated_at' => now(),
            ]);
        });
    }

    private function ensureConsumerRole(User $user): void
    {
        // Mitra tidak boleh masuk flow mode consumer
        if (! $user->isConsumer()) {
            throw ValidationException::withMessages(['role' => 'Hanya consumer yang bisa request mode.']);
        }
    }

    private function getOrCreateConsumerProfile(User $user)
    {
        $profile = DB::table('consumer_profiles')->where('user_id', $user->id)->first();
        if ($profile) return $profile;

        DB::table('consumer_profiles')->insert([
            'user_id' => $user->id,
            'address' => null,
            'mode' => 'buyer',
            'mode_status' => 'none',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('consumer_profiles')->where('user_id', $user->id)->first();
    }

    private function ensureCanRequest($profile, string $targetMode): void
    {
        // pending => tidak boleh request apa pun
        if ($profile->mode_status === 'pending') {
            if (!empty($profile->requested_mode) && $profile->requested_mode !== $targetMode) {
                throw ValidationException::withMessages([
                    'mode_status' => "Pengajuan {$profile->requested_mode} masih pending. Mode lain tidak bisa diajukan.",
                ]);
            }

            throw ValidationException::withMessages(['mode_status' => 'Masih ada pengajuan pending.']);
        }

        // sudah approved jadi affiliate / farmer_seller => tidak boleh request mode lain
        if ($profile->mode_status === 'approved' && $profile->mode !== 'buyer') {
            if ($profile->mode === $targetMode) {
                throw ValidationException::withMessages(['mode' => "Mode {$targetMode} sudah aktif."]);
            }

            throw ValidationException::withMessages([
                'mode' => "Mode {$profile->mode} sudah aktif. Fitur {$targetMode} tidak bisa diaktifkan.",
            ]);
        }
    }
}
