<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProcurementOrderStatusLogger
{
    public function log(
        int $orderId,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $note = null
    ): void {
        if (! Schema::hasTable('admin_order_status_histories')) {
            return;
        }

        DB::table('admin_order_status_histories')->insert([
            'admin_order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
