# Platform E-Commerce Lintas Batas — Koleksi Diagram Arsitektur

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Diagram Arsitektur Sistem

![1. Diagram Arsitektur Sistem](diagrams/01-system-architecture.svg)

---

## 2. Diagram Alir Pemrosesan Permintaan (Pipa Middleware)

![2. Diagram Alir Pemrosesan Permintaan](diagrams/02-request-processing-flow.svg)

---

## 3. Peta Panorama Modul Fitur

![3. Peta Panorama Modul Fitur](diagrams/03-feature-module-map.svg)

---

## 4. Diagram Siklus Hidup Permintaan

![4. Diagram Siklus Hidup Permintaan](diagrams/04-request-lifecycle.svg)

---

## 5. Diagram Siklus Hidup Pesanan

![5. Diagram Siklus Hidup Pesanan](diagrams/05-order-lifecycle.svg)

---

## 6. Diagram Arsitektur Deployment

![6. Diagram Arsitektur Deployment](diagrams/06-deployment-architecture.svg)

---

## 7. Diagram Arsitektur Keamanan

![7. Diagram Arsitektur Keamanan](diagrams/07-security-architecture.svg)

### Ringkasan Perlindungan Keamanan

| Lapisan | Garis Pertahanan | Teknologi/Paket | Cakupan |
|------|------|---------|---------|
| Lapisan pertama | Batas jaringan | Nginx SSL + reverse proxy + validasi Host | Service + Admin |
| Lapisan kedua | Deteksi serangan WAF | `erikwang2013/security-php` 31 detektor | XSS/SQLi/CRLF/Path traversal/XXE/SSRF/Unggah file/Metode/Host/Content-Type/Body, dll. |
| Lapisan ketiga | Kontrol lalu lintas | RateLimitMiddleware + penghitung Redis brute force | Rate limiting token bucket (6 endpoint) + anti-brute force login/registrasi |
| Lapisan keempat | Autentikasi identitas | PosterVerify + JwtAuth HS256 | Verifikasi manusia (slider/teka-teki/klik) + Bearer Token + refresh token ganda |
| Lapisan kelima | Keamanan data | Hashids + AES-256-CBC + Encryptable | Enkripsi tiga lapis: obfuscation ID/enkripsi transportasi/enkripsi field database |
| Lapisan keenam | Keamanan respons | Header keamanan HTTP + penghilangan data sensitif | nosniff/DENY/XSS-Protection/Referrer-Policy/penghilangan log |
| Berkelanjutan | Audit pelacakan | PlatformMiddleware + OperationLogs | Pelacakan sumber 8 platform + pencatatan 6 tabel + log operasi |

---

## 8. Diagram Alir Settlement Multi-Mata Uang

![8. Diagram Alir Settlement Multi-Mata Uang](diagrams/08-multi-currency-settlement.svg)

### Penjelasan Settlement Multi-Mata Uang

**Penetapan harga multi-mata uang**: SKU produk diberi harga per mata uang berdasarkan `currency_code`; saat pemesanan, mata uang pembayaran dikunci (USD / EUR / GBP / CNY, dll.).

**Layanan nilai tukar**: Tabel nilai tukar `erik_exchange_rates` mendukung pemeliharaan manual dan penarikan otomatis dari exchangerate-api, dikelola versi berdasarkan waktu efektif `effective_at`; pada settlement diambil snapshot nilai tukar pada saat pembayaran.

**Pemotongan mata uang asli**: Stripe / PayPal / Klarna / Adyen memotong pembayaran dalam mata uang asli pesanan; setelah verifikasi tanda tangan Webhook mengonfirmasi dana masuk, status pembayaran dan pesanan diperbarui.

**Pembagian dana settlement**: Setelah pembayaran berhasil, `PlatformSettlements` dibuat otomatis (total pesanan + komisi platform + biaya gateway pembayaran, dicatat dalam mata uang pesanan); settlement penjual `MerchantSettlements` (jumlah pesanan → rasio komisi → jumlah settlement), settlement pemasok `SupplierSettlements`, penarikan komisi afiliasi `AffiliatePayouts` — empat jalur settlement independen, status 0 menunggu settlement / 1 sudah di-settle.

**Laba/rugi kurs**: `CurrencyExchangeGainsLosses` melacak perbedaan antara mata uang pembayaran dan mata uang settlement, membandingkan nilai tukar saat pembayaran dengan nilai tukar saat settlement; positif = laba kurs, negatif = rugi kurs, mendukung rekonsiliasi dan audit multi-mata uang e-commerce lintas batas.

---

## Indeks Diagram

| Nomor | Nama Diagram | Tipe | Kegunaan |
|------|------|------|------|
| 1 | Diagram Arsitektur Sistem | Diagram arsitektur | Menampilkan gambaran keseluruhan sistem: klien→akses→aplikasi→data→layanan eksternal |
| 2 | Diagram Alir Pemrosesan Permintaan | Diagram alir | Menampilkan jalur lengkap permintaan HTTP melalui pipa middleware 12 tingkat (10 global + 2 rute) |
| 3 | Peta Panorama Modul Fitur | Diagram fitur | Menampilkan 17 modul fitur besar beserta titik-titik fitur rincinya |
| 4 | Diagram Siklus Hidup Permintaan | Siklus hidup | Menampilkan urutan waktu lengkap dari permintaan hingga respons serta interaksi setiap tahap |
| 5 | Diagram Siklus Hidup Pesanan | Siklus hidup | Menampilkan semua alur status pesanan dari keranjang hingga selesai/refund |
| 6 | Diagram Arsitektur Deployment | Diagram arsitektur | Menampilkan orkestrasi kontainer Docker Compose, jaringan, volume data |
| 7 | Diagram Arsitektur Keamanan | Diagram arsitektur | Menampilkan sistem pertahanan berlapis 6 tingkat: batas→WAF→lalu lintas→autentikasi→data→respons |
| 8 | Diagram Alir Settlement Multi-Mata Uang | Diagram alir | Menampilkan rantai lengkap penetapan harga per mata uang→pembayaran→pembagian dana→settlement→laba/rugi kurs |
