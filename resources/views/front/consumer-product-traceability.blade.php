@extends('layouts.public')
@section('title', 'Traçabilité · '.$product->name.' | NutriTrace')
@section('content')
@php
    $location = $product->origin ?: trim(implode(', ', array_filter([$product->farm?->location, $product->farm?->country])));
    $latestHarvest = $product->lots->pluck('produced_at')->filter()->sortDesc()->first();
@endphp
<style>
.consumer-trace{--ct-green:#2d6a4f;--ct-line:#dfe7e2;color:#1e2b25}.consumer-trace .ct-back{display:inline-flex;align-items:center;gap:8px;color:#276b4e;font-weight:650;text-decoration:none;margin:6px 0 24px}.consumer-trace .ct-back:hover{text-decoration:underline}.consumer-trace .ct-card{border:1px solid var(--ct-line);border-radius:18px;background:#fff;overflow:hidden}.consumer-trace .ct-hero{display:grid;grid-template-columns:minmax(280px,.85fr) 1.15fr;min-height:390px}.consumer-trace .ct-photo{position:relative;min-height:390px;background:#f2f6f2;display:grid;place-items:center;color:#6f917c;font-size:64px}.consumer-trace .ct-photo img{width:100%;height:100%;position:absolute;inset:0;object-fit:cover}.consumer-trace .ct-origin-badge{position:absolute;z-index:1;top:22px;left:22px;border-radius:99px;background:#e8f5ec;color:#28684c;padding:9px 14px;font-size:12px;font-weight:700}.consumer-trace .ct-origin-badge.missing{background:#fff4df;color:#9c6212}.consumer-trace .ct-summary{padding:clamp(24px,4vw,58px);display:flex;flex-direction:column;justify-content:center}.consumer-trace .ct-meta{font-size:12px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#39795d}.consumer-trace h1,.consumer-trace .ct-section-title{color:#1e3027}.consumer-trace h1{font-size:clamp(30px,3.5vw,48px);font-weight:700;line-height:1.12;margin:10px 0}.consumer-trace .ct-lead{font-size:17px;color:#718078;line-height:1.6;margin:0 0 26px}.consumer-trace .ct-facts{display:grid;grid-template-columns:1fr 1fr;gap:22px 30px}.consumer-trace .ct-facts span{display:block;color:#78847e;font-size:13px}.consumer-trace .ct-facts strong{display:block;margin-top:6px;color:#26352d;font-size:16px}.consumer-trace .ct-progress{margin-top:28px}.consumer-trace .ct-progress-label{display:flex;justify-content:space-between;gap:12px;font-size:12px;font-weight:700}.consumer-trace .ct-progress-track{height:9px;margin-top:10px;border-radius:99px;background:#e6ebe7;overflow:hidden}.consumer-trace .ct-progress-track span{display:block;height:100%;border-radius:inherit;background:#4b9468}.consumer-trace .ct-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:22px;margin-top:22px}.consumer-trace .ct-timeline-card,.consumer-trace .ct-side-card{padding:26px}.consumer-trace .ct-overline{font-size:11px;font-weight:800;letter-spacing:.16em;color:#40805e;text-transform:uppercase}.consumer-trace .ct-card-heading{display:flex;justify-content:space-between;align-items:center;gap:14px}.consumer-trace .ct-count{border-radius:99px;background:#e8f5ec;color:#28684c;padding:8px 12px;font-size:12px;font-weight:700;white-space:nowrap}.consumer-trace .ct-events{list-style:none;padding:0;margin:24px 0 0}.consumer-trace .ct-event{position:relative;display:grid;grid-template-columns:42px 110px minmax(0,1fr);gap:14px;padding:0 0 30px}.consumer-trace .ct-event:not(:last-child):before{position:absolute;content:'';top:38px;bottom:0;left:19px;width:1px;background:#d2dfd5}.consumer-trace .ct-step{position:relative;z-index:1;width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:#e8f5ec;color:#28684c;font-size:13px;font-weight:800}.consumer-trace .ct-date{padding-top:11px;color:#77847d;font-size:12px}.consumer-trace .ct-event h3{font-size:16px;margin:7px 0 6px;color:#26352d;font-weight:600}.consumer-trace .ct-event strong{font-size:13px;color:#34463b}.consumer-trace .ct-event p{margin:5px 0 0;color:#78847e;font-size:13px;line-height:1.5}.consumer-trace .ct-side{display:grid;align-content:start;gap:18px}.consumer-trace .ct-side-highlight{background:#eff6f0}.consumer-trace .ct-side h2{font-size:21px;font-weight:650;margin:5px 0 14px}.consumer-trace .ct-side p{color:#718078;font-size:13px;line-height:1.6}.consumer-trace .ct-info-list{list-style:none;margin:12px 0 0;padding:0;display:grid;gap:10px}.consumer-trace .ct-info-list li{display:flex;gap:9px;align-items:flex-start;font-size:13px;color:#52635a}.consumer-trace .ct-dot{display:inline-block;width:9px;height:9px;margin-top:4px;border-radius:50%;background:#4b9468;flex:none}.consumer-trace .ct-dot.missing{background:#d9952e}.consumer-trace .ct-subsections{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:22px}.consumer-trace .ct-record{padding:23px}.consumer-trace .ct-record h2{font-size:21px;font-weight:650;margin:7px 0 18px}.consumer-trace .ct-record-item{padding:14px 0;border-top:1px solid #edf0ed}.consumer-trace .ct-record-item strong{display:block;color:#2d4538}.consumer-trace .ct-record-item p{margin:5px 0 0;color:#78847e;font-size:12px;line-height:1.5}.consumer-trace .ct-empty{padding:18px;border-radius:12px;background:#f7f9f7;color:#728078;font-size:13px}.consumer-trace .ct-btn{display:inline-flex;justify-content:center;align-items:center;border-radius:11px;padding:12px 18px;background:#2d6a4f;color:white;text-decoration:none;font-weight:650}.consumer-trace .ct-btn:hover{background:#24573f;color:#fff}
@media(max-width:950px){.consumer-trace .ct-layout{grid-template-columns:1fr}.consumer-trace .ct-side{grid-template-columns:1fr 1fr}.consumer-trace .ct-hero{grid-template-columns:1fr 1.1fr}}@media(max-width:700px){.consumer-trace .ct-hero{grid-template-columns:1fr}.consumer-trace .ct-photo{min-height:280px}.consumer-trace .ct-layout,.consumer-trace .ct-subsections,.consumer-trace .ct-side{grid-template-columns:1fr}.consumer-trace .ct-timeline-card,.consumer-trace .ct-side-card{padding:20px}.consumer-trace .ct-event{grid-template-columns:40px 1fr;gap:8px 12px}.consumer-trace .ct-date{grid-column:2;padding-top:3px}.consumer-trace .ct-event-copy{grid-column:2}.consumer-trace .ct-card-heading{align-items:flex-start;flex-direction:column}}
.consumer-trace{max-width:1500px;margin-inline:auto;padding-inline:24px;box-sizing:border-box}
@media(max-width:600px){.consumer-trace{padding-inline:16px}}
.consumer-trace .ct-rating-card{padding:26px;margin-top:22px}.consumer-trace .ct-rating-summary{display:flex;align-items:center;gap:12px;margin:18px 0}.consumer-trace .ct-rating-average{font-size:30px;font-weight:750;color:#1e3027}.consumer-trace .ct-rating-stars{color:#d99a20;letter-spacing:2px}.consumer-trace .ct-rating-distribution{max-width:520px;display:grid;gap:7px;margin:18px 0 24px}.consumer-trace .ct-rating-row{display:grid;grid-template-columns:42px minmax(80px,1fr) 32px;align-items:center;gap:10px;font-size:13px;color:#718078}.consumer-trace .ct-rating-track{height:8px;border-radius:99px;background:#edf1ed;overflow:hidden}.consumer-trace .ct-rating-track span{height:100%;display:block;background:#d99a20}.consumer-trace .ct-rating-form{border-top:1px solid #edf0ed;padding-top:20px;margin-top:20px;max-width:650px}.consumer-trace .ct-rating-form label{display:block;font-size:14px;font-weight:650;color:#34463b;margin-bottom:8px}.consumer-trace .ct-rating-picker{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:4px;margin:4px 0 16px}.consumer-trace .ct-rating-picker label{margin:0;cursor:pointer;color:#cbd3cd;font-size:28px;line-height:1}.consumer-trace .ct-rating-picker input{position:absolute;opacity:0;width:1px;height:1px}.consumer-trace .ct-rating-picker label:has(input:checked),.consumer-trace .ct-rating-picker label:has(input:checked)~label{color:#d99a20}.consumer-trace .ct-rating-picker label:focus-within{outline:2px solid #2d6a4f;outline-offset:3px;border-radius:3px}.consumer-trace .ct-rating-form textarea{width:100%;min-height:100px;padding:12px;border:1px solid #dfe7e2;border-radius:10px;resize:vertical}.consumer-trace .ct-rating-list{display:grid;gap:14px;margin-top:24px}.consumer-trace .ct-rating-item{padding:16px;border:1px solid #edf0ed;border-radius:12px}.consumer-trace .ct-rating-item header{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.consumer-trace .ct-rating-item p{margin:10px 0 0;color:#52635a;white-space:pre-line}.consumer-trace .ct-rating-note{padding:12px;border-radius:10px;background:#fff4df;color:#80580e;font-size:13px}
@media(max-width:600px){.consumer-trace .ct-rating-card{padding:20px}.consumer-trace .ct-rating-summary{flex-wrap:wrap}}
</style>
<style>
.consumer-trace .ct-actions{display:flex;flex-wrap:wrap;gap:10px}
.consumer-trace .ct-btn-secondary{background:#fff;color:#2d6a4f;border:1px solid #2d6a4f}
.consumer-trace .ct-btn-secondary:hover{background:#eff6f0;color:#24573f}
</style>
<main class="consumer-trace">
    <a class="ct-back" href="{{ route('public.catalogue') }}"><span aria-hidden="true">←</span> Retour au catalogue</a>
    <section class="ct-card ct-hero">
        <div class="ct-photo"><span class="ct-origin-badge {{ $location ? '' : 'missing' }}">{{ $location ? 'Origine renseignée' : 'Origine à compléter' }}</span>@if($product->image_name)<img src="{{ route('public.catalogue.product-image',$product->id) }}" alt="{{ $product->name }}">@else<span aria-hidden="true">{{ $product->category === 'Fruits' ? '◉' : '✿' }}</span>@endif</div>
        <div class="ct-summary"><span class="ct-meta">{{ $product->category }} · {{ $product->lots->pluck('code')->implode(', ') ?: $product->reference }}</span><h1>{{ $product->name }}</h1><p class="ct-lead">{{ $product->description ?: 'Consultez les informations de provenance et les étapes enregistrées pour ce produit.' }}</p>
            <div class="ct-facts"><div><span>Origine</span><strong>{{ $location ?: 'Non renseignée' }}</strong></div><div><span>Producteur</span><strong>{{ $product->owner?->name ?: ($product->farm?->name ?: 'Producteur non renseigne') }}</strong></div><div><span>Récolte la plus récente</span><strong>{{ $latestHarvest?->format('d/m/Y') ?: 'Non renseignée' }}</strong></div><div><span>Dernière mise à jour</span><strong>{{ $product->updated_at?->format('d/m/Y') ?: '—' }}</strong></div></div>
            <div class="ct-progress"><div class="ct-progress-label"><span>Informations renseignées</span><span>{{ $completeness }}%</span></div><div class="ct-progress-track"><span style="width:{{ $completeness }}%"></span></div></div>
            <div class="ct-actions mt-6"><a class="ct-btn" href="#parcours-documente">Voir la traçabilité</a><a class="ct-btn ct-btn-secondary" href="{{ route('public.products.compare', $product->id) }}">Comparer ce produit</a></div>
        </div>
    </section>
    <p class="mt-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-xs leading-5 text-gray-600">Les étapes et indicateurs ci-dessous proviennent des informations enregistrées par les acteurs de la chaîne ; ils ne sont pas automatiquement vérifiés par NutriTrace. Les certifications affichées ont le statut valide et ne sont pas expirées.</p>
    <div id="parcours-documente" class="ct-layout">
        <section class="ct-card ct-timeline-card"><div class="ct-card-heading"><div><span class="ct-overline">Parcours documenté</span><h2 class="ct-section-title text-lg font-semibold">De la production à la distribution</h2></div><span class="ct-count">{{ $events->count() }} étape(s) enregistrée(s)</span></div>
            @if($events->isNotEmpty())<ol class="ct-events">@foreach($events as $event)<li class="ct-event"><span class="ct-step">{{ $loop->iteration }}</span><time class="ct-date">{{ $event['date']?->format('d/m/Y · H:i') ?? 'Date inconnue' }}</time><div class="ct-event-copy"><h3>{{ $event['title'] }}</h3><strong>{{ $event['place'] ?: 'Lieu non renseigné' }}</strong><p>{{ $event['description'] }}</p></div></li>@endforeach</ol>
            @else<div class="ct-empty mt-5">Aucune étape de production, transformation ou distribution n’est encore enregistrée pour les lots actifs de ce produit.</div>@endif
        </section>
        <aside class="ct-side">
            <section class="ct-card ct-side-card ct-side-highlight"><span class="ct-overline">Résumé des données</span><h2>Comprendre ce produit</h2><p>{{ $product->name }} est classé dans « {{ $product->category }} ». @if($location)Son origine déclarée est {{ $location }}.@elseAucune origine n’est renseignée.@endif @if($product->farm)Le producteur associé est {{ $product->farm->name }}.@endif</p><p class="mb-0">Ce résumé utilise uniquement les informations présentes dans la fiche.</p></section>
            <section class="ct-card ct-side-card"><h2>État des informations</h2><p>{{ $informationCount }} information(s) renseignée(s) sur {{ count($completenessItems) }}.</p><ul class="ct-info-list">@foreach($completenessItems as $label=>$present)<li><span class="ct-dot {{ $present?'':'missing' }}"></span><span>{{ $label }} · {{ $present?'renseigné':'manquant' }}</span></li>@endforeach</ul></section>
        </aside>
    </div>
    <section id="avis-consommateurs" class="ct-card ct-rating-card">
        <div class="ct-card-heading"><div><span class="ct-overline">Avis consommateurs</span><h2 class="ct-section-title text-lg font-semibold">Évaluations du produit</h2></div></div>
        @if($ratingCount > 0)
            <div class="ct-rating-summary"><strong class="ct-rating-average">{{ number_format((float) $averageRating, 1, ',', ' ') }}/5</strong><span class="ct-rating-stars" aria-label="Note moyenne {{ number_format((float) $averageRating, 1, ',', ' ') }} sur 5">{{ str_repeat('★', (int) round((float) $averageRating)) }}{{ str_repeat('☆', 5 - (int) round((float) $averageRating)) }}</span><span>{{ $ratingCount }} avis publié(s)</span></div>
            <div class="ct-rating-distribution" aria-label="Répartition des notes">
                @foreach([5, 4, 3, 2, 1] as $score)
                    @php($scoreCount = (int) ($ratingDistribution[$score] ?? 0))
                    <div class="ct-rating-row"><span>{{ $score }} étoile(s)</span><div class="ct-rating-track"><span style="width:{{ $ratingCount ? ($scoreCount / $ratingCount * 100) : 0 }}%"></span></div><span>{{ $scoreCount }}</span></div>
                @endforeach
            </div>
        @else
            <p class="ct-empty mt-5">Aucune évaluation pour le moment.</p>
        @endif

        @if($ownRating)
            <p class="ct-rating-note mt-4" role="status">{{ session('status') ?: 'Merci pour votre avis. Il a bien été enregistré et ne peut plus être modifié.' }}@if($ownRating->status === 'hidden') Il est actuellement masqué par l’administration.@endif</p>
        @elseif(auth()->check() && auth()->user()->role === 'consommateur')
            @if(session('status'))<p class="ct-rating-note" role="status">{{ session('status') }}</p>@endif
            <form class="ct-rating-form" method="post" action="{{ route('consumer.product-ratings.store', $product->id) }}">
                @csrf
                <label>Votre note <span aria-hidden="true">*</span></label>
                <div class="ct-rating-picker" role="radiogroup" aria-label="Choisissez une note de 1 à 5">
                    @for($star = 5; $star >= 1; $star--)
                        <label for="product-rating-{{ $star }}"><input id="product-rating-{{ $star }}" type="radio" name="rating" value="{{ $star }}" @checked((int) old('rating', $ownRating?->rating) === $star) required><span aria-hidden="true">★</span><span class="sr-only">{{ $star }} étoile(s)</span></label>
                    @endfor
                </div>
                @error('rating')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                <label for="product-rating-comment">Commentaire facultatif</label>
                <textarea id="product-rating-comment" name="comment" maxlength="1000">{{ old('comment', $ownRating?->comment) }}</textarea>
                @error('comment')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                <button class="ct-btn mt-4" type="submit">Envoyer mon avis</button>
            </form>
        @elseif(!auth()->check())
            @if(session('status'))<p class="ct-rating-note" role="status">{{ session('status') }}</p>@endif
            <form class="ct-rating-form" method="post" action="{{ route('consumer.product-ratings.store', $product->id) }}">
                @csrf
                <label>Votre note <span aria-hidden="true">*</span></label>
                <div class="ct-rating-picker" role="radiogroup" aria-label="Choisissez une note de 1 à 5">
                    @for($star = 5; $star >= 1; $star--)
                        <label for="guest-product-rating-{{ $star }}"><input id="guest-product-rating-{{ $star }}" type="radio" name="rating" value="{{ $star }}" @checked((int) old('rating', $ownRating?->rating) === $star) required><span aria-hidden="true">★</span><span class="sr-only">{{ $star }} étoile(s)</span></label>
                    @endfor
                </div>
                @error('rating')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                <label for="guest-rating-name">Votre nom (facultatif)</label>
                <input id="guest-rating-name" class="mb-4 w-full rounded-lg border border-gray-300 p-3" name="name" maxlength="80" value="{{ old('name', $ownRating?->consumer_name) }}" autocomplete="name">
                @error('name')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                <label for="guest-rating-comment">Commentaire facultatif</label>
                <textarea id="guest-rating-comment" name="comment" maxlength="1000">{{ old('comment', $ownRating?->comment) }}</textarea>
                @error('comment')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden"><label for="rating-website">Ne pas remplir</label><input id="rating-website" name="website" tabindex="-1" autocomplete="off"></div>
                <p class="mt-2 text-xs text-gray-500">Aucun compte n’est nécessaire. Les avis sont visibles sur la fiche produit et peuvent être modérés par l’administration.</p>
                <button class="ct-btn mt-4" type="submit">Envoyer mon avis</button>
            </form>
        @else
            <p class="mt-5 text-sm text-gray-600">La notation est réservée aux comptes consommateurs.</p>
        @endif

        @if($ratings->isNotEmpty())
            <div class="ct-rating-list">
                @foreach($ratings as $rating)
                    <article class="ct-rating-item"><header><span class="ct-rating-stars" aria-label="{{ $rating->rating }} sur 5">{{ str_repeat('★', $rating->rating) }}{{ str_repeat('☆', 5 - $rating->rating) }}</span><time>{{ $rating->created_at?->format('d/m/Y') }}</time></header><p>{{ $rating->consumer?->name ?? $rating->consumer_name ?? 'Visiteur' }}</p>@if($rating->comment)<p>{{ $rating->comment }}</p>@endif</article>
                @endforeach
            </div>
            <div class="mt-5">{{ $ratings->links() }}</div>
        @endif
    </section>
    <div class="ct-subsections">
        <section class="ct-card ct-record"><span class="ct-overline">Certifications</span><h2>Documents associés</h2>@forelse($product->certifications as $certification)<div class="ct-record-item"><strong>{{ $certification->title }}</strong><p>{{ $certification->scheme }} · {{ $certification->issuer }}</p><p>Certificat {{ $certification->certificate_number }}@if($certification->expires_at) · expire le {{ $certification->expires_at->format('d/m/Y') }}@endif</p></div>@empty<div class="ct-empty">Aucune certification valide n’est associée à ce produit.</div>@endforelse</section>
        <section class="ct-card ct-record"><span class="ct-overline">Impact environnemental</span><h2>Indicateurs disponibles</h2>@forelse($product->indicators as $indicator)<div class="ct-record-item"><strong>{{ number_format((float)$indicator->value,3,',',' ') }} {{ $indicator->unit }} · {{ $indicator->name }}</strong><p>{{ $indicator->stage }}@if($indicator->measured_at) · mesuré le {{ $indicator->measured_at->format('d/m/Y') }}@endif</p><p>{{ $indicator->method ?: 'Méthode non renseignée' }}@if($indicator->source_notes) · {{ $indicator->source_notes }}@endif</p></div>@empty<div class="ct-empty">Aucun indicateur environnemental n’est associé à ce produit.</div>@endforelse</section>
    </div>
    <div class="mt-5"><a class="ct-btn" href="{{ route('public.catalogue') }}">Retour au catalogue</a></div>
</main>
@endsection
