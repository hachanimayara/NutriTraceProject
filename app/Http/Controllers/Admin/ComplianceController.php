<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\ComplianceEvent;
use App\Models\CorrectiveAction;
use App\Models\Distribution;
use App\Models\EnvironmentalIndicator;
use App\Models\Farm;
use App\Models\Lot;
use App\Models\Product;
use App\Models\TraceabilityAlert;
use App\Models\Transformation;
use App\Models\User;
use App\Notifications\ComplianceDecisionAssigned;
use App\Services\TraceabilityAnalysisService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComplianceController extends Controller
{
    public function dashboard(): View
    {
        $warningDays = max(1, (int) config('compliance.renewal_warning_days', 30));
        $alerts = TraceabilityAlert::query()->whereNotIn('status', ['resolved', 'dismissed']);
        $validCertifications = Certification::query()->where('status', 'valid')->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()));
        $metrics = [
            'valid_certifications' => (clone $validCertifications)->count(),
            'expired_certifications' => Certification::query()->where(fn ($query) => $query->where('status', 'expired')->orWhereDate('expires_at', '<', today()))->count(),
            'expiring_certifications' => (clone $validCertifications)->whereNotNull('expires_at')->whereDate('expires_at', '<=', today()->addDays($warningDays))->count(),
            'open_alerts' => (clone $alerts)->count(),
            'critical_alerts' => (clone $alerts)->where('severity', 'critical')->count(),
            'incomplete_certifications' => $this->requiredDocumentCertifications()->count(),
            'overdue_actions' => CorrectiveAction::query()->whereNotIn('status', ['completed'])->whereNotNull('due_at')->where('due_at', '<', now())->count(),
        ];
        $byType = TraceabilityAlert::query()->whereNotIn('status', ['resolved', 'dismissed'])->selectRaw('code, COUNT(*) as total')->groupBy('code')->orderByDesc('total')->limit(8)->get();
        $bySubjectType = TraceabilityAlert::query()->whereNotIn('status', ['resolved', 'dismissed'])->selectRaw('subject_type, COUNT(*) as total')->groupBy('subject_type')->get();
        $byStage = $bySubjectType->groupBy(fn ($item) => match ($item->subject_type) {
            Certification::class => 'Certifications', Farm::class, Product::class => 'Production agricole', Lot::class => 'Lots', Transformation::class => 'Transformation', Distribution::class => 'Distribution', EnvironmentalIndicator::class => 'Mesures environnementales', default => 'Dossier non relié',
        })->map(fn ($items) => $items->sum('total'))->sortDesc();
        $recent = TraceabilityAlert::query()->with('assignee')->whereNotIn('status', ['resolved', 'dismissed'])->latest('last_checked_at')->latest()->limit(8)->get();

        return view('admin.compliance.dashboard', compact('metrics', 'byType', 'byStage', 'recent', 'warningDays'));
    }

    public function certifications(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['pending', 'valid', 'expired', 'rejected', 'expiring', 'incomplete'])],
            'expires_from' => ['nullable', 'date'],
            'expires_to' => ['nullable', 'date', 'after_or_equal:expires_from'],
        ]);
        $query = Certification::query()->with(['creator', 'farm.owner', 'product.owner', 'product.farm', 'alerts'])->when($filters['q'] ?? null, function (Builder $query, string $term) {
            $like = '%'.addcslashes(trim($term), '%_\\').'%';
            $query->where(fn (Builder $where) => $where->where('title', 'like', $like)->orWhere('scheme', 'like', $like)->orWhere('certificate_number', 'like', $like)->orWhere('issuer', 'like', $like)->orWhereHas('creator', fn ($owner) => $owner->where('name', 'like', $like))->orWhereHas('product', fn ($product) => $product->where('name', 'like', $like))->orWhereHas('farm', fn ($farm) => $farm->where('name', 'like', $like)));
        });
        $status = $filters['status'] ?? null;
        if ($status === 'expired') {
            $query->where(fn ($q) => $q->where('status', 'expired')->orWhereDate('expires_at', '<', today()));
        } elseif ($status === 'expiring') {
            $query->where('status', 'valid')->whereNotNull('expires_at')->whereDate('expires_at', '>=', today())->whereDate('expires_at', '<=', today()->addDays(max(1, (int) config('compliance.renewal_warning_days', 30))));
        } elseif ($status === 'incomplete') {
            $query->whereIn('id', $this->requiredDocumentCertifications()->select('certifications.id'));
        } elseif ($status) {
            $query->where('status', $status);
        }
        $query->when($filters['expires_from'] ?? null, fn ($q, $date) => $q->whereDate('expires_at', '>=', $date))
            ->when($filters['expires_to'] ?? null, fn ($q, $date) => $q->whereDate('expires_at', '<=', $date));

        return view('admin.compliance.certifications', ['certifications' => $query->orderByRaw('expires_at IS NULL, expires_at ASC')->paginate(5)->withQueryString(), 'filters' => $filters, 'warningDays' => max(1, (int) config('compliance.renewal_warning_days', 30))]);
    }

    public function alerts(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['all', 'open', 'reviewing', 'in_progress', 'awaiting_evidence', 'resolved', 'dismissed'])],
            'severity' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
            'code' => ['nullable', 'string', 'max:100'],
        ]);
        $alerts = TraceabilityAlert::query()->with(['assignee', 'subject'])
            ->when(($filters['status'] ?? 'open') === 'open', fn (Builder $q) => $q->whereNotIn('status', ['resolved', 'dismissed']))
            ->when(($filters['status'] ?? null) && $filters['status'] !== 'open' && $filters['status'] !== 'all', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['severity'] ?? null, fn (Builder $q, $severity) => $q->where('severity', $severity))
            ->when($filters['code'] ?? null, fn (Builder $q, $code) => $q->where('code', $code))
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $like = '%'.addcslashes(trim($term), '%_\\').'%';
                $query->where(fn (Builder $where) => $where->where('title', 'like', $like)->orWhere('explanation', 'like', $like)->orWhere('code', 'like', $like)->orWhereHasMorph('subject', [Certification::class, Product::class, Lot::class, EnvironmentalIndicator::class, Transformation::class, Distribution::class], function (Builder $subject, string $type) use ($like) {
                    $columns = match ($type) {
                        Certification::class => ['title', 'scheme', 'certificate_number'],
                        Product::class => ['name', 'reference'],
                        Lot::class => ['code'],
                        EnvironmentalIndicator::class => ['name', 'metric_code'],
                        Transformation::class => ['name', 'reference'],
                        Distribution::class => ['reference'],
                        default => [],
                    };
                    foreach ($columns as $index => $column) {
                        $index === 0 ? $subject->where($column, 'like', $like) : $subject->orWhere($column, 'like', $like);
                    }
                    $ownerRelations = match ($type) {
                        Certification::class => ['creator', 'product.owner', 'farm.owner'],
                        Product::class => ['owner', 'farm.owner'],
                        Lot::class => ['creator', 'product.owner'],
                        EnvironmentalIndicator::class => ['recorder', 'product.owner', 'lot.product.owner'],
                        Transformation::class, Distribution::class => ['owner'],
                        default => [],
                    };
                    foreach ($ownerRelations as $relation) {
                        $subject->orWhereHas($relation, fn (Builder $owner) => $owner->where('name', 'like', $like));
                    }
                }));
            })
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->latest()->paginate(5)->withQueryString();

        return view('admin.compliance.alerts', ['alerts' => $alerts, 'filters' => $filters, 'statusLabels' => $this->alertStatusLabels()]);
    }

    public function showAlert(TraceabilityAlert $alert): View
    {
        $alert->load(['assignee', 'closer', 'creator', 'correctiveActions.responsible', 'correctiveActions.creator', 'events.actor']);
        $subject = $alert->subject;
        if ($subject instanceof Certification) {
            $subject->load(['creator', 'complianceEvents', 'product.owner', 'product.farm.owner', 'product.lots.transformations.owner', 'product.lots.distributions.owner', 'product.lots.distributions.store', 'product.lots.indicators.recorder', 'farm.owner', 'farm.certifications.creator', 'farm.products.owner', 'farm.products.lots.transformations.owner', 'farm.products.lots.distributions.owner', 'farm.products.lots.distributions.store', 'farm.products.lots.indicators.recorder']);
        } elseif ($subject instanceof Product) {
            $subject->load(['owner', 'farm.owner', 'certifications.creator', 'indicators.recorder', 'lots.transformations.owner', 'lots.distributions.owner', 'lots.distributions.store', 'lots.indicators.recorder']);
        } elseif ($subject instanceof Farm) {
            $subject->load(['owner', 'certifications.creator', 'products.owner', 'products.certifications.creator', 'products.lots.transformations.owner', 'products.lots.distributions.owner', 'products.lots.distributions.store', 'products.indicators.recorder']);
        } elseif ($subject instanceof Lot) {
            $subject->load(['product.owner', 'product.farm.owner', 'product.certifications.creator', 'transformations.owner', 'distributions.owner', 'distributions.store', 'indicators.recorder']);
        } elseif ($subject instanceof Transformation) {
            $subject->load(['owner', 'lots.product.owner', 'lots.product.farm.owner', 'lots.product.certifications.creator', 'lots.distributions.owner', 'lots.distributions.store', 'lots.indicators.recorder']);
        } elseif ($subject instanceof Distribution) {
            $subject->load(['owner', 'store', 'lot.product.owner', 'lot.product.farm.owner', 'lot.product.certifications.creator', 'lot.transformations.owner', 'lot.indicators.recorder']);
        } elseif ($subject instanceof EnvironmentalIndicator) {
            $subject->load(['recorder', 'product.owner', 'product.farm.owner', 'product.certifications.creator', 'lot.product.owner', 'lot.product.farm.owner', 'lot.transformations.owner', 'lot.distributions.owner', 'lot.distributions.store']);
        }

        $chain = $this->traceabilityChain($subject);
        $relatedIds = [
            Product::class => $chain['products']->pluck('id'),
            Lot::class => $chain['lots']->pluck('id'),
            Certification::class => $chain['certifications']->pluck('id'),
            EnvironmentalIndicator::class => $chain['indicators']->pluck('id'),
            Transformation::class => $chain['transformations']->pluck('id'),
            Distribution::class => $chain['distributions']->pluck('id'),
            Farm::class => collect([$chain['farm']?->id])->filter(),
        ];
        $hasRelatedSubjects = collect($relatedIds)->contains(fn ($ids) => $ids->isNotEmpty());
        $relatedAlerts = $hasRelatedSubjects ? TraceabilityAlert::query()->where('id', '!=', $alert->id)->where(function (Builder $query) use ($relatedIds) {
            foreach ($relatedIds as $type => $ids) {
                if ($ids->isNotEmpty()) {
                    $query->orWhere(fn (Builder $related) => $related->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            }
        })->with('assignee')->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")->latest()->limit(12)->get() : collect();
        $assignees = User::query()->where('status', 'approved')->whereIn('role', ['admin', 'producteur', 'transformateur', 'distributeur'])->orderBy('name')->get(['id', 'name', 'role']);

        return view('admin.compliance.alert-show', compact('alert', 'subject', 'chain', 'assignees', 'relatedAlerts'));
    }

    public function actions(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['todo', 'in_progress', 'awaiting_verification', 'completed'])], 'overdue' => ['nullable', Rule::in(['1'])]]);
        $actions = CorrectiveAction::query()->with(['alert', 'responsible', 'creator'])
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when(($filters['overdue'] ?? null) === '1', fn (Builder $q) => $q->whereNotIn('status', ['completed'])->whereNotNull('due_at')->where('due_at', '<', now()))
            ->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->latest()->paginate(5)->withQueryString();

        return view('admin.compliance.actions', compact('actions', 'filters'));
    }

    public function analyze(TraceabilityAnalysisService $analysis): RedirectResponse
    {
        $result = $analysis->analyze();

        return redirect()->route('admin.compliance.dashboard')->with('status', $result['checks_triggered'].' signal(s) détecté(s) ou actualisé(s), '.$result['alerts_open'].' anomalie(s) encore ouverte(s).');
    }

    public function decide(Request $request, TraceabilityAlert $alert): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'reviewing', 'in_progress', 'awaiting_evidence', 'resolved', 'dismissed'])],
            'decision' => ['required', 'string', 'min:5', 'max:3000'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'approved')],
            'due_at' => ['nullable', 'date'],
        ]);
        if ($data['status'] === 'resolved' && $alert->correctiveActions()->where('status', '!=', 'completed')->exists()) {
            throw ValidationException::withMessages(['status' => 'Terminez ou annulez les actions correctives ouvertes avant de clôturer cette anomalie.']);
        }
        $previous = $alert->status;
        DB::transaction(function () use ($request, $alert, $data, $previous) {
            $alert->forceFill([
                'status' => $data['status'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'decision' => $data['decision'],
                'reviewed_at' => $alert->reviewed_at ?? now(),
                'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null,
                'closed_by' => in_array($data['status'], ['resolved', 'dismissed'], true) ? $request->user()->id : null,
                'closure_reason' => in_array($data['status'], ['resolved', 'dismissed'], true) ? $data['decision'] : null,
            ])->save();
            $this->event($request, $alert, null, 'alert.decision', $previous, $data['status'], $data['decision'], ['assigned_to' => $data['assigned_to'] ?? null, 'due_at' => $data['due_at'] ?? null]);
        });

        if (! empty($data['assigned_to'])) {
            User::query()->find($data['assigned_to'])?->notify(new ComplianceDecisionAssigned($alert->refresh()));
        }

        return back()->with('status', 'La décision et son historique ont été enregistrés.');
    }

    public function createAction(Request $request, TraceabilityAlert $alert): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:5', 'max:3000'],
            'responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'approved')],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $alert, $data) {
            $action = $alert->correctiveActions()->create(['description' => $data['description'], 'responsible_id' => $data['responsible_id'] ?? null, 'created_by' => $request->user()->id, 'due_at' => $data['due_at'] ?? null, 'status' => 'todo']);
            $this->event($request, $alert, $action, 'action.created', null, 'todo', $data['comment'] ?? $data['description']);
            if ($alert->isOpen() && $alert->status !== 'in_progress') {
                $previous = $alert->status;
                $alert->forceFill(['status' => 'in_progress', 'reviewed_at' => $alert->reviewed_at ?? now()])->save();
                $this->event($request, $alert, null, 'alert.status', $previous, 'in_progress', 'Passage en traitement après création d’une action corrective.');
            }
        });

        return back()->with('status', 'Action corrective créée.');
    }

    public function updateAction(Request $request, CorrectiveAction $action): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'awaiting_verification', 'completed'])],
            'comment' => ['required', 'string', 'min:3', 'max:2000'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $previous = $action->status;
        $oldPath = $action->evidence_path;
        $path = $request->hasFile('evidence') ? $request->file('evidence')->store('compliance-actions', 'local') : $oldPath;
        DB::transaction(function () use ($request, $action, $data, $previous, $path) {
            $action->forceFill(['status' => $data['status'], 'evidence_path' => $path, 'completed_at' => $data['status'] === 'completed' ? now() : null])->save();
            $this->event($request, $action->alert, $action, 'action.updated', $previous, $data['status'], $data['comment'], ['evidence_added' => $request->hasFile('evidence')]);
        });
        if ($request->hasFile('evidence') && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('status', 'Action corrective mise à jour.');
    }

    public function actionEvidence(CorrectiveAction $action)
    {
        abort_unless($action->evidence_path && Storage::disk('local')->exists($action->evidence_path), 404);

        return Storage::disk('local')->download($action->evidence_path, basename($action->evidence_path));
    }

    private function requiredDocumentCertifications(): Builder
    {
        $schemes = (array) config('compliance.required_certification_schemes', []);
        if ($schemes === []) {
            return Certification::query()->whereRaw('1 = 0');
        }
        $certifications = Certification::query()->when(! in_array('*', $schemes, true), fn ($query) => $query->where(fn ($q) => $q->whereIn('scheme', $schemes)->orWhereIn('title', $schemes)))->get(['id', 'document_path']);
        $missingIds = $certifications->filter(fn (Certification $certification) => ! $certification->document_path || ! Storage::disk('local')->exists($certification->document_path))->pluck('id');

        return Certification::query()->whereIn('id', $missingIds);
    }

    private function traceabilityChain(mixed $subject): array
    {
        $product = null;
        $farm = null;
        $directProducts = collect();
        $lots = collect();
        if ($subject instanceof Certification) {
            $product = $subject->product;
            $farm = $subject->farm ?? $product?->farm;
            $directProducts = $farm?->products ?? collect();
            $lots = $product?->lots ?? $directProducts->flatMap->lots;
        } elseif ($subject instanceof Product) {
            $product = $subject;
            $farm = $product->farm;
            $lots = $product->lots;
        } elseif ($subject instanceof Farm) {
            $farm = $subject;
            $directProducts = $farm->products;
            $lots = $directProducts->flatMap->lots;
        } elseif ($subject instanceof Lot) {
            $lots = collect([$subject]);
            $product = $subject->product;
            $farm = $product?->farm;
        } elseif ($subject instanceof Transformation) {
            $lots = $subject->lots;
            $product = $lots->first()?->product;
            $farm = $product?->farm;
        } elseif ($subject instanceof Distribution) {
            $lots = collect([$subject->lot])->filter();
            $product = $subject->lot?->product;
            $farm = $product?->farm;
        } elseif ($subject instanceof EnvironmentalIndicator) {
            $product = $subject->product ?? $subject->lot?->product;
            $farm = $product?->farm;
            $lots = $subject->lot ? collect([$subject->lot]) : ($product?->lots ?? collect());
        }

        $lots = Lot::query()->whereIn('id', $lots->filter()->pluck('id')->unique())
            ->with(['product.owner', 'product.farm.owner', 'product.certifications.creator', 'transformations.owner', 'distributions.owner', 'distributions.store', 'indicators.recorder'])
            ->get();
        $products = $lots->pluck('product')->filter()->concat($directProducts)->push($product)->filter()->unique('id')->values();
        if (! $farm) {
            $farm = $products->first()?->farm;
        }
        $certifications = $products->flatMap(fn (Product $item) => $item->certifications)->concat($farm?->certifications ?? collect())->filter()->unique('id')->values();
        $indicators = $products->flatMap(fn (Product $item) => $item->indicators)->concat($lots->flatMap(fn (Lot $lot) => $lot->indicators))->filter()->unique('id')->values();
        $transformations = $lots->flatMap(fn (Lot $lot) => $lot->transformations)->filter()->unique('id')->values();
        $distributions = $lots->flatMap(fn (Lot $lot) => $lot->distributions)->filter()->unique('id')->values();

        return compact('farm', 'products', 'lots', 'certifications', 'indicators', 'transformations', 'distributions');
    }

    private function event(Request $request, TraceabilityAlert $alert, ?CorrectiveAction $action, string $event, ?string $from, ?string $to, ?string $note, array $metadata = []): void
    {
        ComplianceEvent::create(['alert_id' => $alert->id, 'corrective_action_id' => $action?->id, 'actor_id' => $request->user()->id, 'event' => $event, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'metadata' => $metadata ?: null]);
    }

    private function alertStatusLabels(): array
    {
        return ['open' => 'Nouvelle', 'reviewing' => 'À examiner', 'in_progress' => 'En cours', 'awaiting_evidence' => 'En attente de justificatif', 'resolved' => 'Résolue', 'dismissed' => 'Classée sans suite'];
    }
}
