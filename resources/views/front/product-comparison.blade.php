@extends('layouts.public')

@section('title', 'Comparer les produits | NutriTrace')

@section('content')
<style>
.product-compare{max-width:1540px;margin:0 auto;padding:26px clamp(20px,4vw,60px) 64px;color:#1e3027}
.product-compare a{color:#24664a;text-decoration:none;font-weight:650}.product-compare a:hover{text-decoration:underline}
.pc-back{display:inline-block;margin-bottom:38px}.pc-eyebrow{margin:0 0 8px;color:#39795d;font-size:12px;font-weight:800;letter-spacing:.17em;text-transform:uppercase}
.pc-title{margin:0;color:#24332c;font-family:Georgia,'Times New Roman',serif;font-size:clamp(38px,5vw,68px);line-height:1.08;letter-spacing:-.045em}
.pc-intro{margin:18px 0 36px;color:#718078;font-size:18px;line-height:1.55}
.pc-picker{display:flex;align-items:end;gap:12px;padding:18px;margin-bottom:24px;border:1px solid #dfe7e2;border-radius:14px;background:#fff}
.pc-picker label{display:grid;flex:1;gap:8px;color:#34463b;font-size:13px;font-weight:700}.pc-picker select{width:100%;min-height:48px;padding:0 14px;border:1px solid #d5dfd9;border-radius:9px;background:#fff;color:#25352d;font:inherit}
.pc-button{min-height:48px;padding:0 22px;border:1px solid #2d6a4f;border-radius:10px;background:#2d6a4f;color:#fff!important;font:inherit;font-weight:700;cursor:pointer}.pc-button:hover{background:#24573f;text-decoration:none!important}
.pc-table-wrap{overflow-x:auto;border:1px solid #dfe7e2;border-radius:15px;background:#fff;box-shadow:0 8px 24px rgba(32,67,49,.04)}
.pc-table{width:100%;border-collapse:collapse;min-width:720px}.pc-table th,.pc-table td{width:30%;padding:20px 26px;border-right:1px solid #dfe7e2;border-bottom:1px solid #dfe7e2;text-align:left;vertical-align:middle}.pc-table th:first-child,.pc-table td:first-child{width:40%}.pc-table tr:last-child td{border-bottom:0}.pc-table th:last-child,.pc-table td:last-child{border-right:0}.pc-table thead th{height:76px;background:#245f48;color:#fff;font-size:18px;font-weight:700}.pc-table thead th:first-child{font-weight:500}.pc-table tbody td:first-child{color:#718078;font-size:17px}.pc-table tbody td:not(:first-child){color:#172b21;font-weight:650}.pc-empty{padding:24px;border-radius:12px;background:#f1f7f2;color:#52635a}
@media(max-width:620px){.product-compare{padding-top:18px}.pc-back{margin-bottom:28px}.pc-picker{align-items:stretch;flex-direction:column}.pc-button{width:100%}.pc-table th,.pc-table td{padding:16px}}
</style>

<main class="product-compare">
    <a class="pc-back" href="{{ route('public.catalogue') }}">← Retour au catalogue</a>
    <header>
        <p class="pc-eyebrow">Comparateur</p>
        <h1 class="pc-title">Comparer sans simplifier à l’excès</h1>
        <p class="pc-intro">Les valeurs utilisent les unités renseignées pour chaque produit et ne constituent pas un classement écologique.</p>
    </header>

    @if($otherProducts->isNotEmpty())
        <form class="pc-picker" method="get" action="{{ route('public.products.compare', $currentProduct->id) }}">
            <label for="compare_with">Choisir un autre produit publié
                <select id="compare_with" name="compare_with">
                    @foreach($otherProducts as $option)
                        <option value="{{ $option->id }}" @selected($comparisonProduct?->id === $option->id)>{{ $option->name }}{{ $option->origin ? ' · '.$option->origin : '' }}</option>
                    @endforeach
                </select>
            </label>
            <button class="pc-button" type="submit">Comparer</button>
        </form>
    @endif

    @if($comparisonProduct)
        <div class="pc-table-wrap">
            <table class="pc-table">
                <thead><tr><th scope="col">Critère</th><th scope="col">{{ $currentProduct->name }}</th><th scope="col">{{ $comparisonProduct->name }}</th></tr></thead>
                <tbody>
                    <tr><td>Catégorie</td><td>{{ $currentProduct->category ?: 'Non renseignée' }}</td><td>{{ $comparisonProduct->category ?: 'Non renseignée' }}</td></tr>
                    <tr><td>Origine</td><td>{{ $currentFacts['origin'] }}</td><td>{{ $comparisonFacts['origin'] }}</td></tr>
                    <tr><td>Producteur</td><td>{{ $currentFacts['producer'] }}</td><td>{{ $comparisonFacts['producer'] }}</td></tr>
                    <tr><td>Étapes documentées</td><td>{{ $currentFacts['steps'] }}</td><td>{{ $comparisonFacts['steps'] }}</td></tr>
                    <tr><td>Certifications vérifiées</td><td>{{ $currentFacts['certifications'] }}</td><td>{{ $comparisonFacts['certifications'] }}</td></tr>
                    <tr><td>Empreinte carbone</td><td>{{ $currentFacts['carbon'] }}</td><td>{{ $comparisonFacts['carbon'] }}</td></tr>
                    <tr><td>Consommation d’eau</td><td>{{ $currentFacts['water'] }}</td><td>{{ $comparisonFacts['water'] }}</td></tr>
                    <tr><td>Transport</td><td>{{ $currentFacts['transport'] }}</td><td>{{ $comparisonFacts['transport'] }}</td></tr>
                </tbody>
            </table>
        </div>
    @else
        <div class="pc-empty">Aucun autre produit publié n’est disponible pour la comparaison. Revenez lorsque le catalogue contiendra un autre produit approuvé.</div>
    @endif
</main>
@endsection
