# Architecture Backend Coursia

## Vue d'ensemble

Ce document décrit l'architecture propre et maintenable du backend Coursia, suivant les principes de **Clean Architecture**.

## Structure du Projet

```
src/
├── Entity/          # Modèles de données (structure pure, pas de logique)
│   ├── User.php
│   ├── Course.php   # Demande de livraison créée par un Élu
│   └── Journey.php  # Trajet publié par un Chevalier
│
├── Repository/      # Requêtes personnalisées en base de données
│   ├── UserRepository.php
│   ├── CourseRepository.php
│   └── JourneyRepository.php
│
├── Service/         # LOGIQUE MÉTIER (le cerveau de l'application)
│   └── CourseService.php
│
└── Controller/      # Aiguilleurs (reçoivent les requêtes, appellent les services, renvoient les réponses)
    ├── AuthController.php
    ├── CourseController.php
    └── JourneyController.php
```

## Séparation des Responsabilités

### 1. ENTITIES (Modèles)
**Rôle unique :** Définir la structure des données et les relations.

✅ **Ce qu'on y met :**
- Propriétés (colonnes de la base de données)
- Relations (ManyToOne, OneToMany, etc.)
- Getters/Setters simples
- Validations basiques (ex: `in_array` pour un statut)

❌ **Ce qu'on N'y met PAS :**
- Calculs complexes
- Appels API
- Logique métier

**Exemple :** `Course.php` définit qu'une course a un `status`, un `type`, des adresses, mais ne calcule PAS le type automatiquement.

---

### 2. SERVICES (Logique Métier)
**Rôle unique :** Contenir toute l'intelligence de l'application.

✅ **Ce qu'on y met :**
- `determineCourseType()` : Calcule si une course est National/Regional/International
- `calculateEstimatedPrice()` : Propose un tarif de base
- `canAcceptCourse()` : Vérifie si un Chevalier peut accepter une course
- Toute fonction réutilisable qui implémente une **règle métier**

❌ **Ce qu'on N'y met PAS :**
- Gestion des requêtes HTTP (c'est le rôle du Controller)
- Manipulation directe de l'EntityManager (sauf si nécessaire)

**Exemple :** `CourseService::determineCourseType($addr1, $addr2)` extrait les pays, compare les continents, et renvoie le type.

---

### 3. CONTROLLERS (Aiguilleurs)
**Rôle unique :** Recevoir les requêtes HTTP, appeler les services, renvoyer les réponses JSON.

✅ **Ce qu'on y met :**
- Validation des données entrantes (ex: champs requis)
- Appel des services pour la logique
- Manipulation de l'EntityManager (persist, flush)
- Formatage de la réponse JSON

❌ **Ce qu'on N'y met PAS :**
- Calculs complexes (ils vont dans les Services)
- Logique métier répétée (un Service doit être réutilisable)

**Exemple :** `CourseController::create()` valide les adresses, appelle `CourseService::determineCourseType()`, crée l'objet, le sauvegarde, et renvoie le JSON.

---

### 4. REPOSITORIES (Requêtes Personnalisées)
**Rôle unique :** Créer des requêtes SQL/Doctrine complexes.

✅ **Ce qu'on y met :**
- Requêtes avec jointures
- Filtres personnalisés (ex: trouver les trajets compatibles avec une course)

❌ **Ce qu'on N'y met PAS :**
- Logique métier (elle va dans les Services)

**Exemple :** `JourneyRepository::findMatchingJourneys()` cherche les trajets qui correspondent géographiquement et temporellement à une course.

---

## Les Entités Principales

### **User**
Représente un utilisateur (Élu ou Chevalier, ou les deux).

**Champs clés :**
- `role` : "elu" | "chevalier" | "admin"
- `email`, `password` : Authentification
- `firstName`, `lastName`, `phone`, `nationality` : Profil

### **Course**
Représente une demande de livraison créée par un **Élu**.

**Champs clés :**
- `departureAddress`, `deliveryAddress` : Trajets
- `type` : "national" | "regional" | "international" (calculé automatiquement)
- `status` : "created" | "accepted" | "started" | "finished"
- `price` : Prix final (fixé par le Chevalier quand il accepte)
- `isNegotiable` : Booléen (le prix est-il négociable ?)
- `deliveryDateStart` : Date de début de livraison souhaitée par l'Élu
- `deliveryDateEnd` : Date de fin de livraison souhaitée par l'Élu
- `createdBy` : L'Élu qui a créé la course
- `acceptedBy` : Le Chevalier qui a accepté (null si pas encore acceptée)

### **Journey**
Représente un trajet publié par un **Chevalier** (annonce de disponibilité).

**Champs clés :**
- `chevalier` : Le Chevalier qui publie
- `departureAddress`, `deliveryAddress` : Trajets
- `type` : "national" | "regional" | "international" (calculé automatiquement)
- `departureTime` : Date/heure de départ
- `arrivalTime` : Date/heure d'arrivée prévue
- `pricePerKg` : Prix proposé par kg
- `isNegotiable` : Le prix est-il négociable ?
- `maxWeight` : Poids maximum transportable
- `notes` : Notes libres du Chevalier
- `status` : "available" | "in_progress" | "completed" | "cancelled"

---

## Endpoints API Principaux

### Authentification
- `POST /api/auth/register` : Inscription
- `POST /api/auth/login` : Connexion (renvoie un JWT)
- `GET /api/auth/me` : Profil de l'utilisateur connecté
- `PUT /api/auth/update` : Mise à jour du profil

### Courses (Élu)
- `POST /api/courses` : Créer une demande de livraison
  - Le **type est calculé automatiquement** par le backend
- `GET /api/courses/history` : Historique de ses courses

### Journeys (Chevalier)
- `POST /api/journeys` : Publier un trajet
  - Le **type est calculé automatiquement**
  - Le Chevalier fixe `pricePerKg` et `isNegotiable`
- `GET /api/journeys/available` : Lister les trajets disponibles
  - Paramètres : `?departure=...&delivery=...&date=...`
- `GET /api/journeys/my-journeys` : Historique de ses trajets

---

## Logique Métier : Calcul Automatique du Type

Le `CourseService::determineCourseType()` suit cette règle :

1. **Extraire le pays** de chaque adresse (via heuristique ou API future)
2. **Comparer les pays :**
   - Si identiques → **National**
3. **Comparer les continents :**
   - Si identiques → **Regional**
   - Si différents → **International**

**Avantage :** L'utilisateur n'a pas à choisir, l'application est intelligente.

---

## Principes de Code Propre Appliqués

### 1. Noms Explicites
- ✅ `determineCourseType()` : On comprend immédiatement
- ❌ `calcType()` : Trop court, ambigu

### 2. Fonctions Courtes
- Chaque fonction fait **une seule chose**
- Si une fonction dépasse 30 lignes, la découper

### 3. Pas de Duplication
- Si le même code apparaît 2 fois → Créer une fonction privée ou un Service

### 4. Commentaires Utiles
- Ne pas commenter "Ce que fait le code" (le code doit être lisible)
- Commenter "Pourquoi on le fait" ou les choix techniques

---

## Pour un Nouveau Développeur

Si tu rejoins le projet :

1. **Lire les Entities** → Comprendre les données
2. **Lire les Services** → Comprendre les règles métier
3. **Lire les Controllers** → Comprendre les endpoints API
4. **Ne jamais mettre de logique dans les Controllers** → Toujours créer/utiliser un Service

---

## Évolutivité Future

### Ajouts Faciles
- **Nouveau statut de course** → Ajouter dans `setStatus()` de `Course.php`
- **Nouveau type de trajet** → Ajouter dans `CourseService::determineCourseType()`
- **Nouveau moyen de paiement** → Créer `PaymentService.php`
- **Système de notation** → Créer `Rating.php` Entity + `RatingService.php`

### Migration de la Logique Mobile vers le Backend
Actuellement, le mobile fait encore des calculs (type de course).
**À faire :** Supprimer le dropdown, laisser le backend renvoyer le type automatiquement.

---

## Base de Données

Pour mettre à jour la base après modification des Entities :

```bash
php bin/console doctrine:schema:update --force
```

Pour créer une migration propre (recommandé en production) :

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

---

## Résumé : La Règle d'Or

> **"Si demain on change le design de l'appli mobile pour un site web, est-ce que mes Services restent utilisables ?"**
> 
> Si la réponse est **OUI**, l'architecture est bonne.

---

**Auteur :** Équipe Coursia  
**Version :** 1.0  
**Date :** 21 Janvier 2026
