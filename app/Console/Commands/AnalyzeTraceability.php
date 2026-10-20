<?php

namespace App\Console\Commands;

use App\Services\TraceabilityAnalysisService;
use Illuminate\Console\Command;

class AnalyzeTraceability extends Command
{
    protected $signature = 'nutritrace:analyze';

    protected $description = 'Run deterministic traceability and data-quality checks';

    public function handle(TraceabilityAnalysisService $analysis): int
    {
        $result = $analysis->analyze();
        $this->info($result['checks_triggered'].' alertes nouvelles ou mises à jour; '.$result['alerts_open'].' alertes ouvertes.');
        $this->comment('Contrôles déterministes explicables; aucun modèle IA externe n’est utilisé.');

        return self::SUCCESS;
    }
}
