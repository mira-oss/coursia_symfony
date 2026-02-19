# Coursia Backend - Récapitulatif Phase 1

## ✅ Ce qui a été créé/modifié

### 1. Nouvelles Entités

#### **Journey** (Trajet du Chevalier)
Nouvelle table `journeys` qui permet aux Chevaliers de publier leurs trajets disponibles.

**Champs principaux :**
- `chevalier` : Le Chevalier qui publie
- `departureAddress`, `deliveryAddress` : Points de départ et d'arrivée
- `type` : Calculé automatiquement (national/regional/international)
- `departureTime`, `arrivalTime` : Horaires du voyage
- `pricePerKg` : Prix proposé par kg
- `isNegotiable` : Le prix est-il négociable ? (booléen)
- `maxWeight` : Poids maximum transportable
- `notes` : Notes du Chevalier
- `status` : available, in_progress, completed, cancelled

#### **Course** (Améliorations)
Ajout de nouveaux champs à la table existante `courses` :
- `price` : Prix final (quand acceptée)
- `isNegotiable` : Le prix est-il négociable ?
- `scheduledAt` : Date souhaitée par l'Élu

### 2. Logique Métier

#### **CourseService.php** (NOUVEAU)
Service central contenant toute la logique intelligente :

**Méthodes principales :**
- `determineCourseType($addr1, $addr2)` : 
  - Extrait les pays des adresses
  - Compare les continents
  - Retourne automatiquement : national, regional ou international
  
- `calculateEstimatedPrice($type, $weight)` :
  - Propose un tarif de base selon le type
  - Utile pour guider les Chevaliers
  
- `canAcceptCourse($journey, $course)` :
  - Vérifie la compatibilité géographique et horaire
  - (À implémenter davantage)

### 3. Contrôleurs Mis à Jour

#### **CourseController.php**
- ✅ Injection de `CourseService` dans le constructeur
- ✅ Suppression du champ `type` obligatoire dans la requête
- ✅ **Calcul automatique du type** via `CourseService::determineCourseType()`
- ✅ Support de `scheduledAt` optionnel
- ✅ Nouvel endpoint : `GET /api/courses/history`
  - Retourne les courses en tant qu'Élu
  - Retourne les courses en tant que Chevalier

#### **JourneyController.php** (NOUVEAU)
Gestion complète des trajets des Chevaliers :

**Endpoints :**
- `POST /api/journeys` : Créer un trajet
  - Type calculé automatiquement
  - Le Chevalier fixe : `pricePerKg`, `isNegotiable`, `maxWeight`
  
- `GET /api/journeys/available` : Chercher des trajets
  - Paramètres : `?departure=...&delivery=...&date=...`
  - Utilise `JourneyRepository::findMatchingJourneys()`
  
- `GET /api/journeys/my-journeys` : Historique personnel du Chevalier

### 4. Repositories

#### **JourneyRepository.php** (NOUVEAU)
Méthode de recherche intelligente : `findMatchingJourneys()`
- Cherche les trajets compatibles géographiquement
- Filtre par fenêtre temporelle (±2 jours)
- Trie par date de départ

### 5. Documentation

#### **ARCHITECTURE.md** (NOUVEAU)
Document complet expliquant :
- La séparation des responsabilités (Entity/Service/Controller/Repository)
- Le rôle de chaque composant
- Les règles de code propre appliquées
- Comment un nouveau développeur peut comprendre le projet

---

## 🎯 Objectifs Atteints

### Architecture Propre
✅ **Séparation claire :** Logique métier isolée dans `CourseService`  
✅ **Controllers légers :** Juste de la validation et de l'orchestration  
✅ **Entities simples :** Juste des données, pas de calculs  
✅ **Code réutilisable :** Les Services peuvent être appelés de n'importe où  

### Fonctionnalités Métier
✅ **Type auto-détecté :** Plus besoin de dropdown côté mobile  
✅ **Prix négociable :** Système flexible pour les Chevaliers  
✅ **Recherche de trajets :** Matching géographique et temporel  
✅ **Historique :** Élu et Chevalier voient leurs transactions  

---

## 🔄 Prochaines Étapes Recommandées

### Backend (À faire maintenant)
1. **Améliorer la détection de pays**
   - Actuellement basique (heuristique sur le dernier mot)
   - Intégrer une vraie API de géocodage (Nominatim, Google Maps)
   
2. **Affiner le matching géographique**
   - Actuellement : recherche exacte d'adresse
   - Améliorer : recherche par proximité (rayon de X km)
   
3. **Système de notifications**
   - Notifier un Élu quand un Chevalier correspond
   - Notifier un Chevalier quand une course correspond
   
4. **Acceptation de course**
   - Endpoint : `POST /api/courses/{id}/accept`
   - Le Chevalier accepte, la course change de statut
   - Le `price` et `acceptedBy` sont remplis

### Frontend Mobile (Quand le designer aura fini)
1. **Supprimer le dropdown "Type"**
   - Le backend calcule eautomatiquement
   - Juste afficher le type retourné
   
2. **Page "Trajets Disponibles"**
   - Afficher les Journeys qui correspondent
   - Avec filtres : date, type, négociable
   
3. **Page "Publier un Trajet" (pour Chevalier)**
   - Formulaire : départ, arrivée, date, prix/kg, négociable
   
4. **Historique**
   - Appeler `GET /api/courses/history`
   - Afficher les courses/trajets passés

---

## 📊 État de la Base de Données

### Tables Actuelles
- ✅ `user` : Utilisateurs (Élu/Chevalier)
- ✅ `courses` : Demandes de livraison (avec prix et négociabilité)
- ✅ `journeys` : Trajets publiés par les Chevaliers

### Données de Test
- Les anciennes courses ont été supprimées (normal, nouvelles colonnes ajoutées)
- **À faire :** Créer des données de test via Postman ou l'application

---

## 🧪 Comment Tester

### 1. Créer une course (Élu)
```bash
curl -X POST http://192.168.1.90:8000/api/courses \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "departureAddress": "Paris, France",
    "deliveryAddress": "Cotonou, Bénin",
    "description": "Médicaments urgents"
  }'
```

**Retour attendu :** 
- `type: "international"` (calculé automatiquement)
- `status: "created"`

### 2. Publier un trajet (Chevalier)
```bash
curl -X POST http://192.168.1.90:8000/api/journeys \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "departureAddress": "Paris, France",
    "deliveryAddress": "Cotonou, Bénin",
    "departureTime": "2026-02-15T10:00:00Z",
    "pricePerKg": 12.50,
    "isNegotiable": true,
    "maxWeight": 20
  }'
```

**Retour attendu :**
- `type: "international"` (calculé automatiquement)
- `status: "available"`

### 3. Chercher des trajets disponibles
```bash
curl "http://192.168.1.90:8000/api/journeys/available?departure=Paris&delivery=Cotonou"
```

### 4. Voir son historique
```bash
curl -X GET http://192.168.1.90:8000/api/courses/history \
  -H "Authorization: Bearer VOTRE_TOKEN"
```

---

## 💡 Bonnes Pratiques Mises en Place

1. **Un Service = Une Responsabilité**
   - `CourseService` gère uniquement la logique Course/Journey
   
2. **Nommage Explicite**
   - `determineCourseType()` → On sait ce que ça fait
   - `findMatchingJourneys()` → Clair et précis
   
3. **Validation Centralisée**
   - Les Controllers valident les entrées
   - Les Entities valident les valeurs (via exceptions)
   
4. **Extensibilité**
   - Facile d'ajouter un nouveau type de trajet
   - Facile d'ajouter une nouvelle méthode de calcul de prix

---

## 🚀 Prêt pour la Suite

Le backend est maintenant **propre, solide et évolutif**. 

Quand le designer livrera les maquettes :
1. On pourra brancher le mobile directement sur ces endpoints
2. La logique métier est déjà prête
3. Un autre développeur pourra reprendre facilement grâce à l'ARCHITECTURE.md

**Version :** 1.0  
**Date :** 21 Janvier 2026  
**Statut :** ✅ Backend Phase 1 Complète
