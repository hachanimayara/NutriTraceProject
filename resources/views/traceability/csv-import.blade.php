@extends('layouts.admin')

@section('title', 'Importer un CSV · '.$config['label'])

@section('content')
<main class="mx-auto max-w-7xl space-y-6">
    <nav class="text-sm text-gray-500"><a class="text-green-800 underline" href="{{ route('workspace.resources.index', $resource) }}">{{ $config['label'] }}</a> / Import CSV</nav>
    <header><h1 class="text-3xl font-semibold text-gray-900">Importer un fichier CSV</h1><p class="mt-2 text-sm text-gray-600">Le fichier est analysé et prévisualisé. Rien n’est enregistré avant votre vérification et l’envoi du formulaire de création.</p></header>
    @if(session('status'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <form method="post" action="{{ route('workspace.resources.import.analyze', $resource) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">
            @csrf
            <label class="min-w-[250px] flex-1 text-sm font-semibold text-gray-700">Fichier CSV (UTF-8, virgule ou point-virgule · 1 Mo maximum)
                <input class="mt-2 block w-full rounded-xl border border-gray-300 p-3" type="file" name="file" accept=".csv,.txt,text/csv,text/plain" required>
            </label>
            <button class="rounded-xl bg-green-800 px-5 py-3 font-semibold text-white" type="submit">Analyser le fichier</button>
            <a class="rounded-xl border border-gray-200 px-5 py-3 font-semibold text-gray-800" href="{{ route('workspace.resources.import-template', $resource) }}">Télécharger le modèle</a>
        </form>
    </section>

    @if($preview)
        @if($preview['unknown'])<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Colonnes ignorées :</strong> {{ implode(', ', $preview['unknown']) }}. Seules les colonnes reconnues seront préremplies.</div>@endif
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <header class="border-b border-gray-200 p-5"><h2 class="font-semibold text-gray-900">Prévisualisation · {{ count($preview['rows']) }} ligne(s)</h2><p class="mt-1 text-sm text-gray-500">Choisissez une ligne pour la charger dans le formulaire de création existant.</p></header>
            <div class="divide-y divide-gray-100">
                @foreach($preview['rows'] as $index => $row)
                    <article class="grid gap-4 p-5 lg:grid-cols-[100px_1fr_auto]">
                        <div><strong>Ligne {{ $row['line'] }}</strong><span class="mt-1 block text-xs {{ $row['issues'] ? 'text-amber-800' : 'text-green-800' }}">{{ $row['status'] }}</span></div>
                        <div class="min-w-0">
                            @if($row['data'])<dl class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($row['data'] as $field => $value)<div class="min-w-0 text-sm"><dt class="text-xs text-gray-500">{{ $field }}</dt><dd class="break-words font-medium text-gray-900">{{ is_array($value) ? implode(', ', $value) : $value }}</dd></div>@endforeach</dl>@else<p class="text-sm text-red-700">Aucune donnée reconnue dans cette ligne.</p>@endif
                            @if($row['issues'])<ul class="mt-3 list-inside list-disc space-y-1 text-xs text-amber-900">@foreach($row['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@endif
                        </div>
                        <form method="post" action="{{ route('workspace.resources.import-row', $resource) }}" class="self-start">@csrf<input type="hidden" name="row" value="{{ $index }}"><button class="rounded-lg border border-green-800 px-4 py-2 text-sm font-semibold text-green-800 hover:bg-green-50" type="submit" @disabled(!$row['data'])>Préremplir le formulaire</button></form>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
