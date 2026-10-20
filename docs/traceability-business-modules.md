# NutriTrace business modules

## Included modules

The Laravel application now has persistent modules for farms, products, lots,
transformations, stores, distributions, certifications, and environmental
indicators. Their schema is additive and lives in
`2026_10_19_000001_create_traceability_domain_tables.php`; it does not alter
the existing users/authentication schema.

Authenticated, approved users access these modules under `/workspace/{module}`.
The existing role values determine which modules are visible and writable.
Producer records are scoped to their owner. Public catalogue and traceability
pages expose only published products and active lots. Certification documents
are stored on Laravel's private local disk and downloaded through an
authenticated, scoped route.

Producer farm and product pages use the Figma list layout, database-backed
statistics, search/status filters, CSV export, and create/edit dialogs. Product
to farm assignments update the existing `products.farm_id` relation. A farm's
date and evidence file are persisted on the `farms` row; the evidence content
is base64 encoded in a database `LONGTEXT` column and served only through the
authenticated owner/admin download route. It is not written to Laravel's local
filesystem or browser storage.

## Analysis and limitations

`nutritrace:analyze` runs deterministic data-quality checks: expired lots and
certifications, overdue deliveries, missing measurement methods, and missing
or quantitatively inconsistent transformation links. Each alert records the
rule, explanation, and evidence. These checks are not machine learning, do not
infer fraud, and do not replace human verification. No external AI provider is
configured, so the interface must not describe these alerts as AI results.

The command is scheduled daily at 02:00. In local development, run it manually
or start Laravel's scheduler with `php artisan schedule:work`.

## Setup and validation

From the Laravel project directory, run:

```bat
php artisan migrate
php artisan nutritrace:analyze
php artisan schedule:list
php artisan test --filter=TraceabilityTest
```

Public routes are `/`, `/catalogue`, and `/trace/{public_id}`. The dashboard
links role-appropriate users to the workspace modules; administrators also
have `/admin/alerts` for analysis and review.
