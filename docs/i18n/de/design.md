# Cross-Border-E-Commerce-Plattform — Designdokument

> Dieses Dokument ist eine maschinelle Übersetzung der ursprünglichen chinesischen Dokumentation. Original: [中文原版](../../design.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Datenbankdesign

### 1.1 Namenskonventionen

- Tabellenpräfix: `erik_`
- Primärschlüssel: `id BIGINT UNSIGNED NOT NULL` (von Snowflake generiert, nicht autoinkrementierend)
- Zeitstempel: `created_at`, `updated_at`, `deleted_at` (Soft-Delete)
- Engine: InnoDB, Zeichensatz: utf8mb4_unicode_ci

### 1.2 Modulaufteilung (110 Tabellen)

| Modul | Anzahl Tabellen | Kerntabellen |
|------|------|--------|
| Benutzer & Konten | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| Produkte & Kategorien | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| Transaktionen | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| Zahlung & Finanzen | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| Logistik | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| Zoll & Steuern | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| Marketing | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| Lieferkette | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| Risikomanagement & Compliance | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| Multi-Plattform | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| Content & Erlebnis | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| Abos & B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| Kundenservice | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| AB-Tests | 3 | ab_tests, ab_test_variants, ab_test_results |
| API-Governance | 2 | api_rate_limits, api_docs |
| Basisdaten | 3 | countries, currencies, exchange_rates |

### 1.3 Plattform-Tracking-Felder

| Tabelle | Feld | Beschreibung |
|----|------|------|
| orders | platform VARCHAR(16) | Bestellplattform |
| payments | platform VARCHAR(16) | Zahlungsplattform |
| operation_logs | platform VARCHAR(16) | Betriebsplattform |
| users | last_login_platform VARCHAR(16) | Plattform der letzten Anmeldung |
| search_logs | platform VARCHAR(16) | Suchplattform |
| chat_messages | platform VARCHAR(16) | Nachrichtenquelle |

---

## 2. API-Design

API-Versionskontrolle, Middleware-Pipeline, Endpunkt-Statistik und einheitliche Antwort-Spezifikation — siehe [API-Schnittstellendokument](api.md).

---

## 3. Sicherheitsdesign

### 3.1 SecurityMiddleware kapselt 31 security-php-Erkennungsmodule

| # | Typ | Fehlercode | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | SQL-Injection | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | Pfad-Traversal | 40004 | ✅ | ✅ |
| 5 | Body zu groß | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | Datei-Upload | 40009 | ✅ | ✅ |
| 8 | Sichere Antwort-Header | — | ✅ | ✅ |
| 9 | Brute-Force | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | HTTP-Methode | 40012 | ✅ | ✅ |
| 13 | Host-Header | 40013 | ✅ | — |
| 14 | Maskierung sensibler Daten | — | ✅ | ✅ |
| 15 | CORS-Whitelist | — | ⚠️ | ⚠️ |

### 3.2 Drei-Schichten-Verschlüsselung

| Ebene | Technik | Paket |
|------|------|-----|
| Übertragungsebene | AES-256-CBC | erikwang2013/encryption |
| Datenbankebene | Encryptable-Trait | erikwang2013/encryptable (Maize) |
| ID-Verschleierung | Hashids | erikwang2013/hashids |

---

## 4. Hochparallelitätsdesign

### 4.1 Rate-Limiting

Token-Bucket-Sliding-Window (Redis ZSET, über die `support\Redis`-Fassade): standardmäßig 60s/100 Anfragen, Login 10/60s, Registrierung 5/300s, Social-Login 5/300s, Zahlung 5/60s, Bestellung 3/10s, Suche 10/1s

### 4.2 Redis-Verwendung

Redis wird für das Rate-Limiting-Token-Bucket (`support\Redis`-Fassade), Mensch-Maschine-Verifizierungscodes und Session-Speicherung verwendet; Geschäftsdaten werden nicht auf Anwendungsebene gecacht, sondern direkt aus MySQL gelesen (Read/Write-Splitting + Verbindungspool).

### 4.3 Verbindungspool

MySQL: 50max/10min/2s-Timeout | Read/Write-Splitting: 30max/5min (2 Lesereplikate, sticky=true) | Redis: 30max/5min

---

## 5. Internationalisierung

- Oberfläche: zh_CN, zh_HK, en, ja, ko
- Inhalte: erik_product_translations mit separaten Zeilen pro locale
- Preise: erik_product_sku_prices mit separater Preisgestaltung pro Währung
- Header: Accept-Language + API-Version

## 6. API-Dokumentation

Wird automatisch von hg/apidoc anhand der Controller-Annotationen generiert — siehe [API-Schnittstellendokument](api.md). Nach dem Start unter `/apidoc/` aufrufbar.

## 7. Tests

22 Tests / 45 Assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

Siehe: [Funktionsdesigndokument](features.md) | [Vollständiges Architekturdokument](architecture-full.md) | [Bereitstellungsdokument](deployment.md)
