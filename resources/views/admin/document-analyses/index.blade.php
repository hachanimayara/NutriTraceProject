@extends('layouts.admin')

@section('title', 'IA et reconnaissance de documents | NutriTrace')

@section('content')
<div class="space-y-6">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Administration · Documents privés</p>
        <h1 class="mt-2 text-3xl font-bold text-slate-900">IA et reconnaissance de documents</h1>
        <p class="mt-2 text-slate-600">Importez un certificat, puis lancez son analyse uniquement après avoir accepté sa transmission au fournisseur IA.</p>
    </div>

    @if (session('status')) <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div> @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Importer un certificat</h2>
        <p class="mt-1 text-sm text-slate-600">PDF, JPG, JPEG, PNG ou WEBP · 10 Mo maximum. Le fichier est stocké dans le disque privé.</p>
        <form class="mt-5 flex flex-wrap items-end gap-3" method="POST" action="{{ route('admin.document-analyses.store') }}" enctype="multipart/form-data">
            @csrf
            <label class="min-w-[min(100%,22rem)] flex-1 text-sm font-medium text-slate-700">Document
                <input class="mt-2 block w-full rounded-xl border border-slate-300 p-3" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required>
            </label>
            <button class="rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">Importer le document</button>
        </form>
        @unless ($apiReady)
            <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Analyse Gemini désactivée : renseignez <code>GEMINI_API_KEY</code> dans le fichier <code>.env</code>, puis activez <code>GEMINI_DOCUMENT_ANALYSIS_ENABLED=true</code>. Exécutez ensuite <code>php artisan config:clear</code> et redémarrez le worker Laravel. L’import privé reste disponible, mais aucune analyse ne sera simulée.</p>
        @endunless
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[1fr_220px_auto]" method="GET">
            <input class="rounded-xl border border-slate-300 px-4 py-3" name="q" value="{{ request('q') }}" placeholder="Rechercher un nom de fichier…">
            <select class="rounded-xl border border-slate-300 px-4 py-3" name="status">
                <option value="">Tous les statuts</option>
                @foreach (['pending' => 'En attente', 'processing' => 'En cours', 'analyzed' => 'À vérifier', 'approved' => 'Confirmée', 'rejected' => 'Rejetée', 'failed' => 'Échec'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-800">Filtrer</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-4">Document</th><th class="px-5 py-4">Importé par</th><th class="px-5 py-4">Date</th><th class="px-5 py-4">Statut</th><th class="px-5 py-4">Détail</th><th class="px-5 py-4 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($analyses as $analysis)
                        <tr><td class="px-5 py-4 font-semibold text-slate-900">{{ $analysis->original_filename }}</td><td class="px-5 py-4 text-slate-600">{{ $analysis->uploader?->name ?? '—' }}</td><td class="px-5 py-4 text-slate-600">{{ $analysis->created_at->format('d/m/Y H:i') }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">{{ ['pending'=>'En attente','processing'=>'En cours','analyzed'=>'À vérifier','approved'=>'Confirmée','rejected'=>'Rejetée','failed'=>'Échec'][$analysis->status] ?? $analysis->status }}</span></td><td class="px-5 py-4"><a class="font-semibold text-emerald-800 hover:underline" href="{{ route('admin.document-analyses.show', $analysis) }}">Ouvrir</a></td><td class="px-5 py-4 text-right"><form method="POST" action="{{ route('admin.document-analyses.destroy', $analysis) }}" data-confirm-action="Supprimer définitivement ce document et son fichier privé ?">@csrf @method('DELETE')<button class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-red-200 text-red-700 transition hover:bg-red-50" type="submit" aria-label="Supprimer {{ $analysis->original_filename }}" title="Supprimer le document"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2m3 0-.8 14H5.8L5 6m4 4v6m6-6v6"/></svg></button></form></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Aucun document importé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4">{{ $analyses->links() }}</div>
    </section>
</div>
@endsection
