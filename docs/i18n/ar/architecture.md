# منصة التجارة الإلكترونية عبر الحدود — نظرة عامة على البنية

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. حزمة التقنيات

| الطبقة | التقنية | الإصدار |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| العميل | Flutter (5 منصات) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| قاعدة البيانات | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| الدفع | Stripe / PayPal / Klarna / Adyen | — |

## 2. هيكل الدلائل

```
shop-php/
  service/           API الأعمال (251 ملف PHP)
    config/            35 إعدادًا (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/    39 متحكمًا (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 نموذجًا (BaseModel + 110 نماذج أعمال)
    app/middleware/     14 وسيطة (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8 فئات أدوات (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (استُبدل بـ install.sql في الجذر) + seeders
    tests/              4 فئات اختبار (22 tests, 45 assertions)
  admin/             لوحة الإدارة (239 ملف PHP)
    plugin/admin/app/controller/shop/ 82 متحكمًا
    plugin/admin/app/model/shop/      76 نموذجًا
    plugin/admin/app/view/shop/       لوحات ECharts
    app/middleware/    5 وسائط (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              العملاء
    flutter/lib/      25 ملف Dart (11 صفحة + الطبقة الأساسية + التوجيه)
    harmonyos/        14 ملف ArkTS (9 صفحات + عميل API + الحالة العامة)
  docs/               5 وثائق تصميم
  .claude/skills/     38 مهارة تطوير قياسية
```

## 3. خط أنابيب الوسائط

```
Service: Cors → Security(فحص 31 نوع هجوم) → RateLimit(تحديد المعدل بدلو الرموز) → Platform(تمييز 8 منصات)
        → GeoIp(المنطقة) → Locale(اللغة) → HashidsDecode → VersionRoute
        → (PosterVerify تحقق بشري) → (JwtAuth Token) → HashidsEncode → Encryption(تشفير الواجهة)

Admin:  Security → Platform → HashidsDecode → AccessControl(مدمج RBAC) → HashidsEncode
```

## 4. الأمان

- **فحص 31 نوع هجوم**: XSS/حقن SQL/حقن أوامر/CRLF/عبور المسار/Body/ContentType/رفع الملفات/القوة الغاشمة/XXE/SSRF/إلغاء التسلسل/LDAP/ترويسات البريد/SSTI/NoSQL/إعادة التوجيه المفتوحة/هجمات JWT/Host/تهريب الطلبات/GraphQL/XPATH/Log4Shell/SSI/صيغ CSV/تسرب البيانات/تلوث النماذج الأولية/WebSocket/CORS/إعادة ربط DNS/طرق HTTP/CSRF Origin
- **التشفير ثلاثي الطبقات**: طبقة الواجهة (AES-256-CBC) + طبقة قاعدة البيانات (Encryptable trait) + إخفاء المعرّفات (Hashids)
- **تتبع المنصات**: 8 منصات (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + ترويسة X-Platform + تسجيل في 6 جداول

## 5. الالتزام العالي

- **تحديد المعدل**: دلو الرموز بنافذة منزلقة (Redis ZSET)، قواعد لـ 6 نقاط نهاية
- **قاعدة البيانات**: فصل القراءة/الكتابة (نسختا قراءة + sticky) + تجمع الاتصالات (50/10)
- **العمليات البطيئة**: تعالجها عمليات Cron مستقلة (مزامنة Feed/حساب التوصيات/مطابقة الدفع/تسوية التقسيم إلخ)

## 6. الاختبارات

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+تسرب البيانات
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

## 7. النشر

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# يدوي
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **تعدد اللغات (i18n)**: ملفات ترجمة لـ 5 لغات + LocaleMiddleware + Flutter AppLocalizations
- **توثيق API**: مولّد تلقائيًا عبر hg/apidoc (6 مجموعات، مدفوع بتعليقات المتحكمين)
- **تتبع المنصات**: ترويسة X-Platform لـ 8 منصات + تسجيل في قاعدة البيانات

انظر: [وثيقة النشر](deployment.md) | [وثيقة البنية الكاملة](architecture-full.md) | [وثيقة تصميم الميزات](features.md)
