# 跨境电商平台 — 架构概述 (完整版 Pro)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 技术栈

| 层级 | 技术 |
|------|------|
| API | webman 2.1 + illuminate/database + erikwang2013/* |
| Admin | webman-admin + LayUI + ECharts |
| 客户端 | Flutter (5平台) + HarmonyOS (ArkTS) |
| 数据库 | MySQL 8.0 + Redis 7 + Elasticsearch 8 |

## 2. 目录结构

```
service/        业务API    — 37控制器 + 112模型 + 12中间件 + 32配置
admin/          管理后台    — 27控制器 + 34模型 + 5中间件
apps/flutter/   Flutter    — 25 Dart / 12页面 / PC自适应
apps/harmonyos/ 鸿蒙       — 13 ArkTS / 8页面
database/       数据库     — 110张表 (erik_前缀, snowflake主键)
docs/           文档       — api/architecture-full/architecture/deployment/features/VERSIONS
```

## 3. 中间件管道

```
Service: Cors → Security(15类) → RateLimit → Platform(8平台) → GeoIp
        → Locale(5语言) → HashidsDecode → VersionRoute
        → (PosterVerify) → (JwtAuth) → HashidsEncode

Admin:  Security → Platform → HashidsDecode → AccessControl(内置RBAC) → HashidsEncode
```

## 4. 核心能力

- **Snowflake**: 分布式ID，bigint非自增
- **Hashids**: 接口ID混淆，中间件自动编解码
- **JWT**: ErikJwt HS256认证 + 黑名单 + 刷新
- **三层加密**: 接口层(AES-256-CBC) + 数据库层(Encryptable trait) + ID混淆
- **多语言(i18n)**: 5语言翻译文件(45 key/语言) + LocaleMiddleware + Flutter AppLocalizations
- **平台追踪**: 8平台 X-Platform header + 6表DB记录
- **安全检测**: 15类攻击检测
- **高并发**: 令牌桶限流 + Cache-Aside + 熔断器 + DB读写分离
- **API文档**: hg/apidoc — Service 6分组23方法 + Admin 7分组27控制器全覆盖
- **翻译**: 5语言 × 45 key (service + admin共10文件)

## 5. 测试

23 tests / 68 assertions — ALL PASS

## 6. 部署

```bash
docker compose up -d
cd service && php start.php start -d
cd admin && php start.php start -d
```

详见: [完整架构](architecture-full.md) | [功能设计](features.md) | [API文档](api.md) | [部署](deployment.md)
