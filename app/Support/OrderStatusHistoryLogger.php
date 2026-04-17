<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderStatusHistoryLogger
{
    public function log(
        int $orderId,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        ?string $note = null,
        array $meta = []
    ): void {
        if (! Schema::hasTable('order_status_histories')) {
            return;
        }

        DB::table('order_status_histories')->insert([
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'note' => $note,
            'meta' => empty($meta) ? null : json_encode($meta),
            'created_at' => now(),
        ]);
    }
}
