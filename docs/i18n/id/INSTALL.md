# Platform E-Commerce Lintas Batas — Panduan Instalasi

> Cross-border E-Commerce Platform Installation Guide
>
> [README Tionghoa](../../../README.md) | [English README](../../README-EN.md) | [Laporan Audit](../../AUDIT-REPORT.md)

---

## Persyaratan Lingkungan / Requirements

| Komponen | Versi Minimum | Versi yang Direkomendasikan |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (opsional/optional) |

### Ekstensi PHP

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## Metode Instalasi / Installation Methods

### Cara 1 (Direkomendasikan): Wizard Instalasi Web Sekali Klik

Akses halaman instalasi melalui browser, isi informasi database dan akun admin, **pembuatan tabel, konfigurasi, dan pembuatan admin dilakukan otomatis sepenuhnya**.

```bash
# 1. Instal dependensi
cd admin/
composer install

# 2. Mulai panel admin
php start.php start

# 3. Akses melalui browser (pertama kali otomatis diarahkan ke halaman instalasi)
# http://127.0.0.1:8788/app/admin/install/step1
```

Wizard instalasi akan **menyelesaikan otomatis**:
- Membuat database MySQL (jika belum ada)
- Mengimpor seluruh 117 tabel dari `install.sql` (7 tabel `wa_` + 110 tabel `erik_`)
- Mengimpor menu panel admin
- Menghasilkan `plugin/admin/config/database.php` dan `thinkorm.php`
- Menghasilkan `service/.env` (termasuk kunci JWT/Hashids/enkripsi yang dibuat acak)
- Membuat akun super admin
- Mengirim sinyal SIGUSR1 untuk memicu muat ulang layanan

> Setelah instalasi selesai, layanan API service/ juga perlu dimulai (lihat langkah 5 di bawah).

---

### Cara 2: Instalasi Manual / Manual Installation

<details>
<summary>Cocok untuk deployment baris perintah atau lingkungan database yang sudah ada</summary>

### 1. Buat database

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. Impor database

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` berisi **117 tabel** dan data seed default.

### 3. Konfigurasi service/.env

```bash
cd service/
cp .env.example .env
# Edit .env untuk mengonfigurasi parameter database/Redis/JWT yang sebenarnya
```

**Item konfigurasi kunci:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<kunci acak 32 byte>
HASHIDS_SALT=<salt acak>
ENCRYPTION_KEY=<kunci acak 32 byte>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. Konfigurasi admin/

```bash
cd admin/
cp .env.example .env
# Edit .env, isi informasi database yang sama dengan service
```

### 5. Buat akun admin

```sql
-- Password perlu dibuat dengan bcrypt
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'Super Administrator', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### Cara 3: Deployment Docker / Docker Deployment

```bash
# 1. Konfigurasi variabel lingkungan
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. Mulai semua layanan
docker-compose up -d

# 3. Jalankan wizard instalasi web
# http://localhost/app/admin/install/step1
```

Layanan Docker: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### Mulai Layanan / Start Services

```bash
# Instal dependensi (kedua proyek perlu)
cd service/ && composer install
cd admin/ && composer install

# Mulai layanan API
cd service/
php start.php start -d

# Mulai panel admin
cd admin/
php start.php start -d
```

| Layanan | Port Default | Cara Verifikasi |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| Panel Admin | 8788 | Akses browser `http://127.0.0.1:8788/app/admin` |

### Impor Data Seed (Opsional) / Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # Negara/Wilayah
php start.php seed:currencies    # Mata uang
php start.php seed:hs_codes      # Kode HS Code
php start.php seed:compliance    # Kategori kepatuhan
```

---

## Struktur Direktori / Directory Structure

```
shop-php/
├── install.sql              # SQL instalasi lengkap yang digabungkan
├── admin/                   # Panel admin (webman-admin + LayUI)
│   ├── config/database.php  # Konfigurasi database
│   ├── plugin/admin/        # Plugin webman-admin
│   └── start.php
├── service/                 # Layanan API (webman RESTful)
│   ├── config/              # File konfigurasi
│   ├── database/schema.sql  # SQL tabel bisnis asli (sudah digantikan oleh install.sql)
│   ├── database/seeders/    # Data seed
│   └── start.php
```

---

## Ringkasan Struktur Database / Database Schema Overview

| Modul | Prefiks Tabel | Jumlah Tabel | Keterangan |
|------|--------|--------|------|
| Sistem panel admin | `wa_` | 7 | Admin/role/permission/konfigurasi/lampiran |
| Pengguna dan akun | `erik_users_*` | 7 | Pengguna/alamat/sosial/KYC/favorit/anggota |
| Produk dan kategori | `erik_product_*` | 16 | Produk/SKU/multi-bahasa/multi-mata uang/ulasan/kepatuhan/HS |
| Keranjang dan pesanan | `erik_order_*` | 9 | Keranjang/pesanan/pembayaran/refund/retur/bea cukai |
| Negara/mata uang/logistik | `erik_shipping_*` | 11 | Negara/mata uang/nilai tukar/logistik/zona/gudang/stok |
| Bea cukai dan pajak | `erik_hs_*` | 5 | Kode HS/bea masuk/VAT/pembatasan kepatuhan |
| Pembayaran dan dana | `erik_payment_*` | 6 | Gateway pembayaran/settlement platform/settlement pemasok/laba-rugi kurs |
| Pemasaran | `erik_coupon_*` | 9 | Kupon/flash sale/beli grup/afiliasi |
| Rantai pasok | `erik_supplier_*` | 7 | Pemasok/pembelian/inspeksi kualitas |
| Risiko dan kepatuhan | `erik_risk_*` | 6 | Aturan risiko/GDPR/Cookie/privasi |
| Multi-platform | `erik_platform_*` | 8 | Multi-toko/akun platform/listing/penjual |
| Konten dan pengalaman | `erik_*` | 12 | CMS/Feed/ukuran/notifikasi/email/pencarian/log operasi |
| Langganan/poin, dll. | `erik_*` | 7 | Langganan/poin/kartu hadiah/B2B |
| Uji AB/API/pengaturan | `erik_*` | 7 | Uji AB/rate limit/dokumen API/konfigurasi sistem |

---

## Pemecahan Masalah / Troubleshooting

### MySQL melaporkan error "Specified key was too long"

```sql
-- Pastikan menggunakan utf8mb4 + InnoDB dan aktifkan innodb_large_prefix
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### Konflik Port / Port Conflict

Ubah `APP_PORT` di `admin/.env` atau `service/.env`.

### Koneksi Redis gagal

Periksa ekstensi Redis sudah terinstal dan layanan Redis sudah berjalan:
```bash
redis-cli ping  # Harus mengembalikan PONG
```

### Konflik ID Snowflake

Jika beberapa server diinstansiasi secara bersamaan, pastikan `SNOWFLAKE_WORKER_ID` setiap server berbeda (0-31).

---

## Referensi Cepat Perintah Pengembangan / Development Commands

```bash
# service/ (API)
php start.php start          # Mulai
php start.php start -d       # Proses daemon
php start.php reload         # Muat ulang panas
php start.php stop           # Berhenti
php start.php status         # Status

# admin/ (Panel admin)
php start.php start
php start.php start -d
php start.php reload
```
