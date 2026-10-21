@extends('layouts.admin')

@section('content')
@php
    $title = match($resource) { 'lots' => $record->code, 'certifications' => $record->title, 'indicators' => $record->name, default => $record->name ?? $record->reference };
    $labels = ['reference'=>'Référence','location'=>'Localisation','country'=>'Pays','farming_method'=>'Mode de culture','area_hectares'=>'Surface (ha)','category'=>'Catégorie','origin'=>'Origine','quantity'=>'Quantité','unit'=>'Unité','status'=>'Statut','code'=>'Code du lot','produced_at'=>'Date de production','expires_at'=>'Date d’expiration','facility'=>'Site','performed_at'=>'Date de transformation','process_description'=>'Procédé','energy_kwh'=>'Énergie (kWh)','water_liters'=>'Eau (litres)','address'=>'Adresse','city'=>'Ville','phone'=>'Téléphone','quantity_shipped'=>'Quantité expédiée','quantity_delivered'=>'Quantité livrée','shipped_at'=>'Expédié le','expected_at'=>'Prévu le','delivered_at'=>'Livré le','scheme'=>'Référentiel','certificate_number'=>'N° certificat','issuer'=>'Organisme','issued_at'=>'Délivré le','expires_at'=>'Expire le','stage'=>'Étape','metric_code'=>'Code de mesure','value'=>'Valeur','method'=>'Méthode','measured_at'=>'Mesuré le','description'=>'Description','notes'=>'Notes','source_notes'=>'Source'];
@endphp
<div class="mb-5"><a href="{{ route('workspace.resources.index', $resource) }}" class="text-sm font-semibold text-green-800">← Retour à {{ mb_strtolower($config['label']) }}</a></div>
@if(session('status'))<div class="mb-5 rounded-xl bg-green-50 p-4 text-sm text-green-900">{{ session('status') }}</div>@endif
<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-6 dark:border-gray-800"><div><p class="text-xs font-semibold uppercase tracking-wider text-green-800">{{ $config['label'] }}</p><h1 class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h1><p class="mt-2 text-sm text-gray-500">Créé le {{ $record->created_at?->format('d/m/Y à H:i') }}</p></div>
    </div>
    @if($resource==='lots')<div class="my-6 rounded-xl bg-green-50 p-4"><p class="text-sm font-semibold text-green-950">Page publique de traçabilité</p><a class="mt-1 inline-block text-sm text-green-800 underline" href="{{ route('public.trace', $record->public_id) }}" target="_blank" rel="noopener">Ouvrir la fiche publique</a></div>@endif
    @if($resource==='certifications' && $record->document_path)<div class="my-6 rounded-xl bg-green-50 p-4"><a class="text-sm font-semibold text-green-900 underline" href="{{ route('workspace.certifications.document', $record) }}">Télécharger le justificatif privé</a></div>@endif
    <dl class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($config['fields'] as $field)
            @continue(in_array($field, ['farm_id','product_id','lot_id','store_id'], true))
            @php $value=$record->{$field}; @endphp
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900/50"><dt class="text-xs font-medium text-gray-500">{{ $labels[$field] ?? ucfirst(str_replace('_',' ',$field)) }}</dt><dd class="mt-1 whitespace-pre-line text-sm font-semibold text-gray-900 dark:text-white">{{ $value instanceof \Carbon\CarbonInterface ? $value->format('d/m/Y H:i') : ($value ?: '—') }}</dd></div>
        @endforeach
        @if($record->farm)<div class="rounded-xl bg-gray-50 p-4"><dt class="text-xs text-gray-500">Exploitation liée</dt><dd class="mt-1 text-sm font-semibold">{{ $record->farm->name }}</dd></div>@endif
        @if($record->product)<div class="rounded-xl bg-gray-50 p-4"><dt class="text-xs text-gray-500">Produit lié</dt><dd class="mt-1 text-sm font-semibold">{{ $record->product->name }}</dd></div>@endif
        @if($record->store)<div class="rounded-xl bg-gray-50 p-4"><dt class="text-xs text-gray-500">Point de vente</dt><dd class="mt-1 text-sm font-semibold">{{ $record->store->name }}</dd></div>@endif
    </dl>
    @if($resource==='certifications' && auth()->user()->isAdmin())
        <section class="mt-8 border-t border-gray-100 pt-6"><h2 class="text-lg font-semibold">Historique des contrôles et décisions</h2><div class="mt-3 divide-y divide-gray-100">@forelse($record->complianceEvents as $event)<article class="py-3"><p class="text-sm font-semibold">{{ str($event->event)->replace('.',' · ')->replace('_',' ')->title() }} <span class="font-normal text-gray-500">· {{ $event->actor?->name ?? 'Compte supprimé' }} · {{ $event->created_at->format('d/m/Y H:i') }}</span></p>@if($event->from_status || $event->to_status)<p class="text-xs text-gray-500">{{ $event->from_status ?? '—' }} → {{ $event->to_status ?? '—' }}</p>@endif @if($event->note)<p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $event->note }}</p>@endif</article>@empty<p class="py-3 text-sm text-gray-500">Aucun contrôle ou changement historisé pour cette certification.</p>@endforelse</div></section>
    @endif
    @if($resource==='transformations')<div class="mt-8 grid gap-5 md:grid-cols-2">@foreach(['input'=>'Lots entrants','output'=>'Lots sortants'] as $direction=>$label)<section class="rounded-xl border border-gray-200 p-4"><h2 class="font-semibold">{{ $label }}</h2><ul class="mt-3 space-y-2">@forelse($record->lots->where('pivot.direction',$direction) as $lot)<li class="text-sm"><a class="text-green-800 underline" href="{{ route('workspace.resources.show',['lots',$lot]) }}">{{ $lot->code }} · {{ $lot->product->name }}</a><span class="text-gray-500"> — {{ $lot->pivot->quantity }} {{ $lot->pivot->unit }}</span></li>@empty<li class="text-sm text-gray-500">Aucun lot associé.</li>@endforelse</ul></section>@endforeach</div>@endif
</section>
@endsection
