# Analyse des certificats avec Google Gemini

## Configuration

Configurer localement le `.env` du serveur, sans committer la clé :

```dotenv
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash
GEMINI_DOCUMENT_ANALYSIS_ENABLED=false
```

Créer une clé dans Google AI Studio. La clé est lue par `config/services.php`, envoyée uniquement depuis Laravel via l’en-tête `x-goog-api-key` et n’est jamais rendue au navigateur ni journalisée. Une fois le projet Google, ses quotas et ses éventuels coûts vérifiés, renseigner la clé et activer `GEMINI_DOCUMENT_ANALYSIS_ENABLED=true`. Puis exécuter `php artisan config:clear` et redémarrer les workers.

Google currently limits Gemini 2.5 access to accounts that have actively used the 2.5 models; for new projects its docs recommend a newer Flash model. NutriTrace keeps the requested `gemini-2.5-flash` default and never silently switches models. If the Google project cannot access it, set `GEMINI_MODEL` explicitly to an available model. See https://ai.google.dev/gemini-api/docs/models.

## Documents pris en charge

L’import accepte PDF, JPG/JPEG, PNG et WEBP de 10 Mo maximum. Après consentement explicite, le document est envoyé en contenu inline multimodal (base64) à Gemini. Aucune requête externe n’est faite si la clé manque, si la fonction est désactivée ou si le consentement n’a pas été donné. Les fichiers importés restent sur le disque privé local.

Le document est traité comme une entrée non fiable; ses éventuelles instructions sont ignorées. Les propositions IA et les anomalies automatiques restent soumises à une vérification humaine et ne modifient pas les certifications officielles.

## Exécution des jobs

Exécuter `php artisan migrate` si la table `document_analyses` n’existe pas déjà, puis lancer un worker persistant :

```sh
php artisan queue:work --tries=2
```

La page d’analyse interroge son état en rechargeant la vue uniquement tant que le statut est `processing`. Quand le job finit ou échoue, le prochain chargement affiche le résumé ou le message d’erreur et le polling s’arrête. Pour une queue database, vérifier aussi que le worker utilise la même base et la même configuration que le serveur web.

Les erreurs de clé/accès, de quota, de modèle, de requête, de document trop volumineux, de blocage de sécurité, de réseau ou de réponse invalide deviennent des codes sûrs affichés dans l’interface. Les refus permanents ne sont pas réessayés automatiquement. Une limite 429 avec `Retry-After` peut être réessayée une fois selon la configuration du job. Les secrets et les réponses brutes du fournisseur ne sont pas stockés ni journalisés.

## Tests

```sh
php artisan test --filter=DocumentAnalysisTest
```

Les tests utilisent `Http::fake()` et n’ont besoin d’aucune clé réelle ni de crédits API.

## Documentation officielle

- API `generateContent`, formats image/PDF et contenu multimodal : https://ai.google.dev/api/generate-content
- JSON structuré : https://ai.google.dev/gemini-api/docs/structured-output
- Clés API et sécurité : https://ai.google.dev/gemini-api/docs/api-key
- Tarifs et limites : https://ai.google.dev/gemini-api/docs/pricing
