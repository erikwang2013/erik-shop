# Erik Shop — 简化版 (Lite)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 版本

> 简化版 (MIT开源): `lite` | 标准版 (商业): `standard` | 完整版 (商业): `full`
>
> 商业授权联系: **erik@erik.xyz** | 版本对比: [docs/VERSIONS.md]

## 技术栈

| 层级 | 技术 |
|------|------|
| API | webman 2.1 + illuminate/database |
| Admin | webman-admin + LayUI + ECharts |
| 客户端 | Flutter (iOS/Android/macOS/Windows/Linux/iPadOS) |
| 数据库 | MySQL 8.0 + Redis 7 |

## 快速开始

```bash
# 环境配置
cp .env.example .env   # 编辑填入数据库/JWT密钥

# 导入数据库 (25张表)
mysql -u root -p erik_shop < service/database/schema.sql

# 启动 API
cd service && composer install && php start.php start -d

# 启动 Admin
cd admin && composer install && php start.php start -d

# Flutter 客户端
cd apps/flutter && flutter pub get && flutter run -d macos
```

## 功能覆盖

| 模块 | 功能 |
|------|------|
| 用户 | 注册/登录(JWT)/地址管理 |
| 商品 | 分类 + SKU + 图片 + 评价 |
| 交易 | 购物车 + 订单 + 支付(Stripe) + 退款 |
| 营销 | 优惠券 + 轮播图 |
| 运营 | 通知 + 系统配置 + 操作日志 |
| 基础 | 国家/货币/汇率 + 搜索 |
| 安全 | XSS/SQL注入/CRLF/路径遍历检测 + Hashids接口混淆 |
| 多端 | Flutter 5平台 + Admin Web |

## 项目结构

```
service/        业务API       — 15控制器 + 26模型 + 7中间件
admin/          管理后台       — 基础CRUD + ECharts仪表盘
apps/flutter/   Flutter客户端  — 12页面 PC自适应布局
database/       数据库         — 25张表 (erik_前缀, snowflake主键)
```

## 中间件管道

```
Cors → Security(基础攻击检测) → Locale(语言) → HashidsDecode
    → (JwtAuth) → HashidsEncode → 控制器
```

## 文档

| 文档 |
|------|
| [三版本定义](VERSIONS.md) — 简化版/标准版/完整版差异 |
| [API接口文档](docs/api.md) — ~30个端点 + 请求/响应示例 |
| [功能设计](docs/features.md) — 功能清单 + 业务流程 |
| [部署文档](docs/deployment.md) — Docker/手动部署 |

## 测试

```bash
cd service && php vendor/bin/phpunit tests/
# Security + JWT + ApiResponse
```

## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
