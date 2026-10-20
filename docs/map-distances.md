# Carte et distances distributeur

La page `workspace/map-distances` est réservée aux comptes distributeur approuvés. Elle lit uniquement les points de vente et distributions appartenant au compte connecté. Les distributions liées à un point de vente d'un autre compte ne sont pas exposées.

## Coordonnées

Appliquer la migration avec `php artisan migrate`. Les champs ajoutés sont facultatifs afin de conserver les enregistrements existants : latitude/longitude sur `stores`, et lieu + latitude/longitude de départ sur `distributions`. Renseigner les coordonnées depuis la fiche du point de vente ou le formulaire de distribution. Aucune coordonnée n'est inventée.

La position du navigateur est demandée uniquement après un clic, utilisée en mémoire dans la page et jamais envoyée au serveur ni enregistrée dans la base. Le mode distribution utilise les coordonnées commerciales de départ enregistrées sur la distribution et la position du point de vente associé.

## Fournisseurs configurables

Les valeurs par défaut sont configurées dans `config/maps.php` :

- `MAP_TILE_URL` : modèle d'URL des tuiles (z/x/y).
- `MAP_TILE_ATTRIBUTION` : attribution visible de la carte.
- `OSRM_ROUTING_URL` : base de l'API OSRM, terminée par `/route/v1/driving`.

Des valeurs correspondantes sont documentées dans `.env.example`. Aucune clé Google n'est utilisée. La page contacte le fournisseur de tuiles pour dessiner la carte, puis OSRM uniquement lors d'un calcul explicite. Pour un déploiement ou un trafic soutenu, utiliser un fournisseur OSM/OSRM compatible avec ses conditions d'utilisation et configurer ses URL ici ; le serveur public OSRM convient aux essais, sans garantie de disponibilité.

## Vérification manuelle

1. Ajouter latitude et longitude à au moins deux points de vente.
2. Ouvrir **Carte et distances** en tant que distributeur : vérifier les marqueurs et popups.
3. Choisir « Ma position → point de vente » et autoriser la géolocalisation, puis calculer.
4. Choisir deux points de vente et calculer leur itinéraire.
5. Créer/modifier une distribution avec lieu et coordonnées de départ, puis choisir le mode distribution.
6. Tester un refus de géolocalisation, des coordonnées absentes, et une indisponibilité réseau. La page affiche une erreur et ne simule aucune distance.
