@extends('layouts.admin')

@section('title', 'Carte et distances | NutriTrace')
@section('page-heading', 'Carte et distances')

@section('content')
@vite('resources/js/map-distances.js')
<style>
    .map-page{--map-green:#28684d;--map-line:#dfe8e2;--map-muted:#718078;color:#1d2d25}.map-page [hidden]{display:none!important}.map-page .map-card{border:1px solid var(--map-line);border-radius:17px;background:#fff;box-shadow:0 4px 16px #19372908}.map-page .map-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(330px,.85fr);gap:18px}.map-page #distance-map{height:620px;min-height:420px;border-radius:17px;z-index:0}.map-page .map-panel{padding:22px}.map-page .map-field{display:grid;gap:7px}.map-page .map-field label{font-size:13px;font-weight:650;color:#304137}.map-page .map-field select{width:100%;min-height:46px;border:1px solid #dbe4de;border-radius:11px;background:#fff;padding:10px 12px;color:#25352d}.map-page .map-button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border:1px solid #dbe4de;border-radius:11px;background:white;padding:0 14px;color:#304137;font-size:13px;font-weight:700;cursor:pointer}.map-page .map-button:disabled{opacity:.55;cursor:wait}.map-page .map-button-primary{border-color:var(--map-green);background:var(--map-green);color:white}.map-page .map-status{margin-top:15px;border-radius:11px;background:#eff5f1;padding:12px;color:#40564a;font-size:12px;line-height:1.5}.map-page .map-status[data-kind="error"]{background:#fff1ef;color:#a13d35}.map-page .map-status[data-kind="success"]{background:#eaf6ed;color:#28684d}.map-page .map-result{margin-top:15px;border:1px solid #dce8df;border-radius:13px;background:#f5faf6;padding:15px}.map-page .map-result-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}.map-page .map-result-stat{border-radius:10px;background:white;padding:12px}.map-page .map-muted{color:#76847b;font-size:12px;line-height:1.6}.map-page .nt-map-marker{background:#28684d;border:3px solid white;border-radius:50%;box-shadow:0 2px 9px #162c2140}.map-page .nt-map-marker span{display:block;width:100%;height:100%;border-radius:50%;background:#28684d}.map-page .nt-map-position{background:#3274d9;border:3px solid white;border-radius:50%;box-shadow:0 2px 9px #162c2140}.map-page .nt-map-position span{display:block;width:100%;height:100%;border-radius:50%;background:#3274d9}.map-page .map-no-coordinates{border-radius:12px;background:#fff8e9;padding:14px;color:#7e5b21;font-size:12px}.map-page a{color:#28684d;font-weight:650}
    @media(max-width:1050px){.map-page .map-layout{grid-template-columns:1fr}.map-page #distance-map{height:480px;min-height:360px}}
    @media(max-width:640px){.map-page .map-panel{padding:17px}.map-page #distance-map{height:390px;min-height:330px}.map-page .map-result-stats{grid-template-columns:1fr 1fr}}
</style>

<main class="map-page space-y-5" data-map-distance-app>
    <nav class="flex items-center gap-2 text-xs text-gray-500"><a href="{{ route('distributeur.dashboard') }}">Tableau de bord</a><span aria-hidden="true">›</span><span>Carte et distances</span></nav>
    <header class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[.14em] text-green-800">Espace distributeur</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">Carte et distances</h1><p class="mt-2 text-sm text-gray-500">Visualisez vos points de vente et estimez les trajets routiers.</p></div><a class="map-button" href="{{ route('workspace.resources.index', 'stores') }}">Gérer les points de vente</a></header>

    <div class="map-layout">
        <section class="map-card overflow-hidden" aria-label="Carte des points de vente"><div id="distance-map" role="application" aria-label="Carte interactive des points de vente"></div></section>
        <aside class="map-card map-panel">
            <h2 class="text-lg font-semibold text-gray-900">Calculer un itinéraire</h2>
            <p class="map-muted mt-1">Le trajet et sa durée sont calculés à la demande à partir des coordonnées disponibles.</p>
            <div class="mt-5 grid gap-4">
                <div class="map-field"><label for="route-mode">Mode de calcul</label><select id="route-mode"><option value="current-store">Ma position → point de vente</option><option value="store-store">Point de vente → point de vente</option><option value="distribution">Départ d’une distribution → destination</option></select></div>
                <div class="map-field" data-route-field="store-store"><label for="route-origin-store">Point de départ</label><select id="route-origin-store"><option value="">Choisir un point de vente</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}{{ $store->city ? ' · '.$store->city : '' }}{{ $store->latitude === null || $store->longitude === null ? ' (coordonnées manquantes)' : '' }}</option>@endforeach</select></div>
                <div class="map-field" data-route-field="current-store store-store"><label for="route-destination-store">Destination</label><select id="route-destination-store"><option value="">Choisir un point de vente</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}{{ $store->city ? ' · '.$store->city : '' }}{{ $store->latitude === null || $store->longitude === null ? ' (coordonnées manquantes)' : '' }}</option>@endforeach</select></div>
                <div class="map-field" data-route-field="distribution"><label for="route-distribution">Distribution concernée</label><select id="route-distribution"><option value="">Choisir une distribution</option>@foreach($distributions as $distribution)<option value="{{ $distribution->id }}">{{ $distribution->reference }} → {{ $distribution->store?->name }}{{ $distribution->origin_latitude === null || $distribution->origin_longitude === null ? ' (départ sans coordonnées)' : '' }}</option>@endforeach</select><span class="map-muted">Le point de vente associé sert de destination.</span></div>
                <button type="button" id="use-current-location" class="map-button">Utiliser ma position</button>
                <div class="flex flex-wrap gap-2"><button type="button" id="calculate-route" class="map-button map-button-primary flex-1">Calculer la distance</button><button type="button" id="reset-route" class="map-button">Effacer</button></div>
                <p id="map-status" class="map-status" role="status" aria-live="polite" hidden></p>
                <section id="route-result" class="map-result" aria-live="polite" hidden><p id="route-summary" class="text-sm font-semibold text-gray-800"></p><div class="map-result-stats"><div class="map-result-stat"><span class="map-muted">Distance routière</span><strong id="route-distance" class="mt-1 block text-lg text-gray-900"></strong></div><div class="map-result-stat"><span class="map-muted">Durée estimée</span><strong id="route-duration" class="mt-1 block text-lg text-gray-900"></strong></div></div><a id="route-navigation" class="mt-3 inline-block text-sm" href="#" target="_blank" rel="noopener noreferrer">Ouvrir le trajet dans OpenStreetMap ↗</a></section>
            </div>
            <p class="map-muted mt-4">La géolocalisation n’est demandée qu’après votre clic. Votre position n’est pas enregistrée dans NutriTrace ; elle est transmise au service de routage uniquement lorsque vous calculez un trajet. La durée est indicative, sans données de circulation en temps réel.</p>
        </aside>
    </div>

    @php
        $mappedStores = $stores->map(fn ($store) => ['id' => $store->id, 'name' => $store->name, 'reference' => $store->reference, 'address' => $store->address, 'city' => $store->city, 'country' => $store->country, 'latitude' => $store->latitude, 'longitude' => $store->longitude])->values();
        $mappedDistributions = $distributions->map(fn ($distribution) => ['id' => $distribution->id, 'reference' => $distribution->reference, 'store_id' => $distribution->store_id, 'origin_name' => $distribution->origin_name, 'origin_latitude' => $distribution->origin_latitude, 'origin_longitude' => $distribution->origin_longitude])->values();
        $missingStores = $stores->filter(fn ($store) => $store->latitude === null || $store->longitude === null);
    @endphp
    @if($stores->isEmpty())<p class="map-card p-5 text-sm text-gray-600">Vous n’avez pas encore de point de vente. Ajoutez-en un pour le voir sur la carte.</p>@elseif($missingStores->isNotEmpty())<aside class="map-no-coordinates"><strong>{{ $missingStores->count() }} point(s) de vente sans coordonnées.</strong> Ils n’apparaissent pas sur la carte et ne peuvent pas être choisis comme destination. Ajoutez leur latitude et longitude dans <a href="{{ route('workspace.resources.index', ['stores', 'modal' => 'create']) }}">la gestion des points de vente</a>.</aside>@endif
</main>

<script type="application/json" id="map-store-data">{!! json_encode($mappedStores, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="map-distribution-data">{!! json_encode($mappedDistributions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="map-config">{!! json_encode($mapConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endsection
