<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TraceabilityAlert;
use App\Services\TraceabilityAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TraceabilityAlertController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:open,resolved'],
            'severity' => ['nullable', 'in:info,warning,critical'],
        ]);
        $alerts = TraceabilityAlert::with('creator')
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(function ($where) use ($term) {
                $like = '%'.trim($term).'%';
                $where->where('title', 'like', $like)
                    ->orWhere('explanation', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhereHas('creator', fn ($creator) => $creator->where('name', 'like', $like)->orWhere('email', 'like', $like));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->latest()->paginate(20)->withQueryString();
        $counts = [
            'open' => TraceabilityAlert::where('status', 'open')->count(),
            'critical' => TraceabilityAlert::where('status', 'open')->where('severity', 'critical')->count(),
            'resolved' => TraceabilityAlert::where('status', 'resolved')->count(),
        ];

        return view('admin.alerts.index', compact('alerts', 'counts'));
    }

    public function analyze(TraceabilityAnalysisService $analysis): RedirectResponse
    {
        $result = $analysis->analyze();

        return redirect()->route('admin.alerts.index')->with('status', $result['checks_triggered'].' signal(s) détecté(s) ou actualisé(s). '.$result['alerts_open'].' alerte(s) ouverte(s) au total. Contrôles déterministes, sans modèle IA.');
    }

    public function resolve(TraceabilityAlert $alert, Request $request): RedirectResponse
    {
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('status', 'Alerte marquée comme traitée.');
    }
}
