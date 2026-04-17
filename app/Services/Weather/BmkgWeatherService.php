<?php

namespace App\Services\Weather;

use App\Models\WeatherCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service adapter BMKG untuk fallback data cuaca saat OpenWeather tidak valid.
 */
class BmkgWeatherService
{
    /**
     * Ambil prakiraan BMKG per kode wilayah (adm4) dengan cache DB 6 jam.
     */
    public function forecastByRegionCode(?string $kodeWilayah): array
    {
        $normalizedCode = trim((string) $kodeWilayah);
        if ($normalizedCode === '') {
            return $this->emptyPayload();
        }

        $now = CarbonImmutable::now();
        $cacheKey = $this->cacheKey($normalizedCode);

        $validCache = WeatherCache::query()
            ->where('provider', 'bmkg')
            ->where('cache_key', $cacheKey)
            ->where('valid_until', '>', $now)
            ->latest('fetched_at')
            ->first();

        if ($validCache) {
            $payload = $this->normalizePayload($validCache->payload);
            $payload['from_cache'] = true;
            $payload['is_stale_cache'] = false;

            return $payload;
        }

        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->retry(1, 200)
                ->get($this->endpoint(), [
                    'adm4' => $normalizedCode,
                ]);

            $response->throw();

            $decoded = $response->json();
            if (! is_array($decoded)) {
                throw new \RuntimeException('Payload BMKG bukan JSON object/array.');
            }

            $parsed = $this->parsePayload($decoded);
            if (count($parsed['items']) === 0) {
                throw new \RuntimeException('Payload BMKG valid tetapi item cuaca kosong.');
            }

            WeatherCache::query()->updateOrCreate(
                [
                    'provider' => 'bmkg',
                    'cache_key' => $cacheKey,
                ],
                [
                    'kode_wilayah' => $normalizedCode,
                    'payload' => $parsed,
                    'fetched_at' => $now,
                    'valid_until' => $now->addHours($this->cacheHours()),
                ]
            );

            $parsed['from_cache'] = false;
            $parsed['is_stale_cache'] = false;

            return $parsed;
        } catch (\Throwable $e) {
            Log::warning('BMKG fetch gagal, mencoba cache terakhir.', [
                'kode_wilayah' => $normalizedCode,
                'error' => $e->getMessage(),
            ]);

            $latestCache = WeatherCache::query()
                ->where('provider', 'bmkg')
                ->where('cache_key', $cacheKey)
                ->latest('fetched_at')
                ->first();

            if ($latestCache) {
                $payload = $this->normalizePayload($latestCache->payload);
                $payload['from_cache'] = true;
                $payload['is_stale_cache'] = true;

                return $payload;
            }

            return $this->emptyPayload();
        }
    }

    /**
     * Parse payload BMKG menjadi struktur item cuaca yang konsisten.
     */
    private function parsePayload(array $payload): array
    {
        $rows = collect($this->extractWeatherRows($payload))
            ->map(function (array $row): array {
                return [
                    'local_datetime' => $this->normalizeDateTime(
                        $row['local_datetime'] ?? ($row['datetime'] ?? null)
                    ),
                    't' => $this->toFloatOrNull($row['t'] ?? null),
                    'hu' => $this->toIntOrNull($row['hu'] ?? null),
                    'ws' => $this->toFloatOrNull($row['ws'] ?? null),
                    'wd' => $this->toStringOrNull($row['wd'] ?? null),
                    'weather_desc' => $this->toStringOrNull(
                        $row['weather_desc'] ?? ($row['weather_desc_en'] ?? null)
                    ),
                ];
            })
            ->filter(function (array $row): bool {
                return $row['local_datetime'] !== null
                    || $row['t'] !== null
                    || $row['hu'] !== null
                    || $row['ws'] !== null
                    || $row['wd'] !== null
                    || $row['weather_desc'] !== null;
            })
            ->sortBy('local_datetime')
            ->values()
            ->all();

        return [
            'items' => $rows,
        ];
    }

    /**
     * Ekstrak node cuaca yang memuat field BMKG target secara rekursif.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractWeatherRows(mixed $node): array
    {
        if (! is_array($node)) {
            return [];
        }

        $hasWeatherShape = array_key_exists('t', $node)
            || array_key_exists('hu', $node)
            || array_key_exists('ws', $node)
            || array_key_exists('wd', $node)
            || array_key_exists('weather_desc', $node)
            || array_key_exists('local_datetime', $node)
            || array_key_exists('datetime', $node);

        $rows = [];
        if ($hasWeatherShape) {
            $rows[] = $node;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $rows = array_merge($rows, $this->extractWeatherRows($value));
            }
        }

        return $rows;
    }

    /**
     * Normalisasi payload cache agar tetap aman dibaca.
     */
    private function normalizePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return (array) $payload;
        }

        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->emptyPayload();
    }

    /**
     * Ubah nilai datetime BMKG ke format `Y-m-d H:i:s` jika memungkinkan.
     */
    private function normalizeDateTime(mixed $value): ?string
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
        } catch (\Throwable $e) {
            if (preg_match('/^\d{12}$/', $text)) {
                try {
                    return CarbonImmutable::createFromFormat('YmdHi', $text)->format('Y-m-d H:i:s');
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }
    }

    /**
     * Konversi angka BMKG ke float nullable.
     */
    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Konversi angka BMKG ke integer nullable.
     */
    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    /**
     * Konversi teks BMKG ke string nullable.
     */
    private function toStringOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Bangun key cache BMKG agar stabil dan mudah diindeks.
     */
    private function cacheKey(string $kodeWilayah): string
    {
        return 'bmkg:forecast:' . $kodeWilayah;
    }

    /**
     * Ambil endpoint BMKG dari konfigurasi aplikasi.
     */
    private function endpoint(): string
    {
        $base = rtrim((string) config('weather.bmkg.base', 'https://api.bmkg.go.id'), '/');
        $path = '/' . ltrim((string) config('weather.bmkg.forecast_path', '/publik/prakiraan-cuaca'), '/');

        return $base . $path;
    }

    /**
     * Ambil timeout HTTP untuk request BMKG.
     */
    private function timeoutSeconds(): int
    {
        return max(1, (int) config('weather.bmkg.timeout_seconds', 10));
    }

    /**
     * Ambil TTL cache BMKG dalam jam.
     */
    private function cacheHours(): int
    {
        return max(1, (int) config('weather.bmkg.cache_hours', 6));
    }

    /**
     * Payload kosong default agar caller tidak perlu null-check berulang.
     */
    private function emptyPayload(): array
    {
        return [
            'items' => [],
            'from_cache' => false,
            'is_stale_cache' => false,
        ];
    }
}

