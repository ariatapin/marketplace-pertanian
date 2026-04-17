<?php

namespace App\Services;

use App\Models\User;

class WithdrawPolicyService
{
    public function __construct(
        protected RoleAccessService $roleAccess
    ) {}

    /**
     * Allowed withdraw actors:
     * - role mitra
     * - role consumer with approved mode affiliate/farmer_seller
     */
    public function evaluate(?User $user): array
    {
        if (! $user) {
            return ['allowed' => false, 'message' => 'Unauthenticated.'];
        }

        if ($user->isAdmin()) {
            return ['allowed' => false, 'message' => 'Admin tidak diperbolehkan melakukan withdraw.'];
        }

        if ($user->isMitra()) {
            return ['allowed' => true, 'message' => 'allowed'];
        }

        if ($this->roleAccess->canAccessAffiliate($user) || $this->roleAccess->canAccessSeller($user)) {
            return ['allowed' => true, 'message' => 'allowed'];
        }

        return ['allowed' => false, 'message' => 'Withdraw hanya untuk affiliate/penjual yang sudah disetujui admin.'];
    }
}
