<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AffiliateLockPolicyService
{
    public const FLAG_KEY = 'affiliate_lock_policy';

    /**
     * @return array{
     *   cooldown_enabled: bool,
     *   lock_days: int,
     *   refresh_on_repromote: bool
     * }
     */
    public function resolve(): array
    {
        $defaults = [
            'cooldown_enabled' => true,
            'lock_days' => 30,
            'refresh_on_repromote' => false,
        ];

        if (! Schema::hasTable('feature_flags')) {
            return $defaults;
        }

        $flag = DB::table('feature_flags')
            ->where('key', self::FLAG_KEY)
            ->first(['is_enabled', 'description']);

        if (! $flag) {
            return $defaults;
        }

        $decoded = null;
        $rawDescription = trim((string) ($flag->description ?? ''));
        if ($rawDescription !== '') {
            $parsed = json_decode($rawDescription, true);
            if (is_array($parsed)) {
                $decoded = $parsed;
            }
        }

        $lockDays = (int) ($decoded['lock_days'] ?? $defaults['lock_days']);
        $lockDays = max(1, min(365, $lockDays));

        return [
            'cooldown_enabled' => (bool) ($flag->is_enabled ?? $defaults['cooldown_enabled']),
            'lock_days' => $lockDays,
            'refresh_on_repromote' => (bool) ($decoded['refresh_on_repromote'] ?? $defaults['refresh_on_repromote']),
        ];
    }

    public function save(bool $cooldownEnabled, int $lockDays, bool $refreshOnRepromote): void
    {
        if (! Schema::hasTable('feature_flags')) {
            return;
        }

        $safeLockDays = max(1, min(365, $lockDays));
        $description = json_encode([
            'lock_days' => $safeLockDays,
            'refresh_on_repromote' => $refreshOnRepromote,
        ], JSON_UNESCAPED_UNICODE);

        DB::table('feature_flags')->updateOrInsert(
            ['key' => self::FLAG_KEY],
            [
                'is_enabled' => $cooldownEnabled,
                'description' => $description ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

