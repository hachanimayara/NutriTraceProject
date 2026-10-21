# Messagerie NutriTrace

La messagerie est réservée aux comptes approuvés de type `transformateur` et `distributeur`. Une conversation privée relie un seul compte de chaque rôle. Les messages, notifications, reçus de livraison et de lecture sont conservés en base.

## Mise en service

Appliquer la migration :

```bash
php artisan migrate --path=database/migrations/2026_10_21_000003_create_messaging_tables.php
```

Puis lancer l’application comme d’habitude et ouvrir **Messagerie** dans le menu transformateur ou distributeur. Il n’y a pas de variable `.env` supplémentaire, de worker de queue, ni de service WebSocket à démarrer.

## Synchronisation et présence

Le projet n’avait pas Reverb/Echo configuré. La messagerie utilise donc le repli périodique demandé : messages toutes les 3 secondes lorsque la page est ouverte, liste et compteur toutes les 10 secondes, présence toutes les 30 secondes. Une présence expire après 90 secondes sans heartbeat. La fermeture de la page laisse ainsi expirer le statut en ligne sans tâche planifiée.

Un message est marqué livré quand la boîte de réception du destinataire le récupère. Il est marqué lu quand le destinataire charge la conversation. L’expéditeur voit ces reçus à la prochaine synchronisation. Les notifications de nouveaux messages utilisent le système Laravel déjà installé et renvoient vers la conversation. L’envoi est idempotent côté serveur grâce à un identifiant client, afin qu’une nouvelle tentative réseau ne crée pas de doublon.

## Vérification

Les tests de fonctionnalité sont dans `tests/Feature/MessagingTest.php` et se lancent avec :

```bash
php artisan test tests/Feature/MessagingTest.php
```

Ils couvrent les rôles, l’envoi, l’idempotence, les statuts, les accès privés, la recherche, la validation, le compteur non lu et le heartbeat.
