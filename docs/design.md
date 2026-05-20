# 跨境电商平台 — 设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 项目概述

基于 webman 全家桶的全栈跨境电商平台。三个子项目：service (API)、admin (管理后台)、apps (Flutter+HarmonyOS客户端)。

## 2. 数据库设计

### 2.1 命名规范

- 表前缀: `erik_`
- 主键: `id BIGINT UNSIGNED NOT NULL` (snowflake生成，非自增)
- 时间戳: `created_at`, `updated_at`, `deleted_at` (软删除)
- 引擎: InnoDB，字符集: utf8mb4_unicode_ci

### 2.2 模块划分 (110表)

| 模块 | 表数 | 核心表 |
|------|------|--------|
| 用户与账户 | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| 商品与分类 | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| 交易 | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| 支付与资金 | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| 物流 | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| 海关与税务 | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| 营销 | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| 供应链 | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| 风控与合规 | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| 多平台 | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| 内容与体验 | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| 订阅与B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| 客服 | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| AB测试 | 3 | ab_tests, ab_test_variants, ab_test_results |
| API治理 | 2 | api_rate_limits, api_docs |
| 基础数据 | 3 | countries, currencies, exchange_rates |

### 2.3 核心关系

```
Users
 ├── hasMany → Addresses, SocialAccounts, Wishlists, Orders, Carts, Reviews
 ├── hasMany → Notifications, PriceAlerts, SearchLogs, Subscriptions, PointLogs
 └── hasOne  → Kyc

Products
 ├── belongsTo → Category
 ├── hasMany → Skus, Images, Translations, Reviews, Compliance, HsCodes
 └── Searchable (Elasticsearch)

Orders
 ├── belongsTo → User
 ├── hasMany → Items, Logs, Payments, Refunds, Returns, Documents, Shipments
 └── hasMany → PlatformSettlements, MerchantSettlements

Skus
 ├── belongsTo → Product
 └── hasMany → Prices (per currency)
```

## 3. API设计

### 3.1 版本控制

版本通过 `API-Version: 2026-05-20` header传递，不在URL中。
VersionRoute中间件映射到 `app\controller\v1`/`v2` 命名空间。

### 3.2 中间件管道

```
请求 → Cors → GeoIpMiddleware → LocaleMiddleware → HashidsDecode
     → VersionRoute → PosterVerify(路由级) → JwtAuth(路由级) → HashidsEncode
     → 控制器 → 响应
```

### 3.3 端点统计

- 公开接口: 25个 (认证/商品/分类/内容/搜索/服务)
- 认证接口: 40+个 (用户/购物车/订单/支付/退货/评价/营销)
- Webhook: 1个 (支付回调)

### 3.4 统一响应格式

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

## 4. 安全设计

| 层级 | 措施 |
|------|------|
| 传输层 | HTTPS + API敏感字段AES加密 |
| 接口层 | JWT认证 + Hashids ID混淆 + Poster人机验证 |
| 数据库层 | Encryptable字段加密(email/mobile/address) + 密码bcrypt |
| 业务层 | 风控旁路打分 + 3DS验证 + GDPR数据删除 |

## 5. 国际化

- 界面: zh_CN, zh_HK, en, ja, ko (Flutter ARB + Admin trans())
- 内容: erik_product_translations 按locale独立行存储
- 价格: erik_product_sku_prices 按币种独立定价
- Header: Accept-Language 控制界面语言，API-Version 控制接口版本

## 6. 部署架构

```
                    ┌── Nginx ──┐
                    │   :80     │
                    └──┬────┬───┘
                       │    │
              ┌────────┘    └────────┐
              ▼                      ▼
        ┌── service ──┐      ┌── admin ──┐
        │   :8787     │      │   :8787   │
        └──┬──┬──┬───┘      └──┬──┬──┬──┘
           │  │  │             │  │  │
    ┌──────┘  │  └──────┐      │  │  └──────┐
    ▼         ▼         ▼      ▼  ▼         ▼
  MySQL    Redis      ES    MySQL Redis    ES
  :3306    :6379    :9200   (共享同一数据库实例)
```
