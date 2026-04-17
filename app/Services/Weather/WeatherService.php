<?php

namespace App\Services\Weather;

use App\Models\District;
use App\Models\User;
use App\Models\WeatherSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /** @var array<string, string|null> */
    private array $resolvedBmkgCodes = [];

    public function __construct(
        private readonly WeatherAggregatorService $aggregator
    ) {}

    public function current(string $locationType, int $locationId, float $lat, float $lng): array
    {
        $kodeWilayah = $this->resolveBmkgRegionCode($locationType, $locationId);

        return $this->getOrFetch(
            kind: 'current',
            locationType: $locationType,
            locationId: $locationId,
            lat: $lat,
            lng: $lng,
            ttlMinutes: (int) config('weather.cache.current_minutes', 60),
            fetcher: fn() => $this->aggregator->current($lat, $lng, $kodeWilayah)
        );
    }

    public function forecast(string $locationType, int $locationId, float $lat, float $lng): array
    {
        $kodeWilayah = $this->resolveBmkgRegionCode($locationType, $locationId);

        return $this->getOrFetch(
            kind: 'forecast',
            locationType: $locationType,
            locationId: $locationId,
            lat: $lat,
            lng: $lng,
            ttlMinutes: (int) config('weather.cache.forecast_minutes', 180),
            fetcher: fn() => $this->aggregator->forecast($lat, $lng, $kodeWilayah)
        );
    }

    private function getOrFetch(
        string $kind,
        string $locationType,
        int $locationId,
        float $lat,
        float $lng,
        int $ttlMinutes,
        \Closure $fetcher
    ): array {
        $now = CarbonImmutable::now();

        $cache = WeatherSnapshot::query()
            ->where('provider', 'openweather')
            ->where('kind', $kind)
            ->where('location_type', $locationType)
            ->where('location_id', $locationId)
            ->where('valid_until', '>', $now)
            ->latest('fetched_at')
            ->first();

        if ($cache) {
            return $cache->payload;
        }

        $payload = $fetcher();

        WeatherSnapshot::create([
            'provider' => 'openweather',
            'kind' => $kind,
            'location_type' => $locationType,
            'location_id' => $locationId,
            'lat' => $lat,
            'lng' => $lng,
            'payload' => $payload,
            'fetched_at' => $now,
            'valid_until' => $now->addMinutes($ttlMinutes),
        ]);

        return $payload;
    }

    /**
     * Resolve kode wilayah BMKG (adm4) berdasar konteks lokasi saat ini.
     */
    private function resolveBmkgRegionCode(string $locationType, int $locationId): ?string
    {
        $cacheKey = strtolower(trim($locationType)) . ':' . $locationId;
        if (array_key_exists($cacheKey, $this->resolvedBmkgCodes)) {
            return $this->resolvedBmkgCodes[$cacheKey];
        }

        if ($locationId <= 0) {
            $this->resolvedBmkgCodes[$cacheKey] = null;
            return null;
        }

        $normalizedType = strtolower(trim($locationType));

        if ($normalizedType === 'district') {
            $this->resolvedBmkgCodes[$cacheKey] = (string) $locationId;
            return $this->resolvedBmkgCodes[$cacheKey];
        }

        if ($normalizedType === 'city') {
            $districtId = District::query()
                ->where('city_id', $locationId)
                ->orderBy('id')
                ->value('id');

            $resolved = $districtId ? (string) $districtId : (string) $locationId;
            $this->resolvedBmkgCodes[$cacheKey] = $resolved;

            return $resolved;
        }

        if ($normalizedType === 'user') {
            $user = User::query()
                ->select('district_id', 'city_id')
                ->find($locationId);

            if (! $user) {
                $this->resolvedBmkgCodes[$cacheKey] = null;
                return null;
            }

            if (! empty($user->district_id)) {
                $resolved = (string) ((int) $user->district_id);
                $this->resolvedBmkgCodes[$cacheKey] = $resolved;

                return $resolved;
            }

            if (! empty($user->city_id)) {
                $cityId = (int) $user->city_id;
                $districtId = District::query()
                    ->where('city_id', $cityId)
                    ->orderBy('id')
                    ->value('id');

                $resolved = $districtId ? (string) $districtId : (string) $cityId;
                $this->resolvedBmkgCodes[$cacheKey] = $resolved;

                return $resolved;
            }
        }

        Log::debug('Kode wilayah BMKG tidak ditemukan untuk locationType tertentu.', [
            'location_type' => $locationType,
            'location_id' => $locationId,
        ]);

        $this->resolvedBmkgCodes[$cacheKey] = null;
        return null;
    }
}
