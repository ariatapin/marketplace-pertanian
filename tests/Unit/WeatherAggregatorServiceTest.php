<?php

namespace Tests\Unit;

use App\Services\Weather\BmkgWeatherService;
use App\Services\Weather\OpenWeatherClient;
use App\Services\Weather\WeatherAggregatorService;
use Mockery;
use Tests\TestCase;

class WeatherAggregatorServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_uses_bmkg_fallback_when_openweather_current_is_invalid(): void
    {
        $openWeather = Mockery::mock(OpenWeatherClient::class);
        $bmkg = Mockery::mock(BmkgWeatherService::class);

        $openWeather
            ->shouldReceive('current')
            ->once()
            ->with(-6.261493, 106.8106)
            ->andReturn([
                'main' => ['temp' => null],
                'weather' => [['description' => 'unknown']],
            ]);

        $bmkg
            ->shouldReceive('forecastByRegionCode')
            ->once()
            ->with('3171010')
            ->andReturn([
                'items' => [[
                    'local_datetime' => '2026-02-21 10:00:00',
                    't' => 28.6,
                    'hu' => 82,
                    'ws' => 3.2,
                    'wd' => 'Barat Laut',
                    'weather_desc' => 'Cerah Berawan',
                ]],
            ]);

        $service = new WeatherAggregatorService($openWeather, $bmkg);
        $result = $service->current(-6.261493, 106.8106, '3171010');

        $this->assertSame('bmkg_fallback', $result['source']);
        $this->assertSame(28.6, data_get($result, 'main.temp'));
        $this->assertSame(82, data_get($result, 'main.humidity'));
        $this->assertSame('Cerah Berawan', data_get($result, 'weather.0.description'));
    }

    public function test_it_keeps_openweather_when_current_payload_is_valid(): void
    {
        $openWeather = Mockery::mock(OpenWeatherClient::class);
        $bmkg = Mockery::mock(BmkgWeatherService::class);

        $openWeather
            ->shouldReceive('current')
            ->once()
            ->andReturn([
                'main' => ['temp' => 29.4, 'humidity' => 74],
                'weather' => [['description' => 'clear sky']],
                'wind' => ['speed' => 2.1],
            ]);

        $bmkg->shouldNotReceive('forecastByRegionCode');

        $service = new WeatherAggregatorService($openWeather, $bmkg);
        $result = $service->current(-6.2, 106.8, '3171010');

        $this->assertSame('openweather', $result['source']);
        $this->assertSame(29.4, data_get($result, 'main.temp'));
        $this->assertSame('clear sky', data_get($result, 'weather.0.description'));
    }

    public function test_it_uses_bmkg_fallback_when_openweather_forecast_is_empty(): void
    {
        $openWeather = Mockery::mock(OpenWeatherClient::class);
        $bmkg = Mockery::mock(BmkgWeatherService::class);

        $openWeather
            ->shouldReceive('forecast')
            ->once()
            ->with(-6.261493, 106.8106)
            ->andReturn([
                'list' => [],
            ]);

        $bmkg
            ->shouldReceive('forecastByRegionCode')
            ->once()
            ->with('3171010')
            ->andReturn([
                'items' => [
                    [
                        'local_datetime' => '2026-02-21 10:00:00',
                        't' => 28.6,
                        'hu' => 82,
                        'ws' => 3.2,
                        'wd' => 'Barat Laut',
                        'weather_desc' => 'Hujan Ringan',
                    ],
                ],
            ]);

        $service = new WeatherAggregatorService($openWeather, $bmkg);
        $result = $service->forecast(-6.261493, 106.8106, '3171010');

        $this->assertSame('bmkg_fallback', $result['source']);
        $this->assertSame('Hujan Ringan', data_get($result, 'list.0.weather.0.description'));
    }
}
