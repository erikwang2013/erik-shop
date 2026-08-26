# ক্রস-বর্ডার ই-কমার্স প্ল্যাটফর্ম — ইনস্টলেশন গাইড

> এই নথিটি মূল চীনা ডকুমেন্টেশনের মেশিন অনুবাদ। মূল: [চীনা মূল](../../INSTALL.md).
>
> Cross-border E-Commerce Platform Installation Guide
>
> [চীনা README](../../../README.md) | [ইংরেজি README](../../README-EN.md) | [মূল্যায়ন রিপোর্ট](../../AUDIT-REPORT.md)

---

## পরিবেশের প্রয়োজনীয়তা / Requirements

| কম্পোনেন্ট | সর্বনিম্ন ভার্সন | প্রস্তাবিত ভার্সন |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (অপশনাল/optional) |

### PHP এক্সটেনশন

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## ইনস্টলেশন পদ্ধতি / Installation Methods

### পদ্ধতি ১ (সুপারিশকৃত): ওয়েব ওয়ান-ক্লিক ইনস্টলেশন উইজার্ড

ব্রাউজার দিয়ে ইনস্টলেশন পেজে প্রবেশ করে ডেটাবেস তথ্য ও অ্যাডমিন অ্যাকাউন্ট পূরণ করুন — **টেবিল তৈরি, কনফিগারেশন, অ্যাডমিন তৈরি সব স্বয়ংক্রিয়ভাবে সম্পন্ন হয়**।

```bash
# 1. ডিপেন্ডেন্সি ইনস্টল
cd admin/
composer install

# 2. ম্যানেজমেন্ট ব্যাকএন্ড চালু করুন
php start.php start

# 3. ব্রাউজার দিয়ে অ্যাক্সেস করুন (প্রথমবার স্বয়ংক্রিয়ভাবে ইনস্টল পেজে যাবে)
# http://127.0.0.1:8788/app/admin/install/step1
```

ইনস্টলেশন উইজার্ড **স্বয়ংক্রিয়ভাবে** সম্পন্ন করে:
- MySQL ডেটাবেস তৈরি (যদি না থাকে)
- `install.sql`-এর সবগুলো 117 টি টেবিল ইমপোর্ট (7 টি `wa_` + 110 টি `erik_`)
- ম্যানেজমেন্ট ব্যাকএন্ড মেনু ইমপোর্ট
- `plugin/admin/config/database.php` ও `thinkorm.php` তৈরি
- `service/.env` তৈরি (এলোমেলোভাবে তৈরি JWT/Hashids/এনক্রিপশন কী সহ)
- সুপার অ্যাডমিন অ্যাকাউন্ট তৈরি
- SIGUSR1 সিগন্যাল পাঠিয়ে সার্ভিস রিলোড ট্রিগার

> ইনস্টলেশন শেষ হলে `service/` API সার্ভিসও চালু করতে হবে (নিচের ধাপ ৫ দেখুন)।

---

### পদ্ধতি ২: ম্যানুয়াল ইনস্টলেশন / Manual Installation

<details>
<summary>কমান্ড লাইন ডিপ্লয়মেন্ট বা বিদ্যমান ডেটাবেস পরিবেশের জন্য প্রযোজ্য</summary>

### 1. ডেটাবেস তৈরি

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. ডেটাবেস ইমপোর্ট

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql`-এ **117 টি টেবিল** ও ডিফল্ট সিড ডেটা রয়েছে।

### 3. service/.env কনফিগার করুন

```bash
cd service/
cp .env.example .env
# .env সম্পাদনা করে প্রকৃত ডেটাবেস/Redis/JWT ইত্যাদি প্যারামিটার পূরণ করুন
```

**মূল কনফিগারেশন আইটেম:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<র্যান্ডম 32 বাইট কী>
HASHIDS_SALT=<র্যান্ডম সল্ট>
ENCRYPTION_KEY=<র্যান্ডম 32 বাইট কী>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. admin/ কনফিগার করুন

```bash
cd admin/
cp .env.example .env
# .env সম্পাদনা করে service-এর মতো একই ডেটাবেস তথ্য পূরণ করুন
```

### 5. অ্যাডমিন অ্যাকাউন্ট তৈরি

```sql
-- পাসওয়ার্ড bcrypt দিয়ে তৈরি করতে হবে
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'সুপার অ্যাডমিন', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### পদ্ধতি ৩: Docker ডিপ্লয়মেন্ট / Docker Deployment

```bash
# 1. পরিবেশ ভেরিয়েবল কনফিগার
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. সব সার্ভিস চালু
docker-compose up -d

# 3. ওয়েব ইনস্টলেশন উইজার্ড চালান
# http://localhost/app/admin/install/step1
```

Docker সার্ভিস: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### সার্ভিস চালু করা / Start Services

```bash
# ডিপেন্ডেন্সি ইনস্টল (দুটি প্রজেক্টেই দরকার)
cd service/ && composer install
cd admin/ && composer install

# API সার্ভিস চালু
cd service/
php start.php start -d

# ম্যানেজমেন্ট ব্যাকএন্ড চালু
cd admin/
php start.php start -d
```

| সার্ভিস | ডিফল্ট পোর্ট | ভেরিফিকেশন পদ্ধতি |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| ম্যানেজমেন্ট ব্যাকএন্ড | 8788 | ব্রাউজারে `http://127.0.0.1:8788/app/admin` |

### সিড ডেটা ইমপোর্ট (অপশনাল) / Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # দেশ/অঞ্চল
php start.php seed:currencies    # মুদ্রা
php start.php seed:hs_codes      # HS Code কোড
php start.php seed:compliance    # কমপ্লায়েন্স ক্যাটাগরি
```

---

## ডিরেক্টরি স্ট্রাকচার / Directory Structure

```
shop-php/
├── install.sql              # মার্জ করা সম্পূর্ণ ইনস্টলেশন SQL
├── admin/                   # ম্যানেজমেন্ট ব্যাকএন্ড (webman-admin + LayUI)
│   ├── config/database.php  # ডেটাবেস কনফিগারেশন
│   ├── plugin/admin/        # webman-admin প্লাগইন
│   └── start.php
├── service/                 # API সার্ভিস (webman RESTful)
│   ├── config/              # কনফিগারেশন ফাইল
│   ├── database/schema.sql  # মূল বিজনেস টেবিল SQL (install.sql দিয়ে প্রতিস্থাপিত হয়েছে)
│   ├── database/seeders/    # সিড ডেটা
│   └── start.php
```

---

## ডেটাবেস স্ট্রাকচার ওভারভিউ / Database Schema Overview

| মডিউল | টেবিল প্রিফিক্স | টেবিল সংখ্যা | বিবরণ |
|------|--------|--------|------|
| ম্যানেজমেন্ট ব্যাকএন্ড সিস্টেম | `wa_` | 7 | অ্যাডমিন/রোল/পারমিশন/কনফিগ/অ্যাটাচমেন্ট |
| ইউজার ও অ্যাকাউন্ট | `erik_users_*` | 7 | ইউজার/ঠিকানা/সোশ্যাল/KYC/ফেভারিট/মেম্বার |
| প্রোডাক্ট ও ক্যাটাগরি | `erik_product_*` | 16 | প্রোডাক্ট/SKU/মাল্টি-ল্যাঙ্গুয়েজ/মাল্টি-কারেন্সি/রিভিউ/কমপ্লায়েন্স/HS |
| কার্ট ও অর্ডার | `erik_order_*` | 9 | কার্ট/অর্ডার/পেমেন্ট/রিফান্ড/রিটার্ন/ক্লিয়ারেন্স |
| দেশ/মুদ্রা/লজিস্টিক | `erik_shipping_*` | 11 | দেশ/মুদ্রা/এক্সচেঞ্জ রেট/লজিস্টিক/জোন/ওয়ারহাউস/স্টক |
| কাস্টমস ও ট্যাক্স | `erik_hs_*` | 5 | HS কোড/শুল্ক/VAT/কমপ্লায়েন্স বিধিনিষেধ |
| পেমেন্ট ও ফান্ড | `erik_payment_*` | 6 | পেমেন্ট গেটওয়ে/প্ল্যাটফর্ম সেটেলমেন্ট/সাপ্লায়ার সেটেলমেন্ট/রেট লাভ-লস |
| মার্কেটিং | `erik_coupon_*` | 9 | কুপন/ফ্ল্যাশ সেল/গ্রুপ বাই/ডিস্ট্রিবিউশন |
| সাপ্লাই চেইন | `erik_supplier_*` | 7 | সাপ্লায়ার/প্রকিউরমেন্ট/কোয়ালিটি চেক |
| রিস্ক ও কমপ্লায়েন্স | `erik_risk_*` | 6 | রিস্ক রুল/GDPR/Cookie/প্রাইভেসি |
| মাল্টি-প্ল্যাটফর্ম | `erik_platform_*` | 8 | মাল্টি-স্টোর/প্ল্যাটফর্ম অ্যাকাউন্ট/লিস্টিং/সেলার |
| কনটেন্ট ও এক্সপেরিয়েন্স | `erik_*` | 12 | CMS/Feed/সাইজ/নোটিফিকেশন/ইমেইল/সার্চ/অপারেশন লগ |
| সাবস্ক্রিপশন/পয়েন্ট ইত্যাদি | `erik_*` | 7 | সাবস্ক্রিপশন/পয়েন্ট/গিফট কার্ড/B2B |
| AB টেস্ট/API/সেটিংস | `erik_*` | 7 | AB টেস্ট/রেট লিমিট/API ডক/সিস্টেম কনফিগ |

---

## সাধারণ সমস্যা / Troubleshooting

### MySQL এরর "Specified key was too long"

```sql
-- utf8mb4 + InnoDB ব্যবহার নিশ্চিত করুন এবং innodb_large_prefix সক্রিয় করুন
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### পোর্ট কনফ্লিক্ট / Port Conflict

`admin/.env` বা `service/.env`-এ `APP_PORT` পরিবর্তন করুন।

### Redis সংযোগ ব্যর্থ

Redis এক্সটেনশন ইনস্টল করা আছে এবং Redis সার্ভিস চলছে কিনা যাচাই করুন:
```bash
redis-cli ping  # PONG ফেরত দেবে
```

### Snowflake ID কনফ্লিক্ট

একাধিক সার্ভার একসাথে চললে প্রতিটি সার্ভারের `SNOWFLAKE_WORKER_ID` আলাদা (0-31) রাখুন।

---

## ডেভেলপমেন্ট কমান্ড রেফারেন্স / Development Commands

```bash
# service/ (API)
php start.php start          # চালু
php start.php start -d       # ডেমন প্রসেস
php start.php reload         # হট রিলোড
php start.php stop           # বন্ধ
php start.php status         # স্ট্যাটাস

# admin/ (ম্যানেজমেন্ট ব্যাকএন্ড)
php start.php start
php start.php start -d
php start.php reload
```
