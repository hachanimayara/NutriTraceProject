@extends('layouts.admin')

@section('title', 'Tableau de bord | NutriTrace')
@section('page-heading', 'Tableau de bord')

@section('content')
<div class="mb-7 flex flex-wrap items-end justify-between gap-3">
    <div><h2 class="text-2xl font-semibold text-gray-900">Bienvenue dans votre espace</h2><p class="mt-1 text-sm text-gray-500">Vue d’ensemble de votre plateforme NutriTrace.</p></div>
    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800">Données de démonstration</span>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between"><div><p class="text-sm font-medium text-gray-500">Utilisateurs</p><p class="mt-3 text-3xl font-semibold text-gray-900">128</p></div><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.2a3 3 0 0 1 0 5.6M18 14a5 5 0 0 1 3 4.6V20"/></svg></span></div>
        <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-400">Valeur statique issue de l’écran de démonstration initial.</p>
    </article>
    <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between"><div><p class="text-sm font-medium text-gray-500">Services</p><p class="mt-3 text-3xl font-semibold text-gray-900">12</p></div><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-700"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="9"/></svg></span></div>
        <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-400">Valeur statique issue de l’écran de démonstration initial.</p>
    </article>
    <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-1">
        <div class="flex items-start justify-between"><div><p class="text-sm font-medium text-gray-500">État de la plateforme</p><p class="mt-3 text-3xl font-semibold text-gray-900">Prête</p></div><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/></svg></span></div>
        <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-400">Interface reliée aux routes existantes.</p>
    </article>
</div>

<section class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold text-gray-900">Activité récente</h2><p class="mt-1 text-sm text-gray-500">Les événements et indicateurs apparaîtront ici quand les données métier seront connectées.</p></div><span class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-500">Aucune donnée connectée</span></div>
    <div class="mt-6 flex min-h-52 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-5 text-center"><div><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-emerald-700 shadow-sm"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5m0 14h17M7 15l4-4 3 2 6-7"/></svg></span><p class="mt-3 font-medium text-gray-700">Votre espace est prêt</p><p class="mt-1 text-sm text-gray-500">Les cartes graphiques de TailAdmin pourront afficher les données dès qu’elles seront disponibles.</p></div></div>
</section>
@endsection
