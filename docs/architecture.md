# 跨境电商平台 — 架构概述

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 技术栈
| 层级 | 技术 |
|------|------|
| API | webman 2.1 + illuminate/database + erikwang2013/* |
| Admin | webman-admin + LayUI + ECharts |
| 客户端 | Flutter (5平台) + HarmonyOS (ArkTS) |
| 数据库 | MySQL 8.0 + Redis 7 + ES 8 |

## 2. 中间件管道
Service: Cors→Security(15类)→RateLimit→Platform(8平台)→GeoIp→Locale(5语言)→HashidsDecode→VersionRoute→(PosterVerify)→(JwtAuth)→HashidsEncode
Admin: Security→Platform→HashidsDecode→AccessControl→HashidsEncode

## 3. 核心能力
- Snowflake分布式ID / Hashids接口混淆 / JWT HS256认证 / 三层加密
- 多语言(5语言×45key) / 平台追踪(8平台+6表) / 15类安全检测
- 高并发(令牌桶限流+Cache-Aside+熔断器+DB读写分离)
- API文档: hg/apidoc双端 — Service 6分组23方法 + Admin 10分组15控制器

## 4. 测试
23 tests / 68 assertions PASS | 语法 0 errors
