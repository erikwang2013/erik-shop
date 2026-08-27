# Platform E-Commerce Lintas Batas — Dokumentasi Antarmuka API

> Dokumentasi dinamis: setelah Service dimulai, akses http://localhost:8787/apidoc/ (dihasilkan otomatis oleh hg/apidoc)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Spesifikasi Umum

### Format Permintaan

| Item | Keterangan |
|------|------|
| Base URL | `http://localhost:8787/api` |
| Kontrol versi | Header `API-Version: 2026-05-20` (tidak di URL) |
| Autentikasi | Header `Authorization: Bearer <token>` |
| Bahasa | Header `Accept-Language: zh_CN|zh_HK|en|ja|ko` |
| Platform | Header `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` |
| Content-Type | `application/json` (POST/PUT) |
| Verifikasi manusia | Header `X-Poster-Token: <token>` (operasi sensitif) |

### Format Respons

```json
// Sukses
{"code": 0, "msg": "ok", "data": {}}

// Gagal
{"code": 1, "msg": "Pesan kesalahan", "data": null}

// Paginasi
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// Kode kesalahan
// 40001 Serangan XSS  40002 Injeksi SQL  40003 Injeksi CRLF  40004 Path traversal
// 40005 Body terlalu besar  40006 Kesalahan Content-Type  40008 Serangan brute-force
// 40009 Pelanggaran upload file  40010 Injeksi XXE  40011 Serangan SSRF
// 40012 Metode HTTP salah  40013 Kesalahan header Host
// 401 Belum login  403 Akses ditolak  422 Validasi parameter gagal  429 Terlalu banyak permintaan  503 Layanan untuk sementara tidak tersedia (circuit breaker/degradasi)
```

### Keterangan ID

Semua field ID di seluruh antarmuka merupakan string ter-encode hashids (mis. `Ab3xK9pq`), di-encode/decode otomatis oleh middleware. Frontend tidak perlu menangani secara manual.

---

## 1. Antarmuka Autentikasi

### 1.1 Registrasi `POST /api/auth/register`

> Memerlukan verifikasi manusia `X-Poster-Token`

**Permintaan:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**Respons:**
```json
{
  "code": 0, "msg": "Registrasi berhasil",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 Login `POST /api/auth/login`

**Permintaan:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Respons:**
```json
{
  "code": 0, "msg": "Login berhasil",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "level": 1,
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.3 Perbarui Token `POST /api/auth/refresh`

**Permintaan:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**Respons:**
```json
{
  "code": 0, "msg": "Token diperbarui",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 Login Sosial `POST /api/auth/social`

**Permintaan:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**Respons:**
```json
{
  "code": 0, "msg": "Login berhasil",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. Antarmuka Produk

### 2.1 Daftar Produk `GET /api/products`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| page | int | Tidak | Nomor halaman (default 1) |
| per_page | int | Tidak | Jumlah per halaman (default 20, maks 100) |
| category_id | string | Tidak | ID kategori (hashid, termasuk subkategori) |
| keyword | string | Tidak | Kata kunci pencarian |
| sort | string | Tidak | Urutan: default/price_asc/price_desc/sales/newest |
| min_price | number | Tidak | Harga minimum |
| max_price | number | Tidak | Harga maksimum |

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Ab3xK9pq",
        "title": "Product Title",
        "subtitle": "Subtitle",
        "main_image": "https://img.example.com/p1.jpg",
        "brand": "BrandName",
        "min_price": 29.99,
        "max_price": 49.99,
        "status": 2,
        "is_hot": true,
        "is_new": false,
        "sales_count": 1000
      }
    ],
    "total": 100, "page": 1, "per_page": 20
  }
}
```

### 2.2 Detail Produk `GET /api/products/{id}`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| currency | string | Tidak | Kode mata uang (default USD) |
| dest_country | string | Tidak | ISO2 negara tujuan (default US) |

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (pencocokan multibahasa)",
    "subtitle": "Subtitle",
    "description": "Full description...",
    "brand": "BrandName",
    "main_image": "https://img.example.com/p1.jpg",
    "min_price": 29.99,
    "max_price": 49.99,
    "weight": 500,
    "unit": "piece",
    "status": 2,
    "is_hot": true,
    "is_new": false,
    "sales_count": 1000,
    "view_count": 5000,
    "skus": [
      {
        "id": "Cd4yL8rq",
        "sku_code": "SKU-RED-M",
        "attrs": {"color": "Red", "size": "M"},
        "default_price": 29.99,
        "stock": 100,
        "image": "https://img.example.com/sku1.jpg",
        "display_price": {
          "tax_exclusive": 29.99,
          "tax_inclusive": 35.99,
          "vat_amount": 6.00,
          "vat_rate": 20,
          "currency": "USD",
          "display_mode": "tax_exclusive"
        }
      }
    ],
    "images": [
      {"id": "Ef5zM9ns", "url": "https://img.example.com/p1.jpg", "is_main": true}
    ],
    "compliance_info": [
      {"category": "Tanda CE", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 Ulasan Produk `GET /api/reviews/{productId}`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| page | int | Tidak | Nomor halaman |
| per_page | int | Tidak | Per halaman (default 10) |
| rating | int | Tidak | Filter rating (1-5) |

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Re1v2W3x",
        "user_id": "Ab3xK9pq",
        "product_id": "Ab3xK9pq",
        "rating": 5,
        "content": "Great product!",
        "images": ["https://img.example.com/review1.jpg"],
        "is_anonymous": false,
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 50, "page": 1, "per_page": 10
  }
}
```

---

## 3. Antarmuka Kategori

### 3.1 Daftar Kategori `GET /api/categories`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| parent_id | int | Tidak | ID kategori induk (0=level teratas) |

### 3.2 Pohon Kategori `GET /api/categories/tree`

Mengembalikan pohon kategori berjenjang lengkap.

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ct1g2H3i",
      "parent_id": 0,
      "name": "Clothing",
      "slug": "clothing",
      "icon": "icon-url",
      "level": 1,
      "is_hot": true,
      "children": [
        {
          "id": "Ct4j5K6l",
          "parent_id": "Ct1g2H3i",
          "name": "Dresses", "slug": "dresses",
          "level": 2, "is_hot": false,
          "children": []
        }
      ]
    }
  ]
}
```

---

## 4. Antarmuka Keranjang `[JWT]`

### 4.1 Daftar Keranjang `GET /api/cart`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| currency | string | Tidak | Mata uang (default USD) |

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ca1r2T3s",
      "sku_id": "Cd4yL8rq",
      "product_id": "Ab3xK9pq",
      "title": "Product Title",
      "image": "https://img.example.com/sku1.jpg",
      "attrs": {"color":"Red","size":"M"},
      "price": 29.99,
      "currency": "USD",
      "quantity": 2,
      "selected": true,
      "stock": 100
    }
  ]
}
```

### 4.2 Tambah ke Keranjang `POST /api/cart`

**Permintaan:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 Perbarui Jumlah `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> quantity=0 akan dihapus otomatis

### 4.4 Hapus `DELETE /api/cart/{id}`

---

## 5. Antarmuka Pesanan `[JWT]`

### 5.1 Daftar Pesanan `GET /api/orders`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| status | int | Tidak | Filter status:0 menunggu pembayaran/1 dibayar/2 dikirim/3 diterima/4 selesai/5 dibatalkan/6 dalam refund/7 sudah refund/8 menunggu persetujuan |
| page | int | Tidak | Nomor halaman (default 1) |
| per_page | int | Tidak | Per halaman (default 10) |

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "Dibayar",
        "total_amount": 59.98, "pay_amount": 59.98,
        "currency_code": "USD",
        "created_at": "2026-05-21 10:30:00",
        "paid_at": "2026-05-21 10:31:00"
      }
    ],
    "total": 10, "page": 1, "per_page": 10
  }
}
```

### 5.2 Detail Pesanan `GET /api/orders/{id}`

Mengembalikan informasi pesanan lengkap, termasuk items/logs/documents.

### 5.3 Buat Pesanan `POST /api/orders` `[PosterVerify]`

**Permintaan:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**Respons:**
```json
{
  "code": 0, "msg": "Pesanan berhasil dibuat",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 Batalkan Pesanan `POST /api/orders/{id}/cancel`

> Hanya status=0 (menunggu pembayaran) yang dapat dibatalkan

### 5.5 Faktur Komersial `GET /api/orders/{id}/documents/invoice`

Mengembalikan tautan unduhan file PDF.

### 5.6 Daftar Kemasan `GET /api/orders/{id}/documents/packing-list`

---

## 6. Antarmuka Pembayaran `[JWT]`

### 6.1 Metode Pembayaran Tersedia `GET /api/payment/methods`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| country | string | Tidak | ISO2 (default US) |
| currency | string | Tidak | Mata uang (default USD) |

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "Kartu kredit/debit",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna beli sekarang bayar nanti",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 Buat Pembayaran `POST /api/payment/create` `[PosterVerify]`

**Permintaan:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**Respons:**
```json
{
  "code": 0, "msg": "Pembayaran berhasil dibuat",
  "data": {
    "payment_id": "Pa1y2M3t",
    "order_no": "ORD20260521A1B2C3D4",
    "amount": 59.98,
    "currency": "USD",
    "gateway": "stripe",
    "method": "card",
    "client_secret": "pi_3Nxxxx_secret_xxxx",
    "txn_id": "pi_3Nxxxxxxxxxxxx"
  }
}
```

### 6.3 Status Pembayaran `GET /api/payment/status/{id}`

### 6.4 Callback Webhook `POST /webhook/payment/{gateway}`

> Tanpa JWT. Dipanggil secara asinkron oleh gateway pembayaran. Perlu verifikasi tanda tangan.

---

## 7. Antarmuka Logistik

### 7.1 Perhitungan Ongkir `GET /api/shipping/calculate`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| dest_country_id | int | Ya | ID negara tujuan (snowflake) |
| weight | int | Tidak | Berat (gram) (default 500) |

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "Zona Amerika Utara",
    "weight_kg": 0.5,
    "dest_country": "US",
    "options": [
      {
        "logistics_name": "DHL Express",
        "logistics_code": "DHL",
        "fee": 25.50,
        "estimated_days": "3-5",
        "tracking_url": "https://www.dhl.com/track?num="
      }
    ]
  }
}
```

---

## 8. Antarmuka Bea Masuk

### 8.1 Estimasi Bea Masuk `GET /api/tariff/estimate`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| product_id | string | Ya | ID produk (hashid) |
| dest_country_id | int | Ya | ID negara tujuan |
| declared_value | number | Ya | Nilai deklarasi |

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "Hanya referensi, keputusan akhir oleh bea cukai"
  }
}
```

---

## 9. Antarmuka Retur `[JWT]`

### 9.1 Daftar Retur `GET /api/returns`

### 9.2 Ajukan Retur `POST /api/returns`

**Permintaan:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 Label Retur `GET /api/returns/{id}/label`

---

## 10. Antarmuka Pengguna `[JWT]`

### 10.1 Informasi Pribadi `GET /api/user/profile`

### 10.2 Perbarui Informasi `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 Daftar Alamat `GET /api/user/addresses`

### 10.4 Tambah Alamat `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "Rumah"
}
```

### 10.5 Perbarui Alamat `PUT /api/user/addresses/{id}`

### 10.6 Hapus Alamat `DELETE /api/user/addresses/{id}`

### 10.7 Bahasa dan Mata Uang `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. Antarmuka Pemasaran

### 11.1 Banner Karusel `GET /api/banners?position=home`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| position | string | Tidak | Posisi: home/category/product |

### 11.2 Kupon Tersedia `GET /api/coupons` `[JWT]`

### 11.3 Klaim Kupon `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 Daftar Flash Sale `GET /api/flash-sales`

### 11.5 Daftar Beli Grup `GET /api/group-buys`

### 11.6 Tautan Afiliasi `GET /api/affiliate/links` `[JWT]`

### 11.7 Komisi Afiliasi `GET /api/affiliate/commissions` `[JWT]`

---

## 12. Antarmuka Keanggotaan `[JWT]`

### 12.1 Informasi Keanggotaan `GET /api/membership`

**Respons:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "current_level": {"id": "Lv1", "name": "Gold", "level": 2},
    "current_benefits": [{"benefit_type": "discount", "benefit_value": "5%"}],
    "all_levels": [],
    "current_score": 1500
  }
}
```

### 12.2 Riwayat Poin `GET /api/points`

---

## 13. Antarmuka Lainnya

### 13.1 Data Negara `GET /api/countries`

Mengembalikan semua negara/mata uang/nilai tukar/nilai default yang tersedia.

### 13.2 Konfigurasi Publik `GET /api/settings?group=general`

### 13.3 Pencarian ES `GET /api/search?keyword=xxx`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| keyword | string | Ya | Kata kunci pencarian |
| category_id | string | Tidak | Filter kategori |
| page | int | Tidak | Nomor halaman |

### 13.4 Perbandingan Produk `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE perlu membawa id catatan perbandingan: `DELETE /api/comparisons/{id}` (`{id}` adalah id catatan perbandingan, wajib)

### 13.5 Rekomendasi Personal `GET /api/recommendations` `[JWT]`

### 13.6 Pengingat Penurunan Harga `GET/POST /api/price-alerts` `[JWT]`

### 13.7 Favorit `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 Notifikasi `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 Halaman CMS `GET /api/cms/{slug}`

### 13.11 Tabel Konversi Ukuran `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 Pemeriksaan Kepatuhan `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 Deteksi GeoIP `GET /api/geoip/detect`

### 13.14 Kirim Ulasan `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 Saldo Kartu Hadiah `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 Tukar Kartu Hadiah `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 Permintaan GDPR `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 Ekspor Pesanan `GET /api/export/orders` `[JWT]`

| Parameter | Tipe | Wajib | Keterangan |
|------|------|------|------|
| date_from | string | Tidak | Tanggal mulai (YYYY-MM-DD) |
| date_to | string | Tidak | Tanggal akhir |

Mengembalikan unduhan file CSV.

### 13.19 Permintaan Penawaran B2B `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 Health Check `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## Lampiran: Tabel Kode Status

### Status Pesanan

| Nilai | Keterangan |
|----|------|
| 0 | Menunggu pembayaran |
| 1 | Dibayar |
| 2 | Dikirim |
| 3 | Diterima |
| 4 | Selesai |
| 5 | Dibatalkan |
| 6 | Dalam refund |
| 7 | Dana sudah dikembalikan |
| 8 | Menunggu persetujuan (risiko) |

### Status Produk

| Nilai | Keterangan |
|----|------|
| 0 | Draf |
| 1 | Menunggu persetujuan |
| 2 | Dipublikasikan |
| 3 | Tidak aktif |

### Status Pembayaran

| Nilai | Keterangan |
|----|------|
| 0 | Menunggu pembayaran |
| 1 | Dibayar |
| 2 | Dana dikembalikan |
| 3 | Gagal |

### Mode Tampilan Harga Negara

| Nilai | Keterangan |
|----|------|
| tax_inclusive | Harga termasuk pajak (EU/UK) |
| tax_exclusive | Harga tidak termasuk pajak (US/CA) |
| both | Tampilan ganda (JP) |

---

## Lampiran: Pipeline Middleware

```
Permintaan → Cors → Security(31 jenis) → RateLimit(token bucket) → Platform(8 platform)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → Controller
```

Tanda: `[JWT]` perlu autentikasi | `[PosterVerify]` perlu verifikasi manusia | tanpa tanda = antarmuka publik

---

## Lampiran: Ringkasan Statistik Endpoint

### A.1 Antarmuka Publik (23 endpoint)

| Metode | Path | Keterangan |
|------|------|------|
| POST | /api/auth/register | Registrasi (PosterVerify) |
| POST | /api/auth/login | Login |
| POST | /api/auth/refresh | Perbarui Token |
| POST | /api/auth/social | Login sosial |
| GET | /api/products | Daftar produk (paginasi+filter+urut) |
| GET | /api/products/{id} | Detail produk (multi-bahasa+multi-mata uang+kepatuhan+HS) |
| GET | /api/categories | Daftar kategori |
| GET | /api/categories/tree | Pohon kategori |
| GET | /api/banners | Banner karusel (per posisi+wilayah) |
| GET | /api/countries | Daftar negara/mata uang/nilai tukar |
| GET | /api/search | Pencarian multi-bahasa ES |
| GET | /api/reviews/{productId} | Daftar ulasan produk |
| GET | /api/flash-sales | Flash sale saat ini |
| GET | /api/group-buys | Beli grup saat ini |
| GET | /api/faq | FAQ (per bahasa+kategori) |
| GET | /api/cms/{slug} | Halaman CMS |
| GET | /api/settings | Konfigurasi publik |
| GET | /api/size-charts | Tabel konversi ukuran |
| GET | /api/tariff/estimate | Estimasi bea masuk |
| GET | /api/shipping/calculate | Perhitungan ongkir |
| GET | /api/payment/methods | Metode pembayaran tersedia |
| GET | /api/geoip/detect | Deteksi GeoIP |
| GET | /api/compliance/check | Pemeriksaan kepatuhan |

### A.2 Antarmuka Terautentikasi (47 endpoint)

| Metode | Path | Keterangan |
|------|------|------|
| GET/PUT | /api/user/profile | Informasi pribadi |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | CRUD alamat |
| PUT | /api/user/locale | Perbarui bahasa/mata uang |
| GET/POST | /api/wishlist[/{id}] | Favorit |
| GET/POST | /api/price-alerts | Pengingat penurunan harga |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | Keranjang |
| GET/POST | /api/orders | Daftar/buat pesanan (PosterVerify) |
| GET | /api/orders/{id} | Detail pesanan |
| POST | /api/orders/{id}/cancel | Batalkan pesanan |
| GET | /api/orders/{id}/documents/invoice | Faktur komersial |
| GET | /api/orders/{id}/documents/packing-list | Daftar kemasan |
| POST | /api/payment/create | Buat pembayaran (PosterVerify) |
| GET | /api/payment/status/{id} | Status pembayaran |
| GET/POST | /api/returns[/{id}] | Retur |
| GET | /api/returns/{id}/label | Label retur |
| POST | /api/reviews | Kirim ulasan |
| GET/POST | /api/coupons[/{id}/claim] | Kupon |
| GET/PUT | /api/notifications[/{id}/read] | Notifikasi |
| GET/POST/DELETE | /api/comparisons[/{id}] | Perbandingan produk |
| GET | /api/recommendations | Rekomendasi personal |
| GET | /api/affiliate/links | Tautan afiliasi |
| GET | /api/affiliate/commissions | Komisi afiliasi |
| GET | /api/membership | Level keanggotaan |
| GET | /api/points | Riwayat poin |
| GET/POST | /api/gift-cards | Kartu hadiah |
| GET/POST | /api/b2b/quotes | Permintaan penawaran B2B |
| GET/POST | /api/privacy/request | Permintaan GDPR |
| GET | /api/export/orders | Ekspor pesanan |

### A.3 Webhook (1 endpoint)

| Metode | Path | Keterangan |
|------|------|------|
| POST | /webhook/payment/{gateway} | Notifikasi asinkron pembayaran (verifikasi tanda tangan) |

### A.4 Admin dan Health Check (2 endpoint)

| Metode | Path | Keterangan |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | Eksekusi refund backend |
| GET | /health | Health check |

---

## Lampiran: Spesifikasi Desain API

### Kontrol Versi

Versi diteruskan melalui header `API-Version: 2026-05-20`, tidak di URL. Dipetakan oleh middleware VersionRoute.

### Pipeline Middleware

```
Cors → Security(31 jenis) → RateLimit(sliding window) → Platform(8 platform) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### Statistik Endpoint

- Antarmuka publik: 23 (autentikasi/produk/kategori/konten/pencarian/layanan)
- Antarmuka terautentikasi: 47 (pengguna/keranjang/pesanan/pembayaran/retur/ulasan/pemasaran)
- Webhook: 1 (callback pembayaran)
- Admin: 1 (eksekusi refund)
- Health: 1 (/health health check)

### Respons Terpadu

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### Dokumentasi Dinamis hg/apidoc

Dihasilkan otomatis dari anotasi controller menggunakan hg/apidoc. Akses `/apidoc/` setelah memulai.

Contoh anotasi:
```php
/**
 * @Apidoc\Title("Login Pengguna")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
