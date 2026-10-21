@extends('layouts.admin')

@section('content')
@php
    $isLots = $resource === 'lots';
    $title = $isLots ? 'Lots' : 'Transformations';
    $showModal = request('modal') === 'create' || $modalRecord || $errors->any();
    $editing = (bool) $modalRecord;
    $inputSelection = array_map('intval', (array) old('input_lots', $options['selected_inputs']));
    $outputSelection = array_map('intval', (array) old('output_lots', $options['selected_outputs']));
    $statusLabels = $isLots
        ? ['active' => 'Disponible', 'blocked' => 'Bloqué', 'completed' => 'Terminé']
        : ['recorded' => 'En cours', 'review' => 'À vérifier'];
@endphp
<style>
    .transformer-page{--t-green:#2d6a4f;--t-border:#e0e8e2;--t-muted:#748078;color:#17231f}
    .transformer-page .t-card{border:1px solid var(--t-border);border-radius:18px;background:#fff}
    .transformer-page .t-stat{min-height:92px;padding:18px 20px;display:flex;align-items:center;gap:15px}
    .transformer-page .t-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;font-size:20px}
    .transformer-page .t-icon.green{background:#e8f5ec;color:#2d6a4f}.transformer-page .t-icon.blue{background:#edf7fa;color:#347987}.transformer-page .t-icon.amber{background:#fff6e6;color:#a66c13}
    .transformer-page table th{padding:14px 20px;border-bottom:1px solid #e8ece8;background:#f7f9f7;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#738078;white-space:nowrap}
    .transformer-page table td{padding:17px 20px;border-bottom:1px solid #edf1ed;font-size:13px;white-space:nowrap}
    .transformer-page .t-badge{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:7px 11px;background:#e9f5ee;color:#276b4e;font-size:12px;font-weight:700}
    .transformer-page .t-badge.review,.transformer-page .t-badge.blocked{background:#fff4df;color:#a15d00}
    .transformer-page .t-action{display:inline-grid;place-items:center;width:34px;height:34px;border:1px solid #e4eae5;border-radius:10px;color:#65736b}
    .transformer-page .t-action:hover{background:#eff7f1;color:var(--t-green)}
    #transformer-record-modal{position:fixed;inset:0;margin:auto;box-sizing:border-box;width:min(920px,calc(100vw - 32px));height:min(840px,calc(100dvh - 32px));max-width:none;max-height:calc(100dvh - 32px);padding:0;border:1px solid #e0e8e2;border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 30px 100px #12261b40;color:#1f2937}
    #transformer-record-modal[open]{display:block}
    #transformer-record-modal::backdrop{background:rgba(16,35,29,.42);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}
    #transformer-record-modal form{display:flex;flex-direction:column;width:100%;height:100%;max-height:100%;min-height:0}
    #transformer-record-modal .t-modal-head,#transformer-record-modal .t-modal-foot{position:relative;z-index:2;flex:none;background:#fff;padding:18px 24px}
    #transformer-record-modal .t-modal-head{border-bottom:1px solid #e5e9e5}#transformer-record-modal .t-modal-foot{border-top:1px solid #e5e9e5}
    #transformer-record-modal .t-modal-body{padding:22px 24px;overflow-y:auto;overscroll-behavior:contain}
    #transformer-record-modal .t-field{min-width:0}
    #transformer-record-modal .t-field>label,#transformer-record-modal .t-field>span{display:block;margin-bottom:8px;font-size:13px;line-height:1.35;font-weight:700;color:#26352d}
    #transformer-record-modal .t-field input:not([type=checkbox]),#transformer-record-modal .t-field select,#transformer-record-modal .t-field textarea{display:block;box-sizing:border-box;width:100%;min-height:49px;border:1px solid #dce3de;border-radius:12px;padding:12px 14px;background:#fff;color:#26352d;font-size:15px;line-height:1.4;outline:none}
    #transformer-record-modal .t-field input[type=number]{appearance:textfield}
    #transformer-record-modal .t-field textarea{min-height:96px;resize:vertical}
    #transformer-record-modal .t-field input:focus,#transformer-record-modal .t-field select:focus,#transformer-record-modal .t-field textarea:focus{border-color:#2d6a4f;box-shadow:0 0 0 3px #2d6a4f18}
    #transformer-record-modal .t-field .flex input{flex:1;min-width:0}
    #transformer-record-modal .t-field .flex select{width:100px;flex:none}
    #transformer-record-modal .t-check-list{display:grid;gap:8px;max-height:190px;overflow:auto;border:1px solid #dce3de;border-radius:12px;padding:10px}
    #transformer-record-modal .t-check{display:flex;align-items:center;gap:10px;border-radius:9px;background:#f8faf8;padding:10px;color:#34433a;font-size:13px;cursor:pointer}
    #transformer-record-modal .t-check input{width:17px;height:17px;accent-color:#2d6a4f}
    #transformer-record-modal .t-note{border-radius:14px;background:#eaf5ee;padding:15px;color:#285b43}
    @media(max-width:640px){#transformer-record-modal{width:calc(100vw - 16px);height:calc(100dvh - 16px);max-height:calc(100dvh - 16px);border-radius:14px}#transformer-record-modal .t-modal-head,#transformer-record-modal .t-modal-foot{padding:14px 16px}#transformer-record-modal .t-modal-body{padding:18px 16px}#transformer-record-modal .t-modal-foot{justify-content:stretch}#transformer-record-modal .t-modal-foot button{flex:1;padding:11px 8px;font-size:12px}}
</style>

<main class="transformer-page space-y-5">
    <nav class="flex items-center gap-2 text-xs text-gray-500"><a href="{{ auth()->user()->getDashboardUrl() }}" class="hover:text-green-800">Tableau de bord</a><span>›</span><span>{{ $title }}</span></nav>
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div><p class="text-xs font-semibold uppercase tracking-[.14em] text-green-800">Espace transformateur</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">{{ $title }}</h1><p class="mt-2 text-sm text-gray-500">{{ $isLots ? 'Retracez les lots entrants, sortants et leurs filiations.' : 'Contrôlez les opérations, les rendements et les lots associés.' }}</p></div>
        <div class="flex gap-3"><a href="{{ route('workspace.resources.export', $resource) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-50"><span aria-hidden="true">↓</span> Exporter</a><a href="{{ route('workspace.resources.index', ['resource' => $resource, 'modal' => 'create']) }}" class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white">＋ Ajouter {{ $isLots ? 'un lot' : 'une transformation' }}</a></div>
    </header>
    <div class="-mt-3 flex justify-end"><button type="button" data-open-csv-import="{{ route('workspace.resources.import', ['resource' => $resource, 'popup' => 1]) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">Importer CSV</button></div>

    @if(session('status'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-900" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">Vérifiez les champs signalés dans le formulaire.</div>@endif

    <section class="grid gap-4 md:grid-cols-3" aria-label="Statistiques {{ mb_strtolower($title) }}">
        @foreach($stats as $stat)<article class="t-card t-stat"><span class="t-icon {{ $stat['tone'] }}">{{ $stat['tone']==='amber'?'⚠':($isLots?'⬡':'⤨') }}</span><div><strong class="block text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</strong><span class="text-sm text-gray-500">{{ $stat['label'] }}</span></div></article>@endforeach
    </section>

    <section class="t-card overflow-hidden">
        <form method="get" data-list-filters class="flex flex-wrap gap-3 border-b border-gray-100 p-4">
            <label class="flex min-w-[230px] flex-1 items-center gap-3 rounded-xl border border-gray-200 px-4 py-3"><span class="text-xl text-gray-400" aria-hidden="true">⌕</span><span class="sr-only">Rechercher</span><input class="w-full border-0 bg-transparent p-0 text-sm outline-none focus:ring-0" name="q" value="{{ request('q') }}" placeholder="Rechercher {{ $isLots ? 'lot' : 'transformation' }}..."></label>
            <button type="submit" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-50"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M7 12h10m-7 6h4"/><circle cx="8" cy="6" r="1.5" fill="white"/><circle cx="15" cy="12" r="1.5" fill="white"/><circle cx="12" cy="18" r="1.5" fill="white"/></svg>Filtres</button>
            <select name="status" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm"><option value="">Tous les statuts</option>@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</option>@endforeach</select>
        </form>
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead><tr>
            @if($isLots)<th>N° de lot</th><th>Produit</th><th>Transformation</th><th>Quantité</th><th>Statut</th>
            @else<th>Référence</th><th>Opération</th><th>Produit</th><th>Rendement</th><th>Statut</th>@endif
            <th class="text-right">Détails</th>
        </tr></thead><tbody>
            @forelse($records as $record)
                @php
                    if ($isLots) {
                        $relatedTransformation = $record->transformations->first();
                        $rowStatus = $statusLabels[$record->status] ?? ucfirst($record->status);
                        $canManage = (int) $record->created_by === (int) auth()->id();
                    } else {
                        $inputQuantity = $record->lots->where('pivot.direction','input')->sum(fn($lot)=>(float)$lot->pivot->quantity);
                        $outputQuantity = $record->lots->where('pivot.direction','output')->sum(fn($lot)=>(float)$lot->pivot->quantity);
                        $yield = $inputQuantity > 0 && $outputQuantity > 0 ? (int)round($outputQuantity / $inputQuantity * 100).'%' : '—';
                        $productNames = $record->lots->pluck('product.name')->filter()->unique()->implode(', ');
                        $rowStatus = $statusLabels[$record->status] ?? ucfirst($record->status);
                        $canManage = true;
                    }
                @endphp
                <tr class="hover:bg-green-50/30">
                    @if($isLots)<td><a class="font-semibold text-gray-900 hover:text-green-800" data-detail-popup href="{{ route('workspace.resources.show',[$resource,$record]) }}">{{ $record->code }}</a></td><td class="text-gray-600">{{ $record->product?->name ?? '—' }}</td><td class="text-gray-600">{{ $relatedTransformation?->reference ?? '—' }}</td><td class="text-gray-600">{{ number_format((float)$record->quantity,3,',',' ') }} {{ $record->unit }}</td>
                    @else<td><a class="font-semibold text-gray-900 hover:text-green-800" data-detail-popup href="{{ route('workspace.resources.show',[$resource,$record]) }}">{{ $record->reference }}</a></td><td class="text-gray-600">{{ $record->name }}</td><td class="text-gray-600">{{ $productNames ?: '—' }}</td><td class="text-gray-600">{{ $yield }}</td>@endif
                    <td><span class="t-badge {{ in_array($record->status,['review','blocked'])?'review':'' }}"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>{!! mb_encode_numericentity($rowStatus, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</span></td>
                    <td><div class="flex justify-end gap-2"><a class="t-action" aria-label="Voir" title="Voir" data-detail-popup href="{{ route('workspace.resources.show',[$resource,$record]) }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg></a>@if($canManage)<a class="t-action" aria-label="Modifier" title="Modifier" href="{{ route('workspace.resources.edit',[$resource,$record]) }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m14 5 5 5M4 20l4.2-.8L19 8.4 15.6 5 4.8 15.8 4 20Z"/></svg></a><form method="post" action="{{ route('workspace.resources.destroy',[$resource,$record]) }}" data-confirm-action="Supprimer cet élément ?">@csrf @method('DELETE')<button class="t-action text-red-600" aria-label="Supprimer" title="Supprimer"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m3 0-.8 14H5.8L5 6m4 4v6m6-6v6"/></svg></button></form>@endif</div></td>
                </tr>
            @empty<tr><td colspan="6" class="px-6 py-16 text-center"><strong class="text-gray-800">Aucun résultat</strong><p class="mt-1 text-sm text-gray-500">Vos {{ mb_strtolower($title) }} apparaîtront ici après leur enregistrement.</p></td></tr>@endforelse
        </tbody></table></div>
        <x-pagination :paginator="$records" />
    </section>
</main>

@if($showModal)
<dialog id="transformer-record-modal" aria-labelledby="transformer-modal-title">
    <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('workspace.resources.update',[$resource,$modalRecord]) : route('workspace.resources.store',$resource) }}">
        @csrf @if($editing) @method('PUT') @endif
        <header class="t-modal-head flex items-center justify-between"><h2 id="transformer-modal-title" class="text-xl font-semibold">{{ $editing?'Modifier':'Ajouter' }} {{ $isLots?'un lot':'une transformation' }}</h2><button type="button" data-close-transformer-modal class="grid h-11 w-11 place-items-center rounded-xl border border-gray-200 text-xl text-gray-500 hover:bg-gray-50" aria-label="Fermer">×</button></header>
        <div class="t-modal-body space-y-6">
            <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-green-50 text-sm font-bold text-green-800">01</span><div><h3 class="font-semibold">Informations principales</h3><p class="mt-1 text-xs text-gray-500">Renseignez les données nécessaires à la traçabilité.</p></div></div>
            @if($isLots)
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="t-field"><label for="lot-code">Nom / Référence *</label><input id="lot-code" name="code" required maxlength="80" value="{{ old('code',$modalRecord?->code) }}" placeholder="LOT-TOM-2506-A">@error('code')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="lot-status">Statut *</label><select id="lot-status" name="status" required>@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(old('status',$modalRecord?->status ?? 'active')===$value)>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</option>@endforeach</select>@error('status')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="lot-product">Produit *</label><select id="lot-product" name="product_id" required><option value="">Sélectionner un produit publié</option>@foreach($options['products'] as $product)<option value="{{ $product->id }}" @selected((string)old('product_id',$modalRecord?->product_id)===(string)$product->id)>{{ $product->name }} · {{ $product->reference }}</option>@endforeach</select>@error('product_id')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror @if($options['products']->isEmpty())<small class="mt-1 block text-gray-500">Aucun produit publié n’est disponible.</small>@endif</div>
                    <div class="t-field"><label for="lot-quantity">Quantité *</label><div class="flex gap-2"><input id="lot-quantity" name="quantity" type="number" min="0.001" step="0.001" required value="{{ old('quantity',$modalRecord?->quantity) }}" placeholder="820"><select name="unit" aria-label="Unité" required><option value="kg" @selected(old('unit',$modalRecord?->unit ?? 'kg')==='kg')>kg</option><option value="L" @selected(old('unit',$modalRecord?->unit)==='L')>L</option><option value="unité" @selected(old('unit',$modalRecord?->unit)==='unité')>unité</option></select></div>@error('quantity')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror @error('unit')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="lot-produced-at">Date de production</label><input id="lot-produced-at" name="produced_at" type="date" value="{{ old('produced_at',$modalRecord?->produced_at?->format('Y-m-d')) }}">@error('produced_at')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="lot-expires-at">Date d’expiration</label><input id="lot-expires-at" name="expires_at" type="date" value="{{ old('expires_at',$modalRecord?->expires_at?->format('Y-m-d')) }}">@error('expires_at')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label>Responsable</label><input value="{{ auth()->user()->name }}" disabled><small class="mt-1 block text-gray-500">Votre compte sera enregistré comme responsable du lot.</small></div>
                    <div class="t-field sm:col-span-2"><label for="lot-document">Justificatif (PDF, JPG ou PNG · 5 Mo maximum)</label><input id="lot-document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">@if($modalRecord?->document_name)<a class="mt-1 inline-block text-xs font-semibold text-green-800 underline" href="{{ route('workspace.resources.document',[$resource,$modalRecord]) }}">Document enregistré : {{ $modalRecord->document_name }}</a>@endif @error('document')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field sm:col-span-2"><label for="lot-notes">Description et notes</label><textarea id="lot-notes" name="notes" maxlength="5000" placeholder="Ajoutez les informations utiles...">{{ old('notes',$modalRecord?->notes) }}</textarea>@error('notes')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                </div>
                <aside class="t-note"><strong>Relation : Produit et lot</strong><p class="mt-1 text-xs">Le produit associé conserve la continuité de la traçabilité. Les transformations se relient aux lots depuis leur formulaire.</p></aside>
            @else
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="t-field"><label for="transformation-name">Nom de l’opération *</label><input id="transformation-name" name="name" required maxlength="160" value="{{ old('name',$modalRecord?->name) }}" placeholder="Pressage à froid">@error('name')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="transformation-reference">Référence *</label><input id="transformation-reference" name="reference" required maxlength="80" value="{{ old('reference',$modalRecord?->reference) }}" placeholder="TRF-2025-089">@error('reference')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="transformation-status">Statut *</label><select id="transformation-status" name="status" required>@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(old('status',$modalRecord?->status ?? 'recorded')===$value)>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</option>@endforeach</select>@error('status')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="transformation-facility">Site de transformation</label><input id="transformation-facility" name="facility" maxlength="190" value="{{ old('facility',$modalRecord?->facility) }}" placeholder="Atelier de transformation">@error('facility')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="transformation-date">Date *</label><input id="transformation-date" name="performed_at" type="datetime-local" required value="{{ old('performed_at',$modalRecord?->performed_at?->format('Y-m-d\TH:i')) }}">@error('performed_at')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="transformation-energy">Énergie consommée (kWh)</label><input id="transformation-energy" name="energy_kwh" type="number" min="0" step="0.001" value="{{ old('energy_kwh',$modalRecord?->energy_kwh) }}" placeholder="120">@error('energy_kwh')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label for="transformation-water">Eau consommée (litres)</label><input id="transformation-water" name="water_liters" type="number" min="0" step="0.001" value="{{ old('water_liters',$modalRecord?->water_liters) }}" placeholder="250">@error('water_liters')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    @foreach(['input_lots'=>'Lots entrants *','output_lots'=>'Lots sortants'] as $key=>$label)
                        <div class="t-field"><span>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</span>@if($options['lots']->isNotEmpty())<div class="t-check-list">@foreach($options['lots'] as $lot)<label class="t-check"><input type="checkbox" name="{{ $key }}[]" value="{{ $lot->id }}" @checked(in_array($lot->id,$key==='input_lots'?$inputSelection:$outputSelection,true))><span>{{ $lot->code }} · {{ $lot->product->name }} ({{ number_format((float)$lot->quantity,3,',',' ') }} {{ $lot->unit }})</span></label>@endforeach</div>@else<p class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600">Aucun lot actif lié à un produit publié n’est disponible. <a class="font-semibold text-green-800 underline" href="{{ route('workspace.resources.index','lots') }}">Créer un lot</a>.</p>@endif @error($key)<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    @endforeach
                    <div class="t-field sm:col-span-2"><label for="transformation-process">Description du procédé et notes</label><textarea id="transformation-process" name="process_description" maxlength="5000" placeholder="Décrivez les étapes de transformation...">{{ old('process_description',$modalRecord?->process_description) }}</textarea>@error('process_description')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field sm:col-span-2"><label for="transformation-document">Justificatif (PDF, JPG ou PNG · 5 Mo maximum)</label><input id="transformation-document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">@if($modalRecord?->document_name)<a class="mt-1 inline-block text-xs font-semibold text-green-800 underline" href="{{ route('workspace.resources.document',[$resource,$modalRecord]) }}">Document enregistré : {{ $modalRecord->document_name }}</a>@endif @error('document')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="t-field"><label>Responsable</label><input value="{{ auth()->user()->name }}" disabled><small class="mt-1 block text-gray-500">Votre compte sera enregistré comme responsable.</small></div>
                    <div class="t-field"><label for="transformation-notes">Notes complémentaires</label><textarea id="transformation-notes" name="notes" maxlength="5000" placeholder="Informations utiles à la traçabilité...">{{ old('notes',$modalRecord?->notes) }}</textarea>@error('notes')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                </div>
                <aside class="t-note"><strong>Relation : Lots entrants et sortants</strong><p class="mt-1 text-xs">Votre compte transformateur sera enregistré comme responsable. Les lots sélectionnés apparaîtront dans la fiche de traçabilité.</p></aside>
            @endif
        </div>
        <footer class="t-modal-foot flex flex-wrap justify-end gap-3"><button type="button" data-close-transformer-modal class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-700">Annuler</button><button type="submit" class="rounded-xl bg-green-800 px-6 py-3 text-sm font-semibold text-white hover:bg-green-900">{{ $editing?'Enregistrer les modifications':'Enregistrer' }}</button></footer>
    </form>
</dialog>
<script>
(() => {
    const modal = document.getElementById('transformer-record-modal');
    if (!modal) return;
    document.querySelectorAll('[data-open-transformer-modal]').forEach(button => button.addEventListener('click', () => modal.showModal()));
    document.querySelectorAll('[data-close-transformer-modal]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
    const lotInputs = modal.querySelectorAll('input[name="input_lots[]"]');
    const lotOutputs = modal.querySelectorAll('input[name="output_lots[]"]');
    const keepLotsOnOneSide = (selected, opposite) => selected.forEach(input => input.addEventListener('change', () => {
        const conflict = [...opposite].find(other => other.value === input.value);
        if (input.checked && conflict?.checked) conflict.click();
    }));
    keepLotsOnOneSide(lotInputs, lotOutputs);
    keepLotsOnOneSide(lotOutputs, lotInputs);
    @if($showModal) modal.showModal(); @endif
})();
</script>
@endif
@include('traceability.partials.csv-import-dialog')
@endsection
