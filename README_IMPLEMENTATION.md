# 🚀 Coursia Backend - Phase 1 COMPLÈTE

> Backend Symfony pour la plateforme de mise en relation Coursia (Élus ↔ Chevaliers)

## 📌 Statut du Projet

**Version :** 1.0  
**Phase :** Phase 1 COMPLÈTE ✅  
**Date :** 21 Janvier 2026  
**État de la Base de Données :** ✅ Synchronisée  
**Tests :** ✅ Tous les endpoints fonctionnels

---

## 📚 Documentation Principale

Consultez ces documents dans l'ordre :

1. **[RECAP_PHASE1.md](RECAP_PHASE1.md)** - Objectifs de la Phase 1
2. **[IMPLEMENTATION_COMPLETE.md](IMPLEMENTATION_COMPLETE.md)** - Checklist détaillée de l'implémentation
3. **[ARCHITECTURE.md](ARCHITECTURE.md)** - Architecture du projet et bonnes pratiques
4. **[TEST_ENDPOINTS.md](TEST_ENDPOINTS.md)** - Guide de test des API

---

## ⚡ Démarrage Rapide

### Prérequis
- PHP 8.2+
- Composer
- PostgreSQL
- Symfony CLI (optionnel)

### Installation

```bash
# 1. Installer les dépendances
composer install

# 2. Configurer la base de données
# Éditer .env et configurer DATABASE_URL

# 3. Créer la base de données
php bin/console doctrine:database:create

# 4. Appliquer les migrations
php bin/console doctrine:migrations:migrate

# 5. Démarrer le serveur
symfony serve -d
# ou
php -S 0.0.0.0:8000 -t public/
```

### Test Rapide

```bash
# Inscription
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123",
    "firstName": "Test",
    "lastName": "User",
    "phone": "+33612345678",
    "role": "elu",
    "nationality": "FR"
  }'

# Connexion
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'
```

Plus d'exemples dans [TEST_ENDPOINTS.md](TEST_ENDPOINTS.md).

---

## 🎯 Fonctionnalités Implémentées

### ✅ Authentification
- Inscription (Élu / Chevalier)
- Connexion (JWT)
- Profil utilisateur

### ✅ Courses (Demandes de Livraison - Élu)
- Créer une course
- **Type calculé automatiquement** (national/regional/international)
- Historique des courses

### ✅ Journeys (Trajets - Chevalier)
- Publier un trajet
- **Type calculé automatiquement**
- Recherche de trajets disponibles (filtres géographiques et temporels)
- Historique des trajets

### ✅ Logique Métier Intelligente
- Calcul automatique du type de course/trajet
- Matching géographique et temporel
- Système de prix négociable
- Service de calcul de prix estimé

---

## 📊 Structure du Projet

```
coursia/
├── src/
│   ├── Entity/              # Modèles de données
│   │   ├── User.php
│   │   ├── Course.php
│   │   └── Journey.php
│   ├── Repository/          # Requêtes personnalisées
│   ├── Service/             # Logique métier
│   │   └── CourseService.php
│   └── Controller/          # Endpoints API
│       ├── AuthController.php
│       ├── CourseController.php
│       └── JourneyController.php
├── migrations/              # Migrations de base de données
├── ARCHITECTURE.md          # Documentation architecture
├── TEST_ENDPOINTS.md        # Guide de test
├── IMPLEMENTATION_COMPLETE.md  # Checklist complète
└── README_IMPLEMENTATION.md    # Ce fichier
```

---

## 🔌 Endpoints API

### Authentification
```
POST   /api/auth/register    # Inscription
POST   /api/auth/login       # Connexion
GET    /api/auth/me          # Profil
PUT    /api/auth/update      # Mise à jour profil
```

### Courses
```
POST   /api/courses          # Créer une course
GET    /api/courses/history  # Historique
```

### Journeys
```
POST   /api/journeys                # Publier un trajet
GET    /api/journeys/available      # Rechercher des trajets
GET    /api/journeys/my-journeys    # Historique
```

---

## 🏗️ Architecture

### Principes Appliqués
- **Clean Architecture** : Séparation claire des responsabilités
- **Services** : Logique métier centralisée
- **Controllers légers** : Validation + Orchestration uniquement
- **Entities simples** : Données pures, pas de logique

### Exemple de Flux

```
Mobile App
    ↓
CourseController::create()
    ↓ Validation
CourseService::determineCourseType()
    ↓ Calcul du type
Course Entity
    ↓ Persistance
Database
    ↓ Réponse JSON
Mobile App
```

Plus de détails dans [ARCHITECTURE.md](ARCHITECTURE.md).

---

## 🧪 Tests

### Vérifier le Schéma
```bash
php bin/console doctrine:schema:validate
# ✅ Mapping files are correct
# ✅ Database schema is in sync
```

### Vérifier les Routes
```bash
php bin/console debug:router | grep -E "(courses|journeys)"
# ✅ 5 routes disponibles
```

### Tester les Endpoints
Voir [TEST_ENDPOINTS.md](TEST_ENDPOINTS.md) pour tous les exemples.

---

## 🔄 Prochaines Étapes (Phase 2)

### Backend
1. Améliorer la détection de pays (API géocodage)
2. Matching géographique avancé (rayon de X km)
3. Système de notifications
4. Endpoint d'acceptation de course

### Frontend
1. Supprimer le dropdown "Type" (calculé par le backend)
2. Écran "Trajets Disponibles"
3. Écran "Publier un Trajet"
4. Historique complet

Plus de détails dans [RECAP_PHASE1.md](RECAP_PHASE1.md) section "Prochaines Étapes".

---

## 📈 Statistiques

- **Entités :** 3 (User, Course, Journey)
- **Services :** 1 (CourseService)
- **Contrôleurs :** 3 (Auth, Course, Journey)
- **Endpoints API :** 9
- **Migrations :** 2
- **Lignes de documentation :** 1000+

---

## 💡 Points Clés

### Ce qui Rend ce Backend Spécial

1. **Calcul Automatique du Type**
   - Le frontend envoie juste les adresses
   - Le backend détermine si c'est national/regional/international
   - Plus besoin de dropdown côté mobile !

2. **Architecture Propre**
   - Logique métier dans CourseService
   - Controllers ultra-légers
   - Code facilement maintenable

3. **Documentation Complète**
   - ARCHITECTURE.md pour comprendre la structure
   - TEST_ENDPOINTS.md pour tester
   - IMPLEMENTATION_COMPLETE.md pour suivre le progrès

4. **Prêt pour la Production**
   - Migrations versionnées
   - Validation complète
   - Gestion d'erreurs

---

## 🤝 Pour les Nouveaux Développeurs

Si vous rejoignez le projet :

1. Lisez [ARCHITECTURE.md](ARCHITECTURE.md) pour comprendre la structure
2. Lisez [TEST_ENDPOINTS.md](TEST_ENDPOINTS.md) pour tester l'API
3. Consultez [IMPLEMENTATION_COMPLETE.md](IMPLEMENTATION_COMPLETE.md) pour voir ce qui est fait

**Règle d'or :** La logique métier va toujours dans un Service, jamais dans un Controller !

---

## 📞 Support

Pour toute question sur l'architecture ou l'implémentation, consultez la documentation dans cet ordre :

1. [ARCHITECTURE.md](ARCHITECTURE.md) - Pour comprendre le "pourquoi"
2. [TEST_ENDPOINTS.md](TEST_ENDPOINTS.md) - Pour tester
3. [IMPLEMENTATION_COMPLETE.md](IMPLEMENTATION_COMPLETE.md) - Pour voir ce qui est fait

---

## ✅ Conclusion

Le backend Coursia Phase 1 est **100% fonctionnel** et **prêt pour l'intégration mobile**.

Toutes les fonctionnalités documentées dans [RECAP_PHASE1.md](RECAP_PHASE1.md) sont implémentées et testées.

**Prochaine étape :** Intégration avec le frontend mobile ou démarrage de la Phase 2.

---

**Auteur :** Équipe Coursia  
**Date de Complétion :** 21 Janvier 2026  
**Statut :** ✅ PHASE 1 COMPLÈTE
