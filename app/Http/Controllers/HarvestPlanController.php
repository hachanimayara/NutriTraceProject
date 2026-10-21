<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\HarvestPlan;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HarvestPlanController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where('user_id', $request->user()->id)->whereNull('deleted_at')],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('user_id', $request->user()->id)->whereNull('deleted_at')],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'completed', 'overdue', 'cancelled'])],
            'period' => ['nullable', Rule::in(['today', 'week'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = $this->owned($request)->with(['farm:id,name', 'product:id,name,unit']);
        $this->applyFilters($query, $filters);
        $plans = $query->orderBy('starts_at')->paginate(5)->withQueryString();

        return view('harvest-plans.index', [
            'plans' => $plans,
            'farms' => Farm::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'farm_id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function events(Request $request)
    {
        $filters = $request->validate([
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where('user_id', $request->user()->id)->whereNull('deleted_at')],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('user_id', $request->user()->id)->whereNull('deleted_at')],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'completed', 'overdue', 'cancelled'])],
            'period' => ['nullable', Rule::in(['today', 'week'])],
            'q' => ['nullable', 'string', 'max:120'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $query = $this->owned($request)->with(['farm:id,name', 'product:id,name']);
        $this->applyFilters($query, $filters);
        $query->whereDate('starts_at', '<', $filters['end'])->whereDate('ends_at', '>=', $filters['start']);

        return $query->get()->map(fn (HarvestPlan $plan) => [
            'id' => $plan->id,
            'title' => $plan->product->name.' · '.$plan->estimated_quantity.' '.$plan->unit,
            'start' => $plan->starts_at->toDateString(),
            'end' => $plan->ends_at->copy()->addDay()->toDateString(),
            'url' => route('producer.harvest-plans.show', $plan),
            'backgroundColor' => $this->statusColor($plan->display_status),
            'borderColor' => $this->statusColor($plan->display_status),
            'extendedProps' => ['status' => $plan->display_status, 'farm' => $plan->farm->name, 'reference' => $plan->reference],
        ])->values();
    }

    public function create(Request $request)
    {
        return view('harvest-plans.form', [
            'plan' => null,
            'farms' => Farm::where('user_id', $request->user()->id)->orderBy('name')->get(),
            'products' => Product::where('user_id', $request->user()->id)->orderBy('name')->get(),
            'initialDate' => $request->validate(['date' => ['nullable', 'date', 'after_or_equal:today']])['date'] ?? today()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $plan = $request->user()->harvestPlans()->create($data + ['reference' => 'REC-'.strtoupper(Str::random(10)), 'status' => 'planned']);
        $plan->histories()->create(['user_id' => $request->user()->id, 'event' => 'created', 'changes' => ['reference' => $plan->reference]]);

        return redirect()->route('producer.harvest-plans.show', $plan)->with('status', 'Planification de récolte créée.');
    }

    public function show(Request $request, HarvestPlan $harvestPlan)
    {
        $plan = $this->owned($request)->with(['farm', 'product', 'histories.actor'])->findOrFail($harvestPlan->id);
        return view('harvest-plans.show', compact('plan'));
    }

    public function edit(Request $request, HarvestPlan $harvestPlan)
    {
        $plan = $this->owned($request)->findOrFail($harvestPlan->id);
        abort_if(in_array($plan->status, ['completed', 'cancelled'], true), 403, 'Cette récolte ne peut plus être modifiée.');
        return view('harvest-plans.form', [
            'plan' => $plan,
            'farms' => Farm::where('user_id', $request->user()->id)->orderBy('name')->get(),
            'products' => Product::where('user_id', $request->user()->id)->orderBy('name')->get(),
            'initialDate' => $plan->starts_at->toDateString(),
        ]);
    }

    public function update(Request $request, HarvestPlan $harvestPlan)
    {
        $plan = $this->owned($request)->findOrFail($harvestPlan->id);
        abort_if(in_array($plan->status, ['completed', 'cancelled'], true), 403, 'Cette récolte ne peut plus être modifiée.');
        $data = $this->validated($request, $plan);
        $changes = [];
        foreach ($data as $key => $value) {
            if ((string) $plan->getAttribute($key) !== (string) $value) $changes[$key] = ['before' => $plan->getAttribute($key), 'after' => $value];
        }
        $plan->update($data);
        if ($changes) $plan->histories()->create(['user_id' => $request->user()->id, 'event' => 'updated', 'changes' => $changes]);
        return redirect()->route('producer.harvest-plans.show', $plan)->with('status', 'Planification mise à jour.');
    }

    public function status(Request $request, HarvestPlan $harvestPlan)
    {
        $plan = $this->owned($request)->findOrFail($harvestPlan->id);
        $next = $request->validate(['status' => ['required', Rule::in(['in_progress', 'completed', 'cancelled'])]])['status'];
        $allowed = match ($plan->status) {
            'planned' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            default => [],
        };
        abort_unless(in_array($next, $allowed, true), 422, 'Transition de statut non autorisée.');
        $before = $plan->status;
        $plan->update(['status' => $next]);
        $plan->histories()->create(['user_id' => $request->user()->id, 'event' => 'status_changed', 'changes' => ['status' => ['before' => $before, 'after' => $next]]]);
        return back()->with('status', 'Statut de la récolte mis à jour.');
    }

    public function destroy(Request $request, HarvestPlan $harvestPlan)
    {
        $plan = $this->owned($request)->findOrFail($harvestPlan->id);
        abort_unless(in_array($plan->status, ['planned', 'cancelled'], true), 403, 'Seules les récoltes planifiées ou annulées peuvent être supprimées.');
        $plan->histories()->create(['user_id' => $request->user()->id, 'event' => 'deleted']);
        $plan->delete();
        return redirect()->route('producer.harvest-plans.index')->with('status', 'Planification supprimée.');
    }

    private function validated(Request $request, ?HarvestPlan $plan = null): array
    {
        $farmId = $request->input('farm_id');
        $today = today()->toDateString();
        $startDate = $request->input('starts_at');
        $endDate = $request->input('ends_at');
        $unchangedPastStart = $plan && $plan->starts_at->lt(today()) && $startDate === $plan->starts_at->toDateString();
        $unchangedPastEnd = $plan && $plan->ends_at->lt(today()) && $endDate === $plan->ends_at->toDateString();
        $startRules = ['required', 'date'];
        $endRules = ['required', 'date', 'after_or_equal:starts_at'];
        if (! $unchangedPastStart) $startRules[] = 'after_or_equal:'.$today;
        if (! $unchangedPastEnd) $endRules[] = 'after_or_equal:'.$today;

        return $request->validate([
            'farm_id' => ['required', 'integer', Rule::exists('farms', 'id')->where('user_id', $request->user()->id)->whereNull('deleted_at')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('user_id', $request->user()->id)->where('farm_id', $farmId)->whereNull('deleted_at')],
            'starts_at' => $startRules,
            'ends_at' => $endRules,
            'estimated_quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'unit' => ['required', 'string', 'max:24'],
            'reminder_days' => ['required', 'integer', 'between:0,30'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'farm_id.required' => 'Sélectionnez une exploitation.',
            'farm_id.exists' => 'Cette exploitation ne vous appartient pas.',
            'product_id.required' => 'Sélectionnez un produit.',
            'product_id.exists' => 'Ce produit doit appartenir à votre compte et à l’exploitation choisie.',
            'starts_at.required' => 'La date de début est obligatoire.',
            'ends_at.required' => 'La date de fin est obligatoire.',
            'ends_at.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'estimated_quantity.required' => 'Indiquez la quantité estimée.',
            'estimated_quantity.gt' => 'La quantité estimée doit être supérieure à zéro.',
            'reminder_days.between' => 'Le délai du rappel doit être compris entre 0 et 30 jours.',
        ], [
            'farm_id' => 'exploitation', 'product_id' => 'produit', 'starts_at' => 'date de début',
            'ends_at' => 'date de fin', 'estimated_quantity' => 'quantité estimée', 'reminder_days' => 'délai du rappel',
        ]);
    }

    private function owned(Request $request): Builder
    {
        return HarvestPlan::query()->where('user_id', $request->user()->id);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query->when($filters['farm_id'] ?? null, fn (Builder $q, $id) => $q->where('farm_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $q, $id) => $q->where('product_id', $id));
        if (($filters['status'] ?? null) === 'overdue') $query->whereIn('status', ['planned', 'in_progress'])->whereDate('ends_at', '<', today());
        elseif (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (($filters['period'] ?? null) === 'today') $query->whereDate('starts_at', '<=', today())->whereDate('ends_at', '>=', today());
        elseif (($filters['period'] ?? null) === 'week') $query->whereDate('starts_at', '<=', today()->endOfWeek())->whereDate('ends_at', '>=', today()->startOfWeek());
        if (! empty($filters['q'])) $query->where(fn (Builder $q) => $q->where('reference', 'like', '%'.$filters['q'].'%')->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', '%'.$filters['q'].'%')));
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'in_progress' => '#2675a8', 'completed' => '#2d6a4f', 'overdue' => '#c2413b', 'cancelled' => '#7b8490', default => '#d29a25',
        };
    }
}
