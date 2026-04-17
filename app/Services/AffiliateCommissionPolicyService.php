<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AffiliateCommissionPolicyService
{
    private const DEFAULT_MIN_PERCENT = 0.0;
    private const DEFAULT_MAX_PERCENT = 100.0;

    public function resolveRange(): array
    {
        $range = [
            'min' => self::DEFAULT_MIN_PERCENT,
            'max' => self::DEFAULT_MAX_PERCENT,
        ];

        if (! $this->canReadRangeFromAdminProfile()) {
            return $this->normalizeRange($range['min'], $range['max']);
        }

        $admin = User::query()
            ->whereNormalizedRole('admin')
            ->orderBy('id')
            ->first(['id']);

        if (! $admin) {
            return $this->normalizeRange($range['min'], $range['max']);
        }

        $profile = DB::table('admin_profiles')
            ->where('user_id', (int) $admin->id)
            ->first([
                'affiliate_commission_min_percent',
                'affiliate_commission_max_percent',
            ]);

        $min = (float) ($profile?->affiliate_commission_min_percent ?? self::DEFAULT_MIN_PERCENT);
        $max = (float) ($profile?->affiliate_commission_max_percent ?? self::DEFAULT_MAX_PERCENT);

        return $this->normalizeRange($min, $max);
    }

    public function valueRules(?array $range = null): array
    {
        $resolvedRange = $range ?? $this->resolveRange();
        $min = $this->formatForValidation((float) ($resolvedRange['min'] ?? self::DEFAULT_MIN_PERCENT));
        $max = $this->formatForValidation((float) ($resolvedRange['max'] ?? self::DEFAULT_MAX_PERCENT));

        return [
            'numeric',
            'min:' . $min,
            'max:' . $max,
        ];
    }

    public function formatPercent(float $value): string
    {
        $normalized = max(0, min(100, $value));

        return rtrim(rtrim(number_format($normalized, 2, '.', ''), '0'), '.');
    }

    public function canReadRangeFromAdminProfile(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasTable('admin_profiles')
            && Schema::hasColumn('admin_profiles', 'affiliate_commission_min_percent')
            && Schema::hasColumn('admin_profiles', 'affiliate_commission_max_percent');
    }

    public function persistRange(int $adminUserId, float $minPercent, float $maxPercent): bool
    {
        if ($adminUserId <= 0 || ! $this->canReadRangeFromAdminProfile()) {
            return false;
        }

        $range = $this->normalizeRange($minPercent, $maxPercent);
        $exists = DB::table('admin_profiles')
            ->where('user_id', $adminUserId)
            ->exists();

        $payload = [
            'affiliate_commission_min_percent' => $range['min'],
            'affiliate_commission_max_percent' => $range['max'],
            'updated_at' => now(),
        ];

        if (! $exists) {
            $payload['created_at'] = now();
        }

        DB::table('admin_profiles')->updateOrInsert(
            ['user_id' => $adminUserId],
            $payload
        );

        return true;
    }

    private function normalizeRange(float $min, float $max): array
    {
        $safeMin = max(0, min(100, $min));
        $safeMax = max(0, min(100, $max));
        if ($safeMax < $safeMin) {
            $safeMax = $safeMin;
        }

        return [
            'min' => (float) number_format($safeMin, 2, '.', ''),
            'max' => (float) number_format($safeMax, 2, '.', ''),
        ];
    }

    private function formatForValidation(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
