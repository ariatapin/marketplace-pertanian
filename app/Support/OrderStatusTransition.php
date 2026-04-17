<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class OrderStatusTransition
{
    private const ALLOWED = [
        'pending_payment' => ['paid'],
        'paid' => ['packed'],
        'packed' => ['shipped'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    private const TARGET_MESSAGES = [
        'paid' => 'Order harus pending_payment sebelum paid.',
        'packed' => 'Order harus paid sebelum packed.',
        'shipped' => 'Order harus packed sebelum shipped.',
        'completed' => 'Order harus shipped sebelum completed.',
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

    public function assertTransition(string $from, string $to): void
    {
        if ($this->canTransition($from, $to)) {
            return;
        }

        throw ValidationException::withMessages([
            'order_status' => self::TARGET_MESSAGES[$to] ?? 'Transisi status order tidak valid.',
        ]);
    }
}
