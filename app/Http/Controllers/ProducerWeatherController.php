<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\HarvestPlan;
use App\Services\Weather\AgriculturalWeatherAdviceService;
use App\Services\Weather\OpenMeteoGeocodingService;
use App\Services\Weather\WeatherService;
use App\Services\Weather\WeatherUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProducerWeatherController extends Controller
{
    public function index(Request $request)
    {
        $farms = Farm::query()->where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name', 'country', 'latitude', 'longitude']);

        return view('weather.producer', [
            'farms' => $farms,
            'selectedFarmId' => (string) $request->query('farm_id', $farms->first()?->id ?? ''),
        ]);
    }

    public function forecast(Request $request, WeatherService $weather, OpenMeteoGeocodingService $geocoder, AgriculturalWeatherAdviceService $adviser): JsonResponse
    {
        $validated = $request->validate([
            'farm_id' => ['required', 'integer', Rule::exists('farms', 'id')->where('user_id', $request->user()->id)->whereNull('deleted_at')],
            'refresh' => ['sometimes', 'boolean'],
        ]);

        // Always scope the lookup to the signed-in producer, even after validation.
        $farm = Farm::query()->where('user_id', $request->user()->id)->findOrFail($validated['farm_id']);
        $exactCoordinates = $farm->latitude !== null && $farm->longitude !== null;
        $geocoded = $exactCoordinates ? null : $geocoder->resolve($farm);
        if (! $exactCoordinates && ! $geocoded) {
            return response()->json([
                'status' => 'missing_coordinates',
                'message' => 'Les coordonnées GPS sont absentes et la localité renseignée n’a pas pu être géolocalisée.',
                'edit_url' => route('workspace.resources.edit', ['farms', $farm]),
            ], 422);
        }

        // If only a locality is available, use its Open-Meteo gazetteer point without
        // writing it to the farm as if it were the parcel's exact GPS position.
        $weatherFarm = $farm;
        if (! $exactCoordinates) {
            $weatherFarm = clone $farm;
            $weatherFarm->latitude = $geocoded['latitude'];
            $weatherFarm->longitude = $geocoded['longitude'];
        }

        try {
            $forecast = $weather->forecast($weatherFarm, $request->boolean('refresh'));
        } catch (WeatherUnavailableException $exception) {
            return response()->json(['status' => 'unavailable', 'message' => $exception->getMessage()], 503);
        }

        $advice = $adviser->generate($forecast);
        $firstDay = $forecast['daily']['time'][0];
        $lastDay = $forecast['daily']['time'][count($forecast['daily']['time']) - 1];
        $plans = HarvestPlan::query()
            ->where('user_id', $request->user()->id)
            ->where('farm_id', $farm->id)
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereDate('starts_at', '<=', $lastDay)
            ->whereDate('ends_at', '>=', $firstDay)
            ->with('product:id,name')
            ->orderBy('starts_at')
            ->get();

        $impacts = $plans->map(function (HarvestPlan $plan) use ($advice) {
            $start = $plan->starts_at->toDateString();
            $end = $plan->ends_at->toDateString();
            $risks = collect($advice)->filter(function (array $item) use ($start, $end) {
                if (! in_array($item['type'], ['rain', 'runoff', 'storm', 'wind', 'heat'], true)) {
                    return false;
                }
                foreach ($item['days'] as $day) {
                    if ($day >= $start && $day <= $end) {
                        return true;
                    }
                }

                return false;
            })->map(fn (array $item) => ['type' => $item['type'], 'title' => $item['title'], 'priority' => $item['priority']])->values();

            return [
                'reference' => $plan->reference,
                'product' => $plan->product?->name ?? 'Produit',
                'period' => $start === $end ? $start : $start.' au '.$end,
                'estimated_quantity' => $plan->estimated_quantity,
                'unit' => $plan->unit,
                'risks' => $risks,
            ];
        })->filter()->values();

        $otherFarmHarvests = $impacts->isEmpty()
            ? HarvestPlan::query()
                ->where('user_id', $request->user()->id)
                ->where('farm_id', '!=', $farm->id)
                ->whereIn('status', ['planned', 'in_progress'])
                ->whereDate('starts_at', '<=', $lastDay)
                ->whereDate('ends_at', '>=', $firstDay)
                ->with('farm:id,name')
                ->get()
                ->groupBy('farm_id')
                ->map(fn ($farmPlans) => [
                    'name' => $farmPlans->first()->farm?->name ?? 'Autre exploitation',
                    'count' => $farmPlans->count(),
                ])->values()
            : collect();

        return response()->json([
            'status' => 'ok',
            'farm' => ['id' => $farm->id, 'name' => $farm->name],
            'location_accuracy' => $exactCoordinates ? 'exact' : 'approximate',
            'matched_location' => $exactCoordinates ? null : implode(', ', array_unique(array_filter([$geocoded['name'], $geocoded['admin1'], $geocoded['country']]))),
            'edit_url' => route('workspace.resources.edit', ['farms', $farm]),
            'forecast' => $forecast,
            'advice' => $advice,
            'harvest_impacts' => $impacts,
            'other_farm_harvests' => $otherFarmHarvests,
        ]);
    }
}
