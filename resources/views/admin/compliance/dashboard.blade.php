@extends('layouts.admin')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div><p class="text-xs font-semibold uppercase tracking-[.16em] text-green-800">Administration · Conformité</p><h1 class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">Contrôle de conformité environnementale</h1><p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">Contrôles déterministes, preuves consultables et décisions administratives avec historique. Les signaux demandent une vérification humaine et ne concluent pas à une fraude.</p></div>
    <div class="flex flex-wrap items-center gap-3"><a class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-green-800" href="{{ route('admin.compliance.certifications') }}">Consulter les certifications</a><form method="post" action="{{ route('admin.compliance.analyze') }}">@csrf<button class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white hover:bg-green-900">Lancer les contrôles</button></form></div>
</div>
@if(session('status'))<div class="mb-5 rounded-xl bg-green-50 p-4 text-sm text-green-900">{{ session('status') }}</div>@endif
<div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Certifications valides', $metrics['valid_certifications'], route('admin.compliance.certifications', ['status'=>'valid']), 'Dates non expirées et statut validé'],
        ['Expirées', $metrics['expired_certifications'], route('admin.compliance.certifications', ['status'=>'expired']), 'Statut ou date à vérifier'],
        ['Bientôt expirées', $metrics['expiring_certifications'], route('admin.compliance.certifications', ['status'=>'expiring']), 'Dans les '.$warningDays.' prochains jours'],
        ['Anomalies ouvertes', $metrics['open_alerts'], route('admin.compliance.alerts'), 'Critiques : '.$metrics['critical_alerts']],
        ['Priorité critique', $metrics['critical_alerts'], route('admin.compliance.alerts', ['severity'=>'critical']), 'Justification conservée'],
        ['Dossiers incomplets', $metrics['incomplete_certifications'], route('admin.compliance.certifications', ['status'=>'incomplete']), 'Pièce requise par la configuration'],
        ['Actions en retard', $metrics['overdue_actions'], route('admin.compliance.actions', ['overdue'=>1]), 'Échéance dépassée'],
    ] as [$label,$value,$url,$hint])
        <a href="{{ $url }}" class="rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-green-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($value,0,',',' ') }}</p><p class="mt-1 text-xs text-gray-500">{{ $hint }}</p></a>
    @endforeach
</div>
<div class="grid gap-5 xl:grid-cols-[.8fr_1.2fr]">
    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><div class="mb-4 flex items-center justify-between"><h2 class="font-semibold">Anomalies par règle</h2><a class="text-sm font-semibold text-green-800" href="{{ route('admin.compliance.alerts') }}">Tout voir</a></div>
        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Par type d’anomalie</p>@forelse($byType as $item)<div class="flex items-center justify-between border-t border-gray-100 py-3 text-sm dark:border-gray-800"><span>{{ str($item->code)->replace('_',' ')->title() }}</span><strong>{{ $item->total }}</strong></div>@empty<p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500">Aucun signal ouvert. Lancez un contrôle pour analyser les données actuelles.</p>@endforelse
        <p class="mb-2 mt-5 text-xs font-bold uppercase tracking-wide text-gray-500">Par étape concernée</p>@forelse($byStage as $stage=>$total)<div class="flex items-center justify-between border-t border-gray-100 py-3 text-sm dark:border-gray-800"><span>{{ $stage }}</span><strong>{{ $total }}</strong></div>@empty<p class="text-sm text-gray-500">Aucune étape signalée.</p>@endforelse
    </section>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><div class="mb-4 flex items-center justify-between"><h2 class="font-semibold">Dossiers à traiter</h2><a class="text-sm font-semibold text-green-800" href="{{ route('admin.compliance.alerts') }}">Centre des anomalies</a></div>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">@forelse($recent as $alert)<a href="{{ route('admin.compliance.alerts.show',$alert) }}" class="flex flex-wrap items-center justify-between gap-3 py-3"><span><strong class="block text-sm text-gray-900 dark:text-white">{{ $alert->title }}</strong><small class="text-gray-500">{{ $alert->priority_reason ?: $alert->explanation }}</small></span><span class="rounded-full px-3 py-1 text-xs font-semibold {{ $alert->severity==='critical'?'bg-red-50 text-red-800':($alert->severity==='warning'?'bg-amber-50 text-amber-800':'bg-blue-50 text-blue-800') }}">{{ ucfirst($alert->severity) }}</span></a>@empty<p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500">Aucune anomalie à traiter.</p>@endforelse</div>
    </section>
</div>

@endsection
