<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleAccessService
{
    public function canAccessAffiliate(User $user): bool
    {
        $role = strtolower(trim((string) $user->role));

        if ($role !== 'consumer') {
            return false;
        }

        return $this->hasApprovedConsumerMode($user->id, 'affiliate');
    }

    public function canAccessSeller(User $user): bool
    {
        $role = strtolower(trim((string) $user->role));

        if (in_array($role, ['seller', 'farmer_seller'], true)) {
            return true;
        }

        if ($role !== 'consumer') {
            return false;
        }

        return $this->hasApprovedConsumerMode($user->id, 'farmer_seller');
    }

    private function hasApprovedConsumerMode(int $userId, string $mode): bool
    {
        if (! Schema::hasTable('consumer_profiles')) {
            return false;
        }

        $profile = DB::table('consumer_profiles')
            ->where('user_id', $userId)
            ->first(['mode', 'mode_status']);

        if (! $profile) {
            return false;
        }

        return (string) $profile->mode === $mode
            && (string) $profile->mode_status === 'approved';
    }
}
