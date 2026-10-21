# Planification des récoltes

Le module est réservé aux comptes producteur. Les planifications, événements de calendrier, formulaires et historiques sont limités à l'utilisateur connecté. Un produit doit appartenir au producteur et être rattaché à l'exploitation choisie.

## Installation

Après récupération du code, exécuter les migrations :

```bash
php artisan migrate
```

Le module utilise FullCalendar déjà présent dans `package.json`. Si les assets n'ont pas été compilés après mise à jour :

```bash
npm install
npm run build
```

## Rappels automatiques

Laravel planifie `nutritrace:harvest-reminders` chaque minute dans le fuseau `Africa/Tunis`. Les rappels, notifications du jour prevu et alertes de retard sont enregistres dans la table `notifications`. Aucun worker de queue requis. Les notifications sont synchrones et en base ; aucun worker de queue n'est nécessaire.

En développement local, laisser tourner le scheduler dans un terminal :

```bash
php artisan schedule:work
```

En production Linux, ajouter la tâche cron Laravel (l'utilisateur système doit pouvoir lancer PHP et accéder au projet) :

```cron
* * * * * cd /chemin/vers/NutriTrace && php artisan schedule:run >> /dev/null 2>&1
```

Sur Windows, créer une tâche du Planificateur de tâches répétée chaque minute qui exécute `php artisan schedule:run` avec le répertoire du projet comme dossier de démarrage. Vérifier la disponibilité de la tâche avec `php artisan schedule:list`, et lancer une vérification ponctuelle via `php artisan nutritrace:harvest-reminders`.

Les événements de rappel sont identifiés par planification et type d'événement, de sorte que les exécutions répétées ne produisent pas de notifications en double. Les dates sont enregistrées sans heure et comparées dans le fuseau applicatif.

## Vérifications

Tests du module :

```bash
php artisan test --filter=HarvestPlanTest
```

## Envoi par e-mail

Le rappel email est envoye au premier passage du scheduler le jour calcule par `starts_at - reminder_days`. Le calendrier ne stocke pas heure evenement ; le message le precise. La valeur `0` desactive le rappel email.

Configurer le transport SMTP dans `.env` avec `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME`. Les identifiants ne doivent jamais être copiés dans le dépôt. `.env.example` conserve le transport `log` pour éviter de prétendre envoyer un e-mail dans une installation locale sans SMTP.

Les changements de dates ou du délai réinitialisent l'état d'envoi afin que le prochain rappel suive la nouvelle date. Les échecs SMTP sont journalisés et le rappel peut être retenté. La migration ajoute les champs d'état du rappel; l'exécuter avec `php artisan migrate` après mise à jour du code.
