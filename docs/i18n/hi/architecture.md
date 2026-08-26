# क्रॉस-बॉर्डर ई-कॉमर्स प्लेटफ़ॉर्म — आर्किटेक्चर अवलोकन

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. तकनीकी स्टैक

| परत | तकनीक | संस्करण |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| क्लाइंट | Flutter (5 प्लेटफ़ॉर्म) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| डेटाबेस | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| भुगतान | Stripe / PayPal / Klarna / Adyen | — |

## 2. निर्देशिका संरचना

```
shop-php/
  service/            व्यावसायिक API (251 PHP फ़ाइलें)
    config/            35 कॉन्फ़िग (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/    39 कंट्रोलर (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111 मॉडल (BaseModel + 110 व्यावसायिक मॉडल)
    app/middleware/     14 मिडलवेयर (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8 टूल क्लास (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (रूट install.sql द्वारा प्रतिस्थापित) + seeders
    tests/              4 टेस्ट क्लास (22 टेस्ट, 45 असर्शन)
  admin/              प्रशासन पैनल (239 PHP फ़ाइलें)
    plugin/admin/app/controller/shop/ 82 कंट्रोलर
    plugin/admin/app/model/shop/      76 मॉडल
    plugin/admin/app/view/shop/       ECharts डैशबोर्ड
    app/middleware/    5 मिडलवेयर (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/               क्लाइंट
    flutter/lib/      25 Dart (11 पृष्ठ + कोर परत + रूटिंग)
    harmonyos/        14 ArkTS (9 पृष्ठ + API क्लाइंट + ग्लोबल स्टेट)
  docs/               5 डिज़ाइन दस्तावेज़
  .claude/skills/     38 विकास मानक Skills
```

## 3. मिडलवेयर पाइपलाइन

```
Service: Cors → Security(31 प्रकार की हमले पहचान) → RateLimit(टोकन बकेट रेट लिमिट) → Platform(8 प्लेटफ़ॉर्म पहचान)
        → GeoIp(क्षेत्र) → Locale(भाषा) → HashidsDecode → VersionRoute
        → (PosterVerify मानव-सत्यापन) → (JwtAuth Token) → HashidsEncode → Encryption(इंटरफ़ेस एन्क्रिप्शन)

Admin:  Security → Platform → HashidsDecode → AccessControl(अंतर्निहित RBAC) → HashidsEncode
```

## 4. सुरक्षा

- **31 प्रकार की हमले पहचान**: XSS/SQL इंजेक्शन/कमांड इंजेक्शन/CRLF/पाथ ट्रैवर्सल/Body/ContentType/फ़ाइल अपलोड/ब्रूट फ़ोर्स/XXE/SSRF/डिसीरियलाइज़ेशन/LDAP/ईमेल हेडर/SSTI/NoSQL/ओपन रीडायरेक्ट/JWT हमला/Host/रिक्वेस्ट स्मगलिंग/GraphQL/XPATH/Log4Shell/SSI/CSV फ़ॉर्मूला/डेटा लीक/प्रोटोटाइप पोल्यूशन/WebSocket/CORS/DNS रीबाइंडिंग/HTTP विधि/CSRF Origin
- **तीन-परत एन्क्रिप्शन**: इंटरफ़ेस परत (AES-256-CBC) + डेटाबेस परत (Encryptable trait) + ID अस्पष्टीकरण (Hashids)
- **प्लेटफ़ॉर्म ट्रैकिंग**: 8 प्लेटफ़ॉर्म (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + X-Platform header + 6 टेबल रिकॉर्ड

## 5. उच्च समवर्ती

- **रेट लिमिट**: टोकन बकेट स्लाइडिंग विंडो (Redis ZSET), 6 एंडपॉइंट नियम
- **DB**: रीड/राइट स्प्लिटिंग (2 रीड रेप्लिका + sticky) + कनेक्शन पूल (50/10)
- **धीमे ऑपरेशन**: स्वतंत्र Cron प्रक्रियाओं द्वारा संसाधित (Feed सिंक/अनुशंसा गणना/भुगतान मिलान/सेटलमेंट लेखा आदि)

## 6. टेस्ट

22 टेस्ट / 45 असर्शन — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+डेटा लीक
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

## 7. तैनाती

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# मैनुअल
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **बहुभाषी (i18n)**: 5 भाषा अनुवाद फ़ाइलें + LocaleMiddleware + Flutter AppLocalizations
- **API दस्तावेज़**: hg/apidoc स्वचालित उत्पादन (6 समूह, कंट्रोलर एनोटेशन संचालित)
- **प्लेटफ़ॉर्म ट्रैकिंग**: 8 प्लेटफ़ॉर्म X-Platform header + DB रिकॉर्ड

विवरण के लिए देखें: [तैनाती दस्तावेज़](deployment.md) | [पूर्ण आर्किटेक्चर दस्तावेज़](architecture-full.md) | [कार्यक्षमता डिज़ाइन दस्तावेज़](features.md)
