# Erik Shop — Platform E-Commerce Lintas Batas
Platform e-commerce lintas batas full-stack yang dibangun di atas ekosistem webman, mencakup skenario B2C/B2B dan onboarding penjual pihak ketiga.

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Ringkasan Versi

| | Versi Ringkas (Lite) | Versi Standar (Standard) | Versi Lengkap (Full) |
|---|:---:|:---:|:---:|
| **Posisi** | Pengembang perorangan / e-commerce kecil | Merchant lintas batas yang berkembang | Platform full-stack tingkat perusahaan |
| **Lisensi** | Open source MIT | Lisensi komersial | Lisensi komersial |
| **Cara mendapat** | Unduhan publik GitHub | Hubungi erik@erik.xyz | Hubungi erik@erik.xyz |
| **Branch** | `lite` | `standard` | `full` |
| **Saat ini** | — | — | ✅ |

---

## Catatan Perbaikan 2026-08-07

| # | Masalah | Tingkat Keparahan | Perbaikan |
|---|------|--------|------|
| 1 | Enkripsi respons API tidak terhubung ke middleware | Medium | Membuat EncryptionMiddleware (didorong header X-Encrypt-Response), didaftarkan sebagai tingkat ke-10 pipeline service |
| 2 | Nama kelas Encryption / nama file EncryptionHelper.php tidak cocok | Medium | Diubah namanya menjadi Encryption.php, memperbaiki autoloading PSR-4 |
| 3 | JWT_SECRET_KEY kosong | Low | Membuat kunci 32 byte, sekaligus menyetel JWT_SECRET dan JWT_SECRET_KEY |
| 4 | config/middleware.php adalah array index menyebabkan "Bad middleware config" semua worker crash | Critical | Diubah menjadi struktur standar `'' => [...]` (webman mengharuskan appName => daftar) |
| 5 | Konfigurasi plugin security-php kekurangan key enable, di-skip diam-diam oleh Config::loadFromDir | Critical | Menambahkan `'enable' => true` ke app.php plugin service/admin |
| 6 | config/bootstrap.php mereferensikan support\bootstrap\Db/Redis yang tidak ada | Critical | Dihapus; inisialisasi Eloquent diubah agar support/bootstrap.php me-require Db.php dari vendor/webman/database |
| 7 | Fungsi global redis() tidak ada (webman 2.x tidak memiliki fungsi ini), rate limiting/risiko gagal diam-diam | High | Membuat facade support\Redis (illuminate/redis + phpredis), mendaftarkan fungsi bantu redis() di app/functions.php |
| 8 | Parameter konstruktor RedisManager kurang (butuh 3 parameter: kontainer app/driver/config) | High | Meneruskan placeholder kontainer stdClass + driver phpredis + konfigurasi koneksi |
| 9 | Model mereferensikan trait Erik\Encryptable\Encryptable yang tidak ada (di dalam paket adalah CastsAttributes dengan namespace Maize\Encryptable) | Critical | Membuat lapisan kompatibilitas trait klasik service/Erik/Encryptable/Encryptable.php (di bawahnya memakai paket Encryption::php) |
| 10 | Deklarasi fungsi top-level Installer.php plugin composer duplikat fatal | Medium | Penjaga idempoten function_exists (vendor service/admin keduanya telah diperbaiki) |
| 11 | getHeader() HashidsEncode mengembalikan string menyebabkan error implode | High | Konversi paksa (array) |
| 12 | docker-compose/.env.example meng-hardcode kunci JWT/enkripsi asli | Critical | Diganti placeholder change_me, wizard instalasi membuat kunci acak |
| 13 | Pembuatan pesanan tanpa transaksi, pengurangan stok tidak atomik (oversell konkuren) | Critical | Db::transaction + decrement kondisional atomik |
| 14 | Klaim kupon overshoot/klaim berlebih konkuren | High | Transaksi + kunci baris lockForUpdate + gerbang atomik received_qty |
| 15 | Field verifikasi tanda tangan PayPal Webhook selalu kosong (verify-webhook-signature pasti gagal) | High | Lima field verifikasi tanda tangan diteruskan dari header permintaan |
| 16 | Injeksi SQL wizard instalasi (penggabungan nama database/password) | High | quote + escape backtick + var_export untuk menulis konfigurasi |
| 17 | Degradasi diam-diam saat kunci enkripsi/hash hilang | High | Melempar pengecualian jika Encryption/HashidsHelper nilainya kosong atau panjangnya tidak valid |
| 18 | Nama file tetap ekspor pesanan tumpang tindih saat konkuren | Medium | Nama file uniqid + pembersihan shutdown + try/catch |
| 19 | Dekode Hashids tidak ditulis kembali ke parameter permintaan (parameter rute/GET/POST) | High | setParams/setGet/setPost ditulis kembali |
| 20 | composer.lock di-gitignore (build tidak dapat direproduksi) | Medium | Hapus ignore, masukkan ke kontrol versi |
| 21 | Kontainer tanpa health check, tanpa dependensi startup | Medium | healthcheck semua layanan + depends_on condition |
| 22 | Dockerfile admin tidak dapat dijalankan | High | Menambahkan COPY + composer install + EXPOSE + CMD |
| 23 | Error kompilasi Flutter (konflik intl/generik konstruktor/kurung berlebih) + pengujian pending Timer | High | intl ^0.20.2, static factory, pump memajukan clock |
| 24 | 27 error kompilasi ArkTS HarmonyOS tidak dapat dipaketkan | High | Antarmuka eksplisit, ganti nama kata cadangan, build satu root, impor @kit, konfigurasi hvigor |

---

## Perbandingan Fitur

> Catatan: ◐ = struktur tabel sudah dibuat, bisnis menunggu implementasi (saat ini hanya tabel data dan model, tanpa kode API/bisnis atau hanya implementasi sebagian)

### Sistem Pengguna

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Registrasi/login email (JWT) | ✅ | ✅ | ✅ |
| Login sosial (Google/Apple/Facebook) | — | ✅ | ✅ |
| Manajemen alamat | ✅ | ✅ | ✅ |
| Level anggota + poin | — | — | ◐ |
| Kartu hadiah | — | — | ✅ |
| Verifikasi identitas KYC | — | — | ✅ |

### Sistem Produk

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Manajemen kategori (pohon) | ✅ | ✅ | ✅ |
| SKU + atribut | ✅ | ✅ | ✅ |
| Gambar produk | ✅ | ✅ | ✅ |
| Konten multibahasa | — | ✅ | ✅ |
| Penetapan harga independen multi-mata uang | — | ✅ | ✅ |
| Ulasan produk | ✅ | ✅ | ✅ |
| Label kepatuhan (FDA/CE/RoHS) | — | ✅ | ✅ |
| Pencarian multibahasa ES | — | ✅ | ✅ |
| Sinkronisasi Feed produk (Google/Meta) | — | — | ✅ |
| Tabel konversi ukuran | — | — | ✅ |

### Sistem Transaksi

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Keranjang belanja | ✅ | ✅ | ✅ |
| Manajemen pesanan | ✅ | ✅ | ✅ |
| Pembayaran (Stripe) | ✅ | ✅ | ✅ |
| Pembayaran (PayPal) | ✅ | ✅ | ✅ |
| Pembayaran (Klarna/Adyen) | — | placeholder | placeholder |
| BNPL beli-sekarang-bayar-nanti | — | placeholder | placeholder |
| Refund | ✅ | ✅ | ✅ |
| Manajemen retur | — | ✅ | ✅ |
| Faktur komersial/daftar pengepakan | — | ✅ | ✅ |
| Asuransi logistik | — | — | ◐ |

### Logistik Lintas Batas

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Manajemen perusahaan logistik internasional | — | ✅ | ✅ |
| Zona logistik + tarif bertingkat | — | ✅ | ✅ |
| Gudang luar negeri (kirim+retur) | — | ✅ | ✅ |
| Deklarasi HS | — | Dalam perencanaan | Dalam perencanaan |
| Pelacakan lintasan logistik | — | ✅ | ✅ |
| Manajemen stok multi-gudang | — | — | ✅ |

### Bea Cukai & Pajak

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Pustaka kode HS Code | — | ✅ | ✅ |
| Konfigurasi aturan bea masuk | — | ✅ | ✅ |
| Pengaturan VAT/IOSS | — | ✅ | ✅ |
| Pembatasan kepatuhan berbagai negara | — | ✅ | ✅ |
| Kepatuhan tampilan harga (termasuk/tidak termasuk pajak) | — | ✅ | ✅ |

### Alat Pemasaran

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Kupon | ✅ | ✅ | ✅ |
| Banner karusel | ✅ | ✅ | ✅ |
| Flash sale | — | ✅ | ✅ |
| Beli grup | — | ✅ | ✅ |
| Afiliasi (tautan+komisi+penarikan) | — | ✅ | ✅ |
| Promosi per wilayah | — | ✅ | ✅ |

### Rantai Pasok

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Manajemen pemasok | — | — | ✅ |
| Purchase order | — | — | ◐ |
| Pemeriksaan kualitas (pintu masuk/keluar gudang) | — | — | ◐ |
| Riwayat stok (buku besar tidak dapat diubah) | — | — | ✅ |
| Transfer stok | — | — | ◐ |

### Ekstensi Platform

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Manajemen multi-toko | — | — | ✅ |
| Onboarding multi-penjual (penjual pihak ketiga) | — | — | ✅ |
| Listing Amazon/eBay/Shopee | — | — | ✅ |
| Agregasi pesanan multi-platform | — | — | ✅ |
| Grosir B2B (penetapan harga bertingkat/permintaan penawaran) | — | — | ✅ |

### Risiko & Kepatuhan

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Deteksi serangan dasar (XSS/SQLi) | ✅ | ✅ | ✅ |
| Deteksi serangan lanjutan (XXE/SSRF, dll.) | — | — | ✅ |
| Verifikasi manusia PosterVerify | — | ✅ | ✅ |
| Mesin aturan risiko | — | — | ✅ |
| Permintaan data GDPR/CCPA | — | — | ✅ |
| Manajemen Cookie Consent | — | — | ✅ |
| Pelacakan sumber platform | — | ✅ | ✅ |
| Pelacakan sumber platform (8 platform) | — | ✅ | ✅ |

### Konkurensi Tinggi

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| Kumpulan koneksi DB | ✅ | ✅ | ✅ |
| Rate limiting token bucket | — | — | ✅ |
| Pemisahan baca/tulis DB | — | — | ✅ |
| Tugas terjadwal Cron (11) | — | — | ✅ |

### Konten & Pertumbuhan

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Notifikasi sistem | ✅ | ✅ | ✅ |
| Template email | — | — | ✅ |
| Halaman CMS multibahasa | — | — | ✅ |
| FAQ + basis pengetahuan | — | — | ◐ |
| Pembelian berlangganan | — | — | ✅ |
| Pengujian AB | — | — | ◐ |
| Layanan pelanggan real-time (WebSocket IM) | — | — | ✅ |

### Klien

| Fitur | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| Internasionalisasi (terjemahan 5 bahasa) | ✅ | ✅ | ✅ |
| Dokumentasi API (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Dasbor Admin ECharts | ✅ | ✅ | ✅ |
| Ekspor Admin Excel/PDF | ✅ | ✅ | ✅ |
| Antarmuka multibahasa (5 bahasa) | ✅ | ✅ | ✅ |

---

## Perbandingan Desain

### Database

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Tabel data | **23** | **62** | **110** |
| Terkait pengguna | 3 | 5 | 7 |
| Terkait produk | 6 | 15 | 19 |
| Terkait transaksi | 6 | 9 | 9 |
| Terkait logistik | 0 | 7 | 9 |
| Terkait bea cukai | 0 | 5 | 5 |
| Terkait pemasaran | 4 | 8 | 8 |
| Rantai pasok | 0 | 0 | 5 |
| Risiko & kepatuhan | 0 | 0 | 5 |
| Multi-platform | 0 | 0 | 9 |
| Konten & pertumbuhan | 0 | 1 | 14 |
| Layanan pelanggan/AB/API | 0 | 0 | 5 |

### Pipa Middleware

```
Lite:      Cors → Security(4 jenis) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security(4 jenis) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security(31 jenis) → RateLimit(token bucket) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption(enkripsi antarmuka)
```

### Skala Kode

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Model Service | 26 | 55 | 111 |
| Controller Service | 15 | 24 | 39 |
| Middleware Service | 7 | 9+2 | 12+2 |
| Kelas utilitas Service | 5 | 5 | 15 |
| Model Admin | 15 | 34 | 76 |
| Controller Admin | 15 | 27 | 82 |
| Halaman Flutter | 11 | 11 | 11 |
| HarmonyOS | — | — | 9 halaman |
| Pengujian PHPUnit | 22 | 22 | 54 |

### Tumpukan Teknologi

| Komponen | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| snowflake-php | ✅ | ✅ | ✅ |
| hashids | ✅ | ✅ | ✅ |
| jwt-webman | ✅ | ✅ | ✅ |
| encryption | ✅ | ✅ | ✅ |
| encryptable | ✅ | ✅ | ✅ |
| season | — | ✅ | ✅ |
| poster-php | — | ✅ | ✅ |
| webman-scout | — | ✅ | ✅ |
| phpspreadsheet | ✅ | ✅ | ✅ |
| dompdf | ✅ | ✅ | ✅ |
| stripe/stripe-php | — | ✅ | ✅ |
| maxmind/GeoIP2 | — | ✅ | ✅ |
| guzzlehttp/guzzle | — | ✅ | ✅ |

---

## Jalur Upgrade

```
Lite (open source) ──→ Standard (komersial) ──→ Full (komersial)

Cara upgrade:
  1. Hubungi erik@erik.xyz untuk mendapatkan kode versi terkait
  2. Impor schema inkremental (lite→standard menambah ~40 tabel, standard→Full menambah ~48 tabel)
  3. Salin controller/model/middleware versi terkait
  4. composer require paket dependensi baru
```

---

## Cara Mendapatkan

| Versi | Cara |
|------|------|
| **Versi Ringkas (Lite)** | Open source GitHub [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php) branch `lite` |
| **Versi Standar (Standard)** | Lisensi komersial — hubungi **erik@erik.xyz** |
| **Versi Lengkap (Full)** | Lisensi komersial — hubungi **erik@erik.xyz** |

Lisensi komersial mencakup: kode sumber lengkap / dukungan deployment / pembaruan prioritas / konsultasi teknis
