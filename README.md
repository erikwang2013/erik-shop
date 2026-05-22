# Erik Shop — 跨境电商平台

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 项目简介

基于 webman 全家桶构建的全栈跨境电商平台，覆盖 B2C/B2B 场景和第三方卖家入驻。

### 技术架构

| 层级 | 技术 | 目录 |
|------|------|------|
| 业务 API | webman + illuminate/database + erikwang2013/* | `service/` |
| 管理后台 | webman-admin + LayUI + ECharts | `admin/` |
| 客户端 | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |
| 鸿蒙客户端 | ArkTS + ArkUI (HarmonyOS NEXT) | `apps/harmonyos/` |

### 技术栈

**服务端：** PHP 8.1+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**核心包：** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**支付：** Stripe, PayPal, Klarna, Adyen
**客户端：** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## 快速开始

```bash
# 1. 克隆项目
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. 环境配置
cp .env.example .env
# 编辑 .env 填入数据库/Redis/ES/JWT密钥等配置

# 3. 导入数据库
mysql -u root -p erik_shop < service/database/schema.sql

# 4. 启动 Service API
cd service && composer install && php start.php start -d

# 5. 启动 Admin 管理后台
cd admin && composer install && php start.php start -d

# 6. Flutter 客户端
cd apps/flutter && flutter pub get && flutter run -d macos

# 访问: http://localhost:8787 (API) / http://admin.localhost:8787 (管理后台)
```

## 项目结构

```
shop-php/
  service/          PHP业务API (webman)        — 37控制器 + 112模型 + 11中间件
  admin/            管理后台 (webman-admin)      — 67控制器 + 65模型
  apps/flutter/     Flutter客户端              — 9页面 + 5语言 + PC自适应
  apps/harmonyos/   鸿蒙客户端                  — 8页面 + ArkTS
  database/         数据库                      — 110张表 (erik_前缀, snowflake主键)
  docker/           Docker部署                  — Nginx + PHP + MySQL + Redis + ES
  docs/             设计文档
```

## 功能覆盖

| 维度 | 覆盖内容 |
|------|---------|
| **B2C零售** | 多语言商品、分币种定价、SKU、购物车、订单、支付、退款、退货 |
| **B2B批发** | 阶梯定价(MOQ)、企业认证(税号/营业执照)、询价 |
| **多商家入驻** | 卖家审核、商品审核、分成分账 |
| **跨境合规** | HS Code编码库、关税规则、VAT/IOSS、各国合规标签(FDA/CE/RoHS) |
| **国际物流** | 物流分区运费、海外仓(发货仓+退货仓)、HS申报、商业发票/装箱单 |
| **支付** | Stripe/PayPal/Klarna/Adyen、BNPL先买后付、3DS验证 |
| **营销** | 优惠券(分区+新老客)、轮播图(区域可见)、秒杀、拼团、分销(链接+佣金+提现) |
| **多平台** | Amazon/eBay/Shopee/Lazada/Temu商品刊登+订单聚合 |
| **供应链** | 供应商评级、采购→质检→入库、库存流水(不可变账本)、调拨 |
| **风控合规** | 规则引擎(旁路打分)、KYC实名、GDPR/CCPA数据请求、Cookie Consent |
| **安全防护** | 15类攻击检测(XSS/SQL注入/XXE/SSRF/CRLF/路径遍历/文件上传/暴力破解/HTTP方法/Host/CORS) |
| **高并发** | 令牌桶限流、Cache-Aside缓存(防雪崩+防穿透)、熔断器、DB读写分离、连接池优化 |
| **会员增长** | 积分规则、会员等级权益、礼品卡、降价提醒、订阅周期购、AB测试 |
| **内容管理** | CMS多语言页面、FAQ、知识库、尺码对照表、邮件模板、商品Feed同步 |
| **客服** | WebSocket实时IM、知识库(表结构已建) |
| **基础设施** | Snowflake分布式ID、Hashids接口混淆、JWT认证、AES加密、GeoIP区域识别 |
| **多端覆盖** | Flutter(iOS/Android/macOS/Windows/Linux/iPadOS)+HarmonyOS(ArkTS)+Web Admin |
| **平台追踪** | 8平台来源识别(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web)+DB记录 |
| **测试** | 23 tests / 68 assertions — ALL PASS (Security+Jwt+ApiResponse) |

## 核心设计

- **Snowflake主键**：110张表全部使用 `erikwang2013/snowflake-php` 生成的bigint ID
- **Hashids接口**：中间件自动编码/解码，控制器无感知
- **Encryptable加密**：email/mobile/address等敏感字段数据库级加密
- **JWT认证**：HS256 + 黑名单 + 自动刷新
- **API版本**：`API-Version` header路由，不在URL中
- **Poster验证**：敏感操作(注册/下单/支付)随机人机验证

## 文档

| 文档 | 说明 |
|------|------|
| [功能设计文档](docs/features.md) | 完整功能矩阵、业务流程、API端点设计、状态机 |
| [架构设计文档](docs/architecture-full.md) | 系统架构图、中间件管道、数据架构、安全架构、支付架构 |
| [设计文档](docs/design.md) | 数据库表设计、API规范、安全方案、国际化 |
| [架构文档](docs/architecture.md) | 目录结构、模型继承链、关键包 |
| [API接口文档](docs/api.md) | 71个API端点完整文档 (请求/响应示例/错误码/状态码) |
| [部署文档](docs/deployment.md) | Docker/手动部署、环境变量、运维命令 |


## 开源不易，欢迎支持

| 微信 | 支付宝 |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---


## 测试

```bash
cd service && php vendor/bin/phpunit tests/
# 23 tests, 68 assertions — ALL PASS
# SecurityTest(16): XSS+SQLi+XXE+SSRF+File+Path
# JwtTest(4): encode/decode validation
# ApiResponseTest(3): success/fail/paginate format
```
## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
