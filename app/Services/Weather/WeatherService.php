<?php

namespace App\Services\Weather;

use App\Models\Farm;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    private const CURRENT = [
        'temperature_2m', 'relative_humidity_2m', 'apparent_temperature', 'precipitation',
        'rain', 'weather_code', 'wind_speed_10m', 'wind_direction_10m', 'wind_gusts_10m',
    ];

    private const HOURLY = [
        'temperature_2m', 'relative_humidity_2m', 'precipitation_probability', 'precipitation', 'weather_code',
        'rain', 'wind_speed_10m', 'wind_gusts_10m', 'et0_fao_evapotranspiration',
    ];

    private const DAILY = [
        'weather_code', 'temperature_2m_max', 'temperature_2m_min', 'precipitation_sum',
        'precipitation_probability_max', 'wind_speed_10m_max', 'wind_gusts_10m_max',
        'et0_fao_evapotranspiration',
    ];

    public function forecast(Farm $farm, bool $refresh = false): array
    {
        if ($farm->latitude === null || $farm->longitude === null) {
            throw new WeatherUnavailableException('Les coordonnées GPS de cette exploitation ne sont pas renseignées.');
        }

        $timezone = $this->timezoneFor($farm);
        $cacheKey = 'weather.forecast.'.sha1(implode('|', [round((float) $farm->latitude, 5), round((float) $farm->longitude, 5), $timezone]));
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(max(1, config('weather.cache_ttl', 1800))), function () use ($farm, $timezone) {
            try {
                $response = Http::acceptJson()
                    ->timeout((int) config('weather.timeout_seconds', 10))
                    ->retry(2, 250, throw: false)
                    ->get(config('weather.endpoint'), [
                        'latitude' => (float) $farm->latitude,
                        'longitude' => (float) $farm->longitude,
                        'current' => implode(',', self::CURRENT),
                        'hourly' => implode(',', self::HOURLY),
                        'daily' => implode(',', self::DAILY),
                        'forecast_days' => 7,
                        'timezone' => $timezone,
                        'temperature_unit' => 'celsius',
                        'wind_speed_unit' => 'kmh',
                        'precipitation_unit' => 'mm',
                    ]);
            } catch (ConnectionException $exception) {
                throw new WeatherUnavailableException('Le service météo est momentanément indisponible. Réessayez dans quelques instants.', previous: $exception);
            }

            if ($response->failed()) {
                throw new WeatherUnavailableException('Le service météo n’a pas pu répondre à la demande. Réessayez plus tard.');
            }

            $data = $response->json();
            if (! is_array($data) || ! $this->hasUsableForecast($data)) {
                throw new WeatherUnavailableException('Les prévisions reçues sont incomplètes. Réessayez plus tard.');
            }

            return [
                'current' => $data['current'],
                'current_units' => $data['current_units'] ?? [],
                'hourly' => $data['hourly'],
                'hourly_units' => $data['hourly_units'] ?? [],
                'daily' => $data['daily'],
                'daily_units' => $data['daily_units'] ?? [],
                'timezone' => $data['timezone'] ?? $timezone,
                'timezone_abbreviation' => $data['timezone_abbreviation'] ?? '',
                'fetched_at' => now($timezone)->toIso8601String(),
                'source' => 'Open-Meteo',
            ];
        });
    }

    private function timezoneFor(Farm $farm): string
    {
        $country = mb_strtolower(trim((string) $farm->country));

        return str_contains($country, 'tun') ? config('weather.timezone', 'Africa/Tunis') : 'auto';
    }

    private function hasUsableForecast(array $data): bool
    {
        if (! is_array($data['current'] ?? null) || ! is_array($data['hourly'] ?? null) || ! is_array($data['daily'] ?? null)) {
            return false;
        }

        foreach (['temperature_2m', 'apparent_temperature', 'relative_humidity_2m', 'precipitation', 'weather_code', 'wind_speed_10m', 'wind_direction_10m', 'wind_gusts_10m'] as $key) {
            if (! array_key_exists($key, $data['current']) || ! is_numeric($data['current'][$key])) {
                return false;
            }
        }
        if (! isset($data['current']['time']) || ! is_string($data['current']['time'])) {
            return false;
        }

        foreach ([['hourly', self::HOURLY], ['daily', self::DAILY]] as [$group, $variables]) {
            $times = $data[$group]['time'] ?? null;
            if (! is_array($times) || $times === []) {
                return false;
            }
            foreach ($variables as $variable) {
                if (! is_array($data[$group][$variable] ?? null) || count($data[$group][$variable]) !== count($times)) {
                    return false;
                }
            }
        }

        return count($data['daily']['time']) >= 7 && count($data['hourly']['time']) >= 24;
    }
}
