# 跨境电商平台 — 架构概述

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 技术栈

| 层级 | 技术 | 版本 |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| 客户端 | Flutter (5平台) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| 数据库 | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| 支付 | Stripe / PayPal / Klarna / Adyen | — |

## 2. 目录结构

```
shop-php/
  service/           业务API (308 PHP文件)
    config/            36配置 (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/cdn/...)
    app/controller/    45控制器 (44 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111模型 (BaseModel + 110业务模型)
    app/middleware/     14中间件 (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/         19工具类 (Snowflake/HashidsHelper/ApiResponse/Money/Encryption/Jwt/SecureEncrypter/CircuitBreaker/DistributedLock/Cdn/RiskEngine/RefundHelper/SocialAuth/PaymentGateway + Stripe/PayPal/Klarna/Adyen 网关 + InventoryLogger/Definitions)
    app/process/        16自定义进程 (汇率/物流轨迹/分账结算/支付对账/Feed同步/推荐/合规/退货超时/降价提醒/订阅 + ChatWs/Monitor/Http/SnowflakeWorker/PrivacyComplianceTask)
    database/          schema.sql (已被根目录 install.sql 替代) + seeders
    tests/             24测试类 (全量结果见 docs/test-reports/)
  admin/             管理后台 (259 PHP文件)
    plugin/admin/app/controller/shop/ 70控制器 (插件控制器合计 85，含 CdnProvider)
    plugin/admin/app/model/shop/      69模型 (插件模型合计 78，含 CdnProviders/CdnPurgeLogs)
    plugin/admin/app/view/shop/       ECharts仪表盘 + CDN管理页(Layui 3-tab)
    app/middleware/    5中间件 (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              客户端
    flutter/lib/      26 Dart (13页面 + 6功能模块 + 核心层 + 路由)
    harmonyos/        16 ArkTS (10页面 + 3安全组件 + API客户端 + 全局状态)
  docs/               15篇设计文档 + 图集
    pet.svg             项目宠物「雪球 Snowy」规范源文件
    01-08-*.svg         8张架构/流程/功能/生命周期图 (*.mmd = Mermaid 源)
  .claude/skills/     38个开发规范Skills
```

## 3. 中间件管道

```
Service: Cors → Security(31类攻击检测) → RateLimit(令牌桶限流) → Platform(8平台识别)
        → GeoIp(区域) → Locale(语言) → HashidsDecode → VersionRoute
        → (PosterVerify 人机验证) → (JwtAuth Token) → HashidsEncode → Encryption(接口加密)

Admin:  Security → Platform → HashidsDecode → AccessControl(内置RBAC) → HashidsEncode
```

## 4. 安全

- **31类攻击检测**: XSS/SQL注入/命令注入/CRLF/路径遍历/Body/ContentType/文件上传/暴力破解/XXE/SSRF/反序列化/LDAP/邮件头/SSTI/NoSQL/开放重定向/JWT攻击/Host/请求走私/GraphQL/XPATH/Log4Shell/SSI/CSV公式/数据泄露/原型污染/WebSocket/CORS/DNS重绑定/HTTP方法/CSRF Origin
- **三层加密**: 接口层(AES-256-CBC) + 数据库层(Encryptable trait) + ID混淆(Hashids)
- **平台追踪**: 8平台(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + X-Platform header + 6表记录

## 5. 高并发

- **限流**: 令牌桶滑动窗口(Redis ZSET), 6端点规则
- **熔断/降级**: Redis 熔断器 — 支付网关/社交登录外部API调用, 连续5次失败→熔断30s, 半开探测自动恢复; 业务异常不计入失败; Redis故障自动降级放行(503)
- **DB**: 读写分离(2读副本+sticky) + 连接池(50/10)
- **慢操作**: 由独立 Cron 进程处理（Feed同步/推荐计算/支付对账/分账结算等）

## 6. 测试

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+数据泄露
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

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
