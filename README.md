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
  service/          PHP业务API (webman)        — 36控制器 + 112模型 + 9中间件
  admin/            管理后台 (webman-admin)      — 67控制器 + 65模型
  apps/flutter/     Flutter客户端              — 9页面 + 5语言 + PC自适应
  apps/harmonyos/   鸿蒙客户端                  — 8页面 + ArkTS
  database/         数据库                      — 110张表 (erik_前缀, snowflake主键)
  docker/           Docker部署                  — Nginx + PHP + MySQL + Redis + ES
  docs/             设计文档
```

## 核心功能

| 模块 | 功能 |
|------|------|
| 商品 | 多语言内容、分币种定价、SKU管理、合规标签、HS Code、ES搜索 |
| 交易 | 购物车、订单、Stripe/PayPal/Klarna支付、退款、退货 |
| 物流 | 国际物流商、分区运费、海外仓、HS申报、商业发票/装箱单 |
| 海关 | HS Code编码库、关税规则、VAT/IOSS、各国合规限制 |
| 营销 | 优惠券、轮播图、秒杀、拼团、分销 |
| 供应链 | 供应商、采购单、质检、库存流水 |
| 风控 | 规则引擎、3DS验证、KYC、GDPR/CCPA |
| 内容 | CMS页面、FAQ、知识库、尺码对照表、商品Feed |
| 增长 | 会员体系、积分、礼品卡、B2B批发、订阅周期购 |
| 多平台 | Amazon/eBay/Shopee刊登+订单聚合、多商家入驻 |

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
| [部署文档](docs/deployment.md) | Docker/手动部署、环境变量、运维命令 |

## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
