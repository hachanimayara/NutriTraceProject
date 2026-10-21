@extends('layouts.admin')

@section('title', 'Historique | NutriTrace')

@section('content')
<style>
    .history-filter-grid{display:grid;grid-template-columns:minmax(220px,2fr) repeat(2,minmax(145px,1fr)) minmax(250px,1.5fr) auto;align-items:end;gap:14px}
    .history-filter-grid label{display:block;min-width:0}
    .history-filter-grid label>span{display:block;margin-bottom:6px;font-size:12px;font-weight:650;color:#344054}
    .history-filter-grid input,.history-filter-grid select{display:block;width:100%;min-width:0;min-height:44px;padding:0 12px;border:1px solid #d0d5dd;border-radius:10px;background:#fff;color:#1d2939;font:inherit;font-size:14px;outline:none}
    .history-filter-grid input:focus,.history-filter-grid select:focus{border-color:#28724d;box-shadow:0 0 0 3px #28724d1c}
    .history-date-range,.history-filter-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .history-filter-actions button,.history-filter-actions a{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border-radius:10px;padding:0 14px;font-size:14px;font-weight:650;text-decoration:none;white-space:nowrap}
    .history-filter-actions button{border:0;background:#087338;color:#fff;cursor:pointer}
    .history-filter-actions a{border:1px solid #d0d5dd;color:#344054;background:#fff}
    @media(max-width:1100px){.history-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.history-filter-search{grid-column:1/-1}.history-date-range{grid-column:1/-1}.history-filter-actions{grid-column:1/-1;grid-template-columns:minmax(130px,1fr) auto;justify-content:start}}
    @media(max-width:560px){.history-filter-grid{grid-template-columns:minmax(0,1fr)}.history-filter-search,.history-date-range,.history-filter-actions{grid-column:auto}.history-date-range{grid-template-columns:repeat(2,minmax(0,1fr))}.history-filter-actions{grid-template-columns:minmax(0,1fr) auto}}
</style>
<main class="mx-auto max-w-[1500px] space-y-6 px-1 sm:px-2">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-semibold text-green-800">Espace distributeur</p><h1 class="mt-1 text-3xl font-bold text-slate-900">Historique</h1><p class="mt-2 text-sm text-slate-600">Retrouvez vos expéditions et livraisons enregistrées.</p></div>
        <a href="{{ route('workspace.resources.index', 'distributions') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Gérer les distributions</a>
    </header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Résumé de l’historique">
        @foreach([['Opérations enregistrées', $stats['total'], 'Toutes vos distributions'], ['Livraisons effectuées', $stats['delivered'], 'Statut livré'], ['Expéditions enregistrées', $stats['shipped'], 'Avec une date d’expédition'], ['À suivre', $stats['attention'], 'Distributions en retard']] as [$label, $value, $hint])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-600">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($value, 0, ',', ' ') }}</p><p class="mt-1 text-xs text-slate-500">{{ $hint }}</p></article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('workspace.history.index') }}" class="history-filter-grid border-b border-slate-100 p-4">
            <label class="history-filter-search"><span>Recherche</span><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Référence, produit ou lot"></label>
            <label><span>Type</span><select name="type"><option value="">Tous</option><option value="preparation" @selected(($filters['type'] ?? '') === 'preparation')>En préparation</option><option value="expedition" @selected(($filters['type'] ?? '') === 'expedition')>Expédition</option><option value="livraison" @selected(($filters['type'] ?? '') === 'livraison')>Livraison</option></select></label>
            <label><span>Statut</span><select name="status"><option value="">Tous</option>@foreach($statusLabels as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label>
            <div class="history-date-range"><label><span>Du</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label><label><span>Au</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label></div>
            <div class="history-filter-actions"><button type="submit">Filtrer</button><a href="{{ route('workspace.history.index') }}" aria-label="Réinitialiser les filtres" title="Réinitialiser">↺</a></div>
        </form>

        @if($records->count())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[950px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Opération</th><th class="px-5 py-3">Référence</th><th class="px-5 py-3">Produit / lot</th><th class="px-5 py-3">Destinataire</th><th class="px-5 py-3">Quantité</th><th class="px-5 py-3">Statut</th><th class="px-5 py-3">Détails</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach($records as $record)
                        @php($statusClass = match($record->status) { 'delivered' => 'bg-emerald-50 text-emerald-800', 'delayed' => 'bg-amber-50 text-amber-800', 'cancelled' => 'bg-rose-50 text-rose-800', 'in_transit' => 'bg-sky-50 text-sky-800', default => 'bg-slate-100 text-slate-700' })
                        <tr class="hover:bg-slate-50/70"><td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ ($record->delivered_at ?? $record->shipped_at ?? $record->created_at)?->format('d/m/Y H:i') ?? '—' }}</td><td class="px-5 py-4 font-medium text-slate-800">{{ $record->status === 'delivered' ? 'Livraison' : ($record->status === 'planned' ? 'En préparation' : ($record->shipped_at ? 'Expédition' : 'Distribution')) }}</td><td class="px-5 py-4 font-semibold text-slate-900">{{ $record->reference }}</td><td class="px-5 py-4"><div class="font-medium text-slate-800">{{ $record->lot?->product?->name ?? 'Produit indisponible' }}</div><div class="text-xs text-slate-500">Lot {{ $record->lot?->code ?? '—' }}</div></td><td class="px-5 py-4 text-slate-700">{{ $record->store?->name ?? 'Point de vente indisponible' }}</td><td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ number_format((float) ($record->quantity_delivered ?? $record->quantity_shipped), 3, ',', ' ') }} {{ $record->unit }}</td><td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabels[$record->status] ?? $record->status }}</span></td><td class="px-5 py-4"><a class="font-semibold text-green-800 underline-offset-2 hover:underline" href="{{ route('workspace.history.show', $record) }}">Voir les détails</a></td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $records->links() }}</div>
        @else
            <div class="px-6 py-14 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-green-50 text-xl text-green-800">↺</div><h2 class="mt-4 text-lg font-semibold text-slate-900">{{ $hasHistory ? 'Aucun résultat pour ces filtres' : 'Aucune opération enregistrée' }}</h2><p class="mt-1 text-sm text-slate-600">{{ $hasHistory ? 'Essayez de modifier ou de réinitialiser votre recherche.' : 'Vos distributions apparaîtront ici dès leur enregistrement.' }}</p>@if($hasHistory)<a href="{{ route('workspace.history.index') }}" class="mt-4 inline-flex rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Réinitialiser les filtres</a>@endif</div>
        @endif
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">L’application conserve le statut actuel de chaque distribution, sans journal des anciens changements de statut.</p>
    </section>
</main>
@endsection
