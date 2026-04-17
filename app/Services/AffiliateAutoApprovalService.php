<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AffiliateAutoApprovalService
{
    public function isEligible(User $user): bool
    {
        // valid kalau pernah buyer dan order completed minimal 1x
        return DB::table('orders')
            ->where('buyer_id', $user->id)
            ->where('order_status', 'completed')
            ->exists();
    }
}
