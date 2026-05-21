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
  service/           业务API (178 PHP文件)
    config/            32配置 (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/v1/ 37控制器 (Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         112模型 (BaseModel + 110业务模型)
    app/middleware/     12中间件 (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/StaticFile)
    app/common/          8工具类 (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/Cache/CircuitBreaker/PaymentGateway)
    database/          schema.sql (110表) + seeders
    tests/              3测试类 (23 tests, 68 assertions)
  admin/             管理后台 (137 PHP文件)
    plugin/admin/app/controller/shop/ 67控制器
    plugin/admin/app/model/shop/      65模型
    plugin/admin/app/view/shop/       ECharts仪表盘
    app/middleware/    5中间件 (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              客户端
    flutter/lib/      25 Dart (12页面 + 核心层 + 路由)
    harmonyos/        13 ArkTS (8页面 + API客户端 + 全局状态)
  docs/               5个设计文档
  .claude/skills/     16个开发规范Skills
```

## 3. 中间件管道

```
Service: Cors → Security(15类攻击检测) → RateLimit(令牌桶限流) → Platform(8平台识别)
        → GeoIp(区域) → Locale(语言) → HashidsDecode → VersionRoute
        → (PosterVerify 人机验证) → (JwtAuth Token) → HashidsEncode

Admin:  Security → Platform → HashidsDecode → AccessControl(内置RBAC) → HashidsEncode
```

## 4. 安全

- **15类攻击检测**: XSS/SQL注入/CRLF/路径遍历/Body/ContentType/文件上传/安全响应头/暴力破解/XXE/SSRF/HTTP方法/Host/敏感脱敏/CORS
- **三层加密**: 接口层(AES-256-CBC) + 数据库层(Encryptable trait) + ID混淆(Hashids)
- **平台追踪**: 8平台(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + X-Platform header + 6表记录

## 5. 高并发

- **限流**: 令牌桶滑动窗口(Redis ZSET), 6端点规则
- **缓存**: Cache-Aside + 随机TTL防雪崩 + 空值缓存防穿透 + 标签缓存 + 分布式锁
- **熔断**: 5次失败→熔断60s + 自动恢复 + 降级fallback
- **DB**: 读写分离(2读副本+sticky) + 连接池(50/10)
- **热点缓存**: 6端点(60s~3600s)
- **异步队列**: 邮件/Feed/推荐/导出/日志

## 6. 测试

23 tests / 68 assertions — ALL PASS
- SecurityTest (16): XSS+SQLi+XXE+SSRF+File+Path
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

详见: [部署文档](deployment.md) | [完整架构文档](architecture-full.md) | [功能设计文档](features.md)
