# Erik Shop — 标准版 (Standard)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 跨境电商平台标准版。简化版和完整版见 [VERSIONS.md](VERSIONS.md)

## 技术栈

| 层级 | 技术 |
|------|------|
| API | webman 2.1 + illuminate/database |
| Admin | webman-admin + LayUI + ECharts |
| 客户端 | Flutter (iOS/Android/macOS/Windows/Linux/iPadOS) |
| 数据库 | MySQL 8.0 + Redis 7 + Elasticsearch 8 |

## 快速开始

```bash
cp .env.example .env   # 编辑填入数据库/JWT密钥
mysql -u root -p erik_shop < service/database/schema.sql
cd service && composer install && php start.php start -d
cd admin && composer install && php start.php start -d
cd apps/flutter && flutter pub get && flutter run -d macos
```

## 功能覆盖

| 模块 | 功能 |
|------|------|
| 用户 | 注册/登录(JWT) + 社交登录(Google/Apple/Facebook) + 地址管理 |
| 商品 | 分类 + SKU + 多语言内容 + 多币种定价 + 图片 + 评价 |
| 交易 | 购物车 + 订单 + 支付(Stripe/PayPal) + 退款 + 退货 |
| 营销 | 优惠券 + 轮播图 + 秒杀 + 拼团 + 分销 |
| 跨境 | 国际物流(分区运费/海外仓) + HS Code + 关税/VAT |
| 安全 | XSS/SQL注入/CRLF/路径遍历 + Hashids混淆 + PosterVerify人机验证 |
| 运营 | 通知 + 配置 + 操作日志 + 平台来源追踪 + GeoIP区域识别 |
| 多端 | Flutter 5平台 + Admin Web + ECharts面板 + Excel/PDF导出 |

## 项目结构

```
service/        业务API    — 24控制器 + 55模型 + 9中间件
admin/          管理后台    — 27控制器 + 34模型
apps/flutter/   Flutter    — 12页面 PC自适应
database/       数据库     — ~60张表 (erik_前缀, snowflake主键)
```

## 中间件管道

```
Cors → Security → Platform → GeoIp → Locale → HashidsDecode
    → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode
```

## 文档

| 文档 |
|------|
| [三版本定义](VERSIONS.md) |
| [API接口文档](docs/api.md) |
| [架构设计](docs/architecture.md) |
| [功能设计](docs/features.md) |
| [部署文档](docs/deployment.md) |

## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
