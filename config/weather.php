<?php

return [
    'endpoint' => env('OPEN_METEO_BASE_URL', env('OPEN_METEO_URL', 'https://api.open-meteo.com/v1/forecast')),
    'cache_ttl' => (int) env('OPEN_METEO_CACHE_TTL', (int) env('WEATHER_CACHE_MINUTES', 30) * 60),
    'timeout_seconds' => (int) env('OPEN_METEO_TIMEOUT', env('WEATHER_TIMEOUT_SECONDS', 10)),
    'timezone' => env('OPEN_METEO_TIMEZONE', 'Africa/Tunis'),
    'thresholds' => [
        'rain_mm' => (float) env('WEATHER_RAIN_THRESHOLD_MM', 5),
        'heavy_rain_mm' => (float) env('WEATHER_HEAVY_RAIN_THRESHOLD_MM', 20),
        'heat_c' => (float) env('WEATHER_HEAT_THRESHOLD_C', 35),
        'wind_kmh' => (float) env('WEATHER_WIND_THRESHOLD_KMH', 35),
        'gust_kmh' => (float) env('WEATHER_GUST_THRESHOLD_KMH', 50),
        'humidity_percent' => (float) env('WEATHER_HUMIDITY_THRESHOLD_PERCENT', 80),
        'frost_c' => (float) env('WEATHER_FROST_THRESHOLD_C', 2),
        'et0_mm' => (float) env('WEATHER_ET0_THRESHOLD_MM', 5),
    ],
];
