# QR codes produits

La rubrique **QR Codes et traçabilité** est disponible uniquement dans l’espace producteur. Elle liste les produits du compte connecté et prend en charge la recherche par nom/référence, les filtres par statut/catégorie, ainsi que la pagination existante.

Un QR code encode uniquement l’URL publique `/p/{UUID}`. La migration `2026_10_20_000002_add_public_ids_to_products_table` ajoute un identifiant UUID public stable, et attribue un UUID aux produits déjà présents. Le modèle attribue le même type d’identifiant aux nouveaux produits. Les QR sont dessinés localement dans le navigateur avec la bibliothèque open source `qrcode` ; aucune API de génération n’est utilisée. L’image PNG peut être téléchargée ou imprimée.

La fiche QR est en lecture seule et n’est accessible que si le produit est publié et son producteur approuvé. Les enregistrements supprimés ou non publiables répondent comme introuvables. Elle ne charge pas les coordonnées privées des points de vente, les contacts, notes internes, documents privés ou source_notes des indicateurs. Les lots actifs, transformations associées et distributions non annulées sont présentés selon leurs relations réelles. Les certifications affichées sont valides et non expirées ; l’interface précise que les autres données sont des déclarations des acteurs.

## Configuration et commandes

La migration a été exécutée sur la base de développement de cette installation. Pour un autre environnement :

```sh
php artisan migrate
npm install
npm run build
```

Définir `NUTRITRACE_PUBLIC_URL` avec l’URL de base du site public. En production, elle doit être HTTPS et ne pas utiliser localhost, un domaine de développement, une IP privée ou réservée. Si elle n’est pas sûre, la génération est désactivée au lieu d’encoder un domaine invalide.

Vérifier avec :

```sh
php artisan test --filter='producer_qr_page_lists_only_owned_products_and_filters_them|public_product_qr_url_is_stable_and_shows_only_publishable_public_data'
```

Les indicateurs environnementaux ont un statut `pending`, `valid` ou `rejected`. Les acteurs peuvent enregistrer une mesure, mais seul un administrateur peut la valider. La fiche publique n'affiche que les mesures `valid`. La migration `2026_10_20_000003_add_validation_status_to_environmental_indicators` met les mesures déjà présentes en attente de validation, sans les publier automatiquement.
