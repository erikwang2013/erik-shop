# 跨境电商平台 — 架构设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 系统概述
全栈跨境电商平台: Service(37控制器+112模型+12中间件) + Admin(15控制器+5中间件) + Flutter(25文件/12页面) + HarmonyOS(13文件/8页面) + DB(110表) + ES + Redis

## 2. 中间件管道
Service: Cors→Security(15类)→RateLimit→Platform(8平台)→GeoIp→Locale(5语言)→HashidsDecode→VersionRoute→(PosterVerify)→(JwtAuth)→HashidsEncode
Admin: Security→Platform→HashidsDecode→AccessControl→HashidsEncode

## 3. 安全(15类)
XSS/SQL注入/CRLF/路径遍历/Body/ContentType/文件上传/安全响应头/暴力破解/XXE/SSRF/方法/Host/脱敏/CORS

## 4. 国际化
5语言(zh_CN/zh_HK/en/ja/ko) × 45key × 2端 = 10翻译文件 + LocaleMiddleware + Flutter AppLocalizations

## 5. 平台追踪
8平台(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + 6表DB字段

## 6. 高并发
令牌桶限流 + Cache-Aside(防雪崩/防穿透) + 熔断器 + DB读写分离 + 连接池(50/10)

## 7. API文档(hg/apidoc)
Service: 6分组23方法 | Admin: 10分组15控制器

## 8. 测试
23 tests / 68 assertions PASS
