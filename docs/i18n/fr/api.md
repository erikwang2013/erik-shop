# Plateforme de commerce électronique transfrontalier — Documentation des interfaces API

> Documentation dynamique : après le démarrage du Service, accédez à http://localhost:8787/apidoc/ (généré automatiquement par hg/apidoc)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Normes générales

### Format des requêtes

| Élément | Description |
|------|------|
| Base URL | `http://localhost:8787/api` |
| Contrôle de version | En-tête `API-Version: 2026-05-20` (pas dans l'URL) |
| Authentification | En-tête `Authorization: Bearer <token>` |
| Langue | En-tête `Accept-Language: zh_CN|zh_HK|en|ja|ko` |
| Plateforme | En-tête `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` |
| Content-Type | `application/json` (POST/PUT) |
| Vérification homme-machine | En-tête `X-Poster-Token: <token>` (opérations sensibles) |
| URL des ressources | CDN activé : images/documents émis via le domaine CDN (`https://{CDN_DOMAIN}{chemin}`, réécriture d'URL origin-pull via `Cdn::url()`) |

### Format des réponses

```json
// Succès
{"code": 0, "msg": "ok", "data": {}}

// Échec
{"code": 1, "msg": "message d'erreur", "data": null}

// Pagination
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// Codes d'erreur
// 40001 Attaque XSS  40002 Injection SQL  40003 Injection CRLF  40004 Traversée de chemin
// 40005 Corps de requête trop volumineux  40006 Erreur Content-Type  40008 Attaque par force brute
// 40009 Téléversement de fichier non conforme  40010 Injection XXE  40011 Attaque SSRF
// 40012 Méthode HTTP invalide  40013 En-tête Host invalide
// 401 Non authentifié  403 Accès interdit  422 Échec de validation des paramètres  429 Trop de requêtes  503 Service indisponible (disjoncteur/dégradation)
```

### Remarque sur les ID

Dans toutes les interfaces, les champs ID sont des chaînes encodées en hashids (ex. `Ab3xK9pq`), automatiquement encodées/décodées par le middleware. Le front-end n'a pas besoin de les traiter manuellement.

---

## 1. Interfaces d'authentification

### 1.1 Inscription `POST /api/auth/register`

> Nécessite la vérification homme-machine `X-Poster-Token`

**Requête:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**Réponse:**
```json
{
  "code": 0, "msg": "Inscription réussie",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 Connexion `POST /api/auth/login`

**Requête:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Réponse:**
```json
{
  "code": 0, "msg": "Connexion réussie",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "level": 1,
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.3 Rafraîchissement du Token `POST /api/auth/refresh`

**Requête:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**Réponse:**
```json
{
  "code": 0, "msg": "Token rafraîchi",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 Connexion sociale `POST /api/auth/social`

**Requête:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**Réponse:**
```json
{
  "code": 0, "msg": "Connexion réussie",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. Interfaces produits

### 2.1 Liste des produits `GET /api/products`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| page | int | Non | Numéro de page (défaut 1) |
| per_page | int | Non | Nombre par page (défaut 20, max 100) |
| category_id | string | Non | ID de catégorie (hashid, inclut les sous-catégories) |
| keyword | string | Non | Mot-clé de recherche |
| sort | string | Non | Tri : default/price_asc/price_desc/sales/newest |
| min_price | number | Non | Prix minimum |
| max_price | number | Non | Prix maximum |

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Ab3xK9pq",
        "title": "Product Title",
        "subtitle": "Subtitle",
        "main_image": "https://img.example.com/p1.jpg",
        "brand": "BrandName",
        "min_price": 29.99,
        "max_price": 49.99,
        "status": 2,
        "is_hot": true,
        "is_new": false,
        "sales_count": 1000
      }
    ],
    "total": 100, "page": 1, "per_page": 20
  }
}
```

### 2.2 Détail du produit `GET /api/products/{id}`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| currency | string | Non | Code de devise (défaut USD) |
| dest_country | string | Non | Pays de destination ISO2 (défaut US) |

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (correspondance multilingue)",
    "subtitle": "Subtitle",
    "description": "Full description...",
    "brand": "BrandName",
    "main_image": "https://img.example.com/p1.jpg",
    "min_price": 29.99,
    "max_price": 49.99,
    "weight": 500,
    "unit": "piece",
    "status": 2,
    "is_hot": true,
    "is_new": false,
    "sales_count": 1000,
    "view_count": 5000,
    "skus": [
      {
        "id": "Cd4yL8rq",
        "sku_code": "SKU-RED-M",
        "attrs": {"color": "Red", "size": "M"},
        "default_price": 29.99,
        "stock": 100,
        "image": "https://img.example.com/sku1.jpg",
        "display_price": {
          "tax_exclusive": 29.99,
          "tax_inclusive": 35.99,
          "vat_amount": 6.00,
          "vat_rate": 20,
          "currency": "USD",
          "display_mode": "tax_exclusive"
        }
      }
    ],
    "images": [
      {"id": "Ef5zM9ns", "url": "https://img.example.com/p1.jpg", "is_main": true}
    ],
    "compliance_info": [
      {"category": "Marquage CE", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 Avis produits `GET /api/reviews/{productId}`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| page | int | Non | Numéro de page |
| per_page | int | Non | Nombre par page (défaut 10) |
| rating | int | Non | Filtre par note (1-5) |

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Re1v2W3x",
        "user_id": "Ab3xK9pq",
        "product_id": "Ab3xK9pq",
        "rating": 5,
        "content": "Great product!",
        "images": ["https://img.example.com/review1.jpg"],
        "is_anonymous": false,
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 50, "page": 1, "per_page": 10
  }
}
```

---

## 3. Interfaces de catégories

### 3.1 Liste des catégories `GET /api/categories`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| parent_id | int | Non | ID de la catégorie parente (0 = niveau supérieur) |

### 3.2 Arbre des catégories `GET /api/categories/tree`

Renvoie l'arbre de catégories imbriqué complet.

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ct1g2H3i",
      "parent_id": 0,
      "name": "Clothing",
      "slug": "clothing",
      "icon": "icon-url",
      "level": 1,
      "is_hot": true,
      "children": [
        {
          "id": "Ct4j5K6l",
          "parent_id": "Ct1g2H3i",
          "name": "Dresses", "slug": "dresses",
          "level": 2, "is_hot": false,
          "children": []
        }
      ]
    }
  ]
}
```

---

## 4. Interfaces panier `[JWT]`

### 4.1 Liste du panier `GET /api/cart`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| currency | string | Non | Devise (défaut USD) |

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ca1r2T3s",
      "sku_id": "Cd4yL8rq",
      "product_id": "Ab3xK9pq",
      "title": "Product Title",
      "image": "https://img.example.com/sku1.jpg",
      "attrs": {"color":"Red","size":"M"},
      "price": 29.99,
      "currency": "USD",
      "quantity": 2,
      "selected": true,
      "stock": 100
    }
  ]
}
```

### 4.2 Ajouter au panier `POST /api/cart`

**Requête:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 Mettre à jour la quantité `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> Suppression automatique si quantity=0

### 4.4 Supprimer `DELETE /api/cart/{id}`

---

## 5. Interfaces de commandes `[JWT]`

### 5.1 Liste des commandes `GET /api/orders`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| status | int | Non | Filtre par statut : 0 en attente de paiement / 1 payée / 2 expédiée / 3 reçue / 4 terminée / 5 annulée / 6 remboursement en cours / 7 remboursée / 8 en attente de vérification |
| page | int | Non | Numéro de page (défaut 1) |
| per_page | int | Non | Nombre par page (défaut 10) |

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "Payé",
        "total_amount": 59.98, "pay_amount": 59.98,
        "currency_code": "USD",
        "created_at": "2026-05-21 10:30:00",
        "paid_at": "2026-05-21 10:31:00"
      }
    ],
    "total": 10, "page": 1, "per_page": 10
  }
}
```

### 5.2 Détail de la commande `GET /api/orders/{id}`

Renvoie les informations complètes de la commande, y compris items/logs/documents.

### 5.3 Créer une commande `POST /api/orders` `[PosterVerify]`

**Requête:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**Réponse:**
```json
{
  "code": 0, "msg": "Commande créée",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 Annuler la commande `POST /api/orders/{id}/cancel`

> Annulable uniquement si le statut = 0 (en attente de paiement)

### 5.5 Facture commerciale `GET /api/orders/{id}/documents/invoice`

Renvoie un lien de téléchargement du fichier PDF.

### 5.6 Bordereau de colisage `GET /api/orders/{id}/documents/packing-list`

---

## 6. Interfaces de paiement `[JWT]`

### 6.1 Méthodes de paiement disponibles `GET /api/payment/methods`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| country | string | Non | ISO2 (défaut US) |
| currency | string | Non | Devise (défaut USD) |

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "Carte de crédit/débit",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna Payer plus tard",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 Créer un paiement `POST /api/payment/create` `[PosterVerify]`

**Requête:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**Réponse:**
```json
{
  "code": 0, "msg": "Paiement créé",
  "data": {
    "payment_id": "Pa1y2M3t",
    "order_no": "ORD20260521A1B2C3D4",
    "amount": 59.98,
    "currency": "USD",
    "gateway": "stripe",
    "method": "card",
    "client_secret": "pi_3Nxxxx_secret_xxxx",
    "txn_id": "pi_3Nxxxxxxxxxxxx"
  }
}
```

### 6.3 Statut du paiement `GET /api/payment/status/{id}`

### 6.4 Callback Webhook `POST /webhook/payment/{gateway}`

> Pas de JWT requis. Appelé de manière asynchrone par la passerelle de paiement. Vérification de la signature requise.

---

## 7. Interfaces logistiques

### 7.1 Calcul des frais d'expédition `GET /api/shipping/calculate`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| dest_country_id | int | Oui | ID du pays de destination (snowflake) |
| weight | int | Non | Poids (grammes) (défaut 500) |

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "Amérique du Nord",
    "weight_kg": 0.5,
    "dest_country": "US",
    "options": [
      {
        "logistics_name": "DHL Express",
        "logistics_code": "DHL",
        "fee": 25.50,
        "estimated_days": "3-5",
        "tracking_url": "https://www.dhl.com/track?num="
      }
    ]
  }
}
```

---

## 8. Interfaces douanières

### 8.1 Estimation des droits de douane `GET /api/tariff/estimate`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| product_id | string | Oui | ID du produit (hashid) |
| dest_country_id | int | Oui | ID du pays de destination |
| declared_value | number | Oui | Valeur déclarée |

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "À titre indicatif uniquement, le montant final est déterminé par les douanes"
  }
}
```

---

## 9. Interfaces de retour `[JWT]`

### 9.1 Liste des retours `GET /api/returns`

### 9.2 Demander un retour `POST /api/returns`

**Requête:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 Bordereau de retour `GET /api/returns/{id}/label`

---

## 10. Interfaces utilisateur `[JWT]`

### 10.1 Informations personnelles `GET /api/user/profile`

### 10.2 Mettre à jour les informations `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 Liste des adresses `GET /api/user/addresses`

### 10.4 Ajouter une adresse `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "Domicile"
}
```

### 10.5 Mettre à jour l'adresse `PUT /api/user/addresses/{id}`

### 10.6 Supprimer l'adresse `DELETE /api/user/addresses/{id}`

### 10.7 Langue et devise `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. Interfaces marketing

### 11.1 Bannières carrousel `GET /api/banners?position=home`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| position | string | Non | Position : home/category/product |

### 11.2 Coupons disponibles `GET /api/coupons` `[JWT]`

### 11.3 Récupérer un coupon `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 Liste des ventes flash `GET /api/flash-sales`

### 11.5 Liste des achats groupés `GET /api/group-buys`

### 11.6 Liens de distribution `GET /api/affiliate/links` `[JWT]`

### 11.7 Commissions de distribution `GET /api/affiliate/commissions` `[JWT]`

---

## 12. Interfaces de membre `[JWT]`

### 12.1 Informations de membre `GET /api/membership`

**Réponse:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "current_level": {"id": "Lv1", "name": "Gold", "level": 2},
    "current_benefits": [{"benefit_type": "discount", "benefit_value": "5%"}],
    "all_levels": [],
    "current_score": 1500
  }
}
```

### 12.2 Journal des points `GET /api/points`

---

## 13. Autres interfaces

### 13.1 Données des pays `GET /api/countries`

Renvoie tous les pays/devises/taux de change/valeurs par défaut disponibles.

### 13.2 Configuration publique `GET /api/settings?group=general`

### 13.3 Recherche ES `GET /api/search?keyword=xxx`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| keyword | string | Oui | Terme de recherche |
| category_id | string | Non | Filtre de catégorie |
| page | int | Non | Numéro de page |

### 13.4 Comparaison de produits `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE nécessite l'ID d'enregistrement de comparaison : `DELETE /api/comparisons/{id}` (`{id}` est l'ID de l'enregistrement de comparaison, requis)

### 13.5 Recommandations personnalisées `GET /api/recommendations` `[JWT]`

### 13.6 Alertes de baisse de prix `GET/POST /api/price-alerts` `[JWT]`

### 13.7 Favoris `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 Notifications `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 Pages CMS `GET /api/cms/{slug}`

### 13.11 Tableaux de tailles `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 Vérification de conformité `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 Détection GeoIP `GET /api/geoip/detect`

### 13.14 Publier un avis `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 Solde de carte cadeau `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 Échange de carte cadeau `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 Demande GDPR `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 Exporter les commandes `GET /api/export/orders` `[JWT]`

| Paramètre | Type | Requis | Description |
|------|------|------|------|
| date_from | string | Non | Date de début (YYYY-MM-DD) |
| date_to | string | Non | Date de fin |

Renvoie un téléchargement de fichier CSV.

### 13.19 Demande de devis B2B `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 Vérification de santé `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## Annexe : Correspondance des codes de statut

### Statuts de commande

| Valeur | Description |
|----|------|
| 0 | En attente de paiement |
| 1 | Payée |
| 2 | Expédiée |
| 3 | Reçue |
| 4 | Terminée |
| 5 | Annulée |
| 6 | Remboursement en cours |
| 7 | Remboursée |
| 8 | En attente de vérification (gestion des risques) |

### Statuts de produit

| Valeur | Description |
|----|------|
| 0 | Brouillon |
| 1 | En attente de vérification |
| 2 | En vente |
| 3 | Retiré de la vente |

### Statuts de paiement

| Valeur | Description |
|----|------|
| 0 | En attente de paiement |
| 1 | Payé |
| 2 | Remboursé |
| 3 | Échec |

### Mode d'affichage des prix par pays

| Valeur | Description |
|----|------|
| tax_inclusive | Prix TTC (UE/UK) |
| tax_exclusive | Prix HT (US/CA) |
| both | Affichage parallèle (JP) |

---

## Annexe : Pipeline de middlewares

```
Requête → Cors → Security(31 types) → RateLimit(Seau de jetons) → Platform(8 plateformes)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → Contrôleur
```

Légende : `[JWT]` authentification requise | `[PosterVerify]` vérification homme-machine requise | sans marque = interface publique

---

## Annexe : Vue d'ensemble des statistiques d'endpoints

### A.1 Interfaces publiques (23 endpoints)

| Méthode | Chemin | Description |
|------|------|------|
| POST | /api/auth/register | Inscription (PosterVerify) |
| POST | /api/auth/login | Connexion |
| POST | /api/auth/refresh | Rafraîchissement du token |
| POST | /api/auth/social | Connexion sociale |
| GET | /api/products | Liste des produits (pagination + filtres + tri) |
| GET | /api/products/{id} | Détail du produit (multilingue + multidevise + conformité + HS) |
| GET | /api/categories | Liste des catégories |
| GET | /api/categories/tree | Arbre des catégories |
| GET | /api/banners | Bannières carrousel (par position + région) |
| GET | /api/countries | Liste des pays/devises/taux de change |
| GET | /api/search | Recherche multilingue ES |
| GET | /api/reviews/{productId} | Liste des avis produits |
| GET | /api/flash-sales | Ventes flash en cours |
| GET | /api/group-buys | Achats groupés en cours |
| GET | /api/faq | FAQ (par langue + catégorie) |
| GET | /api/cms/{slug} | Pages CMS |
| GET | /api/settings | Configuration publique |
| GET | /api/size-charts | Tableaux de tailles |
| GET | /api/tariff/estimate | Estimation des droits de douane |
| GET | /api/shipping/calculate | Calcul des frais d'expédition |
| GET | /api/payment/methods | Méthodes de paiement disponibles |
| GET | /api/geoip/detect | Détection GeoIP |
| GET | /api/compliance/check | Vérification de conformité |

### A.2 Interfaces authentifiées (47 endpoints)

| Méthode | Chemin | Description |
|------|------|------|
| GET/PUT | /api/user/profile | Informations personnelles |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | CRUD d'adresses |
| PUT | /api/user/locale | Mise à jour langue/devise |
| GET/POST | /api/wishlist[/{id}] | Favoris |
| GET/POST | /api/price-alerts | Alertes de baisse de prix |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | Panier |
| GET/POST | /api/orders | Liste/création de commandes (PosterVerify) |
| GET | /api/orders/{id} | Détail de la commande |
| POST | /api/orders/{id}/cancel | Annuler la commande |
| GET | /api/orders/{id}/documents/invoice | Facture commerciale |
| GET | /api/orders/{id}/documents/packing-list | Bordereau de colisage |
| POST | /api/payment/create | Créer un paiement (PosterVerify) |
| GET | /api/payment/status/{id} | Statut du paiement |
| GET/POST | /api/returns[/{id}] | Retours |
| GET | /api/returns/{id}/label | Bordereau de retour |
| POST | /api/reviews | Publier un avis |
| GET/POST | /api/coupons[/{id}/claim] | Coupons |
| GET/PUT | /api/notifications[/{id}/read] | Notifications |
| GET/POST/DELETE | /api/comparisons[/{id}] | Comparaison de produits |
| GET | /api/recommendations | Recommandations personnalisées |
| GET | /api/affiliate/links | Liens de distribution |
| GET | /api/affiliate/commissions | Commissions de distribution |
| GET | /api/membership | Niveau de membre |
| GET | /api/points | Journal des points |
| GET/POST | /api/gift-cards | Cartes cadeaux |
| GET/POST | /api/b2b/quotes | Demandes de devis B2B |
| GET/POST | /api/privacy/request | Demandes GDPR |
| GET | /api/export/orders | Exporter les commandes |

### A.3 Webhook (1 endpoint)

| Méthode | Chemin | Description |
|------|------|------|
| POST | /webhook/payment/{gateway} | Notification asynchrone de paiement (vérification de signature) |

### A.4 Admin et vérification de santé (2 endpoints)

| Méthode | Chemin | Description |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | Exécution de remboursement en back-office |
| GET | /health | Vérification de santé |

---

## Annexe : Normes de conception API

### Contrôle de version

La version est transmise via l'en-tête `API-Version: 2026-05-20`, pas dans l'URL. Mappée par le middleware VersionRoute.

### Pipeline de middlewares

```
Cors → Security(31 types) → RateLimit(Fenêtre glissante) → Platform(8 plateformes) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### Statistiques d'endpoints

- Interfaces publiques : 23 (authentification/produits/catégories/contenu/recherche/services)
- Interfaces authentifiées : 47 (utilisateur/panier/commandes/paiement/retours/avis/marketing)
- Webhook : 1 (callback de paiement)
- Admin : 1 (exécution de remboursement)
- Health : 1 (/health vérification de santé)

### Réponse unifiée

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### Documentation dynamique hg/apidoc

Utilise hg/apidoc pour générer automatiquement la documentation à partir des annotations des contrôleurs. Accédez à `/apidoc/` après le démarrage.

Exemple d'annotations :
```php
/**
 * @Apidoc\Title("Connexion utilisateur")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
