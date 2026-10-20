@props(['paginator'])

<nav class="nt-pagination" aria-label="Pagination">
        <p class="nt-pagination-count">Affichage de {{ $paginator->firstItem() ?? 0 }} à {{ $paginator->lastItem() ?? 0 }} sur {{ $paginator->total() }}</p>
        <div class="nt-pagination-links">
            @if($paginator->onFirstPage())
                <span class="nt-page nt-page-disabled" aria-disabled="true">Précédent</span>
            @else
                <a class="nt-page" href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a>
            @endif

            @php($startPage = max(1, $paginator->currentPage() - 1))
            @php($endPage = min($paginator->lastPage(), $paginator->currentPage() + 1))
            @if($startPage > 1)
                <a class="nt-page" href="{{ $paginator->url(1) }}" aria-label="Page 1">1</a>
                @if($startPage > 2)<span class="nt-page nt-page-ellipsis" aria-hidden="true">…</span>@endif
            @endif
            @for($page = $startPage; $page <= $endPage; $page++)
                @if($page === $paginator->currentPage())
                    <span class="nt-page nt-page-current" aria-current="page">{{ $page }}</span>
                @else
                    <a class="nt-page" href="{{ $paginator->url($page) }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                @endif
            @endfor
            @if($endPage < $paginator->lastPage())
                @if($endPage < $paginator->lastPage() - 1)<span class="nt-page nt-page-ellipsis" aria-hidden="true">…</span>@endif
                <a class="nt-page" href="{{ $paginator->url($paginator->lastPage()) }}" aria-label="Page {{ $paginator->lastPage() }}">{{ $paginator->lastPage() }}</a>
            @endif
            @if($paginator->hasMorePages())
                <a class="nt-page" href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a>
            @else
                <span class="nt-page nt-page-disabled" aria-disabled="true">Suivant</span>
            @endif
        </div>
</nav>
    <style>
        .nt-pagination{box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;width:100%;padding:18px 20px;border-top:1px solid #e1e8e2;background:#fff;color:#64746b}
        .nt-pagination-count{margin:0;font-size:12px;line-height:1.5}
        .nt-pagination-links{display:flex;align-items:center;gap:7px}
        .nt-page{display:inline-flex;min-width:30px;height:36px;align-items:center;justify-content:center;padding:0 10px;border:1px solid #d9e2dc;border-radius:7px;background:#fff;color:#26352d;text-decoration:none;font-size:12px;line-height:1;white-space:nowrap}
        a.nt-page:hover{border-color:#2d6a4f;color:#24573f;background:#f3f8f4}
        .nt-page-current{border-color:#245f48;background:#245f48;color:#fff;font-weight:700}
        .nt-page-disabled{color:#9aa69f;background:#fff}
        .nt-page-ellipsis{min-width:16px;padding:0;border-color:transparent}
        @media(max-width:560px){.nt-pagination{align-items:flex-start;flex-direction:column}.nt-pagination-links{gap:5px}.nt-page{min-width:28px;height:34px;padding-inline:8px}}
    </style>
