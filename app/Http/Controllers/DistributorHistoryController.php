<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DistributorHistoryController extends Controller
{
    private const STATUSES = ['planned', 'in_transit', 'delivered', 'delayed', 'cancelled'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(['preparation', 'expedition', 'livraison'])],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $base = Distribution::query()->where('user_id', $request->user()->id);
        $query = (clone $base)->with(['lot.product', 'store']);
        if (! empty($filters['q'])) {
            $term = trim($filters['q']);
            $query->where(fn ($matches) => $matches->where('reference', 'like', "%{$term}%")
                ->orWhereHas('lot', fn ($lot) => $lot->where('code', 'like', "%{$term}%"))
                ->orWhereHas('lot.product', fn ($product) => $product->where('name', 'like', "%{$term}%")));
        }
        if (($filters['type'] ?? null) === 'livraison') {
            $query->where('status', 'delivered');
        }
        if (($filters['type'] ?? null) === 'expedition') {
            $query->whereNotNull('shipped_at')->where('status', '!=', 'delivered');
        }
        if (($filters['type'] ?? null) === 'preparation') {
            $query->where('status', 'planned');
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->whereRaw('DATE(COALESCE(delivered_at, shipped_at, created_at)) >= ?', [$filters['from']]);
        }
        if (! empty($filters['to'])) {
            $query->whereRaw('DATE(COALESCE(delivered_at, shipped_at, created_at)) <= ?', [$filters['to']]);
        }

        return view('traceability.distributor-history', [
            'records' => $query->orderByRaw('COALESCE(delivered_at, shipped_at, created_at) DESC')->orderByDesc('id')->paginate(12)->withQueryString(),
            'stats' => [
                'total' => (clone $base)->count(),
                'delivered' => (clone $base)->where('status', 'delivered')->count(),
                'shipped' => (clone $base)->whereNotNull('shipped_at')->count(),
                'attention' => (clone $base)->where('status', 'delayed')->count(),
            ],
            'filters' => $filters,
            'hasHistory' => (clone $base)->exists(),
            'statusLabels' => ['planned' => 'Préparée', 'in_transit' => 'En transit', 'delivered' => 'Livrée', 'delayed' => 'En retard', 'cancelled' => 'Annulée'],
        ]);
    }

    public function show(Request $request, int $distribution)
    {
        $record = Distribution::query()->where('user_id', $request->user()->id)
            ->with(['lot.product', 'store', 'statusHistory.actor'])->findOrFail($distribution);

        $history = $record->statusHistory;
        $initialEvent = $history->first();
        $previousStatus = $record->status === 'cancelled'
            ? $history->where('to_status', '!=', 'cancelled')->last()?->to_status
            : $record->status;
        $currentStage = match ($previousStatus) {
            'planned' => 2,
            'in_transit', 'delayed' => 3,
            'delivered' => 4,
            default => 1,
        };
        $preparationEvent = $history->first(fn ($event) => in_array($event->to_status, ['preparing', 'prepared'], true));
        $shippingEvent = $history->firstWhere('to_status', 'in_transit');
        $deliveryEvent = $history->last(fn ($event) => $event->to_status === 'delivered');
        $steps = [
            ['label' => 'Commande créée', 'date' => $record->created_at, 'actor' => $initialEvent?->actor, 'state' => 'completed', 'recorded' => true, 'current' => $currentStage === 1 && $record->status !== 'cancelled'],
            ['label' => 'Commande confirmée', 'date' => null, 'actor' => null, 'state' => 'untracked', 'recorded' => false, 'current' => false],
            ['label' => 'Préparation', 'date' => $preparationEvent?->changed_at, 'actor' => $preparationEvent?->actor, 'state' => $currentStage > 2 ? 'completed' : ($currentStage === 2 ? 'current' : 'pending'), 'recorded' => (bool) $preparationEvent, 'current' => $currentStage === 2 && $record->status !== 'cancelled'],
            ['label' => 'Expédition', 'date' => $record->shipped_at ?? $shippingEvent?->changed_at, 'actor' => $shippingEvent?->actor, 'state' => $currentStage > 3 ? 'completed' : ($currentStage === 3 ? 'current' : 'pending'), 'recorded' => (bool) ($record->shipped_at || $shippingEvent), 'current' => $currentStage === 3 && $record->status !== 'cancelled'],
            ['label' => 'Livraison', 'date' => $record->delivered_at ?? $deliveryEvent?->changed_at, 'actor' => $deliveryEvent?->actor, 'state' => $previousStatus === 'delivered' ? 'completed' : 'pending', 'recorded' => (bool) ($record->delivered_at || $deliveryEvent), 'current' => $previousStatus === 'delivered' && $record->status !== 'cancelled'],
        ];

        return view('traceability.distributor-history-show', [
            'record' => $record,
            'timeline' => $steps,
            'statusChanges' => $history,
            'isCancelled' => $record->status === 'cancelled',
            'statusLabels' => ['planned' => 'Préparée', 'in_transit' => 'En transit', 'delivered' => 'Livrée', 'delayed' => 'En retard', 'cancelled' => 'Annulée'],
        ]);
    }
}
