<?php

namespace App\Services\Weather;

use Illuminate\Support\Facades\Http;

class OpenWeatherClient
{
    private string $base;
    private string $key;
    private string $units;
    private string $lang;

    public function __construct()
    {
        $this->base  = rtrim(config('weather.openweather.base'), '/');
        $this->key   = (string) config('weather.openweather.key');
        $this->units = (string) config('weather.openweather.units');
        $this->lang  = (string) config('weather.openweather.lang');

        if ($this->key === '') {
            throw new \RuntimeException('OPENWEATHER_API_KEY belum di-set di .env');
        }
    }

    /**
     * Current weather by coordinates
     */
    public function current(float $lat, float $lng): array
    {
        $res = Http::timeout(15)
            ->retry(2, 250) // 2x retry, 250ms
            ->get($this->base . '/data/2.5/weather', [
                'lat' => $lat,
                'lon' => $lng,
                'appid' => $this->key,
                'units' => $this->units,
                'lang' => $this->lang,
            ]);

        $res->throw();
        return $res->json();
    }

    /**
     * 5 day / 3 hour forecast by coordinates
     */
    public function forecast(float $lat, float $lng): array
    {
        $res = Http::timeout(15)
            ->retry(2, 250)
            ->get($this->base . '/data/2.5/forecast', [
                'lat' => $lat,
                'lon' => $lng,
                'appid' => $this->key,
                'units' => $this->units,
                'lang' => $this->lang,
            ]);

        $res->throw();
        return $res->json();
    }
}
