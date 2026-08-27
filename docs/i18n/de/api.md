# Cross-Border-E-Commerce-Plattform — API-Schnittstellendokument

> Dieses Dokument ist eine maschinelle Übersetzung der ursprünglichen chinesischen Dokumentation. Original: [中文原版](../../api.md).
>
> Dynamisches Dokument: Nach dem Start des Service unter http://localhost:8787/apidoc/ verfügbar (automatisch von hg/apidoc generiert)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Allgemeine Konventionen

### Anfrageformat

| Punkt | Beschreibung |
|------|------|
| Base URL | `http://localhost:8787/api` |
| Versionskontrolle | `API-Version: 2026-05-20` header (nicht in der URL) |
| Authentifizierung | `Authorization: Bearer <token>` header |
| Sprache | `Accept-Language: zh_CN|zh_HK|en|ja|ko` header |
| Plattform | `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` header |
| Content-Type | `application/json` (POST/PUT) |
| Mensch-Maschine-Prüfung | `X-Poster-Token: <token>` header (bei sensiblen Operationen) |

### Antwortformat

```json
// Erfolg
{"code": 0, "msg": "ok", "data": {}}

// Fehler
{"code": 1, "msg": "Fehlermeldung", "data": null}

// Paginierung
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// Fehlercodes
// 40001 XSS-Angriff  40002 SQL-Injection  40003 CRLF-Injection  40004 Pfad-Traversal
// 40005 Anfrage-Body zu groß  40006 Content-Type-Fehler  40008 Brute-Force
// 40009 Datei-Upload-Verstoß  40010 XXE-Injection  40011 SSRF-Angriff
// 40012 HTTP-Methode ungültig  40013 Host-Header-Fehler
// 401 Nicht angemeldet  403 Zugriff verweigert  422 Parameter-Validierung fehlgeschlagen  429 Zu viele Anfragen  503 Dienst vorübergehend nicht verfügbar (Circuit Breaker/Degradation)
```

### ID-Erläuterung

Alle ID-Felder in den Schnittstellen sind hashids-kodierte Zeichenketten (z. B. `Ab3xK9pq`), die von der Middleware automatisch kodiert/dekodiert werden. Keine manuelle Verarbeitung im Frontend erforderlich.

---

## 1. Authentifizierungs-Schnittstellen

### 1.1 Registrierung `POST /api/auth/register`

> Erfordert Mensch-Maschine-Prüfung `X-Poster-Token`

**Anfrage:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**Antwort:**
```json
{
  "code": 0, "msg": "Registrierung erfolgreich",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 Anmeldung `POST /api/auth/login`

**Anfrage:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Antwort:**
```json
{
  "code": 0, "msg": "Anmeldung erfolgreich",
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

### 1.3 Token aktualisieren `POST /api/auth/refresh`

**Anfrage:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**Antwort:**
```json
{
  "code": 0, "msg": "Token aktualisiert",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 Soziale Anmeldung `POST /api/auth/social`

**Anfrage:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**Antwort:**
```json
{
  "code": 0, "msg": "Anmeldung erfolgreich",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. Produkt-Schnittstellen

### 2.1 Produktliste `GET /api/products`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| page | int | nein | Seitennummer (Standard 1) |
| per_page | int | nein | Anzahl pro Seite (Standard 20, Maximum 100) |
| category_id | string | nein | Kategorie-ID (hashid, inkl. Unterkategorien) |
| keyword | string | nein | Suchbegriff |
| sort | string | nein | Sortierung: default/price_asc/price_desc/sales/newest |
| min_price | number | nein | Mindestpreis |
| max_price | number | nein | Höchstpreis |

**Antwort:**
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

### 2.2 Produktdetail `GET /api/products/{id}`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| currency | string | nein | Währungscode (Standard USD) |
| dest_country | string | nein | Zielland ISO2 (Standard US) |

**Antwort:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (mehrsprachige Anpassung)",
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
      {"category": "CE-Kennzeichnung", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 Produktbewertungen `GET /api/reviews/{productId}`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| page | int | nein | Seitennummer |
| per_page | int | nein | Pro Seite (Standard 10) |
| rating | int | nein | Bewertungsfilter (1-5) |

**Antwort:**
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

## 3. Kategorie-Schnittstellen

### 3.1 Kategorieliste `GET /api/categories`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| parent_id | int | nein | ID der übergeordneten Kategorie (0 = oberste Ebene) |

### 3.2 Kategoriebaum `GET /api/categories/tree`

Gibt den vollständig verschachtelten Kategoriebaum zurück.

**Antwort:**
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

## 4. Warenkorb-Schnittstellen `[JWT]`

### 4.1 Warenkorb-Liste `GET /api/cart`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| currency | string | nein | Währung (Standard USD) |

**Antwort:**
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

### 4.2 In den Warenkorb legen `POST /api/cart`

**Anfrage:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 Menge aktualisieren `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> Bei quantity=0 automatisches Löschen

### 4.4 Löschen `DELETE /api/cart/{id}`

---

## 5. Bestell-Schnittstellen `[JWT]`

### 5.1 Bestellliste `GET /api/orders`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| status | int | nein | Statusfilter: 0 ausstehende Zahlung / 1 bezahlt / 2 versendet / 3 empfangen / 4 abgeschlossen / 5 storniert / 6 Rückerstattung läuft / 7 erstattet / 8 in Prüfung |
| page | int | nein | Seitennummer (Standard 1) |
| per_page | int | nein | Pro Seite (Standard 10) |

**Antwort:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "Bezahlt",
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

### 5.2 Bestelldetail `GET /api/orders/{id}`

Gibt die vollständigen Bestellinformationen inkl. items/logs/documents zurück.

### 5.3 Bestellung erstellen `POST /api/orders` `[PosterVerify]`

**Anfrage:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**Antwort:**
```json
{
  "code": 0, "msg": "Bestellung erstellt",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 Bestellung stornieren `POST /api/orders/{id}/cancel`

> Nur bei Status=0 (ausstehende Zahlung) stornierbar

### 5.5 Handelsrechnung `GET /api/orders/{id}/documents/invoice`

Gibt einen PDF-Download-Link zurück.

### 5.6 Packliste `GET /api/orders/{id}/documents/packing-list`

---

## 6. Zahlungs-Schnittstellen `[JWT]`

### 6.1 Verfügbare Zahlungsmethoden `GET /api/payment/methods`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| country | string | nein | ISO2 (Standard US) |
| currency | string | nein | Währung (Standard USD) |

**Antwort:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "Kreditkarte/Lastschrift",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna Jetzt kaufen, später zahlen",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 Zahlung erstellen `POST /api/payment/create` `[PosterVerify]`

**Anfrage:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**Antwort:**
```json
{
  "code": 0, "msg": "Zahlung erstellt",
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

### 6.3 Zahlungsstatus `GET /api/payment/status/{id}`

### 6.4 Webhook-Callback `POST /webhook/payment/{gateway}`

> Kein JWT erforderlich. Asynchron vom Zahlungs-Gateway aufgerufen. Signaturprüfung erforderlich.

---

## 7. Logistik-Schnittstellen

### 7.1 Frachtberechnung `GET /api/shipping/calculate`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| dest_country_id | int | ja | ID des Ziellandes (snowflake) |
| weight | int | nein | Gewicht (Gramm) (Standard 500) |

**Antwort:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "Nordamerika",
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

## 8. Zoll-Schnittstellen

### 8.1 Zollschätzung `GET /api/tariff/estimate`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| product_id | string | ja | Produkt-ID (hashid) |
| dest_country_id | int | ja | ID des Ziellandes |
| declared_value | number | ja | Deklarationswert |

**Antwort:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "Nur als Referenz, maßgeblich ist die Zollprüfung"
  }
}
```

---

## 9. Retouren-Schnittstellen `[JWT]`

### 9.1 Retouren-Liste `GET /api/returns`

### 9.2 Retoure beantragen `POST /api/returns`

**Anfrage:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 Retouren-Etikett `GET /api/returns/{id}/label`

---

## 10. Benutzer-Schnittstellen `[JWT]`

### 10.1 Persönliche Daten `GET /api/user/profile`

### 10.2 Daten aktualisieren `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 Adressliste `GET /api/user/addresses`

### 10.4 Adresse hinzufügen `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "Zuhause"
}
```

### 10.5 Adresse aktualisieren `PUT /api/user/addresses/{id}`

### 10.6 Adresse löschen `DELETE /api/user/addresses/{id}`

### 10.7 Sprache/Währung `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. Marketing-Schnittstellen

### 11.1 Karussell `GET /api/banners?position=home`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| position | string | nein | Position: home/category/product |

### 11.2 Verfügbare Gutscheine `GET /api/coupons` `[JWT]`

### 11.3 Gutschein einlösen `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 Blitzverkaufs-Liste `GET /api/flash-sales`

### 11.5 Gruppenkauf-Liste `GET /api/group-buys`

### 11.6 Affiliate-Links `GET /api/affiliate/links` `[JWT]`

### 11.7 Affiliate-Provisionen `GET /api/affiliate/commissions` `[JWT]`

---

## 12. Mitgliedschafts-Schnittstellen `[JWT]`

### 12.1 Mitgliedschaftsinformationen `GET /api/membership`

**Antwort:**
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

### 12.2 Punkteverlauf `GET /api/points`

---

## 13. Sonstige Schnittstellen

### 13.1 Länderdaten `GET /api/countries`

Gibt alle verfügbaren Länder/Währungen/Wechselkurse/Standardwerte zurück.

### 13.2 Öffentliche Konfiguration `GET /api/settings?group=general`

### 13.3 ES-Suche `GET /api/search?keyword=xxx`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| keyword | string | ja | Suchbegriff |
| category_id | string | nein | Kategoriefilter |
| page | int | nein | Seitennummer |

### 13.4 Produktvergleich `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE erfordert die Vergleichs-ID: `DELETE /api/comparisons/{id}` (`{id}` ist die ID des Vergleichseintrags, Pflicht)

### 13.5 Personalisierte Empfehlungen `GET /api/recommendations` `[JWT]`

### 13.6 Preisalarme `GET/POST /api/price-alerts` `[JWT]`

### 13.7 Merkliste `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 Benachrichtigungen `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 CMS-Seiten `GET /api/cms/{slug}`

### 13.11 Größentabelle `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 Compliance-Prüfung `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 GeoIP-Erkennung `GET /api/geoip/detect`

### 13.14 Bewertung abgeben `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 Geschenkkarten-Guthaben `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 Geschenkkarte einlösen `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 GDPR-Anfrage `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 Bestellungen exportieren `GET /api/export/orders` `[JWT]`

| Parameter | Typ | Pflicht | Beschreibung |
|------|------|------|------|
| date_from | string | nein | Startdatum (YYYY-MM-DD) |
| date_to | string | nein | Enddatum |

Gibt eine CSV-Datei zum Download zurück.

### 13.19 B2B-Preisangebot `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 Health-Check `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## Anhang: Statuscode-Übersicht

### Bestellstatus

| Wert | Beschreibung |
|----|------|
| 0 | Ausstehende Zahlung |
| 1 | Bezahlt |
| 2 | Versendet |
| 3 | Empfangen |
| 4 | Abgeschlossen |
| 5 | Storniert |
| 6 | Rückerstattung läuft |
| 7 | Erstattet |
| 8 | In Prüfung (Risiko) |

### Produktstatus

| Wert | Beschreibung |
|----|------|
| 0 | Entwurf |
| 1 | In Prüfung |
| 2 | Veröffentlicht |
| 3 | Nicht mehr gelistet |

### Zahlungsstatus

| Wert | Beschreibung |
|----|------|
| 0 | Ausstehende Zahlung |
| 1 | Bezahlt |
| 2 | Erstattet |
| 3 | Fehlgeschlagen |

### Anzeigemodus für Landespreise

| Wert | Beschreibung |
|----|------|
| tax_inclusive | Inkl. Steuer (EU/UK) |
| tax_exclusive | Ohne Steuer (US/CA) |
| both | Nebeneinander (JP) |

---

## Anhang: Middleware-Pipeline

```
Anfrage → Cors → Security(31 Typen) → RateLimit(Token-Bucket) → Platform(8 Plattformen)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → Controller
```

Kennzeichnung: `[JWT]` erfordert Authentifizierung | `[PosterVerify]` erfordert Mensch-Maschine-Prüfung | ohne Kennzeichnung = öffentliche Schnittstelle

---

## Anhang: Endpunkt-Statistikübersicht

### A.1 Öffentliche Schnittstellen (23 Endpunkte)

| Methode | Pfad | Beschreibung |
|------|------|------|
| POST | /api/auth/register | Registrierung (PosterVerify) |
| POST | /api/auth/login | Anmeldung |
| POST | /api/auth/refresh | Token aktualisieren |
| POST | /api/auth/social | Soziale Anmeldung |
| GET | /api/products | Produktliste (Paginierung+Filter+Sortierung) |
| GET | /api/products/{id} | Produktdetail (mehrsprachig+mehrwährig+Compliance+HS) |
| GET | /api/categories | Kategorieliste |
| GET | /api/categories/tree | Kategoriebaum |
| GET | /api/banners | Karussell (nach Position+Region) |
| GET | /api/countries | Länder-/Währungs-/Wechselkursliste |
| GET | /api/search | ES-Mehrsprach-Suche |
| GET | /api/reviews/{productId} | Produktbewertungsliste |
| GET | /api/flash-sales | Aktuelle Blitzverkäufe |
| GET | /api/group-buys | Aktuelle Gruppenkäufe |
| GET | /api/faq | FAQ (nach Sprache+Kategorie) |
| GET | /api/cms/{slug} | CMS-Seiten |
| GET | /api/settings | Öffentliche Konfiguration |
| GET | /api/size-charts | Größentabelle |
| GET | /api/tariff/estimate | Zollschätzung |
| GET | /api/shipping/calculate | Frachtberechnung |
| GET | /api/payment/methods | Verfügbare Zahlungsmethoden |
| GET | /api/geoip/detect | GeoIP-Erkennung |
| GET | /api/compliance/check | Compliance-Prüfung |

### A.2 Authentifizierte Schnittstellen (47 Endpunkte)

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET/PUT | /api/user/profile | Persönliche Daten |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | Adress-CRUD |
| PUT | /api/user/locale | Sprache/Währung aktualisieren |
| GET/POST | /api/wishlist[/{id}] | Merkliste |
| GET/POST | /api/price-alerts | Preisalarme |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | Warenkorb |
| GET/POST | /api/orders | Bestellliste/-erstellung (PosterVerify) |
| GET | /api/orders/{id} | Bestelldetail |
| POST | /api/orders/{id}/cancel | Bestellung stornieren |
| GET | /api/orders/{id}/documents/invoice | Handelsrechnung |
| GET | /api/orders/{id}/documents/packing-list | Packliste |
| POST | /api/payment/create | Zahlung erstellen (PosterVerify) |
| GET | /api/payment/status/{id} | Zahlungsstatus |
| GET/POST | /api/returns[/{id}] | Retoure |
| GET | /api/returns/{id}/label | Retouren-Etikett |
| POST | /api/reviews | Bewertung abgeben |
| GET/POST | /api/coupons[/{id}/claim] | Gutscheine |
| GET/PUT | /api/notifications[/{id}/read] | Benachrichtigungen |
| GET/POST/DELETE | /api/comparisons[/{id}] | Produktvergleich |
| GET | /api/recommendations | Personalisierte Empfehlungen |
| GET | /api/affiliate/links | Affiliate-Links |
| GET | /api/affiliate/commissions | Affiliate-Provisionen |
| GET | /api/membership | Mitgliedsstufen |
| GET | /api/points | Punkteverlauf |
| GET/POST | /api/gift-cards | Geschenkkarten |
| GET/POST | /api/b2b/quotes | B2B-Preisangebote |
| GET/POST | /api/privacy/request | GDPR-Anfragen |
| GET | /api/export/orders | Bestellungen exportieren |

### A.3 Webhook (1 Endpunkt)

| Methode | Pfad | Beschreibung |
|------|------|------|
| POST | /webhook/payment/{gateway} | Asynchrone Zahlungsbenachrichtigung (Signaturprüfung) |

### A.4 Admin und Health-Check (2 Endpunkte)

| Methode | Pfad | Beschreibung |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | Ausführung von Rückerstattungen im Backend |
| GET | /health | Health-Check |

---

## Anhang: API-Designspezifikation

### Versionskontrolle

Die Version wird über den Header `API-Version: 2026-05-20` übergeben, nicht in der URL. Zuordnung durch die VersionRoute-Middleware.

### Middleware-Pipeline

```
Cors → Security(31 Typen) → RateLimit(Sliding Window) → Platform(8 Plattformen) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### Endpunkt-Statistik

- Öffentliche Schnittstellen: 23 (Authentifizierung/Produkte/Kategorien/Content/Suche/Dienste)
- Authentifizierte Schnittstellen: 47 (Benutzer/Warenkorb/Bestellungen/Zahlung/Retoure/Bewertungen/Marketing)
- Webhook: 1 (Zahlungs-Callback)
- Admin: 1 (Rückerstattungs-Ausführung)
- Health: 1 (/health Health-Check)

### Einheitliche Antwort

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### hg/apidoc dynamisches Dokument

Wird mit hg/apidoc automatisch aus den Controller-Annotationen generiert. Nach dem Start unter `/apidoc/` verfügbar.

Annotationsbeispiel:
```php
/**
 * @Apidoc\Title("Benutzer-Login")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
