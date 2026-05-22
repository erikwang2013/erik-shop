# Erik Shop — 三版本定义

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

| 项目 | 简化版 (Lite) | 标准版 (Standard) | 完整版 (Full) |
|------|:------------:|:----------------:|:------------:|
| **定位** | 个人/小型电商起步 | 成长型跨境商家 | 企业级全栈平台 |
| **分支** | `lite` | `standard` | `full` |
| **当前** | ✅ 当前分支 | 计划中 | 计划中 |

---

## 功能差异

| 功能模块 | 简化版 | 标准版 | 完整版 |
|---------|:-----:|:------:|:-----:|
| 用户注册登录 (JWT) | ✅ | ✅ | ✅ |
| 商品管理 + 分类 + SKU | ✅ | ✅ | ✅ |
| 购物车 + 订单 + 支付 | ✅ | ✅ | ✅ |
| 基础安全 (XSS/SQLi/CRLF/Path) | ✅ | ✅ | ✅ |
| Hashids ID混淆 | ✅ | ✅ | ✅ |
| Flutter客户端 (5平台) | ✅ | ✅ | ✅ |
| Admin管理后台 | ✅ | ✅ | ✅ |
| 多语言商品内容 | — | ✅ | ✅ |
| 多币种独立定价 | — | ✅ | ✅ |
| 国际物流 (分区运费/海外仓) | — | ✅ | ✅ |
| HS Code + 关税 + VAT | — | ✅ | ✅ |
| 优惠券 + 秒杀 + 拼团 | — | ✅ | ✅ |
| 退货管理 | — | ✅ | ✅ |
| 分销系统 | — | ✅ | ✅ |
| 供应商 + 采购 + 质检 | — | — | ✅ |
| 多商家入驻 | — | — | ✅ |
| 多平台刊登 (Amazon/eBay) | — | — | ✅ |
| B2B批发 | — | — | ✅ |
| 会员体系 + 积分 + 礼品卡 | — | — | ✅ |
| 订阅周期购 | — | — | ✅ |
| 实时客服 (WebSocket IM) | — | — | ✅ |
| GDPR/CCPA隐私合规 | — | — | ✅ |
| 风控引擎 + KYC | — | — | ✅ |
| AB测试 | — | — | ✅ |
| HarmonyOS客户端 | — | — | ✅ |
| 15类安全检测 | — | — | ✅ |
| 高并发优化 (限流/缓存/熔断) | — | — | ✅ |

---

## 数据表

| 版本 | 表数 | 核心变化 |
|------|:---:|---------|
| 简化版 | ~25 | 用户/商品/订单/支付/基础数据 |
| 标准版 | ~65 | +多语言/多币种/物流/海关/营销 |
| 完整版 | 110 | +供应链/风控/多平台/B2B/客服/AB测试 |

---

## 中间件

| 版本 | 数量 | 中间件 |
|------|:---:|--------|
| 简化版 | 7 | Cors/Security(basic)/Locale/HashidsDecode/VersionRoute/JwtAuth/HashidsEncode |
| 标准版 | 9 | +PosterVerify(人机验证)/Platform(来源追踪) |
| 完整版 | 11 | +RateLimit(限流)/GeoIp(区域) |

---

## 升级路径

```
简化版 (Lite)
  → composer require erikwang2013/season
  → 导入 standard 增量 schema
  → 复制标准版控制器/模型
  → 标准版 (Standard)

标准版 (Standard)
  → composer require stripe/stripe-php phpoffice/phpspreadsheet
  → 导入 full 增量 schema
  → 复制完整版控制器/模型/中间件
  → 完整版 (Full)
```
