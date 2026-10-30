# 跨境电商平台 — 设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 数据库设计

### 1.1 命名规范

- 表前缀: `erik_`
- 主键: `id BIGINT UNSIGNED NOT NULL` (snowflake生成，非自增)
- 时间戳: `created_at`, `updated_at`, `deleted_at` (软删除)
- 引擎: InnoDB, 字符集: utf8mb4_unicode_ci

### 1.2 模块划分 (98表，含 9 张 wa_ 系统表)

| 模块 | 表数 | 核心表 |
|------|------|--------|
| 用户与账户 | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| 商品与分类 | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| 交易 | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| 支付与资金 | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| 物流 | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| 海关与税务 | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| 营销 | 2 | coupons, user_coupons |
| 供应链 | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| 风控与合规 | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| 多商家 | 5 | shops, merchants, merchant_products, merchant_settlements |
| 内容与体验 | 12 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, price_alerts, search_logs, operation_logs, settings |
| 订阅与B2B | 6 | subscriptions, subscription_orders, point_logs, b2b_prices, b2b_verifications, b2b_quotes |
| FAQ | 1 | faq_translations |
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

### 1.4 核心业务服务（v1.5.7）

| 组件 | 职责 | 调用方 |
|------|------|--------|
| OrderCancellation | 用户取消/超时取消/风控驳回共用：原子状态门闩、库存恢复、优惠券恢复、used_qty 回退、订单/库存日志 | OrderController、UnpaidOrderCron、风控驳回 |
| OrderPricing | 运输分区运费（product.weight × qty）与关税/VAT 统一定价；整单免税阈值、VAT 基数含 duty | 下单、订阅建单 |
| PaymentSuccessService | webhook 与 PaymentReconcileCron 共用入账：订单 0→1、支付 0→1、PlatformSettlements、Products/ProductSkus.sales_count；迟到支付记 warning | PaymentController webhook、PaymentReconcileCron |
| SubscriptionOrderService | 订阅首期/续费统一建单：address_id 必填 + 地址快照、USD 报价、商品上架校验、原子扣库存、重量运费税费 | SubscriptionController、SubscriptionCron |
| UnpaidOrderCron | 每 5 分钟取消超过 30 分钟未支付的 status=0 订单；跳过已有进行中网关支付的订单 | 独立进程（config/process.php；超时分钟数见 config/cron.php） |

预期业务错误抛 `BusinessException`（422）、锁超时抛 `LockTimeoutException`（429），其余异常返回 500 且不泄露 SQL。

---

## 2. API设计

API 版本控制、端点统计与统一响应规范，详见 [API接口文档](api.md)。

中间件实际顺序：Cors → Security → RateLimit → Platform → GeoIp → Locale → HashidsDecode → VersionRoute → HashidsEncode → Encryption（10 全局），路由级 PosterVerify、JwtAuth。

---

## 3. 安全设计

### 3.1 SecurityMiddleware 封装 security-php 31个检测器

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

令牌桶滑动窗口(Redis ZSET, 经 support\Redis 门面), 7 条端点规则 + 默认: 登录 10次/60s, 密码重置 5次/300s, 注册 5次/300s, 社交登录 5次/300s, 支付 5次/60s, 下单 3次/10s, 搜索 10次/1s, 默认 100次/60s。所有 API 响应带 `Cache-Control: no-store, no-cache, must-revalidate`；密码重置另有每邮箱 5 次失败锁定，重新申请清零。

### 4.2 熔断与降级

Redis 熔断器（`app\common\CircuitBreaker`）: 支付网关/社交登录等外部 API 调用统一走 `CircuitBreaker::call()` — 连续 5 次失败触发熔断 30s, TTL 过期后下一个请求自动半开探测, 成功即复位。业务异常白名单（无效卡/无效 token）不计入失败, 防止攻击者用无效请求打挂依赖服务; Redis 不可用时自动降级放行。熔断期间接口返回 503「服务暂不可用」。

### 4.3 Redis 用途

Redis 用于限流令牌桶（`support\Redis` 门面）、熔断器计数、人机验证码与 Session 存储，以及 CDN 全局开关传播（prefix `shop:`，60s TTL，admin → service）；业务数据不做应用层缓存，直接读取 MySQL（读写分离 + 连接池）；静态资源（上传图片/文档）由 `Cdn::url()` 在输出边界重写为 CDN 域名，经 CDN 边缘缓存分发（`expires 7d, immutable`）。

### 4.4 连接池

MySQL: 50max/10min/2s超时 | 读写分离: 30max/5min (2读副本, sticky=true) | Redis: 30max/5min



---

## 5. 国际化

- 界面: zh_CN, zh_HK, en, ja, ko
- 内容: erik_product_translations 按locale独立行
- 价格: erik_product_sku_prices 按币种独立定价
- Header: Accept-Language + API-Version

## 6. API文档

使用 hg/apidoc 根据控制器注解自动生成，详见 [API接口文档](api.md)。启动后访问 `/apidoc/`。

## 7. 测试

6 个测试文件 / 49 单元测试 / 110 断言 — 本地通过；3 组集成测试需 MySQL schema，本地未运行；CI PHP 8.3/8.4。

```bash
cd service && php vendor/bin/phpunit tests/
# 单元: HttpParsingTest + MoneyTest
# 集成: OrderCancellationTest + OrderPricingTest + PaymentSuccessServiceTest
```

详见: [功能设计文档](features.md) | [完整架构文档](architecture-full.md) | [部署文档](deployment.md)
