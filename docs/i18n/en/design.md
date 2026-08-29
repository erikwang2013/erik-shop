# Cross-Border E-Commerce Platform — Design Document

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Database Design

### 1.1 Naming Conventions

- Table prefix: `erik_`
- Primary key: `id BIGINT UNSIGNED NOT NULL` (snowflake-generated, non-auto-increment)
- Timestamps: `created_at`, `updated_at`, `deleted_at` (soft delete)
- Engine: InnoDB, Charset: utf8mb4_unicode_ci

### 1.2 Module Breakdown (110 tables)

| Module | Tables | Core Tables |
|------|------|--------|
| Users & Accounts | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| Products & Categories | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| Transactions | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| Payments & Funds | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| Logistics | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| Customs & Tax | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| Marketing | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| Supply Chain | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| Risk & Compliance | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| Multi-Platform | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| Content & Experience | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| Subscriptions & B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| Customer Service | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| AB Testing | 3 | ab_tests, ab_test_variants, ab_test_results |
| API Governance | 2 | api_rate_limits, api_docs |
| Base Data | 3 | countries, currencies, exchange_rates |

### 1.3 Platform Tracking Fields

| Table | Field | Description |
|----|------|------|
| orders | platform VARCHAR(16) | Order placement platform |
| payments | platform VARCHAR(16) | Payment platform |
| operation_logs | platform VARCHAR(16) | Operation platform |
| users | last_login_platform VARCHAR(16) | Last login platform |
| search_logs | platform VARCHAR(16) | Search platform |
| chat_messages | platform VARCHAR(16) | Message source |

---

## 2. API Design

API versioning, middleware pipeline, endpoint statistics, and unified response conventions are covered in [API Documentation](api.md).

---

## 3. Security Design

### 3.1 SecurityMiddleware wraps 31 detectors from security-php

| # | Type | Error Code | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | SQL injection | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | Path traversal | 40004 | ✅ | ✅ |
| 5 | Body too large | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | File upload | 40009 | ✅ | ✅ |
| 8 | Security response headers | — | ✅ | ✅ |
| 9 | Brute force | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | HTTP method | 40012 | ✅ | ✅ |
| 13 | Host header | 40013 | ✅ | — |
| 14 | Sensitive data masking | — | ✅ | ✅ |
| 15 | CORS whitelist | — | ⚠️ | ⚠️ |

### 3.2 Three-Layer Encryption

| Layer | Technology | Package |
|------|------|-----|
| Transport layer | AES-256-CBC | erikwang2013/encryption |
| Database layer | Encryptable trait | erikwang2013/encryptable (Maize) |
| ID obfuscation | Hashids | erikwang2013/hashids |

---

## 4. High-Concurrency Design

### 4.1 Rate Limiting

Token bucket sliding window (Redis ZSET, via the `support\Redis` facade): default 60s/100 requests, login 10/60s, register 5/300s, social login 5/300s, payment 5/60s, order placement 3/10s, search 10/1s

### 4.2 Circuit Breaker and Degradation

Redis circuit breaker (`app\common\CircuitBreaker`): all external API calls such as payment gateways/social login go through `CircuitBreaker::call()` — 5 consecutive failures open the circuit for 30s; once the TTL expires, the next request automatically probes half-open and resets on success. A business-rejection whitelist (invalid card/invalid token) never counts toward failures, preventing attackers from taking down dependencies with junk requests; when Redis is unavailable it auto-degrades to pass-through. While the circuit is open, APIs return 503 "Service Unavailable".

### 4.3 Redis Usage

Redis is used for rate limiting token buckets (`support\Redis` facade), human verification codes, Session storage, and CDN global on/off propagation to the service (shared key prefix `shop:`, 60s TTL); business data is not cached at the application layer, it reads MySQL directly (read/write split + connection pool). Static uploads (products/banners) are served through the CDN edge (7-day immutable cache, auto-purge on CRUD).

### 4.4 Connection Pools

MySQL: 50max/10min/2s timeout | Read/write split: 30max/5min (2 read replicas, sticky=true) | Redis: 30max/5min



---

## 5. Internationalization

- Interface: zh_CN, zh_HK, en, ja, ko
- Content: erik_product_translations with independent rows per locale
- Pricing: erik_product_sku_prices with independent pricing per currency
- Headers: Accept-Language + API-Version

## 6. API Documentation

Auto-generated from controller annotations using hg/apidoc, see [API Documentation](api.md). Access `/apidoc/` after startup.

## 7. Testing

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

See also: [Feature Design Document](features.md) | [Full Architecture Document](architecture-full.md) | [Deployment Document](deployment.md)
