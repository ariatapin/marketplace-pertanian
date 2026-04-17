<?php

namespace App\Services\Location;

use App\Models\City;
use App\Models\User;

class LocationResolver
{
    public function forUser(?User $user): array
    {
        if (! $user) {
            return $this->fallback();
        }

        if ($user->lat !== null && $user->lng !== null) {
            return [
                'type' => 'user',
                'id' => (int) $user->id,
                'lat' => (float) $user->lat,
                'lng' => (float) $user->lng,
                'label' => $this->resolveCityLabel($user->city_id) ?? 'Lokasi User',
                'source' => 'users.latlng',
            ];
        }

        if ($user->city_id) {
            $city = City::select('id', 'name', 'type', 'lat', 'lng')->find((int) $user->city_id);
            if ($city && $city->lat !== null && $city->lng !== null) {
                return [
                    'type' => 'city',
                    'id' => (int) $city->id,
                    'lat' => (float) $city->lat,
                    'lng' => (float) $city->lng,
                    'label' => trim(($city->type ? $city->type . ' ' : '') . $city->name),
                    'source' => 'cities.latlng',
                ];
            }
        }

        return $this->fallback();
    }

    public function fallback(): array
    {
        $firstCityWithCoordinate = City::query()
            ->select('id', 'name', 'type', 'lat', 'lng')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderBy('id')
            ->first();

        if ($firstCityWithCoordinate) {
            return [
                'type' => 'city',
                'id' => (int) $firstCityWithCoordinate->id,
                'lat' => (float) $firstCityWithCoordinate->lat,
                'lng' => (float) $firstCityWithCoordinate->lng,
                'label' => trim(($firstCityWithCoordinate->type ? $firstCityWithCoordinate->type . ' ' : '') . $firstCityWithCoordinate->name),
                'source' => 'cities.first_available',
            ];
        }

        return [
            'type' => 'custom',
            'id' => 0,
            'lat' => (float) config('weather.fallback.lat', 0),
            'lng' => (float) config('weather.fallback.lng', 0),
            'label' => (string) config('weather.fallback.label', 'Lokasi Default'),
            'source' => 'config.weather.fallback',
        ];
    }

    private function resolveCityLabel(?int $cityId): ?string
    {
        if (! $cityId) {
            return null;
        }

        $city = City::select('name', 'type')->find($cityId);
        if (! $city) {
            return null;
        }

        return trim(($city->type ? $city->type . ' ' : '') . $city->name);
    }
}
