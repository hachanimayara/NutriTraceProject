@extends('layouts.admin')

@section('title', 'Détail utilisateur | NutriTrace')

@section('content')
<div class="mb-5 flex items-center gap-2 text-xs text-gray-500"><a href="{{ route('admin.dashboard') }}" class="hover:text-green-800">Accueil</a><span>›</span><a href="{{ route('admin.users.index') }}" class="hover:text-green-800">Utilisateurs et rôles</a><span>›</span><span>Détail</span></div>
@if(session('status'))<div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>@endif
@if(session('warning'))<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">{{ session('warning') }}</div>@endif

@php
    $isProtected = $user->role === 'admin' || $user->is_protected_admin || $user->id === auth()->id();
    $statusStyle = ['approved'=>'bg-green-50 text-green-800','pending'=>'bg-amber-50 text-amber-800','suspended'=>'bg-red-50 text-red-800','deleted'=>'bg-gray-100 text-gray-700'][$user->status] ?? 'bg-gray-100 text-gray-700';
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4"><span class="grid h-16 w-16 place-items-center overflow-hidden rounded-2xl bg-green-100 text-xl font-bold text-green-800">@if($user->profile_photo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_photo_path) }}" alt="Photo de {{ $user->name }}" class="h-full w-full object-cover">@else{{ mb_strtoupper(mb_substr($user->name,0,1)) }}@endif</span><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $user->name }}</h1><p class="mt-1 text-sm text-gray-500">{{ $user->email }}</p></div></div>
    <span class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-xs font-semibold {{ $statusStyle }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ ['approved'=>'Approuvé','pending'=>'En attente','suspended'=>'Suspendu','deleted'=>'Supprimé'][$user->status] ?? ucfirst($user->status) }}</span>
</div>

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
    <div class="space-y-5">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <div class="mb-5 border-b border-gray-100 pb-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Informations du compte</h2><p class="mt-1 text-xs text-gray-500">Coordonnées et informations fournies lors de l’inscription.</p></div>
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                @foreach([['Nom complet',$user->name],['Adresse e-mail',$user->email],['Téléphone',$user->phone ?: 'Non renseigné'],['Rôle',['admin'=>'Administrateur','producteur'=>'Producteur','transformateur'=>'Transformateur','distributeur'=>'Distributeur','consommateur'=>'Ancien compte consommateur'][$user->role] ?? ucfirst($user->role)],['Organisation',$user->organization ?: 'Non renseignée'],['Langue',strtoupper($user->preferred_locale ?: 'FR')],['Date d’inscription',$user->created_at?->format('d/m/Y à H:i') ?? '—'],['Dernière mise à jour',$user->updated_at?->format('d/m/Y à H:i') ?? '—']] as [$label,$value])<div><dt class="text-xs text-gray-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm font-medium text-gray-800 dark:text-gray-100">{{ $value }}</dd></div>@endforeach
                <div class="sm:col-span-2"><dt class="text-xs text-gray-500">À propos</dt><dd class="mt-1 whitespace-pre-line text-sm text-gray-800 dark:text-gray-100">{{ $user->about ?: 'Aucune description renseignée.' }}</dd></div>
            </dl>
        </section>
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <div class="mb-4 border-b border-gray-100 pb-4 dark:border-gray-800"><h2 class="font-semibold text-gray-900 dark:text-white">Validation du compte</h2><p class="mt-1 text-xs text-gray-500">Historique de l’approbation par un administrateur.</p></div>
            <dl class="grid gap-5 sm:grid-cols-2"><div><dt class="text-xs text-gray-500">Statut</dt><dd class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-100">{{ ['approved'=>'Approuvé','pending'=>'En attente','suspended'=>'Suspendu','deleted'=>'Supprimé'][$user->status] ?? ucfirst($user->status) }}</dd></div><div><dt class="text-xs text-gray-500">Date d’approbation</dt><dd class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $user->approved_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div><div><dt class="text-xs text-gray-500">Approuvé par</dt><dd class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $user->approver?->name ?? '—' }}</dd></div></dl>
        </section>
    </div>

    <aside class="h-fit rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h2 class="font-semibold text-gray-900 dark:text-white">Actions du compte</h2><p class="mt-1 text-xs text-gray-500">Les changements sont enregistrés dans l’historique admin.</p>
        <div class="mt-5 space-y-3">
            @if($user->status === 'pending' && ! $user->trashed() && $user->role !== 'admin')<form method="post" action="{{ route('admin.users.approve', $user) }}">@csrf<button class="w-full rounded-xl bg-green-800 px-4 py-3 text-sm font-semibold text-white hover:bg-green-900">Approuver le compte</button></form><p class="text-xs leading-5 text-gray-500">L’utilisateur sera autorisé à se connecter et recevra une notification e-mail avec un lien de connexion.</p>@endif
            @if($user->status === 'suspended' && ! $isProtected && ! $user->trashed())<form method="post" action="{{ route('admin.users.activate', $user) }}">@csrf<button class="w-full rounded-xl bg-green-800 px-4 py-3 text-sm font-semibold text-white hover:bg-green-900">Réactiver le compte</button></form>@endif
            @if($user->status === 'approved' && ! $isProtected && ! $user->trashed())<form method="post" action="{{ route('admin.users.suspend', $user) }}" data-confirm-action="Suspendre ce compte ?">@csrf<button class="w-full rounded-xl border border-amber-300 px-4 py-3 text-sm font-semibold text-amber-800 hover:bg-amber-50">Suspendre le compte</button></form>@endif
            @if(! $isProtected && ! $user->trashed())<form method="post" action="{{ route('admin.users.destroy', $user) }}" data-confirm-action="Supprimer ce compte ? Il sera placé dans la corbeille.">@csrf @method('DELETE')<button class="w-full rounded-xl border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 hover:bg-red-50">Supprimer le compte</button></form>@endif
            @if($user->trashed())<p class="rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-600">Ce compte a été supprimé et n’est plus accessible.</p>@elseif($isProtected)<p class="rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-600">Les comptes administrateur protégés ne peuvent pas être suspendus ou supprimés ici.</p>@elseif($user->status === 'approved')<p class="rounded-xl bg-green-50 p-3 text-xs leading-5 text-green-800">Ce compte peut actuellement accéder à son espace NutriTrace.</p>@endif
        </div>
    </aside>
</div>
<div class="mt-5"><a href="{{ route('admin.users.index', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-green-800 hover:bg-green-50">← Retour à la liste</a></div>
@endsection
