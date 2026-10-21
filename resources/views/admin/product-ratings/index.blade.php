@extends('layouts.admin')

@section('title', 'Avis consommateurs')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-semibold text-emerald-800">Administration</p>
        <h1 class="mt-1 text-3xl font-bold text-slate-900">Avis consommateurs</h1>
        <p class="mt-2 text-slate-600">Consultez les évaluations publiées et modérez les commentaires.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-600">Avis publiés</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ $totalRatings }}</p></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-600">Note moyenne publiée</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ $averageRating !== null ? number_format((float) $averageRating, 1, ',', ' ').'/5' : '—' }}</p></section>
    </div>

    <section class="grid gap-4 lg:grid-cols-2">
        @foreach([['title' => 'Produits les mieux notés', 'items' => $topProducts], ['title' => 'Produits les moins bien notés', 'items' => $lowestProducts]] as $ranking)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-900">{{ $ranking['title'] }} <span class="text-xs font-normal text-slate-500">(au moins 3 avis)</span></h2>
                @forelse($ranking['items'] as $item)
                    <div class="mt-3 flex justify-between gap-3 border-t border-slate-100 pt-3 text-sm"><span>{{ $item->product?->name ?? 'Produit supprimé' }} · {{ $item->ratings_count }} avis</span><strong>{{ number_format((float) $item->average_rating, 1, ',', ' ') }}/5</strong></div>
                @empty
                    <p class="mt-3 text-sm text-slate-500">Pas encore assez d’avis pour établir un classement.</p>
                @endforelse
            </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-semibold text-slate-900">Répartition des avis publiés</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-5">
            @foreach([5, 4, 3, 2, 1] as $score)
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><span class="text-amber-600">{{ str_repeat('★', $score) }}</span><span class="ml-2 font-semibold">{{ (int) ($distribution[$score] ?? 0) }}</span></div>
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="get" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <label class="text-sm font-medium text-slate-700">Recherche
                <input class="mt-1 w-full rounded-lg border-slate-300" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Consommateur ou produit">
            </label>
            <label class="text-sm font-medium text-slate-700">Produit
                <select class="mt-1 w-full rounded-lg border-slate-300" name="product_id"><option value="">Tous les produits</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select>
            </label>
            <label class="text-sm font-medium text-slate-700">Note
                <select class="mt-1 w-full rounded-lg border-slate-300" name="rating"><option value="">Toutes</option>@foreach([5,4,3,2,1] as $score)<option value="{{ $score }}" @selected((string) ($filters['rating'] ?? '') === (string) $score)>{{ $score }} étoile(s)</option>@endforeach</select>
            </label>
            <label class="text-sm font-medium text-slate-700">Publication
                <select class="mt-1 w-full rounded-lg border-slate-300" name="status"><option value="">Toutes</option><option value="published" @selected(($filters['status'] ?? '') === 'published')>Publié</option><option value="hidden" @selected(($filters['status'] ?? '') === 'hidden')>Masqué</option></select>
            </label>
            <div class="flex items-end gap-2"><button class="rounded-lg bg-emerald-800 px-4 py-2 font-semibold text-white" type="submit">Filtrer</button><a class="rounded-lg border border-slate-300 px-4 py-2" href="{{ route('admin.product-ratings.index') }}">Effacer</a></div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Produit</th><th class="px-4 py-3">Consommateur</th><th class="px-4 py-3">Note</th><th class="px-4 py-3">Commentaire</th><th class="px-4 py-3">Publication</th><th class="px-4 py-3">Détail</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($ratings as $rating)
                        <tr><td class="whitespace-nowrap px-4 py-3">{{ $rating->created_at?->format('d/m/Y H:i') }}</td><td class="px-4 py-3">{{ $rating->product?->name ?? 'Produit supprimé' }}</td><td class="px-4 py-3">{{ $rating->consumer?->name ?? ($rating->consumer_name ?: 'Visiteur') }}</td><td class="whitespace-nowrap px-4 py-3 text-amber-600">{{ str_repeat('★', $rating->rating) }}<span class="sr-only">{{ $rating->rating }}/5</span></td><td class="max-w-sm px-4 py-3"><span class="line-clamp-2">{{ $rating->comment ?: '—' }}</span></td><td class="px-4 py-3">{{ $rating->status === 'published' ? 'Publié' : 'Masqué' }}</td><td class="px-4 py-3"><a class="font-semibold text-emerald-800 underline" href="{{ route('admin.product-ratings.show', $rating) }}">Consulter</a></td></tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucun avis ne correspond à ces critères.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $ratings->links() }}</div>
    </section>
</div>
@endsection
