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