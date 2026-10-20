@extends('layouts.admin')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[.16em] text-green-800">Espace {{ ucfirst(auth()->user()->role) }}</p>
        <h1 class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ $config['label'] }}</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Suivez les informations et les relations de votre chaîne de traçabilité.</p>
    </div>
    @if(in_array(auth()->user()->role, $config['write_roles'], true))
        <a href="{{ route('workspace.resources.create', $resource) }}" class="inline-flex items-center gap-2 rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white shadow hover:bg-green-900"><span aria-hidden="true">ï¼‹</span> Ajouter</a>
    @endif
</div>

@if(session('status'))<div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">{{ session('status') }}</div>@endif
<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" data-list-filters class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-gray-800">
        <label class="flex min-w-[240px] flex-1 items-center gap-2 rounded-xl border border-gray-200 px-3 py-2.5 dark:border-gray-700"><span class="text-gray-400" aria-hidden="true">⌕</span><span class="sr-only">Rechercher</span><input name="q" value="{{ request('q') }}" placeholder="Rechercher dans {{ mb_strtolower($config['label']) }}..." class="w-full border-0 bg-transparent text-sm outline-none focus:ring-0"></label>
        @if(in_array('status', $config['fields'], true))
            <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Tous les statuts</option>@foreach($statusOptions as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</option>@endforeach</select>
        @endif
        @if($resource === 'products')<select name="category" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Toutes les cat&#233;gories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select>@endif
        @if($resource === 'indicators')<select name="stage" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Toutes les étapes</option>@foreach($stages as $stage)<option value="{{ $stage }}" @selected(request('stage')===$stage)>{{ $stage }}</option>@endforeach</select>@endif
        <button type="submit" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Filtrer</button>
    </form>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-900/60"><tr><th class="px-5 py-3">Élément</th><th class="px-5 py-3">Référence / relation</th><th class="px-5 py-3">Donnée principale</th><th class="px-5 py-3">Statut</th><th class="px-5 py-3">Mise à jour</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($records as $record)
                    @php
                        $title = match($resource) { 'lots' => $record->code, 'certifications' => $record->title, 'indicators' => $record->name, default => $record->name ?? $record->reference };
                        $reference = $record->reference ?? $record->certificate_number ?? $record->metric_code ?? $record->code ?? '—';
                        $detail = match($resource) { 'farms' => $record->location, 'products' => $record->category, 'lots' => $record->product?->name, 'transformations' => $record->facility, 'stores' => $record->city, 'distributions' => $record->store?->name, 'certifications' => $record->scheme, 'indicators' => $record->value.' '.$record->unit, default => '—' };
                        $status = $record->status ?? 'Mesuré';
                    @endphp
                    <tr class="hover:bg-green-50/40 dark:hover:bg-white/[0.02]">
                        <td class="px-5 py-4"><a class="font-semibold text-gray-900 hover:text-green-800 dark:text-white" data-detail-popup href="{{ route('workspace.resources.show', [$resource, $record]) }}">{{ $title }}</a><div class="mt-1 text-xs text-gray-500">{{ class_basename($record) }}</div></td>
                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $reference }}</td>
                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $detail ?: '—' }}</td>
                        <td class="px-5 py-4"><span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">{{ str_replace('_', ' ', ucfirst($status)) }}</span></td>
                        <td class="px-5 py-4 text-xs text-gray-500">{{ $record->updated_at?->format('d/m/Y') }}</td>
                        <td class="px-5 py-4 text-right"><a class="font-semibold text-green-800 hover:underline" data-detail-popup href="{{ route('workspace.resources.show', [$resource, $record]) }}">Détails →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-14 text-center"><p class="font-semibold text-gray-800 dark:text-white">Aucun résultat</p><p class="mt-1 text-sm text-gray-500">Les enregistrements créés apparaîtront ici.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$records" />
</section>
@endsection
