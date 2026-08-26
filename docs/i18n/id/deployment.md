# Platform E-Commerce Lintas Batas — Dokumen Deployment

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Deployment Docker (Direkomendasikan)

### 1.1 Persyaratan Lingkungan

- Docker 24.0+ / Docker Compose v2
- Host: Linux (disarankan Ubuntu 22.04+)
- Memori: minimal 4GB, disarankan 8GB+

### 1.2 Langkah Deployment

```bash
# 1. Klon proyek
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. Konfigurasi variabel lingkungan
cp .env.example .env
# Edit .env untuk mengubah semua password dan kunci:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, dll.

# 3. Mulai semua layanan
docker compose up -d

# 4. Lihat log
docker compose logs -f service
docker compose logs -f admin

# 5. Akses
# API: http://localhost/api
# Panel admin: http://admin.localhost
```

### 1.3 Daftar Layanan

| Layanan | Port | Penjelasan |
|------|------|------|
| nginx | 80, 443 | Reverse proxy |
| service | 8787 (internal) | API bisnis PHP |
| admin | 8788 (internal) | Panel admin |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 Checklist Lingkungan Produksi

- [ ] Semua kunci di `.env` telah diubah menjadi nilai acak
- [ ] `STRIPE_MODE=live` (lingkungan produksi)
- [ ] `APP_ENV=production`
- [ ] `debug` di `config/app.php` diset ke `false`
- [ ] Konfigurasi sertifikat SSL (nginx+Let's Encrypt)
- [ ] Database telah mengimpor `install.sql` di root (117 tabel, diimpor otomatis oleh wizard instalasi Web)
- [ ] Indeks ES telah dibuat: `php start.php scout:import "app\model\Products"`
- [ ] Volume data MySQL/Redis/ES telah dikonfigurasi backup

## 2. Deployment Manual

### 2.1 Dependensi Lingkungan

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (opsional, diperlukan untuk fitur pencarian)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# Edit .env
composer install --no-dev --optimize-autoloader
php start.php start -d
# Mendengarkan: http://0.0.0.0:8787
```

### 2.3 Panel Admin

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# Mendengarkan: http://0.0.0.0:8787 (port lain perlu dibedakan oleh reverse proxy Nginx)
```

### 2.4 Reverse Proxy Nginx

```nginx
# Lihat docker/nginx/conf.d/shop.conf
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
```

## 3. Inisialisasi Database

```bash
# Buat database
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Impor struktur tabel
mysql -u root -p erik_shop < install.sql

# Impor data seed (opsional)
php -r "
require 'vendor/autoload.php';
// Impor data seed negara/mata uang/HS Code/zona logistik, dll.
"
```

## 4. Referensi Variabel Lingkungan

| Variabel | Nilai Default | Penjelasan |
|------|--------|------|
| APP_ENV | production | Lingkungan aplikasi |
| DB_HOST | 127.0.0.1 | Alamat database |
| DB_PORT | 3306 | Port database |
| DB_NAME | erik_shop | Nama database |
| DB_USER | erik | Pengguna database |
| DB_PASS | (wajib) | Password database |
| REDIS_HOST | 127.0.0.1 | Alamat Redis |
| JWT_SECRET | (wajib) | Kunci tanda tangan JWT (256bit) |
| HASHIDS_SALT | (wajib) | Salt Hashids |
| ENCRYPTION_KEY | (wajib) | Kunci enkripsi AES |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Kunci Stripe |
| STRIPE_WEBHOOK_SECRET | - | Verifikasi tanda tangan Webhook Stripe |

## 5. Perintah Operasional

```bash
# Service API
cd service
php start.php status        # Lihat status
php start.php reload        # Restart mulus
php start.php stop          # Berhenti

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # Lihat status kontainer
docker compose logs -f      # Lihat log
docker compose restart      # Restart semua
docker compose down         # Berhenti
```
