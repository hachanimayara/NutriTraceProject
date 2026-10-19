@extends('layouts.admin')

@section('title', 'Gestion des utilisateurs | NutriTrace')

@section('content')
@php
    $roleLabels = [
        'admin' => 'Administrateur',
        'producteur' => 'Producteur',
        'transformateur' => 'Transformateur',
        'distributeur' => 'Distributeur',
        'consommateur' => 'Consommateur',
    ];
    $statusLabels = ['approved' => 'Approuvé', 'pending' => 'En attente', 'suspended' => 'Suspendu', 'deleted' => 'Supprimé'];
    $statusClasses = [
        'approved' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'pending' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'suspended' => 'bg-rose-50 text-rose-800 ring-rose-200',
        'deleted' => 'bg-gray-100 text-gray-700 ring-gray-200',
    ];
@endphp

<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-5 rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-green-50 to-teal-50 p-6 shadow-theme-xs sm:p-8">
        <div class="flex min-w-0 gap-4">
            <span class="mt-1 grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-green-800 shadow-sm ring-1 ring-green-100" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 8v6m3-3h-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-green-800/75"><a href="{{ route('admin.dashboard') }}" class="hover:underline">Accueil</a><span aria-hidden="true">›</span><span>Administration</span></div>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">Gestion des utilisateurs</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 sm:text-base">Gérez les comptes NutriTrace, suivez les rôles et examinez les demandes d’inscription.</p>
            </div>
        </div>
        @if($counts['pending'] > 0)
            <a href="{{ route('admin.users.index', ['status' => 'pending']) }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-green-800 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-green-900 focus:outline-none focus:ring-4 focus:ring-green-800/20">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                Examiner les demandes
                <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $counts['pending'] }}</span>
            </a>
        @endif
    </header>

    @if(session('status'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
    @if(session('warning'))<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>@endif

    <section aria-label="Statistiques des utilisateurs" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'Total des comptes', 'value' => $counts['total'], 'icon' => 'users'],
            ['label' => 'Administrateurs', 'value' => $counts['admin'], 'icon' => 'shield'],
            ['label' => 'Acteurs de la chaîne', 'value' => $counts['actors'], 'icon' => 'leaf'],
            ['label' => 'Demandes en attente', 'value' => $counts['pending'], 'icon' => 'clock'],
        ] as $stat)
            <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs transition hover:-translate-y-0.5 hover:border-green-200 hover:shadow-md dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3 text-gray-500">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-green-50 text-green-800" aria-hidden="true">
                        @if($stat['icon'] === 'users')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round"/></svg>
                        @elseif($stat['icon'] === 'shield')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @elseif($stat['icon'] === 'leaf')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 4c-8 0-14 3-14 10a6 6 0 0 0 6 6c7 0 8-8 8-16ZM4 21c2-5 6-8 11-11" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @else<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>@endif
                    </span>
                    <span class="text-sm font-medium">{{ $stat['label'] }}</span>
                </div>
                <p class="mt-5 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ number_format($stat['value'], 0, ',', ' ') }}</p>
            </article>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="get" action="{{ route('admin.users.index') }}" class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row dark:border-gray-800">
            <label class="relative min-w-0 flex-1">
                <span class="sr-only">Rechercher par nom ou e-mail</span>
                <svg class="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4" stroke-linecap="round"/></svg>
                <input name="q" value="{{ request('q') }}" type="search" placeholder="Rechercher par nom ou e-mail…" class="h-12 w-full rounded-xl border border-gray-200 bg-white pl-11 pr-4 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-green-700 focus:ring-4 focus:ring-green-700/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </label>
            <label><span class="sr-only">Filtrer par rôle</span><select name="role" aria-label="Filtrer par rôle" class="h-12 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-800 outline-none focus:border-green-700 focus:ring-4 focus:ring-green-700/10 sm:w-52 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Tous les rôles</option>@foreach($roleLabels as $key => $label)<option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="sr-only">Filtrer par statut</span><select name="status" aria-label="Filtrer par statut" class="h-12 w-full rounded-xl border border-gray-200 bg-white px-4 text-sm text-gray-800 outline-none focus:border-green-700 focus:ring-4 focus:ring-green-700/10 sm:w-48 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Tous les statuts</option><option value="pending" @selected(request('status') === 'pending')>En attente</option><option value="approved" @selected(request('status') === 'approved')>Approuvé</option><option value="suspended" @selected(request('status') === 'suspended')>Suspendu</option></select></label>
            <button class="inline-flex h-12 shrink-0 items-center justify-center rounded-xl bg-green-800 px-5 text-sm font-semibold text-white transition hover:bg-green-900 focus:outline-none focus:ring-4 focus:ring-green-800/20">Filtrer</button>
            <a href="{{ route('admin.users.export', request()->only('q', 'role', 'status')) }}" class="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl border border-green-200 px-4 text-sm font-semibold text-green-800 transition hover:bg-green-50 dark:border-green-900 dark:text-green-300 dark:hover:bg-green-900/20">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 16V4m-4 4 4-4 4 4M4 15v4h16v-4" stroke-linecap="round" stroke-linejoin="round"/></svg>Exporter
            </a>
        </form>

        @if(request()->hasAny(['q', 'role', 'status']))
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <span class="text-xs text-gray-500">Filtres actifs :</span>
                @if(request('q'))<span class="rounded-full bg-green-50 px-3 py-1 text-xs text-green-800">Recherche : {{ request('q') }}</span>@endif
                @if(request('role'))<span class="rounded-full bg-green-50 px-3 py-1 text-xs text-green-800">Rôle : {{ $roleLabels[request('role')] ?? request('role') }}</span>@endif
                @if(request('status'))<span class="rounded-full bg-green-50 px-3 py-1 text-xs text-green-800">Statut : {{ $statusLabels[request('status')] ?? request('status') }}</span>@endif
                <a href="{{ route('admin.users.index') }}" class="ml-1 text-xs font-medium text-green-800 underline underline-offset-2">Effacer les filtres</a>
            </div>
        @endif

        <div class="space-y-3 bg-gray-50/70 p-4 sm:p-5 dark:bg-gray-900/30">
            @forelse($users as $user)
                @php
                    $roleLabel = $roleLabels[$user->role] ?? ucfirst($user->role);
                    $initials = collect(explode(' ', trim($user->name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
                @endphp
                <article class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-green-200 hover:shadow-md sm:flex-row sm:items-center sm:justify-between sm:p-5 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex min-w-0 items-center gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-green-50 font-semibold text-green-800 ring-1 ring-green-100" aria-hidden="true">
                            @if($user->profile_photo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_photo_path) }}" alt="" class="h-full w-full object-cover">@else{{ mb_strtoupper($initials) }}@endif
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.users.show', $user) }}" class="truncate text-base font-semibold text-gray-900 hover:text-green-800 dark:text-white">{{ $user->name }}</a>
                                <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-800 ring-1 ring-inset ring-green-200">{{ $roleLabel }}</span>
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusClasses[$user->status] ?? 'bg-gray-100 text-gray-700 ring-gray-200' }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $statusLabels[$user->status] ?? ucfirst($user->status) }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm text-gray-500">{{ $user->email }}</p>
                            <p class="mt-2 text-xs text-gray-500">Inscrit le {{ $user->created_at?->format('d/m/Y') ?? '—' }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-gray-100 pt-3 sm:border-0 sm:pt-0 dark:border-gray-800">
                        <a href="{{ route('admin.users.show', $user) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-700 transition hover:border-green-700 hover:text-green-800 focus:outline-none focus:ring-4 focus:ring-green-700/10 dark:border-gray-700 dark:text-gray-200">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/></svg>Détails
                        </a>
                        @if($user->status === 'pending')
                            <form method="post" action="{{ route('admin.users.approve', $user) }}">@csrf<button class="inline-flex h-10 items-center gap-2 rounded-xl bg-green-800 px-4 text-sm font-semibold text-white transition hover:bg-green-900 focus:outline-none focus:ring-4 focus:ring-green-800/20">Approuver</button></form>
                        @elseif($user->status === 'suspended' && $user->role !== 'admin')
                            <form method="post" action="{{ route('admin.users.activate', $user) }}">@csrf<button class="inline-flex h-10 items-center gap-2 rounded-xl bg-green-800 px-4 text-sm font-semibold text-white transition hover:bg-green-900 focus:outline-none focus:ring-4 focus:ring-green-800/20">Réactiver</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-700 dark:bg-gray-900">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-green-50 text-green-800"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4" stroke-linecap="round"/></svg></span>
                    <h2 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">Aucun utilisateur trouvé</h2>
                    <p class="mt-1 text-sm text-gray-500">Modifiez les filtres ou effacez-les pour afficher tous les comptes.</p>
                    @if(request()->hasAny(['q', 'role', 'status']))<a href="{{ route('admin.users.index') }}" class="mt-4 inline-flex rounded-lg px-3 py-2 text-sm font-semibold text-green-800 hover:bg-green-50">Effacer les filtres</a>@endif
                </div>
            @endforelse
        </div>

        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 dark:border-gray-800">
            <p class="text-xs text-gray-500">{{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} sur {{ $users->total() }} utilisateur(s)</p>
            <div>{{ $users->onEachSide(1)->links() }}</div>
        </footer>
    </section>
</div>
@endsection
