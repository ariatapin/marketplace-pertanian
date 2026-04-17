<?php

namespace App\Policies;

use App\Models\StoreProduct;
use App\Models\User;

class StoreProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMitra();
    }

    public function view(User $user, StoreProduct $storeProduct): bool
    {
        return $user->isMitra() && (int) $storeProduct->mitra_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isMitra();
    }

    public function update(User $user, StoreProduct $storeProduct): bool
    {
        return $user->isMitra() && (int) $storeProduct->mitra_id === (int) $user->id;
    }

    public function delete(User $user, StoreProduct $storeProduct): bool
    {
        return $user->isMitra() && (int) $storeProduct->mitra_id === (int) $user->id;
    }
}
