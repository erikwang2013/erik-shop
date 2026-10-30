# 跨境电商平台 — 架构概述

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 技术栈

| 层级 | 技术 | 版本 |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| 客户端 | Flutter (5平台) | 3.x |
| 数据库 | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| 支付 | Stripe / PayPal / Klarna / Adyen | — |

## 2. 目录结构

```
shop-php/
  service/           业务API (262 PHP文件)
    config/            36配置 (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/cdn/...)
    app/controller/    40控制器 (39 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         90模型 (BaseModel + 89业务模型)
    app/middleware/     14中间件 (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/         25工具类 (Snowflake/HashidsHelper/ApiResponse/Money/Encryption/Jwt/SecureEncrypter/CircuitBreaker/DistributedLock/Cdn/RiskEngine/RefundHelper/SocialAuth/PaymentGateway + Stripe/PayPal/Klarna/Adyen 网关 + InventoryLogger/Definitions + OrderCancellation/OrderPricing/PaymentSuccessService/SubscriptionOrderService/BusinessException/LockTimeoutException)
    app/process/        15自定义进程 (汇率/物流轨迹/分账结算/支付对账/Feed同步/推荐/合规/退货超时/降价提醒/订阅/未付款超时取消 + Monitor/Http/SnowflakeWorker/PrivacyComplianceTask)
    database/          schema.sql (已被根目录 install.sql 替代) + seeders
    tests/             6测试文件 (2 单元 + 3 集成 + IntegrationTestCase；全量结果见 docs/test-reports/)
  admin/             管理后台 (232 PHP文件)
    plugin/admin/app/controller/ 73控制器 (含 CdnProvider)
    plugin/admin/app/model/      66模型 (含 CdnProviders/CdnPurgeLogs)
    plugin/admin/app/view/shop/  ECharts仪表盘 + CDN管理页(Layui 3-tab)
    app/middleware/    5中间件 (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              客户端
    flutter/lib/      26 Dart (12页面 + 6功能模块 + 核心层 + 路由)
    harmonyos/        16 ArkTS (10页面 + 3安全组件 + API客户端 + 全局状态)
  docs/               15篇设计文档 + 图集
    pet.svg             项目宠物「雪球 Snowy」规范源文件
    01-08-*.svg         8张架构/流程/功能/生命周期图 (*.mmd = Mermaid 源)
  .claude/skills/     38个开发规范Skills
```

统计（v1.5.7）：service 40控制器 / 90模型 / 25 common / 15进程 / 262 PHP文件 / 98张表 / 89路由；admin 73控制器 / 66模型 / 232 PHP文件。

## 3. 中间件管道

```
Service: Cors → Security(31类攻击检测) → RateLimit(令牌桶限流) → Platform(8平台识别)
        → GeoIp(区域) → Locale(语言) → HashidsDecode → VersionRoute → HashidsEncode
        → Encryption(接口加密)
        (路由级: PosterVerify 人机验证 → JwtAuth Token)

Admin:  Security → Platform → HashidsDecode → AccessControl(内置RBAC) → HashidsEncode
```

RateLimit 位于 Platform 之前，未识别平台的请求同样受限流约束。

## 4. 安全

- **31类攻击检测**: XSS/SQL注入/命令注入/CRLF/路径遍历/Body/ContentType/文件上传/暴力破解/XXE/SSRF/反序列化/LDAP/邮件头/SSTI/NoSQL/开放重定向/JWT攻击/Host/请求走私/GraphQL/XPATH/Log4Shell/SSI/CSV公式/数据泄露/原型污染/WebSocket/CORS/DNS重绑定/HTTP方法/CSRF Origin
- **三层加密**: 接口层(AES-256-CBC) + 数据库层(Encryptable trait) + ID混淆(Hashids)；安装器为 service/admin 生成同一把随机 ENCRYPTION_KEY，仓库不再内置示例密钥
- **平台追踪**: 8平台(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + X-Platform header + 5表记录
- **响应缓存**: 所有 API 响应带 `Cache-Control: no-store, no-cache, must-revalidate`
- **密码重置**: 300s/5次限流 + 每邮箱 5 次失败锁定（重新申请清零）

## 5. 高并发

- **限流**: 令牌桶滑动窗口(Redis ZSET), 7端点规则（login 60/10、password/reset 300/5、register 300/5、social 300/5、payment 60/5、orders 10/3、search 1/10，默认 60/100）
- **熔断/降级**: Redis 熔断器 — 支付网关/社交登录外部API调用, 连续5次失败→熔断30s, 半开探测自动恢复; 业务异常不计入失败; Redis故障自动降级放行(503)
- **DB**: 读写分离(2读副本+sticky) + 连接池(50/10)
- **慢操作**: 由独立 Cron 进程处理（Feed同步/推荐计算/支付对账/分账结算/未付款订单超时取消等）

## 6. 测试

6 个测试文件 / 49 单元测试 / 110 断言 — 本地通过
- HttpParsingTest、MoneyTest（单元）
- OrderCancellationTest、OrderPricingTest、PaymentSuccessServiceTest（集成，需 MySQL schema，本地未运行）
- CI 覆盖 PHP 8.3 / 8.4

历史口径（2026-08-04）：22 tests / 45 assertions，含 SecurityTest(12) + JwtTest(4) + ApiResponseTest(3) + RedisFacadeTest(3)。

## 7. 部署

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# 手动
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **多语言 (i18n)**: 5语言翻译文件 + LocaleMiddleware + Flutter AppLocalizations
- **API文档**: hg/apidoc自动生成 (6分组, 控制器注解驱动)
- **平台追踪**: 8平台 X-Platform header + DB记录
- **CDN (可选)**: Origin-Pull 回源 — 上传文件存 admin 本地磁盘, 输出边界 `Cdn::url()` 重写为 `https://{CDN_DOMAIN}{path}`; 4家提供商(Cloudflare/CloudFront/阿里云/腾讯云), 管理端配置+自动刷新, 详情见 [CDN 支持方案](PLAN-CDN.md)

详见: [部署文档](deployment.md) | [完整架构文档](architecture-full.md) | [功能设计文档](features.md)
