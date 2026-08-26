# Cross-Border E-Commerce Platform — Installation Guide

> Cross-border E-Commerce Platform Installation Guide
>
> [中文 README](../../../README.md) | [English README](../../README-EN.md) | [Audit Report](../../AUDIT-REPORT.md)

---

## Environment Requirements

| Component | Minimum Version | Recommended Version |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (optional) |

### PHP Extensions

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## Installation Methods

### Method 1 (Recommended): Web One-Click Installation Wizard

Access the installation page via a browser, fill in database information and an admin account, and **table creation, configuration, and admin creation are fully automated**.

```bash
# 1. Install dependencies
cd admin/
composer install

# 2. Start the admin console
php start.php start

# 3. Open in a browser (auto-redirects to the install page on first run)
# http://127.0.0.1:8788/app/admin/install/step1
```

The installation wizard **automatically**:
- Creates the MySQL database (if it does not exist)
- Imports all 117 tables from `install.sql` (7 `wa_` + 110 `erik_`)
- Imports the admin console menus
- Generates `plugin/admin/config/database.php` and `thinkorm.php`
- Generates `service/.env` (with randomly generated JWT/Hashids/encryption keys)
- Creates the super admin account
- Sends a SIGUSR1 signal to trigger service reload

> After installation, you also need to start the service/ API service (see step 5 below).

---

### Method 2: Manual Installation

<details>
<summary>For command-line deployment or existing database environments</summary>

### 1. Create the Database

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. Import the Database

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` contains **117 tables** and default seed data.

### 3. Configure service/.env

```bash
cd service/
cp .env.example .env
# Edit .env to set actual database/Redis/JWT and other parameters
```

**Key configuration items:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<random 32-byte key>
HASHIDS_SALT=<random salt>
ENCRYPTION_KEY=<random 32-byte key>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. Configure admin/

```bash
cd admin/
cp .env.example .env
# Edit .env, fill in the same database information as service
```

### 5. Create the Admin Account

```sql
-- Password must be generated with bcrypt
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'Super Admin', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### Method 3: Docker Deployment

```bash
# 1. Configure environment variables
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. Start all services
docker-compose up -d

# 3. Run the Web installation wizard
# http://localhost/app/admin/install/step1
```

Docker services: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### Start Services

```bash
# Install dependencies (both projects need it)
cd service/ && composer install
cd admin/ && composer install

# Start the API service
cd service/
php start.php start -d

# Start the admin console
cd admin/
php start.php start -d
```

| Service | Default Port | How to Verify |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| Admin console | 8788 | Browser: `http://127.0.0.1:8788/app/admin` |

### Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # Countries/regions
php start.php seed:currencies    # Currencies
php start.php seed:hs_codes      # HS Code encodings
php start.php seed:compliance    # Compliance categories
```

---

## Directory Structure

```
shop-php/
├── install.sql              # Merged full installation SQL
├── admin/                   # Admin console (webman-admin + LayUI)
│   ├── config/database.php  # Database configuration
│   ├── plugin/admin/        # webman-admin plugin
│   └── start.php
├── service/                 # API service (webman RESTful)
│   ├── config/              # Configuration files
│   ├── database/schema.sql  # Original business table SQL (superseded by install.sql)
│   ├── database/seeders/    # Seed data
│   └── start.php
```

---

## Database Schema Overview

| Module | Table Prefix | Table Count | Description |
|------|--------|--------|------|
| Admin system | `wa_` | 7 | Admins/roles/permissions/config/attachments |
| Users & Accounts | `erik_users_*` | 7 | Users/addresses/social/KYC/wishlists/membership |
| Products & Categories | `erik_product_*` | 16 | Products/SKU/multilingual/multi-currency/reviews/compliance/HS |
| Cart & Orders | `erik_order_*` | 9 | Cart/orders/payments/refunds/returns/customs clearance |
| Countries/Currencies/Logistics | `erik_shipping_*` | 11 | Countries/currencies/FX rates/logistics/zones/warehouses/inventory |
| Customs & Tax | `erik_hs_*` | 5 | HS codes/tariffs/VAT/compliance restrictions |
| Payments & Funds | `erik_payment_*` | 6 | Payment gateways/platform settlements/supplier settlement/FX gains-losses |
| Marketing | `erik_coupon_*` | 9 | Coupons/flash sales/group buys/affiliate |
| Supply Chain | `erik_supplier_*` | 7 | Suppliers/purchasing/QC |
| Risk & Compliance | `erik_risk_*` | 6 | Risk rules/GDPR/Cookie/privacy |
| Multi-Platform | `erik_platform_*` | 8 | Multi-store/platform accounts/listings/sellers |
| Content & Experience | `erik_*` | 12 | CMS/Feed/size charts/notifications/email/search/operation logs |
| Subscriptions/Points etc. | `erik_*` | 7 | Subscriptions/points/gift cards/B2B |
| AB Testing/API/Settings | `erik_*` | 7 | AB testing/rate limits/API docs/system config |

---

## Troubleshooting

### MySQL Error "Specified key was too long"

```sql
-- Ensure utf8mb4 + InnoDB are used and innodb_large_prefix is enabled
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### Port Conflict

Modify `APP_PORT` in `admin/.env` or `service/.env`.

### Redis Connection Failure

Check that the Redis extension is installed and the Redis service is running:
```bash
redis-cli ping  # Should return PONG
```

### Snowflake ID Conflicts

If multiple servers instantiate at the same time, ensure each server has a distinct `SNOWFLAKE_WORKER_ID` (0-31).

---

## Development Commands Quick Reference

```bash
# service/ (API)
php start.php start          # Start
php start.php start -d       # Daemon
php start.php reload         # Hot reload
php start.php stop           # Stop
php start.php status         # Status

# admin/ (admin console)
php start.php start
php start.php start -d
php start.php reload
```
