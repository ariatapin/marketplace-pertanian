<?php

return [
    'provider' => env('WEATHER_PROVIDER', 'openweather'),
    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
        'base' => env('OPENWEATHER_BASE_URL', 'https://api.openweathermap.org'),
        'units' => env('OPENWEATHER_UNITS', 'metric'),
        'lang' => env('OPENWEATHER_LANG', 'id'),
    ],
    'bmkg' => [
        'base' => env('BMKG_BASE_URL', 'https://api.bmkg.go.id'),
        'forecast_path' => env('BMKG_FORECAST_PATH', '/publik/prakiraan-cuaca'),
        'timeout_seconds' => (int) env('BMKG_TIMEOUT_SECONDS', 10),
        'cache_hours' => (int) env('BMKG_CACHE_HOURS', 6),
    ],
    'cache' => [
        'current_minutes' => (int) env('WEATHER_CACHE_MINUTES_CURRENT', 60),
        'forecast_minutes' => (int) env('WEATHER_CACHE_MINUTES_FORECAST', 180),
    ],
    'fallback' => [
        'lat' => (float) env('WEATHER_FALLBACK_LAT', 0),
        'lng' => (float) env('WEATHER_FALLBACK_LNG', 0),
        'label' => env('WEATHER_FALLBACK_LABEL', 'Lokasi Default'),
    ],
    'auto_notice' => [
        'enabled' => (bool) env('WEATHER_AUTO_NOTICE_ENABLED', true),
        'cron' => env('WEATHER_AUTO_NOTICE_CRON', '17 * * * *'),
        'scope' => env('WEATHER_AUTO_NOTICE_SCOPE', 'province'),
        'minimum_severity' => env('WEATHER_AUTO_NOTICE_MINIMUM_SEVERITY', 'yellow'),
        'minimum_users' => (int) env('WEATHER_AUTO_NOTICE_MINIMUM_USERS', 1),
        'valid_hours' => (int) env('WEATHER_AUTO_NOTICE_VALID_HOURS', 12),
        'limit' => (int) env('WEATHER_AUTO_NOTICE_LIMIT', 80),
        'dispatch_now' => (bool) env('WEATHER_AUTO_NOTICE_DISPATCH_NOW', true),
        'admin_email' => env('WEATHER_AUTO_NOTICE_ADMIN_EMAIL', ''),
    ],
];
