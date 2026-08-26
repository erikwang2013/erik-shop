# क्रॉस-बॉर्डर ई-कॉमर्स प्लेटफ़ॉर्म — तैनाती दस्तावेज़

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Docker तैनाती (अनुशंसित)

### 1.1 पर्यावरण आवश्यकताएँ

- Docker 24.0+ / Docker Compose v2
- होस्ट: Linux (अनुशंसित Ubuntu 22.04+)
- मेमोरी: न्यूनतम 4GB, अनुशंसित 8GB+

### 1.2 तैनाती चरण

```bash
# 1. प्रोजेक्ट क्लोन करें
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. पर्यावरण चर कॉन्फ़िगर करें
cp .env.example .env
# सभी पासवर्ड और कुंजियाँ बदलने के लिए .env संपादित करें:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET आदि

# 3. सभी सेवाएँ शुरू करें
docker compose up -d

# 4. लॉग देखें
docker compose logs -f service
docker compose logs -f admin

# 5. एक्सेस
# API: http://localhost/api
# प्रशासन पैनल: http://admin.localhost
```

### 1.3 सेवा सूची

| सेवा | पोर्ट | विवरण |
|------|------|------|
| nginx | 80, 443 | रिवर्स प्रॉक्सी |
| service | 8787 (आंतरिक) | PHP व्यावसायिक API |
| admin | 8788 (आंतरिक) | प्रशासन पैनल |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 प्रोडक्शन पर्यावरण जाँच सूची

- [ ] `.env` में सभी कुंजियाँ यादृच्छिक मानों में बदली गईं
- [ ] `STRIPE_MODE=live` (प्रोडक्शन पर्यावरण)
- [ ] `APP_ENV=production`
- [ ] `config/app.php` में `debug` को `false` पर सेट करें
- [ ] SSL प्रमाणपत्र कॉन्फ़िगरेशन (nginx+Let's Encrypt)
- [ ] डेटाबेस में रूट `install.sql` आयात (117 टेबलें, Web स्थापना विज़ार्ड स्वतः आयात करता है)
- [ ] ES इंडेक्स बनाया गया: `php start.php scout:import "app\model\Products"`
- [ ] MySQL/Redis/ES डेटा वॉल्यूम बैकअप कॉन्फ़िगर किया गया

## 2. मैनुअल तैनाती

### 2.1 पर्यावरण निर्भरताएँ

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (वैकल्पिक, खोज कार्यक्षमता के लिए आवश्यक)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# .env संपादित करें
composer install --no-dev --optimize-autoloader
php start.php start -d
# सुनना: http://0.0.0.0:8787
```

### 2.3 Admin प्रशासन पैनल

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# सुनना: http://0.0.0.0:8787 (दूसरे पोर्ट के लिए Nginx रिवर्स प्रॉक्सी से अलग करना होगा)
```

### 2.4 Nginx रिवर्स प्रॉक्सी

```nginx
# docker/nginx/conf.d/shop.conf देखें
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
```

## 3. डेटाबेस आरंभीकरण

```bash
# डेटाबेस बनाएँ
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# टेबल संरचना आयात करें
mysql -u root -p erik_shop < install.sql

# सीड डेटा आयात करें (वैकल्पिक)
php -r "
require 'vendor/autoload.php';
// देश/मुद्रा/HS Code/लॉजिस्टिक्स ज़ोन आदि सीड डेटा आयात करें
"
```

## 4. पर्यावरण चर संदर्भ

| चर | डिफ़ॉल्ट मान | विवरण |
|------|--------|------|
| APP_ENV | production | एप्लिकेशन पर्यावरण |
| DB_HOST | 127.0.0.1 | डेटाबेस पता |
| DB_PORT | 3306 | डेटाबेस पोर्ट |
| DB_NAME | erik_shop | डेटाबेस नाम |
| DB_USER | erik | डेटाबेस उपयोगकर्ता |
| DB_PASS | (आवश्यक) | डेटाबेस पासवर्ड |
| REDIS_HOST | 127.0.0.1 | Redis पता |
| JWT_SECRET | (आवश्यक) | JWT हस्ताक्षर कुंजी (256bit) |
| HASHIDS_SALT | (आवश्यक) | Hashids नमक मान |
| ENCRYPTION_KEY | (आवश्यक) | AES एन्क्रिप्शन कुंजी |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Stripe कुंजी |
| STRIPE_WEBHOOK_SECRET | - | Stripe Webhook हस्ताक्षर सत्यापन |

## 5. संचालन कमांड

```bash
# Service API
cd service
php start.php status        # स्थिति देखें
php start.php reload        # स्मूथ रीस्टार्ट
php start.php stop          # रोकें

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # कंटेनर स्थिति देखें
docker compose logs -f      # लॉग देखें
docker compose restart      # सभी रीस्टार्ट करें
docker compose down         # रोकें
```
