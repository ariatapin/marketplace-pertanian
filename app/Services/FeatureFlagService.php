<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeatureFlagService
{
    public function get(string $key): ?object
    {
        if (! Schema::hasTable('feature_flags')) {
            return null;
        }

        return DB::table('feature_flags')
            ->where('key', $key)
            ->first();
    }

    public function isEnabled(string $key, bool $default = false): bool
    {
        $flag = $this->get($key);
        if (! $flag) {
            return $default;
        }

        return (bool) $flag->is_enabled;
    }

    public function description(string $key, ?string $default = null): ?string
    {
        $flag = $this->get($key);
        if (! $flag) {
            return $default;
        }

        $description = trim((string) ($flag->description ?? ''));

        return $description !== '' ? $description : $default;
    }
}
