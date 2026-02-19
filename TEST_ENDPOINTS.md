# Guide de Test des Endpoints - Coursia Backend

Ce document fournit des exemples de requêtes pour tester tous les endpoints de l'API Coursia.

## Configuration

**Base URL :** `http://192.168.1.90:8000`

Pour les endpoints protégés, vous devez inclure un token JWT dans l'en-tête :
```
Authorization: Bearer VOTRE_TOKEN_JWT
```

---

## 1. Authentification

### Inscription
```bash
curl -X POST http://192.168.1.90:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "elu@example.com",
    "password": "password123",
    "firstName": "Jean",
    "lastName": "Dupont",
    "phone": "+33612345678",
    "role": "elu",
    "nationality": "FR"
  }'
```

### Connexion
```bash
curl -X POST http://192.168.1.90:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "elu@example.com",
    "password": "password123"
  }'
```

**Réponse :**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "email": "elu@example.com",
    "firstName": "Jean",
    "lastName": "Dupont",
    "role": "elu"
  }
}
```

Copiez le token et utilisez-le pour les requêtes suivantes.

---

## 2. Courses (Demandes de Livraison - Élu)

### Créer une Course

**Important :** Le champ `type` est calculé automatiquement par le backend !

#### Exemple 1 : Course Nationale
```bash
curl -X POST http://192.168.1.90:8000/api/courses \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "departureAddress": "Paris, France",
    "deliveryAddress": "Lyon, France",
    "description": "Documents urgents à livrer",
    "deliveryDateStart": "2026-02-10T10:00:00Z",
    "deliveryDateEnd": "2026-02-15T18:00:00Z"
  }'
```

**Réponse attendue :**
```json
{
  "message": "Course créée avec succès",
  "course": {
    "id": 1,
    "departureAddress": "Paris, France",
    "deliveryAddress": "Lyon, France",
    "type": "national",
    "status": "created",
    "deliveryDateStart": "2026-02-10 10:00:00",
    "deliveryDateEnd": "2026-02-15 18:00:00",
    "createdAt": "2026-01-21 15:30:00"
  }
}
```

#### Exemple 2 : Course Régionale (Afrique)
```bash
curl -X POST http://192.168.1.90:8000/api/courses \
  -H "Authorization: Bearer VOTRE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "departureAddress": "Cotonou, Bénin",
    "deliveryAddress": "Lomé, Togo",
    "description": "Colis de vêtements"
  }'
```

**Réponse attendue :**
```json
{
  "course": {
    "type": "regional"
  }
}
```

#### Exemple 3 : Course Internationale
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

**Réponse attendue :**
```json
{
  "course": {
    "type": "international"
  }
}
```

### Voir son Historique de Courses
```bash
curl -X GET http://192.168.1.90:8000/api/courses/history \
  -H "Authorization: Bearer VOTRE_TOKEN"
```

**Réponse :**
```json
{
  "asElu": [
    {
      "id": 1,
      "title": "Course de Jean",
      "departureAddress": "Paris, France",
      "deliveryAddress": "Cotonou, Bénin",
      "type": "international",
      "status": "created",
      "price": null,
      "isNegotiable": true,
      "deliveryDateStart": "2026-02-10 10:00:00",
      "deliveryDateEnd": null,
      "createdAt": "2026-01-21 15:30:00"
    }
  ],
  "asChevalier": []
}
```

---

## 3. Journeys (Trajets des Chevaliers)

### Créer un Compte Chevalier d'abord
```bash
curl -X POST http://192.168.1.90:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "chevalier@example.com",
    "password": "password123",
    "firstName": "Marie",
    "lastName": "Martin",
    "phone": "+33698765432",
    "role": "chevalier",
    "nationality": "FR"
  }'
```

Puis connectez-vous :
```bash
curl -X POST http://192.168.1.90:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "chevalier@example.com",
    "password": "password123"
  }'
```

### Publier un Trajet

**Important :** Le `type` est calculé automatiquement !

```bash
curl -X POST http://192.168.1.90:8000/api/journeys \
  -H "Authorization: Bearer TOKEN_DU_CHEVALIER" \
  -H "Content-Type: application/json" \
  -d '{
    "departureAddress": "Paris, France",
    "deliveryAddress": "Cotonou, Bénin",
    "departureTime": "2026-02-15T10:00:00Z",
    "arrivalTime": "2026-02-16T14:00:00Z",
    "pricePerKg": 12.50,
    "isNegotiable": true,
    "maxWeight": 20,
    "notes": "Je peux transporter des colis fragiles. Vol direct."
  }'
```

**Réponse attendue :**
```json
{
  "message": "Trajet créé avec succès",
  "journey": {
    "id": 1,
    "chevalier": {
      "id": 2,
      "name": "Marie Martin"
    },
    "departureAddress": "Paris, France",
    "deliveryAddress": "Cotonou, Bénin",
    "type": "international",
    "departureTime": "2026-02-15 10:00:00",
    "arrivalTime": "2026-02-16 14:00:00",
    "pricePerKg": 12.5,
    "isNegotiable": true,
    "maxWeight": 20,
    "notes": "Je peux transporter des colis fragiles. Vol direct.",
    "status": "available",
    "createdAt": "2026-01-21 15:45:00"
  }
}
```

### Chercher des Trajets Disponibles

#### Sans Filtre (tous les trajets)
```bash
curl -X GET "http://192.168.1.90:8000/api/journeys/available"
```

#### Avec Filtres
```bash
curl -X GET "http://192.168.1.90:8000/api/journeys/available?departure=Paris&delivery=Cotonou&date=2026-02-15"
```

**Réponse :**
```json
{
  "journeys": [
    {
      "id": 1,
      "chevalier": {
        "id": 2,
        "name": "Marie Martin"
      },
      "departureAddress": "Paris, France",
      "deliveryAddress": "Cotonou, Bénin",
      "type": "international",
      "departureTime": "2026-02-15 10:00:00",
      "pricePerKg": 12.5,
      "isNegotiable": true,
      "maxWeight": 20,
      "status": "available"
    }
  ]
}
```

### Voir son Historique de Trajets
```bash
curl -X GET http://192.168.1.90:8000/api/journeys/my-journeys \
  -H "Authorization: Bearer TOKEN_DU_CHEVALIER"
```

---

## Scénarios de Test Complets

### Scénario 1 : Élu crée une course, Chevalier publie un trajet correspondant

1. **Élu s'inscrit et se connecte**
2. **Élu crée une course :**
   ```bash
   curl -X POST http://192.168.1.90:8000/api/courses \
     -H "Authorization: Bearer TOKEN_ELU" \
     -H "Content-Type: application/json" \
     -d '{
       "departureAddress": "Paris, France",
       "deliveryAddress": "Cotonou, Bénin",
       "description": "Médicaments"
     }'
   ```

3. **Chevalier s'inscrit et se connecte**
4. **Chevalier publie un trajet correspondant :**
   ```bash
   curl -X POST http://192.168.1.90:8000/api/journeys \
     -H "Authorization: Bearer TOKEN_CHEVALIER" \
     -H "Content-Type: application/json" \
     -d '{
       "departureAddress": "Paris, France",
       "deliveryAddress": "Cotonou, Bénin",
       "departureTime": "2026-02-20T08:00:00Z",
       "pricePerKg": 15.0,
       "isNegotiable": true,
       "maxWeight": 25
     }'
   ```

5. **Élu cherche les trajets disponibles :**
   ```bash
   curl -X GET "http://192.168.1.90:8000/api/journeys/available?departure=Paris&delivery=Cotonou"
   ```

6. **Résultat :** L'Élu voit le trajet du Chevalier dans les résultats !

---

## Validation du Calcul Automatique du Type

### Test 1 : National
- Départ : `Paris, France`
- Arrivée : `Lyon, France`
- **Attendu :** `type: "national"`

### Test 2 : Régional (même continent)
- Départ : `Cotonou, Bénin`
- Arrivée : `Lomé, Togo`
- **Attendu :** `type: "regional"`

### Test 3 : International (continents différents)
- Départ : `Paris, France`
- Arrivée : `Cotonou, Bénin`
- **Attendu :** `type: "international"`

---

## Vérification de l'Architecture

### Ce qui est intelligent (fait par le backend) ✅
- Calcul automatique du type de course/trajet
- Validation des adresses
- Recherche de trajets compatibles (fenêtre de ±2 jours)

### Ce que le frontend n'a plus à faire ✅
- Choisir le type via un dropdown
- Calculer les compatibilités géographiques

---

## Prochaines Fonctionnalités à Tester (Quand Implémentées)

### Acceptation de Course
```bash
curl -X POST http://192.168.1.90:8000/api/courses/1/accept \
  -H "Authorization: Bearer TOKEN_CHEVALIER" \
  -H "Content-Type: application/json" \
  -d '{
    "proposedPrice": 25.0
  }'
```

---

**Dernière mise à jour :** 21 Janvier 2026
**Version :** 1.0
