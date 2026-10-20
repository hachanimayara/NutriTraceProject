@extends('layouts.admin')

@section('title', 'Analyse de document | NutriTrace')
@section('content')
@php
    $fieldLabels = ['product_name'=>'Produit','producer_name'=>'Producteur / entreprise','lot_number'=>'Numéro du lot','certificate_number'=>'Numéro du certificat','certificate_type'=>'Type de certification','issuer'=>'Organisme émetteur','issued_at'=>'Date d’émission','expires_at'=>'Date d’expiration','geographic_origin'=>'Origine géographique','other_information'=>'Autres informations pertinentes'];
    $values = $analysis->extracted_data['fields'] ?? [];
    $confidence = $analysis->extracted_data['confidence'] ?? [];
@endphp
<div class="mx-auto max-w-6xl space-y-6" @if($analysis->status === 'processing') data-analysis-poll="{{ route('admin.document-analyses.show', $analysis) }}" @endif>
    <a class="font-semibold text-emerald-800 hover:underline" href="{{ route('admin.document-analyses.index') }}">← Retour aux analyses</a>
    @if (session('status')) <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div> @endif
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">Analyse documentaire · {{ $analysis->created_at->format('d/m/Y H:i') }}</p><h1 class="mt-2 break-all text-3xl font-bold text-slate-900">{{ $analysis->original_filename }}</h1><p class="mt-2 text-sm text-slate-600">Importé par {{ $analysis->uploader?->name ?? '—' }} · {{ strtoupper(str_replace('image/', '', $analysis->mime_type)) }}</p></div>
        <span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-800">{{ ['pending'=>'En attente','processing'=>'Analyse en cours…','analyzed'=>'À vérifier','approved'=>'Confirmée par un administrateur','rejected'=>'Rejetée par un administrateur','failed'=>'Échec'][$analysis->status] ?? $analysis->status }}</span>
    </header>
    <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
        <div><h2 class="font-semibold text-slate-900">Document original</h2><p class="mt-1 text-sm text-slate-600">Le téléchargement est contrôlé par l’administration.</p></div>
        <a class="rounded-xl border border-slate-300 px-4 py-2.5 font-semibold text-slate-800 hover:bg-slate-50" href="{{ route('admin.document-analyses.file', $analysis) }}">Télécharger le document</a>
    </section>
    @if($analysis->status === 'failed' && filled($analysis->summary))
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">
            <h2 class="font-semibold">Résumé d’une analyse précédente</h2>
            <p class="mt-1 text-sm">La dernière tentative a échoué. Ce résumé provient d’un traitement antérieur et ne décrit pas cette tentative.</p>
            <p class="mt-3 whitespace-pre-line text-sm">{{ $analysis->summary }}</p>
        </section>
    @endif
    @if ($analysis->status === 'pending' || $analysis->status === 'failed')
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ $analysis->status === 'failed' ? 'Relancer l’analyse' : 'Analyser le document' }}</h2>
            @if($analysis->status === 'failed') <p class="mt-2 text-sm text-amber-800">{{ ['not_configured'=>'Service Google Gemini non configuré.','consent_required'=>'Le consentement explicite est requis avant tout envoi du document.','provider_auth'=>'La clé Gemini est invalide ou l’accès à l’API est refusé.','provider_quota'=>'Quota Gemini atteint. Vérifiez les limites et la facturation du projet Google AI.','provider_rate_limit'=>'Limite temporaire de requêtes atteinte. Le système a attendu le délai indiqué par le fournisseur; réessayez plus tard si nécessaire.','provider_request_invalid'=>'Gemini a refusé le format de la requête. Vérifiez le type de document et le modèle configuré.','provider_model_unavailable'=>'Le modèle configuré est introuvable ou inaccessible. Vérifiez GEMINI_MODEL et sa disponibilité pour votre projet.','provider_file_too_large'=>'Le fournisseur refuse la taille du document. Réduisez le document puis réessayez.','provider_blocked'=>'Gemini a bloqué la réponse. Vérifiez le document puis réessayez.','provider_unavailable'=>'Service IA temporairement indisponible.','invalid_response'=>'Réponse IA invalide ou document non pris en charge. Aucune extraction n’a été enregistrée.'][$analysis->failure_code] ?? 'L’analyse a échoué. Vous pouvez la relancer.' }}</p> @endif
            @if($apiReady)
                <form class="mt-4" method="POST" action="{{ route('admin.document-analyses.analyze', $analysis) }}">@csrf
                    <label class="flex items-start gap-3 text-sm leading-6 text-slate-700"><input class="mt-1" type="checkbox" name="external_processing_consent" value="1" required><span>J’autorise explicitement l’envoi de ce document à Google Gemini pour l’analyse. Je comprends qu’il peut contenir des données personnelles. Le document sera envoyé au modèle configuré par l’administration.</span></label>
                    <button class="mt-4 rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">Analyser le document</button>
                </form>
            @else
                <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Analyse indisponible : activez GEMINI_DOCUMENT_ANALYSIS_ENABLED et configurez GEMINI_API_KEY côté serveur. Le fichier reste stocké en privé.</p>
            @endif
        </section>
    @endif
    @if($analysis->status === 'processing') <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-900" role="status">Analyse en attente ou en cours. Cette page sera actualisée automatiquement. Si le statut ne change pas, démarrez le worker Laravel avec <code>php artisan queue:work --tries=2</code>.</div> @endif
    @if($analysis->status === 'analyzed' || in_array($analysis->status, ['approved','rejected'], true))
        <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
            <section class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-xl font-semibold text-slate-900">Informations extraites</h2>
                <p class="mt-1 text-sm text-slate-600">Les valeurs sont des propositions à vérifier. L’analyse IA ne certifie pas l’authenticité du document.</p>
                <form class="mt-5 space-y-4" method="POST" action="{{ route('admin.document-analyses.review', $analysis) }}">@csrf @method('PUT')
                    @foreach($fieldLabels as $key => $label)
                        <label class="block text-sm font-medium text-slate-700">{{ $label }} <span class="font-normal text-slate-400">@if(isset($confidence[$key])) · confiance IA {{ number_format((float)$confidence[$key] * 100, 0) }}% @endif</span>
                            @if($key === 'other_information')
                                <textarea class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5" name="fields[{{ $key }}]" rows="3" maxlength="2000" @disabled($analysis->status !== 'analyzed')>{{ old('fields.'.$key, $values[$key] ?? '') }}</textarea>
                            @else
                                <input class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5" name="fields[{{ $key }}]" type="text" value="{{ old('fields.'.$key, $values[$key] ?? '') }}" maxlength="500" @disabled($analysis->status !== 'analyzed')>
                            @endif
                        </label>
                    @endforeach
                    <div class="rounded-xl bg-slate-50 p-4"><h3 class="font-semibold text-slate-900">Résumé proposé par l’IA</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $analysis->summary }}</p></div>
                    <div><h3 class="font-semibold text-slate-900">Anomalies et contrôles</h3>
                        @forelse($analysis->anomalies ?? [] as $anomaly)
                            <article class="mt-3 rounded-xl border border-slate-200 p-4"><div class="flex flex-wrap justify-between gap-2"><strong>{{ $anomaly['field'] ?? 'Document' }}</strong><span class="text-xs font-semibold uppercase text-amber-800">{{ $anomaly['severity'] }} · {{ $anomaly['source'] === 'ai_suggestion' ? 'Suggestion IA' : 'Règle NutriTrace' }}</span></div><p class="mt-1 text-sm text-slate-600">{{ $anomaly['description'] }}</p></article>
                        @empty <p class="mt-2 text-sm text-slate-500">Aucune anomalie détectée par les contrôles appliqués.</p> @endforelse
                    </div>
                    @if($analysis->status === 'analyzed')
                        <label class="block text-sm font-medium text-slate-700">Commentaire administrateur<textarea class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5" name="comment" rows="3" maxlength="2000">{{ old('comment') }}</textarea></label>
                        <div class="flex flex-wrap gap-3"><button class="rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white" name="decision" value="approved">Confirmer après vérification</button><button class="rounded-xl border border-red-200 px-5 py-3 font-semibold text-red-700" name="decision" value="rejected">Rejeter l’analyse</button></div>
                    @endif
                </form>
            </section>
            <aside class="h-fit rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950"><h2 class="font-bold">Décision humaine requise</h2><p class="mt-2">Les résultats IA et anomalies ne constituent pas une preuve de fraude. Confirmer ou rejeter ici ne modifie aucune certification ni donnée officielle de traçabilité.</p>
                @if($analysis->reviewed_at)<hr class="my-4 border-amber-200"><p><strong>Décision :</strong> {{ $analysis->admin_decision === 'approved' ? 'Confirmée' : 'Rejetée' }}</p><p><strong>Révisée par :</strong> {{ $analysis->reviewer?->name ?? '—' }}</p><p><strong>Date :</strong> {{ $analysis->reviewed_at->format('d/m/Y H:i') }}</p>@if($analysis->review_comment)<p class="mt-2"><strong>Commentaire :</strong> {{ $analysis->review_comment }}</p>@endif @endif
            </aside>
        </div>
    @endif
</div>
@if($analysis->status === 'processing')<script>setTimeout(() => location.reload(), 5000)</script>@endif
@endsection
