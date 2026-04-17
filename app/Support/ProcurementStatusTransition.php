<?php

namespace App\Support;

class ProcurementStatusTransition
{
    private const ALLOWED = [
        'pending' => ['pending', 'approved', 'processing', 'cancelled'],
        'approved' => ['approved', 'processing', 'cancelled'],
        'processing' => ['processing', 'shipped', 'cancelled'],
        'shipped' => ['shipped', 'delivered'],
        'delivered' => ['delivered'],
        'cancelled' => ['cancelled'],
    ];

    public function canTransition(string $from, string $to): bool
    {
        $allowedTargets = self::ALLOWED[$from] ?? [];

        return in_array($to, $allowedTargets, true);
    }

    public function allowedTargets(string $from): array
    {
        return self::ALLOWED[$from] ?? [];
    }
}
