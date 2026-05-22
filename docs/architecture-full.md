# 跨境电商平台 — 架构设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 系统概述

### 1.1 定位

基于 webman 高性能框架的全栈跨境电商平台，支持 B2C、B2B、第三方卖家入驻。

| 组件 | 技术栈 | 规模 |
|------|--------|------|
| Service API | PHP 8.3 / webman 2.1 / illuminate database | 36控制器 + 112模型 + 9中间件 |
| Admin | webman-admin / LayUI / ECharts | 67控制器 + 65模型 + 5中间件 |
| Flutter | Riverpod / GoRouter / Dio | 25 Dart文件 / 12页面 |
| HarmonyOS | ArkTS / ArkUI | 12 ETS文件 / 8页面 |
| 数据库 | MySQL 8.0 + Redis 7 + ES 8 | 110张表 |

### 1.2 核心指标

| 指标 | 值 |
|------|-----|
| API P99 | <200ms |
| 并发 | 10000+ (32 worker常驻内存) |
| 表数 | 110 |
| 端点 | 71 |
| 中间件 | 11 (service:9全局+2路由 / admin:5全局) |
| 语言 | zh_CN, zh_HK, en, ja, ko |
| 币种 | 19种独立定价 |
| 支付 | Stripe / PayPal / Klarna / Adyen |

---

## 2. 系统架构图

```mermaid
graph TD
    subgraph Clients[客户端层]
        F[Flutter 5平台<br/>iOS Android macOS Win Linux]
        H[HarmonyOS ArkTS]
        W[Web Browser Admin]
    end
    subgraph Gateway[接入层]
        N[Nginx :80/:443]
    end
    subgraph Apps[应用层]
        S[Service API :8787<br/>36 Controllers 112 Models 9 MW]
        A[Admin :8787<br/>67 Controllers 65 Models 5 MW]
    end
    subgraph Data[数据层]
        M[(MySQL 8.0 :3306<br/>110 tables erik_)]
        R[(Redis 7 :6379<br/>cache session limit)]
        E[(ES 8 :9200<br/>multilingual search)]
    end
    F --> N
    H --> N
    W --> N
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
        HM[HarmonyOS: ArkTS]
        WB[Web Browser: Admin]
    end
    subgraph Gateway["2. 接入层 Nginx :80"]
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
    subgraph Pipeline["4. 中间件管道 8全局+2路由"]
        CORS[Cors] --> PLAT[Platform]
        PLAT --> GEO[GeoIp]
        GEO --> LOC[Locale]
        LOC --> HDEC[HashidsDecode]
        HDEC --> VER[VersionRoute]
        VER --> POSTV[PosterVerify 路由级]
        POSTV --> JWT[JwtAuth 路由级]
        JWT --> HENC[HashidsEncode]
    end
    subgraph Controllers["5. 控制器 36个"]
        AUTH[Auth] & PROD[Product] & CART[Cart]
        ORD[Order] & PAY[Payment] & SHIP[Shipping]
        TARI[Tariff] & USER[User] & COUP[Coupon]
        RET[Return] & NOTI[Notify] & EXPORT[Export]
    end
    subgraph Models["6. 模型层 112 Models"]
        BM[BaseModel: Snowflake+Encryptable+SoftDelete]
        REL[Relations: hasMany/belongsTo 68FK]
        SRCH[Searchable: ES同步 多语言分词]
    end
    subgraph Data["7. 数据层"]
        MySQL[(MySQL 8.0<br/>110 tables<br/>erik_ prefix)]
        Redis[(Redis 7<br/>cache/session<br/>limit/poster)]
        ES[(ES 8<br/>multilingual<br/>search)]
    end
    subgraph Response["8. 响应返回"]
        JSON[JSON: code msg data]
        OUTENC[HashidsEncode: ID编码]
        HEADERS[Headers: CORS X-Platform]
    end

    FL & HM & WB --> NG
    NG --> CORS
    PASS --> PLAT
    HENC --> AUTH & PROD & CART & ORD & PAY & SHIP & TARI & USER & COUP & RET & NOTI & EXPORT
    AUTH & PROD & ORD --> BM
    ORD --> REL
    PROD --> SRCH
    BM --> MySQL & Redis
    SRCH --> ES
    MySQL & Redis & ES --> JSON
    JSON --> OUTENC --> HEADERS
    HEADERS --> FL & HM & WB

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
| 1.客户端层 | Flutter 5平台 + HarmonyOS + Web Admin, 全部通过 HTTP/JSON 通信 |
| 2.接入层 | Nginx 按域名分流: api→service, admin→admin |
| 3.安全层 | SecurityMiddleware 6道检测门, 任一道命中即返回错误码 |
| 4.中间件管道 | 8个全局MW串行处理 + 2个路由级MW(PosterVerify敏感操作, JwtAuth认证接口) |
| 5.控制器层 | 36个API控制器按功能分组, 处理全部业务逻辑 |
| 6.模型层 | 112个Eloquent模型, BaseModel提供Snowflake ID/Encryptable/SoftDelete, 68表外键关联 |
| 7.数据层 | MySQL(110表erik_前缀/snowflake主键) + Redis(缓存/Session/限流/Poster) + ES(多语言搜索) |
| 8.响应返回 | JSON统一格式 → HashidsEncode编码ID → CORS/X-Platform Headers → 返回客户端 |

### 2.2 进程模型

```
webman Master (:8787)
  ├── HTTP Worker x32 (CPU×4, 常驻内存, DB连接池)
  ├── Monitor Process (文件监控+内存监控)
  └── SnowflakeWorker (启动时初始化Snowflake单例)
```

---

## 3. 中间件管道

### 3.1 Service API 完整管道

```mermaid
graph LR
    A[HTTP Request] --> B[Cors]
    B --> C[Security<br/>攻击检测]
    C --> D[Platform<br/>来源识别]
    D --> E[GeoIp<br/>区域识别]
    E --> F[Locale<br/>语言]
    F --> G[HashidsDecode<br/>ID解码]
    G --> H[VersionRoute<br/>版本路由]
    H --> I{敏感操作?}
    I -->|Yes| J[PosterVerify<br/>人机验证]
    I -->|No| K{JWT保护?}
    J --> K
    K -->|Yes| L[JwtAuth<br/>Token验证]
    K -->|No| M[HashidsEncode<br/>ID编码]
    L --> M
    M --> N[Controller]
    N --> O[HTTP Response]
    style C fill:#fcc
    style J fill:#ffc
    style L fill:#cfc
```

### 3.2 Service 中间件详情

| # | 中间件 | 类型 | 功能 |
|---|--------|------|------|
| 1 | Cors | 全局 | Access-Control-* 响应头, OPTIONS预检返回200 |
| 2 | SecurityMiddleware | 全局 | XSS/SQL注入/CRLF/路径遍历/Content-Type/请求体10MB |
| 4 | PlatformMiddleware | 全局 | X-Platform header + UA降级识别8个平台 |
| 5 | GeoIpMiddleware | 全局 | MaxMind GeoIP2 未登录用户区域/币种/语言识别 |
| 6 | LocaleMiddleware | 全局 | Accept-Language解析, 5语言精确匹配→降级→默认 |
| 7 | HashidsDecode | 全局 | URL/Body中 `*_id` 字段 hashid→snowflake ID |
| 8 | VersionRoute | 全局 | API-Version header→控制器命名空间(v1/v2)映射 |
| 9 | PosterVerify | 路由 | 注册/下单/支付 Redis验证token |
| 10 | JwtAuth | 路由 | Bearer Token HS256验签+过期+userId注入 |
| 11 | HashidsEncode | 全局 | 响应JSON递归遍历, snowflake ID→hashid |

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

