# منصة التجارة الإلكترونية عبر الحدود — دليل التثبيت

> Cross-border E-Commerce Platform Installation Guide
>
> [中文 README](../../../README.md) | [English README](../../README-EN.md) | [تقرير المراجعة](../../AUDIT-REPORT.md)

---

## المتطلبات البيئية / Requirements

| المكوّن | أقل إصدار | الإصدار الموصى به |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (اختياري/optional) |

### إضافات PHP

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## طرق التثبيت / Installation Methods

### الطريقة الأولى (موصى بها): معالج التثبيت بنقرة واحدة عبر الويب

من خلال زيارة صفحة التثبيت في المتصفح وإدخال معلومات قاعدة البيانات وحساب المدير، **يتم تلقائيًا وبشكل كامل إنشاء الجداول والتكوين وحساب المدير**.

```bash
# 1. تثبيت التبعيات
cd admin/
composer install

# 2. تشغيل لوحة الإدارة
php start.php start

# 3. زيارة المتصفح (يُحوَّل تلقائيًا إلى صفحة التثبيت في أول مرة)
# http://127.0.0.1:8788/app/admin/install/step1
```

سينهي معالج التثبيت **تلقائيًا**:
- إنشاء قاعدة بيانات MySQL (إن لم تكن موجودة)
- استيراد جميع جداول `install.sql` الـ117 (7 جداول `wa_` + 110 جداول `erik_`)
- استيراد قوائم لوحة الإدارة
- إنشاء `plugin/admin/config/database.php` و `thinkorm.php`
- إنشاء `service/.env` (يشمل مفاتيح JWT/Hashids/تشفير مولّدة عشوائيًا)
- إنشاء حساب المدير الفائق
- إرسال إشارة SIGUSR1 لتشغيل إعادة تحميل الخدمة

> بعد اكتمال التثبيت، يجب أيضًا تشغيل خدمة API في service/ (انظر الخطوة 5 أدناه).

---

### الطريقة الثانية: التثبيت اليدوي / Manual Installation

<details>
<summary>مناسب للنشر عبر سطر الأوامر أو بيئات قواعد البيانات الموجودة</summary>

### 1. إنشاء قاعدة البيانات

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. استيراد قاعدة البيانات

```bash
mysql -u root -p shop_db < install.sql
```

> يحتوي `install.sql` على **117 جدولًا** وبيانات أولية افتراضية.

### 3. إعداد service/.env

```bash
cd service/
cp .env.example .env
# حرّر .env لإدخال معاملات قاعدة البيانات/Redis/JWT الفعلية
```

**بنود التكوين الأساسية:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<مفتاح عشوائي 32 بايت>
HASHIDS_SALT=<ملح عشوائي>
ENCRYPTION_KEY=<مفتاح عشوائي 32 بايت>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. إعداد admin/

```bash
cd admin/
cp .env.example .env
# حرّر .env وأدخل نفس معلومات قاعدة البيانات المستخدمة في service
```

### 5. إنشاء حساب المدير

```sql
-- يجب توليد كلمة المرور عبر bcrypt
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'المدير الفائق', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### الطريقة الثالثة: النشر عبر Docker / Docker Deployment

```bash
# 1. إعداد متغيرات البيئة
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. تشغيل جميع الخدمات
docker-compose up -d

# 3. تشغيل معالج التثبيت عبر الويب
# http://localhost/app/admin/install/step1
```

خدمات Docker: Nginx(:80) → service(:8787) + admin(:8788)، MySQL(:3306)، Redis(:6379)، ES(:9200)

---

### تشغيل الخدمات / Start Services

```bash
# تثبيت التبعيات (مطلوب للمشروعين)
cd service/ && composer install
cd admin/ && composer install

# تشغيل خدمة API
cd service/
php start.php start -d

# تشغيل لوحة الإدارة
cd admin/
php start.php start -d
```

| الخدمة | المنفذ الافتراضي | طريقة التحقق |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| لوحة الإدارة | 8788 | زيارة المتصفح `http://127.0.0.1:8788/app/admin` |

### استيراد البيانات الأولية (اختياري) / Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # الدول/المناطق
php start.php seed:currencies    # العملات
php start.php seed:hs_codes      # رموز HS Code
php start.php seed:compliance    # تصنيفات الامتثال
```

---

## هيكل الدلائل / Directory Structure

```
shop-php/
├── install.sql              # SQL التثبيت الكامل المدمج
├── admin/                   # لوحة الإدارة (webman-admin + LayUI)
│   ├── config/database.php  # إعداد قاعدة البيانات
│   ├── plugin/admin/        # إضافة webman-admin
│   └── start.php
├── service/                 # خدمة API (webman RESTful)
│   ├── config/              # ملفات الإعداد
│   ├── database/schema.sql  # SQL جداول الأعمال الأصلي (استُبدل بـ install.sql)
│   ├── database/seeders/    # البيانات الأولية
│   └── start.php
```

---

## نظرة عامة على بنية قاعدة البيانات / Database Schema Overview

| الوحدة | بادئة الجداول | عدد الجداول | الوصف |
|------|--------|--------|------|
| نظام لوحة الإدارة | `wa_` | 7 | المديرون/الأدوار/الصلاحيات/الإعدادات/المرفقات |
| المستخدمون والحسابات | `erik_users_*` | 7 | المستخدمون/العناوين/الاجتماعي/KYC/المفضلة/العضوية |
| المنتجات والتصنيفات | `erik_product_*` | 16 | المنتجات/SKU/متعدد اللغات/متعدد العملات/التقييمات/الامتثال/HS |
| سلة التسوق والطلبات | `erik_order_*` | 9 | سلة التسوق/الطلبات/الدفع/الاسترداد/الإرجاع/التخليص |
| الدول/العملات/اللوجستيات | `erik_shipping_*` | 11 | الدول/العملات/أسعار الصرف/اللوجستيات/المناطق/المستودعات/المخزون |
| الجمارك والضرائب | `erik_hs_*` | 5 | رموز HS/الرسوم الجمركية/VAT/قيود الامتثال |
| الدفع والأموال | `erik_payment_*` | 6 | بوابات الدفع/تسوية المنصة/تسوية الموردين/أرباح الصرف |
| التسويق | `erik_coupon_*` | 9 | قسائم الخصم/البيع الخاطف/الشراء الجماعي/التوزيع |
| سلسلة التوريد | `erik_supplier_*` | 7 | الموردون/المشتريات/فحص الجودة |
| المخاطر والامتثال | `erik_risk_*` | 6 | قواعد المخاطر/GDPR/Cookie/الخصوصية |
| متعدد المنصات | `erik_platform_*` | 8 | متاجر متعددة/حسابات المنصات/النشر/البائعون |
| المحتوى والتجربة | `erik_*` | 12 | CMS/Feed/المقاسات/الإشعارات/البريد/البحث/سجلات العمليات |
| الاشتراكات/النقاط إلخ | `erik_*` | 7 | الاشتراكات/النقاط/بطاقات الهدايا/B2B |
| اختبار AB/API/الإعدادات | `erik_*` | 7 | اختبار AB/تحديد المعدل/وثائق API/إعدادات النظام |

---

## الأسئلة الشائعة / Troubleshooting

### خطأ MySQL "Specified key was too long"

```sql
-- تأكد من استخدام utf8mb4 + InnoDB مع تفعيل innodb_large_prefix
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### تعارض المنافذ / Port Conflict

عدّل `APP_PORT` في `admin/.env` أو `service/.env`.

### فشل الاتصال بـ Redis

تحقق من تثبيت إضافة Redis وتشغيل خدمة Redis:
```bash
redis-cli ping  # يجب أن يُرجع PONG
```

### تعارض معرفات Snowflake

إذا تمت تهيئة عدة خوادم في نفس الوقت، تأكد من اختلاف `SNOWFLAKE_WORKER_ID` لكل خادم (0-31).

---

## مرجع أوامر التطوير / Development Commands

```bash
# service/ (API)
php start.php start          # تشغيل
php start.php start -d       # تشغيل كعملية خلفية
php start.php reload         # إعادة تحميل ساخنة
php start.php stop           # إيقاف
php start.php status         # الحالة

# admin/ (لوحة الإدارة)
php start.php start
php start.php start -d
php start.php reload
```
