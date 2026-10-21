@extends('layouts.admin')

@section('content')
@php
    $isProducts = $resource === 'products';
    $title = $isProducts ? 'Produits' : 'Exploitations agricoles';
    $singular = $isProducts ? 'produit' : 'exploitation';
    $showModal = request('modal') === 'create' || $modalRecord || $errors->any();
@endphp
<style>
    .producer-page{--p-green:#2d6a4f;--p-deep:#173f30;--p-border:#e0e8e2;--p-muted:#718078;color:#17231f}
    .producer-page .p-card{border:1px solid var(--p-border);border-radius:18px;background:#fff}
    .producer-page .p-stat{min-height:92px;padding:18px 20px;display:flex;align-items:center;gap:15px}
    .producer-page .p-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;font-size:20px}
    .producer-page .p-icon.green{background:#e8f5ec;color:#2d6a4f}.producer-page .p-icon.blue{background:#edf7fa;color:#347987}.producer-page .p-icon.amber{background:#fff6e6;color:#a66c13}
    .producer-page .p-icon svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:1.7}
    .producer-page table th{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#738078;background:#f7f9f7}
    .producer-page table td,.producer-page table th{padding:16px 20px;border-bottom:1px solid #edf1ed;white-space:nowrap}
    .producer-page .p-status{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:6px 11px;background:#e9f5ee;color:#276b4e;font-size:12px;font-weight:700}
    .producer-page .p-status.review{background:#fff4df;color:#a15d00}.producer-page .p-status.draft{background:#fff4df;color:#a15d00}
    .producer-page .p-action{display:inline-grid;place-items:center;width:34px;height:34px;border:1px solid #e4eae5;border-radius:10px;color:#65736b}
    .producer-page .p-action:hover{background:#eff7f1;color:var(--p-green)}
    #producer-record-modal{position:fixed;inset:0;margin:auto;box-sizing:border-box;width:min(920px,calc(100vw - 32px));max-width:none;height:min(840px,calc(100dvh - 32px));max-height:calc(100dvh - 32px);padding:0;border:1px solid #e0e8e2;border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 30px 100px #12261b40;color:#1f2937}
    #producer-record-modal[open]{display:block}
    #producer-record-modal::backdrop{background:rgba(16,35,29,.38);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}
    #producer-record-modal form{display:flex;flex-direction:column;width:100%;height:100%;max-height:100%;min-height:0}
    #producer-record-modal .p-modal-head,#producer-record-modal .p-modal-foot{position:relative;flex:none;background:#fff;z-index:2;padding:18px 24px}
    #producer-record-modal .p-modal-head{border-bottom:1px solid #e5e9e5}#producer-record-modal .p-modal-foot{border-top:1px solid #e5e9e5}
    #producer-record-modal .p-modal-body{padding:22px 24px;overflow-y:auto;overscroll-behavior:contain}
    #producer-record-modal .p-field{min-width:0}
    #producer-record-modal .p-field label{display:block;margin-bottom:8px;font-size:13px;line-height:1.35;font-weight:700;color:#26352d}
    #producer-record-modal .p-field input:not([type=file]),#producer-record-modal .p-field select,#producer-record-modal .p-field textarea{display:block;box-sizing:border-box;width:100%;min-height:49px;border:1px solid #dce3de;border-radius:12px;padding:12px 14px;background:#fff;color:#26352d;font-size:15px;line-height:1.4;outline:none}
    #producer-record-modal .p-field input[type=file]{display:block;box-sizing:border-box;width:100%;min-height:49px;border:1px solid #dce3de;border-radius:12px;padding:12px 14px;background:#fff;color:#26352d;font-size:14px}
    #producer-record-modal .p-field select[multiple]{min-height:96px}
    #producer-record-modal .p-field .flex input{width:auto;flex:1}
    #producer-record-modal .p-field .flex select{width:auto;flex:0 0 78px}
    #producer-record-modal .p-field textarea{min-height:92px;resize:vertical}
    #producer-record-modal .p-field input:focus,#producer-record-modal .p-field select:focus,#producer-record-modal .p-field textarea:focus{border-color:#2d6a4f;box-shadow:0 0 0 3px #2d6a4f18}
    #producer-record-modal .p-field input::placeholder,#producer-record-modal .p-field textarea::placeholder{color:#9aa59e}
    #producer-record-modal .p-note{border-radius:14px;background:#eaf5ee;padding:16px;color:#285b43}
    @media(max-width:640px){#producer-record-modal{width:calc(100vw - 16px);height:calc(100dvh - 16px);max-height:calc(100dvh - 16px);border-radius:14px}#producer-record-modal .p-modal-head,#producer-record-modal .p-modal-foot{padding:14px 16px}#producer-record-modal .p-modal-body{padding:18px 16px}#producer-record-modal .p-modal-foot{justify-content:stretch}#producer-record-modal .p-modal-foot button{flex:1;padding:11px 8px;font-size:12px}}
</style>
<main class="producer-page space-y-5">
    <nav class="flex items-center gap-2 text-xs text-gray-500"><a href="{{ auth()->user()->getDashboardUrl() }}" class="hover:text-green-800">Tableau de bord</a><span>&rsaquo;</span><span>{{ $title }}</span></nav>
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-3xl font-semibold tracking-tight text-gray-900">{{ $title }}</h1><p class="mt-2 text-sm text-gray-500">{!! $isProducts ? 'G&#233;rez le catalogue et reliez chaque produit &#224; son exploitation.' : 'Suivez les origines, pratiques et producteurs responsables.' !!}</p></div>
        <div class="flex gap-3"><a href="{{ route('workspace.resources.export', $resource) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-50"><span aria-hidden="true"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v4h14v-4"/></svg></span> Exporter</a><a href="{{ route('workspace.resources.index', ['resource' => $resource, 'modal' => 'create']) }}" class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white">＋ Ajouter {{ $isProducts ? 'un produit' : 'une exploitation' }}</a></div>
    </header>
    <div class="-mt-3 flex justify-end"><button type="button" data-open-csv-import="{{ route('workspace.resources.import', ['resource' => $resource, 'popup' => 1]) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">Importer CSV</button></div>

    @if(session('status'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-900" role="status">{{ session('status') }}</div>@endif
    @if($errors->has('associated_products'))<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('associated_products') }}</div>@endif

    <section class="grid gap-4 md:grid-cols-3" aria-label="Statistiques {{ mb_strtolower($title) }}">
        @foreach($stats as $stat)
            <article class="p-card p-stat"><span class="p-icon {{ $stat['tone'] }}"><svg viewBox="0 0 24 24"><path d="M4 19h16M6 16V9m4 7V5m4 11v-4m4 4V7"/><path d="M3 19h18"/></svg></span><div><strong class="block text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</strong><span class="text-sm text-gray-500">{{ $stat['label'] }}</span></div></article>
        @endforeach
    </section>

    <section class="p-card overflow-hidden">
        <form method="get" data-list-filters class="flex flex-wrap gap-3 border-b border-gray-100 p-4">
            <label class="flex min-w-[230px] flex-1 items-center gap-3 rounded-xl border border-gray-200 px-4 py-3"><span class="text-xl text-gray-400"><svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 4.5 4.5"/></svg></span><span class="sr-only">Rechercher</span><input class="w-full border-0 bg-transparent p-0 text-sm outline-none focus:ring-0" name="q" value="{{ request('q') }}" placeholder="Rechercher {{ $isProducts ? 'produit' : 'exploitation' }}..."></label>
            <button type="submit" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-50"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M7 12h10m-7 6h4"/><circle cx="8" cy="6" r="1.5" fill="white"/><circle cx="15" cy="12" r="1.5" fill="white"/><circle cx="12" cy="18" r="1.5" fill="white"/></svg>Filtres</button>
            @if($isProducts)<select name="category" aria-label="Filtrer par catégorie" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm"><option value="">Toutes les cat&#233;gories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select>@endif
            @if(in_array('status', $config['fields'], true))<select name="status" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm"><option value="">Tous les statuts</option>@foreach($statusOptions as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</option>@endforeach</select>@endif
        </form>
        <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-sm"><thead><tr>
            @if($isProducts)<th>Produit</th><th>Référence</th><th>Catégorie</th><th>Exploitation</th><th>Statut</th>
            @else<th>Exploitation</th><th>Localisation</th><th>Agriculture</th><th>Producteur</th><th>Statut</th>@endif
            <th class="text-right">Détails</th>
        </tr></thead><tbody>
            @forelse($records as $record)
                <tr class="hover:bg-green-50/30">
                    @if($isProducts)<td><a class="font-semibold text-gray-900 hover:text-green-800" data-detail-popup href="{{ route('workspace.resources.show', [$resource,$record]) }}">{{ $record->name }}</a></td><td class="text-gray-600">{{ $record->reference }}</td><td class="text-gray-600">{{ $record->category }}</td><td class="text-gray-600">{{ $record->farm?->name ?? 'À associer' }}</td>
                    @else<td><a class="font-semibold text-gray-900 hover:text-green-800" data-detail-popup href="{{ route('workspace.resources.show', [$resource,$record]) }}">{{ $record->name }}</a></td><td class="text-gray-600">{{ $record->location }}, {{ $record->country }}</td><td class="text-gray-600">{{ $record->farming_method ?: 'Non renseignée' }}</td><td class="text-gray-600">{{ $record->owner?->name ?? auth()->user()->name }}</td>@endif
                    <td><span class="p-status {{ in_array($record->status,['review','draft']) ? ($record->status==='draft'?'draft':'review') : '' }}"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>{!! mb_encode_numericentity(match($record->status){'active'=>'Active','review'=>'&#192; v&#233;rifier','draft'=>'Brouillon','published'=>'Publi&#233;','archived'=>'Archiv&#233;',default=>ucfirst($record->status)}, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</span></td>
                    <td><div class="flex justify-end gap-2"><a class="p-action" aria-label="Voir {{ $record->name }}" title="Voir" data-detail-popup href="{{ route('workspace.resources.show',[$resource,$record]) }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg></a><a class="p-action" aria-label="Modifier {{ $record->name }}" title="Modifier" href="{{ route('workspace.resources.edit',[$resource,$record]) }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m14 5 5 5M4 20l4.2-.8L19 8.4 15.6 5 4.8 15.8 4 20Z"/></svg></a><form method="post" action="{{ route('workspace.resources.destroy',[$resource,$record]) }}" data-confirm-action="Supprimer cet élément ?">@csrf @method('DELETE')<button class="p-action text-red-600" aria-label="Supprimer {{ $record->name }}" title="Supprimer"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m3 0-.8 14H5.8L5 6m4 4v6m6-6v6"/></svg></button></form></div></td>
                </tr>
            @empty<tr><td colspan="6" class="px-6 py-16 text-center"><strong class="text-gray-800">Aucun résultat</strong><p class="mt-1 text-sm text-gray-500">Vos {{ $isProducts ? 'produits' : 'exploitations' }} apparaîtront ici après leur enregistrement.</p></td></tr>@endforelse
        </tbody></table></div>
        <x-pagination :paginator="$records" />
    </section>
</main>

@if($showModal)
<dialog id="producer-record-modal">
    <form method="post" enctype="multipart/form-data" action="{{ $modalRecord ? route('workspace.resources.update',[$resource,$modalRecord]) : route('workspace.resources.store',$resource) }}">
        @csrf @if($modalRecord) @method('PUT') @endif
        <input type="hidden" name="status" value="{{ old('status',$modalRecord?->status ?? ($isProducts ? 'draft' : 'active')) }}">
        <header class="p-modal-head flex items-center justify-between"><h2 class="text-xl font-semibold">{{ $modalRecord ? 'Modifier' : 'Ajouter' }} {{ $isProducts ? 'un produit' : 'une exploitation' }}</h2><button type="button" data-close-producer-modal class="grid h-11 w-11 place-items-center rounded-xl border border-gray-200 text-xl text-gray-500 hover:bg-gray-50" aria-label="Fermer">&times;</button></header>
        <div class="p-modal-body space-y-6">
            <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-green-50 text-sm font-bold text-green-800">01</span><div><h3 class="font-semibold">Informations principales</h3><p class="mt-1 text-xs text-gray-500">Renseignez les données nécessaires à l’identification.</p></div></div>
            @if($isProducts)
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="p-field"><label for="product-name">Nom du produit *</label><input id="product-name" name="name" required maxlength="160" value="{{ old('name',$modalRecord?->name) }}" placeholder="Tomates anciennes">@error('name')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field"><label for="product-reference">Référence *</label><input id="product-reference" name="reference" required maxlength="80" value="{{ old('reference',$modalRecord?->reference) }}" placeholder="PRD-2025-042">@error('reference')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field"><label for="product-category">Catégorie *</label><select id="product-category" name="category" required><option value="">Sélectionner une catégorie</option>@foreach(['Légumes','Fruits','Céréales','Légumineuses','Boissons','Produits laitiers','Viandes','Épicerie','Autre'] as $category)<option value="{{ $category }}" @selected(old('category',$modalRecord?->category)===$category)>{{ $category }}</option>@endforeach</select>@error('category')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field"><label for="product-farm">Exploitation associ&eacute;e *</label><select id="product-farm" name="farm_id" required><option value="">S&eacute;lectionner une exploitation</option>@foreach($options['farms'] as $farm)<option value="{{ $farm->id }}" @selected((string)old('farm_id',$modalRecord?->farm_id)===(string)$farm->id)>{{ $farm->name }}</option>@endforeach</select>@error('farm_id')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror @if($options['farms']->isEmpty())<small class="mt-1 block text-gray-500">Cr&eacute;ez d’abord une exploitation avant d’ajouter un produit.</small>@endif</div>
                    <div class="p-field"><label for="product-origin">Origine</label><input id="product-origin" name="origin" maxlength="190" value="{{ old('origin',$modalRecord?->origin) }}" placeholder="Drôme, France">@error('origin')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field"><label for="product-quantity">Quantité produite</label><div class="flex gap-2"><input id="product-quantity" name="quantity" type="number" min="0" step="0.001" value="{{ old('quantity',$modalRecord?->quantity) }}" placeholder="1 200" class="flex-1"><select name="unit" aria-label="Unité" class="max-w-28"><option value="kg" @selected(old('unit',$modalRecord?->unit)==='kg')>kg</option><option value="L" @selected(old('unit',$modalRecord?->unit)==='L')>L</option><option value="unité" @selected(old('unit',$modalRecord?->unit)==='unité')>unité</option></select></div>@error('quantity')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field sm:col-span-2"><label for="product-image">Photo du produit (JPG, PNG ou WebP · 5 Mo maximum)</label><input id="product-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">@if($modalRecord?->image_name)<small class="mt-1 block text-gray-500">Image actuelle : {{ $modalRecord->image_name }}. Choisissez une nouvelle image pour la remplacer.</small><label class="mt-2 flex items-center gap-2 text-sm font-normal"><input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-green-800"> Supprimer l’image actuelle</label>@endif @error('image')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field sm:col-span-2"><label for="product-description">Description et notes</label><textarea id="product-description" name="description" rows="4" maxlength="5000" placeholder="Ajoutez les informations utiles...">{{ old('description',$modalRecord?->description) }}</textarea>@error('description')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                </div>
                <aside class="p-note"><strong>◇ &nbsp;Relation : Exploitation d’origine</strong><p class="mt-1 text-xs">La sélection relie ce produit à son exploitation et sera visible sur sa fiche de traçabilité.</p></aside>
            @else
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="p-field sm:col-span-2"><label for="farm-name">Nom / Référence *</label><input id="farm-name" name="name" required maxlength="160" value="{{ old('name',$modalRecord?->name) }}" placeholder="Nouvelle exploitation">@error('name')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror<input type="hidden" name="reference" value="{{ old('reference',$modalRecord?->reference ?? 'EXP-'.strtoupper(\Illuminate\Support\Str::random(8))) }}"></div>
                    <div class="p-field"><label for="farm-status">Statut *</label><select id="farm-status" name="status" required><option value="active" @selected(old('status',$modalRecord?->status ?? 'active')==='active')>Active</option><option value="review" @selected(old('status',$modalRecord?->status)==='review')>&#192; v&#233;rifier</option></select>@error('status')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field"><label>Responsable *</label><select disabled><option>{{ auth()->user()->name }}</option></select><input type="hidden" name="responsible" value="{{ auth()->id() }}"><small class="mt-1 block text-gray-500">Votre compte sera enregistré comme responsable.</small></div>
                    <div class="p-field"><label for="farm-date">Date de cr&eacute;ation</label><input id="farm-date" type="date" name="started_at" autocomplete="off" value="{{ old('started_at',$modalRecord?->started_at?->format('Y-m-d')) }}">@error('started_at')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field sm:col-span-2"><label for="farm-document">Justificatif (PDF, JPG ou PNG · 5 Mo maximum)</label><input id="farm-document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">@if($modalRecord?->document_name)<a class="mt-1 inline-block text-xs font-semibold text-green-800 underline" href="{{ route('workspace.farms.document',$modalRecord) }}">Justificatif enregistré en base : {{ $modalRecord->document_name }}</a>@endif @error('document')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field sm:col-span-2"><label for="farm-description">Description et notes</label><textarea id="farm-description" name="description" rows="3" maxlength="5000" placeholder="Ajoutez les informations utiles...">{{ old('description',$modalRecord?->description) }}</textarea>@error('description')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field"><label for="farm-location">Localisation *</label><input id="farm-location" name="location" required maxlength="190" value="{{ old('location',$modalRecord?->location) }}" placeholder="Drôme"></div>
                    <div class="p-field"><label for="farm-country">Pays *</label><input id="farm-country" name="country" required maxlength="100" value="{{ old('country',$modalRecord?->country ?? 'Tunisie') }}" placeholder="Tunisie"></div>
                    <div class="p-field"><label for="farm-method">Agriculture / pratique</label><input id="farm-method" name="farming_method" maxlength="120" value="{{ old('farming_method',$modalRecord?->farming_method) }}" placeholder="Biologique, raisonnée..."></div>
                    <div class="p-field"><label for="farm-area">Surface (hectares)</label><input id="farm-area" type="number" min="0" step="0.001" name="area_hectares" value="{{ old('area_hectares',$modalRecord?->area_hectares) }}" placeholder="12.5"></div>
                    <div class="p-field sm:col-span-2"><strong class="text-sm">Coordonnées GPS pour les prévisions météo</strong><small class="mt-1 block text-gray-500">Renseignez la latitude et la longitude exactes de l’exploitation. Vous pouvez les retrouver sur une carte.</small></div>
                    <div class="p-field"><label for="farm-latitude">Latitude</label><input id="farm-latitude" type="number" min="-90" max="90" step="0.0000001" name="latitude" value="{{ old('latitude',$modalRecord?->latitude) }}" placeholder="36.8065">@error('latitude')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="p-field"><label for="farm-longitude">Longitude</label><input id="farm-longitude" type="number" min="-180" max="180" step="0.0000001" name="longitude" value="{{ old('longitude',$modalRecord?->longitude) }}" placeholder="10.1815">@error('longitude')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                </div>
                <aside class="p-note"><strong>Relation : Exploitation et produits</strong><p class="mt-1 text-xs">Apr&egrave;s cr&eacute;ation de l’exploitation, rattachez chaque produit depuis son formulaire.</p></aside>
            @endif
        </div>
        <footer class="p-modal-foot flex flex-wrap justify-end gap-3"><button type="button" data-close-producer-modal class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-700">Annuler</button>
            @if($isProducts)<button type="submit" name="status" value="draft" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-semibold">Enregistrer en brouillon</button><button type="submit" name="status" value="published" class="rounded-xl bg-green-800 px-6 py-3 text-sm font-semibold text-white hover:bg-green-900">Enregistrer</button>
            @else<button type="submit" class="rounded-xl bg-green-800 px-6 py-3 text-sm font-semibold text-white hover:bg-green-900">{{ $modalRecord ? 'Enregistrer les modifications' : 'Enregistrer' }}</button>@endif
        </footer>
    </form>
</dialog>
<script>
(() => {
    const modal = document.getElementById('producer-record-modal');
    if (!modal) return;
    document.querySelectorAll('[data-open-producer-modal]').forEach(button => button.addEventListener('click', () => modal.showModal()));
    document.querySelectorAll('[data-close-producer-modal]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
    @if($showModal) modal.showModal(); @endif
})();
</script>
@endif
@include('traceability.partials.csv-import-dialog')
@endsection
