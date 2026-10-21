<?php

namespace App\Services;

use App\Models\Certification;
use App\Models\Distribution;
use App\Models\EnvironmentalIndicator;
use App\Models\Lot;
use App\Models\Product;
use App\Models\TraceabilityAlert;
use App\Models\Transformation;
use Illuminate\Support\Collection;

/** Deterministic data-quality checks. This service does not call or simulate an AI model. */
class TraceabilityAnalysisService
{
    public function analyze(): array
    {
        $createdOrUpdated = 0;
        $warningDays = max(1, (int) config('compliance.renewal_warning_days', 30));
        $requiredDocumentSchemes = config('compliance.required_certification_schemes', []);

        Certification::with(['product.lots', 'farm.certifications', 'farm.products.lots'])->each(function (Certification $certification) use (&$createdOrUpdated, $warningDays, $requiredDocumentSchemes) {
            $expiresAt = $certification->expires_at?->startOfDay();
            $affectedLotsQuery = Lot::query()->where('status', 'active')->where(function ($query) use ($certification) {
                if ($certification->product_id) $query->where('product_id', $certification->product_id);
                if ($certification->farm_id) $query->orWhereHas('product', fn ($product) => $product->where('farm_id', $certification->farm_id));
            });
            $affectedLotCodes = (clone $affectedLotsQuery)->pluck('code');

            if (in_array($certification->status, ['valid', 'expired'], true) && $expiresAt && $expiresAt->lt(today())) {
                $statusMismatch = $certification->status === 'valid';
                $title = $statusMismatch ? 'Certification enregistrée comme valide mais expirée' : 'Certification expirée';
                $explanation = $statusMismatch ? 'Le statut administratif indique « validé », mais la date d’expiration enregistrée est dépassée. Une vérification humaine est nécessaire avant de s’appuyer sur cette certification.' : 'La date d’expiration est dépassée. Vérifiez les lots et les mouvements liés à ce dossier.';
                $createdOrUpdated += (int) $this->openAlert($certification, 'certification_expired', $affectedLotCodes->isNotEmpty() ? 'critical' : 'warning', $title, $explanation, ['status' => $certification->status, 'expires_at' => $certification->expires_at->toDateString(), 'affected_active_lots' => $affectedLotCodes->values()->all()]);
            } elseif ($certification->status === 'valid' && $expiresAt && $expiresAt->between(today(), today()->addDays($warningDays))) {
                $createdOrUpdated += (int) $this->openAlert($certification, 'certification_expiring', 'warning', 'Certification proche de son expiration', 'La date de validité approche selon le seuil configuré. Préparez le renouvellement et vérifiez les lots associés.', ['expires_at' => $certification->expires_at->toDateString(), 'warning_days' => $warningDays, 'affected_lots' => $affectedLotCodes->values()->all()]);
            } elseif ($certification->status === 'expired' && $expiresAt && $expiresAt->gte(today())) {
                $createdOrUpdated += (int) $this->openAlert($certification, 'certification_status_date_mismatch', 'warning', 'Statut expiré avec une date encore valide', 'Le statut enregistré est « expiré », mais la date d’expiration est aujourd’hui ou ultérieure. Vérifiez le dossier sans modifier automatiquement son statut.', ['status' => $certification->status, 'expires_at' => $certification->expires_at->toDateString()]);
            } elseif ($certification->status === 'valid' && ! $expiresAt) {
                $createdOrUpdated += (int) $this->openAlert($certification, 'certification_expiry_unknown', 'info', 'Date d’expiration non renseignée', 'La certification est enregistrée comme valide, mais aucune date d’expiration ne permet de vérifier sa validité dans le temps.', ['status' => $certification->status, 'expires_at' => null]);
            }
            if ($certification->issued_at && $certification->expires_at && $certification->issued_at->gt($certification->expires_at)) {
                $createdOrUpdated += (int) $this->openAlert($certification, 'certification_invalid_dates', 'warning', 'Dates de certification incohérentes', 'La date de délivrance enregistrée est postérieure à la date d’expiration. Vérifiez les dates et le justificatif.', ['issued_at' => $certification->issued_at->toDateString(), 'expires_at' => $certification->expires_at->toDateString()]);
            }

            $documentRequired = in_array('*', $requiredDocumentSchemes, true)
                || collect($requiredDocumentSchemes)->contains(fn ($scheme) => mb_strtolower((string) $scheme) === mb_strtolower($certification->scheme) || mb_strtolower((string) $scheme) === mb_strtolower($certification->title));
            if ($documentRequired && (! $certification->document_path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($certification->document_path))) {
                $createdOrUpdated += (int) $this->openAlert($certification, 'certification_document_missing', 'warning', 'Justificatif de certification manquant', 'La configuration de conformité exige un justificatif pour ce référentiel, mais aucun fichier accessible n’est associé au dossier.', ['scheme' => $certification->scheme, 'document_path_present' => (bool) $certification->document_path]);
            }
        });

        foreach ((array) config('compliance.required_certifications_by_category', []) as $category => $schemes) {
            $products = Product::query()->where('status', 'published')->when($category !== '*', fn ($query) => $query->where('category', $category))->with(['farm', 'certifications'])->get();
            foreach ($products as $product) {
                foreach ((array) $schemes as $scheme) {
                    $certificates = $product->certifications->concat($product->farm?->certifications ?? collect());
                    $isCovered = $certificates->contains(fn (Certification $certification) => (mb_strtolower($certification->scheme) === mb_strtolower((string) $scheme) || mb_strtolower($certification->title) === mb_strtolower((string) $scheme)) && $certification->status === 'valid' && (! $certification->expires_at || $certification->expires_at->gte(today())));
                    if (! $isCovered) {
                        $createdOrUpdated += (int) $this->openAlert($product, 'required_certification_missing', 'warning', 'Certification requise à vérifier', 'Le référentiel configuré pour cette catégorie n’est pas couvert par une certification enregistrée comme valide et non expirée. Vérifiez les données et les exigences applicables.', ['required_scheme' => (string) $scheme, 'category' => $product->category, 'product_reference' => $product->reference]);
                    }
                }
            }
        }

        foreach ((array) config('compliance.required_indicator_codes_by_stage', []) as $stage => $codes) {
            foreach (Product::query()->where('status', 'published')->with('lots:id,product_id')->get(['id', 'name', 'reference', 'category']) as $product) {
                $lotIds = $product->lots->pluck('id');
                foreach ((array) $codes as $metricCode) {
                    $exists = EnvironmentalIndicator::query()->where('metric_code', $metricCode)->when($stage !== '*', fn ($query) => $query->where('stage', $stage))
                        ->where(function ($query) use ($product, $lotIds) { $query->where('product_id', $product->id); if ($lotIds->isNotEmpty()) $query->orWhereIn('lot_id', $lotIds); })->exists();
                    if (! $exists) {
                        $createdOrUpdated += (int) $this->openAlert($product, 'required_indicator_missing', 'info', 'Indicateur environnemental requis absent', 'Le code d’indicateur configuré pour cette étape n’est pas enregistré pour ce produit ni ses lots liés. Le dossier est incomplet au regard de cette règle configurée.', ['required_metric_code' => (string) $metricCode, 'required_stage' => (string) $stage, 'product_reference' => $product->reference]);
                    }
                }
            }
        }

        Lot::where('status', 'active')->whereNotNull('expires_at')->whereDate('expires_at', '<', today())
            ->each(function (Lot $lot) use (&$createdOrUpdated) {
                $createdOrUpdated += (int) $this->openAlert($lot, 'lot_expired', 'critical', 'Lot arrivé à expiration', 'La date d’expiration enregistrée est dépassée. Vérifiez le statut et les mouvements de ce lot.', ['expires_at' => $lot->expires_at?->toDateString()]);
            });

        Distribution::whereIn('status', ['planned', 'in_transit'])->whereNotNull('expected_at')->where('expected_at', '<', now())
            ->each(function (Distribution $distribution) use (&$createdOrUpdated) {
                $createdOrUpdated += (int) $this->openAlert($distribution, 'distribution_late', 'warning', 'Livraison en retard', 'La date prévue est passée et le statut indique que la livraison n’est pas terminée.', ['expected_at' => $distribution->expected_at?->toIso8601String(), 'status' => $distribution->status]);
            });

        EnvironmentalIndicator::where(fn ($query) => $query->whereNull('method')->orWhere('method', ''))->each(function (EnvironmentalIndicator $indicator) use (&$createdOrUpdated) {
            $createdOrUpdated += (int) $this->openAlert($indicator, 'indicator_missing_method', 'info', 'Méthode de mesure manquante', 'La mesure ne documente pas de méthode de calcul ou de mesure. Sa comparabilité ne peut pas être évaluée.', ['metric_code' => $indicator->metric_code, 'value' => (string) $indicator->value, 'unit' => $indicator->unit]);
        });

        EnvironmentalIndicator::where('value', '<', 0)->each(function (EnvironmentalIndicator $indicator) use (&$createdOrUpdated) {
            $createdOrUpdated += (int) $this->openAlert($indicator, 'indicator_negative_value', 'warning', 'Valeur environnementale négative', 'La mesure quantitative est négative. Vérifiez sa saisie, son unité et sa méthode; aucune conversion ni correction automatique n’est appliquée.', ['metric_code' => $indicator->metric_code, 'value' => (string) $indicator->value, 'unit' => $indicator->unit]);
        });

        foreach ((array) config('compliance.indicator_rules', []) as $metricCode => $rule) {
            EnvironmentalIndicator::where('metric_code', $metricCode)->each(function (EnvironmentalIndicator $indicator) use (&$createdOrUpdated, $rule, $metricCode) {
                $allowedUnits = (array) ($rule['units'] ?? []);
                if ($allowedUnits !== [] && ! in_array($indicator->unit, $allowedUnits, true)) {
                    $createdOrUpdated += (int) $this->openAlert($indicator, 'indicator_unit_invalid', 'warning', 'Unité non conforme à la règle configurée', 'L’unité enregistrée ne correspond à aucune unité autorisée pour cette mesure. Les valeurs ne sont pas converties automatiquement.', ['metric_code' => $metricCode, 'actual_unit' => $indicator->unit, 'allowed_units' => $allowedUnits]);
                }
                $value = (float) $indicator->value;
                $minimum = isset($rule['min']) ? (float) $rule['min'] : null;
                $maximum = isset($rule['max']) ? (float) $rule['max'] : null;
                if (($minimum !== null && $value < $minimum) || ($maximum !== null && $value > $maximum)) {
                    $createdOrUpdated += (int) $this->openAlert($indicator, 'indicator_out_of_range', 'warning', 'Valeur hors plage configurée', 'La valeur sort de la plage configurée pour cette mesure. Vérifiez la donnée source et sa méthode sans remplacer la valeur déclarée.', ['metric_code' => $metricCode, 'value' => $value, 'unit' => $indicator->unit, 'configured_min' => $minimum, 'configured_max' => $maximum]);
                }
            });
        }

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

        return ['alerts_open' => TraceabilityAlert::whereNotIn('status', ['resolved', 'dismissed'])->count(), 'checks_triggered' => $createdOrUpdated];
    }

    private function quantityForSameUnit(Collection $lots): ?float
    {
        $units = $lots->pluck('pivot.unit')->unique();
        if ($units->count() !== 1) return null;

        return (float) $lots->sum(fn (Lot $lot) => (float) $lot->pivot->quantity);
    }

    private function openAlert($subject, string $code, string $severity, string $title, string $explanation, array $evidence): bool
    {
        $alert = TraceabilityAlert::query()->where([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'code' => $code,
        ])->whereNotIn('status', ['resolved', 'dismissed'])->latest()->first() ?? new TraceabilityAlert([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'code' => $code,
            'status' => 'open',
        ]);
        $priorityReason = match ($severity) {
            'critical' => 'Impact potentiel sur un ou plusieurs lots actifs; examen prioritaire requis.',
            'warning' => 'Échéance ou cohérence de données à vérifier rapidement.',
            default => 'Information incomplète; à examiner selon le contexte du dossier.',
        };
        $alert->fill(['created_by' => null, 'severity' => $severity, 'title' => $title, 'explanation' => $explanation, 'evidence' => $evidence, 'priority_reason' => $priorityReason, 'last_checked_at' => now()]);
        $alert->save();

        return true;
    }
}
