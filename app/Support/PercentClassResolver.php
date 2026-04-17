<?php

namespace App\Support;

final class PercentClassResolver
{
    public static function prefixed(
        string $prefix,
        float|int $value,
        int $step = 5,
        int $min = 0,
        int $max = 100
    ): string {
        return $prefix . self::bucket($value, $step, $min, $max);
    }

    public static function bucket(
        float|int $value,
        int $step = 5,
        int $min = 0,
        int $max = 100
    ): int {
        $safeStep = max(1, $step);
        $safeMin = min($min, $max);
        $safeMax = max($min, $max);
        $normalized = max($safeMin, min($safeMax, (float) $value));
        $bucket = (int) round($normalized / $safeStep) * $safeStep;

        return max($safeMin, min($safeMax, $bucket));
    }
}
