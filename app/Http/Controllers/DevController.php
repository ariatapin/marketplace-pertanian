<?php

namespace App\Http\Controllers;

use App\Services\Weather\OpenWeatherClient;
use App\Services\Weather\WeatherService;

class DevController extends Controller
{
    public function openWeather(OpenWeatherClient $openWeatherClient)
    {
        $lat = -6.2000000;
        $lng = 106.8166667;

        return response()->json([
            'current' => $openWeatherClient->current($lat, $lng),
            'forecast' => $openWeatherClient->forecast($lat, $lng),
        ]);
    }

    public function weatherCache(WeatherService $weatherService)
    {
        $lat = -6.2000000;
        $lng = 106.8166667;

        $current = $weatherService->current('custom', 1, $lat, $lng);
        $forecast = $weatherService->forecast('custom', 1, $lat, $lng);

        return response()->json([
            'current_name' => data_get($current, 'name'),
            'current_temp' => data_get($current, 'main.temp'),
            'forecast_count' => is_array(data_get($forecast, 'list')) ? count($forecast['list']) : null,
            'cache_written' => true,
        ]);
    }

    public function weatherWidget()
    {
        return view('dev.weather-widget-page');
    }
}
