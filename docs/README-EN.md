# Erik Shop — Cross-Border E-Commerce Platform (Full Edition)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

<p align="center">
  <img src="pet.svg" width="180" alt="Snowy — Erik Shop project pet">
</p>

## Editions

> Lite (Personal Use Only): `lite` | Standard (Commercial): `standard` | Full (Commercial): `full`
>
> Commercial license inquiries: **erik@erik.xyz** | Edition comparison: [VERSIONS.md](VERSIONS.md)

## Overview

A full-stack cross-border e-commerce platform built on the webman ecosystem, covering B2C, B2B, and multi-vendor marketplace scenarios.

The project mascot is **Snowy** — a globe sprite wearing a snowflake crown and meridian lines, hugging a cross-border parcel. Full design notes: [Project Pet — Snowy](../README.md#项目宠物--雪球-snowy).

### Architecture

| Layer | Technology | Directory |
|-------|-----------|-----------|
| Business API | webman + illuminate/database + erikwang2013/* | `service/` |
| Admin Panel | webman-admin + LayUI + ECharts | `admin/` |
| Mobile/Desktop | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |

### Tech Stack

**Server:** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**Core packages:** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**Payments:** Stripe, PayPal, Klarna, Adyen
**Clients:** Flutter 3.x (Riverpod + GoRouter + Dio)

## Architecture Diagrams

> Full diagram collection: [docs/diagrams.md](diagrams.md)

### System Architecture

![System Architecture](01-system-architecture.svg)

### Request Processing Flow

![Request Processing Flow](02-request-processing-flow.svg)

### Feature Module Map

![Feature Module Map](03-feature-module-map.svg)

> The map covers 19 major feature modules (including Report Center and Platform Stats).

### Request Lifecycle

![Request Lifecycle](04-request-lifecycle.svg)

### Order Lifecycle

![Order Lifecycle](05-order-lifecycle.svg)

> See [full diagram collection](diagrams.md) for 8 diagrams including deployment, security architecture, and multi-currency settlement.

### Security Architecture

![Security Architecture](07-security-architecture.svg)

**Resilience (circuit breaker):** a Redis-backed `CircuitBreaker` guards all outbound payment (Stripe/PayPal/Klarna/Adyen) and social-login calls — 5 consecutive failures open the circuit for 30s, then half-open probing auto-recovers. Business rejections (declined card, invalid token) are whitelisted and never count, so junk requests cannot knock out dependencies. If Redis itself fails, the breaker degrades to pass-through; while open, APIs return 503.

### Multi-Currency Settlement Flow

![Multi-Currency Settlement Flow](08-multi-currency-settlement.svg)

### Multi-Currency Settlement Notes

**Multi-currency pricing**: product SKUs are priced per `currency_code`; orders lock in the settlement currency (USD / EUR / GBP / CNY etc.) at checkout.

**Exchange rate service**: the `erik_exchange_rates` table supports manual maintenance and automatic pulls via exchangerate-api, versioned by the `effective_at` timestamp; settlement uses the exchange-rate snapshot at payment time.

**Native-currency capture**: Stripe / PayPal / Klarna / Adyen charge in the order currency; the payment webhook is signature-verified before payment and order status are updated.

**Settlement splitting**: on successful payment, `PlatformSettlements` is auto-generated (order total + platform commission + gateway fee, booked in the order currency). Three independent settlement lines — merchant settlements `MerchantSettlements` (order amount → commission rate → settlement amount), supplier settlements `SupplierSettlements`, and platform settlement `PlatformSettlements` — each with status 0 pending / 1 settled.

**FX gain/loss**: `CurrencyExchangeGainsLosses` tracks the difference between the received and settled currencies, comparing the exchange rate at payment vs. at settlement; positive = FX gain, negative = FX loss, supporting multi-currency reconciliation and auditing for cross-border e-commerce.

## Quick Start

### Method 1: Web Installer (Recommended)

```bash
# 1. Install admin dependencies
cd admin && composer install

# 2. Start the admin panel
php start.php start -d

# 3. Open the installer in your browser
# http://127.0.0.1:8788/app/admin/install/step1
# Fill in database credentials → set admin account → done

# 4. Install and start the API service
cd ../service && composer install && php start.php start -d
```

> The web installer automatically: creates the database → imports 98 tables → generates `service/.env` and `admin/.env` (with random crypto keys) → creates the admin account → reloads services

### Method 2: Manual Installation

See [INSTALL.md](INSTALL.md) for detailed manual setup instructions.

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

### Docker Deployment

```bash
# Configure environment
cp .env.example .env  # set DB_PASS / JWT_SECRET etc.

# Start all services
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

## Project Structure

```
shop-php/
  install.sql       # One-click install SQL (98 tables), auto-imported by web installer
  service/          PHP API (webman)            — 40 controllers + 90 models + 14 middleware + 14 processes
  admin/            Admin panel (webman-admin)   — 71 controllers + 66 models + ECharts dashboard + web installer
  apps/flutter/     Flutter client              — 6 feature modules + 12 pages + 5 languages + PC adaptive layout
  docker/           Docker deployment           — Nginx + PHP + MySQL + Redis + ES
  scripts/          Tooling                     — e2e / smoke_controllers / check_install_tables
  docs/             Design documentation + diagram set
    pet.svg           Project pet "Snowy" (canonical source)
    01-08-*.svg       8 diagrams: system architecture / request flow / feature map / request lifecycle /
                      order lifecycle / deployment / security / multi-currency settlement (*.mmd = Mermaid source)
    README-EN.md      English documentation (this file)
```

### Runtime Directories

| Path | Contents |
|------|----------|
| `service/public/`, `admin/public/` | Static asset roots (incl. `pet.svg`, `favicon.ico`) |
| `service/app/view/`, `admin/plugin/admin/app/view/` | View templates (admin login / dashboard / installer) |
| `service/app/process/` | Custom processes and cron jobs (exchange rates, shipment tracking, settlement, reconciliation, feed sync) |
| `service/database/seeders/` | Seed data (countries / HS codes / exchange rates / shipping zones / compliance / size charts / risk rules) |
| `service/runtime/`, `admin/runtime/` | Runtime cache and logs (not version-controlled) |

## Feature Coverage

| Dimension | Coverage |
|-----------|----------|
| **B2C Retail** | Multi-language products, multi-currency pricing, SKU, cart, orders, payments, refunds, returns |
| **B2B Wholesale** | Tiered pricing (MOQ), business verification (tax ID/license), RFQ quotes |
| **Multi-Vendor Marketplace** | Seller onboarding + product review + revenue splitting |
| **Cross-Border Compliance** | HS Code library, tariff rules, VAT/IOSS, compliance labels (FDA/CE/RoHS) |
| **International Logistics** | Zone-based shipping rates, overseas warehouses, HS customs declaration, commercial invoices |
| **Payments** | Stripe/PayPal/Klarna/Adyen, BNPL, 3DS verification |
| **Marketing** | Coupons (zone-limited), banners (region-visible) |
| **Supply Chain** | Supplier ratings, purchase orders, quality inspection, inventory ledger (immutable), transfers |
| **Risk & Compliance** | Rule engine (scoring pipeline), KYC verification, GDPR/CCPA data requests, Cookie consent |
| **Security** | 31 attack detection types (XSS/SQLi/XXE/SSRF/CRLF/path traversal/file upload/brute force) |
| **Performance** | Token bucket rate limiting, DB read/write split, connection pooling, CDN edge caching + auto-purge (origin-pull) |
| **Reporting** | Admin report center: sales summary, 30-day trend, TOP products, payment method / order status distribution |
| **Platform Stats** | Service homepage stats: user/product/order/GMV totals and today's new counts |
| **Membership** | Points ledger, membership tiers + benefits, price alerts, subscription auto-buy |
| **Content** | CMS multi-language pages, FAQ, size charts, product feed sync |
| **Customer Service** | FAQ support |
| **Infrastructure** | Snowflake distributed IDs, Hashids obfuscation, JWT auth, AES encryption, GeoIP detection |
| **Multi-Platform Tracking** | 7 source platform identification (iOS/iPadOS/macOS/Windows/Linux/Android/Web) + DB logging |
| **Testing** | See `.github/workflows/ci.yml` |

## Core Design

- **Snowflake PKs**: All 98 tables use `erikwang2013/snowflake-php` generated bigint IDs
- **Hashids Interface**: Middleware auto-encodes/decodes — transparent to controllers
- **Encryptable Fields**: email/mobile/address and other PII encrypted at database level
- **JWT Auth**: HS256 + access/refresh dual-token auto-refresh
- **API Versioning**: `API-Version` header routing, not in URL paths
- **Poster Verification**: Random human-verification (slide/puzzle/click) for sensitive operations

## Documentation

| Document | Description |
|----------|-------------|
| [中文 README](../README.md) | Chinese documentation |
| [INSTALL.md](INSTALL.md) | Installation guide (web installer + manual) |
| [AUDIT-REPORT.md](AUDIT-REPORT.md) | Installation system audit report |
| [Features](features.md) | Complete feature matrix, workflows, API endpoints, state machines |
| [Diagrams](diagrams.md) | Architecture, flowchart, feature map, lifecycle, deployment, multi-currency settlement (8 diagrams, Mermaid sources included) |
| [Project Pet — Snowy](pet.svg) | Mascot SVG source (design rationale, integration points, reuse & regeneration in the [Chinese README](../README.md#项目宠物--雪球-snowy)) |
| [Architecture](architecture-full.md) | System architecture diagrams, middleware pipeline, data/security/payment architecture, CDN edge layer |
| [Design](design.md) | Database schema, API specification, security, i18n |
| [API Reference](api.md) | 71 API endpoints (static documentation) |
| [API Docs (live)](http://localhost:8787/apidoc/) | Auto-generated by hg/apidoc (6 groups: auth/products/trade/shipping-customs/users-marketing/ops) |
| [Deployment](deployment.md) | Docker/manual deployment, environment variables (incl. CDN_*), operations, CDN edge caching |

## Running Tests

```bash
cd service && DB_USER=qa DB_PASS=qa_pass vendor/bin/phpunit  # unit/integration (211 tests / 1000 assertions)
cd admin  && DB_USER=qa DB_PASS=qa_pass vendor/bin/phpunit  # admin (2 tests / 7 assertions)
cd service/tests/api && php run_all.php                      # API automation (4 suites, 213 assertions)
cd scripts/e2e && SERVICE_BASE=http://127.0.0.1:8787 node ui-e2e.js  # UI E2E (admin + service pages)

# Dependency security audit (1 known low-severity CVE: CVE-2025-45769
# firebase/php-jwt <7.0.0, constrained by jwt-webman ^6.0; HS256
# symmetric-signature usage is not on the affected path)
composer audit
```

Latest full QA report: [test-reports/QA-REPORT-2026-08-27.md](test-reports/QA-REPORT-2026-08-27.md)
(screenshots in `test-reports/screenshots/`, E2E detail in `scripts/e2e/results.json`).

**Known gaps fixed (2026-08-28)**: 19 admin page views + ShopExport export page implemented;
E2E rerun 47 PASS / 0 FAIL / 1 WARN (100%). See report §8.

## Open Source vs Commercial Edition

`shop-php` is the open-source base edition, ready to run as-is. The commercial edition is available separately on the same code baseline, adding marketing growth, multi-platform marketplace, content/support and a HarmonyOS client. Both share the same order, payment, inventory, cross-border compliance and security foundation.

| Dimension | Open source | Commercial |
|---|---:|---:|
| PHP source files | 484 | 536 |
| Controllers | 111 | 128 |
| Data models | 156 | 189 |
| Data tables | 98 | 119 |
| Route declarations | 93 | 106 |
| Clients | Flutter + Web admin | Flutter + HarmonyOS ArkTS + Web admin |
| Platform detection | 7 platforms | 8 platforms (incl. HarmonyOS) |

**Commercial-only capabilities**

- Marketing: flash sale (with activity SKUs), group buy, affiliate (links/commissions/payouts), gift cards, point rules, A/B testing
- Multi-platform: Amazon / eBay / Shopee / Lazada / Temu listings and order aggregation
- Content & support: knowledge base, email templates, WebSocket real-time IM
- Membership: point rules, gift cards, A/B testing
- Client: HarmonyOS ArkTS app
- Data model: 21 commercial-only tables (A/B testing 3, affiliate 3, chat 2, flash sale 2, knowledge base 2, marketplace sync 4, gift card/group buy/point rules/shipping insurance/email templates 1 each)

**Retained in the open-source edition:** B2C/B2B retail and wholesale, multi-vendor onboarding, cross-border compliance, international logistics, Stripe/PayPal payments (Klarna/Adyen/BNPL placeholders), coupons, supply chain, immutable inventory ledger, risk control/KYC, 31 attack detectors, rate limiting and read/write splitting, CDN origin-pull and edge cache, reports, membership benefits, CMS/FAQ, multi-language/multi-currency, Flutter clients and the Web admin.

**Licensing:** the open-source edition is for personal study and research only. Commercial deployment, paid services, SaaS operation and resale require prior written authorization. For commercial licensing and delivery terms, contact **erik@erik.xyz**.

## 项目授权 / Project License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

- 本项目仅供个人学习、研究使用。
- 未经作者书面授权，禁止任何形式的商业使用（包括但不限于商业部署、付费服务、二次销售、SaaS 运营及以本项目为基础提供商业服务）。
- 商业授权请联系 **erik@erik.xyz**。
- 未经授权的商业使用，作者将在全球范围内依法追究法律责任，并保留终身追责的权利。
- 第三方组件仍遵循其各自原有的许可证；视觉资产适用仓库内的 ASSETS-LICENSE.md（如适用）。

**English:** This project is provided for personal study and research only. Commercial use of any kind requires prior written authorization from the author (erik@erik.xyz). Unauthorized commercial use will be pursued for legal liability worldwide, and the author reserves the right to pursue such liability for life. Third-party components remain under their original licenses; visual assets are governed by ASSETS-LICENSE.md where applicable.
