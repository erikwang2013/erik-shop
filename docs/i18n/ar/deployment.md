# منصة التجارة الإلكترونية عبر الحدود — وثيقة النشر

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. النشر عبر Docker (موصى به)

### 1.1 المتطلبات البيئية

- Docker 24.0+ / Docker Compose v2
- النظام المضيف: Linux (يُوصى بـ Ubuntu 22.04+)
- الذاكرة: الحد الأدنى 4GB، ويُوصى بـ 8GB+

### 1.2 خطوات النشر

```bash
# 1. استنساخ المشروع
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. إعداد متغيرات البيئة
cp .env.example .env
# حرّر .env لتغيير جميع كلمات المرور والمفاتيح:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET وغيرها

# 3. تشغيل جميع الخدمات
docker compose up -d

# 4. عرض السجلات
docker compose logs -f service
docker compose logs -f admin

# 5. الوصول
# API: http://localhost/api
# لوحة الإدارة: http://admin.localhost
```

### 1.3 قائمة الخدمات

| الخدمة | المنفذ | الوصف |
|------|------|------|
| nginx | 80, 443 | الوكيل العكسي |
| service | 8787 (داخلي) | API أعمال PHP |
| admin | 8788 (داخلي) | لوحة الإدارة |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 قائمة فحص بيئة الإنتاج

- [ ] جميع المفاتيح في `.env` تم تغييرها إلى قيم عشوائية
- [ ] `STRIPE_MODE=live` (بيئة الإنتاج)
- [ ] `APP_ENV=production`
- [ ] ضبط `debug` على `false` في `config/app.php`
- [ ] تكوين شهادة SSL (nginx + Let's Encrypt)
- [ ] تم استيراد قاعدة البيانات من `install.sql` في الجذر (117 جدولًا، يستورده معالج التثبيت عبر الويب تلقائيًا)
- [ ] تم إنشاء فهارس ES: `php start.php scout:import "app\model\Products"`
- [ ] تم إعداد نسخ احتياطي لأحجام بيانات MySQL/Redis/ES

## 2. النشر اليدوي

### 2.1 تبعيات البيئة

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (اختياري، مطلوب لوظيفة البحث)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# حرّر .env
composer install --no-dev --optimize-autoloader
php start.php start -d
# يستمع: http://0.0.0.0:8787
```

### 2.3 لوحة إدارة Admin

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# يستمع: http://0.0.0.0:8787 (منفذ آخر يجب التمييز بينه عبر Nginx الوكيل العكسي)
```

### 2.4 Nginx الوكيل العكسي

```nginx
# انظر docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
```

## 3. تهيئة قاعدة البيانات

```bash
# إنشاء قاعدة البيانات
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# استيراد بنية الجداول
mysql -u root -p erik_shop < install.sql

# استيراد بيانات البذور (اختياري)
php -r "
require 'vendor/autoload.php';
// استيراد بيانات البذور مثل الدول/العملات/رموز HS Code/مناطق الشحن
"
```

## 4. مرجع متغيرات البيئة

| المتغير | القيمة الافتراضية | الوصف |
|------|--------|------|
| APP_ENV | production | بيئة التطبيق |
| DB_HOST | 127.0.0.1 | عنوان قاعدة البيانات |
| DB_PORT | 3306 | منفذ قاعدة البيانات |
| DB_NAME | erik_shop | اسم قاعدة البيانات |
| DB_USER | erik | مستخدم قاعدة البيانات |
| DB_PASS | (إلزامي) | كلمة مرور قاعدة البيانات |
| REDIS_HOST | 127.0.0.1 | عنوان Redis |
| JWT_SECRET | (إلزامي) | مفتاح توقيع JWT (256bit) |
| HASHIDS_SALT | (إلزامي) | قيمة ملح Hashids |
| ENCRYPTION_KEY | (إلزامي) | مفتاح تشفير AES |
| SNOWFLAKE_WORKER_ID | 1 | معرّف عامل Snowflake (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | مفتاح Stripe |
| STRIPE_WEBHOOK_SECRET | - | التحقق من توقيع Stripe Webhook |

## 5. أوامر التشغيل والصيانة

```bash
# Service API
cd service
php start.php status        # عرض الحالة
php start.php reload        # إعادة تشغيل سلسة
php start.php stop          # إيقاف

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # عرض حالة الحاويات
docker compose logs -f      # عرض السجلات
docker compose restart      # إعادة تشغيل الكل
docker compose down         # إيقاف
```
