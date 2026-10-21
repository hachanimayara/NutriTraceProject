@extends('layouts.admin')

@section('content')<style>
    .food-catalog-delete-form { flex: 0 0 auto; margin: 0; }
    .food-catalog-delete-button { display: inline-flex !important; width: 40px !important; min-width: 40px !important; height: 40px !important; min-height: 40px !important; padding: 0 !important; align-items: center; justify-content: center; border: 1px solid #fecaca; border-radius: 10px; background: #fff; color: #b91c1c; line-height: 1; cursor: pointer; transition: background-color .15s, border-color .15s; }
    .food-catalog-delete-button:hover { border-color: #f87171; background: #fef2f2; }
    .food-catalog-delete-button svg { display: block; width: 18px !important; height: 18px !important; flex: 0 0 18px; }
</style>
@php $fieldLabels = ['name'=>'Nom du produit','generic_name'=>'Nom générique','brands'=>'Marque','code'=>'Code-barres','categories'=>'Catégorie','quantity'=>'Quantité / conditionnement','ingredients'=>'Ingrédients','allergens'=>'Allergènes','traces'=>'Traces','nutrition'=>'Valeurs nutritionnelles pour 100 g/ml','nutri_score'=>'Nutri-Score','eco_score'=>'Eco-Score']; @endphp

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
            <div class="flex flex-wrap items-center gap-3 md:col-span-2"><button class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white hover:bg-green-900">Rechercher</button><span class="text-xs text-gray-500">La recherche par nom utilise Search-a-licious ; le code-barres utilise l’API produit v3.</span></div>
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
                <fieldset><legend class="mb-3 text-sm font-semibold">Choisissez les champs à importer</legend><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($fieldLabels as $key=>$label)<label class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm"><input type="checkbox" name="fields[]" value="{{ $key }}" @checked(filled($detailValues[$key])) class="mt-0.5 accent-green-800"><span>{{ $label }}<small class="block text-gray-500">{{ filled($detailValues[$key]) ? 'Disponible' : 'Non renseigné' }}</small></span></label>@endforeach</div></fieldset>
                <button class="rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white hover:bg-green-900">Enregistrer le produit au catalogue</button>
                <p class="text-xs text-gray-500">Cette fiche sera enregistrée séparément de vos produits NutriTrace et ne sera liée à aucun lot.</p>
            </form>
            @if($detail['image'])<p class="mt-4 text-xs text-gray-500">Les données Open Food Facts sont sous licence ODbL ; attribution : Open Food Facts. Les images sont fournies par la base.</p>@endif
        </section>
    @endif

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-lg font-semibold">Produits enregistrés dans mon catalogue</h2>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @forelse($items as $item)
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">{{ $item->name }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ $item->source === 'manual' ? 'Catalogue local' : 'Open Food Facts' }} · Code-barres : {{ $item->barcode ?: 'Non renseigné' }} · {{ $item->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <form method="post" class="food-catalog-delete-form" action="{{ route('workspace.open-food-facts.items.destroy', $item) }}" data-confirm-action="Supprimer {{ $item->name }} de votre catalogue alimentaire ?">
                            @csrf @method('DELETE')
                            <button type="submit" class="food-catalog-delete-button" aria-label="Supprimer {{ $item->name }}" title="Supprimer cette fiche"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m3 0-.8 14H5.8L5 6m4 4v6m6-6v6"/></svg></button>
                        </form>
                    </div>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach($item->data as $key => $value)
                            <div><dt class="text-xs text-gray-500">{{ $fieldLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</dt><dd class="break-words text-sm">{{ is_array($value) ? collect($value)->map(fn($v, $k) => ucfirst(str_replace('_', ' ', $k)).': '.$v)->implode(' · ') : $value }}</dd></div>
                        @endforeach
                    </dl>
                </article>
            @empty
                <p class="text-sm text-gray-500">Aucun produit n’a encore été enregistré dans votre catalogue.</p>
            @endforelse
        </div>
        @if($items->hasPages())
            <nav class="mt-6 border-t border-gray-100 pt-4" aria-label="Pagination du catalogue alimentaire">{{ $items->links() }}</nav>
        @endif
    </section>
    <p class="text-xs text-gray-500">Attribution des données : Open Food Facts. Réutilisation soumise à la licence ODbL. Les informations peuvent être incomplètes ; vérifiez-les avant usage.</p>
</main>
<script>
document.querySelectorAll('form[action="{{ route('workspace.open-food-facts.search') }}"]').forEach(form => form.addEventListener('submit', () => { const button = form.querySelector('button[type="submit"],button:not([type])'); if (button) { button.disabled = true; button.textContent = 'Recherche en cours…'; } }));
document.querySelectorAll('#off-import-form').forEach(form => form.addEventListener('submit', event => {
    const selected = [...form.querySelectorAll('input[name="fields[]"]:checked')];
    if (!selected.length) { event.preventDefault(); alert('Sélectionnez au moins un champ à importer.'); return; }
}));
</script>
@endsection
