@php
    $title = match ($resource) {
        'lots' => $record->code,
        'certifications' => $record->title,
        'indicators' => $record->name,
        default => $record->name ?? $record->reference,
    };
    $labels = [
        'name' => 'Nom', 'reference' => 'Référence', 'location' => 'Localisation', 'country' => 'Pays',
        'farming_method' => 'Mode de culture', 'area_hectares' => 'Surface (ha)', 'started_at' => 'Date de création',
        'category' => 'Catégorie', 'origin' => 'Origine', 'quantity' => 'Quantité', 'unit' => 'Unité', 'status' => 'Statut',
        'code' => 'Code du lot', 'produced_at' => 'Date de production', 'expires_at' => 'Date d’expiration', 'notes' => 'Notes',
        'facility' => 'Site', 'performed_at' => 'Date de transformation', 'process_description' => 'Procédé',
        'energy_kwh' => 'Énergie (kWh)', 'water_liters' => 'Eau (litres)', 'address' => 'Adresse', 'city' => 'Ville',
        'contact_name' => 'Contact', 'phone' => 'Téléphone', 'quantity_shipped' => 'Quantité expédiée',
        'quantity_delivered' => 'Quantité livrée', 'shipped_at' => 'Date d’expédition', 'expected_at' => 'Date prévue',
        'delivered_at' => 'Date de livraison', 'title' => 'Titre', 'scheme' => 'Référentiel',
        'certificate_number' => 'N° de certificat', 'issuer' => 'Organisme', 'issued_at' => 'Délivré le',
        'stage' => 'Étape', 'metric_code' => 'Code de mesure', 'value' => 'Valeur', 'method' => 'Méthode',
        'measured_at' => 'Mesuré le', 'description' => 'Description', 'source_notes' => 'Source',
    ];
@endphp

<div class="detail-preview">
    <p class="detail-preview-kicker">{{ $config['label'] }}</p>
    <h2 id="detail-preview-heading" class="detail-preview-title">{{ $title ?: $config['label'] }}</h2>
    <p class="detail-preview-meta">Créé le {{ $record->created_at?->format('d/m/Y à H:i') ?? '—' }}</p>

    <dl class="detail-preview-grid">
        @foreach($config['fields'] as $field)
            @continue(in_array($field, ['farm_id', 'product_id', 'lot_id', 'store_id'], true))
            @php $value = $record->{$field}; @endphp
            @continue($value === null || $value === '')
            <div class="detail-preview-item">
                <dt>{{ $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)) }}</dt>
                <dd>{{ $value instanceof \Carbon\CarbonInterface ? $value->format('d/m/Y H:i') : (is_bool($value) ? ($value ? 'Oui' : 'Non') : $value) }}</dd>
            </div>
        @endforeach
        @foreach(['farm' => 'Exploitation liée', 'product' => 'Produit lié', 'lot' => 'Lot lié', 'store' => 'Point de vente'] as $relation => $label)
            @if($record->relationLoaded($relation) && $record->{$relation})
                <div class="detail-preview-item"><dt>{{ $label }}</dt><dd>{{ $record->{$relation}->name ?? $record->{$relation}->code ?? $record->{$relation}->reference ?? '—' }}</dd></div>
            @endif
        @endforeach
    </dl>

    @if($resource === 'transformations')
        <div class="detail-preview-related">
            @foreach(['input' => 'Lots entrants', 'output' => 'Lots sortants'] as $direction => $label)
                <section><h3>{{ $label }}</h3>
                    <ul>
                        @forelse($record->lots->where('pivot.direction', $direction) as $lot)
                            <li>{{ $lot->code }} · {{ $lot->product?->name ?? 'Produit non renseigné' }} — {{ $lot->pivot->quantity }} {{ $lot->pivot->unit }}</li>
                        @empty<li class="detail-preview-muted">Aucun lot associé.</li>@endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    @elseif($resource === 'lots')
        <div class="detail-preview-related"><section><h3>Transformations associées</h3><ul>
            @forelse($record->transformations as $transformation)
                <li>{{ $transformation->name }} · {{ $transformation->reference }}</li>
            @empty<li class="detail-preview-muted">Aucune transformation enregistrée.</li>@endforelse
        </ul></section></div>
    @endif

    <div class="detail-preview-footer">
        <a href="{{ route('workspace.resources.show', [$resource, $record]) }}">Ouvrir la fiche complète</a>
    </div>
</div>
