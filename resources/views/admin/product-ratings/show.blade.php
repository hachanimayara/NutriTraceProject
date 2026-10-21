@extends('layouts.admin')

@section('title', 'Détail d’un avis')

@section('content')
<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <a class="text-sm font-semibold text-emerald-800 underline" href="{{ route('admin.product-ratings.index') }}">← Retour aux avis</a>
    @if(session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-emerald-900" role="status">{{ session('status') }}</div>@endif
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-semibold text-emerald-800">Détail de l’avis</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $rating->product?->name ?? 'Produit supprimé' }}</h1>
        <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-slate-500">Consommateur</dt><dd class="font-semibold">{{ $rating->consumer?->name ?? ($rating->consumer_name ?: 'Visiteur sans nom communiqué') }}</dd></div>
            <div><dt class="text-sm text-slate-500">E-mail</dt><dd>{{ $rating->consumer?->email ?? '—' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Référence produit</dt><dd>{{ $rating->product?->reference ?? '—' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Date</dt><dd>{{ $rating->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Note</dt><dd class="text-amber-600">{{ str_repeat('★', $rating->rating) }}{{ str_repeat('☆', 5 - $rating->rating) }} <span class="text-slate-700">({{ $rating->rating }}/5)</span></dd></div>
            <div><dt class="text-sm text-slate-500">Publication</dt><dd>{{ $rating->status === 'published' ? 'Publié' : 'Masqué' }}</dd></div>
        </dl>
        <div class="mt-6"><h2 class="font-semibold text-slate-900">Commentaire</h2><p class="mt-2 whitespace-pre-line text-slate-700">{{ $rating->comment ?: 'Aucun commentaire.' }}</p></div>
        <form class="mt-8 border-t border-slate-200 pt-5" method="post" action="{{ route('admin.product-ratings.publication', $rating) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $rating->status === 'published' ? 'hidden' : 'published' }}">
            <button class="rounded-lg px-4 py-2 font-semibold text-white {{ $rating->status === 'published' ? 'bg-rose-700' : 'bg-emerald-800' }}" type="submit">{{ $rating->status === 'published' ? 'Masquer cet avis' : 'Publier cet avis' }}</button>
        </form>
    </section>
</div>
@endsection
