@extends('layouts.admin')

@section('title', 'QR Codes et traçabilité | NutriTrace')
@section('page-heading', 'QR Codes et traçabilité')

@section('content')
@vite('resources/js/producer-qr-codes.js')
<style>
    .qr-page{--qr-green:#28684d;--qr-line:#dfe8e2;color:#1d2d25}.qr-page .qr-card{border:1px solid var(--qr-line);border-radius:17px;background:#fff;box-shadow:0 4px 16px #19372908}.qr-page .qr-table th{padding:13px 17px;background:#f7f9f7;color:#76847b;text-align:left;font-size:11px;letter-spacing:.06em;text-transform:uppercase}.qr-page .qr-table td{padding:15px 17px;border-top:1px solid #edf1ee;font-size:13px}.qr-page .qr-button{display:inline-flex;min-height:40px;align-items:center;justify-content:center;gap:8px;border:1px solid #dbe5de;border-radius:10px;background:#fff;padding:0 13px;color:#2b4235;font-size:12px;font-weight:700;text-decoration:none;cursor:pointer}.qr-page .qr-button-primary{border-color:var(--qr-green);background:var(--qr-green);color:#fff}.qr-page .qr-button:disabled{cursor:not-allowed;opacity:.5}.qr-page .qr-badge{display:inline-flex;border-radius:99px;background:#e9f5ee;padding:6px 10px;color:#28684d;font-size:11px;font-weight:750}.qr-page .qr-badge.draft{background:#fff5e5;color:#91651b}.qr-page .qr-badge.archived{background:#f1f3f2;color:#69746d}.qr-page .qr-toolbar input,.qr-page .qr-toolbar select{min-height:43px;border:1px solid #dce5df;border-radius:10px;background:#fff;padding:0 12px;color:#25352d}.qr-page .qr-toolbar input{width:100%}.qr-page #product-qr-dialog{position:fixed;inset:0;margin:auto;width:min(480px,calc(100vw - 24px));max-height:calc(100dvh - 24px);padding:0;border:1px solid #dce7df;border-radius:20px;background:#fff;color:#1d2d25;box-shadow:0 25px 90px #10251b40}.qr-page #product-qr-dialog[open]{display:block}.qr-page #product-qr-dialog::backdrop{background:#10251b99;backdrop-filter:blur(4px)}.qr-page .qr-dialog-body{padding:26px;text-align:center}.qr-page #product-qr-canvas{display:grid;min-height:320px;place-items:center;margin:18px auto}.qr-page #product-qr-canvas canvas{max-width:100%;height:auto!important}.qr-page .qr-dialog-actions{display:flex;flex-wrap:wrap;justify-content:center;gap:9px}.qr-page #product-qr-status{margin:12px 0;border-radius:10px;background:#f1f5f2;padding:10px;font-size:12px}.qr-page #product-qr-status[data-kind="error"]{background:#fff0ed;color:#a53a30}.qr-page #product-qr-status[data-kind="success"]{background:#e9f5ee;color:#28684d}.qr-page .qr-empty{padding:34px;text-align:center;color:#718078;font-size:13px}
    @media(max-width:720px){.qr-page .qr-scroll{overflow-x:auto}.qr-page .qr-table{min-width:650px}.qr-page .qr-dialog-body{padding:20px}}
</style>

<main class="qr-page space-y-5" data-producer-qr-list>
    <nav class="flex items-center gap-2 text-xs text-gray-500"><a href="{{ route('producteur.dashboard') }}">Tableau de bord</a><span aria-hidden="true">›</span><span>QR Codes et traçabilité</span></nav>
    <header class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[.14em] text-green-800">Espace producteur</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">QR Codes et traçabilité</h1><p class="mt-2 text-sm text-gray-500">Générez les codes menant aux fiches publiques actualisées de vos produits.</p></div><a href="{{ route('workspace.resources.index', 'products') }}" class="qr-button">Gérer mes produits</a></header>

    @if(!$publicUrlReady)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">Le domaine public n’est pas configuré pour créer des liens QR sûrs. Configurez <code>NUTRITRACE_PUBLIC_URL</code> avec l’adresse HTTPS publique du site, puis rechargez cette page.</div>
    @endif

    <section class="qr-card overflow-hidden">
        <form method="get" class="qr-toolbar flex flex-wrap gap-3 border-b border-gray-100 p-4" role="search">
            <label class="min-w-[220px] flex-1"><span class="sr-only">Rechercher un produit</span><input type="search" name="q" value="{{ request('q') }}" maxlength="120" placeholder="Rechercher par nom ou référence…"></label>
            <label><span class="sr-only">Filtrer par statut</span><select name="status"><option value="">Tous les statuts</option><option value="draft" @selected(request('status')==='draft')>Brouillon</option><option value="published" @selected(request('status')==='published')>Publié</option><option value="archived" @selected(request('status')==='archived')>Archivé</option></select></label>
            <label><span class="sr-only">Filtrer par catégorie</span><select name="category"><option value="">Toutes les catégories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></label>
            <button class="qr-button qr-button-primary" type="submit">Filtrer</button>
            @if(request()->hasAny(['q','status','category']))<a class="qr-button" href="{{ route('workspace.qr-codes.index') }}">Réinitialiser</a>@endif
        </form>
        <div class="qr-scroll"><table class="qr-table w-full"><thead><tr><th>Produit</th><th>Référence</th><th>Statut</th><th>État du QR code</th><th class="text-right">Actions</th></tr></thead><tbody>
            @forelse($products as $product)
                @php
                    $isPublic = $product->status === 'published' && $publicUrlReady && filled($product->public_id);
                    $publicProductUrl = $isPublic ? $baseUrl.route('public.products.qr', $product->public_id, false) : '';
                    $statusLabel = ['published' => 'Publié', 'draft' => 'Brouillon', 'archived' => 'Archivé'][$product->status] ?? $product->status;
                @endphp
                <tr>
                    <td><strong class="text-gray-900">{{ $product->name }}</strong><span class="mt-1 block text-xs text-gray-500">{{ $product->category ?: 'Catégorie non renseignée' }}</span></td>
                    <td class="text-gray-600">{{ $product->reference }}</td>
                    <td><span class="qr-badge {{ $product->status }}">{{ $statusLabel }}</span></td>
                    <td>@if($isPublic)<span class="text-sm font-medium text-green-800">Disponible</span>@elseif($product->status!=='published')<span class="text-sm text-gray-500">Après publication</span>@else<span class="text-sm text-amber-800">URL publique à configurer</span>@endif</td>
                    <td class="text-right">@if($isPublic)<button type="button" class="qr-button qr-button-primary" data-open-product-qr data-url="{{ $publicProductUrl }}" data-name="{{ $product->name }}" data-reference="{{ $product->reference }}">Générer / voir le QR code</button>@else<button type="button" class="qr-button" disabled>QR non disponible</button>@endif</td>
                </tr>
            @empty<tr><td colspan="5" class="qr-empty">Aucun produit ne correspond à votre recherche.</td></tr>@endforelse
        </tbody></table></div>
        <x-pagination :paginator="$products" />
    </section>
</main>

<dialog id="product-qr-dialog" aria-labelledby="product-qr-title">
    <div class="qr-dialog-body"><div class="flex items-start justify-between gap-4 text-left"><div><h2 id="product-qr-title" class="text-xl font-semibold text-gray-900"></h2><p id="product-qr-reference" class="mt-1 text-xs text-gray-500"></p></div><button type="button" data-close-product-qr class="qr-button" aria-label="Fermer">×</button></div>
        <div id="product-qr-canvas" aria-label="Aperçu du QR code"></div>
        <p id="product-qr-status" role="status" aria-live="polite"></p>
        <div class="qr-dialog-actions"><button id="product-qr-download" type="button" class="qr-button qr-button-primary" disabled>Télécharger en PNG</button><button id="product-qr-print" type="button" class="qr-button" disabled>Imprimer</button><a id="product-qr-public-link" class="qr-button" href="#" target="_blank" rel="noopener noreferrer">Voir la fiche publique</a></div>
    </div>
</dialog>
@endsection
