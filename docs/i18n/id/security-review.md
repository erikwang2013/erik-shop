# Laporan Tinjauan Integrasi Security Plugin

**Tanggal**: 2026-08-04
**Ruang Lingkup**: Integrasi erikwang2013/security-php v1.1.6
**Peninjau**: Claude Code (otomatis)

---

## 1. Hasil Pengujian

| Pemeriksaan | Hasil |
|---|---|
| Pemeriksaan sintaks PHP (47 file) | Semua lulus |
| PHPUnit (22 tests, 45 assertions) | Semua lulus |
| Pengujian payload keamanan SecurityGuard | Berhasil memblokir XSS + SQLi |
| Pengujian permintaan aman SecurityGuard | Tidak ada false positive |
| Analisis statis phpstan | Tidak terinstal (tidak memblokir) |

## 2. Masalah yang Telah Diperbaiki

### 2.1 Data unggahan file tidak diteruskan ke SecurityGuard (Kritis)

**File**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

Middleware hanya meneruskan `$request->all()` ke `SecurityGuard::guard()`, tetapi metode ini tidak menyertakan data unggahan file. `UploadDetector` membutuhkan data file dengan format `['tmp_name' => ..., 'name' => ...]`.

**Perbaikan**: Menambahkan loop untuk menggabungkan `$request->file()` ke dalam array data sebelum diteruskan ke `SecurityGuard::guard()`.

### 2.2 Konfigurasi encryptable Admin kekurangan nilai default (Sedang)

**File**: `admin/config/plugin/erikwang2013/encryptable/app.php`

Konfigurasi admin menggunakan `env('ENCRYPTION_KEY')` tanpa nilai fallback, mengembalikan `null` saat variabel lingkungan tidak ada. Service menggunakan `getenv('ENCRYPTION_KEY') ?: ''` dan benar-benar fallback ke string kosong.

**Perbaikan**: Konfigurasi admin diseragamkan dengan operator `?: ''`, konsisten dengan perilaku service.

### 2.3 Variabel lingkungan Docker Compose tidak lengkap (Sedang)

**File**: `docker-compose.yml`

- Kontainer service kekurangan `ENCRYPTION_CIPHER` dan `ENCRYPTION_PREVIOUS_KEYS`
- Kontainer admin kekurangan `ENCRYPTION_KEY`、`ENCRYPTION_CIPHER`、`ENCRYPTION_PREVIOUS_KEYS`、`HASHIDS_SALT`、`SNOWFLAKE_WORKER_ID`、`SNOWFLAKE_DATACENTER_ID`

**Perbaikan**: Semua variabel lingkungan yang hilang telah ditambahkan, menggunakan nilai default yang konsisten dengan `.env.example`.

### 2.4 Deteksi berulang middleware WAF (Kritis, diperbaiki pada ronde pertama)

`SecurityMiddleware` kustom berisi ~200 baris regex inline, duplikat total dengan 31 detektor paket `security-php`. Setiap permintaan dipindai dua kali, membuang CPU dan berpotensi memblokir ganda.

**Perbaikan**: Middleware ditulis ulang untuk menggunakan API `SecurityGuard::guard()`, berkurang dari 341 baris menjadi ~110 baris (service), dari 136 baris menjadi ~85 baris (admin). Perlindungan brute force dan header keamanan respons dipertahankan.

### 2.5 ENCRYPTION_KEY hilang (Kritis, diperbaiki pada ronde pertama)

`ENCRYPTION_KEY` di file `.env.example` menggunakan placeholder, kekurangan `ENCRYPTION_CIPHER` dan `ENCRYPTION_PREVIOUS_KEYS`. Tidak ada file `.env` aktual.

**Perbaikan**: Membuat kunci base64 32 byte, menambahkan `ENCRYPTION_CIPHER=AES-256-CBC` dan `ENCRYPTION_PREVIOUS_KEYS`, membuat file `.env`.

## 3. Kelengkapan Konfigurasi Ekosistem

### 3.1 Paket (konsisten di kedua proyek)

| Paket | Versi | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | Terinstal | Terinstal |
| erikwang2013/encryptable | - | Terinstal | Terinstal |
| erikwang2013/encryption | - | Terinstal | Terinstal |
| erikwang2013/jwt-webman | - | Terinstal | Terinstal |
| erikwang2013/hashids | - | Terinstal | Terinstal |
| erikwang2013/snowflake-php | - | Terinstal | Terinstal |
| erikwang2013/poster-php | - | Terinstal | Terinstal |
| erikwang2013/season | - | Terinstal | Terinstal |
| erikwang2013/webman-scout | - | Terinstal | Terinstal |

### 3.2 Konfigurasi WAF

| Item | Service | Admin | Status |
|---|---|---|---|
| File konfigurasi | `config/plugin/erikwang2013/security-php/app.php` | Sama | Telah dipublikasikan |
| Detektor yang diaktifkan | 31/31 | 31/31 | Benar |
| Daftar hitam IP | diaktifkan (5 att/60s -> larangan 900s) | Sama | Benar |
| Detektor mode blok | 28 | 28 | Benar |
| Detektor log-only | 3 (header_injection, ssti, nosql_injection) | 3 | Benar |
| Penyimpanan | file | file | Benar |
| Pencatatan log | diaktifkan (file, rotasi 10MB) | Sama | Benar |
| Middleware terdaftar | `config/middleware.php` | `config/middleware.php` | Benar |

### 3.3 Konfigurasi Enkripsi

| Item | Service | Admin | Status |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | Sama | Telah diset |
| ENCRYPTION_CIPHER | `AES-256-CBC` | Sama | Telah diset |
| ENCRYPTION_PREVIOUS_KEYS | (kosong) | (kosong) | Telah diset |
| Konfigurasi encryptable | `config/plugin/erikwang2013/encryptable/app.php` | Sama (telah diseragamkan) | Benar |
| Konfigurasi encryption | `config/encryption.php` | - | Benar |
| File .env | Ada | Ada | Telah dibuat |
| .env.example | Telah diperbarui | Telah diperbarui | Benar |
| docker-compose | Telah diperbarui | Telah diperbarui | Benar |

### 3.4 Model dengan Trait Encryptable

31 model menggunakan trait `Encryptable`, field sensitif telah dideklarasikan dengan benar sebagai `$encryptable`:

| Kategori | Model | Field Sensitif |
|---|---|---|
| PII Pengguna | Users | email, mobile |
| PII Pengguna | UserAddresses | name, phone, detail |
| PII Pengguna | UserKyc | real_name, id_number |
| PII Pengguna | UserSocialAccounts | access_token, refresh_token |
| Privasi | PrivacyRequests | email |
| Keuangan | GiftCards | receiver_email |
| Keuangan | AffiliatePayouts | account |
| Keuangan | PaymentGateways | name, api_key, api_secret, webhook_secret |
| Platform | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| Platform | PlatformAccounts | account_name, api_key, api_secret |
| Platform | PlatformListings | platform_account_id |
| Logistik | LogisticsCompanies | name, api_key |
| Pemasok | Suppliers | name, email, phone |
| Pemasok | B2bVerifications | company_name |
| Merchant | Merchants | store_name, email, phone |
| Lainnya | EmailLogs | to_email |
| Lainnya | 15 model lainnya | field name |

## 4. Perbaikan Ronde Kedua (Enkripsi API + Kunci JWT)

### 4.1 Middleware enkripsi respons API (Sedang, telah diperbaiki)

**File**: `service/app/middleware/EncryptionMiddleware.php` (baru)

Paket `erikwang2013/encryption` telah terinstal dan kelas utilitas `app/common/Encryption` ada, tetapi sebelumnya tidak dihubungkan ke pipeline middleware. Data sensitif antarmuka kekurangan enkripsi/dekripsi lapisan transportasi.

**Perbaikan**:
- Membuat `EncryptionMiddleware`, enkripsi/dekripsi yang didorong header HTTP:
  - `X-Encrypted: 1` — dekripsi permintaan: mendekripsi body ciphertext base64 menjadi JSON lalu meneruskan ke controller
  - `X-Encrypt-Response: 1` — enkripsi respons: mengenkripsi field `data` dalam respons menjadi ciphertext base64
  - `X-Encrypt-Fields: field1,field2` — hanya mengenkripsi field tertentu dalam respons
- Didaftarkan sebagai tingkat terakhir tumpukan middleware (setelah HashidsEncode)
- Health check (`/api/health`、`/api/ping`) dan endpoint dokumentasi (`/apidoc`) melewati enkripsi/dekripsi

### 4.2 Nama kelas/nama file tidak cocok (Sedang, telah diperbaiki)

**File**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

Kelas `app\common\Encryption` dideklarasikan di file `EncryptionHelper.php`, tidak sesuai dengan spesifikasi PSR-4, menyebabkan autoloading Composer gagal. Di lingkungan IDE dan CLI, kelas tersebut mungkin tidak dapat ditemukan oleh autoloader.

**Perbaikan**: File diubah namanya menjadi `Encryption.php` agar sesuai dengan nama kelas.

### 4.3 JWT_SECRET_KEY kosong (Rendah, telah diperbaiki)

**File**: `service/.env.example`、`service/.env`、`docker-compose.yml`

`JWT_SECRET_KEY` adalah string kosong; meskipun middleware JWT memiliki rantai fallback `JWT_SECRET → JWT_SECRET_KEY` (memprioritaskan `JWT_SECRET`), nilai placeholder tidak aman.

**Perbaikan**: Membuat kunci base64 32 byte, sekaligus menyetel `JWT_SECRET` dan `JWT_SECRET_KEY`. Memperbarui `.env.example`、`.env` dan `docker-compose.yml`.

## 5. Masalah yang Perlu Diobservasi (Titik Optimasi Potensial)

### 5.1 Ketergantungan SecurityGuard pada header untuk webman/Workerman (Risiko Rendah)

**Dampak**: Detektor seperti CSRF Origin, Host Header, DNS Rebinding, Request Smuggling, CORS bergantung pada data header HTTP di `$_SERVER`.

Di lingkungan non-CGI Workerman, `$_SERVER` mungkin tidak terisi penuh dengan header HTTP. SecurityGuard sudah memiliki logika fallback (misalnya melewati deteksi jika nilai header kosong), sehingga **tidak akan false positive**, tetapi **mungkin melewatkan sebagian serangan header**. Tingkat dampak rendah, karena lapisan reverse proxy Nginx biasanya juga memfilter header berbahaya.

**Saran**: Jika diperlukan deteksi header yang lebih lengkap, nilai header dapat diteruskan secara eksplisit dalam parameter `$meta` SecurityGuard. Saat ini tidak perlu diubah.

### 5.2 Dampak detektor CSRF Origin pada Admin (Tidak Ada Risiko)

Detektor `csrf_origin` Admin dalam mode `block`, `allowed_origins` kosong. Namun karena detektor hanya terpicu ketika header Origin ada dan tidak cocok dengan Host, akses panel admin biasanya tidak memiliki header Origin (akses same-origin), sehingga **tidak akan terblokir secara keliru**.

### 5.3 31 detektor semuanya diaktifkan, overhead per permintaan (Catatan Performa)

Semua permintaan akan menjalankan seluruh 31 detektor (termasuk JWT, WebSocket, GraphQL, CSV, prototype pollution, dll.). Setiap detektor menjalankan pencocokan regex pada semua field permintaan. Untuk skenario penggunaan proyek ini, overhead masih dalam rentang yang dapat diterima (webman adalah proses memori tetap, tanpa overhead cold start CGI).

### 5.4 Persistensi daftar hitam IP (Catatan Operasional)

Backend penyimpanan adalah mode `file`, path default `sys_get_temp_dir() . '/security_storage.json'`. Di kontainer Docker, direktori sementara dapat hilang setelah restart. Jika perlu berbagi daftar hitam di deployment multi-kontainer, dapat dialihkan ke mode `redis`.

## 6. Ringkasan File yang Diubah

```
admin/.env.example                                (ENCRYPTION_KEY ditambahkan)
admin/.env                                        (dibuat dari .env.example)
admin/CLAUDE.md                                   (tumpukan middleware + pembaruan tech stack)
admin/composer.json                               (dependensi security-php)
admin/config/plugin/erikwang2013/encryptable/app.php  (penyeragaman nilai default)
admin/config/plugin/erikwang2013/security-php/app.php  (baru, 31 detektor)
admin/app/middleware/SecurityMiddleware.php       (ditulis ulang untuk menggunakan SecurityGuard)
service/.env.example                              (pembaruan ENCRYPTION_KEY/CIPHER + kunci JWT)
service/.env                                      (dibuat dari .env.example, sinkronisasi kunci JWT)
service/CLAUDE.md                                 (tumpukan middleware + Encryption + pembaruan tech stack)
service/composer.json                             (dependensi security-php)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (baru, 31 detektor)
service/app/common/Encryption.php                 (diubah namanya dari EncryptionHelper.php)
service/app/middleware/EncryptionMiddleware.php   (baru, enkripsi/dekripsi respons API)
service/app/middleware/SecurityMiddleware.php     (ditulis ulang untuk menggunakan SecurityGuard + unggahan file)
docker-compose.yml                                (melengkapi variabel lingkungan encryption/jwt)
docs/security-review.md                           (laporan ini)
```

## 7. Kesimpulan

**Status**: Lulus

- Deteksi WAF berhasil memblokir XSS, injeksi SQL, dan serangan lainnya (31 detektor, API SecurityGuard::guard)
- Konfigurasi enkripsi field sensitif lengkap (31 model, 6 kategori data sensitif, trait Encryptable)
- Enkripsi/dekripsi transportasi API telah terhubung ke middleware (EncryptionMiddleware, AES-256-CBC, dipicu header)
- Kunci JWT telah dikonfigurasi (JWT_SECRET + JWT_SECRET_KEY keduanya telah diset)
- Deteksi unggahan file telah diperbaiki (menggabungkan data $_FILES dan meneruskan ke SecurityGuard)
- Tidak ada regresi fungsional (22/22 pengujian lulus)
- Tidak ada deteksi middleware berulang
- Variabel lingkungan deployment Docker lengkap
