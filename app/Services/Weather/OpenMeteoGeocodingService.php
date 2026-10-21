<?php

namespace App\Services\Weather;

use App\Models\Farm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenMeteoGeocodingService
{
    public function resolve(Farm $farm): ?array
    {
        $place = trim((string) $farm->location);
        if ($place === '') return null;

        $cacheKey = 'weather.geocode.'.sha1(mb_strtolower($place.'|'.$farm->country));
        $result = Cache::remember($cacheKey, now()->addDays(7), function () use ($place, $farm) {
            try {
                $response = Http::acceptJson()
                    ->timeout((int) config('weather.timeout_seconds', 10))
                    ->retry(2, 250, throw: false)
                    ->get(config('weather.geocoding_endpoint', 'https://geocoding-api.open-meteo.com/v1/search'), [
                        'name' => $place,
                        'count' => 1,
                        'language' => 'fr',
                        'format' => 'json',
                        'countryCode' => $this->countryCode($farm->country),
                    ]);
            } catch (\Throwable) {
                return ['matched' => false];
            }

            $match = $response->json('results.0');
            if ($response->failed() || ! is_array($match) || ! is_numeric($match['latitude'] ?? null) || ! is_numeric($match['longitude'] ?? null)) {
                return ['matched' => false];
            }

            return [
                'matched' => true,
                'latitude' => (float) $match['latitude'],
                'longitude' => (float) $match['longitude'],
                'name' => (string) ($match['name'] ?? $place),
                'admin1' => (string) ($match['admin1'] ?? ''),
                'country' => (string) ($match['country'] ?? $farm->country),
            ];
        });

        return ($result['matched'] ?? false) ? $result : null;
    }

    private function countryCode(?string $country): ?string
    {
        $country = mb_strtolower(trim((string) $country));

        return str_contains($country, 'tun') ? 'TN' : null;
    }
}
