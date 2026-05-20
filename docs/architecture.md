# 跨境电商平台 — 架构文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 系统架构

```
apps/flutter/  ←── HTTP/JSON ──→  service/  ── MySQL/Redis/ES
apps/harmonyos/                        ↕ (共享DB)
                                  admin/  ←── 浏览器 ──→  管理用户
```

- **service/** — RESTful API，PHP 8.1 + webman 2.1，常驻内存多进程
- **admin/** — 管理后台，独立 webman 实例，webman-admin 框架
- **apps/flutter/** — Flutter 3.x，支持 iPadOS/macOS/Windows/Linux
- **apps/harmonyos/** — ArkTS + ArkUI，HarmonyOS NEXT

## 2. 目录结构

```
shop-php/
  service/
    config/          31个配置文件 (database/redis/jwt/snowflake/hashids/encryption/poster/scout/...)
    app/
      controller/v1/  36个API控制器
      model/          112个Eloquent模型 (BaseModel + 110业务模型 + 1 Product)
      middleware/      9个中间件
      common/          6个工具类 (Snowflake/Hashids/ApiResponse/Encryption/Jwt/PaymentGateway)
      process/         SnowflakeWorker + Monitor
    database/         schema.sql (110表) + seeders
    public/           静态文件

  admin/
    config/           webman + admin 配置
    plugin/admin/
      app/
        controller/shop/  67个管理控制器 (全部继承Crud)
        model/shop/       65个管理模型
        view/shop/        ECharts仪表盘视图
      config/menu.php    526行菜单配置 (7组商城菜单)

  apps/
    flutter/lib/
      core/api/      Dio HTTP客户端 + JWT + Locale拦截器
      core/i18n/     5语言翻译 + Riverpod Provider
      features/      9个功能页面 (首页/商品/购物车/订单/结算/用户/地址/登录/搜索)
      data/models/   商品/订单模型
      routing/       GoRouter 10条路由
    harmonyos/       ArkTS 8页面 + ApiClient + AppState

  docker/            Nginx + docker-compose (MySQL+Redis+ES+Service+Admin)
  docs/              设计/架构/部署文档
```

## 3. 中间件管道

```
                                        ┌─ PosterVerify (注册/下单/支付)
                                        │
请求 → Cors → GeoIp → Locale → HashidsDecode → VersionRoute → JwtAuth → HashidsEncode → 控制器
  │      │       │        │          │              │            │           │
  │      │       │        │          │              │            │           └─ 响应ID编码
  │      │       │        │          │              │            └─ JWT验证+userId注入
  │      │       │        │          │              └─ API-Version header→命名空间映射
  │      │       │        │          └─ hashid参数→snowflake ID解码
  │      │       │        └─ Accept-Language→当前语言设置
  │      │       └─ MaxMind GeoIP→区域/币种/语言自动识别
  │      └─ 跨域头处理
  └─ OPTIONS预检
```

## 4. 数据流

### 4.1 请求流

```
Flutter/HarmonyOS
  → Dio/HttpClient (Bearer Token + API-Version + Accept-Language)
  → Cors (跨域)
  → GeoIpMiddleware (未登录用户区域识别)
  → LocaleMiddleware (语言设置)
  → HashidsDecode (URL参数/body中*_id字段 hashid→snowflake ID)
  → VersionRoute (API-Version header→控制器命名空间)
  → PosterVerify (敏感路由: Redis验证token)
  → JwtAuth (Bearer验证+userId注入)
  → Controller (业务逻辑)
  → 响应
  → HashidsEncode (响应数据中snowflake ID→hashid)
```

### 4.2 下单流程

```
1. 校验购物车商品 (CartController)
2. 校验商品合规 (Compliance::check)
3. 估算关税 (TariffController::estimate)
4. 计算运费 (ShippingController::calculate)
5. 扣减库存 (InventoryManager::outbound)
6. 创建订单 (OrderController::store)
7. 风控打分 (RiskEngine::score)
8. 清除购物车
```

### 4.3 支付流程

```
1. 获取可用支付方式 (PaymentController::methods)
2. 创建支付 (PaymentController::create → PaymentGateway::make)
3. Stripe PaymentIntent → client_secret → 前端3DS验证
4. Webhook回调 (验签 → 更新支付状态 → 更新订单状态 → 创建分账记录)
```

## 5. 模型继承链

```
Illuminate\Database\Eloquent\Model
  └── app\model\BaseModel
        ├── $incrementing = false (禁用自增)
        ├── $keyType = 'string' (bigint超出PHP int范围)
        ├── use SoftDeletes (软删除)
        ├── boot(): Snowflake::nextId() (自动生成主键)
        └── 110个业务模型继承
              ├── use Encryptable (email/mobile等敏感字段)
              ├── use Searchable (Product → ES同步)
              ├── hasMany / belongsTo (68个表含外键关系)
              └── $encryptable = ['email', 'mobile', ...]
```

## 6. 关键包

| 包 | 命名空间 | 用途 |
|---|---------|------|
| snowflake-php | Erikwang2013\Snowflake | 分布式ID生成 |
| hashids | Erikwang2013\Hashids | 接口ID编解码 |
| jwt-webman | ErikJwt\JWT | JWT认证 |
| encryption | Erikwang2013\Encryption | 接口加密 |
| encryptable | Maize\Encryptable | 数据库字段加密 |
| poster-php | Erikwang2013\Poster | 人机验证 |
| webman-scout | Erikwang2013\WebmanScout | ES搜索 |
| season | Erikwang2013\Season | 国家数据 |
