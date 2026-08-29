# ক্রস-বর্ডার ই-কমার্স প্ল্যাটফর্ম — ডিপ্লয়মেন্ট ডকুমেন্ট

> এই নথিটি মূল চীনা ডকুমেন্টেশনের মেশিন অনুবাদ। মূল: [চীনা মূল](../../deployment.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Docker ডিপ্লয়মেন্ট (সুপারিশকৃত)

### 1.1 পরিবেশের প্রয়োজনীয়তা

- Docker 24.0+ / Docker Compose v2
- হোস্ট: Linux (Ubuntu 22.04+ সুপারিশকৃত)
- মেমরি: সর্বনিম্ন 4GB, সুপারিশকৃত 8GB+

### 1.2 ডিপ্লয়মেন্ট ধাপ

```bash
# 1. প্রজেক্ট ক্লোন
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. পরিবেশ ভেরিয়েবল কনফিগার
cp .env.example .env
# .env সম্পাদনা করে সব পাসওয়ার্ড ও কী পরিবর্তন করুন:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET ইত্যাদি

# 3. সব সার্ভিস চালু
docker compose up -d

# 4. লগ দেখা
docker compose logs -f service
docker compose logs -f admin

# 5. অ্যাক্সেস
# API: http://localhost/api
# ম্যানেজমেন্ট ব্যাকএন্ড: http://admin.localhost
```

### 1.3 সার্ভিস তালিকা

| সার্ভিস | পোর্ট | বিবরণ |
|------|------|------|
| nginx | 80, 443 | রিভার্স প্রক্সি |
| service | 8787 (ইন্টারনাল) | PHP বিজনেস API |
| admin | 8788 (ইন্টারনাল) | ম্যানেজমেন্ট ব্যাকএন্ড |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 প্রোডাকশন চেকলিস্ট

- [ ] `.env`-এ সব কী র্যান্ডম মানে পরিবর্তন করা হয়েছে
- [ ] `STRIPE_MODE=live` (প্রোডাকশন)
- [ ] `APP_ENV=production`
- [ ] `config/app.php`-এ `debug` `false` সেট করা আছে
- [ ] SSL সার্টিফিকেট কনফিগার করা আছে (nginx+Let's Encrypt)
- [ ] ডেটাবেসে রুটের `install.sql` ইমপোর্ট করা হয়েছে (117 টি টেবিল, ওয়েব ইনস্টলেশন উইজার্ড স্বয়ংক্রিয়ভাবে ইমপোর্ট করে)
- [ ] ES ইনডেক্স তৈরি হয়েছে: `php start.php scout:import "app\model\Products"`
- [ ] CDN কনফিগার করা হয়েছে: `CDN_ENABLED=true` + `CDN_DOMAIN` (CNAME admin ডোমেইনে) + নির্বাচিত প্রোভাইডারের ক্রেডেনশিয়াল
- [ ] আপলোড docker ভলিউম স্থায়ী (admin_uploads / service_public)
- [ ] MySQL/Redis/ES ডেটা ভলিউম ব্যাকআপ কনফিগার করা আছে

## 2. ম্যানুয়াল ডিপ্লয়মেন্ট

### 2.1 পরিবেশ ডিপেন্ডেন্সি

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (অপশনাল, সার্চ ফিচারের জন্য প্রয়োজন)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# .env সম্পাদনা করুন
composer install --no-dev --optimize-autoloader
php start.php start -d
# লিসেনিং: http://0.0.0.0:8787
```

### 2.3 Admin ম্যানেজমেন্ট ব্যাকএন্ড

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# লিসেনিং: http://0.0.0.0:8787 (আলাদা পোর্ট Nginx রিভার্স প্রক্সিতে ভাগ করতে হবে)
```

### 2.4 Nginx রিভার্স প্রক্সি

```nginx
# docker/nginx/conf.d/shop.conf দেখুন
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
# এজ ক্যাশিং (CDN-এর অরিজিন রেসপন্স):
# location /app/admin/upload/ { expires 7d; add_header Cache-Control "public, max-age=604800, immutable"; }
```

## 3. ডেটাবেস ইনিশিয়ালাইজেশন

```bash
# ডেটাবেস তৈরি
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# টেবিল স্ট্রাকচার ইমপোর্ট
mysql -u root -p erik_shop < install.sql

# সিড ডেটা ইমপোর্ট (অপশনাল)
php -r "
require 'vendor/autoload.php';
// দেশ/মুদ্রা/HS Code/লজিস্টিক জোন ইত্যাদি সিড ডেটা ইমপোর্ট
"
```

## 4. পরিবেশ ভেরিয়েবল রেফারেন্স

| ভেরিয়েবল | ডিফল্ট মান | বিবরণ |
|------|--------|------|
| APP_ENV | production | অ্যাপ্লিকেশন এনভায়রনমেন্ট |
| DB_HOST | 127.0.0.1 | ডেটাবেস ঠিকানা |
| DB_PORT | 3306 | ডেটাবেস পোর্ট |
| DB_NAME | erik_shop | ডেটাবেস নাম |
| DB_USER | erik | ডেটাবেস ইউজার |
| DB_PASS | (বাধ্যতামূলক) | ডেটাবেস পাসওয়ার্ড |
| REDIS_HOST | 127.0.0.1 | Redis ঠিকানা |
| JWT_SECRET | (বাধ্যতামূলক) | JWT সিগনেচার কী (256bit) |
| HASHIDS_SALT | (বাধ্যতামূলক) | Hashids সল্ট মান |
| ENCRYPTION_KEY | (বাধ্যতামূলক) | AES এনক্রিপশন কী |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Stripe কী |
| STRIPE_WEBHOOK_SECRET | - | Stripe Webhook সিগনেচার ভেরিফিকেশন |
| CDN_ENABLED | false | CDN গ্লোবাল সুইচ (true = URL রিরাইট + পার্জ সক্ষম) |
| CDN_DEFAULT_PROVIDER | cloudflare | ডিফল্ট প্রোভাইডার (cloudflare/cloudfront/aliyun/tencent) |
| CDN_DOMAIN | - | CDN ডোমেইন (যেমন cdn.erik.xyz, CNAME admin ডোমেইনে) |
| CF_API_TOKEN | - | Cloudflare API Token |
| CF_ZONE_ID | - | Cloudflare Zone ID |
| AWS_ACCESS_KEY_ID | - | AWS কী (CloudFront) |
| AWS_SECRET_ACCESS_KEY | - | AWS সিক্রেট |
| AWS_REGION | us-east-1 | AWS অঞ্চল |
| CLOUDFRONT_DISTRIBUTION_ID | - | CloudFront ডিস্ট্রিবিউশন ID |
| ALIYUN_ACCESS_KEY_ID | - | Aliyun AccessKey ID |
| ALIYUN_ACCESS_KEY_SECRET | - | Aliyun AccessKey Secret |
| TENCENT_SECRET_ID | - | Tencent SecretId |
| TENCENT_SECRET_KEY | - | Tencent SecretKey |

## 5. অপারেশন কমান্ড

```bash
# Service API
cd service
php start.php status        # স্ট্যাটাস দেখা
php start.php reload        # স্মুথ রিস্টার্ট
php start.php stop          # বন্ধ

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # কনটেইনার স্ট্যাটাস দেখা
docker compose logs -f      # লগ দেখা
docker compose restart      # সব রিস্টার্ট
docker compose down         # বন্ধ
```
