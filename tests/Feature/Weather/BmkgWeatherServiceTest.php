<?php

namespace Tests\Feature\Weather;

use App\Models\WeatherCache;
use App\Services\Weather\BmkgWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BmkgWeatherServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_caches_bmkg_payload_for_six_hours(): void
    {
        Http::fake([
            'https://api.bmkg.go.id/publik/prakiraan-cuaca*' => Http::response([
                'data' => [[
                    'cuaca' => [[
                        [
                            'local_datetime' => '2026-02-21 11:00:00',
                            't' => 30.2,
                            'hu' => 77,
                            'ws' => 3.5,
                            'wd' => 'Barat',
                            'weather_desc' => 'Cerah',
                        ],
                    ]],
                ]],
            ], 200),
        ]);

        $service = app(BmkgWeatherService::class);

        $first = $service->forecastByRegionCode('3171010');
        $second = $service->forecastByRegionCode('3171010');

        $this->assertSame(false, (bool) ($first['from_cache'] ?? true));
        $this->assertSame(true, (bool) ($second['from_cache'] ?? false));
        $this->assertSame('2026-02-21 11:00:00', data_get($first, 'items.0.local_datetime'));
        $this->assertSame(30.2, data_get($first, 'items.0.t'));

        $this->assertDatabaseHas('weather_caches', [
            'provider' => 'bmkg',
            'cache_key' => 'bmkg:forecast:3171010',
            'kode_wilayah' => '3171010',
        ]);

        Http::assertSentCount(1);
    }

    public function test_it_uses_stale_cache_when_bmkg_request_fails(): void
    {
        WeatherCache::query()->create([
            'provider' => 'bmkg',
            'cache_key' => 'bmkg:forecast:3171010',
            'kode_wilayah' => '3171010',
            'payload' => [
                'items' => [[
                    'local_datetime' => '2026-02-20 09:00:00',
                    't' => 27.1,
                    'hu' => 83,
                    'ws' => 2.9,
                    'wd' => 'Utara',
                    'weather_desc' => 'Berawan',
                ]],
            ],
            'fetched_at' => now()->subHours(8),
            'valid_until' => now()->subHours(2),
        ]);

        Http::fake([
            'https://api.bmkg.go.id/publik/prakiraan-cuaca*' => Http::response([
                'status' => 'error',
            ], 500),
        ]);

        $service = app(BmkgWeatherService::class);
        $result = $service->forecastByRegionCode('3171010');

        $this->assertSame(true, (bool) ($result['from_cache'] ?? false));
        $this->assertSame(true, (bool) ($result['is_stale_cache'] ?? false));
        $this->assertSame(27.1, data_get($result, 'items.0.t'));
    }
}

