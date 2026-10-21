@extends('layouts.admin')

@section('content')
@php
    $isStores = $resource === 'stores';
    $title = $isStores ? 'Points de vente' : 'Distributions';
    $showModal = request('modal') === 'create' || $modalRecord || $errors->any();
    $editing = (bool) $modalRecord;
    $statuses = $isStores
        ? ['active' => 'Actif', 'inactive' => 'Inactif']
        : ['planned' => 'Préparée', 'in_transit' => 'Expédiée', 'delivered' => 'Reçue', 'delayed' => 'En retard', 'cancelled' => 'Annulée'];
    $statusLabels = $isStores
        ? ['active' => 'Actif', 'inactive' => 'Inactif']
        : ['planned' => 'Préparée', 'in_transit' => 'Expédiée', 'delivered' => 'Reçue', 'delayed' => 'En retard', 'cancelled' => 'Annulée'];
@endphp
<style>
    .dist-page{--d-green:#2d6a4f;--d-line:#dfe7e2;--d-muted:#718078;color:#17231f}
    .dist-page .d-card{border:1px solid var(--d-line);border-radius:18px;background:#fff}
    .dist-page .d-stat{min-height:92px;padding:18px 20px;display:flex;align-items:center;gap:15px}
    .dist-page .d-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:#e9f5ee;color:#2d6a4f;font-size:20px}
    .dist-page .d-icon.blue{background:#edf7fa;color:#347987}.dist-page .d-icon.amber{background:#fff6e6;color:#a66c13}
    .dist-page table th{padding:14px 20px;border-bottom:1px solid #e8ece8;background:#f7f9f7;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#738078;white-space:nowrap}
    .dist-page table td{padding:17px 20px;border-bottom:1px solid #edf1ed;font-size:13px;white-space:nowrap}
    .dist-page .d-badge{display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:7px 11px;background:#e9f5ee;color:#276b4e;font-size:12px;font-weight:700}
    .dist-page .d-badge.delayed,.dist-page .d-badge.inactive{background:#fff4df;color:#a15d00}.dist-page .d-badge.cancelled{background:#fff0f0;color:#b42318}
    .dist-page .d-action{display:inline-grid;place-items:center;width:34px;height:34px;border:1px solid #e4eae5;border-radius:10px;color:#65736b}
    .dist-page .d-action:hover{background:#eff7f1;color:var(--d-green)}
    #distributor-record-modal{position:fixed;inset:0;margin:auto;box-sizing:border-box;width:min(1000px,calc(100vw - 32px));height:min(840px,calc(100dvh - 32px));max-width:none;max-height:calc(100dvh - 32px);padding:0;border:1px solid #e0e8e2;border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 30px 100px #12261b40;color:#1f2937}
    #distributor-record-modal[open]{display:block}#distributor-record-modal::backdrop{background:rgba(16,35,29,.42);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}
    #distributor-record-modal form{display:flex;flex-direction:column;width:100%;height:100%;max-height:100%;min-height:0}
    #distributor-record-modal .d-modal-head,#distributor-record-modal .d-modal-foot{position:relative;z-index:2;flex:none;background:#fff;padding:18px 24px}
    #distributor-record-modal .d-modal-head{border-bottom:1px solid #e5e9e5}#distributor-record-modal .d-modal-foot{border-top:1px solid #e5e9e5}
    #distributor-record-modal .d-modal-body{padding:24px;overflow-y:auto;overscroll-behavior:contain}
    #distributor-record-modal .d-field{min-width:0}#distributor-record-modal .d-field>label,#distributor-record-modal .d-field>span{display:block;margin-bottom:8px;font-size:13px;line-height:1.35;font-weight:700;color:#26352d}
    #distributor-record-modal .d-field input:not([type=checkbox]),#distributor-record-modal .d-field select,#distributor-record-modal .d-field textarea{display:block;box-sizing:border-box;width:100%;min-height:49px;border:1px solid #dce3de;border-radius:12px;padding:12px 14px;background:#fff;color:#26352d;font-size:15px;line-height:1.4;outline:none}
    #distributor-record-modal .d-field textarea{min-height:100px;resize:vertical}#distributor-record-modal .d-field input:focus,#distributor-record-modal .d-field select:focus,#distributor-record-modal .d-field textarea:focus{border-color:#2d6a4f;box-shadow:0 0 0 3px #2d6a4f18}
    #distributor-record-modal .d-note{border-radius:14px;background:#eaf5ee;padding:15px;color:#285b43}
    @media(max-width:640px){#distributor-record-modal{width:calc(100vw - 16px);height:calc(100dvh - 16px);max-height:calc(100dvh - 16px);border-radius:14px}#distributor-record-modal .d-modal-head,#distributor-record-modal .d-modal-foot{padding:14px 16px}#distributor-record-modal .d-modal-body{padding:18px 16px}#distributor-record-modal .d-modal-foot{justify-content:stretch}#distributor-record-modal .d-modal-foot button{flex:1;padding:11px 8px;font-size:12px}}
</style>

<main class="dist-page space-y-5">
    <nav class="flex items-center gap-2 text-xs text-gray-500"><a href="{{ auth()->user()->getDashboardUrl() }}" class="hover:text-green-800">Tableau de bord</a><span>›</span><span>{{ $title }}</span></nav>
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div><p class="text-xs font-semibold uppercase tracking-[.14em] text-green-800">Espace distributeur</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">{{ $title }}</h1><p class="mt-2 text-sm text-gray-500">{{ $isStores ? 'Gérez les destinations et leurs informations de réception.' : 'Suivez les mouvements des lots jusqu’aux points de vente.' }}</p></div>
        <div class="flex gap-3"><a href="{{ route('workspace.resources.export', $resource) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-50"><span aria-hidden="true">↓</span> Exporter</a><a href="{{ route('workspace.resources.index', ['resource' => $resource, 'modal' => 'create']) }}" class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white">＋ Ajouter {{ $isStores ? 'un point de vente' : 'une distribution' }}</a></div>
    </header>
    <div class="-mt-3 flex justify-end"><button type="button" data-open-csv-import="{{ route('workspace.resources.import', ['resource' => $resource, 'popup' => 1]) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">Importer CSV</button></div>

    @if(session('status'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-900" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">Vérifiez les champs signalés dans le formulaire.</div>@endif

    <section class="grid gap-4 md:grid-cols-3" aria-label="Statistiques {{ mb_strtolower($title) }}">
        @foreach($stats as $stat)<article class="d-card d-stat"><span class="d-icon {{ $stat['tone'] }}">{{ $stat['tone']==='amber'?'⚠':($isStores?'⌖':'♧') }}</span><div><strong class="block text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</strong><span class="text-sm text-gray-500">{{ $stat['label'] }}</span></div></article>@endforeach
    </section>

    <section class="d-card overflow-hidden">
        <form method="get" data-list-filters class="flex flex-wrap gap-3 border-b border-gray-100 p-4">
            <label class="flex min-w-[230px] flex-1 items-center gap-3 rounded-xl border border-gray-200 px-4 py-3"><span class="text-xl text-gray-400" aria-hidden="true">⌕</span><span class="sr-only">Rechercher</span><input class="w-full border-0 bg-transparent p-0 text-sm outline-none focus:ring-0" name="q" value="{{ request('q') }}" placeholder="{{ $isStores ? 'Rechercher point de vente...' : 'Rechercher distribution...' }}"></label>
            <button type="submit" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-50"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M7 12h10m-7 6h4"/><circle cx="8" cy="6" r="1.5" fill="white"/><circle cx="15" cy="12" r="1.5" fill="white"/><circle cx="12" cy="18" r="1.5" fill="white"/></svg>Filtres</button>
            <select name="status" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm"><option value="">Tous les statuts</option>@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</option>@endforeach</select>
        </form>
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead><tr>
            @if($isStores)<th>Point de vente</th><th>Référence</th><th>Ville</th><th>Responsable</th><th>Statut</th>
            @else<th>Référence</th><th>Lot</th><th>Destination</th><th>Expédition</th><th>Statut</th>@endif
            <th class="text-right">Détails</th>
        </tr></thead><tbody>
            @forelse($records as $record)
                @php $rowStatus = $statusLabels[$record->status] ?? ucfirst($record->status); @endphp
                <tr class="hover:bg-green-50/30">
                    @if($isStores)
                        <td><a class="font-semibold text-gray-900 hover:text-green-800" data-detail-popup href="{{ route('workspace.resources.show',[$resource,$record]) }}">{{ $record->name }}</a></td><td class="text-gray-600">{{ $record->reference }}</td><td class="text-gray-600">{{ $record->city ?: '—' }}</td><td class="text-gray-600">{{ $record->contact_name ?: '—' }}</td>
                    @else
                        <td><a class="font-semibold text-gray-900 hover:text-green-800" data-detail-popup href="{{ route('workspace.resources.show',[$resource,$record]) }}">{{ $record->reference }}</a></td><td class="text-gray-600">{{ $record->lot?->code ?? '—' }}</td><td class="text-gray-600">{{ $record->store?->name ?? '—' }}</td><td class="text-gray-600">{{ $record->shipped_at?->format('d/m/Y') ?? '—' }}</td>
                    @endif
                    <td><span class="d-badge {{ $record->status }}"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>{!! mb_encode_numericentity($rowStatus, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</span></td>
                    <td><div class="flex justify-end gap-2"><a class="d-action" aria-label="Voir" title="Voir" data-detail-popup href="{{ route('workspace.resources.show',[$resource,$record]) }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg></a><a class="d-action" aria-label="Modifier" title="Modifier" href="{{ route('workspace.resources.edit',[$resource,$record]) }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m14 5 5 5M4 20l4.2-.8L19 8.4 15.6 5 4.8 15.8 4 20Z"/></svg></a><form method="post" action="{{ route('workspace.resources.destroy',[$resource,$record]) }}" data-confirm-action="Supprimer cet élément ?">@csrf @method('DELETE')<button class="d-action text-red-600" aria-label="Supprimer" title="Supprimer"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m3 0-.8 14H5.8L5 6m4 4v6m6-6v6"/></svg></button></form></div></td>
                </tr>
            @empty<tr><td colspan="6" class="px-6 py-16 text-center"><strong class="text-gray-800">Aucun résultat</strong><p class="mt-1 text-sm text-gray-500">Vos {{ mb_strtolower($title) }} apparaîtront ici après leur enregistrement.</p></td></tr>@endforelse
        </tbody></table></div>
        <x-pagination :paginator="$records" />
    </section>
</main>

@if($showModal)
<dialog id="distributor-record-modal" aria-labelledby="distributor-modal-title">
    <form method="post" action="{{ $editing ? route('workspace.resources.update',[$resource,$modalRecord]) : route('workspace.resources.store',$resource) }}">
        @csrf @if($editing) @method('PUT') @endif
        <header class="d-modal-head flex items-center justify-between"><h2 id="distributor-modal-title" class="text-xl font-semibold">{{ $editing?'Modifier':'Ajouter' }} {{ $isStores?'un point de vente':'une distribution' }}</h2><button type="button" data-close-distributor-modal class="grid h-11 w-11 place-items-center rounded-xl border border-gray-200 text-xl text-gray-500 hover:bg-gray-50" aria-label="Fermer">×</button></header>
        <div class="d-modal-body space-y-6">
            <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-green-50 text-sm font-bold text-green-800">01</span><div><h3 class="font-semibold">Informations principales</h3><p class="mt-1 text-xs text-gray-500">Renseignez les données nécessaires à la traçabilité.</p></div></div>
            @if($isStores)
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="d-field"><label for="store-name">Nom du point de vente *</label><input id="store-name" name="name" required maxlength="160" value="{{ old('name',$modalRecord?->name) }}" placeholder="Bio Marché Lyon">@error('name')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="store-reference">Référence *</label><input id="store-reference" name="reference" required maxlength="80" value="{{ old('reference',$modalRecord?->reference) }}" placeholder="PDV-LYO-001">@error('reference')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="store-status">Statut *</label><select id="store-status" name="status" required>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(old('status',$modalRecord?->status ?? 'active')===$value)>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</option>@endforeach</select>@error('status')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="store-city">Ville</label><input id="store-city" name="city" maxlength="120" value="{{ old('city',$modalRecord?->city) }}" placeholder="Lyon">@error('city')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field sm:col-span-2"><label for="store-address">Adresse *</label><input id="store-address" name="address" required maxlength="255" value="{{ old('address',$modalRecord?->address) }}" placeholder="12 rue des Halles">@error('address')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="store-latitude">Latitude</label><input id="store-latitude" name="latitude" type="number" step="any" min="-90" max="90" value="{{ old('latitude',$modalRecord?->latitude) }}" placeholder="36.8065">@error('latitude')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="store-longitude">Longitude</label><input id="store-longitude" name="longitude" type="number" step="any" min="-180" max="180" value="{{ old('longitude',$modalRecord?->longitude) }}" placeholder="10.1815">@error('longitude')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="store-country">Pays *</label><input id="store-country" name="country" required maxlength="100" value="{{ old('country',$modalRecord?->country ?? 'France') }}" placeholder="France">@error('country')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="store-contact">Responsable / contact</label><input id="store-contact" name="contact_name" maxlength="160" value="{{ old('contact_name',$modalRecord?->contact_name) }}" placeholder="Sophie Bernard">@error('contact_name')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="store-phone">Téléphone</label><input id="store-phone" name="phone" maxlength="32" value="{{ old('phone',$modalRecord?->phone) }}" placeholder="+33 6 12 34 56 78">@error('phone')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                </div>
                <aside class="d-note"><strong>Relation : Distributions reçues</strong><p class="mt-1 text-xs">Les expéditions vers ce point de vente seront visibles sur sa fiche de traçabilité.</p></aside>
            @else
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="d-field"><label for="distribution-reference">Référence *</label><input id="distribution-reference" name="reference" required maxlength="80" value="{{ old('reference',$modalRecord?->reference) }}" placeholder="DIS-2506-108">@error('reference')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="distribution-status">Statut *</label><select id="distribution-status" name="status" required>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(old('status',$modalRecord?->status ?? 'planned')===$value)>{!! mb_encode_numericentity($label, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') !!}</option>@endforeach</select>@error('status')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="distribution-lot">Lot *</label><select id="distribution-lot" name="lot_id" required><option value="">Sélectionner un lot actif</option>@foreach($options['lots'] as $lot)<option value="{{ $lot->id }}" @selected((string)old('lot_id',$modalRecord?->lot_id)===(string)$lot->id)>{{ $lot->code }} · {{ $lot->product?->name }} — {{ number_format((float)$lot->quantity,3,',',' ') }} {{ $lot->unit }}</option>@endforeach</select>@error('lot_id')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror @if($options['lots']->isEmpty())<small class="mt-1 block text-gray-500">Aucun lot actif d’un produit publié n’est disponible.</small>@endif</div>
                    <div class="d-field"><label for="distribution-store">Point de vente *</label><select id="distribution-store" name="store_id" required><option value="">Sélectionner un point de vente</option>@foreach($options['stores'] as $store)<option value="{{ $store->id }}" @selected((string)old('store_id',$modalRecord?->store_id)===(string)$store->id)>{{ $store->name }}{{ $store->city ? ' · '.$store->city : '' }}</option>@endforeach</select>@error('store_id')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror @if($options['stores']->isEmpty())<small class="mt-1 block text-gray-500">Créez d’abord un point de vente.</small>@endif</div>
                    <div class="d-field"><label for="distribution-origin-name">Lieu de départ</label><input id="distribution-origin-name" name="origin_name" maxlength="190" value="{{ old('origin_name',$modalRecord?->origin_name) }}" placeholder="Entrepôt, ville…">@error('origin_name')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="distribution-origin-latitude">Latitude du départ</label><input id="distribution-origin-latitude" name="origin_latitude" type="number" step="any" min="-90" max="90" value="{{ old('origin_latitude',$modalRecord?->origin_latitude) }}" placeholder="36.8065">@error('origin_latitude')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="distribution-origin-longitude">Longitude du départ</label><input id="distribution-origin-longitude" name="origin_longitude" type="number" step="any" min="-180" max="180" value="{{ old('origin_longitude',$modalRecord?->origin_longitude) }}" placeholder="10.1815">@error('origin_longitude')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="quantity-shipped">Quantité expédiée *</label><div class="flex gap-2"><input id="quantity-shipped" name="quantity_shipped" type="number" min="0.001" step="0.001" required value="{{ old('quantity_shipped',$modalRecord?->quantity_shipped) }}" placeholder="120"><select name="unit" aria-label="Unité" required><option value="kg" @selected(old('unit',$modalRecord?->unit ?? 'kg')==='kg')>kg</option><option value="L" @selected(old('unit',$modalRecord?->unit)==='L')>L</option><option value="unité" @selected(old('unit',$modalRecord?->unit)==='unité')>unité</option></select></div>@error('quantity_shipped')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="quantity-delivered">Quantité reçue</label><input id="quantity-delivered" name="quantity_delivered" type="number" min="0" step="0.001" value="{{ old('quantity_delivered',$modalRecord?->quantity_delivered) }}" placeholder="120">@error('quantity_delivered')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="shipped-at">Date d’expédition</label><input id="shipped-at" name="shipped_at" type="date" value="{{ old('shipped_at',$modalRecord?->shipped_at?->format('Y-m-d')) }}">@error('shipped_at')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="expected-at">Livraison prévue</label><input id="expected-at" name="expected_at" type="date" value="{{ old('expected_at',$modalRecord?->expected_at?->format('Y-m-d')) }}">@error('expected_at')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field"><label for="delivered-at">Date de réception</label><input id="delivered-at" name="delivered_at" type="date" value="{{ old('delivered_at',$modalRecord?->delivered_at?->format('Y-m-d')) }}">@error('delivered_at')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                    <div class="d-field sm:col-span-2"><label for="distribution-notes">Description et notes</label><textarea id="distribution-notes" name="notes" maxlength="5000" placeholder="Ajoutez les informations utiles...">{{ old('notes',$modalRecord?->notes) }}</textarea>@error('notes')<small class="mt-1 block text-red-700">{{ $message }}</small>@enderror</div>
                </div>
                <aside class="d-note"><strong>Relation : Lot et point de vente</strong><p class="mt-1 text-xs">Le lot expédié et sa destination seront conservés dans la fiche de traçabilité.</p></aside>
            @endif
        </div>
        <footer class="d-modal-foot flex flex-wrap justify-end gap-3"><button type="button" data-close-distributor-modal class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-700">Annuler</button><button type="submit" class="rounded-xl bg-green-800 px-6 py-3 text-sm font-semibold text-white hover:bg-green-900">{{ $editing?'Enregistrer les modifications':'Enregistrer' }}</button></footer>
    </form>
</dialog>
<script>
(() => {
    const modal = document.getElementById('distributor-record-modal');
    if (!modal) return;
    document.querySelectorAll('[data-open-distributor-modal]').forEach(button => button.addEventListener('click', () => modal.showModal()));
    document.querySelectorAll('[data-close-distributor-modal]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
    @if($showModal) modal.showModal(); @endif
})();
</script>
@endif
@include('traceability.partials.csv-import-dialog')
@endsection
