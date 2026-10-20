<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Store;
use Illuminate\Http\Request;

class MapDistancesController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $stores = Store::query()->where('user_id', $userId)->orderBy('name')->get([
            'id', 'name', 'reference', 'address', 'city', 'country', 'latitude', 'longitude',
        ]);
        $distributions = Distribution::query()
            ->where('user_id', $userId)
            ->whereHas('store', fn ($query) => $query->where('user_id', $userId))
            ->with(['store:id,user_id,name,reference,address,city,country,latitude,longitude'])
            ->latest()
            ->get(['id', 'store_id', 'reference', 'status', 'origin_name', 'origin_latitude', 'origin_longitude']);

        return view('traceability.map-distances', [
            'stores' => $stores,
            'distributions' => $distributions,
            'mapConfig' => [
                'tileUrl' => config('maps.tile_url'),
                'tileAttribution' => config('maps.tile_attribution'),
                'routingUrl' => config('maps.routing_url'),
            ],
        ]);
    }
}
