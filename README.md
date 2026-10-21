# 🌾 NutriTrace — Plateforme de Traçabilité Agroalimentaire & DevOps

[![NutriTrace CI/CD Pipeline](https://github.com/hachanimayara/NutriTraceProject/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/hachanimayara/NutriTraceProject/actions/workflows/ci-cd.yml)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=NutriTraceProject&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=NutriTraceProject)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=NutriTraceProject&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=NutriTraceProject)
[![Duplicated Lines (%)](https://sonarcloud.io/api/project_badges/measure?project=NutriTraceProject&metric=duplicated_lines_density)](https://sonarcloud.io/summary/new_code?id=NutriTraceProject)

**NutriTrace** est une solution web complète dédiée à la transparence, au suivi de la chaîne d'approvisionnement agroalimentaire « de la ferme à l'assiette » et à l'évaluation de l'impact environnemental. Le projet intègre une démarche DevOps rigoureuse : conteneurisation intégrale (Docker), pipeline d'intégration continue (GitHub Actions) et métrologie de la qualité logicielle (SonarQube Cloud).

---

## 📑 Sommaire
1. [Fonctionnalités Métier & Architecture Applicative](#-fonctionnalités-métier)
2. [Stack Technique Globale](#-stack-technique-globale)
3. [Architecture Docker & Environnement Local](#-architecture-docker--environnement-local)
4. [Installation & Démarrage Rapide](#-installation--démarrage-rapide)
5. [Tests Automatisés & Conformité](#-tests-automatisés--conformité)
6. [Pipeline DevOps & CI/CD (GitHub Actions)](#-pipeline-devops--cicd)
7. [Audit de Qualité & Sécurité (SonarQube Cloud)](#-audit-de-qualité--sécurité-sonarqube-cloud)
8. [Sécurité & Bonnes Pratiques](#-sécurité--bonnes-pratiques)
9. [Contributeurs](#-contributeurs)

---

## 🎯 Fonctionnalités Métier

L'application s'articule autour de rôles métiers stricts (RBAC) pour sécuriser l'accès aux données :
- **Producteurs :** Gestion des exploitations agricoles (`farms`), référencement des produits du terroir, suivi des méthodes de culture et rattachement des certifications officielles.
- **Transformateurs :** Traçabilité ascendante et descendante des lots (`lots`), enregistrement des opérations industrielles (`transformations`), bilans énergétiques (consommation kWh, eau en litres).
- **Distributeurs :** Gestion du réseau de distribution, enregistrement des points de vente (`stores`) avec géolocalisation, suivi logistique des expéditions (`distributions`).
- **Administrateurs & Modérateurs :** Validation des indicateurs environnementaux (`indicators`), audit des utilisateurs, contrôle des flux et journalisation d'audit.
- **Fonctionnalités avancées :**
  - **Authentification moderne :** Prise en charge des **Passkeys biométriques (Face ID / WebAuthn)** en complément du mot de passe standard.
  - **Génération QR Code consommateur :** Consultation publique instantanée et vérifiée de la fiche produit et de son historique.
  - **Intégration Open Food Facts :** Importation et enrichissement automatique des fiches nutritionnelles par code-barres.
  - **Analyse documentaire par IA :** Extraction automatique de données depuis les certificats et justificatifs importés.

---

## 🛠️ Stack Technique Globale

| Composant | Technologie | Description |
| :--- | :--- | :--- |
| **Back-End** | Laravel 12 / PHP 8.2-FPM | Framework PHP moderne, architecture MVC, Eloquent ORM |
| **Front-End** | Blade / Tailwind CSS / Vite | Templates réactifs, compilation optimisée via Vite |
| **Base de données** | MySQL 8.0 | Persistance relationnelle avec contraintes d'intégrité et clés étrangères |
| **Serveur Web** | Nginx 1.25 (Alpine) | Reverse proxy et gestionnaire de requêtes FastCGI |
| **Conteneurs** | Docker & Docker Compose | Environnement reproductible multi-services |
| **Qualité / Linter** | Laravel Pint | Respect strict des normes PSR-12 |
| **Tests** | PHPUnit (Laravel Test Suite) | 64 tests unitaires et fonctionnels (100% validés) |
| **Intégration Continue** | GitHub Actions | Automatisation des builds, migrations et tests à chaque push |
| **Audit Statique** | SonarQube Cloud | Surveillance de la dette technique, sécurité et duplications |

---

## 🐳 Architecture Docker & Environnement Local

L'infrastructure locale est orchestrée par **Docker Compose** et se divise en 3 conteneurs isolés :

```text
  [ Navigateur ] ──(HTTP 8000)──> [ nutritrace_web (Nginx) ]
                                          │
                                       FastCGI (port 9000)
                                          │
                                          ▼
                                 [ nutritrace_app (PHP 8.2-FPM) ]
                                          │
                                       SQL (port 3306)
                                          ▼
                                 [ nutritrace_db (MySQL 8.0) ]
nutritrace_app : Exécute l'application Laravel, Composer et les commandes Artisan (extensions PHP : pdo_mysql, intl, gd, bcmath, zip).

nutritrace_web : Sert les fichiers statiques compilés et achemine les requêtes dynamiques vers PHP-FPM.

nutritrace_db : Serveur MySQL 8.0 isolé avec volume persistant (dbdata). Le port est mappé sur 3307 côté machine hôte pour prévenir les conflits.

🚀 Installation & Démarrage Rapide
Prérequis
Docker Desktop en cours d'exécution

Git

1. Récupération du projet
Bash
git clone [https://github.com/hachanimayara/NutriTraceProject.git](https://github.com/hachanimayara/NutriTraceProject.git)
cd NutriTraceProject
2. Configuration d'environnement
Copiez le modèle de variables :

Bash
cp .env.example .env
Assurez-vous que les variables réseau ciblent le conteneur Docker :

Extrait de code
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=nutritrace
DB_USERNAME=nutritrace_user
DB_PASSWORD=nutritrace_password
3. Démarrage des conteneurs
Bash
docker compose up -d --build
4. Initialisation de l'application
Exécutez dans le conteneur applicatif :

Bash
# Installation des dépendances et génération de clé
docker compose exec app composer install
docker compose exec app php artisan key:generate

# Migrations de la base de données
docker compose exec app php artisan migrate --seed

# Compilation des assets Vite
docker compose exec app npm install
docker compose exec app npm run build
L'application est immédiatement accessible à l'adresse : http://localhost:8000

🧪 Tests Automatisés & Conformité
Le projet intègre une couverture de 64 tests fonctionnels et unitaires validant la robustesse du système :

Gestion des rôles, cycles de vie des comptes et sécurité des sessions (AccountManagementTest).

Logique métier de traçabilité, cohérence des lots et restrictions d'accès (TraceabilityTest).

Intégration externe Open Food Facts (OpenFoodFactsTest).

Traitement documentaire et extraction IA (DocumentAnalysisTest).

Bash
# Lancer l'intégralité de la suite de tests
docker compose exec app php artisan test

# Vérifier la conformité du style de code
docker compose exec app ./vendor/bin/pint --test

# Corriger automatiquement les écarts de style
docker compose exec app ./vendor/bin/pint
⚙️ Pipeline DevOps & CI/CD
Le workflow GitHub Actions (.github/workflows/ci-cd.yml) s'exécute automatiquement sur un runner Ubuntu à chaque push ou pull-request sur la branche main :

Service MySQL éphémère : Déploiement d'un conteneur MySQL 8.0 dédié aux tests avec contrôle de santé (mysqladmin ping).

Runtimes & Dépendances : Configuration de PHP 8.2 et Node.js 20, installation déterministe via composer install et npm ci.

Audit de Style : Contrôle PSR-12 strict avec vendor/bin/pint --test.

Build Front-End : Exécution de npm run build produisant le manifest.json indispensable aux rendus Blade.

Validation Métier : Exécution des migrations isolées (php artisan migrate --env=testing --force) et validation des 64 tests PHPUnit.

Contrôle Qualité Distant : Déclenchement du SonarScanner et envoi des métriques à SonarQube Cloud.

📊 Audit de Qualité & Sécurité (SonarQube Cloud)
Les analyses statiques réalisées sur la plateforme SonarQube Cloud attestent de la qualité du code produit :

Statut Global : Quality Gate Passed

Volume de code audité : ~13 000 lignes de code (13k LoC)

Taux de duplication : 1.4% (excellente factorisation et modularité)

Maintenabilité : Note A (dette technique minimale)

🔒 Sécurité & Bonnes Pratiques
Zéro secret exposé : Aucun mot de passe, token ou clé d'API n'est présent dans le dépôt Git. Les secrets critiques (SONAR_TOKEN, SONAR_HOST_URL) sont administrés via les secrets chiffrés GitHub Actions.

Isolation des tests : Les tests de la CI tournent sur une instance de base de données dédiée et volatile, empêchant toute corruption de données.

Principe du moindre privilège : Dans l'environnement Docker, les processus applicatifs s'exécutent avec l'utilisateur non privilégié www-data.

👥 Contributeurs
Mayara Hachani (@hachanimayara)

Souhail Daadoucha (@souhaildaadoucha)

Hanin Ammani (@ammanihanin)

Adam Zitoun (@zitounadam)
