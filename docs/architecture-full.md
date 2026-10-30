# 跨境电商平台 — 架构设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 系统概述

### 1.1 定位

基于 webman 高性能框架的全栈跨境电商平台，支持 B2C、B2B、第三方卖家入驻。

| 组件 | 技术栈 | 规模 |
|------|--------|------|
| Service API | PHP 8.3 / webman 2.1 / illuminate database | 40控制器 + 90模型 + 25 common + 15进程 + 14中间件 |
| Admin | webman-admin / LayUI / ECharts | 73控制器 + 66模型 + 5中间件 |
| Flutter | Riverpod / GoRouter / Dio | 12页面 |
| 数据库 | MySQL 8.0 + Redis 7 + ES 8 | 98张表 (89 `shop_` + 9 `wa_`) |

### 1.2 核心指标

| 指标 | 值 |
|------|-----|
| API P99 | <200ms |
| 并发 | 10000+ (32 worker常驻内存) |
| 表数 | 98 |
| 端点 | 89 |
| 中间件 | 14 (service:10全局+2路由+AdminKey+StaticFile / admin:4全局+1内置) |
| 语言 | zh_CN, zh_HK, en, ja, ko |
| 币种 | 19种独立定价 |
| 支付 | Stripe / PayPal / Klarna / Adyen |

---

## 2. 系统架构图

```mermaid
graph TD
    subgraph Clients[客户端层]
        F[Flutter 5平台<br/>iOS Android macOS Win Linux]
        W[Web Browser Admin]
    end
    subgraph Gateway[接入层]
        C[CDN 边缘层<br/>Origin-Pull 回源<br/>静态资源缓存]
        N[Nginx :80/:443]
    end
    subgraph Apps[应用层]
        S[Service API :8787<br/>40 Controllers 90 Models 14 MW]
        A[Admin :8788<br/>73 Controllers 66 Models 5 MW]
    end
    subgraph Data[数据层]
        M[(MySQL 8.0 :3306<br/>98 tables)]
        R[(Redis 7 :6379<br/>cache session limit)]
        E[(ES 8 :9200<br/>multilingual search)]
    end
    F & W --> C
    C --> N
    N -->|api.erik.xyz| S
    N -->|admin.erik.xyz| A
    S --> M
    S --> R
    S --> E
    A --> M
    A --> R
```

### 2.1 完整设计流程图

```mermaid
graph TB
    subgraph Clients["1. 客户端层"]
        FL[Flutter: iOS Android macOS Win Linux]
        WB[Web Browser: Admin]
    end
    subgraph Gateway["2. 接入层 CDN + Nginx :80"]
        CDN[CDN 边缘层: CNAME 回源 admin 域名<br/>静态资源缓存 expires 7d]
        NG[Nginx: api.erik.xyz→service / admin.erik.xyz→admin]
    end
    subgraph Security["3. 安全层 SecurityMiddleware 6道检测"]
        CT{Content-Type?} -->|Y| BS{Body Size?}
        BS -->|Y| XS{XSS?}
        XS -->|pass| SQ{SQLi?}
        SQ -->|pass| CR{CRLF?}
        CR -->|pass| PT{Path?}
        PT -->|pass| PASS[Pass]
    end
    subgraph Pipeline["4. 中间件管道 10全局+2路由"]
        CORS[Cors] --> SEC[Security WAF]
        SEC --> RATE[RateLimit 令牌桶]
        RATE --> PLAT[Platform]
        PLAT --> GEO[GeoIp]
        GEO --> LOC[Locale]
        LOC --> HDEC[HashidsDecode]
        HDEC --> VER[VersionRoute]
        VER --> POSTV[PosterVerify 路由级]
        POSTV --> JWT[JwtAuth 路由级]
    end
    subgraph Controllers["5. 控制器 40个"]
        AUTH[Auth] & PROD[Product] & CART[Cart]
        ORD[Order] & PAY[Payment] & SHIP[Shipping]
        TARI[Tariff] & USER[User] & COUP[Coupon]
        RET[Return] & NOTI[Notify] & EXPORT[Export]
    end
    subgraph Models["6. 模型层 90 Models"]
        BM[BaseModel: Snowflake ID 主键]
        REL[Relations: hasMany/belongsTo]
        SRCH[Searchable: ES同步 多语言分词]
    end
    subgraph Data["7. 数据层"]
        MySQL[(MySQL 8.0<br/>98 tables<br/>89 shop_ + 9 wa_)]
        Redis[(Redis 7<br/>cache/session<br/>limit/poster)]
        ES[(ES 8<br/>multilingual<br/>search)]
    end
    subgraph Response["8. 响应返回"]
        HENC[HashidsEncode: ID编码]
        ENC[Encryption: 接口加密]
        JSON[JSON: code msg data]
        HEADERS[Headers: CORS X-Platform]
        HENC --> ENC --> JSON --> HEADERS
    end

    FL & WB --> CDN --> NG
    NG --> CORS
    PASS --> PLAT
    JWT --> AUTH & PROD & CART & ORD & PAY & SHIP & TARI & USER & COUP & RET & NOTI & EXPORT
    AUTH & PROD & CART & ORD & PAY & SHIP & TARI & USER & COUP & RET & NOTI & EXPORT --> HENC
    AUTH & PROD & ORD --> BM
    ORD --> REL
    PROD --> SRCH
    BM --> MySQL & Redis
    SRCH --> ES
    HEADERS --> FL & WB

    style Security fill:#fff0f0
    style Pipeline fill:#f0f0ff
    style Controllers fill:#f0fff0
    style Models fill:#fffff0
    style Data fill:#f5f5f5
    style Response fill:#f0ffff
```

**流程图说明:**

| 层 | 说明 |
|----|------|
| 1.客户端层 | Flutter 5平台 + Web Admin, 全部通过 HTTP/JSON 通信 |
| 2.接入层 | CDN 边缘层(Origin-Pull 回源, 静态资源缓存) → Nginx 按域名分流: api→service, admin→admin |
| 3.安全层 | SecurityMiddleware 31类攻击检测器, 命中即返回错误码/403 |
| 4.中间件管道 | 请求阶段10个全局MW + 2个路由级MW(PosterVerify敏感操作, JwtAuth认证接口)；响应阶段 HashidsEncode编码ID → Encryption加密 |
| 5.控制器层 | 40个API控制器按功能分组, 处理全部业务逻辑 |
| 6.模型层 | 90个Eloquent模型, BaseModel提供Snowflake ID主键, 45个模型按表启用SoftDelete |
| 7.数据层 | MySQL(98表: 89张shop_前缀 + 9张wa_/snowflake主键) + Redis(缓存/Session/限流/Poster) + ES(多语言搜索) |
| 8.响应返回 | JSON统一格式 → HashidsEncode编码ID → Encryption加密(X-Encrypt-Response) → 返回客户端 |
| 9.熔断与降级 | Redis 熔断器(CircuitBreaker): 支付网关/社交登录外部API, 连续5次失败→熔断30s, 半开探测恢复; 业务异常白名单; Redis故障降级放行(503) |

### 2.2 进程模型

```
webman Master (:8787)
  ├── HTTP Worker x32 (CPU×4, 常驻内存, DB连接池)
  ├── Monitor Process (文件监控+内存监控)
  ├── SnowflakeWorker (启动时初始化Snowflake单例)
  └── 12 个 Cron/Task 进程 (汇率/物流/Feed/推荐/合规/退货/降价/支付对账/分账/隐私/订阅/未付款超时取消)
```

`app/process/` 共 15 个 PHP 文件，其中 12 个注册为常驻定时进程。

---

## 3. 中间件管道

### 3.1 Service API 完整管道

```mermaid
graph LR
    A[HTTP Request] --> B[Cors]
    B --> C[Security<br/>攻击检测]
    C --> D[RateLimit<br/>令牌桶限流]
    D --> E[Platform<br/>来源识别]
    E --> F[GeoIp<br/>区域识别]
    F --> G[Locale<br/>语言]
    G --> H[HashidsDecode<br/>ID解码]
    H --> I[VersionRoute<br/>版本路由]
    I --> J{敏感操作?}
    J -->|Yes| K[PosterVerify<br/>人机验证]
    J -->|No| L{JWT保护?}
    K --> L
    L -->|Yes| M[JwtAuth<br/>Token验证]
    L -->|No| N[Controller]
    M --> N
    N --> O[HashidsEncode<br/>响应 ID 编码]
    O --> P[Encryption<br/>响应加密]
    P --> Q[HTTP Response]
    style C fill:#fcc
    style D fill:#fcc
    style K fill:#ffc
    style M fill:#cfc
```

请求阶段：全局 Cors→Security→RateLimit→Platform→GeoIp→Locale→HashidsDecode→VersionRoute，随后进入路由级 PosterVerify/JwtAuth → Controller；响应阶段：HashidsEncode → Encryption → Response。3.2 表格按注册顺序（10 全局 + 2 路由）列出。

### 3.2 Service 中间件详情

| # | 中间件 | 类型 | 功能 |
|---|--------|------|------|
| 1 | Cors | 全局 | Access-Control-* 响应头, OPTIONS预检返回200 |
| 2 | SecurityMiddleware | 全局 | XSS/SQL注入/CRLF/路径遍历/Content-Type/请求体10MB |
| 3 | RateLimitMiddleware | 全局 | 令牌桶限流(Redis ZSET滑动窗口, 7端点规则+默认) |
| 4 | PlatformMiddleware | 全局 | X-Platform header + UA降级识别8个平台 |
| 5 | GeoIpMiddleware | 全局 | MaxMind GeoIP2 未登录用户区域/币种/语言识别 |
| 6 | LocaleMiddleware | 全局 | Accept-Language解析, 5语言精确匹配→降级→默认 |
| 7 | HashidsDecode | 全局 | URL/Body中 `*_id` 字段 hashid→snowflake ID |
| 8 | VersionRoute | 全局 | API-Version header→控制器命名空间(v1/v2)映射 |
| 9 | HashidsEncode | 全局 | 响应JSON递归遍历, snowflake ID→hashid |
| 10 | EncryptionMiddleware | 全局 | 接口AES加解密(X-Encrypt-Response/X-Encrypted) |
| 11 | PosterVerify | 路由 | 注册/下单/支付 Redis验证token |
| 12 | JwtAuth | 路由 | Bearer Token HS256验签+过期+userId注入 |
| 13 | AdminKeyMiddleware | 路由 | 内部管理操作密钥校验 |
| 14 | StaticFile | 全局 | webman 静态资源服务 |

### 3.3 Admin 管道

```
请求 → SecurityMiddleware → PlatformMiddleware → HashidsDecode
     → webman-admin AccessControl(内置RBAC) → HashidsEncode → 控制器
```

| # | Admin中间件 | 功能 |
|---|------------|------|
| 1 | SecurityMiddleware | XSS/SQL注入/CRLF/路径遍历/Content-Type/20MB |
| 2 | PlatformMiddleware | X-Platform + UA 8平台识别 |
| 3 | HashidsDecode | 请求 hashid→snowflake ID |
| - | AccessControl(内置) | 管理员角色权限验证 |
| 4 | HashidsEncode | 响应 snowflake ID→hashid |

---

## 4. 安全架构

### 4.1 攻击检测管道 (SecurityMiddleware)

```mermaid
graph TD
    A[HTTP Request] --> B{Content-Type OK?}
    B -->|No| R1[403 Forbidden]
    B -->|Yes| C{Body < Limit?}
    C -->|No| R2[413 Too Large]
    C -->|Yes| D{XSS Pattern?}
    D -->|Hit| R3[40001 XSS]
    D -->|Pass| E{SQLi Pattern?}
    E -->|Hit| R4[40002 SQLi]
    E -->|Pass| F{CRLF in Header?}
    F -->|Hit| R5[40003 CRLF]
    F -->|Pass| G{Path Traversal?}
    G -->|Hit| R6[40004 Path]
    G -->|Pass| H[Pass]
    style R1 fill:#fcc
    style R2 fill:#fcc
    style R3 fill:#fcc
    style R4 fill:#fcc
    style R5 fill:#fcc
    style R6 fill:#fcc
    style H fill:#cfc
```

### 4.2 SecurityMiddleware 攻击检测规则详情 (15类自定义)

| # | 攻击类型 | 主要检测方式 | Service | Admin | 错误码 |
|---|---------|------------|---------|-------|--------|
| 1 | XSS跨站脚本 | 13条正则: script/iframe/on事件/svg+on/style/expression/javascript:/embed/object/link/meta | ✅ | ✅ | 40001 |
| 2 | SQL注入 | 13条正则: UNION SELECT/SELECT FROM WHERE/sleep/benchmark/pg_sleep/布尔型/字符串型/注释符/MySQL特殊注释/schema枚举/load_file/into outfile/存储过程/waitfor/delay | ✅ | ✅ | 40002 |
| 3 | CRLF Header注入 | `[\r\n]` in: Authorization/X-Platform/API-Version/X-Forwarded-For/Referer/Origin | ✅ | ✅ | 40003 |
| 4 | 路径遍历 | `../` + `%2e%2f`编码 + `%252e%252f`二层编码 + null byte `\0` + `.env`/`.git`/`phpmyadmin`/`wp-admin`/`/etc/`/`/proc/`/`composer.json` | ✅ | ✅ | 40004 |
| 5 | 请求体限制 | Content-Length > 10MB(Service) / 20MB(Admin) | ✅ | ✅ | 40005 |
| 6 | Content-Type | 仅JSON/form-data/form-urlencoded | ✅ | ✅ | 40006 |
| 7 | 文件上传校验 | 黑名单扩展名(php/phtml/sh/exe/js/...)+双重扩展名+空扩展名 | ✅ | ✅ | 40009 |
| 8 | HTTP安全响应头 | nosniff/DENY/XSS-Protection/Referrer-Policy/Permissions-Policy/Cache-Control/Server隐藏 | ✅ | ✅ | — |
| 9 | 暴力破解防护 | Redis计数器: API 10次/60s, Admin 5次/300s | ✅ | ✅ | 40008 |
| 10 | XXE实体注入 | `<!ENTITY SYSTEM>`, `<!DOCTYPE [` | ✅ | ✅ | 40010 |
| 11 | SSRF服务器伪造 | 内网IP(127/10/172.16/192.168/0.0/169.254.169.254)+localhost+metadata.google.internal | ✅ | ✅ | 40011 |
| 12 | HTTP方法校验 | 仅 GET/POST/PUT/DELETE/PATCH/OPTIONS/HEAD | ✅ | ✅ | 40012 |
| 13 | Host头校验 | 拒绝裸IP直连 | ✅ | — | 40013 |
| 14 | 敏感数据脱敏 | 日志/错误响应过滤password/token/secret | ✅ | ✅ | — |
| 15 | CORS白名单 | 可配置origin限制 | ⚠️ | ⚠️ | — |

### 4.3 认证流程

```
注册: email+password → PosterVerify(人机验证) → bcrypt(password+salt)
     → Snowflake生成ID → 返回 JWT

登录: email+password → password_verify(password+salt, bcrypt_hash)
     → 更新last_login_at/ip/platform → 签发JWT

请求: Authorization: Bearer <token>
     → JwtAuth → Jwt::decode → 验签HS256+过期 → 注入request->userId

刷新: POST /api/auth/refresh {refresh_token} → Jwt::decode → 新access_token
```

### 4.4 数据安全 (三层加密)

| 层级 | 技术 | 包 | 字段 |
|------|------|-----|------|
| 传输层 | AES-256-CBC | erikwang2013/encryption | POST body敏感字段 |
| 数据库层 | Encryptable trait | erikwang2013/encryptable (Maize) | email, mobile, name, phone, detail, tax_id |
| ID混淆 | Hashids编码 | erikwang2013/hashids | 接口层所有snowflake ID |

### 4.5 平台来源追踪

| 平台 | 识别方式 | Header值 |
|------|---------|---------|
| iOS | Flutter `Platform.isIOS` + `TargetPlatform.iOS` | `ios` |
| iPadOS | Flutter `Platform.isIOS` + `!TargetPlatform.iOS` | `ipados` |
| macOS | Flutter `Platform.isMacOS` / UA `Macintosh` | `macos` |
| Windows | Flutter `Platform.isWindows` / UA `Windows` | `windows` |
| Linux | Flutter `Platform.isLinux` / UA `Linux` | `linux` |
| Android | Flutter `Platform.isAndroid` / UA `Android` | `android` |
| HarmonyOS | Flutter/ArkTS `ohos` / UA `HarmonyOS` | `harmonyos` |
| Web | UA无匹配 / 默认值 | `web` |

记录表: `erik_orders.platform`, `erik_payments.platform`, `erik_operation_logs.platform`, `erik_users.last_login_platform`, `erik_search_logs.platform`

---

## 5. 数据架构

### 5.1 主键策略

```
Snowflake 64bit: [1bit|42bit时间戳|5bitDC|5bitWID|12bit序号]
- 全局唯一 / 趋势递增 / 非自增
- PHP $keyType='string' (防溢出)
- Service worker_id=1, Admin worker_id=2
- 生成: Snowflake::nextId()
```

### 5.2 模型继承

```
Illuminate\Database\Eloquent\Model
  └── app\model\BaseModel
        ├── $incrementing=false, $keyType='string', $guarded=[]
        ├── boot(): Snowflake::nextId()
        └── 89业务模型
              ├── 45个 use SoftDeletes (对应有 deleted_at 列的表)
              ├── 部分 use Encryptable (敏感字段: email/mobile/name等)
              ├── use Searchable (Product→ES)
              └── hasMany/belongsTo 关联
```

### 5.3 多语言/多币种

- **翻译**: `erik_product_translations(product_id,locale)` 独立表，按locale查询
- **定价**: `erik_product_sku_prices(sku_id,currency_code)` 按币种独立价格

---

## 6. 支付架构

```mermaid
sequenceDiagram
    participant C as Client
    participant S as Service
    participant G as Gateway
    participant W as Webhook
    C->>S: GET /api/payment/methods
    S-->>C: 方式列表
    C->>S: POST /api/payment/create
    S->>G: PaymentGateway::make(code)
    G-->>S: txn_id + client_secret
    S-->>C: client_secret
    C->>G: SDK支付+3DS
    G->>W: 异步通知
    W->>S: 验签→更新Payment→更新Order→分账
```

```
PaymentGatewayInterface
  ├── createPayment(data): array
  ├── capturePayment(txnId): array
  ├── refundPayment(txnId, amount): array
  └── verifyWebhook(payload, sig): bool
```

### 6.1 统一入账与支付重试

- Webhook 与 `PaymentReconcileCron` 共用 `PaymentSuccessService`：订单 0→1、支付 0→1、生成 `PlatformSettlements`、累加 `Products/ProductSkus.sales_count`；迟到支付只记 warning，不重复入账。
- 支付创建锁 TTL 120s（覆盖 Stripe/PayPal 网关耗时）；`client_secret` 存 `gateway_data`，重试可复用，避免锁过期后读到未落库的待支付单。
- 真实退款走 `/api/admin/refunds/{id}/execute`，`/reject` 保留；已删除 `/approve` 伪入口。

### 6.2 订单取消、定价与订阅建单

- `OrderCancellation`：用户取消、超时取消、风控驳回共用；原子状态门闩 + 库存恢复 + 优惠券恢复/`used_qty` 回退 + 订单/库存日志。
- `OrderPricing`：运输分区运费按 `product.weight × qty` 计算；关税/VAT 整单判断免税阈值，VAT 基数含 duty；下单与订阅共用。
- `SubscriptionOrderService`：首期/续费统一建单；`address_id` 必填并写地址快照、USD 报价、商品上架校验、原子扣库存、重量运费税费。
- 风控默认 `bypass` 仅打分；仅 `block` 模式下 `review` 订单置 `status=8`，审核在支付前；`reject` 经 `OrderCancellation` 恢复库存/券。

---

## 7. 高并发架构

### 7.1 限流策略 (RateLimitMiddleware)

```mermaid
graph LR
    A[Request] --> B{匹配规则?}
    B -->|Yes| C[Redis ZSET<br/>滑动窗口计数]
    B -->|No| D[默认规则<br/>60s/100次]
    C --> E{超限?}
    D --> C
    E -->|Yes| F[429 Retry-After]
    E -->|No| G[Pass]
```

| 端点 | 窗口 | 限制 | 说明 |
|------|------|------|------|
| /api/auth/login | 60s | 10次 | 防撞库 |
| /api/auth/password/reset | 300s | 5次 | 防密码重置滥用（另有每邮箱 5 次失败锁定） |
| /api/auth/register | 300s | 5次 | 防批量注册 |
| /api/auth/social | 300s | 5次 | 防社交登录枚举/爆破 |
| /api/payment | 60s | 5次 | 防盗刷 |
| /api/orders | 10s | 3次 | 防刷单 |
| /api/search | 1s | 10次 | 防爬虫 |
| 默认 | 60s | 100次 | 通用API |

### 7.2 Redis 用途

Redis 用于限流令牌桶、人机验证码与 Session 存储（中间件层）以及 CDN 全局开关传播（prefix `shop:`，60s TTL）；业务数据不做应用层缓存，直接读取 MySQL（读写分离 + 连接池）；静态资源（上传图片/文档）由 `Cdn::url()` 在输出边界重写为 CDN 域名，经 CDN 边缘缓存（`expires 7d, immutable`）分发。

### 7.4 连接池优化

| 资源 | 最大连接 | 最小连接 | 等待超时 | 空闲超时 | 心跳 |
|------|---------|---------|---------|---------|------|
| MySQL | 50 | 10 | 2s | 60s | 45s |
| Redis | 30 | 5 | — | 60s | — |

### 7.5 慢操作处理

| 操作 | 实现 |
|------|------|
| 汇率更新 | ExchangeRateCron（每小时，外部 API） |
| Feed 同步 | ProductFeedCron（每 6 小时生成 TSV 并记录日志） |
| 推荐计算 | RecommendationCron（每日，购买共现） |
| 支付对账 | PaymentReconcileCron（每 6 小时，Stripe/PayPal，共用 PaymentSuccessService 入账） |
| 分账结算 | SettlementCron（每日，whereNotExists + chunkById(500)，单笔事务 + 订单行锁 + 已有分账复查） |
| 未付款订单超时取消 | UnpaidOrderCron（每 5 分钟，取消超过 30 分钟的 status=0 订单；跳过有进行中网关支付的订单） |
| 物流轨迹 | ShipmentTrackingCron（每 30 分钟，需配置 API） |
| 平台订单同步 | PlatformOrderSyncCron（每 5 分钟，需配置 API） |
| 退货超时 | ReturnExpireCron（每小时） |
| 降价/到货通知 | PriceAlertCron（每 10 分钟） |
| 合规规则更新 | ComplianceCron（每日，需配置 API） |

## 8. 部署架构

```
docker-compose.yml:
  CDN (Cloudflare/CloudFront/阿里云/腾讯云) — CNAME 回源 admin 域名
  nginx (alpine) :80 :443
  service (php:8.3) :8787 internal, 32 workers
  admin (php:8.3) :8788 internal
  mysql (8.0) :3306 / redis (7) :6379 / es (8) :9200
网络: erik-net bridge | 数据卷持久化 (erik_data/erik_redis/erik_es/erik_logs + admin_uploads:/app/plugin/admin/public/upload + service_public:/app/public/documents)
路由: api.erik.xyz→service | admin.erik.xyz→admin | CDN_DOMAIN→admin(回源)
```

---

## 8. 国际化 (i18n)

| 层级 | 实现 |
|------|------|
| Service | LocaleMiddleware + 5语言翻译文件(45 key/语言) |
| Admin | 5语言翻译文件 |
| Flutter | AppLocalizations + Riverpod Provider |
| API | Accept-Language header 自动注入 |

## 9. API文档 (hg/apidoc)

| 组件 | 说明 |
|------|------|
| 包 | hg/apidoc v5.3 |
| 配置 | config/plugin/hg/apidoc/app.php (6分组) |
| 注解 | @Apidoc\Title/Desc/Method/Url/Param/Returned |
| 访问 | http://localhost:8787/apidoc/ |

## 11. 测试

```bash
cd service && php vendor/bin/phpunit tests/
```

| 测试文件 | Tests | 覆盖 |
|--------|-------|------|
| HttpParsingTest / MoneyTest（单元） | 49 | 请求解析、金额运算 |
| OrderCancellationTest（集成） | — | 取消/库存/券恢复（需 MySQL schema） |
| OrderPricingTest（集成） | — | 分区运费/关税/VAT（需 MySQL schema） |
| PaymentSuccessServiceTest（集成） | — | 统一入账/幂等（需 MySQL schema） |
| **Total** | **49 单元 / 110 断言** | 本地通过；3 组集成本地未运行；CI PHP 8.3/8.4 |

历史口径（2026-08-04）：22 tests / 45 assertions（SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3）。

---

## 12. 项目统计

| 维度 | 数量 |
|------|------|
| PHP源文件 | service:262 + admin:232 = 494 |
| Dart (Flutter) | 25 |
| 数据库表 | 98 (89 shop_ + 9 wa_) |
| API端点 | 89 |
| 中间件 | 14 |
| 工具类 | 25 |
| 定时任务 | 12 |
| 配置项 | 36+ |
| 测试 | 6 测试文件, 49 单元 / 110 断言 |
| Skills | 38 |
| 文档 | 15 |
| **总计** | **~750** |

---

## 13. 2026-10-30 一致性与安全修复（v1.5.7）

- 订单取消统一走 `OrderCancellation`（用户/超时/风控驳回），原子状态门闩 + 库存/优惠券恢复；新增 `UnpaidOrderCron` 每 5 分钟取消超 30 分钟未付款订单，跳过有进行中网关支付的订单。
- 支付入账统一走 `PaymentSuccessService`（webhook 与 PaymentReconcileCron 共用）；支付创建锁 TTL 120s，`client_secret` 落 `gateway_data` 支持重试复用。
- `SettlementCron` 改为 `whereNotExists` + `chunkById(500)`，单笔 `Db::transaction` + 订单行锁 + 已有分账复查。
- `OrderPricing` 统一运费与关税/VAT（整单免税阈值，VAT 基数含 duty）；`SubscriptionOrderService` 统一首期/续费建单（地址快照、USD 报价、原子扣库存）。
- 异常分类：`BusinessException` 422、`LockTimeoutException` 429，其他 500 不泄露 SQL。
- 所有 API 响应 `Cache-Control: no-store, no-cache, must-revalidate`；密码重置 300s/5 次 + 每邮箱 5 次失败锁定。
- 移除仓库示例加密密钥，安装器为 service/admin 生成同一把随机密钥；历史使用示例密钥的部署需轮换并重加密。
- 删除 `/api/admin/refunds/{id}/approve`，真实退款使用 `/execute`，`/reject` 保留。
