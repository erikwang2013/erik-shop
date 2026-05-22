# Erik Shop — 跨境电商平台
基于 webman 全家桶构建的全栈跨境电商平台，覆盖 B2C/B2B 场景和第三方卖家入驻。

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 版本概览

| | 简化版 (Lite) | 标准版 (Standard) | 完整版 (Full) |
|---|:---:|:---:|:---:|
| **定位** | 个人开发者 / 小型电商 | 成长型跨境商家 | 企业级全栈平台 |
| **许可证** | MIT 开源 | 商业授权 | 商业授权 |
| **获取方式** | GitHub 公开下载 | 联系 erik@erik.xyz | 联系 erik@erik.xyz |
| **分支** | `lite` | `standard` | `full` |
| **当前** | — | — | ✅ |

---

## 功能对比

### 用户系统

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 邮箱注册/登录 (JWT) | ✅ | ✅ | ✅ |
| 社交登录 (Google/Apple/Facebook) | — | ✅ | ✅ |
| 地址管理 | ✅ | ✅ | ✅ |
| 会员等级 + 积分 | — | — | ✅ |
| 礼品卡 | — | — | ✅ |
| KYC实名认证 | — | — | ✅ |

### 商品系统

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 分类管理 (树形) | ✅ | ✅ | ✅ |
| SKU + 属性 | ✅ | ✅ | ✅ |
| 商品图片 | ✅ | ✅ | ✅ |
| 多语言内容 | — | ✅ | ✅ |
| 多币种独立定价 | — | ✅ | ✅ |
| 商品评价 | ✅ | ✅ | ✅ |
| 合规标签 (FDA/CE/RoHS) | — | ✅ | ✅ |
| ES多语言搜索 | — | ✅ | ✅ |
| 商品Feed同步 (Google/Meta) | — | — | ✅ |
| 尺码对照表 | — | — | ✅ |

### 交易系统

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 购物车 | ✅ | ✅ | ✅ |
| 订单管理 | ✅ | ✅ | ✅ |
| 支付 (Stripe) | ✅ | ✅ | ✅ |
| 支付 (PayPal/Klarna/Adyen) | — | ✅ | ✅ |
| BNPL先买后付 | — | ✅ | ✅ |
| 退款 | ✅ | ✅ | ✅ |
| 退货管理 | — | ✅ | ✅ |
| 商业发票/装箱单 | — | ✅ | ✅ |
| 物流保险 | — | — | ✅ |

### 跨境物流

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 国际物流商管理 | — | ✅ | ✅ |
| 物流分区 + 阶梯费率 | — | ✅ | ✅ |
| 海外仓 (发货+退货) | — | ✅ | ✅ |
| HS申报 | — | ✅ | ✅ |
| 物流轨迹追踪 | — | ✅ | ✅ |
| 多仓库库存管理 | — | — | ✅ |

### 海关税务

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| HS Code编码库 | — | ✅ | ✅ |
| 关税规则配置 | — | ✅ | ✅ |
| VAT/IOSS设置 | — | ✅ | ✅ |
| 各国合规限制 | — | ✅ | ✅ |
| 价格展示合规 (含/不含税) | — | ✅ | ✅ |

### 营销工具

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 优惠券 | ✅ | ✅ | ✅ |
| 轮播图 | ✅ | ✅ | ✅ |
| 秒杀 | — | ✅ | ✅ |
| 拼团 | — | ✅ | ✅ |
| 分销 (链接+佣金+提现) | — | ✅ | ✅ |
| 区域促销 | — | ✅ | ✅ |

### 供应链

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 供应商管理 | — | — | ✅ |
| 采购单 | — | — | ✅ |
| 质检 (入库+出库门禁) | — | — | ✅ |
| 库存流水 (不可变账本) | — | — | ✅ |
| 库存调拨 | — | — | ✅ |

### 平台扩展

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 多店铺管理 | — | — | ✅ |
| 多商家入驻 (第三方卖家) | — | — | ✅ |
| Amazon/eBay/Shopee刊登 | — | — | ✅ |
| 多平台订单聚合 | — | — | ✅ |
| B2B批发 (阶梯定价/询价) | — | — | ✅ |

### 风控合规

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 基础攻击检测 (XSS/SQLi) | ✅ | ✅ | ✅ |
| 扩展攻击检测 (XXE/SSRF等) | — | — | ✅ |
| PosterVerify人机验证 | — | ✅ | ✅ |
| 风控规则引擎 | — | — | ✅ |
| GDPR/CCPA数据请求 | — | — | ✅ |
| Cookie Consent管理 | — | — | ✅ |
| 平台来源追踪 | — | ✅ | ✅ |
| 平台来源追踪 (8平台) | — | ✅ | ✅ |

### 高并发

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| DB连接池 | ✅ | ✅ | ✅ |
| 令牌桶限流 | — | — | ✅ |
| Cache-Aside缓存 | — | — | ✅ |
| 熔断器 | — | — | ✅ |
| DB读写分离 | — | — | ✅ |
| 异步队列 | — | — | ✅ |

### 内容与增长

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 系统通知 | ✅ | ✅ | ✅ |
| 邮件模板 | — | — | ✅ |
| CMS多语言页面 | — | — | ✅ |
| FAQ + 知识库 | — | — | ✅ |
| 订阅周期购 | — | — | ✅ |
| AB测试 | — | — | ✅ |
| 实时客服 (WebSocket IM) | — | — | ✅ |

### 客户端

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Admin ECharts仪表盘 | ✅ | ✅ | ✅ |
| Admin Excel/PDF导出 | ✅ | ✅ | ✅ |
| 多语言界面 (5语言) | ✅ | ✅ | ✅ |

---

## 设计对比

### 数据库

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| 数据表 | **23** | **62** | **110** |
| 用户相关 | 3 | 5 | 7 |
| 商品相关 | 6 | 15 | 19 |
| 交易相关 | 6 | 9 | 9 |
| 物流相关 | 0 | 7 | 9 |
| 海关相关 | 0 | 5 | 5 |
| 营销相关 | 4 | 8 | 8 |
| 供应链 | 0 | 0 | 5 |
| 风控合规 | 0 | 0 | 5 |
| 多平台 | 0 | 0 | 9 |
| 内容增长 | 0 | 1 | 14 |
| 客服/AB/API | 0 | 0 | 5 |

### 中间件管道

```
Lite:      Cors → Security(4类) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security(4类) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security(15类) → RateLimit(令牌桶) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode
```

### 代码规模

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Service 模型 | 26 | 55 | 112 |
| Service 控制器 | 15 | 24 | 37 |
| Service 中间件 | 7 | 9+2 | 11+2 |
| Service 工具类 | 5 | 5 | 8 |
| Admin 模型 | 15 | 34 | 65 |
| Admin 控制器 | 15 | 27 | 67 |
| Flutter 页面 | 12 | 12 | 12 |
| HarmonyOS | — | — | 8页面 |
| PHPUnit测试 | 23 | 23 | 23 |

### 技术栈

| 组件 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| snowflake-php | ✅ | ✅ | ✅ |
| hashids | ✅ | ✅ | ✅ |
| jwt-webman | ✅ | ✅ | ✅ |
| encryption | ✅ | ✅ | ✅ |
| encryptable | ✅ | ✅ | ✅ |
| season | — | ✅ | ✅ |
| poster-php | — | ✅ | ✅ |
| webman-scout | — | ✅ | ✅ |
| phpspreadsheet | ✅ | ✅ | ✅ |
| dompdf | ✅ | ✅ | ✅ |
| stripe/stripe-php | — | ✅ | ✅ |
| maxmind/GeoIP2 | — | ✅ | ✅ |
| guzzlehttp/guzzle | — | ✅ | ✅ |

---

## 升级路径

```
Lite (开源) ──→ Standard (商业) ──→ Full (商业)

升级方式:
  1. 联系 erik@erik.xyz 获取对应版本代码
  2. 导入增量 schema (lite→standard 增加~40表, standard→Full 增加~48表)
  3. 复制对应版本控制器/模型/中间件
  4. composer require 新增依赖包
```

---

## 获取方式

| 版本 | 方式 |
|------|------|
| **简化版 (Lite)** | GitHub 开源 [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php) `lite` 分支 |
| **标准版 (Standard)** | 商业授权 — 联系 **erik@erik.xyz** |
| **完整版 (Full)** | 商业授权 — 联系 **erik@erik.xyz** |

商业授权包含: 完整源代码 / 部署支持 / 优先更新 / 技术咨询
