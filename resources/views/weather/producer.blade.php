@extends('layouts.admin')

@section('content')
<div class="weather-page" data-producer-weather data-endpoint="{{ route('producer.weather.forecast') }}" data-selected-farm="{{ $selectedFarmId }}">
    <header class="weather-header">
        <div><p class="weather-eyebrow">Espace producteur</p><h1>Météo agricole</h1><p>Prévisions pour vos exploitations et repères pratiques pour organiser vos activités.</p></div>
        <div class="weather-attributions"><a class="weather-attribution" href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Weather data by Open-Meteo.com</a><span class="weather-geocoding-attribution">Location search by <a href="https://www.geonames.org/" target="_blank" rel="noopener noreferrer">GeoNames</a></span></div>
    </header>

    @if($farms->isEmpty())
        <section class="weather-state"><h2>Aucune exploitation enregistrée</h2><p>Ajoutez une exploitation avant de consulter sa météo.</p><a href="{{ route('workspace.resources.create', 'farms') }}">Ajouter une exploitation</a></section>
    @else
        <section class="weather-toolbar">
            <label for="weather-farm">Exploitation</label>
            <select id="weather-farm" data-weather-farm>
                @foreach($farms as $farm)<option value="{{ $farm->id }}" @selected((string)$farm->id === $selectedFarmId)>{{ $farm->name }}{{ $farm->location ? ' · '.$farm->location : '' }}</option>@endforeach
            </select>
            <button type="button" data-weather-refresh><span aria-hidden="true">↻</span> Actualiser</button>
            <span class="weather-updated" data-weather-updated></span>
        </section>
        <div class="weather-message" data-weather-message role="status" aria-live="polite" hidden></div>
        <main data-weather-content aria-busy="true">
            <section class="weather-current" data-weather-current aria-label="Conditions actuelles"></section>
            <section class="weather-grid">
                <article class="weather-panel weather-chart-panel"><div class="weather-section-heading"><div><h2>Température et précipitations</h2><p>Prévisions horaires</p></div></div><div class="weather-chart" data-weather-chart></div><div class="weather-hourly" data-weather-hourly></div></article>
                <article class="weather-panel"><div class="weather-section-heading"><div><h2>Prochains 7 jours</h2><p>Prévisions quotidiennes</p></div></div><div class="weather-daily" data-weather-daily></div></article>
            </section>
            <section class="weather-panel weather-advice-panel">
                <div class="weather-section-heading"><div><h2>Conseils agricoles</h2><p>Repères indicatifs issus des prévisions, à adapter à la culture et à la parcelle.</p></div></div>
                <div class="weather-filters"><label>Priorité <select data-filter-priority><option value="all">Toutes</option><option value="high">Risque élevé</option><option value="watch">Vigilance</option><option value="info">Information</option></select></label><label>Phénomène <select data-filter-type><option value="all">Tous</option><option value="rain">Pluie</option><option value="runoff">Forte pluie</option><option value="storm">Orage</option><option value="heat">Chaleur</option><option value="wind">Vent</option><option value="humidity">Humidité</option><option value="frost">Gel</option><option value="et0">ET₀</option></select></label></div>
                <div class="weather-advice" data-weather-advice></div>
            </section>
            <section class="weather-panel"><div class="weather-section-heading"><div><h2>Alertes météo</h2><p>Phénomènes nécessitant une attention particulière.</p></div></div><div class="weather-alerts" data-weather-alerts></div></section>
            <section class="weather-panel"><div class="weather-section-heading"><div><h2>Impact sur les récoltes planifiées</h2><p>Récoltes prévues sur l’exploitation sélectionnée pendant les sept prochains jours.</p></div><a class="weather-calendar-link" href="{{ route('producer.harvest-plans.index') }}">Voir le calendrier des récoltes →</a></div><div class="weather-harvest-impacts" data-weather-impacts></div></section>
        </main>
        <p class="weather-footnote">Les valeurs présentées sont des prévisions, pas des observations sur votre parcelle. ET₀ indique une évapotranspiration de référence, pas le besoin réel d’irrigation.</p>
    @endif
</div>
@endsection
