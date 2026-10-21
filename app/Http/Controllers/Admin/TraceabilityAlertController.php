<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TraceabilityAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TraceabilityAlertController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.compliance.alerts');
    }

    public function analyze(TraceabilityAnalysisService $analysis): RedirectResponse
    {
        $result = $analysis->analyze();

        return redirect()->route('admin.compliance.dashboard')->with('status', $result['checks_triggered'].' signal(s) détecté(s) ou actualisé(s). '.$result['alerts_open'].' alerte(s) ouverte(s) au total. Contrôles déterministes, sans modèle IA.');
    }

    public function resolve(TraceabilityAlert $alert, Request $request): RedirectResponse
    {
        return redirect()->route('admin.compliance.alerts.show', $alert)
            ->with('status', 'Ajoutez une décision motivée dans le dossier avant de clôturer cette anomalie.');
    }
}
