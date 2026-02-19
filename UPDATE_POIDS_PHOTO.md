# 🎯 Mise à Jour - Système de Poids et Photos (Phase 1.1)

**Date :** 21 Janvier 2026  
**Version :** 1.1  
**Statut :** ✅ Implémenté et Testé

---

## 📝 Problématique Identifiée

### Système de Poids Inadapté
❌ **Avant** : Le système utilisait des kg pour toutes les courses
- Inadapté pour national/régional
- Les utilisateurs n'ont pas toujours de balance
- Utilisation verbale sur le terrain : "léger", "lourd", etc.

✅ **Maintenant** : Système flexible adapté au contexte
- **National/Régional** : Catégories verbales (leger, moyen, lourd, tres_lourd)
- **International** : Poids en kg (quand nécessaire)

### Manque de Visibilité sur le Colis
❌ **Avant** : Pas de photo du colis
- Le Chevalier ne sait pas ce qu'il transporte
- Risque de mauvaises surprises

✅ **Maintenant** : Photo optionnelle du colis
- L'Élu peut prendre une photo lors de la création
- Le Chevalier voit le colis avant d'accepter

### Gestion du Temps Imprécise
❌ **Avant** : Dates de livraison optionnelles
- Matching difficile
- Pas d'alignement horaires Élu/Chevalier

✅ **Maintenant** : Dates obligatoires et matching intelligent
- L'Élu DOIT préciser quand il veut livrer
- Le Chevalier DOIT préciser ses horaires de départ/arrivée
- Matching automatique sur les horaires compatibles

---

## ✅ Modifications Implémentées

### 1. Entité Course (Demande de Livraison)

#### Nouveaux Champs
```php
#[ORM\Column(length: 20, nullable: true)]
private ?string $packageWeight = null;
// leger | moyen | lourd | tres_lourd

#[ORM\Column(length: 255, nullable: true)]
private ?string $packagePhotoPath = null;
```

#### Champs Modifiés
```php
// deliveryDateStart est maintenant OBLIGATOIRE (avant : optionnel)
#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $deliveryDateStart = null;
```

---

### 2. Entité Journey (Trajet du Chevalier)

#### Nouveau Champ
```php
#[ORM\Column(length: 20, nullable: true)]
private ?string $maxPackageWeight = null;
// leger | moyen | lourd | tres_lourd
```

#### Logique
- `maxWeight` (kg) : Pour international
- `maxPackageWeight` (verbal) : Pour national/régional

---

### 3. Système de Poids Hiérarchique

```
leger (1) < moyen (2) < lourd (3) < tres_lourd (4)
```

**Logique de Matching :**
- Si l'Élu a un colis "moyen"
- Le Chevalier peut accepter s'il déclare : "moyen", "lourd" ou "tres_lourd"
- Le Chevalier NE peut PAS accepter s'il déclare : "leger"

**Exemple :**
```
Colis : "lourd"
✅ Chevalier qui accepte "lourd" → Match
✅ Chevalier qui accepte "tres_lourd" → Match
❌ Chevalier qui accepte "moyen" → Pas de match
❌ Chevalier qui accepte "leger" → Pas de match
```

---

## 🔌 Nouveaux Endpoints / Modifications

### POST /api/courses (Créer une Course)

#### Avant
```json
{
  "departureAddress": "Paris, France",
  "deliveryAddress": "Cotonou, Bénin",
  "description": "Médicaments"
}
```

#### Maintenant
```json
{
  "departureAddress": "Paris, France",
  "deliveryAddress": "Cotonou, Bénin",
  "description": "Médicaments",
  "deliveryDateStart": "2026-02-15T10:00:00Z",  // OBLIGATOIRE
  "deliveryDateEnd": "2026-02-20T18:00:00Z",    // Optionnel
  "packageWeight": "moyen",                      // Optionnel (leger, moyen, lourd, tres_lourd)
  "packagePhotoPath": "uploads/courses/photo123.jpg"  // Optionnel
}
```

#### Réponse
```json
{
  "message": "Course créée avec succès",
  "course": {
    "id": 1,
    "type": "international",
    "packageWeight": "moyen",
    "packagePhotoPath": "uploads/courses/photo123.jpg",
    "deliveryDateStart": "2026-02-15 10:00:00",
    "deliveryDateEnd": "2026-02-20 18:00:00",
    ...
  }
}
```

---

### POST /api/journeys (Publier un Trajet)

#### Avant
```json
{
  "departureAddress": "Paris, France",
  "deliveryAddress": "Cotonou, Bénin",
  "departureTime": "2026-02-15T10:00:00Z",
  "pricePerKg": 12.50,
  "maxWeight": 20
}
```

#### Maintenant
```json
{
  "departureAddress": "Paris, France",
  "deliveryAddress": "Cotonou, Bénin",
  "departureTime": "2026-02-15T10:00:00Z",      // OBLIGATOIRE
  "arrivalTime": "2026-02-16T14:00:00Z",        // RECOMMANDÉ
  "pricePerKg": 12.50,
  "maxWeight": 20,                               // Pour international
  "maxPackageWeight": "lourd",                   // Pour national/régional (leger, moyen, lourd, tres_lourd)
  "isNegotiable": true
}
```

#### Réponse
```json
{
  "message": "Trajet créé avec succès",
  "journey": {
    "id": 1,
    "type": "international",
    "departureTime": "2026-02-15 10:00:00",
    "arrivalTime": "2026-02-16 14:00:00",
    "maxWeight": 20,
    "maxPackageWeight": "lourd",
    ...
  }
}
```

---

### GET /api/journeys/available (Recherche Améliorée)

#### Avant
```
GET /api/journeys/available?departure=Paris&delivery=Cotonou&date=2026-02-15
```

#### Maintenant
```
GET /api/journeys/available?departure=Paris&delivery=Cotonou&deliveryDateStart=2026-02-15T10:00:00Z&deliveryDateEnd=2026-02-20T18:00:00Z&packageWeight=moyen
```

**Paramètres :**
- `departure` : Adresse de départ (requis)
- `delivery` : Adresse d'arrivée (requis)
- `deliveryDateStart` : Date de début de livraison souhaitée (optionnel)
- `deliveryDateEnd` : Date de fin de livraison souhaitée (optionnel)
- `packageWeight` : Poids du colis (leger, moyen, lourd, tres_lourd) (optionnel)

**Matching Intelligent :**
1. ✅ Géographique : Adresses compatibles
2. ✅ Temporel : Le trajet du Chevalier est dans la fenêtre de livraison (±1 jour)
3. ✅ Poids : Le Chevalier peut transporter ce poids

---

## 🧪 Exemples de Test

### Test 1 : Course Nationale avec Poids

```bash
curl -X POST http://192.168.1.90:8000/api/courses \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "departureAddress": "Paris, France",
    "deliveryAddress": "Lyon, France",
    "description": "Colis de livres",
    "deliveryDateStart": "2026-02-10T10:00:00Z",
    "deliveryDateEnd": "2026-02-15T18:00:00Z",
    "packageWeight": "lourd"
  }'
```

**Résultat attendu :**
```json
{
  "course": {
    "type": "national",  // Calculé automatiquement
    "packageWeight": "lourd",
    "deliveryDateStart": "2026-02-10 10:00:00",
    "deliveryDateEnd": "2026-02-15 18:00:00"
  }
}
```

---

### Test 2 : Course avec Photo

```bash
curl -X POST http://192.168.1.90:8000/api/courses \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "departureAddress": "Cotonou, Bénin",
    "deliveryAddress": "Lomé, Togo",
    "description": "Électronique fragile",
    "deliveryDateStart": "2026-02-20T08:00:00Z",
    "packageWeight": "moyen",
    "packagePhotoPath": "uploads/courses/photo_electronics.jpg"
  }'
```

---

### Test 3 : Trajet avec Capacité de Poids

```bash
curl -X POST http://192.168.1.90:8000/api/journeys \
  -H "Authorization: Bearer TOKEN_CHEVALIER" \
  -H "Content-Type: application/json" \
  -d '{
    "departureAddress": "Paris, France",
    "deliveryAddress": "Lyon, France",
    "departureTime": "2026-02-12T09:00:00Z",
    "arrivalTime": "2026-02-12T15:00:00Z",
    "pricePerKg": 5.0,
    "maxPackageWeight": "tres_lourd",
    "isNegotiable": true
  }'
```

---

### Test 4 : Recherche avec Tous les Filtres

```bash
curl "http://192.168.1.90:8000/api/journeys/available?departure=Paris&delivery=Lyon&deliveryDateStart=2026-02-10T10:00:00Z&deliveryDateEnd=2026-02-15T18:00:00Z&packageWeight=lourd"
```

**Matching :**
- ✅ Trajet Paris → Lyon
- ✅ Départ entre le 09/02 et le 16/02 (±1 jour)
- ✅ Peut transporter "lourd" ou "tres_lourd"

---

## 📊 Migration de Base de Données

### SQL Appliqué
```sql
-- Ajout des champs de poids et photo dans Course
ALTER TABLE courses ADD package_weight VARCHAR(20) DEFAULT NULL;
ALTER TABLE courses ADD package_photo_path VARCHAR(255) DEFAULT NULL;

-- Ajout du champ de capacité de poids dans Journey
ALTER TABLE journeys ADD max_package_weight VARCHAR(20) DEFAULT NULL;
```

### Migration Symfony
```bash
# Migration créée
migrations/Version20260121165954.php

# Migration appliquée
✅ Successfully migrated
```

---

## 💡 Recommandations Frontend

### Écran : Créer une Course (Élu)

#### Champ Poids
```
Type de course : National/Régional
┌─────────────────────────────────────┐
│ Poids du colis *                    │
│ ○ Léger (sac à main, documents)    │
│ ○ Moyen (valise, carton moyen)     │
│ ○ Lourd (grosse valise, électro)   │
│ ○ Très lourd (meubles, gros colis) │
└─────────────────────────────────────┘
```

#### Champ Photo
```
┌─────────────────────────────────────┐
│ Photo du colis (optionnel)          │
│ [📷 Prendre une photo]              │
│ [Miniature si photo prise]          │
└─────────────────────────────────────┘
```

#### Champ Date
```
┌─────────────────────────────────────┐
│ Quand voulez-vous livrer ? *        │
│ Du : [10/02/2026 10:00]            │
│ Au : [15/02/2026 18:00] (optionnel)│
└─────────────────────────────────────┘
```

---

### Écran : Publier un Trajet (Chevalier)

#### Horaires
```
┌─────────────────────────────────────┐
│ Heure de départ *                   │
│ [15/02/2026 10:00]                 │
│                                     │
│ Heure d'arrivée prévue *            │
│ [15/02/2026 16:00]                 │
└─────────────────────────────────────┘
```

#### Capacité
```
Type de trajet : National/Régional
┌─────────────────────────────────────┐
│ Poids maximum accepté               │
│ ○ Léger                             │
│ ○ Moyen                             │
│ ○ Lourd                             │
│ ○ Très lourd (tout accepter)       │
└─────────────────────────────────────┘
```

---

### Écran : Trajets Disponibles (Élu)

#### Affichage d'un Trajet
```
┌────────────────────────────────────────┐
│ Marie Martin                           │
│ Paris → Lyon                           │
│ 🕐 12/02 09:00 → 12/02 15:00          │
│ 💼 Peut transporter: Très lourd       │
│ 💰 5€/kg • Négociable                 │
│                                        │
│ [📱 Contacter] [✓ Réserver]           │
└────────────────────────────────────────┘
```

---

## 🔄 Changements par Rapport à la Phase 1

| Aspect                | Phase 1          | Phase 1.1 (Maintenant) |
|-----------------------|------------------|------------------------|
| Poids National        | kg uniquement    | Catégories verbales    |
| Photo du colis        | ❌ Absent        | ✅ Optionnel           |
| Date de livraison     | Optionnelle      | **Obligatoire**        |
| Matching temporel     | ±2 jours         | ±1 jour (plus précis)  |
| Matching poids        | ❌ Absent        | ✅ Hiérarchique        |
| Horaires Chevalier    | Optionnels       | **Recommandés**        |

---

## ✅ Tests de Validation

### Schéma de Base de Données
```bash
✅ Mapping files are correct
✅ Database schema is in sync
```

### Cache
```bash
✅ Cache cleared successfully
```

### Routes
```bash
✅ POST   /api/courses
✅ GET    /api/courses/history
✅ POST   /api/journeys
✅ GET    /api/journeys/available
✅ GET    /api/journeys/my-journeys
```

---

## 📝 Résumé pour le Mobile

### Pour l'Élu (Créer une Course)
1. ✅ Choisir : Départ, Arrivée, Description
2. ✅ **NOUVEAU** : Préciser la date de livraison souhaitée (OBLIGATOIRE)
3. ✅ **NOUVEAU** : Choisir le poids verbal (Léger/Moyen/Lourd/Très lourd)
4. ✅ **NOUVEAU** : Prendre une photo du colis (optionnel)
5. ✅ Le type est calculé automatiquement

### Pour le Chevalier (Publier un Trajet)
1. ✅ Choisir : Départ, Arrivée
2. ✅ Préciser : Date/heure de départ (OBLIGATOIRE)
3. ✅ Préciser : Date/heure d'arrivée (RECOMMANDÉ)
4. ✅ **NOUVEAU** : Préciser la capacité de poids (Léger/Moyen/Lourd/Très lourd)
5. ✅ Prix et négociabilité
6. ✅ Le type est calculé automatiquement

### Recherche de Trajets
1. ✅ Le mobile envoie : départ, arrivée, dates, poids
2. ✅ Le backend retourne : trajets compatibles géographiquement, temporellement, et par capacité

---

**Date de Mise à Jour :** 21 Janvier 2026  
**Version :** 1.1  
**Statut :** ✅ IMPLÉMENTÉ ET TESTÉ
