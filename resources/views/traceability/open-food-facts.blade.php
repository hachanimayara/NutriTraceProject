@extends('layouts.admin')

@section('content')
@php $fieldLabels = ['name'=>'Nom du produit','generic_name'=>'Nom générique','brands'=>'Marque','code'=>'Code-barres','categories'=>'Catégorie','quantity'=>'Quantité / conditionnement','ingredients'=>'Ingrédients','allergens'=>'Allergènes','traces'=>'Traces','nutrition'=>'Valeurs nutritionnelles pour 100 g/ml','nutri_score'=>'Nutri-Score','eco_score'=>'Eco-Score']; @endphp
<style>
    #manual-entry-dialog{position:fixed;inset:0;margin:auto;width:min(920px,calc(100vw - 32px));max-width:none;max-height:calc(100dvh - 32px);padding:0;overflow:auto;border:1px solid #dce5df;border-radius:20px;background:#fff;color:#1f2937;box-shadow:0 24px 80px #10251b40}
    #manual-entry-dialog::backdrop{background:#101b1680;backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}
    @media(max-width:640px){#manual-entry-dialog{width:calc(100vw - 16px);max-height:calc(100dvh - 16px);border-radius:14px}}
</style>
<main class="space-y-6 text-gray-800">
    <nav class="text-xs text-gray-500"><a class="hover:text-green-800" href="{{ route('transformateur.dashboard') }}">Tableau de bord</a><span class="mx-2">›</span>Catalogue alimentaire</nav>
    <header><p class="text-xs font-semibold uppercase tracking-[.14em] text-green-800">Espace transformateur</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">Catalogue alimentaire et informations nutritionnelles</h1><p class="mt-2 max-w-3xl text-sm text-gray-500">Recherchez un aliment dans Open Food Facts, puis choisissez les informations à rattacher à une fiche produit publiée.</p></header>
    @if(session('status'))<div role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    @if($message)<div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $message }}</div>@endif

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-lg font-semibold">Rechercher dans le catalogue</h2>
        <form method="get" action="{{ route('workspace.open-food-facts.search') }}" class="mt-5 grid gap-4 md:grid-cols-2">
            <div><label for="off-query" class="mb-2 block text-sm font-medium">Nom du produit</label><input id="off-query" name="query" value="{{ old('query', request('query')) }}" maxlength="120" class="w-full rounded-xl border border-gray-300 px-4 py-3" placeholder="Ex. huile d’olive" autocomplete="off"></div>
            <div><label for="off-barcode" class="mb-2 block text-sm font-medium">Code-barres (EAN/UPC)</label><input id="off-barcode" name="barcode" value="{{ old('barcode', request('barcode')) }}" inputmode="numeric" pattern="[0-9]{8,14}" class="w-full rounded-xl border border-gray-300 px-4 py-3" placeholder="8 à 14 chiffres"></div>
            <div class="flex flex-wrap items-center gap-3 md:col-span-2"><button class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white hover:bg-green-900">Rechercher</button><button type="button" data-open-manual-dialog class="rounded-xl border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700">Saisie manuelle</button><span class="text-xs text-gray-500">La recherche par nom utilise Search-a-licious ; le code-barres utilise l’API produit v3.</span></div>
        </form>
    </section>

    @if(count($results))
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-semibold">Résultats de recherche</h2><div class="mt-4 divide-y divide-gray-100">
            @foreach($results as $item)<form method="get" action="{{ route('workspace.open-food-facts.search') }}" class="flex flex-wrap items-center justify-between gap-3 py-4"><div><strong>{{ $item['name'] ?: 'Nom non renseigné' }}</strong><p class="mt-1 text-sm text-gray-500">{{ $item['brands'] ?: 'Marque non renseignée' }} · Code-barres : {{ $item['code'] ?: 'Non renseigné' }}</p></div>@if($item['code'])<input type="hidden" name="barcode" value="{{ $item['code'] }}"><button class="rounded-xl border border-green-800 px-4 py-2.5 text-sm font-semibold text-green-900">Voir les informations</button>@endif</form>@endforeach
        </div></section>
    @endif

    @if($detail)
        @php
            $detailValues = ['name'=>$detail['name'],'generic_name'=>$detail['generic_name'],'brands'=>$detail['brands'],'code'=>$detail['code'],'categories'=>$detail['categories'],'quantity'=>$detail['quantity'],'ingredients'=>$detail['ingredients'],'allergens'=>$detail['allergens'],'traces'=>$detail['traces'],'nutrition'=>$detail['nutrition'] ? collect($detail['nutrition'])->map(fn($v,$k)=>ucfirst(str_replace('_',' ',$k)).': '.$v)->implode(' · ') : null,'nutri_score'=>$detail['nutri_score'],'eco_score'=>$detail['eco_score']];
        @endphp
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-wrap items-start gap-5">@if($detail['image'])<img src="{{ $detail['image'] }}" alt="Image Open Food Facts : {{ $detail['name'] ?: 'produit' }}" loading="lazy" referrerpolicy="no-referrer" class="h-28 w-28 rounded-xl border border-gray-200 object-contain">@endif<div><h2 class="text-xl font-semibold">{{ $detail['name'] ?: 'Produit sans nom renseigné' }}</h2><p class="mt-1 text-sm text-gray-500">Source : Open Food Facts · Code : {{ $detail['code'] ?: 'Non renseigné' }}</p></div></div>
            <dl class="mt-5 grid gap-3 sm:grid-cols-2">@foreach($fieldLabels as $key=>$label)<div class="rounded-xl bg-gray-50 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm">{{ filled($detailValues[$key]) ? $detailValues[$key] : 'Non renseigné' }}</dd></div>@endforeach</dl>
            <form method="post" action="{{ route('workspace.open-food-facts.import') }}" class="mt-6 space-y-5" id="off-import-form">
                @csrf
                <input type="hidden" name="barcode" value="{{ $detail['code'] }}">
                <div><label for="local-product" class="mb-2 block text-sm font-semibold">Fiche produit locale (publiée)</label><select name="local_product_id" id="local-product" class="w-full rounded-xl border border-gray-300 px-4 py-3" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} · {{ $product->reference }}</option>@endforeach</select>
                @if($products->isEmpty())<p class="mt-2 text-sm text-amber-800">Aucun produit publié n’est disponible pour le moment. Les produits sont créés et publiés dans le flux existant, puis associés aux lots de sortie.</p>@endif</div>
                <fieldset><legend class="mb-3 text-sm font-semibold">Choisissez les champs à importer</legend><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($fieldLabels as $key=>$label)<label class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm"><input type="checkbox" name="fields[]" value="{{ $key }}" @checked(filled($detailValues[$key])) class="mt-0.5 accent-green-800"><span>{{ $label }}<small class="block text-gray-500">{{ filled($detailValues[$key]) ? 'Disponible' : 'Non renseigné' }}</small></span></label>@endforeach</div></fieldset>
                @if($products->isNotEmpty())<label class="flex items-start gap-2 text-sm text-gray-700"><input type="checkbox" name="overwrite" value="1" class="mt-1 accent-green-800"><span>J’autorise le remplacement des champs Open Food Facts déjà enregistrés dans cette fiche.</span></label><button class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white hover:bg-green-900">Importer les informations sélectionnées</button>@endif
                <p class="text-xs text-gray-500">L’import ne modifie pas le nom, la quantité de production, les dates, les matières premières ni les lots NutriTrace.</p>
            </form>
            @if($detail['image'])<p class="mt-4 text-xs text-gray-500">Les données Open Food Facts sont sous licence ODbL ; attribution : Open Food Facts. Les images sont fournies par la base.</p>@endif
        </section>
    @endif

    <dialog id="manual-entry-dialog" aria-labelledby="manual-entry-title">
        <div class="p-5 sm:p-7">
        <header class="mb-5 flex items-start justify-between gap-4"><div><h2 id="manual-entry-title" class="text-lg font-semibold">Saisie manuelle</h2><p class="mt-1 text-sm text-gray-500">Utile si le produit n’est pas répertorié ou si le catalogue est indisponible.</p></div><button type="button" data-close-manual-dialog aria-label="Fermer" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-gray-200 text-xl text-gray-500 hover:bg-gray-50">&times;</button></header>
        @if($products->isEmpty())<p class="mt-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">Créez d’abord un produit dans le flux de gestion existant, puis publiez-le pour pouvoir y rattacher les données.</p>@else
        <form method="post" action="{{ route('workspace.open-food-facts.import') }}" class="mt-5 space-y-5" id="manual-import-form">
            @csrf<input type="hidden" name="manual_mode" value="1">
            <div><label for="manual-product" class="mb-2 block text-sm font-semibold">Fiche produit publiée</label><select name="local_product_id" id="manual-product" class="w-full rounded-xl border border-gray-300 px-4 py-3" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} · {{ $product->reference }}</option>@endforeach</select></div>
            <div class="grid gap-4 sm:grid-cols-2">@foreach(['name'=>'Nom','generic_name'=>'Nom générique','brands'=>'Marque','code'=>'Code-barres','categories'=>'Catégorie','quantity'=>'Conditionnement','ingredients'=>'Ingrédients','allergens'=>'Allergènes','traces'=>'Traces','nutrition'=>'Valeurs nutritionnelles pour 100 g/ml','nutri_score'=>'Nutri-Score','eco_score'=>'Eco-Score'] as $key=>$label)<div><label for="manual-{{ $key }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label><input id="manual-{{ $key }}" name="manual[{{ $key }}]" @if($key==='code')inputmode="numeric" pattern="[0-9]{8,14}"@endif class="w-full rounded-xl border border-gray-300 px-3 py-2.5"></div>@endforeach</div>
            <fieldset><legend class="mb-2 text-sm font-semibold">Champs à enregistrer</legend><div class="flex flex-wrap gap-3">@foreach(['name','generic_name','brands','code','categories','quantity','ingredients','allergens','traces','nutrition','nutri_score','eco_score'] as $key)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="fields[]" value="{{ $key }}" class="accent-green-800">{{ ['name'=>'Nom','generic_name'=>'Nom générique','brands'=>'Marque','code'=>'Code-barres','categories'=>'Catégorie','quantity'=>'Conditionnement','ingredients'=>'Ingrédients','allergens'=>'Allergènes','traces'=>'Traces','nutrition'=>'Valeurs nutritionnelles','nutri_score'=>'Nutri-Score','eco_score'=>'Eco-Score'][$key] }}</label>@endforeach</div></fieldset>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="overwrite" value="1" class="mt-1 accent-green-800"><span>J’autorise le remplacement des informations existantes.</span></label><button class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white hover:bg-green-900">Enregistrer les informations manuelles</button>
        </form>@endif
        </div>
    </dialog>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-lg font-semibold">Informations déjà enregistrées</h2><div class="mt-4 grid gap-4 lg:grid-cols-2">@forelse($products->filter(fn($product)=>filled($product->open_food_facts_data)) as $product)<article class="rounded-xl border border-gray-200 p-4"><h3 class="font-semibold">{{ $product->name }} <span class="text-sm font-normal text-gray-500">· {{ $product->reference }}</span></h3><p class="mt-1 text-xs text-gray-500">{{ $product->open_food_facts_source === 'manual' ? 'Saisie manuelle' : 'Open Food Facts' }} · Code : {{ $product->open_food_facts_code ?: 'Non renseigné' }} · {{ $product->open_food_facts_imported_at?->format('d/m/Y H:i') }}</p><dl class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($product->open_food_facts_data as $key=>$value)<div><dt class="text-xs text-gray-500">{{ $fieldLabels[$key] ?? ucfirst(str_replace('_',' ',$key)) }}</dt><dd class="break-words text-sm">{{ is_array($value) ? collect($value)->map(fn($v,$k)=>ucfirst(str_replace('_',' ',$k)).': '.$v)->implode(' · ') : $value }}</dd></div>@endforeach</dl></article>@empty<p class="text-sm text-gray-500">Aucune information enrichie n’a encore été enregistrée.</p>@endforelse</div></section>
    <p class="text-xs text-gray-500">Attribution des données : Open Food Facts. Réutilisation soumise à la licence ODbL. Les informations peuvent être incomplètes ; vérifiez-les avant usage.</p>
</main>
<script>
document.querySelectorAll('form[action="{{ route('workspace.open-food-facts.search') }}"]').forEach(form => form.addEventListener('submit', () => { const button = form.querySelector('button[type="submit"],button:not([type])'); if (button) { button.disabled = true; button.textContent = 'Recherche en cours…'; } }));
const manualDialog = document.getElementById('manual-entry-dialog');
document.querySelector('[data-open-manual-dialog]')?.addEventListener('click', () => manualDialog?.showModal());
document.querySelector('[data-close-manual-dialog]')?.addEventListener('click', () => manualDialog?.close());
manualDialog?.addEventListener('click', event => { if (event.target === manualDialog) manualDialog.close(); });
@if($errors->any() && old('manual_mode')) manualDialog?.showModal(); @endif
document.querySelectorAll('#off-import-form, #manual-import-form').forEach(form => form.addEventListener('submit', event => {
    const select = form.querySelector('select[name="local_product_id"]');
    if (!select?.value) { event.preventDefault(); return; }
    const selected = [...form.querySelectorAll('input[name="fields[]"]:checked')];
    if (!selected.length) { event.preventDefault(); alert('Sélectionnez au moins un champ à importer.'); return; }
    const overwrite = form.querySelector('input[name="overwrite"]');
    if (overwrite?.checked && !window.confirm('Confirmez-vous le remplacement des informations déjà enregistrées ?')) event.preventDefault();
}));
</script>
@endsection
