# 跨境电商平台 — 架构图集

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 一、系统架构图

```mermaid
graph TB
    subgraph Clients["客户端层 Client Layer"]
        direction LR
        FL["Flutter App<br/>iOS · Android · macOS<br/>Windows · Linux · iPadOS<br/>Riverpod + GoRouter + Dio"]
        HM["HarmonyOS App<br/>ArkTS + ArkUI<br/>API 12+"]
        WB["Web Admin<br/>LayUI + ECharts<br/>管理后台"]
    end

    subgraph Gateway["接入层 Gateway"]
        NG["Nginx :80 / :443<br/>api.erik.xyz → Service<br/>admin.erik.xyz → Admin<br/>静态资源 + SSL 终端"]
    end

    subgraph Apps["应用层 Application"]
        subgraph Service["Service API (webman) :8787"]
            direction TB
            MW["全局中间件 9个<br/>Cors → Security → Platform<br/>→ GeoIp → Locale<br/>→ HashidsDecode → VersionRoute<br/>→ HashidsEncode"]
            RT["路由中间件 2个<br/>PosterVerify · JwtAuth"]
            CTRL["37 控制器 7组<br/>Auth · Product · Cart · Order<br/>Payment · Shipping · Tariff<br/>User · Coupon · Return<br/>Notify · Export · Affiliate<br/>B2b · Cms · Faq · FlashSale<br/>GroupBuy · GiftCard · Review<br/>Search · SocialAuth · Settings"]
            MDL["112 模型 Model<br/>BaseModel: Snowflake ID<br/>+ Encryptable 加密<br/>+ Searchable ES 同步<br/>+ SoftDelete 软删除<br/>68 表外键关联"]
        end
        subgraph Admin["Admin (webman-admin) :8788"]
            direction TB
            AMW["中间件 5个<br/>Security → Platform<br/>→ HashidsDecode<br/>→ AccessControl RBAC<br/>→ HashidsEncode"]
            ACTRL["67 控制器<br/>商品 · 订单 · 用户 · 营销<br/>物流 · 供应链 · 风控<br/>CMS · 客服 · 系统"]
            AMDL["65 模型"]
        end
    end

    subgraph Data["数据层 Data"]
        direction LR
        MySQL[("MySQL 8.0 :3306<br/>110 表 · erik_ 前缀<br/>Snowflake bigint PK<br/>读写分离 2读副本")]
        Redis[("Redis 7 :6379<br/>Cache-Aside 缓存<br/>Session · 限流令牌桶<br/>Poster 验证 · 分布式锁")]
        ES[("Elasticsearch 8 :9200<br/>多语言分词搜索<br/>商品 · 分类 · CMS")]
    end

    subgraph External["外部服务 External"]
        direction LR
        Pay["支付网关<br/>Stripe · PayPal<br/>Klarna · Adyen<br/>Webhook 异步通知"]
        Geo["GeoIP<br/>MaxMind GeoIP2<br/>区域 · 币种识别"]
        Email["邮件服务<br/>SMTP 异步队列<br/>多语言模板"]
    end

    FL --> NG
    HM --> NG
    WB --> NG
    NG --> Service
    NG --> Admin
    Service --> MySQL
    Service --> Redis
    Service --> ES
    Service --> Pay
    Service --> Geo
    Service --> Email
    Admin --> MySQL
    Admin --> Redis

    style Clients fill:#e1f5fe
    style Gateway fill:#fff3e0
    style Apps fill:#f3e5f5
    style Service fill:#e8f5e9
    style Admin fill:#fff9c4
    style Data fill:#fce4ec
    style External fill:#e0f2f1
```

---

## 二、请求处理流程图（中间件管道）

```mermaid
graph TB
    START(["HTTP 请求到达"]) --> CORS["① Cors<br/>Access-Control-* 响应头<br/>OPTIONS 预检返回 200"]

    CORS --> SEC["② Security 攻击检测<br/>━━━━━━━━━━━━━━━"]
    SEC --> SEC1{"Content-Type<br/>合法?"}
    SEC1 -->|否| E403["403 Forbidden"]
    SEC1 -->|是| SEC2{"Body Size<br/>≤ 10MB?"}
    SEC2 -->|否| E413["413 Too Large"]
    SEC2 -->|是| SEC3{"XSS 检测<br/>18条规则"}
    SEC3 -->|命中| E40001["40001 XSS 拦截"]
    SEC3 -->|通过| SEC4{"SQLi 检测<br/>20条规则"}
    SEC4 -->|命中| E40002["40002 SQLi 拦截"]
    SEC4 -->|通过| SEC5{"CRLF 检测<br/>Header 注入"}
    SEC5 -->|命中| E40003["40003 CRLF 拦截"]
    SEC5 -->|通过| SEC6{"路径遍历<br/>检测"}
    SEC6 -->|命中| E40004["40004 Path 拦截"]
    SEC6 -->|通过| SEC_PASS["安全检测通过"]

    SEC_PASS --> PLAT["③ Platform<br/>X-Platform Header<br/>+ UA 降级识别<br/>8 平台来源"]

    PLAT --> GEO["④ GeoIp<br/>MaxMind GeoIP2<br/>未登录用户<br/>区域/币种/语言识别"]

    GEO --> LOCALE["⑤ Locale<br/>Accept-Language 解析<br/>5 语言精确匹配 → 降级 → 默认"]

    LOCALE --> HDEC["⑥ HashidsDecode<br/>请求中 *_id 字段<br/>hashid → snowflake ID"]

    HDEC --> VER["⑦ VersionRoute<br/>API-Version Header<br/>→ v1/v2 命名空间路由"]

    VER --> RATE["⑧ RateLimit<br/>Redis ZSET 滑动窗口<br/>令牌桶限流"]

    RATE -->|超限| E429["429 Too Many Requests<br/>Retry-After 响应头"]
    RATE -->|通过| ROUTE_CHECK{"路由匹配?"}

    ROUTE_CHECK -->|敏感操作<br/>注册/下单/支付| POSTER["⑨ PosterVerify<br/>Redis 验证 token<br/>滑块/拼图/点击"]
    ROUTE_CHECK -->|需认证| JWT_START["⑩ JwtAuth"]
    ROUTE_CHECK -->|公开接口| HENC

    POSTER --> JWT_START

    JWT_START --> JWT_CHECK{"Bearer Token<br/>HS256 验签"}
    JWT_CHECK -->|失效/过期| E401["401 Unauthorized"]
    JWT_CHECK -->|有效| JWT_INJECT["注入 request→userId<br/>检查黑名单"]

    JWT_INJECT --> HENC["⑪ HashidsEncode<br/>响应 JSON 递归遍历<br/>snowflake ID → hashid"]

    HENC --> CTRL["Controller<br/>业务逻辑处理<br/>Model 查询 · 验证 · 计算"]

    CTRL --> ENC_RESP{"需加密响应?"}

    ENC_RESP -->|是| ENC["⑫ Encryption<br/>AES-256-CBC 加密<br/>X-Encrypted: 1 Header"]
    ENC_RESP -->|否| RESP
    ENC --> RESP(["JSON Response<br/>{code, msg, data}<br/>+ CORS Headers<br/>+ X-Platform Header"])

    style SEC fill:#ffcdd2
    style RATE fill:#fff9c4
    style POSTER fill:#ffe0b2
    style JWT_START fill:#c8e6c9
    style CTRL fill:#bbdefb
    style START fill:#e8eaf6
    style RESP fill:#e8eaf6
    style E403 fill:#ff5252,color:#fff
    style E413 fill:#ff5252,color:#fff
    style E40001 fill:#ff5252,color:#fff
    style E40002 fill:#ff5252,color:#fff
    style E40003 fill:#ff5252,color:#fff
    style E40004 fill:#ff5252,color:#fff
    style E429 fill:#ff9800,color:#fff
    style E401 fill:#ff5252,color:#fff
```

---

## 三、功能模块全景图

```mermaid
graph TB
    CENTER["Erik Shop<br/>跨境电商平台 Full"]

    CENTER --> B2C["B2C 零售"]
    CENTER --> B2B["B2B 批发"]
    CENTER --> MART["多商家入驻"]
    CENTER --> CROSS["跨境合规"]
    CENTER --> LOGISTICS["国际物流"]
    CENTER --> PAY["支付系统"]
    CENTER --> MKT["营销中心"]
    CENTER --> MULTI["多平台管理"]
    CENTER --> SUPPLY["供应链"]
    CENTER --> RISK["风控合规"]
    CENTER --> SECURITY["安全防护"]
    CENTER --> PERF["高并发"]
    CENTER --> GROWTH["会员增长"]
    CENTER --> CMS["内容管理"]
    CENTER --> CS["客服系统"]
    CENTER --> INFRA["基础设施"]
    CENTER --> CLIENTS["多端覆盖"]

    B2C --> B2C_DETAIL["多语言商品 · 分币种定价<br/>SKU 库存 · 购物车<br/>订单 · 退款 · 退货"]

    B2B --> B2B_DETAIL["阶梯定价 MOQ<br/>企业认证 税号/营业执照<br/>询价 报价"]

    MART --> MART_DETAIL["卖家审核入驻<br/>商品审核上架<br/>分成分账结算"]

    CROSS --> CROSS_DETAIL["HS Code 编码库<br/>关税规则 目的国+HS→税率<br/>VAT/IOSS 欧盟<br/>合规标签 FDA/CE/RoHS"]

    LOGISTICS --> LOGISTICS_DETAIL["物流分区运费 重量阶梯<br/>DHL/UPS/FedEx/EMS<br/>海外仓 发货+退货<br/>商业发票/装箱单 PDF"]

    PAY --> PAY_DETAIL["Stripe PaymentIntent<br/>PayPal REST API<br/>Klarna BNPL 先买后付<br/>Adyen 全球支付<br/>3DS 验证 · Webhook 分账"]

    MKT --> MKT_DETAIL["优惠券 分区+新老客<br/>轮播图 区域可见<br/>秒杀 限时限量<br/>拼团 成团人数+有效期<br/>分销 链接+佣金+提现"]

    MULTI --> MULTI_DETAIL["Amazon · eBay<br/>Shopee · Lazada · Temu<br/>商品刊登 · 订单聚合<br/>多店铺管理"]

    SUPPLY --> SUPPLY_DETAIL["供应商档案+评级<br/>采购单 审核→收货→质检<br/>库存流水 不可变账本<br/>调拨管理"]

    RISK --> RISK_DETAIL["规则引擎 旁路打分<br/>KYC 实名认证<br/>GDPR/CCPA 数据请求<br/>Cookie Consent 版本管理"]

    SECURITY --> SECURITY_DETAIL["15类攻击检测<br/>XSS · SQL注入 · CRLF<br/>路径遍历 · XXE · SSRF<br/>文件上传 · 暴力破解<br/>Host · CORS · 脱敏"]

    PERF --> PERF_DETAIL["令牌桶限流 滑动窗口<br/>Cache-Aside 防雪崩+穿透<br/>熔断器 5次→60s<br/>DB读写分离 2读副本<br/>连接池 DB 50/10 · Redis 30/5"]

    GROWTH --> GROWTH_DETAIL["会员等级+权益<br/>积分规则+流水<br/>礼品卡 余额+兑换<br/>降价/到货提醒<br/>AB测试 流量分配+置信度"]

    CMS --> CMS_DETAIL["CMS 多语言页面<br/>FAQ · 知识库<br/>尺码对照表 服装/鞋类<br/>邮件模板 多语言<br/>商品Feed Google/Meta"]

    CS --> CS_DETAIL["WebSocket 实时 IM<br/>chat_sessions<br/>chat_messages<br/>知识库多语言"]

    INFRA --> INFRA_DETAIL["Snowflake 分布式 ID<br/>Hashids 接口混淆<br/>JWT HS256 + 黑名单<br/>AES-256-CBC 加密<br/>Encryptable 数据库加密<br/>Poster 人机验证<br/>GeoIP 区域识别"]

    CLIENTS --> CLIENTS_DETAIL["Flutter 5平台<br/>iOS · Android · macOS<br/>Windows · Linux · iPadOS<br/>HarmonyOS ArkTS<br/>Web Admin LayUI+ECharts"]

    style CENTER fill:#1565c0,color:#fff,stroke:#0d47a1
    style B2C fill:#e3f2fd
    style B2B fill:#e8f5e9
    style MART fill:#fff3e0
    style CROSS fill:#fce4ec
    style LOGISTICS fill:#f3e5f5
    style PAY fill:#e0f2f1
    style MKT fill:#fff9c4
    style MULTI fill:#ede7f6
    style SUPPLY fill:#efebe9
    style RISK fill:#ffebee
    style SECURITY fill:#ffcdd2
    style PERF fill:#b3e5fc
    style GROWTH fill:#c8e6c9
    style CMS fill:#ffe0b2
    style CS fill:#d1c4e9
    style INFRA fill:#b2dfdb
    style CLIENTS fill:#bbdefb
```

---

## 四、请求生命周期图

```mermaid
sequenceDiagram
    participant Client as 客户端<br/>Flutter/HarmonyOS/Web
    participant Nginx as Nginx :80/:443
    participant Security as Security<br/>Middleware
    participant Pipeline as 中间件管道<br/>9 Global MW
    participant RateLimit as RateLimit<br/>Middleware
    participant Route as 路由分发
    participant Poster as PosterVerify
    participant JWT as JwtAuth
    participant Controller as Controller
    participant Model as Model<br/>BaseModel
    participant MySQL as MySQL 8.0
    participant Redis as Redis 7
    participant ES as ES 8
    participant Response as Response<br/>HashidsEncode

    Client->>Nginx: HTTP Request<br/>api.erik.xyz/api/v1/...
    Nginx->>Security: 转发请求

    rect rgb(255, 205, 210)
        Note over Security: 安全检测阶段
        Security->>Security: ① Content-Type 校验
        Security->>Security: ② Body Size ≤ 10MB
        Security->>Security: ③ XSS 检测 18规则
        Security->>Security: ④ SQLi 检测 20规则
        Security->>Security: ⑤ CRLF Header 注入
        Security->>Security: ⑥ Path Traversal
    end

    alt 攻击命中
        Security-->>Client: 4xxxx 错误响应
    end

    Security->>Pipeline: 安全通过

    rect rgb(227, 242, 253)
        Note over Pipeline: 请求预处理阶段
        Pipeline->>Pipeline: ⑦ Cors 响应头设置
        Pipeline->>Pipeline: ⑧ Platform 来源识别
        Pipeline->>Pipeline: ⑨ GeoIp 区域/币种
        Pipeline->>Pipeline: ⑩ Locale 语言设置
        Pipeline->>Pipeline: ⑪ HashidsDecode ID解码
        Pipeline->>Pipeline: ⑫ VersionRoute API版本
    end

    Pipeline->>RateLimit: 限流检查

    rect rgb(255, 249, 196)
        Note over RateLimit: 令牌桶限流
        RateLimit->>Redis: ZADD + ZREMRANGEBYSCORE<br/>滑动窗口计数
        Redis-->>RateLimit: 当前窗口请求数
    end

    alt 超限
        RateLimit-->>Client: 429 Retry-After
    end

    RateLimit->>Route: 通过

    Route->>Route: API-Version → 命名空间<br/>Controller 匹配

    alt 敏感操作 注册/下单/支付
        Route->>Poster: 人机验证
        Poster->>Redis: GET poster_token:{key}
        Redis-->>Poster: token
        alt 验证失败
            Poster-->>Client: 40007 验证失败
        end
    end

    alt 需认证接口
        Route->>JWT: Bearer Token
        JWT->>JWT: HS256 验签 + 过期检查
        JWT->>Redis: SISMEMBER jwt_blacklist
        Redis-->>JWT: 黑名单状态
        alt Token 无效
            JWT-->>Client: 401 Unauthorized
        end
        JWT->>Controller: request→userId
    end

    Route->>Controller: 业务处理

    rect rgb(200, 230, 201)
        Note over Controller,ES: 业务逻辑处理
        Controller->>Model: 调用 Model
        Model->>Model: Snowflake ID 生成
        Model->>Model: Encryptable 加密/解密
        Model->>MySQL: 读写操作
        MySQL-->>Model: 结果
        Model->>Redis: 缓存读写 Cache-Aside
        Redis-->>Model: 缓存数据
        Model->>ES: 搜索/索引
        ES-->>Model: 搜索结果
        Model-->>Controller: 业务数据
    end

    Controller->>Response: 返回数据

    rect rgb(187, 222, 251)
        Note over Response: 响应后处理
        Response->>Response: HashidsEncode ID编码
        Response->>Response: AES 加密敏感字段
        Response->>Response: JSON {code, msg, data}
    end

    Response->>Nginx: HTTP Response
    Nginx->>Client: JSON + CORS Headers
```

---

## 五、订单生命周期图

```mermaid
stateDiagram-v2
    [*] --> 购物车: 添加商品

    购物车 --> 待支付: 提交订单<br/>POST /api/orders
    购物车 --> [*]: 清空/过期

    待支付 --> 已取消: 超时未付<br/>30min 自动取消
    待支付 --> 已取消: 用户取消
    待支付 --> 支付中: 发起支付<br/>POST /api/payment/create

    支付中 --> 待支付: 支付失败
    支付中 --> 已支付: 支付成功<br/>Webhook 确认

    已支付 --> 待审核: 风控审核<br/>规则引擎评分
    已支付 --> 拣货中: 审核通过

    待审核 --> 拣货中: 审核通过
    待审核 --> 已取消: 审核拒绝 → 退款

    拣货中 --> 已发货: 出库完成<br/>生成运单号

    已发货 --> 运输中: 物流揽收
    已发货 --> 已发货: 更新物流轨迹

    运输中 --> 海关查验: 到达目的国
    海关查验 --> 运输中: 查验放行
    海关查验 --> 海关扣留: 查验异常
    海关扣留 --> 运输中: 补充资料通过

    运输中 --> 已签收: 客户签收
    运输中 --> 配送异常: 地址/联系失败

    配送异常 --> 运输中: 重新配送
    配送异常 --> 已退回: 退回寄件人

    已签收 --> 已完成: 自动确认<br/>7天无售后

    已签收 --> 退款中: 申请退货退款
    退款中 --> 待退货: 商家同意

    待退货 --> 退货中: 客户发货
    退货中 --> 已退回: 仓库签收
    已退回 --> 退款中: 质检通过
    已退回 --> 已签收: 质检不通过 → 退回客户

    退款中 --> 已退款: 退款完成<br/>原路返回

    已完成 --> [*]
    已取消 --> [*]
    已退款 --> [*]

    note right of 待支付: 库存预占 30min
    note right of 支付中: Stripe/PayPal/Klarna/Adyen
    note right of 已支付: 库存扣减 永久
    note right of 运输中: Webhook 推送更新
    note right of 已签收: 7天自动确认收货
```

---

## 六、部署架构图

```mermaid
graph TB
    subgraph Internet["互联网"]
        User["用户 全球访问"]
    end

    subgraph VPS["云服务器 / VPS"]
        subgraph Docker["Docker Compose"]
            direction TB

            subgraph Net["erik-net Bridge Network"]
                direction TB

                NGX["Nginx :80 :443<br/>alpine<br/>反向代理 + SSL 终端<br/>静态资源缓存"]

                subgraph PHP_Service["Service Container"]
                    SVC["PHP 8.3 + webman 2.1<br/>:8787 内部端口<br/>32 Worker 常驻内存<br/>OPCache 256MB<br/>Snowflake Worker ID=1"]
                end

                subgraph PHP_Admin["Admin Container"]
                    ADM["PHP 8.3 + webman-admin<br/>:8788 内部端口<br/>LayUI + ECharts<br/>内置 RBAC<br/>Snowflake Worker ID=2"]
                end

                subgraph DB["数据库容器"]
                    MYSQL[("MySQL 8.0 :3306<br/>110 表 erik_ 前缀<br/>erik_data 数据卷<br/>character-set utf8mb4")]
                    REDIS[("Redis 7 :6379<br/>erik_redis 数据卷<br/>maxmemory 512mb<br/>allkeys-lru 淘汰")]
                    ES[("Elasticsearch 8 :9200<br/>erik_es 数据卷<br/>多语言分词<br/>IK Analyzer")]
                end
            end
        end

        subgraph Volumes["持久化数据卷"]
            V1["erik_data MySQL 数据"]
            V2["erik_redis Redis 数据"]
            V3["erik_es ES 索引"]
            V4["erik_logs 应用日志"]
        end
    end

    subgraph External["外部服务"]
        Stripe["Stripe API<br/>PaymentIntent + 3DS"]
        PayPal["PayPal REST<br/>Orders API v2"]
        Klarna["Klarna API<br/>BNPL 先买后付"]
        SMTP["SMTP 邮件<br/>异步队列发送"]
    end

    User --> NGX
    NGX -->|api.erik.xyz| SVC
    NGX -->|admin.erik.xyz| ADM
    SVC --> MYSQL
    SVC --> REDIS
    SVC --> ES
    SVC --> Stripe
    SVC --> PayPal
    SVC --> Klarna
    SVC --> SMTP
    ADM --> MYSQL
    ADM --> REDIS
    MYSQL -.-> V1
    REDIS -.-> V2
    ES -.-> V3
    SVC -.-> V4

    style Internet fill:#e8eaf6
    style VPS fill:#e8f5e9
    style Docker fill:#f3e5f5
    style Net fill:#e1f5fe
    style PHP_Service fill:#c8e6c9
    style PHP_Admin fill:#fff9c4
    style DB fill:#fce4ec
    style Volumes fill:#efebe9
    style External fill:#e0f2f1
```

---

## 图例索引

| 编号 | 图名 | 类型 | 用途 |
|------|------|------|------|
| 一 | 系统架构图 | 架构图 | 展示系统全貌：客户端→接入→应用→数据→外部服务 |
| 二 | 请求处理流程图 | 流程图 | 展示 HTTP 请求经过 12 级中间件管道的完整路径 |
| 三 | 功能模块全景图 | 功能图 | 展示 16 大功能模块及其细分功能点 |
| 四 | 请求生命周期图 | 生命周期 | 展示从请求到响应的完整时序和各阶段交互 |
| 五 | 订单生命周期图 | 生命周期 | 展示订单从购物车到完成/退款的所有状态流转 |
| 六 | 部署架构图 | 架构图 | 展示 Docker Compose 容器编排、网络、数据卷 |
