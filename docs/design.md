# 跨境电商平台 — 设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 数据库设计

### 1.1 命名规范

- 表前缀: `erik_`
- 主键: `id BIGINT UNSIGNED NOT NULL` (snowflake生成，非自增)
- 时间戳: `created_at`, `updated_at`, `deleted_at` (软删除)
- 引擎: InnoDB, 字符集: utf8mb4_unicode_ci

### 1.2 模块划分 (110表)

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

### 1.3 平台追踪字段

| 表 | 字段 | 说明 |
|----|------|------|
| orders | platform VARCHAR(16) | 下单平台 |
| payments | platform VARCHAR(16) | 支付平台 |
| operation_logs | platform VARCHAR(16) | 操作平台 |
| users | last_login_platform VARCHAR(16) | 最后登录平台 |
| search_logs | platform VARCHAR(16) | 搜索平台 |
| chat_messages | platform VARCHAR(16) | 消息来源 |

---

## 2. API设计

### 2.1 版本控制

版本通过 `API-Version: 2026-05-20` header传递，不在URL中。VersionRoute中间件映射。

### 2.2 中间件管道

```
Cors → Security(15类) → RateLimit(滑动窗口) → Platform(8平台) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode
```

### 2.3 端点统计

- 公开接口: 25个 (认证/商品/分类/内容/搜索/服务)
- 认证接口: 45个 (用户/购物车/订单/支付/退货/评价/营销)
- Webhook: 1个 (支付回调)

### 2.4 统一响应

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

---

## 3. 安全设计

### 3.1 15类攻击检测

| # | 类型 | 错误码 | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | SQL注入 | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | 路径遍历 | 40004 | ✅ | ✅ |
| 5 | Body过大 | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | 文件上传 | 40009 | ✅ | ✅ |
| 8 | 安全响应头 | — | ✅ | ✅ |
| 9 | 暴力破解 | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | HTTP方法 | 40012 | ✅ | ✅ |
| 13 | Host头 | 40013 | ✅ | — |
| 14 | 敏感脱敏 | — | ✅ | ✅ |
| 15 | CORS白名单 | — | ⚠️ | ⚠️ |

### 3.2 三层加密

| 层级 | 技术 | 包 |
|------|------|-----|
| 传输层 | AES-256-CBC | erikwang2013/encryption |
| 数据库层 | Encryptable trait | erikwang2013/encryptable (Maize) |
| ID混淆 | Hashids | erikwang2013/hashids |

---

## 4. 高并发设计

### 4.1 限流

令牌桶滑动窗口(Redis ZSET): 登录10次/60s, 注册5次/300s, 支付5次/60s, 下单3次/10s, 搜索10次/1s

### 4.2 缓存策略

| 策略 | 实现 |
|------|------|
| Cache-Aside | `Cache::remember(key, ttl, callback)` |
| 防雪崩 | 随机TTL ±10% |
| 防穿透 | 空值缓存60s |
| 标签失效 | `Cache::setWithTag/deleteByTag` |
| 分布式锁 | Redis SETNX + TTL |

### 4.3 连接池

MySQL: 50max/10min/2s超时 | 读写分离: 30max/5min (2读副本, sticky=true) | Redis: 30max/5min

### 4.4 熔断

`CircuitBreaker`: 5次失败→熔断60s→自动恢复→降级fallback

---

## 5. 国际化

- 界面: zh_CN, zh_HK, en, ja, ko
- 内容: erik_product_translations 按locale独立行
- 价格: erik_product_sku_prices 按币种独立定价
- Header: Accept-Language + API-Version

## 6. 测试

23 tests / 68 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (16) + JwtTest (4) + ApiResponseTest (3)
```

详见: [功能设计文档](features.md) | [完整架构文档](architecture-full.md) | [部署文档](deployment.md)
