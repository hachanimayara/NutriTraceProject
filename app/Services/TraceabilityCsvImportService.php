<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TraceabilityCsvImportService
{
    private const REQUIRED = [
        'farms' => ['name', 'reference', 'location', 'country'],
        'products' => ['name', 'reference', 'category', 'unit', 'status'],
        'lots' => ['product_id', 'code', 'quantity', 'unit', 'status'],
        'transformations' => ['reference', 'name', 'performed_at', 'status'],
        'stores' => ['name', 'reference', 'address', 'country', 'status'],
        'distributions' => ['reference', 'lot_id', 'store_id', 'quantity_shipped', 'unit', 'status'],
        'certifications' => ['title', 'scheme', 'certificate_number', 'issuer', 'status'],
        'indicators' => ['stage', 'metric_code', 'name', 'value', 'unit'],
    ];

    private const ALIASES = [
        'name' => ['nom'],
        'reference' => ['référence', 'ref', 'reference_du_produit'],
        'location' => ['localisation', 'lieu'], 'country' => ['pays'],
        'farming_method' => ['agriculture', 'mode_de_culture', 'méthode_de_culture'],
        'area_hectares' => ['surface', 'superficie', 'hectares'], 'started_at' => ['date_debut', 'date_de_début'],
        'category' => ['catégorie', 'categorie'], 'farm_id' => ['exploitation_id'],
        'quantity' => ['quantité', 'quantite'], 'unit' => ['unité', 'unite'],
        'status' => ['statut'], 'product_id' => ['produit_id'], 'code' => ['code_du_lot', 'numéro_de_lot', 'numero_de_lot'],
        'produced_at' => ['date_de_production'], 'expires_at' => ['date_expiration', 'date_d_expiration'],
        'facility' => ['site'], 'performed_at' => ['date_de_transformation'],
        'process_description' => ['description_du_procédé', 'description_du_procede'],
        'energy_kwh' => ['énergie_kwh', 'energie_kwh'], 'water_liters' => ['eau_litres'],
        'address' => ['adresse'], 'city' => ['ville'], 'contact_name' => ['responsable', 'personne_à_contacter', 'personne_a_contacter'],
        'phone' => ['téléphone', 'telephone'], 'lot_id' => ['lot_id'], 'store_id' => ['point_de_vente_id'],
        'quantity_shipped' => ['quantité_expédiée', 'quantite_expediee'], 'quantity_delivered' => ['quantité_livrée', 'quantite_livree'],
        'shipped_at' => ['expédition', 'expedition', 'date_expedition'], 'expected_at' => ['livraison_prévue', 'livraison_prevue'],
        'delivered_at' => ['date_de_livraison'], 'title' => ['certification', 'nom_de_la_certification'],
        'scheme' => ['référentiel', 'referentiel'], 'certificate_number' => ['numéro_de_certificat', 'numero_de_certificat'],
        'issuer' => ['organisme', 'organisme_certificateur'], 'issued_at' => ['date_de_délivrance', 'date_de_delivrance'],
        'stage' => ['étape', 'etape'], 'metric_code' => ['code_de_mesure'], 'value' => ['valeur'],
        'method' => ['méthode', 'methode'], 'measured_at' => ['date_de_mesure'], 'source_notes' => ['source_et_notes'],
        'description' => ['description'], 'notes' => ['notes'], 'origin' => ['origine'],
    ];

    private const RESOURCE_NAME_ALIASES = [
        'farms' => ['exploitation'], 'products' => ['produit'],
        'transformations' => ['operation', 'opération'], 'stores' => ['point_de_vente'],
        'indicators' => ['indicateur'],
    ];

    public function analyze(UploadedFile $file, string $resource, array $allowedFields): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || $contents === '' || str_contains($contents, "\0")) {
            return ['compatible' => false, 'headers' => [], 'unknown' => [], 'rows' => [], 'error' => 'Le fichier est vide ou contient des données binaires.'];
        }
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $firstLine = strtok($contents, "\r\n") ?: '';
        $delimiter = $this->delimiter($firstLine);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $header = fgetcsv($stream, 0, $delimiter, '"', '\\');
        if (! is_array($header) || count($header) < 1) {
            fclose($stream);
            return ['compatible' => false, 'headers' => [], 'unknown' => [], 'rows' => [], 'error' => 'Impossible de lire les en-têtes CSV.'];
        }

        $fieldLookup = [];
        foreach ($allowedFields as $field) {
            $fieldLookup[$this->normalize($field)] = $field;
            foreach (self::ALIASES[$field] ?? [] as $alias) $fieldLookup[$this->normalize($alias)] = $field;
        }
        foreach (self::RESOURCE_NAME_ALIASES[$resource] ?? [] as $alias) {
            if (in_array('name', $allowedFields, true)) $fieldLookup[$this->normalize($alias)] = 'name';
        }
        $headers = [];
        $unknown = [];
        $ambiguous = [];
        foreach ($header as $index => $label) {
            $normalized = $this->normalize((string) $label);
            $mapped = $fieldLookup[$normalized] ?? null;
            if ($mapped === null) {
                $unknown[] = trim((string) $label);
                $headers[$index] = null;
                continue;
            }
            if (in_array($mapped, $headers, true)) $ambiguous[] = $mapped;
            $headers[$index] = $mapped;
        }

        $required = self::REQUIRED[$resource] ?? [];
        $rows = [];
        $line = 1;
        while (($values = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            $line++;
            if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) continue;
            if (count($rows) >= 100) {
                fclose($stream);
                return ['compatible' => false, 'headers' => $header, 'unknown' => $unknown, 'rows' => [], 'error' => 'Le fichier dépasse la limite de 100 lignes. Divisez-le en plusieurs fichiers.'];
            }
            $data = [];
            $issues = [];
            foreach ($headers as $index => $field) {
                if ($field === null || ! array_key_exists($index, $values)) continue;
                $value = trim((string) $values[$index]);
                if ($value !== '') {
                    if ($this->isNumeric($field) && ! str_contains($value, '.') && str_contains($value, ',')) $value = str_replace(',', '.', $value);
                    if ($this->isDate($field) && $this->validDate($value)) $value = $this->formatDateForForm($field, $value);
                    if (array_key_exists($field, $data)) {
                        $issues[] = "Plusieurs colonnes alimentent le champ « {$field} ».";
                        unset($data[$field]);
                    } elseif (! in_array($field, $ambiguous, true)) {
                        $data[$field] = in_array($field, ['associated_products', 'input_lots', 'output_lots'], true)
                            ? array_values(array_filter(array_map('trim', explode('|', $value)), fn ($item) => $item !== ''))
                            : $value;
                    }
                }
            }
            foreach ($required as $field) {
                if (! isset($data[$field]) || trim((string) $data[$field]) === '') $issues[] = "Champ obligatoire à compléter : {$field}.";
            }
            foreach ($data as $field => $value) {
                if ($this->isNumeric($field) && ! is_numeric(str_replace(',', '.', $value))) $issues[] = "Valeur numérique invalide dans « {$field} » : {$value}.";
                if (in_array($field, ['area_hectares', 'quantity', 'quantity_shipped', 'quantity_delivered', 'energy_kwh', 'water_liters'], true) && is_numeric($value) && (float) $value < 0) $issues[] = "La valeur de « {$field} » ne peut pas être négative.";
                if ($field === 'quantity' && is_numeric($value) && (float) $value <= 0 && $resource === 'lots') $issues[] = 'La quantité d’un lot doit être supérieure à zéro.';
                if ($field === 'quantity_shipped' && is_numeric($value) && (float) $value <= 0) $issues[] = 'La quantité expédiée doit être supérieure à zéro.';
                if ($this->isDate($field) && ! $this->validDate($value)) $issues[] = "Date invalide dans « {$field} » : {$value}.";
            }
            $rows[] = ['line' => $line, 'data' => $data, 'issues' => array_values(array_unique($issues)), 'status' => $issues ? 'à compléter / vérifier' : 'prête à vérifier'];
        }
        fclose($stream);
        $recognized = count(array_filter($headers, fn ($field) => $field !== null));

        return [
            'compatible' => $recognized > 0 && $rows !== [],
            'headers' => array_values($header),
            'unknown' => array_values(array_filter($unknown)),
            'rows' => $rows,
            'error' => $recognized ? null : 'Aucune colonne ne correspond au formulaire de cette gestion.',
        ];
    }

    public function templateHeaders(array $fields): array
    {
        return $fields;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/_+/', '_', str_replace(' ', '_', Str::lower(Str::ascii(trim($value))))), '_');
    }

    private function delimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);
        return array_key_first($counts) ?: ';';
    }

    private function isNumeric(string $field): bool
    {
        return in_array($field, ['latitude', 'longitude', 'area_hectares', 'quantity', 'quantity_shipped', 'quantity_delivered', 'energy_kwh', 'water_liters', 'value'], true);
    }

    private function isDate(string $field): bool
    {
        return str_ends_with($field, '_at');
    }

    private function validDate(string $value): bool
    {
        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\\TH:i', 'd/m/Y', 'd/m/Y H:i'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return true;
        }
        return false;
    }

    private function formatDateForForm(string $field, string $value): string
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d\\TH:i', 'Y-m-d', 'd/m/Y H:i', 'd/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date && (\DateTimeImmutable::getLastErrors() === false || \DateTimeImmutable::getLastErrors()['error_count'] === 0)) {
                return in_array($field, ['produced_at', 'expires_at', 'issued_at', 'measured_at', 'started_at'], true)
                    ? $date->format('Y-m-d')
                    : $date->format('Y-m-d\\TH:i');
            }
        }
        return $value;
    }
}
