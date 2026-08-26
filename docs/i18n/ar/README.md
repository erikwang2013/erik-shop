> هذه الوثيقة ترجمة آلية للوثائق الصينية الأصلية. الأصل: [中文原版](../../../README.md).

# Erik Shop — منصة التجارة الإلكترونية عبر الحدود (الإصدار الكامل Full)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## الإصدارات

> الإصدار المبسط (MIT مفتوح المصدر): `lite` | الإصدار القياسي (تجاري): `standard` | الإصدار الكامل (تجاري): `full`
>
> للترخيص التجاري: **erik@erik.xyz** | مقارنة الإصدارات: [VERSIONS.md](VERSIONS.md)

## اللغة / Languages

| اللغة | الرابط |
|------|------|
| 中文 | [README.md](README.md) |
| English | [docs/i18n/en/README.md](../en/README.md) |
| 한국어 | [docs/i18n/ko/README.md](../ko/README.md) |
| Русский | [docs/i18n/ru/README.md](../ru/README.md) |
| Deutsch | [docs/i18n/de/README.md](../de/README.md) |
| Français | [docs/i18n/fr/README.md](../fr/README.md) |
| Español | [docs/i18n/es/README.md](../es/README.md) |
| Português | [docs/i18n/pt/README.md](../pt/README.md) |
| हिन्दी | [docs/i18n/hi/README.md](../hi/README.md) |
| العربية | [docs/i18n/ar/README.md](../ar/README.md) |
| বাংলা | [docs/i18n/bn/README.md](../bn/README.md) |
| Bahasa Indonesia | [docs/i18n/id/README.md](../id/README.md) |
| 日本語 | [docs/i18n/ja/README.md](../ja/README.md) |

## نبذة عن المشروع

منصة تجارة إلكترونية متكاملة عبر الحدود مبنية على عائلة webman، تغطي سيناريوهات B2C/B2B واستضافة البائعين من طرف ثالث.

### البنية التقنية

| الطبقة | التقنية | الدليل |
|------|------|------|
| API الأعمال | webman + illuminate/database + erikwang2013/* | `service/` |
| لوحة الإدارة | webman-admin + LayUI + ECharts | `admin/` |
| العميل | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |
| عميل HarmonyOS | ArkTS + ArkUI (HarmonyOS NEXT) | `apps/harmonyos/` |

### مجموعة التقنيات

**الخادم:** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**الحزم الأساسية:** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**الدفع:** Stripe, PayPal (كامل)؛ Klarna, Adyen (نائب، `PaymentGateway::make` غير منفذ، انظر PLAN.md)
**العميل:** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## مجموعة الرسوم البيانية للبنية

> مجموعة الرسوم الكاملة وعرض كبير: [diagrams.md](diagrams.md)

### مخطط بنية النظام

![مخطط بنية النظام](diagrams/01-system-architecture.svg)

### مخطط تدفق معالجة الطلبات

![مخطط تدفق معالجة الطلبات](diagrams/02-request-processing-flow.svg)

### خريطة وحدات الميزات الشاملة

![خريطة وحدات الميزات الشاملة](diagrams/03-feature-module-map.svg)

### مخطط دورة حياة الطلب

![مخطط دورة حياة الطلب](diagrams/04-request-lifecycle.svg)

> مزيد من التفاصيل في [مجموعة الرسوم الكاملة](diagrams.md) (تشمل دورة حياة الطلب، بنية النشر، البنية الأمنية، التسوية متعددة العملات — 8 رسوم)

### مخطط البنية الأمنية

![مخطط البنية الأمنية](diagrams/07-security-architecture.svg)

### مخطط تدفق التسوية متعددة العملات

![مخطط تدفق التسوية متعددة العملات](diagrams/08-multi-currency-settlement.svg)

### شرح التسوية متعددة العملات

**التسعير متعدد العملات**: تُسعَّر SKU المنتجات حسب `currency_code` لكل عملة، وعند الطلب تُثبَّت عملة التحصيل (USD / EUR / GBP / CNY إلخ).

**خدمة أسعار الصرف**: يدعم جدول `erik_exchange_rates` الصيانة اليدوية manual والسحب التلقائي من exchangerate-api، مع إدارة النسخ حسب وقت السريان `effective_at`، وتُلتقط لقطة سعر الصرف في وقت الدفع عند التسوية.

**الخصم بالعملة الأصلية**: Stripe / PayPal يخصمان بالعملة الأصلية حسب عملة الطلب (Klarna/Adyen نائب غير موصول)، ويتم تحديث حالة الدفع والطلب بعد تأكيد وصول الأموال عبر التحقق من توقيع Webhook.

**التسوية المقسومة**: بعد نجاح الدفع تُنشأ تلقائيًا تسوية المنصة `PlatformSettlements` (إجمالي الطلب + عمولة المنصة + رسوم بوابة الدفع، مُسجلة بعملة الطلب)؛ تسوية البائع `MerchantSettlements` (مبلغ الطلب ← نسبة الخصم ← مبلغ التسوية)، تسوية المورد `SupplierSettlements`، وسحب عمولة التوزيع `AffiliatePayouts` — أربعة خطوط تسوية مستقلة، الحالة 0 قيد التسوية / 1 تم التسوية.

**أرباح/خسائر الصرف**: يتتبع `CurrencyExchangeGainsLosses` الفرق بين عملة التحصيل وعملة التسوية، بمقارنة سعر الصرف وقت الدفع مع سعر الصرف وقت التسوية، الموجب = ربح صرف والسالب = خسارة صرف، لدعم مطابقة الحسابات والتدقيق متعدد العملات في التجارة عبر الحدود.

## البدء السريع

### الطريقة الأولى: التثبيت بنقرة واحدة عبر الويب (موصى بها)

```bash
# 1. تثبيت تبعيات admin
cd admin && composer install

# 2. تشغيل لوحة الإدارة
php start.php start -d

# 3. فتح معالج التثبيت في المتصفح
# http://127.0.0.1:8788/app/admin/install/step1
# أدخل معلومات قاعدة البيانات → إعداد حساب المدير → اكتمل

# 4. تثبيت التبعيات وتشغيل API
cd ../service && composer install && php start.php start -d
```

> يكمل معالج التثبيت تلقائيًا: إنشاء قاعدة البيانات ← استيراد 117 جدولًا ← إنشاء service/.env و admin/.env (يشمل مفاتيح عشوائية) ← إنشاء المدير ← إعادة تحميل الخدمات

### الطريقة الثانية: التثبيت اليدوي عبر سطر الأوامر

انظر [INSTALL.md](../../INSTALL.md)

### النشر عبر Docker

```bash
# إعداد متغيرات البيئة
cp .env.example .env  # أو تعيين DB_PASS / JWT_SECRET وغيرها من المتغيرات

# تشغيل جميع الخدمات بنقرة واحدة
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

انظر [وثيقة النشر](deployment.md)

## هيكل المشروع

```
shop-php/
  install.sql       # SQL التثبيت بنقرة واحدة (117 جدولًا)، يستورده معالج التثبيت تلقائيًا
  service/          واجهة برمجة تطبيقات PHP (webman)        — 39 متحكمًا + 111 نموذجًا + 14 وسيطة
  admin/            لوحة الإدارة (webman-admin)      — 82 متحكمًا + 76 نموذجًا + لوحات ECharts + معالج تثبيت ويب
  apps/flutter/     عميل Flutter              — 11 صفحة + 5 لغات + تكيف مع الكمبيوتر الشخصي
  apps/harmonyos/   عميل HarmonyOS                  — 9 صفحات + ArkTS
  docker/           نشر Docker                  — Nginx + PHP + MySQL + Redis + ES
  docs/             وثائق التصميم
```

## تغطية الميزات

| البُعد | المحتوى المغطى |
|------|---------|
| **بيع التجزئة B2C** | منتجات متعددة اللغات، تسعير لكل عملة، SKU، سلة تسوق، طلبات، دفع، استرداد، إرجاع |
| **الجملة B2B** | تسعير متدرج (MOQ)، تحقق الشركات (الرقم الضريبي/السجل التجاري)، الاستفسار عن الأسعار |
| **استضافة البائعين** | مراجعة البائعين، مراجعة المنتجات، توزيع العمولات والتسوية |
| **الامتثال عبر الحدود** | مكتبة رموز HS Code، قواعد الرسوم الجمركية، VAT/IOSS، ملصقات الامتثال للدول (FDA/CE/RoHS) |
| **اللوجستيات الدولية** | شحن حسب المناطق، مستودعات خارجية (شحن + إرجاع)، فاتورة تجارية/قائمة تعبئة، تصريح HS (قيد التخطيط) |
| **الدفع** | Stripe/PayPal (كامل)، Klarna/Adyen (نائب)، BNPL اشترِ الآن وادفع لاحقًا (نائب)، تحقق 3DS |
| **التسويق** | قسائم الخصم (مناطق + عملاء جدد/قدامى)، صور دائرية (مرئية حسب المنطقة)، بيع خاطف، شراء جماعي، توزيع (رابط + عمولة + سحب) |
| **متعدد المنصات** | نشر المنتجات وتجميع الطلبات على Amazon/eBay/Shopee/Lazada/Temu |
| **سلسلة التوريد** | تقييم الموردين، شراء←فحص الجودة←استلام، سجل المخزون (دفتر غير قابل للتغيير)، نقل |
| **إدارة المخاطر والامتثال** | محرك قواعد (تقييم جانبي)، تحقق KYC، طلبات بيانات GDPR/CCPA، موافقة Cookie |
| **الحماية الأمنية** | كشف 31 نوع هجوم (XSS/حقن SQL/XXE/SSRF/CRLF/عبور المسار/رفع الملفات/القوة الغاشمة/طريقة HTTP/Host/CORS إلخ) |
| **التزامن العالي** | تحديد المعدل بدلو الرموز، فصل القراءة/الكتابة في قاعدة البيانات، تحسين تجمع الاتصالات |
| **نمو الأعضاء** | قواعد النقاط، امتيازات مستويات العضوية، بطاقات الهدايا، تنبيهات انخفاض الأسعار، الشراء الدوري بالاشتراك، اختبار AB |
| **إدارة المحتوى** | صفحات CMS متعددة اللغات، الأسئلة الشائعة FAQ، قاعدة المعرفة، جدول مقاسات الملابس، قوالب البريد الإلكتروني، مزامنة خلاصات المنتجات |
| **خدمة العملاء** | محادثة فورية عبر WebSocket، قاعدة المعرفة (تم إنشاء بنية الجداول) |
| **البنية التحتية** | معرف Snowflake الموزع، إخفاء واجهات Hashids، مصادقة JWT، تشفير AES، تحديد المنطقة GeoIP |
| **تغطية متعددة الأطراف** | Flutter (iOS/Android/macOS/Windows/Linux/iPadOS) + HarmonyOS (ArkTS) + Web Admin |
| **تتبع المنصات** | التعرف على 8 منصات (iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + تسجيل في قاعدة البيانات |
| **الاختبارات** | 22 اختبارًا / 45 تأكيدًا — ALL PASS (Security+Jwt+ApiResponse+Redis) |

## التصميم الأساسي

- **مفتاح Snowflake**: جميع الجداول الـ117 تستخدم معرّف bigint المولّد من `erikwang2013/snowflake-php`
- **واجهات Hashids**: تقوم الوسيطة بالتشفير/فك التشفير تلقائيًا، دون إدراك المتحكمين
- **تشفير Encryptable**: تشفير على مستوى قاعدة البيانات للحقول الحساسة مثل email/mobile/address
- **مصادقة JWT**: HS256 + رمزا وصول/تحديث access/refresh بتجديد تلقائي
- **إصدار API**: توجيه عبر ترويسة `API-Version`، وليس في URL
- **تحقق Poster**: تحقق بشري عشوائي للعمليات الحساسة (تسجيل/طلب/دفع)

## التوثيق

| الوثيقة | الوصف |
|------|------|
| [README-EN.md](../../README-EN.md) | English documentation |
| [INSTALL.md](../../INSTALL.md) | دليل التثبيت (تثبيت ويب بنقرة واحدة + تثبيت يدوي) |
| [AUDIT-REPORT.md](../../AUDIT-REPORT.md) | تقرير مراجعة نظام التثبيت |
| [خطة المشروع](PLAN.md) | خطة مشروع مرحلية من إنتاج الفريق (خريطة طريق من 4 مراحل + مخاطر رئيسية + مكاسب سريعة) |
| [تفاصيل بحث الفريق](PLAN-RESEARCH.md) | بحث الوضع الراهن في 7 مجالات: منفذ / فجوات / مخاطر / توصيات |
| [وثيقة تصميم الميزات](features.md) | مصفوفة الميزات الكاملة، العمليات التجارية، آلات الحالة |
| [مجموعة الرسوم البيانية](diagrams.md) | رسوم البنية والتدفقات والوظائف ودورات الحياة والنشر والتسوية متعددة العملات (8 رسوم Mermaid) |
| [وثيقة تصميم البنية](architecture-full.md) | مخطط بنية النظام، خط أنابيب الوسائط، بنية البيانات، البنية الأمنية، بنية الدفع |
| [وثيقة التصميم](design.md) | تصميم جداول قاعدة البيانات، مواصفات API، خطط الأمان، التدويل |
| [وثيقة البنية](architecture.md) | هيكل الدلائل، سلسلة وراثة النماذج، الحزم الرئيسية |
| [وثيقة واجهات API](api.md) | 71 نقطة نهاية API (وثائق ثابتة) |
| [وثائق hg/apidoc](http://localhost:8787/apidoc/) | مولّدة تلقائيًا بواسطة hg/apidoc (6 مجموعات: المصادقة/المنتجات/المعاملات/الشحن والجمارك/المستخدم والتسويق/العمليات) |
| [وثيقة النشر](deployment.md) | نشر Docker/يدوي، متغيرات البيئة، أوامر التشغيل والصيانة |


## المصدر المفتوح يحتاج دعمكم

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](../../weixinpay.png "WeChat") | ![Alipay](../../alipay.png "Alipay") |

### التحويل البنكي العالمي (ZA Bank)

**معلومات المستفيد**

- اسم المستفيد: WANG KEXUN
- رقم حساب المستفيد: 881015918251

**البنك المستفيد**

- SWIFT Code: AABLHKHHXXX
- اسم البنك: ZA Bank Limited
- رقم البنك: 387
- عنوان البنك: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**البنك الوسيط للتحويلات الدولية (إذا لزم الأمر)**

> هذه معلومات البنك الوسيط للتحويلات الدولية (بنك التحويل)، وليست معلومات البنك المستفيد. يُرجى الاستفسار من بنك التحويل عما إذا كان مطلوبًا تقديمها.

- **لإيداع الدولار الهونغ كونغي واليوان الصيني والدولار الأمريكي** (البنك الوسيط Citibank):
  - اسم البنك: Citibank N.A. Hong Kong
  - SWIFT Code: CITIHKHXXXX
  - رقم البنك: 006
  - اسم الفرع: Hong Kong Branch
  - رقم الفرع: 391
  - عنوان البنك: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **للعملات الأخرى** (البنك الوسيط BNY Mellon):
  - اسم البنك: THE BANK OF NEW YORK MELLON
  - SWIFT Code: IRVTUS3NXXX
  - عنوان البنك: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---


## الاختبارات

```bash
make test             # الطريقة الموصى بها
cd service && php vendor/bin/phpunit tests/   # الأمر الأصلي
# 22 tests, 45 assertions — ALL PASS

# مراجعة أمان التبعيات (ثغرة CVE واحدة معروفة منخفضة الخطورة: CVE-2025-45769 firebase/php-jwt <7.0.0،
# مقيدة بـ jwt-webman ^6.0 ولا يمكن ترقيتها، استخدام توقيع HS256 المتماثل غير متأثر)
composer audit
```

## أدوات التطوير

```bash
make help             # عرض جميع الأوامر
make lint             # فحص بناء جملة PHP
make check            # التحليل الثابت phpstan
make fix              # تنسيق الكود php-cs-fixer
```

CI/CD: `.github/workflows/ci.yml` — اختبارات مصفوفة PHP 8.3/8.4

## الترخيص

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
