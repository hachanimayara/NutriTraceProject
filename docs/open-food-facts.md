# Open Food Facts dans NutriTrace

Le catalogue est disponible dans l’espace **Transformateur → Catalogue alimentaire et informations nutritionnelles**. La recherche par nom passe par Search-a-licious (`POST https://search.openfoodfacts.org/search`, avec `q`, `fields`, `langs`, `page` et `page_size`) ; la consultation par code-barres utilise Product Opener v3 (`GET /api/v3/product/{code}`). Les deux appels sont effectués côté Laravel avec un User-Agent identifié, des délais d’attente et un cache (5 minutes pour la recherche et 12 heures pour les fiches code-barres). La recherche est limitée à huit résultats et protégée par une limite de requêtes locale.

Un transformateur choisit un produit NutriTrace publié, puis les champs Open Food Facts à associer. Les données sont conservées dans les colonnes `open_food_facts_*` de `products`, séparément des champs de production locaux. Si un champ importé est déjà présent, le transformateur doit cocher explicitement le remplacement. La saisie manuelle est disponible lorsque le produit manque ou que l’API est temporairement inaccessible. Les opérations et lots ne sont jamais créés ni modifiés par l’import.

Les données disponibles et les images sont fournis par Open Food Facts, sous licence ODbL ; l’interface affiche l’attribution. Les informations de la base peuvent être incomplètes et doivent être vérifiées.

## Configuration

`OPEN_FOOD_FACTS_USER_AGENT` est facultative ; configurez de préférence `NutriTrace/1.0 (votre contact de projet)` dans `.env`. Aucune clé API Open Food Facts n’est utilisée.

## Migration et vérifications

```sh
php artisan migrate
php artisan test --filter=OpenFoodFactsTest
```

Les tests utilisent `Http::fake()` et n’appellent pas Open Food Facts. Une recherche réelle nécessite une vérification manuelle avec le serveur externe.
