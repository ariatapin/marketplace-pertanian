<?php

namespace App\Services\Weather;

class WeatherAlertEngine
{
    /**
     * Tentukan severity dari forecast OpenWeather (5 day / 3 hour).
     * Kita pakai rules sederhana (bisa kamu upgrade nanti).
     */
    public function evaluateForecast(array $forecast): array
    {
        $list = $forecast['list'] ?? [];
        if (!is_array($list) || count($list) === 0) {
            return [
                'severity' => 'green',
                'type' => 'no_data',
                'message' => 'Data cuaca belum tersedia.',
                'valid_until' => null,
            ];
        }

        // Ambil window 24 jam ke depan (sekitar 8 slot 3-jam)
        $window = array_slice($list, 0, 8);

        $maxWind = 0.0;
        $maxRain3h = 0.0;
        $maxTemp = -999.0;
        $minTemp = 999.0;
        $maxPop = 0.0;

        foreach ($window as $item) {
            $wind = (float) data_get($item, 'wind.speed', 0);
            $maxWind = max($maxWind, $wind);

            // rain 3h bisa null
            $rain3h = (float) data_get($item, 'rain.3h', 0);
            $maxRain3h = max($maxRain3h, $rain3h);

            $temp = (float) data_get($item, 'main.temp', 0);
            $maxTemp = max($maxTemp, $temp);
            $minTemp = min($minTemp, $temp);

            $pop = (float) data_get($item, 'pop', 0); // probability of precipitation 0..1
            $maxPop = max($maxPop, $pop);
        }

        // RULES (MVP):
        // RED: hujan deras / angin kencang / panas ekstrim
        if ($maxRain3h >= 10 || $maxPop >= 0.8) {
            return [
                'severity' => 'red',
                'type' => 'heavy_rain',
                'message' => 'Waspada hujan lebat dalam 24 jam ke depan.',
                'valid_until' => data_get($window[7] ?? [], 'dt_txt'),
            ];
        }

        if ($maxWind >= 12) { // ~> 43 km/jam
            return [
                'severity' => 'red',
                'type' => 'strong_wind',
                'message' => 'Waspada angin kencang dalam 24 jam ke depan.',
                'valid_until' => data_get($window[7] ?? [], 'dt_txt'),
            ];
        }

        if ($maxTemp >= 35) {
            return [
                'severity' => 'red',
                'type' => 'heat',
                'message' => 'Waspada suhu tinggi dalam 24 jam ke depan.',
                'valid_until' => data_get($window[7] ?? [], 'dt_txt'),
            ];
        }

        // YELLOW: potensi hujan sedang atau angin sedang
        if ($maxRain3h >= 3 || $maxPop >= 0.5) {
            return [
                'severity' => 'yellow',
                'type' => 'rain',
                'message' => 'Potensi hujan dalam 24 jam ke depan.',
                'valid_until' => data_get($window[7] ?? [], 'dt_txt'),
            ];
        }

        if ($maxWind >= 7) { // ~> 25 km/jam
            return [
                'severity' => 'yellow',
                'type' => 'wind',
                'message' => 'Angin cukup kencang, perhatikan kondisi lahan/gudang.',
                'valid_until' => data_get($window[7] ?? [], 'dt_txt'),
            ];
        }

        return [
            'severity' => 'green',
            'type' => 'normal',
            'message' => 'Cuaca relatif aman.',
            'valid_until' => data_get($window[7] ?? [], 'dt_txt'),
        ];
    }
}
