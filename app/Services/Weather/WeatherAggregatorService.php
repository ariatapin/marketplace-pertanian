<?php

namespace App\Services\Weather;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Aggregator cuaca: prioritas OpenWeather, fallback ke BMKG saat data tidak valid.
 */
class WeatherAggregatorService
{
    public function __construct(
        private readonly OpenWeatherClient $openWeatherClient,
        private readonly BmkgWeatherService $bmkgWeatherService
    ) {
    }

    /**
     * Ambil current weather dengan fallback BMKG jika data OpenWeather invalid.
     */
    public function current(float $lat, float $lon, ?string $kodeWilayah = null): array
    {
        $bmkgCode = trim((string) $kodeWilayah);
        if ($this->isMissingCoordinate($lat, $lon) && $bmkgCode !== '') {
            $bmkgPayload = $this->bmkgWeatherService->forecastByRegionCode($bmkgCode);
            $fallbackPayload = $this->mapBmkgToCurrentPayload($bmkgPayload, $lat, $lon);
            if ($fallbackPayload !== []) {
                Log::info('Cuaca tanpa koordinat menggunakan BMKG fallback.', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'kode_wilayah' => $bmkgCode,
                ]);

                return $this->withSource($fallbackPayload, 'bmkg_fallback');
            }

            return [];
        }

        $openPayload = [];

        try {
            $openPayload = $this->normalizePayload($this->openWeatherClient->current($lat, $lon));
        } catch (\Throwable $e) {
            Log::warning('OpenWeather current gagal.', [
                'lat' => $lat,
                'lon' => $lon,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $this->needsBmkgFallback($openPayload)) {
            return $this->withSource($openPayload, 'openweather');
        }

        if ($bmkgCode === '') {
            return $this->withSource($openPayload, 'openweather');
        }

        $bmkgPayload = $this->bmkgWeatherService->forecastByRegionCode($bmkgCode);
        $fallbackPayload = $this->mapBmkgToCurrentPayload($bmkgPayload, $lat, $lon);
        if ($fallbackPayload !== []) {
            Log::info('Fallback cuaca menggunakan BMKG.', [
                'lat' => $lat,
                'lon' => $lon,
                'kode_wilayah' => $bmkgCode,
            ]);

            return $this->withSource($fallbackPayload, 'bmkg_fallback');
        }

        return $this->withSource($openPayload, 'openweather');
    }

    /**
     * Ambil forecast dengan fallback BMKG saat list OpenWeather kosong/tidak valid.
     */
    public function forecast(float $lat, float $lon, ?string $kodeWilayah = null): array
    {
        $bmkgCode = trim((string) $kodeWilayah);
        if ($this->isMissingCoordinate($lat, $lon) && $bmkgCode !== '') {
            $bmkgPayload = $this->bmkgWeatherService->forecastByRegionCode($bmkgCode);
            $fallbackPayload = $this->mapBmkgToForecastPayload($bmkgPayload, $lat, $lon);
            if ($fallbackPayload !== []) {
                Log::info('Forecast tanpa koordinat menggunakan BMKG fallback.', [
                    'lat' => $lat,
                    'lon' => $lon,
                    'kode_wilayah' => $bmkgCode,
                ]);

                return $this->withSource($fallbackPayload, 'bmkg_fallback');
            }

            return [];
        }

        $openPayload = [];

        try {
            $openPayload = $this->normalizePayload($this->openWeatherClient->forecast($lat, $lon));
        } catch (\Throwable $e) {
            Log::warning('OpenWeather forecast gagal.', [
                'lat' => $lat,
                'lon' => $lon,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $this->forecastNeedsBmkgFallback($openPayload)) {
            return $this->withSource($openPayload, 'openweather');
        }

        if ($bmkgCode === '') {
            return $this->withSource($openPayload, 'openweather');
        }

        $bmkgPayload = $this->bmkgWeatherService->forecastByRegionCode($bmkgCode);
        $fallbackPayload = $this->mapBmkgToForecastPayload($bmkgPayload, $lat, $lon);
        if ($fallbackPayload !== []) {
            Log::info('Fallback forecast menggunakan BMKG.', [
                'lat' => $lat,
                'lon' => $lon,
                'kode_wilayah' => $bmkgCode,
            ]);

            return $this->withSource($fallbackPayload, 'bmkg_fallback');
        }

        return $this->withSource($openPayload, 'openweather');
    }

    /**
     * Tentukan apakah payload OpenWeather perlu fallback BMKG.
     */
    private function needsBmkgFallback(array $openPayload): bool
    {
        $temp = data_get($openPayload, 'main.temp');
        $description = strtolower(trim((string) data_get($openPayload, 'weather.0.description', '')));

        $temperatureMissing = $temp === null || $temp === '' || ! is_numeric($temp);
        $descriptionMissing = $description === '' || $description === 'unknown';

        return $temperatureMissing || $descriptionMissing;
    }

    /**
     * Tentukan apakah forecast OpenWeather perlu fallback BMKG.
     */
    private function forecastNeedsBmkgFallback(array $openPayload): bool
    {
        $list = data_get($openPayload, 'list');
        if (! is_array($list) || count($list) === 0) {
            return true;
        }

        $first = data_get($openPayload, 'list.0');
        if (! is_array($first)) {
            return true;
        }

        $temp = data_get($first, 'main.temp');
        $description = strtolower(trim((string) data_get($first, 'weather.0.description', '')));

        $temperatureMissing = $temp === null || $temp === '' || ! is_numeric($temp);
        $descriptionMissing = $description === '' || $description === 'unknown';

        return $temperatureMissing || $descriptionMissing;
    }

    /**
     * Map item BMKG menjadi format payload current mirip OpenWeather.
     */
    private function mapBmkgToCurrentPayload(array $bmkgPayload, float $lat, float $lon): array
    {
        $first = data_get($bmkgPayload, 'items.0');
        if (! is_array($first)) {
            return [];
        }

        $description = trim((string) ($first['weather_desc'] ?? ''));
        $dateText = $this->normalizeDateText($first['local_datetime'] ?? null);

        return [
            'coord' => ['lat' => $lat, 'lon' => $lon],
            'weather' => [[
                'main' => $this->mapWeatherMain($description),
                'description' => $description !== '' ? $description : 'unknown',
            ]],
            'main' => [
                'temp' => $first['t'] ?? null,
                'humidity' => $first['hu'] ?? null,
            ],
            'wind' => [
                'speed' => $first['ws'] ?? null,
                'deg' => null,
                'direction' => $first['wd'] ?? null,
            ],
            'dt_txt' => $dateText,
            'dt' => $this->toUnixTimestamp($dateText),
        ];
    }

    /**
     * Map array item BMKG ke format list forecast OpenWeather.
     */
    private function mapBmkgToForecastPayload(array $bmkgPayload, float $lat, float $lon): array
    {
        $items = data_get($bmkgPayload, 'items', []);
        if (! is_array($items) || $items === []) {
            return [];
        }

        $list = collect($items)
            ->map(function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $description = trim((string) ($row['weather_desc'] ?? ''));
                $dateText = $this->normalizeDateText($row['local_datetime'] ?? null);

                return [
                    'dt_txt' => $dateText,
                    'dt' => $this->toUnixTimestamp($dateText),
                    'main' => [
                        'temp' => $row['t'] ?? null,
                        'humidity' => $row['hu'] ?? null,
                    ],
                    'weather' => [[
                        'main' => $this->mapWeatherMain($description),
                        'description' => $description !== '' ? $description : 'unknown',
                    ]],
                    'wind' => [
                        'speed' => $row['ws'] ?? null,
                        'deg' => null,
                        'direction' => $row['wd'] ?? null,
                    ],
                    'pop' => 0,
                    'rain' => ['3h' => 0],
                ];
            })
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();

        if ($list === []) {
            return [];
        }

        return [
            'city' => [
                'coord' => ['lat' => $lat, 'lon' => $lon],
            ],
            'list' => $list,
        ];
    }

    /**
     * Mapping deskripsi BMKG ke kategori weather main ala OpenWeather.
     */
    private function mapWeatherMain(string $description): string
    {
        $text = strtolower(trim($description));
        if ($text === '' || $text === 'unknown') {
            return 'Unknown';
        }

        if (str_contains($text, 'petir') || str_contains($text, 'thunder')) {
            return 'Thunderstorm';
        }

        if (str_contains($text, 'hujan') || str_contains($text, 'rain') || str_contains($text, 'gerimis')) {
            return 'Rain';
        }

        if (str_contains($text, 'awan') || str_contains($text, 'cloud')) {
            return 'Clouds';
        }

        if (str_contains($text, 'kabut') || str_contains($text, 'fog') || str_contains($text, 'mist')) {
            return 'Mist';
        }

        if (str_contains($text, 'cerah') || str_contains($text, 'clear')) {
            return 'Clear';
        }

        return 'Clouds';
    }

    /**
     * Tambahkan marker sumber payload agar consumer tahu asal data.
     */
    private function withSource(array $payload, string $source): array
    {
        $normalized = $this->normalizePayload($payload);
        $normalized['source'] = $source;

        return $normalized;
    }

    /**
     * Normalisasi payload agar selalu array.
     */
    private function normalizePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return (array) $payload;
        }

        return [];
    }

    /**
     * Normalisasi teks tanggal untuk field `dt_txt`.
     */
    private function normalizeDateText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($text)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Konversi string tanggal ke unix timestamp.
     */
    private function toUnixTimestamp(?string $dateText): ?int
    {
        if (! $dateText) {
            return null;
        }

        try {
            return CarbonImmutable::parse($dateText)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Deteksi koordinat kosong (0,0) yang tidak layak untuk request OpenWeather.
     */
    private function isMissingCoordinate(float $lat, float $lon): bool
    {
        return abs($lat) < 0.000001 && abs($lon) < 0.000001;
    }
}
