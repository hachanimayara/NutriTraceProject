<?php

namespace App\Services;

use App\Models\Certification;
use App\Models\Distribution;
use App\Models\EnvironmentalIndicator;
use App\Models\Lot;
use App\Models\TraceabilityAlert;
use App\Models\Transformation;
use Illuminate\Support\Collection;

/** Deterministic data-quality checks. This service does not call or simulate an AI model. */
class TraceabilityAnalysisService
{
    public function analyze(): array
    {
        $createdOrUpdated = 0;

        Lot::where('status', 'active')->whereNotNull('expires_at')->whereDate('expires_at', '<', today())
            ->each(function (Lot $lot) use (&$createdOrUpdated) {
                $createdOrUpdated += (int) $this->openAlert($lot, 'lot_expired', 'critical', 'Lot arrivé à expiration', 'La date d’expiration enregistrée est dépassée. Vérifiez le statut et les mouvements de ce lot.', ['expires_at' => $lot->expires_at?->toDateString()]);
            });

        Certification::where('status', 'valid')->whereNotNull('expires_at')->whereDate('expires_at', '<', today())
            ->each(function (Certification $certification) use (&$createdOrUpdated) {
                $createdOrUpdated += (int) $this->openAlert($certification, 'certification_expired', 'warning', 'Certification expirée', 'La date de validité enregistrée est dépassée. Une vérification humaine est nécessaire.', ['expires_at' => $certification->expires_at?->toDateString()]);
            });

        Distribution::whereIn('status', ['planned', 'in_transit'])->whereNotNull('expected_at')->where('expected_at', '<', now())
            ->each(function (Distribution $distribution) use (&$createdOrUpdated) {
                $createdOrUpdated += (int) $this->openAlert($distribution, 'distribution_late', 'warning', 'Livraison en retard', 'La date prévue est passée et le statut indique que la livraison n’est pas terminée.', ['expected_at' => $distribution->expected_at?->toIso8601String(), 'status' => $distribution->status]);
            });

        EnvironmentalIndicator::whereNull('method')->orWhere('method', '')->each(function (EnvironmentalIndicator $indicator) use (&$createdOrUpdated) {
            $createdOrUpdated += (int) $this->openAlert($indicator, 'indicator_missing_method', 'info', 'Méthode de mesure manquante', 'La mesure ne documente pas de méthode de calcul ou de mesure. Sa comparabilité ne peut pas être évaluée.', ['metric_code' => $indicator->metric_code, 'value' => (string) $indicator->value, 'unit' => $indicator->unit]);
        });

        Transformation::with('lots')->each(function (Transformation $transformation) use (&$createdOrUpdated) {
            $inputs = $transformation->lots->where('pivot.direction', 'input');
            $outputs = $transformation->lots->where('pivot.direction', 'output');
            if ($inputs->isEmpty() || $outputs->isEmpty()) {
                $createdOrUpdated += (int) $this->openAlert($transformation, 'transformation_incomplete_links', 'warning', 'Étapes de lot incomplètes', 'La transformation doit relier au moins un lot entrant et un lot sortant pour assurer une continuité vérifiable.', ['input_lots' => $inputs->count(), 'output_lots' => $outputs->count()]);
                return;
            }

            $inputQuantity = $this->quantityForSameUnit($inputs);
            $outputQuantity = $this->quantityForSameUnit($outputs);
            if ($inputQuantity !== null && $outputQuantity !== null && $outputQuantity > $inputQuantity) {
                $createdOrUpdated += (int) $this->openAlert($transformation, 'transformation_output_exceeds_input', 'warning', 'Quantité sortante supérieure à la quantité entrante', 'La comparaison arithmétique des quantités liées signale un écart à vérifier. Les unités ne sont pas converties et l’alerte ne constitue pas une conclusion de fraude.', ['input_quantity' => $inputQuantity, 'output_quantity' => $outputQuantity]);
            }
        });

        return ['alerts_open' => TraceabilityAlert::where('status', 'open')->count(), 'checks_triggered' => $createdOrUpdated];
    }

    private function quantityForSameUnit(Collection $lots): ?float
    {
        $units = $lots->pluck('pivot.unit')->unique();
        if ($units->count() !== 1) return null;

        return (float) $lots->sum(fn (Lot $lot) => (float) $lot->pivot->quantity);
    }

    private function openAlert($subject, string $code, string $severity, string $title, string $explanation, array $evidence): bool
    {
        $alert = TraceabilityAlert::firstOrNew([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'code' => $code,
            'status' => 'open',
        ]);
        $alert->fill(['created_by' => null, 'severity' => $severity, 'title' => $title, 'explanation' => $explanation, 'evidence' => $evidence]);
        $alert->save();

        return true;
    }
}
