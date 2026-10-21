@extends('layouts.admin')

@section('title', 'Détail de la distribution | NutriTrace')

@section('content')
<style>
    .distribution-timeline{position:relative;display:grid;gap:0;margin:0;padding:0;list-style:none}
    .distribution-timeline:before{position:absolute;top:23px;bottom:24px;left:19px;width:2px;background:#e4e7ec;content:""}
    .distribution-timeline-item{position:relative;display:grid;grid-template-columns:40px minmax(0,1fr);gap:14px;padding:0 0 24px}
    .distribution-timeline-marker{z-index:1;display:grid;width:40px;height:40px;place-items:center;border:2px solid #d0d5dd;border-radius:50%;background:#fff;color:#667085;font-weight:700}
    .distribution-timeline-item[data-state="completed"] .distribution-timeline-marker{border-color:#28724d;background:#28724d;color:#fff}
    .distribution-timeline-item[data-state="current"] .distribution-timeline-marker{border:3px solid #28724d;background:#ecfdf3;color:#17633e;box-shadow:0 0 0 4px #28724d1c}
    .distribution-timeline-item[data-current="true"] .distribution-timeline-marker{border-color:#175cd3;box-shadow:0 0 0 4px #eff8ff}
    .distribution-timeline-item[data-state="untracked"] .distribution-timeline-marker{border-style:dashed;background:#f9fafb}
    .distribution-timeline-content{min-width:0;padding:2px 0 0}
    .distribution-timeline-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px}
    .distribution-timeline-title{margin:0;color:#1d2939;font-size:15px;font-weight:700}
    .distribution-timeline-badge{display:inline-flex;align-items:center;border-radius:999px;background:#f2f4f7;padding:4px 9px;color:#475467;font-size:11px;font-weight:700}
    [data-state="completed"] .distribution-timeline-badge{background:#ecfdf3;color:#17633e}
    [data-state="current"] .distribution-timeline-badge{background:#eff8ff;color:#175cd3}
    .distribution-timeline-meta{margin:6px 0 0;color:#667085;font-size:13px;line-height:1.55}
</style>
<main class="mx-auto max-w-5xl space-y-6">
    <a href="{{ route('workspace.history.index') }}" class="inline-flex text-sm font-semibold text-green-800 hover:underline">← Retour à l’historique</a>
    @php($statusClass = match($record->status) { 'delivered' => 'bg-emerald-50 text-emerald-800', 'delayed' => 'bg-amber-50 text-amber-800', 'cancelled' => 'bg-rose-50 text-rose-800', 'in_transit' => 'bg-sky-50 text-sky-800', default => 'bg-slate-100 text-slate-700' })
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
    <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-6"><div><p class="text-sm font-semibold text-green-800">{{ $record->status === 'delivered' ? 'Livraison' : ($record->status === 'planned' ? 'En préparation' : 'Expédition') }}</p><h1 class="mt-1 text-3xl font-bold text-slate-900">{{ $record->reference }}</h1><p class="mt-2 text-sm text-slate-500">Créée le {{ $record->created_at?->format('d/m/Y à H:i') ?? '—' }}</p></div><span class="inline-flex rounded-full px-3 py-1.5 text-sm font-semibold {{ $statusClass }}">{{ $statusLabels[$record->status] ?? $record->status }}</span></header>
        @if($isCancelled)
            <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><strong>Cette distribution a été annulée.</strong><p class="mt-1">Le motif d’annulation n’est pas conservé dans les données de NutriTrace.@if($record->notes) Les notes disponibles sont affichées plus bas.@endif</p></div>
        @endif

        <section class="mt-7 border-b border-slate-100 pb-7" aria-labelledby="distribution-timeline-title">
            <div class="mb-5"><h2 id="distribution-timeline-title" class="text-xl font-bold text-slate-900">Parcours de la commande</h2><p class="mt-1 text-sm text-slate-600">Les événements datés proviennent des enregistrements de cette distribution.</p></div>
            <ol class="distribution-timeline">
                @foreach($timeline as $index => $step)
                    @php($stateLabel = $isCancelled && $step['state'] === 'current' ? 'Interrompue' : ($step['current'] ? ($step['state'] === 'completed' ? 'Étape actuelle · terminée' : 'Étape actuelle') : ['completed' => 'Terminée', 'current' => 'Étape actuelle', 'pending' => 'En attente', 'untracked' => 'Non suivie'][$step['state']]))
                    <li class="distribution-timeline-item" data-state="{{ $step['state'] }}" data-current="{{ $step['current'] ? 'true' : 'false' }}">
                        <span class="distribution-timeline-marker" aria-hidden="true">{{ $step['state'] === 'completed' ? '✓' : $index + 1 }}</span>
                        <div class="distribution-timeline-content">
                            <div class="distribution-timeline-head"><h3 class="distribution-timeline-title">{{ $step['label'] }}</h3><span class="distribution-timeline-badge">{{ $stateLabel }}</span></div>
                            <p class="distribution-timeline-meta">
                                @if($step['date']){{ $step['date']->format('d/m/Y à H:i') }}@else Aucune date enregistrée pour cette étape.@endif
                                @if($step['actor'])<span> · Responsable : {{ $step['actor']->name }}</span>@else<span> · Responsable non enregistré</span>@endif
                                @if($step['state'] === 'untracked')<br>Cette étape n’est pas historisée par le système actuel.@endif
                                @if($step['state'] === 'completed' && ! $step['recorded'] && $index > 0)<br>Étape considérée comme dépassée d’après le statut actuel; sa date précise n’est pas disponible.@endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
            @if($statusChanges->count() > 1)
                <section class="mt-2 rounded-xl bg-slate-50 p-4"><h3 class="text-sm font-bold text-slate-800">Changements de statut enregistrés</h3><ol class="mt-3 space-y-3">
                    @foreach($statusChanges->slice(1) as $change)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-l-2 border-green-700 pl-3 text-sm"><span class="font-semibold text-slate-800">{{ $statusLabels[$change->from_status] ?? '—' }} → {{ $statusLabels[$change->to_status] ?? $change->to_status }}</span><span class="text-slate-500">{{ $change->changed_at?->format('d/m/Y à H:i') ?? 'Date non enregistrée' }} · {{ $change->actor?->name ?? 'Responsable non enregistré' }}</span></li>
                    @endforeach
                </ol></section>
            @endif
        </section>

        <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                'Produit' => $record->lot?->product?->name,
                'Référence produit' => $record->lot?->product?->reference,
                'Numéro du lot' => $record->lot?->code,
                'Quantité expédiée' => $record->quantity_shipped !== null ? number_format((float) $record->quantity_shipped, 3, ',', ' ').' '.$record->unit : null,
                'Quantité livrée' => $record->quantity_delivered !== null ? number_format((float) $record->quantity_delivered, 3, ',', ' ').' '.$record->unit : null,
                'Point de vente destinataire' => $record->store?->name,
                'Adresse du point de vente' => $record->store?->address,
                'Ville' => $record->store?->city,
                'Contact' => $record->store?->contact_name,
                'Téléphone' => $record->store?->phone,
                'Lieu de départ' => $record->origin_name,
                'Expédiée le' => $record->shipped_at?->format('d/m/Y à H:i'),
                'Livraison prévue le' => $record->expected_at?->format('d/m/Y à H:i'),
                'Livrée le' => $record->delivered_at?->format('d/m/Y à H:i'),
            ] as $label => $value)
                <div class="min-w-0 rounded-xl bg-slate-50 p-4"><dt class="text-xs font-medium text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm font-semibold text-slate-900">{{ filled($value) ? $value : '—' }}</dd></div>
            @endforeach
        </dl>
        @if($record->notes)<section class="mt-5 rounded-xl border border-slate-200 p-4"><h2 class="text-sm font-semibold text-slate-700">Notes</h2><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $record->notes }}</p></section>@endif
        <p class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Les événements antérieurs à l’activation du journal ne peuvent pas être reconstitués. Les anciennes opérations n’affichent que les dates déjà enregistrées dans leur fiche.</p>
    </section>
</main>
@endsection
