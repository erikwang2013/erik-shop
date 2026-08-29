# ক্রস-বর্ডার ই-কমার্স প্ল্যাটফর্ম — আর্কিটেকচার ওভারভিউ

> এই নথিটি মূল চীনা ডকুমেন্টেশনের মেশিন অনুবাদ। মূল: [চীনা মূল](../../architecture.md).

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. টেকনোলজি স্ট্যাক

| স্তর | প্রযুক্তি | ভার্সন |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| ক্লায়েন্ট | Flutter (৫টি প্ল্যাটফর্ম) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| ডেটাবেস | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| পেমেন্ট | Stripe / PayPal / Klarna / Adyen | — |

## 2. ডিরেক্টরি স্ট্রাকচার

```
shop-php/
  service/            বিজনেস API (251 PHP ফাইল)
    config/            36 কনফিগ (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/cdn/...)
    app/controller/    39 কন্ট্রোলার (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 মডেল (BaseModel + 110 বিজনেস মডেল)
    app/middleware/     14 মিডলওয়্যার (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8 টুল ক্লাস (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (রুটের install.sql দিয়ে প্রতিস্থাপিত হয়েছে) + seeders
    tests/              4 টেস্ট ক্লাস (22 tests, 45 assertions)
  admin/             ম্যানেজমেন্ট ব্যাকএন্ড (239 PHP ফাইল)
    plugin/admin/app/controller/shop/ 82 কন্ট্রোলার
    plugin/admin/app/model/shop/      76 মডেল
    plugin/admin/app/view/shop/       ECharts ড্যাশবোর্ড
    app/middleware/    5 মিডলওয়্যার (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              ক্লায়েন্ট
    flutter/lib/      25 Dart (11 পেজ + কোর লেয়ার + রাউটিং)
    harmonyos/        14 ArkTS (9 পেজ + API ক্লায়েন্ট + গ্লোবাল স্টেট)
  docs/               5 ডিজাইন ডকুমেন্ট
  .claude/skills/     38 ডেভেলপমেন্ট স্ট্যান্ডার্ড Skills
```

## 3. মিডলওয়্যার পাইপলাইন

```
Service: Cors → Security(31 ধরনের অ্যাটাক ডিটেকশন) → RateLimit(টোকেন বাকেট রেট লিমিট) → Platform(8 প্ল্যাটফর্ম আইডেন্টিফিকেশন)
        → GeoIp(অঞ্চল) → Locale(ভাষা) → HashidsDecode → VersionRoute
        → (PosterVerify হিউম্যান ভেরিফিকেশন) → (JwtAuth Token) → HashidsEncode → Encryption(ইন্টারফেস এনক্রিপশন)

Admin:  Security → Platform → HashidsDecode → AccessControl(বিল্ট-ইন RBAC) → HashidsEncode
```

## 4. নিরাপত্তা

- **31 ধরনের অ্যাটাক ডিটেকশন**: XSS/SQL ইনজেকশন/কমান্ড ইনজেকশন/CRLF/পাথ ট্রাভার্সাল/Body/ContentType/ফাইল আপলোড/ব্রুট ফোর্স/XXE/SSRF/ডিসিরিয়ালাইজেশন/LDAP/মেইল হেডার/SSTI/NoSQL/ওপেন রিডাইরেক্ট/JWT অ্যাটাক/Host/রিকোয়েস্ট স্মাগলিং/GraphQL/XPATH/Log4Shell/SSI/CSV ফর্মুলা/ডেটা লিক/প্রোটোটাইপ পলিউশন/WebSocket/CORS/DNS রিবাইন্ডিং/HTTP মেথড/CSRF Origin
- **তিন স্তরের এনক্রিপশন**: ইন্টারফেস স্তর (AES-256-CBC) + ডেটাবেস স্তর (Encryptable trait) + ID অবফাসকেশন (Hashids)
- **প্ল্যাটফর্ম ট্র্যাকিং**: 8 প্ল্যাটফর্ম (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + X-Platform header + 6 টেবিলে রেকর্ড

## 5. হাই কনকারেন্সি

- **রেট লিমিট**: টোকেন বাকেট স্লাইডিং উইন্ডো (Redis ZSET), 6 এন্ডপয়েন্ট রুল
- **সার্কিট ব্রেকার/ডিগ্রেডেশন**: Redis সার্কিট ব্রেকার — বাহ্যিক API কল (পেমেন্ট গেটওয়ে/সোশ্যাল লগইন): ধারাবাহিক 5টি ব্যর্থতা → 30s খোলা, হাফ-ওপেন প্রোবসহ স্বয়ংক্রিয় পুনরুদ্ধার; বিজনেস এরর ব্যর্থতা হিসেবে গণনা করা হয় না; Redis ডাউন হলে স্বয়ংক্রিয় fail-open (503)
- **DB**: রিড-রাইট সেপারেশন (2 রিড রেপ্লিকা + sticky) + কানেকশন পুল (50/10)
- **ধীর অপারেশন**: আলাদা Cron প্রসেসে পরিচালিত (Feed সিঙ্ক/রেকমেন্ডেশন ক্যালকুলেশন/পেমেন্ট রিকনসিলিয়েশন/সেটেলমেন্ট ইত্যাদি)

## 6. টেস্ট

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+ডেটা লিক
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

## 7. ডিপ্লয়মেন্ট

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# ম্যানুয়াল
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **মাল্টি-ল্যাঙ্গুয়েজ (i18n)**: 5 ভাষার ট্রান্সলেশন ফাইল + LocaleMiddleware + Flutter AppLocalizations
- **API ডকুমেন্টেশন**: hg/apidoc স্বয়ংক্রিয় জেনারেশন (6 গ্রুপ, কন্ট্রোলার অ্যানোটেশন ড্রাইভেন)
- **প্ল্যাটফর্ম ট্র্যাকিং**: 8 প্ল্যাটফর্ম X-Platform header + DB রেকর্ড
- **CDN এক্সিলারেশন**: অরিজিন-পুল মডেল — আপলোড admin অরিজিন ডিস্কে থাকে এবং রিসোর্স URL আউটপুটে `Cdn::url()` দিয়ে `https://{CDN_DOMAIN}{path}` রিরাইট হয়; ৪টি প্রোভাইডার (Cloudflare/CloudFront/Aliyun/Tencent) fail-open অটো-পার্জ ও ৭ দিনের এজ ক্যাশিংসহ

বিস্তারিত: [ডিপ্লয়মেন্ট ডকুমেন্ট](deployment.md) | [সম্পূর্ণ আর্কিটেকচার ডকুমেন্ট](architecture-full.md) | [ফিচার ডিজাইন ডকুমেন্ট](features.md)
