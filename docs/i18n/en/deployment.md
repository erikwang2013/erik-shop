# Cross-Border E-Commerce Platform — Deployment Document

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Docker Deployment (Recommended)

### 1.1 Environment Requirements

- Docker 24.0+ / Docker Compose v2
- Host: Linux (Ubuntu 22.04+ recommended)
- Memory: minimum 4GB, 8GB+ recommended

### 1.2 Deployment Steps

```bash
# 1. Clone the project
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. Configure environment variables
cp .env.example .env
# Edit .env and change all passwords and secrets:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET etc.

# 3. Start all services
docker compose up -d

# 4. View logs
docker compose logs -f service
docker compose logs -f admin

# 5. Access
# API: http://localhost/api
# Admin console: http://admin.localhost
```

### 1.3 Service List

| Service | Port | Description |
|------|------|------|
| nginx | 80, 443 | Reverse proxy |
| service | 8787 (internal) | PHP business API |
| admin | 8788 (internal) | Admin console |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 Production Environment Checklist

- [ ] All secrets in `.env` changed to random values
- [ ] `STRIPE_MODE=live` (production)
- [ ] `APP_ENV=production`
- [ ] `debug` set to `false` in `config/app.php`
- [ ] SSL certificate configured (nginx + Let's Encrypt)
- [ ] Database imported from root `install.sql` (117 tables, auto-imported by the Web installation wizard)
- [ ] ES index created: `php start.php scout:import "app\model\Products"`
- [ ] MySQL/Redis/ES data volume backups configured
- [ ] CDN enabled: `CDN_ENABLED=true`, `CDN_DOMAIN`, `CDN_DEFAULT_PROVIDER` and per-provider credentials in `.env` (or configure in the admin CDN page, which overrides .env)
- [ ] CDN domain CNAME points back to the admin domain (origin-pull)

## 2. Manual Deployment

### 2.1 Environment Dependencies

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (optional, required for search features)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# Edit .env
composer install --no-dev --optimize-autoloader
php start.php start -d
# Listening: http://0.0.0.0:8787
```

### 2.3 Admin Console

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# Listening: http://0.0.0.0:8787 (another port needs Nginx reverse proxy to differentiate)
```

### 2.4 Nginx Reverse Proxy

```nginx
# See docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787

# CDN edge caching for admin uploads (served through the CDN domain):
location /app/admin/upload/ {
    expires 7d;
    add_header Cache-Control "public, max-age=604800, immutable";
}
```

## 3. Database Initialization

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import table structure
mysql -u root -p erik_shop < install.sql

# Import seed data (optional)
php -r "
require 'vendor/autoload.php';
// Import seed data for countries/currencies/HS codes/logistics zones etc.
"
```

## 4. Environment Variable Reference

| Variable | Default | Description |
|------|--------|------|
| APP_ENV | production | Application environment |
| DB_HOST | 127.0.0.1 | Database host |
| DB_PORT | 3306 | Database port |
| DB_NAME | erik_shop | Database name |
| DB_USER | erik | Database user |
| DB_PASS | (required) | Database password |
| REDIS_HOST | 127.0.0.1 | Redis host |
| JWT_SECRET | (required) | JWT signing secret (256-bit) |
| HASHIDS_SALT | (required) | Hashids salt |
| ENCRYPTION_KEY | (required) | AES encryption key |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Stripe secret key |
| STRIPE_WEBHOOK_SECRET | - | Stripe webhook signature verification |
| CDN_ENABLED | false | CDN global on/off (propagated to service via shared Redis, prefix `shop:`, 60s TTL) |
| CDN_DEFAULT_PROVIDER | cloudflare | Default provider: cloudflare / cloudfront / aliyun / tencent |
| CDN_DOMAIN | - | Origin-pull CDN domain (no scheme); URL rewrite target, e.g. cdn.erik.xyz (CNAME back to the admin domain) |
| CF_API_TOKEN | - | Cloudflare API token |
| CF_ZONE_ID | - | Cloudflare zone ID |
| AWS_ACCESS_KEY_ID | - | CloudFront access key ID |
| AWS_SECRET_ACCESS_KEY | - | CloudFront secret access key |
| CLOUDFRONT_DISTRIBUTION_ID | - | CloudFront distribution ID |
| AWS_REGION | us-east-1 | CloudFront region |
| ALIYUN_ACCESS_KEY_ID | - | Aliyun CDN access key ID |
| ALIYUN_ACCESS_KEY_SECRET | - | Aliyun CDN access key secret |
| TENCENT_SECRET_ID | - | Tencent Cloud CDN secret ID |
| TENCENT_SECRET_KEY | - | Tencent Cloud CDN secret key |

## 5. Operations Commands

```bash
# Service API
cd service
php start.php status        # View status
php start.php reload        # Graceful restart
php start.php stop          # Stop

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # View container status
docker compose logs -f      # View logs
docker compose restart      # Restart all
docker compose down         # Stop
```
