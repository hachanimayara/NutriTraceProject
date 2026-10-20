<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCertificateDocument;
use App\Models\AdminAuditLog;
use App\Models\DocumentAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentAnalysisController extends Controller
{
    private const EDITABLE_FIELDS = ['product_name', 'producer_name', 'lot_number', 'certificate_number', 'certificate_type', 'issuer', 'issued_at', 'expires_at', 'geographic_origin', 'other_information'];

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:pending,processing,analyzed,approved,rejected,failed']]);
        $analyses = DocumentAnalysis::with('uploader')
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('original_filename', 'like', '%'.trim($term).'%'))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.document-analyses.index', ['analyses' => $analyses, 'apiReady' => (bool) config('services.gemini.document_analysis_enabled') && (bool) config('services.gemini.api_key')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']]);
        $file = $validated['document'];
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 422, 'Type réel de fichier non autorisé.');
        $path = $file->store('document-analyses', 'local');
        $analysis = DocumentAnalysis::create([
            'uploaded_by' => $request->user()->id,
            'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'storage_path' => $path, 'mime_type' => $mime, 'status' => 'pending',
        ]);

        return redirect()->route('admin.document-analyses.show', $analysis)->with('status', 'Document importé dans le stockage privé. Lancez l’analyse depuis sa fiche après avoir accepté la transmission à Google Gemini.');
    }

    public function show(DocumentAnalysis $analysis): View
    {
        return view('admin.document-analyses.show', ['analysis' => $analysis->load(['uploader', 'reviewer']), 'apiReady' => (bool) config('services.gemini.document_analysis_enabled') && (bool) config('services.gemini.api_key')]);
    }

    public function analyze(Request $request, DocumentAnalysis $analysis): RedirectResponse
    {
        $request->validate(['external_processing_consent' => ['accepted']]);
        if ($analysis->status === 'processing') {
            return redirect()->route('admin.document-analyses.show', $analysis)
                ->with('status', 'Cette analyse est déjà dans la file de traitement.');
        }
        if (! in_array($analysis->status, ['pending', 'failed'], true)) {
            return back()->withErrors(['analysis' => 'Seuls les documents en attente ou en échec peuvent être analysés.']);
        }
        if (! config('services.gemini.document_analysis_enabled') || ! config('services.gemini.api_key')) {
            return back()->withErrors(['analysis' => 'L’analyse Gemini est désactivée ou sa clé est absente. Le document reste privé et aucune analyse simulée ne sera affichée.']);
        }
        $claimed = DocumentAnalysis::query()->whereKey($analysis->id)->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing', 'external_processing_consented_at' => now(), 'failure_code' => null,
                'admin_decision' => null, 'review_comment' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                'updated_at' => now(),
            ]);
        if ($claimed !== 1) {
            return redirect()->route('admin.document-analyses.show', $analysis->fresh())
                ->with('status', 'Cette analyse a déjà été prise en charge.');
        }
        AnalyzeCertificateDocument::dispatch($analysis->id)->afterCommit();

        return redirect()->route('admin.document-analyses.show', $analysis)->with('status', 'Analyse lancée. Cette page se mettra à jour lorsque le traitement sera terminé.');
    }

    public function download(DocumentAnalysis $analysis)
    {
        abort_unless(Storage::disk('local')->exists($analysis->storage_path), 404);

        return Storage::disk('local')->download($analysis->storage_path, $analysis->original_filename, [
            'Content-Type' => $analysis->mime_type, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(DocumentAnalysis $analysis): RedirectResponse
    {
        $disk = Storage::disk('local');
        if ($disk->exists($analysis->storage_path) && ! $disk->delete($analysis->storage_path)) {
            return back()->withErrors(['document' => 'Le fichier privé n’a pas pu être supprimé. La fiche a été conservée.']);
        }

        $analysis->delete();

        return redirect()->route('admin.document-analyses.index')->with('status', 'Le document et son fichier privé ont été supprimés.');
    }

    public function review(Request $request, DocumentAnalysis $analysis): RedirectResponse
    {
        abort_unless($analysis->status === 'analyzed', 409, 'Seule une analyse terminée peut être révisée.');
        $rules = ['decision' => ['required', 'in:approved,rejected'], 'comment' => ['nullable', 'string', 'max:2000']];
        foreach (self::EDITABLE_FIELDS as $field) {
            $rules['fields.'.$field] = ['nullable', 'string', 'max:'.($field === 'other_information' ? 2000 : 500)];
            if (in_array($field, ['issued_at', 'expires_at'], true)) {
                $rules['fields.'.$field][] = 'date_format:Y-m-d';
            }
        }
        $data = $request->validate($rules);
        $fields = $analysis->extracted_data['fields'] ?? [];
        foreach (self::EDITABLE_FIELDS as $field) {
            $fields[$field] = filled($data['fields'][$field] ?? null) ? trim($data['fields'][$field]) : null;
        }
        $analysis->update([
            'extracted_data' => ['fields' => $fields, 'confidence' => $analysis->extracted_data['confidence'] ?? []],
            'status' => $data['decision'], 'admin_decision' => $data['decision'], 'review_comment' => $data['comment'] ?? null,
            'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ]);
        AdminAuditLog::create(['admin_id' => $request->user()->id, 'action' => 'document_analysis_'.$data['decision'], 'metadata' => ['analysis_id' => $analysis->id], 'ip_address' => $request->ip()]);

        return redirect()->route('admin.document-analyses.show', $analysis)->with('status', 'Décision enregistrée. Les données officielles NutriTrace n’ont pas été modifiées.');
    }
}
