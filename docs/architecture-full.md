# 跨境电商平台 — 架构设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 系统概述

### 1.1 系统定位

基于 webman 高性能框架的全栈跨境电商平台，支持 B2C、B2B、第三方卖家入驻（多商家模式）。

### 1.2 核心指标

| 指标 | 目标值 |
|------|--------|
| API响应时间 (P99) | <200ms |
| 并发连接 | 10000+ (webman常驻内存) |
| 数据库表 | 110张 |
| API端点 | 70+ |
| 支持语言 | zh_CN, zh_HK, en, ja, ko |
| 支持币种 | USD, EUR, GBP, JPY, KRW, CNY 等19种 |
| 支付网关 | Stripe, PayPal, Klarna, Adyen |

### 1.3 技术选型依据

| 技术 | 选择理由 |
|------|---------|
| webman | 常驻内存、协程支持、workerman底层、比传统PHP-FPM快10-100倍 |
| illuminate/database | Laravel Eloquent ORM，独立于Laravel框架使用 |
| MySQL 8.0 | 成熟稳定、JSON支持、utf8mb4完整覆盖emoji和多语言 |
| Redis 7 | 缓存/Session/队列/限流/Poster验证存储 |
| Elasticsearch 8 | 多语言全文搜索、分词、聚合筛选 |
| Flutter | 一套代码5平台(iOS/Android/macOS/Windows/Linux) |
| HarmonyOS NEXT | 鸿蒙生态，ArkTS声明式UI |

---

## 2. 系统架构

### 2.1 整体架构图

```
┌─────────────────────────────────────────────────────────────┐
│                      客户端层                                │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │ Flutter   │  │ Flutter  │  │ Flutter   │  │ Flutter  │   │
│  │ macOS     │  │ Windows  │  │ Linux     │  │ iPadOS   │   │
│  └─────┬────┘  └─────┬────┘  └─────┬────┘  └─────┬────┘   │
│        │             │            │            │           │
│  ┌─────┴─────────────┴────────────┴────────────┴─────┐     │
│  │              HarmonyOS NEXT (ArkTS)                │     │
│  └───────────────────────┬───────────────────────────┘     │
└──────────────────────────┼─────────────────────────────────┘
                           │ HTTP/JSON + JWT
┌──────────────────────────┼─────────────────────────────────┐
│                     接入层                                  │
│  ┌───────────────────────┴───────────────────────────┐     │
│  │              Nginx (:80/:443)                       │     │
│  │  api.erik.xyz → service:8787                       │     │
│  │  admin.erik.xyz → admin:8787                       │     │
│  └──────┬───────────────────────────────┬────────────┘     │
└─────────┼───────────────────────────────┼──────────────────┘
          │                               │
┌─────────┼───────────────────────────────┼──────────────────┐
│         ▼                               ▼                  │
│  ┌──────────────┐              ┌──────────────┐           │
│  │  Service API │              │  Admin 管理   │           │
│  │  webman 2.1  │              │  webman-admin │           │
│  │  :8787       │              │  :8787        │           │
│  │              │              │              │            │
│  │  36控制器    │              │  67控制器     │           │
│  │  112模型     │              │  65模型       │           │
│  │  9中间件     │              │  LayUI+ECharts│           │
│  └──┬───┬───┬──┘              └──┬───┬───┬───┘            │
│     │   │   │                    │   │   │                 │
└─────┼───┼───┼────────────────────┼───┼───┼─────────────────┘
      │   │   │                    │   │   │
      ▼   ▼   ▼                    ▼   ▼   ▼
┌─────────────────────────────────────────────────────────────┐
│                      数据层                                  │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │ MySQL 8  │  │ Redis 7  │  │ ES 8     │  │ 文件存储  │   │
│  │ :3306    │  │ :6379    │  │ :9200    │  │ public/   │   │
│  │          │  │          │  │          │  │           │   │
│  │ 110表    │  │ Cache    │  │ 商品搜索  │  │ 上传文件  │   │
│  │ InnoDB   │  │ Session  │  │ 多语言    │  │ PDF导出   │   │
│  │ erik_    │  │ 限流     │  │ 分词      │  │ Feed      │   │
│  │ 前缀     │  │ Poster   │  │           │  │           │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 进程模型

```
webman Master Process
  ├── HTTP Worker 1  (监听8787, 处理HTTP请求)
  ├── HTTP Worker 2
  ├── ... (CPU核心数×4)
  ├── HTTP Worker N
  ├── Monitor Process (文件变更监控, 开发环境自动重载)
  └── SnowflakeWorker  (worker启动时初始化Snowflake单例)

每个Worker:
  - 独立的PHP进程
  - 常驻内存(避免每次请求重新加载)
  - 数据库连接池(复用连接)
  - Event-Loop事件驱动
```

---

## 3. 中间件管道设计

### 3.1 管道架构

```
HTTP请求
   │
   ▼
┌───────────────────────────────────────────────────────┐
│ 1. Cors 中间件                                         │
│    - 添加 Access-Control-* 响应头                       │
│    - OPTIONS 预检请求直接返回200                         │
│    - 允许 Headers: Authorization, API-Version,          │
│      Accept-Language, X-Device-Fingerprint             │
├───────────────────────────────────────────────────────┤
│ 2. GeoIpMiddleware                                     │
│    - 读取 X-Real-IP                                    │
│    - MaxMind GeoLite2 查询区域                          │
│    - 注入 request->geoCountry/geoCurrency/geoLanguage   │
│    - 已登录用户跳过(保留手动选择)                         │
│    - 内网IP使用默认值                                   │
├───────────────────────────────────────────────────────┤
│ 3. LocaleMiddleware                                    │
│    - 解析 Accept-Language header                       │
│    - 精确匹配(zh_CN) → 降级匹配(zh) → 默认(en)           │
│    - 注入 request->locale                              │
│    - 设置PHP翻译语言                                    │
├───────────────────────────────────────────────────────┤
│ 4. HashidsDecode 中间件                                │
│    - 解码URL路径参数中的hashid                           │
│    - 解码GET/POST参数中以_id结尾的字段                    │
│    - hashid → snowflake ID (BIGINT string)             │
│    - 解码失败时保持原值                                  │
├───────────────────────────────────────────────────────┤
│ 5. VersionRoute 中间件 (全局, 不在路由级)                │
│    - 读 API-Version header (如 2026-05-20)             │
│    - 查 config/versions.php 映射表                     │
│    - 注入 request->apiVersion (如 v1, v2)              │
│    - 未传递时使用默认版本                                │
├───────────────────────────────────────────────────────┤
│ 6. PosterVerify 中间件 (路由级, 仅敏感操作)              │
│    - 检查当前路径是否在 protected_routes 中              │
│    - 提取 X-Poster-Token header                       │
│    - Redis 查询 erik:poster:{token}                    │
│    - 验证通过后删除Redis中的token                        │
│    - 未传递token时返回 need_poster:true                 │
├───────────────────────────────────────────────────────┤
│ 7. JwtAuth 中间件 (路由级, 仅认证接口)                    │
│    - 提取 Authorization: Bearer xxx                   │
│    - ErikJwt\JWT::decode(token)                       │
│    - 验证签名(HS256) + 过期时间                          │
│    - 注入 request->userId (snowflake ID string)        │
│    - 失败返回401                                        │
├───────────────────────────────────────────────────────┤
│ 8. HashidsEncode 中间件                                │
│    - 拦截JSON响应                                       │
│    - 遍历响应体, 编码_id结尾字段和id字段                  │
│    - snowflake ID (数字字符串) → hashid                 │
│    - 递归处理嵌套数组/对象                               │
└───────────────────────────────────────────────────────┘
   │
   ▼
Controller 处理业务逻辑
   │
   ▼
HTTP响应 (JSON)
```

### 3.2 关键设计决策

**为什么Hashids放在中间件而不是控制器里？**
- 控制器完全不感知hashids，始终操作原始snowflake ID
- 防止人为遗忘编解码
- 统一处理所有入口/出口

**为什么Version在Header不在URL？**
- URL保持干净：`/api/products` 而非 `/api/v1/products`
- 版本演进时不需要修改路由注册
- 客户端升级只需改header值

**为什么JwtAuth在HashidsDecode之后？**
- JWT payload中的sub是snowflake ID
- 必须先解码hashid才能拿到真实ID进行比对
- 顺序不可变

**为什么PosterVerify在JwtAuth之前？**
- 部分敏感操作(如注册)不需要登录但要人机验证
- PosterVerify是路由级中间件，可以独立应用于注册路由

---

## 4. 数据架构

### 4.1 主键策略

```
Snowflake ID (64bit):
┌─┬─────────────────────┬──────────┬──────────┬────────────┐
│0│    42bit 时间戳      │ 5bit DC  │ 5bit WID │ 12bit 序号  │
└─┴─────────────────────┴──────────┴──────────┴────────────┘

特点:
- 全局唯一（分布式环境安全）
- 趋势递增（数据库索引友好）
- 不依赖数据库自增（分库分表安全）
- BIGINT UNSIGNED → PHP必须用string类型（防止溢出）
- 生成: Snowflake::nextId() → "1234567890123456789"
```

### 4.2 敏感数据保护

```
三层加密体系:

1. 接口层 (erikwang2013/encryption)
   POST body中的敏感字段 → AES-256-CBC加密 → 传输
   适用: 支付信息、证件号码

2. 数据库层 (Maize\Encryptable\Encryptable trait)
   写入: email/mobile → AES加密 → 存储密文
   读取: 密文 → AES解密 → 返回明文
   查询: whereEncrypted('email', 'test@example.com')
   适用: email, mobile, name, phone, detail, tax_id

3. 接口ID混淆 (erikwang2013/hashids)
   snowflake ID → hashid (8位字符)
   对外暴露: "Ab3xK9pq" (隐藏真实ID)
   中间件自动编解码
```

### 4.3 多语言存储策略

```
为什么不用JSON字段存储多语言？
  错误: {title: {zh_CN: "标题", en: "Title", ja: "タイトル"}}
  问题: JSON查询低效、无法索引、难以约束

正确: erik_product_translations 独立表
  product_id | locale  | title | description
  1          | zh_CN   | 标题  | 描述
  1          | en      | Title | Description
  1          | ja      | タイトル | 説明

优势:
  - locale可建索引
  - 可约束unique(product_id, locale)
  - 支持全文检索
  - 易于扩展新语言
```

### 4.4 多币种定价策略

```
为什么不用汇率换算？
  汇率波动 → 频繁调价 → 用户体验差
  不同市场定价策略不同(发达国家价高、新兴市场价低)

方案: erik_product_sku_prices 按币种独立定价
  sku_id | currency_code | price
  1      | USD           | 29.99
  1      | EUR           | 34.99   ← 不是29.99×汇率
  1      | JPY           | 4500    ← 日本市场独立定价

降级: 某币种未设置价格时 → 汇率换算(CNY基准)
```

---

## 5. 安全架构

### 5.1 认证流程

```
┌──────────┐     ┌──────────┐     ┌──────────┐
│ 客户端    │────▶│ 中间件    │────▶│ 控制器    │
└──────────┘     └──────────┘     └──────────┘

1. 登录: POST /api/auth/login {email, password}
   → password_verify(password+salt, bcrypt_hash)
   → Jwt::encode({sub: userId, email, level, iat, exp})
   → 返回 {access_token, expires_in}

2. 访问: Authorization: Bearer <token>
   → JwtAuth::process() → Jwt::decode(token)
   → 验证 HS256签名 + 过期时间
   → 注入 request->userId

3. 刷新: POST /api/auth/refresh {refresh_token}
   → Jwt::decode(refresh_token) → 新access_token
```

### 5.2 支付安全

```
- Stripe: 使用PaymentIntent + client_secret (前端3DS验证)
- Webhook 验签: Stripe-Signature header验证
- PCI-DSS: 不存储信用卡号，全部由网关处理
- 幂等: transaction_no去重，防止webhook重放
```

### 5.3 数据安全

```
- GDPR: 用户可请求数据访问/删除/可携 (POST /api/privacy/request)
- CCPA: 用户可Opt-Out (POST /api/privacy/request {type: opt_out})
- Cookie: 记录 consent 版本和偏好
- 数据删除: 匿名化用户+脱敏订单+清除营销记录（保留订单税务审计）
```

---

## 6. 国际化架构

```
┌─────────────────────────────────────┐
│           客户端                     │
│  Flutter: AppLocalizations (5语言)   │
│  HarmonyOS: AppState.locale         │
│  发送: Accept-Language: ja           │
│       API-Version: 2026-05-20       │
└────────────────┬────────────────────┘
                 │
┌────────────────▼────────────────────┐
│         Service API                 │
│  LocaleMiddleware: 解析 Accept-Lang  │
│  → 设置当前语言 → trans() 翻译消息    │
│                                     │
│  内容多语言:                          │
│  ProductTranslations.where(locale)  │
│  CmsPageTranslations.where(locale)  │
│  FaqTranslations.where(locale)      │
│  KnowledgeBaseTranslations(locale)  │
│  EmailTemplates(code + locale)      │
└─────────────────────────────────────┘
```

---

## 7. 支付架构

```
┌──────────────────────────────┐
│     PaymentGateway 工厂      │
│                              │
│  ::make('stripe')  ──▶ StripeGateway (实现)
│  ::make('paypal')  ──▶ PayPalGateway (占位)
│  ::make('klarna')  ──▶ KlarnaGateway (占位)
│  ::make('adyen')   ──▶ AdyenGateway (占位)
└──────────────────────────────┘
         │
         │ 实现 PaymentGatewayInterface
         │
         ├── createPayment(array): array
         ├── capturePayment(txnId): array
         ├── refundPayment(txnId, amount): array
         └── verifyWebhook(payload, signature): bool

Webhook 回调处理:
  验签 → 更新Payment状态 → 更新Order状态 → 创建PlatformSettlement

分账计算:
  PlatformSettlement {
    total_amount      = 订单总额
    platform_fee      = 总额 × 平台佣金率(5%)
    payment_gateway_fee = 总额 × 网关费率(2.9%) + 固定费($0.30)
    supplier_amount   = 总额 - 平台佣金 - 网关费 - 分销佣金
    affiliate_amount  = 总额 × 分销佣金率(5%)
  }
```

---

## 8. 部署架构

### 8.1 Docker Compose 拓扑

```
┌─────────────────────────────────────────────────────────┐
│                    Docker Compose                        │
│                                                         │
│  ┌──────────┐                                            │
│  │  nginx    │ :80 → service:8787                       │
│  │  alpine   │ :80 → admin:8787                         │
│  └─────┬────┘                                            │
│        │                                                 │
│  ┌─────┴──────────────┐                                 │
│  │    PHP Containers   │                                 │
│  │  ┌────────┐┌───────┐│                                │
│  │  │service ││ admin ││  PHP 8.3-cli-alpine            │
│  │  │:8787   ││:8787  ││  OPCache enabled               │
│  │  └───┬────┘└──┬────┘│                                 │
│  └──────┼────────┼─────┘                                 │
│         │        │                                       │
│  ┌──────┼────────┼──────────────────────────┐           │
│  │  ┌───┴────────┴───┐  ┌──────┐  ┌───────┐ │           │
│  │  │   MySQL 8.0    │  │Redis │  │  ES 8 │ │           │
│  │  │   :3306        │  │:6379 │  │ :9200 │ │           │
│  │  │   erik_shop    │  │      │  │       │ │           │
│  │  └────────────────┘  └──────┘  └───────┘ │           │
│  │      Data Volumes (持久化)                 │           │
│  └──────────────────────────────────────────┘           │
└─────────────────────────────────────────────────────────┘
```

### 8.2 网络隔离

```
erik-net (bridge):
  nginx ←→ service
  nginx ←→ admin
  service ←→ mysql
  service ←→ redis
  service ←→ elasticsearch
  admin ←→ mysql
  admin ←→ redis

外部只能访问 nginx:80/443
内部容器间通过服务名通信
```

### 8.3 环境变量注入

```
所有密钥通过 .env → docker-compose environment 注入
不硬编码到代码中:
  - DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
  - STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET
  - PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET
  - MAXMIND_LICENSE_KEY
```

---

## 9. 可观测性

### 9.1 日志策略

```
层级          | 通道    | 路径                          | 说明
Webman日志    | default | runtime/logs/webman.log       | 错误+访问
Monolog       | default | runtime/logs/webman.log       | 带时间戳
操作日志      | DB      | erik_operation_logs           | 管理员操作审计
风控日志      | DB      | erik_risk_logs                | 风控事件
邮件日志      | DB      | erik_email_logs               | 发送记录
搜索日志      | DB      | erik_search_logs              | 搜索分析
```

### 9.2 监控指标

```
Admin仪表盘 (ECharts):
  - 今日订单/营收 KPI卡片
  - 近7天销售趋势 (柱状图+折线图)
  - 订单状态分布 (玫瑰饼图)
  - 区域销售分布 (柱状图)
  - 币种收入占比 (饼图)
  - 退货率 / 风控拦截率
  - 支付成功率
```

---

## 10. 扩展性设计

### 10.1 API版本演进

```
config/versions.php:
  '2026-05-20' → 'v1'  (当前)
  '2026-08-01' → 'v2'  (未来)

v2控制器创建:
  app/controller/v2/ProductController.php

VersionRoute中间件自动路由
客户端只需更新 API-Version header
```

### 10.2 多店铺扩展

```
erik_shops 表实现数据隔离
  未来: shop_id贯穿所有业务表
  当前: 单店铺模式，shop_id默认=0
```

### 10.3 插件化支付

```
PaymentGatewayInterface 定义标准接口
  新增网关: 实现接口 + 注册到 PaymentGateway::make()
  数据库: erik_payment_gateways + erik_payment_gateway_methods
```

---

## 11. 项目统计

| 维度 | 数量 |
|------|------|
| PHP源文件 (service) | 210 |
| PHP源文件 (admin) | 214 |
| Dart源文件 (Flutter) | 25 |
| ArkTS源文件 (HarmonyOS) | 12 |
| 数据库表 | 110 |
| API端点 | 71 |
| 中间件 | 9 |
| 工具类 | 6 |
| 定时任务 | 12 |
| 配置项 | 31 |
| 文档 | 8 |
| **总计** | **~620** |
