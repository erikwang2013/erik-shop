# Erik Shop — Cross-Border E-Commerce Platform
A full-stack cross-border e-commerce platform built on the webman ecosystem, covering B2C/B2B scenarios and third-party seller onboarding.

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Version Overview

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| **Positioning** | Individual developers / small e-commerce | Growing cross-border merchants | Enterprise full-stack platform |
| **License** | MIT open source | Commercial license | Commercial license |
| **How to Get** | Public GitHub download | Contact erik@erik.xyz | Contact erik@erik.xyz |
| **Branch** | `lite` | `standard` | `full` |
| **Current** | — | — | ✅ |

---

## 2026-08-07 Fix Log

| # | Issue | Severity | Fix |
|---|------|--------|------|
| 1 | API response encryption not wired into middleware | Medium | Created EncryptionMiddleware (X-Encrypt-Response header-driven), registered as level 10 of the service pipeline |
| 2 | Class name Encryption / filename EncryptionHelper.php mismatch | Medium | Renamed to Encryption.php, fixed PSR-4 autoloading |
| 3 | JWT_SECRET_KEY empty | Low | Generated 32-byte key, set both JWT_SECRET and JWT_SECRET_KEY |
| 4 | config/middleware.php as indexed array caused "Bad middleware config" crash on all workers | Critical | Changed to `'' => [...]` standard structure (webman requires appName => list) |
| 5 | security-php plugin config missing enable key, silently skipped by Config::loadFromDir | Critical | Added `'enable' => true` to the plugin app.php in service/admin |
| 6 | config/bootstrap.php referenced non-existent support\bootstrap\Db/Redis | Critical | Removed; Eloquent initialization now handled by support/bootstrap.php requiring vendor/webman/database Db.php |
| 7 | Global redis() function does not exist (webman 2.x has no such function), rate limiting/risk control silently disabled | High | Created support\Redis facade (illuminate/redis + phpredis), registered redis() helper function in app/functions.php |
| 8 | RedisManager constructor parameters missing (needs 3: app container/driver/config) | High | Passed stdClass container placeholder + phpredis driver + connection config |
| 9 | Models referenced non-existent Erik\Encryptable\Encryptable trait (package has Maize\Encryptable namespace CastsAttributes) | Critical | Created service/Erik/Encryptable/Encryptable.php classic trait compatibility layer (reusing the package's Encryption::php underneath) |
| 10 | Composer plugin Installer.php top-level function duplicate declaration fatal | Medium | function_exists idempotency guard (fixed in both service/admin vendors) |
| 11 | HashidsEncode getHeader() returns string causing implode error | High | Cast to (array) |
| 12 | docker-compose/.env.example hardcoded real JWT/encryption keys | Critical | Replaced with change_me placeholders, installer generates random keys |
| 13 | Order creation without transaction, non-atomic stock deduction (concurrent overselling) | Critical | Db::transaction + conditional decrement atomic deduction |
| 14 | Coupon claim concurrent oversupply/over-claiming | High | Transaction + row lock lockForUpdate + received_qty atomic latch |
| 15 | PayPal Webhook signature fields always empty (verify-webhook-signature inevitably fails) | High | Five signature fields passed through from request headers |
| 16 | Installer SQL injection (database name/password concatenation) | High | quote + backtick escaping + var_export config writing |
| 17 | Silent fallback when encryption/hash keys missing | High | Encryption/HashidsHelper throw exceptions on empty or invalid-length values |
| 18 | Order export fixed filename concurrent overwrite | Medium | uniqid filename + shutdown cleanup + try/catch |
| 19 | Hashids decode not written back to request parameters (route params/GET/POST) | High | setParams/setGet/setPost write back |
| 20 | composer.lock gitignored (non-reproducible builds) | Medium | Removed from ignore list, tracked in version control |
| 21 | Containers without health checks or startup dependencies | Medium | healthcheck + depends_on condition on all services |
| 22 | admin Dockerfile not runnable | High | Added COPY + composer install + EXPOSE + CMD |
| 23 | Flutter compile errors (intl conflict/constructor generics/extra parentheses) + test pending Timer | High | intl ^0.20.2, static factories, pump advances clock |
| 24 | HarmonyOS 27 ArkTS compile errors preventing build | High | Explicit interfaces, reserved word renaming, single-root build, @kit imports, hvigor config |

---

## Feature Comparison

> Note: ◐ = schema built, business logic pending (currently only tables and models exist, no API/business code or only partial implementation)

### User System

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Email registration/login (JWT) | ✅ | ✅ | ✅ |
| Social login (Google/Apple/Facebook) | — | ✅ | ✅ |
| Address management | ✅ | ✅ | ✅ |
| Membership levels + points | — | — | ◐ |
| Gift cards | — | — | ✅ |
| KYC identity verification | — | — | ✅ |

### Product System

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Category management (tree) | ✅ | ✅ | ✅ |
| SKU + attributes | ✅ | ✅ | ✅ |
| Product images | ✅ | ✅ | ✅ |
| Multilingual content | — | ✅ | ✅ |
| Per-currency independent pricing | — | ✅ | ✅ |
| Product reviews | ✅ | ✅ | ✅ |
| Compliance labels (FDA/CE/RoHS) | — | ✅ | ✅ |
| ES multilingual search | — | ✅ | ✅ |
| Product feed sync (Google/Meta) | — | — | ✅ |
| Size charts | — | — | ✅ |

### Transaction System

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Cart | ✅ | ✅ | ✅ |
| Order management | ✅ | ✅ | ✅ |
| Payment (Stripe) | ✅ | ✅ | ✅ |
| Payment (PayPal) | ✅ | ✅ | ✅ |
| Payment (Klarna/Adyen) | — | Placeholder | Placeholder |
| BNPL buy now pay later | — | Placeholder | Placeholder |
| Refunds | ✅ | ✅ | ✅ |
| Return management | — | ✅ | ✅ |
| Commercial invoice/packing list | — | ✅ | ✅ |
| Shipping insurance | — | — | ◐ |

### Cross-Border Logistics

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| International carrier management | — | ✅ | ✅ |
| Shipping zones + tiered rates | — | ✅ | ✅ |
| Overseas warehouses (shipping + returns) | — | ✅ | ✅ |
| HS declaration | — | Planned | Planned |
| Shipment tracking | — | ✅ | ✅ |
| Multi-warehouse inventory management | — | — | ✅ |

### Customs & Tax

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| HS Code database | — | ✅ | ✅ |
| Tariff rule configuration | — | ✅ | ✅ |
| VAT/IOSS settings | — | ✅ | ✅ |
| Country compliance restrictions | — | ✅ | ✅ |
| Price display compliance (incl./excl. tax) | — | ✅ | ✅ |

### Marketing Tools

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Coupons | ✅ | ✅ | ✅ |
| Banners | ✅ | ✅ | ✅ |
| Flash sales | — | ✅ | ✅ |
| Group buys | — | ✅ | ✅ |
| Affiliate (links + commissions + payouts) | — | ✅ | ✅ |
| Regional promotions | — | ✅ | ✅ |

### Supply Chain

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Supplier management | — | — | ✅ |
| Purchase orders | — | — | ◐ |
| Quality inspection (inbound/outbound gate) | — | — | ◐ |
| Inventory ledger (immutable) | — | — | ✅ |
| Inventory transfers | — | — | ◐ |

### Platform Expansion

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Multi-store management | — | — | ✅ |
| Multi-merchant onboarding (third-party sellers) | — | — | ✅ |
| Amazon/eBay/Shopee listings | — | — | ✅ |
| Multi-platform order aggregation | — | — | ✅ |
| B2B wholesale (tiered pricing/RFQ) | — | — | ✅ |

### Risk & Compliance

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Basic attack detection (XSS/SQLi) | ✅ | ✅ | ✅ |
| Extended attack detection (XXE/SSRF etc.) | — | — | ✅ |
| PosterVerify human verification | — | ✅ | ✅ |
| Risk rule engine | — | — | ✅ |
| GDPR/CCPA data requests | — | — | ✅ |
| Cookie Consent management | — | — | ✅ |
| Platform source tracking | — | ✅ | ✅ |
| Platform source tracking (8 platforms) | — | ✅ | ✅ |

### High Concurrency

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| DB connection pool | ✅ | ✅ | ✅ |
| Token bucket rate limiting | — | — | ✅ |
| DB read/write split | — | — | ✅ |
| Cron scheduled tasks (11) | — | — | ✅ |

### Content & Growth

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| System notifications | ✅ | ✅ | ✅ |
| Email templates | — | — | ✅ |
| CMS multilingual pages | — | — | ✅ |
| FAQ + knowledge base | — | — | ◐ |
| Subscription orders | — | — | ✅ |
| AB testing | — | — | ◐ |
| Real-time customer service (WebSocket IM) | — | — | ✅ |

### Clients

| Feature | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| Internationalization (5-language translations) | ✅ | ✅ | ✅ |
| API documentation (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Admin ECharts dashboard | ✅ | ✅ | ✅ |
| Admin Excel/PDF export | ✅ | ✅ | ✅ |
| Multilingual interface (5 languages) | ✅ | ✅ | ✅ |

---

## Design Comparison

### Database

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Tables | **23** | **62** | **110** |
| User-related | 3 | 5 | 7 |
| Product-related | 6 | 15 | 19 |
| Transaction-related | 6 | 9 | 9 |
| Logistics-related | 0 | 7 | 9 |
| Customs-related | 0 | 5 | 5 |
| Marketing-related | 4 | 8 | 8 |
| Supply chain | 0 | 0 | 5 |
| Risk & compliance | 0 | 0 | 5 |
| Multi-platform | 0 | 0 | 9 |
| Content & growth | 0 | 1 | 14 |
| Customer service/AB/API | 0 | 0 | 5 |

### Middleware Pipeline

```
Lite:      Cors → Security (4 types) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security (4 types) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security (31 types) → RateLimit (token bucket) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption (interface encryption)
```

### Code Scale

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Service models | 26 | 55 | 111 |
| Service controllers | 15 | 24 | 39 |
| Service middleware | 7 | 9+2 | 12+2 |
| Service utility classes | 5 | 5 | 15 |
| Admin models | 15 | 34 | 76 |
| Admin controllers | 15 | 27 | 82 |
| Flutter pages | 11 | 11 | 11 |
| HarmonyOS | — | — | 9 pages |
| PHPUnit tests | 22 | 22 | 54 |

### Tech Stack

| Component | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| snowflake-php | ✅ | ✅ | ✅ |
| hashids | ✅ | ✅ | ✅ |
| jwt-webman | ✅ | ✅ | ✅ |
| encryption | ✅ | ✅ | ✅ |
| encryptable | ✅ | ✅ | ✅ |
| season | — | ✅ | ✅ |
| poster-php | — | ✅ | ✅ |
| webman-scout | — | ✅ | ✅ |
| phpspreadsheet | ✅ | ✅ | ✅ |
| dompdf | ✅ | ✅ | ✅ |
| stripe/stripe-php | — | ✅ | ✅ |
| maxmind/GeoIP2 | — | ✅ | ✅ |
| guzzlehttp/guzzle | — | ✅ | ✅ |

---

## Upgrade Path

```
Lite (open source) ──→ Standard (commercial) ──→ Full (commercial)

Upgrade method:
  1. Contact erik@erik.xyz to obtain the code for the corresponding version
  2. Import the incremental schema (lite→standard adds ~40 tables, standard→Full adds ~48 tables)
  3. Copy the controllers/models/middleware of the corresponding version
  4. composer require the new dependency packages
```

---

## How to Get

| Version | Method |
|------|------|
| **Lite** | Open source on GitHub [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php) `lite` branch |
| **Standard** | Commercial license — contact **erik@erik.xyz** |
| **Full** | Commercial license — contact **erik@erik.xyz** |

Commercial license includes: full source code / deployment support / priority updates / technical consulting
