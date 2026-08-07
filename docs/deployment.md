# 跨境电商平台 — 部署文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Docker 部署 (推荐)

### 1.1 环境要求

- Docker 24.0+ / Docker Compose v2
- 主机: Linux (推荐 Ubuntu 22.04+)
- 内存: 最低 4GB, 推荐 8GB+

### 1.2 部署步骤

```bash
# 1. 克隆项目
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. 配置环境变量
cp .env.example .env
# 编辑 .env 修改所有密码和密钥:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET 等

# 3. 启动全部服务
docker compose up -d

# 4. 查看日志
docker compose logs -f service
docker compose logs -f admin

# 5. 访问
# API: http://localhost/api
# 管理后台: http://admin.localhost
```

### 1.3 服务清单

| 服务 | 端口 | 说明 |
|------|------|------|
| nginx | 80, 443 | 反向代理 |
| service | 8787 (内部) | PHP业务API |
| admin | 8788 (内部) | 管理后台 |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 生产环境检查清单

- [ ] `.env` 中所有密钥已更改为随机值
- [ ] `STRIPE_MODE=live` (生产环境)
- [ ] `APP_ENV=production`
- [ ] `config/app.php` 中 `debug` 设为 `false`
- [ ] SSL证书配置 (nginx+Let's Encrypt)
- [ ] 数据库已导入根目录 `install.sql`（117 张表，Web 安装向导自动导入）
- [ ] ES索引已创建: `php start.php scout:import "app\model\Products"`
- [ ] MySQL/Redis/ES 数据卷已配置备份

## 2. 手动部署

### 2.1 环境依赖

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (可选，搜索功能需要)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# 编辑 .env
composer install --no-dev --optimize-autoloader
php start.php start -d
# 监听: http://0.0.0.0:8787
```

### 2.3 Admin 管理后台

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# 监听: http://0.0.0.0:8787 (另一个端口需Nginx反向代理区分)
```

### 2.4 Nginx 反向代理

```nginx
# 见 docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
```

## 3. 数据库初始化

```bash
# 创建数据库
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 导入表结构
mysql -u root -p erik_shop < install.sql

# 导入种子数据（可选）
php -r "
require 'vendor/autoload.php';
// 导入国家/货币/HS Code/物流分区等种子数据
"
```

## 4. 环境变量参考

| 变量 | 默认值 | 说明 |
|------|--------|------|
| APP_ENV | production | 应用环境 |
| DB_HOST | 127.0.0.1 | 数据库地址 |
| DB_PORT | 3306 | 数据库端口 |
| DB_NAME | erik_shop | 数据库名 |
| DB_USER | erik | 数据库用户 |
| DB_PASS | (必填) | 数据库密码 |
| REDIS_HOST | 127.0.0.1 | Redis地址 |
| JWT_SECRET | (必填) | JWT签名密钥(256bit) |
| HASHIDS_SALT | (必填) | Hashids盐值 |
| ENCRYPTION_KEY | (必填) | AES加密密钥 |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Stripe密钥 |
| STRIPE_WEBHOOK_SECRET | - | Stripe Webhook验签 |

## 5. 运维命令

```bash
# Service API
cd service
php start.php status        # 查看状态
php start.php reload        # 平滑重启
php start.php stop          # 停止

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # 查看容器状态
docker compose logs -f      # 查看日志
docker compose restart      # 重启全部
docker compose down         # 停止
```
