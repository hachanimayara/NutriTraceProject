@extends('layouts.admin')

@section('title', 'Tableau de bord | NutriTrace')
@section('page-heading', 'Tableau de bord')

@section('content')
@php
    $roleNames = ['admin' => 'Administration', 'producteur' => 'Producteur agricole', 'transformateur' => 'Transformateur', 'distributeur' => 'Distributeur'];
    $firstName = \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ');
    $allValues = collect($chart['series'])->flatMap(fn ($series) => $series['values'])->all();
    $chartMax = max(1, ...($allValues ?: [0]));
    $linePaths = [];
    foreach ($chart['series'] as $series) {
        $points = [];
        foreach ($series['values'] as $index => $value) {
            $points[] = (42 + ($index * 104)).','. (195 - (($value / $chartMax) * 155));
        }
        $linePaths[] = ['path' => 'M '.implode(' L ', $points), 'color' => $series['color']];
    }
    $totalProducts = $role === 'producteur' ? \App\Models\Product::where('user_id', auth()->id())->count() : null;
    $toneClasses = ['mint' => 'dash-tone-mint', 'blue' => 'dash-tone-blue', 'violet' => 'dash-tone-violet', 'amber' => 'dash-tone-amber'];
@endphp

<style>
    .role-dashboard{--dash-ink:#182b24;--dash-muted:#748078;--dash-line:#e0e9e3;--dash-green:#28684d;margin:-1rem;min-height:calc(100vh - 76px);padding:26px;background:#f2f7f4;color:var(--dash-ink)}
    @media(min-width:768px){.role-dashboard{margin:-1.5rem;padding:30px}}
    .dash-head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px}.dash-title{margin:0;color:#172a23;font-size:30px;font-weight:700;letter-spacing:-.035em;line-height:1.2}.dash-subtitle{margin:8px 0 0;color:#76817a;font-size:14px}
    .dash-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.dash-button{display:inline-flex;min-height:46px;align-items:center;justify-content:center;gap:8px;padding:0 15px;border:1px solid var(--dash-line);border-radius:12px;background:#fff;color:#24362e;font-size:13px;font-weight:700;text-decoration:none;transition:transform .18s,box-shadow .18s}.dash-button:hover{transform:translateY(-1px);box-shadow:0 8px 20px #183b2910}.dash-button svg{width:17px;height:17px}.dash-button-primary{border-color:#28684d;background:#28684d;color:#fff;box-shadow:0 8px 18px #28684d20}
    .dash-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(205px,1fr));gap:14px}.dash-stat{display:flex;min-height:94px;align-items:center;gap:13px;padding:15px;border:1px solid var(--dash-line);border-radius:15px;background:#fff;box-shadow:0 3px 10px #152f2410;text-decoration:none;transition:transform .18s,box-shadow .18s}.dash-stat:hover{transform:translateY(-2px);box-shadow:0 10px 22px #183b2912}.dash-icon{display:grid;width:46px;height:46px;flex:0 0 46px;place-items:center;border-radius:13px}.dash-icon svg{width:23px;height:23px;stroke:currentColor}.dash-tone-mint .dash-icon{background:#e8f5ed;color:#367b58}.dash-tone-blue .dash-icon{background:#eaf4f8;color:#428398}.dash-tone-violet .dash-icon{background:#f2edfa;color:#785aa0}.dash-tone-amber .dash-icon{background:#fff6e6;color:#c28a27}.dash-stat-copy{min-width:0;flex:1}.dash-stat-label{display:block;overflow:hidden;color:#79827d;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.dash-stat-value{display:block;margin-top:4px;color:#1c2b25;font-size:24px;font-weight:750;letter-spacing:-.03em;line-height:1.1}.dash-stat-hint{display:block;margin-top:5px;color:#829087;font-size:10px;text-align:right}
    .dash-panels{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(300px,1fr);gap:17px;margin-top:19px}.dash-panel{overflow:hidden;border:1px solid var(--dash-line);border-radius:17px;background:#fff;box-shadow:0 3px 12px #152f2409}.dash-panel-head{display:flex;min-height:82px;align-items:center;justify-content:space-between;gap:13px;padding:18px 21px;border-bottom:1px solid var(--dash-line)}.dash-panel-title{margin:0;color:#25352e;font-size:16px;font-weight:700}.dash-panel-caption{margin:5px 0 0;color:#87918b;font-size:11px}.dash-panel-head .dash-period{padding:9px 11px;border:1px solid var(--dash-line);border-radius:10px;background:white;color:#39483f;font-size:12px;white-space:nowrap}
    .dash-chart{padding:13px 17px 8px}.dash-chart svg{display:block;width:100%;height:245px;overflow:visible}.dash-chart-grid{stroke:#edf1ee;stroke-width:1}.dash-chart-label{fill:#8b958f;font:11px sans-serif}.dash-chart-line{fill:none;stroke-width:3.2;stroke-linecap:round;stroke-linejoin:round}.dash-chart-point{fill:#fff;stroke-width:2.3}.dash-chart-legend{display:flex;flex-wrap:wrap;gap:15px;padding:0 22px 17px}.dash-legend-item{display:inline-flex;align-items:center;gap:7px;color:#77827c;font-size:11px}.dash-legend-dot{width:8px;height:8px;border-radius:50%}
    .dash-alert-count{padding:7px 10px;border-radius:999px;background:#fff7e8;color:#9e741e;font-size:10px;font-weight:700;white-space:nowrap}.dash-alert-list{list-style:none;margin:0;padding:0}.dash-alert{display:flex;align-items:center;gap:12px;padding:13px 17px;border-bottom:1px solid #edf1ee;text-decoration:none;transition:background .15s}.dash-alert:hover,.dash-data-row:hover{background:#f8fbf9}.dash-alert:last-child{border-bottom:0}.dash-alert-icon{display:grid;width:36px;height:36px;flex:0 0 36px;place-items:center;border-radius:11px;background:#fff5e4;color:#bd8321}.dash-alert-icon.critical{background:#fff0ef;color:#c65049}.dash-alert-icon.info{background:#edf5f8;color:#4e8493}.dash-alert-icon svg{width:18px;height:18px;stroke:currentColor}.dash-alert-copy{min-width:0;flex:1}.dash-alert-title{display:block;overflow:hidden;color:#2b3932;font-size:12px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.dash-alert-detail{display:-webkit-box;overflow:hidden;margin-top:4px;color:#8a948e;font-size:10px;line-height:1.45;-webkit-box-orient:vertical;-webkit-line-clamp:2}.dash-arrow{color:#8c9690;font-size:19px}.dash-alert-empty{display:grid;min-height:150px;place-items:center;padding:22px;color:#818c85;text-align:center;font-size:12px}.dash-alert-foot{display:flex;justify-content:center;padding:13px;border-top:1px solid var(--dash-line)}.dash-alert-foot a{color:#2d6b50;font-size:12px;font-weight:700;text-decoration:none}
    .dash-sections{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px;margin-top:19px}.dash-section{min-width:0}.dash-section-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:17px 20px;border-bottom:1px solid var(--dash-line)}.dash-section-head h2{margin:0;color:#25352e;font-size:15px;font-weight:700}.dash-section-head a{color:#317253;font-size:11px;font-weight:700;text-decoration:none;white-space:nowrap}.dash-data-list{list-style:none;margin:0;padding:0}.dash-data-row{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid #edf1ee;text-decoration:none}.dash-data-row:last-child{border-bottom:0}.dash-data-mark{display:grid;width:35px;height:35px;flex:0 0 35px;place-items:center;border-radius:11px;background:#edf6ef;color:#367b58;font-size:11px;font-weight:800}.dash-data-copy{min-width:0;flex:1}.dash-data-title{display:block;overflow:hidden;color:#283830;font-size:12px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.dash-data-subtitle{display:block;overflow:hidden;margin-top:3px;color:#849089;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.dash-data-meta{color:#758078;font-size:10px;white-space:nowrap}.dash-empty{padding:25px;text-align:center;color:#87918b;font-size:12px}
    .dash-categories{display:grid;gap:13px;padding:19px 21px}.dash-category{display:grid;grid-template-columns:minmax(90px,1fr) auto;gap:9px;align-items:center;color:#69766f;font-size:11px}.dash-category-track{grid-column:1/-1;height:7px;overflow:hidden;border-radius:99px;background:#edf1ee}.dash-category-fill{height:100%;border-radius:inherit;background:#4b9168}.dash-evidence{margin-top:17px}
    .dash-quick{margin-top:19px;padding:20px 22px 22px}.dash-quick-title{margin:0;color:#25352e;font-size:16px;font-weight:700}.dash-quick-subtitle{margin:5px 0 0;color:#87918b;font-size:11px}.dash-quick-links{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-top:15px}.dash-quick-link{display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:49px;padding:0 13px;border:1px solid var(--dash-line);border-radius:11px;background:#fff;color:#35453c;font-size:12px;font-weight:650;text-decoration:none;transition:border-color .15s,background .15s}.dash-quick-link:hover{border-color:#9bc4a9;background:#f7fbf8;color:#245d43}.dash-quick-link span:last-child{color:#718078;font-size:17px}
    @media(max-width:1100px){.dash-panels{grid-template-columns:1fr}.dash-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:740px){.role-dashboard{margin:-1rem;padding:20px 14px}.dash-head{align-items:flex-start;flex-direction:column}.dash-title{font-size:25px}.dash-actions{width:100%}.dash-actions .dash-button{flex:1}.dash-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.dash-stat{min-height:84px;gap:9px;padding:11px}.dash-icon{width:39px;height:39px;flex-basis:39px;border-radius:11px}.dash-icon svg{width:20px;height:20px}.dash-stat-value{font-size:21px}.dash-stat-hint{display:none}.dash-sections{grid-template-columns:1fr}.dash-panel-head{padding:16px}.dash-chart{padding:11px 9px}.dash-chart svg{height:210px}.dash-data-row{padding:12px}.dash-data-meta{display:none}}
</style>

<main class="role-dashboard">
    <header class="dash-head">
        <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-[.16em] text-green-800">{{ $roleNames[$role] ?? 'NutriTrace' }}</p>
            <h1 class="dash-title">Bonjour {{ $firstName }}, voici l'essentiel.</h1>
            <p class="dash-subtitle">Suivez les op&eacute;rations et les &eacute;l&eacute;ments qui n&eacute;cessitent votre attention.</p>
        </div>
        <div class="dash-actions">
            <a href="{{ $exportUrl }}" class="dash-button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v4h14v-4"/></svg>Exporter</a>
            <a href="{{ $secondary['url'] }}" class="dash-button">{{ html_entity_decode($secondary['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</a>
            <a href="{{ $primary['url'] }}" class="dash-button dash-button-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>{{ $primary['label'] }}</a>
        </div>
    </header>

    <section class="dash-stats" aria-label="Indicateurs principaux">
        @foreach($stats as $stat)
            <a href="{{ $stat['url'] }}" class="dash-stat {{ $toneClasses[$stat['tone']] ?? 'dash-tone-mint' }}">
                <span class="dash-icon">
                    @switch($loop->index % 4)
                        @case(0)<svg viewBox="0 0 24 24" fill="none" stroke-width="1.7"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4.5 7.7 7.5 4.4 7.5-4.4M12 12v9"/></svg>@break
                        @case(1)<svg viewBox="0 0 24 24" fill="none" stroke-width="1.7"><path d="m3 11 9-7 9 7v9H5v-9"/><path d="M9 20v-6h6v6"/></svg>@break
                        @case(2)<svg viewBox="0 0 24 24" fill="none" stroke-width="1.7"><path d="M4 18 18 4m-9 0h9v9"/><path d="M5 5h4M5 5v4m14 10h-4m4 0v-4"/></svg>@break
                        @default<svg viewBox="0 0 24 24" fill="none" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2M8 8h8"/></svg>
                    @endswitch
                </span>
                <span class="dash-stat-copy"><span class="dash-stat-label">{!! html_entity_decode($stat['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}</span><strong class="dash-stat-value">{{ number_format($stat['value'], 0, ',', ' ') }}</strong></span>
                <span class="dash-stat-hint">{!! html_entity_decode($stat['hint'], ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}</span>
            </a>
        @endforeach
    </section>

    <section class="dash-panels">
        <article class="dash-panel">
            <header class="dash-panel-head"><div><h2 class="dash-panel-title">{!! html_entity_decode($chart['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}</h2><p class="dash-panel-caption">{!! html_entity_decode($chart['caption'], ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}</p></div><span class="dash-period">6 derniers mois</span></header>
            <div class="dash-chart">
                <svg viewBox="0 0 600 245" role="img" aria-label="Graphique d’activit&eacute; des six derniers mois">
                    @foreach([40, 90, 140, 195] as $gridY)<line x1="42" y1="{{ $gridY }}" x2="562" y2="{{ $gridY }}" class="dash-chart-grid"/>@endforeach
                    @foreach($linePaths as $index => $line)<path d="{{ $line['path'] }}" class="dash-chart-line" style="stroke:{{ $line['color'] }}"/>@foreach($chart['series'][$index]['values'] as $pointIndex => $value)<circle cx="{{ 42 + ($pointIndex * 104) }}" cy="{{ 195 - (($value / $chartMax) * 155) }}" r="3.5" class="dash-chart-point" style="stroke:{{ $line['color'] }}"/>@endforeach @endforeach
                    @foreach($chart['labels'] as $index => $label)<text x="{{ 42 + ($index * 104) }}" y="222" text-anchor="middle" class="dash-chart-label">{{ html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</text>@endforeach
                </svg>
            </div>
            <div class="dash-chart-legend">@foreach($chart['series'] as $series)<span class="dash-legend-item"><i class="dash-legend-dot" style="background:{{ $series['color'] }}"></i>{!! html_entity_decode($series['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}</span>@endforeach</div>
        </article>

        <article class="dash-panel">
            <header class="dash-panel-head"><div><h2 class="dash-panel-title">Alertes &agrave; examiner</h2><p class="dash-panel-caption">Anomalies et informations qui demandent un suivi</p></div><span class="dash-alert-count">{{ $alerts['count'] }} alerte(s)</span></header>
            @if($alerts['items']->isNotEmpty())
                <ul class="dash-alert-list">
                    @foreach($alerts['items'] as $alert)
                        <li><a class="dash-alert" href="{{ $alert['url'] }}"><span class="dash-alert-icon {{ $alert['severity'] }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg></span><span class="dash-alert-copy"><span class="dash-alert-title">{{ html_entity_decode($alert['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span><span class="dash-alert-detail">{{ html_entity_decode($alert['detail'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span></span><span class="dash-arrow">&rsaquo;</span></a></li>
                    @endforeach
                </ul>
                <footer class="dash-alert-foot"><a href="{{ $alertsUrl }}">Voir les &eacute;l&eacute;ments &agrave; traiter&nbsp; &rsaquo;</a></footer>
            @else
                <div class="dash-alert-empty">Aucune alerte ouverte pour le moment.</div>
            @endif
        </article>
    </section>

    @if($role === 'producteur' && $categories->isNotEmpty())
        <section class="dash-sections">
            <article class="dash-panel"><header class="dash-section-head"><div><h2>Produits par cat&eacute;gorie</h2></div><a href="/workspace/products">Voir les produits&nbsp; &rsaquo;</a></header>
                <div class="dash-categories">@foreach($categories as $category)<div class="dash-category"><span>{{ $category->category ?: 'Autre' }}</span><strong>{{ $category->total }}</strong><div class="dash-category-track"><div class="dash-category-fill" style="width:{{ $totalProducts ? max(3, ($category->total / $totalProducts) * 100) : 0 }}%"></div></div></div>@endforeach</div>
            </article>
        </section>
    @endif

    @if($sections)
        <section class="dash-sections">
            @foreach($sections as $section)
                <article class="dash-panel dash-section">
                    <header class="dash-section-head"><h2>{!! html_entity_decode($section['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}</h2><a href="{{ $section['url'] }}">Tout voir&nbsp; &rsaquo;</a></header>
                    @if($section['items'])
                        <ul class="dash-data-list">@foreach($section['items'] as $item)<li><a class="dash-data-row" href="{{ $item['url'] }}"><span class="dash-data-mark">{{ mb_strtoupper(mb_substr(strip_tags(html_entity_decode($item['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 1)) }}</span><span class="dash-data-copy"><span class="dash-data-title">{{ html_entity_decode($item['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span><span class="dash-data-subtitle">{{ html_entity_decode($item['subtitle'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span></span><span class="dash-data-meta">{{ html_entity_decode($item['meta'], ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span></a></li>@endforeach</ul>
                    @else
                        <div class="dash-empty">Aucun &eacute;l&eacute;ment enregistr&eacute; pour le moment.</div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    <section class="dash-panel dash-quick">
        <h2 class="dash-quick-title">Acc&egrave;s rapides aux gestions</h2>
        <p class="dash-quick-subtitle">Modules disponibles dans votre espace {{ strtolower($roleNames[$role] ?? '') }}.</p>
        <nav class="dash-quick-links" aria-label="Acc&egrave;s rapides">@foreach($quickLinks as $item)<a href="{{ $item['path'] }}" class="dash-quick-link"><span>{{ $item['name'] }}</span><span>&rsaquo;</span></a>@endforeach</nav>
    </section>
</main>
@endsection
