# Plateforme de commerce électronique transfrontalier — Document de conception

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Conception de la base de données

### 1.1 Normes de nommage

- Préfixe de table : `erik_`
- Clé primaire : `id BIGINT UNSIGNED NOT NULL` (généré par snowflake, non auto-incrémenté)
- Horodatages : `created_at`, `updated_at`, `deleted_at` (suppression douce)
- Moteur : InnoDB, jeu de caractères : utf8mb4_unicode_ci

### 1.2 Répartition par modules (110 tables)

| Module | Nombre de tables | Tables principales |
|------|------|--------|
| Utilisateurs et comptes | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| Produits et catégories | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| Transactions | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| Paiement et fonds | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| Logistique | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| Douanes et fiscalité | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| Marketing | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| Chaîne d'approvisionnement | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| Risque et conformité | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| Multiplateforme | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| Contenu et expérience | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| Abonnements et B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| Service client | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| Tests AB | 3 | ab_tests, ab_test_variants, ab_test_results |
| Gouvernance API | 2 | api_rate_limits, api_docs |
| Données de base | 3 | countries, currencies, exchange_rates |

### 1.3 Champs de suivi de plateforme

| Table | Champ | Description |
|----|------|------|
| orders | platform VARCHAR(16) | Plateforme de commande |
| payments | platform VARCHAR(16) | Plateforme de paiement |
| operation_logs | platform VARCHAR(16) | Plateforme d'opération |
| users | last_login_platform VARCHAR(16) | Dernière plateforme de connexion |
| search_logs | platform VARCHAR(16) | Plateforme de recherche |
| chat_messages | platform VARCHAR(16) | Source du message |

---

## 2. Conception API

Contrôle de version API, pipeline de middlewares, statistiques d'endpoints et spécification de réponse unifiée, voir [Documentation des interfaces API](api.md).

---

## 3. Conception de la sécurité

### 3.1 SecurityMiddleware encapsule les 31 détecteurs de security-php

| # | Type | Code d'erreur | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | Injection SQL | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | Traversée de chemin | 40004 | ✅ | ✅ |
| 5 | Corps trop volumineux | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | Téléversement de fichiers | 40009 | ✅ | ✅ |
| 8 | En-têtes de réponse de sécurité | — | ✅ | ✅ |
| 9 | Force brute | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | Méthode HTTP | 40012 | ✅ | ✅ |
| 13 | En-tête Host | 40013 | ✅ | — |
| 14 | Masquage des données sensibles | — | ✅ | ✅ |
| 15 | Liste blanche CORS | — | ⚠️ | ⚠️ |

### 3.2 Chiffrement à trois couches

| Couche | Technologie | Paquet |
|------|------|-----|
| Couche de transport | AES-256-CBC | erikwang2013/encryption |
| Couche de base de données | Trait Encryptable | erikwang2013/encryptable (Maize) |
| Obscurcissement d'ID | Hashids | erikwang2013/hashids |

---

## 4. Conception haute concurrence

### 4.1 Limitation de débit

Seau à jetons à fenêtre glissante (ZSET Redis, via la facade support\Redis) : 100 requêtes/60s par défaut, connexion 10/60s, inscription 5/300s, connexion sociale 5/300s, paiement 5/60s, commande 3/10s, recherche 10/1s

### 4.2 Utilisations de Redis

Redis est utilisé pour la limitation de débit par seau à jetons (facade `support\Redis`), les codes de vérification homme-machine et le stockage des sessions ; les données métier ne sont pas mises en cache au niveau application, elles sont lues directement depuis MySQL (séparation lecture/écriture + pools de connexions).

### 4.3 Pools de connexions

MySQL : 50 max/10min/2s de délai | séparation lecture/écriture : 30 max/5min (2 réplicas de lecture, sticky=true) | Redis : 30 max/5min



---

## 5. Internationalisation

- Interface : zh_CN, zh_HK, en, ja, ko
- Contenu : erik_product_translations, lignes indépendantes par locale
- Prix : erik_product_sku_prices, tarification indépendante par devise
- En-têtes : Accept-Language + API-Version

## 6. Documentation API

Générée automatiquement par hg/apidoc à partir des annotations des contrôleurs, voir [Documentation des interfaces API](api.md). Après démarrage, accéder à `/apidoc/`.

## 7. Tests

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

Voir : [Document de conception fonctionnelle](features.md) | [Document complet d'architecture](architecture-full.md) | [Document de déploiement](deployment.md)
