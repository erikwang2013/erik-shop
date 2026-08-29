# Erik Shop — 跨境电商平台

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz


## 语言 / Languages

| 语言 | 链接 |
|------|------|
| 中文 | [README.md](README.md) |
| English | [docs/i18n/en/README.md](docs/i18n/en/README.md) |
| 한국어 | [docs/i18n/ko/README.md](docs/i18n/ko/README.md) |
| Русский | [docs/i18n/ru/README.md](docs/i18n/ru/README.md) |
| Deutsch | [docs/i18n/de/README.md](docs/i18n/de/README.md) |
| Français | [docs/i18n/fr/README.md](docs/i18n/fr/README.md) |
| Español | [docs/i18n/es/README.md](docs/i18n/es/README.md) |
| Português | [docs/i18n/pt/README.md](docs/i18n/pt/README.md) |
| हिन्दी | [docs/i18n/hi/README.md](docs/i18n/hi/README.md) |
| العربية | [docs/i18n/ar/README.md](docs/i18n/ar/README.md) |
| বাংলা | [docs/i18n/bn/README.md](docs/i18n/bn/README.md) |
| Bahasa Indonesia | [docs/i18n/id/README.md](docs/i18n/id/README.md) |
| 日本語 | [docs/i18n/ja/README.md](docs/i18n/ja/README.md) |

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

**服务端：** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**核心包：** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**支付：** Stripe, PayPal（完整）；Klarna, Adyen（占位，`PaymentGateway::make` 未实现，见 docs/PLAN.md）
**客户端：** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## 架构图集

> 完整图集及大图查看：[docs/diagrams.md](docs/diagrams.md)

### 系统架构图

![系统架构图](docs/01-system-architecture.svg)

### 请求处理流程图

![请求处理流程图](docs/02-request-processing-flow.svg)

### 功能模块全景图

![功能模块全景图](docs/03-feature-module-map.svg)

### 请求生命周期图

![请求生命周期图](docs/04-request-lifecycle.svg)

> 更多细节见 [完整架构图集](docs/diagrams.md)（含订单生命周期、部署架构、安全架构、多币种结算等 8 张图）

### 安全架构图

![安全架构图](docs/07-security-architecture.svg)

### 多币种结算流程图

![多币种结算流程图](docs/08-multi-currency-settlement.svg)

### 多币种结算说明

**多币种定价**：商品 SKU 按 `currency_code` 分币种定价，下单时订单锁定收款币种（USD / EUR / GBP / CNY 等）。

**汇率服务**：`erik_exchange_rates` 汇率表支持 manual 手动维护与 exchangerate-api 自动拉取，按 `effective_at` 生效时间版本化管理，结算时取支付时点汇率快照。

**原币扣款**：Stripe / PayPal 按订单币种原币扣款（Klarna/Adyen 为占位，未接入），Webhook 验签确认到账后更新支付与订单状态。

**分账结算**：支付成功后自动生成 `PlatformSettlements` 平台分账（订单总额 + 平台佣金 + 支付网关手续费，按订单币种记账）；卖家结算 `MerchantSettlements`（订单金额 → 抽成率 → 结算金额）、供应商结算 `SupplierSettlements`、分销佣金提现 `AffiliatePayouts` 四线独立结算，状态 0 待结算 / 1 已结算。

**汇兑损益**：`CurrencyExchangeGainsLosses` 追踪收款币种与结算币种差异，对比支付时汇率与结算时汇率，正数 = 汇兑收益、负数 = 汇兑亏损，支撑跨境电商多币种对账与审计。

## 快速开始

### 方式一：Web 一键安装（推荐）

```bash
# 1. 安装 admin 依赖
cd admin && composer install

# 2. 启动管理后台
php start.php start -d

# 3. 浏览器打开安装向导
# http://127.0.0.1:8788/app/admin/install/step1
# 填入数据库信息 → 设置管理员账号 → 完成

# 4. 安装依赖并启动 API
cd ../service && composer install && php start.php start -d
```

> 安装向导自动完成：建库 → 导入 117 张表 → 生成 service/.env 和 admin/.env（含随机密钥） → 创建管理员 → 重载服务

### 方式二：命令行手动安装

详见 [INSTALL.md](docs/INSTALL.md)

### Docker 部署

```bash
# 配置环境变量
cp .env.example .env  # 或设置 DB_PASS / JWT_SECRET 等变量

# 一键启动全部服务
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

详见 [部署文档](docs/deployment.md)

## 项目结构

```
shop-php/
  install.sql       # 一键安装 SQL（117 张表），Web 安装向导自动导入
  service/          PHP业务API (webman)        — 39控制器 + 111模型 + 14中间件
  admin/            管理后台 (webman-admin)      — 82控制器 + 76模型 + ECharts仪表盘 + Web安装向导
  apps/flutter/     Flutter客户端              — 11页面 + 5语言 + PC自适应
  apps/harmonyos/   鸿蒙客户端                  — 9页面 + ArkTS
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
| **国际物流** | 物流分区运费、海外仓(发货仓+退货仓)、商业发票/装箱单、HS申报（规划中） |
| **支付** | Stripe/PayPal（完整）、Klarna/Adyen（占位）、BNPL先买后付（占位）、3DS验证 |
| **营销** | 优惠券(分区+新老客)、轮播图(区域可见)、秒杀、拼团、分销(链接+佣金+提现) |
| **多平台** | Amazon/eBay/Shopee/Lazada/Temu商品刊登+订单聚合 |
| **供应链** | 供应商评级、采购→质检→入库、库存流水(不可变账本)、调拨 |
| **风控合规** | 规则引擎(旁路打分)、KYC实名、GDPR/CCPA数据请求、Cookie Consent |
| **安全防护** | 31类攻击检测(XSS/SQL注入/XXE/SSRF/CRLF/路径遍历/文件上传/暴力破解/HTTP方法/Host/CORS等) |
| **高并发** | 令牌桶限流、DB读写分离、连接池优化 |
| **CDN内容分发** | Origin-Pull回源(零迁移)、4家提供商(Cloudflare/CloudFront/阿里云/腾讯云)、管理端配置+手动刷新/预热+自动刷新(fail-open)、边缘缓存(expires 7d immutable) |
| **会员增长** | 积分规则、会员等级权益、礼品卡、降价提醒、订阅周期购、AB测试 |
| **内容管理** | CMS多语言页面、FAQ、知识库、尺码对照表、邮件模板、商品Feed同步 |
| **客服** | WebSocket实时IM、知识库(表结构已建) |
| **基础设施** | Snowflake分布式ID、Hashids接口混淆、JWT认证、AES加密、GeoIP区域识别 |
| **多端覆盖** | Flutter(iOS/Android/macOS/Windows/Linux/iPadOS)+HarmonyOS(ArkTS)+Web Admin |
| **平台追踪** | 8平台来源识别(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web)+DB记录 |
| **测试** | 22 tests / 45 assertions — ALL PASS (Security+Jwt+ApiResponse+Redis) |

## 核心设计

- **Snowflake主键**：117张表全部使用 `erikwang2013/snowflake-php` 生成的bigint ID
- **Hashids接口**：中间件自动编码/解码，控制器无感知
- **Encryptable加密**：email/mobile/address等敏感字段数据库级加密
- **JWT认证**：HS256 + access/refresh 双token自动刷新
- **API版本**：`API-Version` header路由，不在URL中
- **Poster验证**：敏感操作(注册/下单/支付)随机人机验证

## 文档

| 文档 | 说明 |
|------|------|
| [README-EN.md](docs/README-EN.md) | English documentation |
| [INSTALL.md](docs/INSTALL.md) | 安装指南（Web 一键安装 + 手动安装） |
| [AUDIT-REPORT.md](docs/AUDIT-REPORT.md) | 安装系统审查报告 |
| [项目规划](docs/PLAN.md) | 团队产出的分阶段项目规划（4 阶段路线图 + 关键风险 + Quick Wins） |
| [CDN支持方案](docs/PLAN-CDN.md) | CDN 内容分发实现方案（Origin-Pull 回源 + 统一 Provider 抽象 + 4 家提供商） |
| [团队调研明细](docs/PLAN-RESEARCH.md) | 7 领域现状调研：已实现 / 差距 / 风险 / 建议 |
| [功能设计文档](docs/features.md) | 完整功能矩阵、业务流程、状态机 |
| [架构图集](docs/diagrams.md) | 架构图、流程图、功能图、生命周期图、部署图、多币种结算图（8张Mermaid图） |
| [架构设计文档](docs/architecture-full.md) | 系统架构图、中间件管道、数据架构、安全架构、支付架构 |
| [设计文档](docs/design.md) | 数据库表设计、API规范、安全方案、国际化 |
| [架构文档](docs/architecture.md) | 目录结构、模型继承链、关键包 |
| [API接口文档](docs/api.md) | 71个API端点 (静态文档) |
| [hg/apidoc接口文档](http://localhost:8787/apidoc/) | hg/apidoc自动生成 (6分组: 认证/商品/交易/物流海关/用户营销/运营) |
| [部署文档](docs/deployment.md) | Docker/手动部署、环境变量、运维命令 |


## 开源不易，欢迎支持

| 微信 | 支付宝 |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

### 全球银行转账 (ZA Bank)

**收款人信息**

- 收款人姓名：WANG KEXUN
- 收款账户号码：881015918251

**收款银行**

- SWIFT Code：AABLHKHHXXX
- 银行名称：ZA Bank Limited
- 银行编号：387
- 银行地址：Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**跨境汇款代理银行（如需）**

> 此为跨境汇款代理银行（中转银行）信息，非收款银行信息。请向汇款银行查询是否需要提供。

- **汇入港元、人民币及美元**（代理银行 Citibank）：
  - 银行名称：Citibank N.A. Hong Kong
  - SWIFT Code：CITIHKHXXXX
  - 银行编号：006
  - 分行名称：Hong Kong Branch
  - 分行编号：391
  - 银行地址：Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **汇入其他币种**（代理银行 BNY Mellon）：
  - 银行名称：THE BANK OF NEW YORK MELLON
  - SWIFT Code：IRVTUS3NXXX
  - 银行地址：THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### 虚拟币打赏 (Crypto Donation)

如果这个项目对你有帮助，欢迎扫描二维码打赏支持，谢谢！

| 主网 (Network) | 二维码 (QR Code) | 钱包地址 (Wallet Address) |
|---|---|---|
| BNB Smart Chain (BEP20) | [<img src="docs/coin/1.jpg" width="150" alt="BNB Smart Chain (BEP20)">](docs/coin/1.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Tron (TRC20) | [<img src="docs/coin/2.jpg" width="150" alt="Tron (TRC20)">](docs/coin/2.jpg) | `TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| Ethereum (ERC20) | [<img src="docs/coin/3.jpg" width="150" alt="Ethereum (ERC20)">](docs/coin/3.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Aptos | [<img src="docs/coin/4.jpg" width="150" alt="Aptos">](docs/coin/4.jpg) | `0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| Plasma | [<img src="docs/coin/5.jpg" width="150" alt="Plasma">](docs/coin/5.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Polygon POS | [<img src="docs/coin/6.jpg" width="150" alt="Polygon POS">](docs/coin/6.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Solana | [<img src="docs/coin/7.jpg" width="150" alt="Solana">](docs/coin/7.jpg) | `2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` |
| The Open Network (TON) | [<img src="docs/coin/8.jpg" width="150" alt="The Open Network (TON)">](docs/coin/8.jpg) | `UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| Arbitrum One | [<img src="docs/coin/9.jpg" width="150" alt="Arbitrum One">](docs/coin/9.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| AVAX C-Chain | [<img src="docs/coin/10.jpg" width="150" alt="AVAX C-Chain">](docs/coin/10.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |

---


## 测试

```bash
make test             # 推荐方式
cd service && DB_USER=qa DB_PASS=qa_pass vendor/bin/phpunit  # service 单元/集成（211 tests / 1000 assertions）
cd admin  && DB_USER=qa DB_PASS=qa_pass vendor/bin/phpunit  # admin（2 tests / 7 assertions）
cd service/tests/api && php run_all.php                      # API 自动化（4 套件 213 项断言）
cd scripts/e2e && SERVICE_BASE=http://127.0.0.1:8787 node ui-e2e.js  # UI E2E（admin + service 页面）

# 依赖安全审计（已知 1 个低危 CVE：CVE-2025-45769 firebase/php-jwt <7.0.0，
# 受 jwt-webman ^6.0 约束无法升级，HS256 对称签名用法不受影响）
composer audit
```

最新全量测试结果见 [docs/test-reports/QA-REPORT-2026-08-27.md](docs/test-reports/QA-REPORT-2026-08-27.md)（截图在 `docs/test-reports/screenshots/`，E2E 明细在 `scripts/e2e/results.json`）。

**已知缺口已全部修复（2026-08-28）**：19 个 admin 页面视图 + ShopExport 数据导出页已实现，E2E 重跑 47 PASS / 0 FAIL / 1 WARN（100%），详见测试报告 §8。

## 开发工具

```bash
make help             # 查看所有命令
make lint             # PHP 语法检查
make check            # phpstan 静态分析
make fix              # php-cs-fixer 代码格式化
```

CI/CD: `.github/workflows/ci.yml` — PHP 8.3/8.4 矩阵测试

## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
