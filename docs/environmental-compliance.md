# Contrôle de conformité environnementale

## Mise en service

Appliquer la migration additive :

```bash
php artisan migrate --path=database/migrations/2026_10_21_000004_expand_compliance_workflow.php
```

L’espace est accessible aux administrateurs dans le menu **Conformité environnementale**. Aucun service externe ou secret n’est requis. Les contrôles sont déterministes et expliquent les valeurs qui les déclenchent.

## Règles métier configurables

Les informations sectorielles absentes du schéma ne sont pas présumées. Définir les exigences réellement applicables dans `config/compliance.php` avant d’activer ces contrôles :

- `renewal_warning_days` : délai d’alerte avant expiration, réglable par `COMPLIANCE_RENEWAL_WARNING_DAYS`.
- `required_certification_schemes` : référentiels dont une certification doit avoir un justificatif; `'*'` signifie tous les référentiels.
- `required_certifications_by_category` : catégories de produits publiés et certifications attendues pour chacune.
- `required_indicator_codes_by_stage` : indicateurs requis par étape, à contrôler pour les produits publiés et leurs lots.
- `indicator_rules` : unités autorisées et limites `min`/`max` par `metric_code`.

Les listes de référentiels, catégories, codes de mesure, unités et limites doivent correspondre aux règles réelles de l’organisation. Elles sont vides par défaut. Sans configuration, le système n’invente donc pas de certification obligatoire ou d’indicateur manquant. Les valeurs négatives, dates contradictoires, méthodes absentes et incohérences de quantités sont vérifiées selon les règles explicites du code.

## Workflow administrateur

Le tableau présente les données réelles : certifications validées selon leur statut et leurs dates, dates échues/proches, anomalies ouvertes, dossiers incomplets selon les pièces configurées et actions en retard. Les listes de certifications et d’anomalies sont paginées et filtrables.

Chaque alerte conserve la règle, l’explication, les valeurs de preuve, la priorité et les lots réellement reliés. L’administrateur peut ajouter une décision motivée, un responsable et une échéance, créer une action corrective, y déposer un justificatif privé, suivre son historique puis clôturer le dossier. La clôture est bloquée tant qu’une action corrective n’est pas terminée. Le classement sans suite exige une justification.

Les liens de traçabilité utilisent uniquement les relations existantes : exploitation/produit, produit/lot, transformation-lot et distribution-lot. Les indicateurs sont affichés avec leur étape et unité; aucune comparaison ou agrégation entre unités différentes n’est effectuée.

## Vérification

```bash
php artisan test tests/Feature/ComplianceWorkflowTest.php
php artisan test --filter=deterministic_analysis_records_explainable_alerts_without_duplicates tests/Feature/TraceabilityTest.php
```

Les contrôles peuvent être lancés depuis l’écran ou avec `php artisan nutritrace:analyze`. Le scheduler du projet exécute déjà cette commande chaque jour à **02:00**. En développement local, démarrer le scheduler avec `php artisan schedule:work`; en production, le processus habituel `schedule:run` doit être déclenché chaque minute par le planificateur système.
