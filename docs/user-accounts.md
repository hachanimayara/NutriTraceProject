# Comptes, rôles et sécurité NutriTrace

## Parcours disponibles

- Inscription publique : `/register` (producteur, transformateur, distributeur, consommateur). Le compte démarre avec `status=pending`.
- Connexion et récupération : `/login`, `/forgot-password`.
- Espaces : `/admin/dashboard`, `/producteur/dashboard`, `/transformateur/dashboard`, `/distributeur/dashboard`, `/consommateur/dashboard`.
- Administration des comptes : `/admin/users` (administrateur uniquement).
- Profil et passkeys : `/profile`; notifications : `/notifications`.
- Première configuration d’un administrateur : `php artisan nutritrace:admin adresse@example.tn`. La commande demande le mot de passe sans l’afficher. Aucun rôle administrateur n’est proposé à l’inscription.

## Base de données

Le `.env` de développement actuel pointe vers SQLite (`DB_CONNECTION=sqlite`). Les migrations de comptes sont additives; `php artisan migrate` ne supprime pas les utilisateurs. Pour MySQL, créez la base et le compte MySQL puis configurez les variables suivantes dans votre `.env` local :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nutritrace
DB_USERNAME=...
DB_PASSWORD=...
```

Ne copiez pas de secrets dans Git. Après configuration, vérifiez la connexion avec `php artisan migrate:status`, puis exécutez `php artisan migrate`.

## CAPTCHA et e-mail

L’inscription, la connexion par mot de passe, la récupération du mot de passe et la préparation Google exigent un jeton Google reCAPTCHA v2 vérifié côté serveur. Configurez GOOGLE_RECAPTCHA_SITE_KEY, GOOGLE_RECAPTCHA_SECRET_KEY et GOOGLE_RECAPTCHA_ENABLED=true dans votre .env local. Les tests HTTP simulent Google; aucun contournement n’est activé.

Configurez `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` dans `.env`. Laravel local est configuré par défaut pour journaliser les messages; ceci n’est pas un envoi SMTP. Les demandes de compte créent aussi une notification persistée pour chaque administrateur existant.

## Google OAuth

Créez un client OAuth Google de type application web, ajoutez l’URI de redirection exacte `https://votre-domaine/auth/google/callback` (ou `http://localhost:8000/auth/google/callback` en local), puis renseignez :

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback
```

Une inscription Google demande aussi un rôle public, un téléphone tunisien et l’acceptation des conditions. Elle ne lie jamais automatiquement une adresse déjà présente; les comptes nouveaux restent en attente. Le retour OAuth utilise le contrôle `state` de Socialite.

## Passkeys / Face ID / Windows Hello

Passkeys WebAuthn est intégré via le paquet Laravel maintenu. Les appareils conservent les facteurs biométriques; NutriTrace stocke la clé publique et les métadonnées WebAuthn uniquement. Les opérations nécessitent un navigateur compatible et `localhost` ou HTTPS. Pour production, utilisez l’URL HTTPS réelle et configurez le RP ID/origines dans `config/passkeys.php` (basés par défaut sur `APP_URL`). Les passkeys peuvent être créées, listées et révoquées dans le profil; le navigateur propose le mécanisme local qu’il prend en charge.

## Commandes Windows

```powershell
php artisan migrate
php artisan nutritrace:admin admin@votre-domaine.tn
npm.cmd run build
php artisan serve
```

Puis ouvrez `http://127.0.0.1:8000/register` ou `http://127.0.0.1:8000/login`. Il faut configurer Google reCAPTCHA v2 pour réussir une inscription hors tests. Google nécessite les identifiants OAuth; les e-mails nécessitent SMTP pour réellement sortir du serveur.

## Limites présentes

Les espaces par rôle et l’administration sont protégés et persistants, mais leurs fonctions métier propres (produits, transformations, livraisons, lots) ne sont pas ajoutées par ce module. L’administration n’a pas d’action de réactivation dédiée pour les comptes suspendus. MySQL, SMTP, Google OAuth, SMTP et Google reCAPTCHA dépend des clés externes et ne peuvent être validés sans accès aux services; leur fonctionnement externe ne peut donc pas être validé ici.
