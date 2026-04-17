<?php

namespace App\Support;

use App\Models\User;
use App\Services\RoleAccessService;

class RoleRedirector
{
    public function __construct(
        protected RoleAccessService $roleAccess
    ) {}

    public function pathFor(?User $user): string
    {
        if (! $user) {
            return '/dashboard';
        }

        $role = strtolower(trim((string) $user->role));

        if ($role === 'admin') {
            return '/admin/dashboard';
        }

        if ($role === 'mitra') {
            return '/mitra/dashboard';
        }

        if ($this->roleAccess->canAccessAffiliate($user)) {
            return '/affiliate/dashboard';
        }

        if ($this->roleAccess->canAccessSeller($user)) {
            return '/seller/dashboard';
        }

        return '/dashboard';
    }

    public function postLoginPathFor(?User $user): string
    {
        $target = $this->pathFor($user);

        if (in_array($target, ['/affiliate/dashboard', '/seller/dashboard'], true)) {
            return '/';
        }

        return $target;
    }
}
