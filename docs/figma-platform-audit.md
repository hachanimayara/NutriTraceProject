# Audit du design Figma NutriTrace

## Source de reference

Le dossier `NutriTrace platform interfaces` est l'export React/Vite a utiliser comme reference visuelle. Il contient une application React 19, Vite 8 et Tailwind CSS 4, avec un seul composant principal `src/App.tsx` et sa feuille `src/index.css`. Le projet Laravel reste l'application a executer et a connecter a la base de donnees.

## Ecrans et acteurs identifies

| Ecran du prototype | Acteur cible | Etat dans le prototype |
| --- | --- | --- |
| Accueil, catalogue, comparaison, tracabilite | Public / consommateur | Navigation et donnees de demonstration locales |
| Connexion | Tous | Maquette seulement; le systeme Laravel existant reste la source d'authentification et est hors perimetre de modification |
| Tableau de bord, utilisateurs | Administrateur | Cartes et lignes statiques |
| Exploitations, produits | Producteur | Listes et formulaires statiques |
| Transformations, lots | Transformateur | Listes et formulaires statiques |
| Distributions, points de vente | Distributeur | Listes et formulaires statiques |
| Certifications, impact environnemental | Administration / acteurs autorises | Donnees statiques |
| NutriTrace AI | Administration / acteurs autorises | Reponses marquees comme demonstration, sans fournisseur IA |

Le prototype regroupe toutes les pages derriere un etat React local. Les formulaires ferment leur modal sans persistance, les lignes proviennent de tableaux en dur, et les actions ne communiquent pas avec Laravel. Le prototype ne fournit pas de pages distinctes ni de permissions serveur par role.

## Etat du projet Laravel

- Laravel 12, avec authentification, approbation et middleware de role deja en place.
- Les routes existantes couvrent l'authentification, le profil, les notifications, les utilisateurs administrateurs et des tableaux de bord de role.
- Les modeles metier existants sont `User` et `AdminAuditLog`; les migrations existantes ne definissent pas encore les entites de tracabilite (exploitations, produits, lots, transformations, distributions, points de vente, certifications et indicateurs).
- Les interfaces Foody/TailAdmin existantes doivent etre conservees et adaptees plutot que remplacees par le prototype React autonome.

## Plan d'implementation

1. Ajouter le schema relationnel metier et les modeles, sans modifier les migrations utilisateurs ni le code d'authentification.
2. Ajouter les routes, policies/controles d'acces et operations CRUD selon les roles deja existants; limiter chaque acteur a ses donnees.
3. Integrer les ecrans du prototype dans des vues Blade coherentes avec les layouts existants, avec listes, filtres, pagination, formulaires et details relies a la base.
4. Relier le catalogue public et la page de tracabilite aux donnees publiees et aux etapes liees aux lots.
5. Alimenter les tableaux de bord par des statistiques SQL. Ajouter des controles deterministes explicables pour les anomalies; ne pas presenter de donnees fictives comme des resultats IA. Preparer un connecteur IA optionnel uniquement si les cles fournisseur sont configurees.
6. Executer les migrations dans un environnement de test, les tests fonctionnels et la validation des permissions, puis documenter installation et limites.

## Limites a respecter

- Le design Figma fournit des exemples visuels, pas les regles metier completes. Les champs et relations seront gardes minimaux, coherents et extensibles.
- Aucune authentification, inscription, OAuth, approbation ou gestion des roles existante ne sera modifiee.
- Aucun resultat IA externe ne sera simule ou annonce comme reel sans fournisseur configure.
