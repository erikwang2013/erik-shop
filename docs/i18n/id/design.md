# Platform E-Commerce Lintas Batas — Dokumen Desain

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Desain Database

### 1.1 Konvensi Penamaan

- Prefiks tabel: `erik_`
- Primary key: `id BIGINT UNSIGNED NOT NULL` (dibuat oleh snowflake, bukan auto-increment)
- Timestamp: `created_at`, `updated_at`, `deleted_at` (soft delete)
- Engine: InnoDB, charset: utf8mb4_unicode_ci

### 1.2 Pembagian Modul (110 tabel)

| Modul | Jumlah Tabel | Tabel Inti |
|------|------|--------|
| Pengguna dan akun | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| Produk dan kategori | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| Transaksi | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| Pembayaran dan dana | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| Logistik | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| Bea cukai dan pajak | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| Pemasaran | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| Rantai pasok | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| Risiko dan kepatuhan | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| Multi-platform | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| Konten dan pengalaman | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| Langganan dan B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| Layanan pelanggan | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| Pengujian AB | 3 | ab_tests, ab_test_variants, ab_test_results |
| Tata kelola API | 2 | api_rate_limits, api_docs |
| Data dasar | 3 | countries, currencies, exchange_rates |

### 1.3 Field Pelacakan Platform

| Tabel | Field | Penjelasan |
|----|------|------|
| orders | platform VARCHAR(16) | Platform saat order |
| payments | platform VARCHAR(16) | Platform pembayaran |
| operation_logs | platform VARCHAR(16) | Platform operasi |
| users | last_login_platform VARCHAR(16) | Platform login terakhir |
| search_logs | platform VARCHAR(16) | Platform pencarian |
| chat_messages | platform VARCHAR(16) | Sumber pesan |

---

## 2. Desain API

Versioning API, pipa middleware, statistik endpoint, dan spesifikasi respons seragam, lihat [Dokumen API](api.md).

---

## 3. Desain Keamanan

### 3.1 SecurityMiddleware membungkus 31 detektor security-php

| # | Tipe | Kode Error | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | Injeksi SQL | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | Path traversal | 40004 | ✅ | ✅ |
| 5 | Body terlalu besar | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | Unggah file | 40009 | ✅ | ✅ |
| 8 | Header respons keamanan | — | ✅ | ✅ |
| 9 | Brute force | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | Metode HTTP | 40012 | ✅ | ✅ |
| 13 | Header Host | 40013 | ✅ | — |
| 14 | Penghilangan data sensitif | — | ✅ | ✅ |
| 15 | Daftar putih CORS | — | ⚠️ | ⚠️ |

### 3.2 Enkripsi Tiga Lapis

| Lapisan | Teknologi | Paket |
|------|------|-----|
| Lapisan transportasi | AES-256-CBC | erikwang2013/encryption |
| Lapisan database | Trait Encryptable | erikwang2013/encryptable (Maize) |
| Obfuscation ID | Hashids | erikwang2013/hashids |

---

## 4. Desain Konkurensi Tinggi

### 4.1 Rate Limiting

Jendela geser token bucket (Redis ZSET, melalui facade `support\Redis`): default 60s/100 kali, login 10 kali/60s, registrasi 5 kali/300s, login sosial 5 kali/300s, pembayaran 5 kali/60s, order 3 kali/10s, pencarian 10 kali/1s

### 4.2 Circuit Breaker dan Degradasi

Circuit breaker Redis (`app\common\CircuitBreaker`): panggilan API eksternal seperti gateway pembayaran/login sosial semuanya melalui `CircuitBreaker::call()` — 5 kegagalan beruntun membuka sirkuit selama 30s, setelah TTL habis permintaan berikutnya otomatis melakukan probe half-open, berhasil maka langsung reset. Daftar putih pengecualian bisnis (kartu tidak valid/token tidak valid) tidak dihitung sebagai kegagalan, mencegah penyerang menjatuhkan layanan dependen dengan permintaan tidak valid; saat Redis tidak tersedia otomatis degradasi dan mengizinkan (fail-open). Selama sirkuit terbuka, antarmuka mengembalikan 503「Layanan untuk sementara tidak tersedia」.

### 4.3 Penggunaan Redis

Redis digunakan untuk rate limiting token bucket (facade `support\Redis`), kode verifikasi manusia, dan penyimpanan Session; data bisnis tidak melakukan cache lapisan aplikasi, langsung membaca MySQL (pemisahan baca/tulis + kumpulan koneksi).

### 4.4 Kumpulan Koneksi

MySQL: 50max/10min/2s timeout | Pemisahan baca/tulis: 30max/5min (2 replika baca, sticky=true) | Redis: 30max/5min



---

## 5. Internasionalisasi

- Antarmuka: zh_CN, zh_HK, en, ja, ko
- Konten: erik_product_translations baris independen per locale
- Harga: erik_product_sku_prices penetapan harga independen per mata uang
- Header: Accept-Language + API-Version

## 6. Dokumentasi API

Menggunakan hg/apidoc untuk membuat otomatis berdasarkan anotasi controller, lihat [Dokumen API](api.md). Akses `/apidoc/` setelah startup.

## 7. Pengujian

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

Lihat: [Dokumen Desain Fitur](features.md) | [Dokumen Arsitektur Lengkap](architecture-full.md) | [Dokumen Deployment](deployment.md)
