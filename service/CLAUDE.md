# service/ — 跨境电商业务 API

基于 webman 框架的 RESTful API 服务。支持多语言商品、多币种定价、跨境物流、海关报关、社交登录。

## 项目约定

### Copyright 头部
所有 PHP 文件必须以 Copyright 头部开头：
```php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
```

### 命名空间与全局函数
- 全局变量/函数前 **不加** 反斜杠 `\`
- 控制器命名空间：`app\controller\v1`、`app\controller\v2`（按版本）
- 模型命名空间：`app\model`
- 中间件命名空间：`app\middleware`
- 工具类命名空间：`app\common`（含跨境专用：SocialAuth, PaymentGateway）

### 配置文件
- 所有配置文件位于 `config/` 目录，每个配置项必须带注释说明
- 配置键名使用 snake_case

## 目录结构

```
shop-php/
  install.sql       # 完整安装 SQL（117张表），Web安装向导自动导入
  service/
    .env.example    # 环境变量模板，Web安装向导自动生成 .env
    .env            # 实际环境变量（首次安装自动生成，含 JWT_SECRET + JWT_SECRET_KEY + Hashids + AES 密钥）
    config/
    database.php      # MySQL 连接，表前缀 erik_
    redis.php         # Redis 缓存/session
    jwt.php           # JWT 密钥和有效期
    snowflake.php     # Snowflake worker_id/datacenter_id
    hashids.php       # Hashids salt/min_length
    encryption.php    # AES 加解密密钥
    poster.php        # 随机验证（滑块/拼图/点击）
    scout.php         # Elasticsearch 连接
    versions.php      # API 版本映射表
    country.php       # 跨境运营配置（默认市场/禁售国家/默认币种）
    route.php         # API 路由定义
  app/
    controller/
      v1/             # v1 版本控制器（按功能分组）
        AuthController.php
        SocialAuthController.php
        ProductController.php
        CategoryController.php
        CartController.php
        OrderController.php
        PaymentController.php
        ShippingController.php
        TariffController.php
        WishlistController.php
        CountryController.php
        FlashSaleController.php
        GroupBuyController.php
        ...
    model/            # 模型（extends BaseModel，110张业务表）
    middleware/       # 中间件（含 VersionRoute, PosterVerify, LocaleMiddleware, GeoIpMiddleware）
    common/           # 工具类（8个）
      Snowflake.php         # Snowflake分布式ID生成
      HashidsHelper.php     # Hashids ID 混淆
      ApiResponse.php       # 统一响应格式
      Encryption.php        # 接口AES加解密
      Jwt.php               # JWT 认证工具
      PaymentGateway.php    # 支付网关抽象（Stripe/PayPal/Klarna/Adyen/Afterpay）
      SocialAuth.php        # 社交登录
      Definitions.php       # 共享定义/常量
    process/          # 自定义进程 + 定时任务
      ExchangeRateCron.php      # 汇率更新
      ShipmentTrackingCron.php  # 物流轨迹拉取
      ProductFeedCron.php       # Feed同步
      RecommendationCron.php    # 推荐计算
      ComplianceCron.php        # 合规规则更新
      ReturnExpireCron.php      # 退货超时关闭
      PriceAlertCron.php        # 降价/到货通知
      PaymentReconcileCron.php  # 支付对账
      SettlementCron.php        # 分账结算
      PlatformOrderSyncCron.php # 多平台订单同步
    search/           # Scout Searchable 定义
  database/
    schema.sql        # 原始业务表SQL（已被根目录 install.sql 替代，Web安装向导不再使用此文件）
    seeders/          # 种子数据（国家/HS Code/汇率/物流分区/合规分类/尺码表/风控规则）
```

## 核心架构模式

### API 版本控制（Header 方式）
版本号不放在 URL 中，通过 `API-Version` header 传递：
```
客户端请求:  GET /api/products
            API-Version: 2026-05-20
            Accept-Language: ja

VersionRoute 中间件:
  → 解析 header → 匹配版本 → 转发到 app\controller\v1\ProductController
LocaleMiddleware:
  → 解析 Accept-Language → 设置当前语言 → 多语言内容自动对应
```

### 中间件栈（按顺序）
1. `Cors` — CORS 跨域处理
2. `Security` — WAF 攻击检测（基于 erikwang2013/security-php，31 类攻击检测器）+ 暴力破解防护
3. `RateLimit` — 令牌桶限流
4. `Platform` — 操作来源端识别（Web/App/iOS/Android）
5. `GeoIpMiddleware` — IP 区域识别，自动设置语言/币种（未登录用户）
6. `LocaleMiddleware` — 解析 `Accept-Language` header，设置当前语言
7. `HashidsDecode` — 请求中的 hashid 参数自动解码为 snowflake ID
8. `VersionRoute` — 读取 `API-Version` header，路由到版本控制器
9. `PosterVerify` — 敏感操作随机验证（注册/下单/支付，路由级中间件）
10. `JwtAuth` — JWT token 验证（路由级中间件）
11. `HashidsEncode` — 响应中的 snowflake ID 自动编码为 hashids
12. `Encryption` — 接口数据加解密（通过 `X-Encrypt-Response` / `X-Encrypted` header 触发）

### 多语言商品模式
```
erik_products (id, category_id, brand, status, ...)  ← 公共字段
erik_product_translations (product_id, locale, title, description)  ← 按语言分离
```
- 查询时通过 Eloquent `whereHas` 关联 `product_translations` 按当前 locale 过滤
- Product 模型定义 `translation()` hasOne 关系，自动 eager load
- ES 搜索索引包含所有语言的 title/description，搜索时按 locale 加权

### 多币种定价模式
```
erik_product_skus (id, product_id, attrs, stock, ...)           ← 基础SKU
erik_product_sku_prices (sku_id, currency_code, price, origin_price) ← 分币种价格
```
- 每个 SKU 按币种存独立价格（非汇率换算），支持区域差异化定价
- 默认币种（CNY/USD）作为基准，其他币种可选独立定价或汇率换算
- API 返回时按用户选择/默认币种合并价格字段

### HS Code + 关税模式
```
erik_hs_codes (id, code, description)                           ← HS编码库
erik_product_hs_codes (product_id, hs_code_id, is_primary)      ← 商品关联
erik_tariff_rules (dest_country_id, hs_code_id, duty_rate, vat_rate) ← 关税规则
```
- 下单时 `TariffController` 根据目的国+商品HS Code查 `erik_tariff_rules` 计算预估关税和增值税
- 预估结果在结算页展示，实际以海关核定为准

### 社交登录模式
```
用户点击 Google/Apple/Facebook 登录
  → SDK 返回 id_token
  → POST /api/auth/social {provider, id_token, email, name}
  → SocialAuth.php 验证 id_token
  → 查找 erik_user_social_accounts 表
  → 已有绑定：直接登录，返回 JWT
  → 未绑定：自动创建用户 + 绑定，返回 JWT
```

### API 响应格式
统一使用 `ApiResponse` 工具类：
```php
ApiResponse::success($data, 'message');
ApiResponse::fail('error message', $code);
ApiResponse::paginate($items, $total, $page, $perPage);
```

### 国际化 (i18n)
- 翻译文件位于 `resource/translations/`，支持 `zh_CN`（默认）、`zh_HK`、`en`、`ja`、`ko`
- API 消息通过 `trans()` 翻译，界面文本通过此机制
- 商品等内容数据通过 `erik_product_translations` 多语言表存储

## 技术栈

| 包 | 用途 |
|---|------|
| workerman/webman-framework | HTTP 框架 |
| webman/database + illuminate/database | MySQL ORM |
| erikwang2013/snowflake-php | 分布式 ID 生成 |
| erikwang2013/hashids | 接口 ID 加解密 |
| erikwang2013/jwt-webman | JWT 认证 |
| erikwang2013/security-php | WAF 攻击检测与防护（31 类检测器，IP 黑名单） |
| erikwang2013/encryption | 接口敏感数据加解密 |
| erikwang2013/encryptable | 数据库字段加解密 |
| erikwang2013/poster-php | 敏感操作随机验证 |
| erikwang2013/webman-scout | ES 数据同步与多语言搜索 |
| erikwang2013/season | 国家旗帜 + 货币符号数据 |
| phpoffice/phpspreadsheet | Excel 导出（订单/报关） |
| barryvdh/laravel-dompdf | PDF 导出（商业发票/装箱单） |
| guzzlehttp/guzzle | 社交登录 API 调用 + 物流 API |

## 命令

```bash
# 根目录 Makefile 快捷命令
make start            # 启动 service + admin
make stop             # 停止所有服务
make reload           # 平滑重启
make test             # 运行 PHPUnit 测试
make lint             # PHP 语法检查
make check            # phpstan 静态分析
make fix              # php-cs-fixer 代码格式化

# 原生命令
php start.php start         # 启动（开发模式）
php start.php start -d      # 守护进程启动
php start.php stop          # 停止
php start.php reload        # 平滑重启
php start.php status        # 查看状态
php vendor/bin/phpunit      # 运行测试
```

## 测试与工具

| 工具 | 配置 | 用途 |
|------|------|------|
| PHPUnit 12.5 | `phpunit.xml` | 单元测试（22 tests, 45 assertions） |
| phpstan | `phpstan.neon` (level 5) | 静态分析 |
| php-cs-fixer | `.php-cs-fixer.php` | PSR-12 代码格式化 |
| CI/CD | `.github/workflows/ci.yml` | PHP 8.3/8.4 矩阵测试 |

## 功能实现状态

| 模块 | 状态 |
|------|:--:|
| Stripe 支付 | 完整 |
| PayPal REST API (Guzzle + OAuth2) | 完整 |
| PhpSpreadsheet XLSX 导出 | 完整 |
| MaxMind GeoLite2 GeoIP | 完整 |
| Item-based 协同过滤推荐 | 完整 |
