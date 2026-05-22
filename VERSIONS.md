# Erik Shop — 三版本定义

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

| 项目 | 简化版 (Lite) | 标准版 (Standard) | 完整版 (Pro) |
|------|:---:|:---:|:---:|
| **定位** | 个人/小型电商 | 成长型跨境商家 | 企业级全栈 |
| **分支** | `lite` | `standard` | `pro` |
| **当前** | — | ✅ 当前分支 | — |

---

## 功能差异

| 模块 | Lite | Standard | Pro |
|------|:---:|:---:|:---:|
| 用户注册/登录 (JWT+社交) | ✅ | ✅ | ✅ |
| 商品 + 分类 + SKU | ✅ | ✅ | ✅ |
| 购物车 + 订单 + 支付 + 退款 | ✅ | ✅ | ✅ |
| 优惠券 + 轮播图 | ✅ | ✅ | ✅ |
| 基础安全 (XSS/SQLi/CRLF/Path) | ✅ | ✅ | ✅ |
| Hashids + Snowflake + JWT | ✅ | ✅ | ✅ |
| Flutter客户端 | ✅ | ✅ | ✅ |
| Admin管理后台 | ✅ | ✅ | ✅ |
| 多语言商品内容 | — | ✅ | ✅ |
| 多币种独立定价 | — | ✅ | ✅ |
| 国际物流 (分区运费/海外仓) | — | ✅ | ✅ |
| HS Code + 关税 + VAT | — | ✅ | ✅ |
| 秒杀 + 拼团 + 分销 | — | ✅ | ✅ |
| 退货管理 | — | ✅ | ✅ |
| 社交登录 (Google/Apple/Facebook) | — | ✅ | ✅ |
| PosterVerify人机验证 | — | ✅ | ✅ |
| 平台来源追踪 | — | ✅ | ✅ |
| GeoIP区域识别 | — | ✅ | ✅ |
| 供应商 + 采购 + 质检 | — | — | ✅ |
| 多商家入驻 | — | — | ✅ |
| 多平台刊登 (Amazon/eBay) | — | — | ✅ |
| B2B批发 | — | — | ✅ |
| 会员体系 + 积分 + 礼品卡 | — | — | ✅ |
| 订阅周期购 + AB测试 | — | — | ✅ |
| 实时客服 (WebSocket IM) | — | — | ✅ |
| GDPR/CCPA + 风控 + KYC | — | — | ✅ |
| 15类安全检测 + 高并发优化 | — | — | ✅ |

---

## 数据表

| 版本 | 表数 | 核心变化 |
|------|:---:|---------|
| Lite | ~25 | 用户/商品/订单/支付/基础数据 |
| Standard | ~60 | +多语言/多币种/物流/海关/营销/退货/社交登录 |
| Pro | 110 | +供应链/风控/多平台/B2B/客服/AB测试 |

## 中间件

| 版本 | 数量 | 中间件 |
|------|:---:|--------|
| Lite | 7 | Cors/Security(basic)/Locale/Hashids×2/VersionRoute/JwtAuth |
| Standard | 9 | +Platform(来源追踪)/GeoIp(区域)/PosterVerify(人机验证) |
| Pro | 11 | +RateLimit(限流)/扩展Security(15类) |

---

## 升级路径

```
Lite → composer require erikwang2013/season phpoffice/phpspreadsheet
     → 导入 standard 增量 schema
     → 复制标准版控制器/模型/中间件
     → Standard

Standard → 导入 pro 增量 schema
        → 复制完整版控制器/模型/中间件
        → Pro
```
