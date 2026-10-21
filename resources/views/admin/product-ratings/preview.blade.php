<p class="detail-preview-kicker">Avis consommateur</p>
<h2 class="detail-preview-title">{{ $rating->product?->name ?? 'Produit supprimé' }}</h2>
<p class="detail-preview-meta">Reçu le {{ $rating->created_at?->format('d/m/Y à H:i') ?? 'Date inconnue' }}</p>

<dl class="detail-preview-grid">
    <div class="detail-preview-item">
        <dt>Consommateur</dt>
        <dd>{{ $rating->consumer?->name ?? ($rating->consumer_name ?: 'Visiteur sans nom communiqué') }}</dd>
    </div>
    <div class="detail-preview-item">
        <dt>E-mail</dt>
        <dd>{{ $rating->consumer?->email ?? 'Non communiqué' }}</dd>
    </div>
    <div class="detail-preview-item">
        <dt>Référence produit</dt>
        <dd>{{ $rating->product?->reference ?? 'Non renseignée' }}</dd>
    </div>
    <div class="detail-preview-item">
        <dt>Note</dt>
        <dd><span style="color:#bd8321">{{ str_repeat('★', $rating->rating) }}{{ str_repeat('☆', 5 - $rating->rating) }}</span> ({{ $rating->rating }}/5)</dd>
    </div>
    <div class="detail-preview-item">
        <dt>Publication</dt>
        <dd>{{ $rating->status === 'published' ? 'Publié' : 'Masqué' }}</dd>
    </div>
    <div class="detail-preview-item">
        <dt>Commentaire</dt>
        <dd>{{ $rating->comment ?: 'Aucun commentaire.' }}</dd>
    </div>
</dl>
