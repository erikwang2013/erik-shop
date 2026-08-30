# Erik Shop — Cross-Border E-Commerce Platform (Full)

> This document is a machine translation of the original Chinese documentation. See [中文原版](../../../README.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Version

> Lite (MIT open source): `lite` | Standard (commercial): `standard` | Full (commercial): `full`
>
> Commercial license inquiries: **erik@erik.xyz** | Version comparison: [docs/VERSIONS.md]

## Languages

| Language | Link |
|------|------|
| Chinese | [README.md](README.md) |
| English | [docs/i18n/en/README.md](../en/README.md) |
| 한국어 | [docs/i18n/ko/README.md](../ko/README.md) |
| Русский | [docs/i18n/ru/README.md](../ru/README.md) |
| Deutsch | [docs/i18n/de/README.md](../de/README.md) |
| Français | [docs/i18n/fr/README.md](../fr/README.md) |
| Español | [docs/i18n/es/README.md](../es/README.md) |
| Português | [docs/i18n/pt/README.md](../pt/README.md) |
| हिन्दी | [docs/i18n/hi/README.md](../hi/README.md) |
| العربية | [docs/i18n/ar/README.md](../ar/README.md) |
| বাংলা | [docs/i18n/bn/README.md](../bn/README.md) |
| Bahasa Indonesia | [docs/i18n/id/README.md](../id/README.md) |
| 日本語 | [docs/i18n/ja/README.md](../ja/README.md) |

## Project Introduction

A full-stack cross-border e-commerce platform built on the webman ecosystem, covering B2C/B2B scenarios and third-party seller onboarding.

### Technical Architecture

| Layer | Technology | Directory |
|------|------|------|
| Business API | webman + illuminate/database + erikwang2013/* | `service/` |
| Admin Console | webman-admin + LayUI + ECharts | `admin/` |
| Client | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |
| HarmonyOS Client | ArkTS + ArkUI (HarmonyOS NEXT) | `apps/harmonyos/` |

### Tech Stack

**Server:** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**Core packages:** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**Payments:** Stripe, PayPal (full); Klarna, Adyen (placeholder, `PaymentGateway::make` not implemented, see docs/PLAN.md)
**Clients:** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## Architecture Diagrams

> Full diagram set and large views: [docs/diagrams.md](../../diagrams.md)

### System Architecture Diagram

![System Architecture Diagram](./diagrams/01-system-architecture.svg)

### Request Processing Flow Diagram

![Request Processing Flow Diagram](./diagrams/02-request-processing-flow.svg)

### Feature Module Map

![Feature Module Map](./diagrams/03-feature-module-map.svg)
> The map covers 19 major feature modules (including Report Center and Platform Stats).

### Request Lifecycle Diagram

![Request Lifecycle Diagram](./diagrams/04-request-lifecycle.svg)

> More details in the [complete architecture diagram set](../../diagrams.md) (including order lifecycle, deployment architecture, security architecture, multi-currency settlement and 8 more diagrams)

### Security Architecture Diagram

![Security Architecture Diagram](./diagrams/07-security-architecture.svg)

**Resilience (circuit breaker):** a Redis-backed CircuitBreaker guards all outbound payment (Stripe/PayPal/Klarna/Adyen) and social-login calls — 5 consecutive failures open the circuit for 30s, then half-open probing auto-recovers. Business rejections (declined card, invalid token) are whitelisted and never count, so junk requests cannot knock out dependencies. If Redis itself fails, the breaker degrades to pass-through; while open, APIs return 503.

### Multi-Currency Settlement Flow Diagram

![Multi-Currency Settlement Flow Diagram](./diagrams/08-multi-currency-settlement.svg)

### Multi-Currency Settlement Notes

**Multi-currency pricing**: Product SKUs are priced per `currency_code`, and orders lock in the receiving currency at checkout (USD / EUR / GBP / CNY etc.).

**FX service**: The `erik_exchange_rates` table supports manual maintenance and automatic fetching via exchangerate-api, versioned by `effective_at` effective time; settlement uses the FX rate snapshot at payment time.

**Original-currency charging**: Stripe / PayPal charge in the order currency (Klarna/Adyen are placeholders, not integrated). Webhook signature verification confirms receipt before updating payment and order status.

**Settlement allocation**: After successful payment, `PlatformSettlements` are generated automatically (order total + platform commission + payment gateway fee, booked in the order currency); merchant settlements `MerchantSettlements` (order amount → commission rate → settlement amount), supplier settlements `SupplierSettlements`, and affiliate commission payouts `AffiliatePayouts` are four independent settlement lines, status 0 pending settlement / 1 settled.

**FX gains/losses**: `CurrencyExchangeGainsLosses` tracks the difference between the receiving currency and the settlement currency, comparing the FX rate at payment time with the rate at settlement time; positive = FX gain, negative = FX loss, supporting multi-currency reconciliation and auditing for cross-border e-commerce.

## Quick Start

### Method 1: Web One-Click Installation (Recommended)

```bash
# 1. Install admin dependencies
cd admin && composer install

# 2. Start the admin console
php start.php start -d

# 3. Open the installation wizard in your browser
# http://127.0.0.1:8788/app/admin/install/step1
# Enter database info → Set admin account → Done

# 4. Install dependencies and start the API
cd ../service && composer install && php start.php start -d
```

> The installation wizard automatically: creates the database → imports 117 tables → generates service/.env and admin/.env (with random keys) → creates the admin → reloads services

### Method 2: Manual Command-Line Installation

See [INSTALL.md](INSTALL.md)

### Docker Deployment

```bash
# Configure environment variables
cp .env.example .env  # or set DB_PASS / JWT_SECRET and other variables

# Start all services with one command
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

See the [deployment documentation](../../deployment.md)

## Usage

### Admin Panel

Open `http://127.0.0.1:8788/app/admin` in your browser to log in (create the admin account via the installation wizard on first use):

- **Dashboard**: GMV, order volume, user growth and other key metrics at a glance
- **Report Center**: sales summary, 30-day trend, TOP products, payment method / order status distribution
- Daily management of products, orders, marketing, supply chain and other modules

### API Calls

```bash
# Get product list
curl http://127.0.0.1:8787/api/products \
  -H "API-Version: 2026-05-20" \
  -H "X-Platform: web"

# Homepage platform stats (user/product/order/GMV totals and today's new counts)
curl http://127.0.0.1:8787/
```

> API versioning is done via the `API-Version` header (not in the URL); sensitive endpoints require `Authorization: Bearer <token>` (JWT).

### Clients

- **Flutter client**: `apps/flutter/` (iOS / Android / macOS / Windows / Linux)
- **HarmonyOS client**: `apps/harmonyos/` (HarmonyOS NEXT, ArkTS + ArkUI)

## Project Structure

```
shop-php/
  install.sql       # One-click installation SQL (117 tables), auto-imported by the Web installer
  service/          PHP business API (webman)        — 39 controllers + 111 models + 14 middleware
  admin/            Admin console (webman-admin)      — 83 controllers + 76 models + ECharts dashboard + Web installer
  apps/flutter/     Flutter client              — 11 pages + 5 languages + PC responsive
  apps/harmonyos/   HarmonyOS client                  — 9 pages + ArkTS
  docker/           Docker deployment                  — Nginx + PHP + MySQL + Redis + ES
  docs/             Design documentation
```

## Feature Coverage

| Dimension | Coverage |
|---------|------|
| **B2C Retail** | Multilingual products, per-currency pricing, SKUs, cart, orders, payment, refunds, returns |
| **B2B Wholesale** | Tiered pricing (MOQ), business verification (tax ID / business license), RFQ |
| **Multi-Merchant Onboarding** | Seller review, product review, commission splitting |
| **Cross-Border Compliance** | HS Code database, tariff rules, VAT/IOSS, country compliance labels (FDA/CE/RoHS) |
| **International Logistics** | Zone-based shipping rates, overseas warehouses (shipping + return warehouses), commercial invoice / packing list, HS declaration (planned) |
| **Payments** | Stripe/PayPal (full), Klarna/Adyen (placeholder), BNPL buy now pay later (placeholder), 3DS verification |
| **Marketing** | Coupons (zone + new/existing customers), banners (region visibility), flash sales, group buys, affiliate (links + commissions + payouts) |
| **Multi-Platform** | Amazon/eBay/Shopee/Lazada/Temu product listings + order aggregation |
| **Supply Chain** | Supplier rating, purchasing → QC → warehousing, inventory ledger (immutable), transfers |
| **Risk & Compliance** | Rule engine (side-channel scoring), KYC, GDPR/CCPA data requests, Cookie Consent |
| **Security** | 31 attack detection types (XSS/SQLi/XXE/SSRF/CRLF/path traversal/file upload/brute force/HTTP method/Host/CORS etc.) |
| **High Concurrency** | Token bucket rate limiting, DB read/write split, connection pool optimization, CDN edge caching + auto-purge (origin-pull) |
| **Reporting** | Admin report center: sales summary, 30-day trend, TOP products, payment method / order status distribution |
| **Platform Stats** | Service homepage stats: user/product/order/GMV totals and today's new counts |
| **Membership Growth** | Points rules, membership level benefits, gift cards, price drop alerts, subscription orders, AB testing |
| **Content Management** | CMS multilingual pages, FAQ, knowledge base, size charts, email templates, product Feed sync |
| **Customer Service** | WebSocket real-time IM, knowledge base (schema built) |
| **Infrastructure** | Snowflake distributed IDs, Hashids API obfuscation, JWT auth, AES encryption, GeoIP region detection |
| **Multi-Platform Clients** | Flutter (iOS/Android/macOS/Windows/Linux/iPadOS) + HarmonyOS (ArkTS) + Web Admin |
| **Platform Tracking** | 8 platform source detection (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + DB records |
| **Testing** | 22 tests / 45 assertions — ALL PASS (Security+Jwt+ApiResponse+Redis) |

## Core Design

- **Snowflake primary keys**: All 117 tables use bigint IDs generated by `erikwang2013/snowflake-php`
- **Hashids API**: Middleware encodes/decodes automatically, controllers are unaware
- **Encryptable encryption**: Sensitive fields (email/mobile/address etc.) are encrypted at the database level
- **JWT auth**: HS256 + access/refresh dual-token auto refresh
- **API versioning**: `API-Version` header routing, not in the URL
- **Poster verification**: Random human verification for sensitive operations (register/order/payment)

## Documentation

| Document | Description |
|------|------|
| [README-EN.md](../../README-EN.md) | English documentation |
| [INSTALL.md](INSTALL.md) | Installation guide (Web one-click + manual) |
| [AUDIT-REPORT.md](AUDIT-REPORT.md) | Installation system audit report |
| [Project Plan](../../PLAN.md) | Team-produced phased project plan (4-phase roadmap + key risks + Quick Wins) |
| [Team Research Details](../../PLAN-RESEARCH.md) | 7-domain status research: implemented / gaps / risks / suggestions |
| [Feature Design Document](../../features.md) | Complete feature matrix, business flows, state machines |
| [Architecture Diagram Set](../../diagrams.md) | Architecture, flow, feature, lifecycle, deployment, multi-currency settlement diagrams (8 Mermaid diagrams) |
| [Architecture Design Document](../../architecture-full.md) | System architecture, middleware pipeline, data architecture, security architecture, payment architecture, CDN edge layer |
| [Design Document](../../design.md) | Database schema design, API conventions, security design, internationalization |
| [Architecture Document](../../architecture.md) | Directory structure, model inheritance chain, key packages |
| [API Documentation](../../api.md) | 71 API endpoints (static documentation) |
| [hg/apidoc API docs](http://localhost:8787/apidoc/) | Auto-generated by hg/apidoc (6 groups: auth/product/trading/logistics customs/user marketing/operations) |
| [Deployment Documentation](../../deployment.md) | Docker/manual deployment, environment variables (incl. CDN_*), operations commands, CDN edge caching |


## Open Source Is Not Easy, Support Welcome

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](../../weixinpay.png "WeChat") | ![Alipay](../../alipay.png "Alipay") |

### Global Bank Transfer (ZA Bank)

**Payee Information**

- Payee Name: WANG KEXUN
- Payee Account Number: 881015918251

**Receiving Bank**

- SWIFT Code: AABLHKHHXXX
- Bank Name: ZA Bank Limited
- Bank Code: 387
- Bank Address: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Correspondent Bank for Cross-Border Remittance (if needed)**

> This is the correspondent (intermediary) bank information for cross-border remittance, not the receiving bank's information. Please check with your remitting bank whether this is required.

- **For HKD, CNY and USD remittances** (correspondent bank Citibank):
  - Bank Name: Citibank N.A. Hong Kong
  - SWIFT Code: CITIHKHXXXX
  - Bank Code: 006
  - Branch Name: Hong Kong Branch
  - Branch Code: 391
  - Bank Address: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **For other currencies** (correspondent bank BNY Mellon):
  - Bank Name: THE BANK OF NEW YORK MELLON
  - SWIFT Code: IRVTUS3NXXX
  - Bank Address: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### Crypto Donation

If this project helps you, scan the QR code to donate, thank you!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---


## Testing

```bash
make test             # Recommended
cd service && php vendor/bin/phpunit tests/   # Native command
# 22 tests, 45 assertions — ALL PASS

# Dependency security audit (1 known low-severity CVE: CVE-2025-45769 firebase/php-jwt <7.0.0,
# constrained by jwt-webman ^6.0 and cannot be upgraded; HS256 symmetric signing usage unaffected)
composer audit
```

## Development Tools

```bash
make help             # View all commands
make lint             # PHP syntax check
make check            # phpstan static analysis
make fix              # php-cs-fixer code formatting
```

CI/CD: `.github/workflows/ci.yml` — PHP 8.3/8.4 matrix tests

## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
