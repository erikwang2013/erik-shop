# Platform E-Commerce Lintas Batas — Ringkasan Arsitektur

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. Tumpukan Teknologi

| Lapisan | Teknologi | Versi |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| Klien | Flutter (5 platform) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| Database | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| Pembayaran | Stripe / PayPal / Klarna / Adyen | — |

## 2. Struktur Direktori

```
shop-php/
  service/           API bisnis (251 file PHP)
    config/            36 konfigurasi (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/cdn/...)
    app/controller/    39 controller (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 model (BaseModel + 110 model bisnis)
    app/middleware/     14 middleware (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          9 kelas utilitas (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions/Cdn)
    database/          schema.sql (telah digantikan oleh install.sql di root) + seeders
    tests/              4 kelas pengujian (22 tests, 45 assertions)
  admin/             Panel admin (239 file PHP)
    plugin/admin/app/controller/shop/ 82 controller
    plugin/admin/app/model/shop/      76 model
    plugin/admin/app/view/shop/       dasbor ECharts
    app/middleware/    5 middleware (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              Klien
    flutter/lib/      25 Dart (11 halaman + lapisan inti + routing)
    harmonyos/        14 ArkTS (9 halaman + klien API + status global)
  docs/               5 dokumen desain
  .claude/skills/     38 Skills standar pengembangan
```

## 3. Pipa Middleware

```
Service: Cors → Security(deteksi 31 jenis serangan) → RateLimit(rate limiting token bucket) → Platform(pengenalan 8 platform)
        → GeoIp(wilayah) → Locale(bahasa) → HashidsDecode → VersionRoute
        → (PosterVerify verifikasi manusia) → (JwtAuth Token) → HashidsEncode → Encryption(enkripsi antarmuka)

Admin:  Security → Platform → HashidsDecode → AccessControl(RBAC bawaan) → HashidsEncode
```

## 4. Keamanan

- **Deteksi 31 jenis serangan**: XSS/Injeksi SQL/Injeksi perintah/CRLF/Path Traversal/Body/ContentType/Unggah file/Brute force/XXE/SSRF/Deserialisasi/LDAP/Header email/SSTI/NoSQL/Open Redirect/Serangan JWT/Host/Request smuggling/GraphQL/XPATH/Log4Shell/SSI/Formula CSV/Kebocoran data/Prototype pollution/WebSocket/CORS/DNS rebinding/Metode HTTP/CSRF Origin
- **Enkripsi tiga lapis**: Lapisan antarmuka (AES-256-CBC) + Lapisan database (trait Encryptable) + Obfuscation ID (Hashids)
- **Pelacakan platform**: 8 platform (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + header X-Platform + pencatatan 6 tabel

## 5. Konkurensi Tinggi

- **Rate limiting**: jendela geser token bucket (Redis ZSET), 6 aturan endpoint
- **Circuit breaker/degradasi**: circuit breaker Redis — panggilan API eksternal gateway pembayaran/login sosial, 5 kegagalan beruntun→terbuka 30s, probe half-open pulih otomatis; pengecualian bisnis tidak dihitung sebagai kegagalan; saat Redis gagal otomatis degradasi mengizinkan (503)
- **DB**: pemisahan baca/tulis (2 replika baca + sticky) + kumpulan koneksi (50/10)
- **Operasi lambat**: ditangani oleh proses Cron independen (sinkronisasi Feed/perhitungan rekomendasi/rekonsiliasi pembayaran/settlement pembagian dana, dll.)

## 6. Pengujian

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+kebocoran data
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

## 7. Deployment

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# Manual
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **Multibahasa (i18n)**: 5 file terjemahan bahasa + LocaleMiddleware + Flutter AppLocalizations
- **CDN**: Origin-Pull multi-provider (Cloudflare/CloudFront/Aliyun/Tencent) — `Cdn::url()` menulis ulang ke `https://{CDN_DOMAIN}{path}`, cache edge 7d immutable (volume unggahan: admin_uploads/service_public)
- **Dokumentasi API**: dibuat otomatis oleh hg/apidoc (6 grup, didorong anotasi controller)
- **Pelacakan platform**: 8 platform header X-Platform + pencatatan DB

Lihat: [Dokumen Deployment](deployment.md) | [Dokumen Arsitektur Lengkap](architecture-full.md) | [Dokumen Desain Fitur](features.md)
