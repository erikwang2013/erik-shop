# تقرير مراجعة تكامل إضافة Security

**التاريخ**: 2026-08-04
**النطاق**: تكامل erikwang2013/security-php v1.1.6
**المراجع**: Claude Code (تلقائي)

---

## 1. نتائج الاختبارات

| الفحص | النتيجة |
|---|---|
| فحص صيغة PHP (47 ملفًا) | الكل ناجح |
| PHPUnit (22 tests, 45 assertions) | الكل ناجح |
| اختبار الحمولات الأمنية لـ SecurityGuard | اعتراض صحيح لـ XSS + SQLi |
| اختبار الطلبات الآمنة لـ SecurityGuard | لا إنذارات كاذبة |
| التحليل الثابت phpstan | غير مثبت (غير مانع) |

## 2. المشكلات المُصلحة

### 2.1 بيانات رفع الملفات لا تُمرر إلى SecurityGuard (حرجة)

**الملفات**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

كانت الوسيطة تمرر `$request->all()` فقط إلى `SecurityGuard::guard()`، وهذه الطريقة لا تتضمن بيانات رفع الملفات. يحتاج `UploadDetector` بيانات الملفات بصيغة `['tmp_name' => ..., 'name' => ...]`.

**الإصلاح**: أُضيفت حلقة لدمج `$request->file()` في مصفوفة البيانات قبل تمريرها إلى `SecurityGuard::guard()`.

### 2.2 إعداد encryptable في admin ينقصه قيمة افتراضية (متوسطة)

**الملف**: `admin/config/plugin/erikwang2013/encryptable/app.php`

يستخدم إعداد admin `env('ENCRYPTION_KEY')` بدون قيمة احتياطية، فيُرجع `null` عند غياب متغير البيئة. بينما يستخدم Service `getenv('ENCRYPTION_KEY') ?: ''` ويرجع صحيحًا إلى سلسلة فارغة.

**الإصلاح**: توحيد إعداد admin باستخدام معامل `?: ''` بما يتوافق مع سلوك service.

### 2.3 متغيرات بيئة Docker Compose غير مكتملة (متوسطة)

**الملف**: `docker-compose.yml`

- حاوية service تنقصها `ENCRYPTION_CIPHER` و `ENCRYPTION_PREVIOUS_KEYS`
- حاوية admin تنقصها `ENCRYPTION_KEY` و `ENCRYPTION_CIPHER` و `ENCRYPTION_PREVIOUS_KEYS` و `HASHIDS_SALT` و `SNOWFLAKE_WORKER_ID` و `SNOWFLAKE_DATACENTER_ID`

**الإصلاح**: أُضيفت جميع متغيرات البيئة المفقودة، بالقيم الافتراضية المتوافقة مع `.env.example`.

### 2.4 كشف مكرر في وسيطة WAF (حرجة، أُصلحت في الجولة الأولى)

تحتوي `SecurityMiddleware` المخصصة على ~200 سطر من التعبيرات النمطية المضمّنة، مكررة تمامًا مع كواشف حزمة `security-php` الـ31. يُفحص كل طلب مرتين، ما يهدر وحدة المعالجة وقد يتسبب في اعتراض مزدوج.

**الإصلاح**: أُعيدت كتابة الوسيطة لاستخدام واجهة `SecurityGuard::guard()`، وانخفضت من 341 سطرًا إلى ~110 (service)، ومن 136 سطرًا إلى ~85 (admin). حماية القوة الغاشمة وترويسات الاستجابة الأمنية محفوظة.

### 2.5 غياب ENCRYPTION_KEY (حرجة، أُصلحت في الجولة الأولى)

في ملف `.env.example` استخدم `ENCRYPTION_KEY` علامة نائبة، وينقصه `ENCRYPTION_CIPHER` و `ENCRYPTION_PREVIOUS_KEYS`. لا يوجد ملف `.env` فعلي.

**الإصلاح**: توليد مفتاح base64 من 32 بايت، وإضافة `ENCRYPTION_CIPHER=AES-256-CBC` و `ENCRYPTION_PREVIOUS_KEYS`، وإنشاء ملف `.env`.

## 3. اكتمال التهيئة البيئية

### 3.1 الحزم (متطابقة في المشروعين)

| الحزمة | الإصدار | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | مثبتة | مثبتة |
| erikwang2013/encryptable | - | مثبتة | مثبتة |
| erikwang2013/encryption | - | مثبتة | مثبتة |
| erikwang2013/jwt-webman | - | مثبتة | مثبتة |
| erikwang2013/hashids | - | مثبتة | مثبتة |
| erikwang2013/snowflake-php | - | مثبتة | مثبتة |
| erikwang2013/poster-php | - | مثبتة | مثبتة |
| erikwang2013/season | - | مثبتة | مثبتة |
| erikwang2013/webman-scout | - | مثبتة | مثبتة |

### 3.2 إعداد WAF

| البند | Service | Admin | الحالة |
|---|---|---|---|
| ملف الإعداد | `config/plugin/erikwang2013/security-php/app.php` | نفسه | نُشر |
| الكواشف المفعلة | 31/31 | 31/31 | صحيح |
| القائمة السوداء IP | مفعلة (5 هجمات/60 ثانية → حظر 900 ثانية) | نفسه | صحيح |
| كواشف وضع الحظر | 28 | 28 | صحيح |
| كواشف التسجيل فقط | 3 (header_injection, ssti, nosql_injection) | 3 | صحيح |
| التخزين | file | file | صحيح |
| التسجيل | مفعل (file، تدوير 10MB) | نفسه | صحيح |
| تسجيل الوسيطة | `config/middleware.php` | `config/middleware.php` | صحيح |

### 3.3 إعداد التشفير

| البند | Service | Admin | الحالة |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | نفسه | مُعيَّن |
| ENCRYPTION_CIPHER | `AES-256-CBC` | نفسه | مُعيَّن |
| ENCRYPTION_PREVIOUS_KEYS | (فارغ) | (فارغ) | مُعيَّن |
| إعداد encryptable | `config/plugin/erikwang2013/encryptable/app.php` | نفسه (مُوحَّد) | صحيح |
| إعداد encryption | `config/encryption.php` | - | صحيح |
| ملف .env | موجود | موجود | أُنشئ |
| .env.example | مُحدَّث | مُحدَّث | صحيح |
| docker-compose | مُحدَّث | مُحدَّث | صحيح |

### 3.4 النماذج التي تستخدم Encryptable trait

31 نموذجًا تستخدم `Encryptable` trait، والحقول الحساسة معلنة صحيحًا كـ `$encryptable`:

| الفئة | النماذج | الحقول الحساسة |
|---|---|---|
| معلومات شخصية للمستخدم | Users | email, mobile |
| معلومات شخصية للمستخدم | UserAddresses | name, phone, detail |
| معلومات شخصية للمستخدم | UserKyc | real_name, id_number |
| معلومات شخصية للمستخدم | UserSocialAccounts | access_token, refresh_token |
| الخصوصية | PrivacyRequests | email |
| المالية | GiftCards | receiver_email |
| المالية | AffiliatePayouts | account |
| المالية | PaymentGateways | name, api_key, api_secret, webhook_secret |
| المنصات | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| المنصات | PlatformAccounts | account_name, api_key, api_secret |
| المنصات | PlatformListings | platform_account_id |
| اللوجستيات | LogisticsCompanies | name, api_key |
| الموردون | Suppliers | name, email, phone |
| الموردون | B2bVerifications | company_name |
| البائعون | Merchants | store_name, email, phone |
| أخرى | EmailLogs | to_email |
| أخرى | 15 نموذجًا إضافيًا | حقول name |

## 4. إصلاحات الجولة الثانية (تشفير API + مفتاح JWT)

### 4.1 وسيطة تشفير استجابات API (متوسطة، مُصلحة)

**الملف**: `service/app/middleware/EncryptionMiddleware.php` (جديد)

حزمة `erikwang2013/encryption` مثبتة وفئة الأدوات `app/common/Encryption` موجودة، لكنها لم تكن موصولة بخط أنابيب الوسائط سابقًا. البيانات الحساسة للواجهات كانت تفتقر إلى تشفير/فك تشفير طبقة النقل.

**الإصلاح**:
- إنشاء `EncryptionMiddleware`، بتشفير/فك تشفير مدفوع بترويسات HTTP:
  - `X-Encrypted: 1` — فك تشفير الطلب: فك نص مشفر base64 إلى JSON ثم تمريره للمتحكم
  - `X-Encrypt-Response: 1` — تشفير الاستجابة: تشفير حقل `data` في الاستجابة إلى نص مشفر base64
  - `X-Encrypt-Fields: field1,field2` — تشفير الحقول المحددة فقط في الاستجابة
- تسجيلها كآخر مستوى في مكدس الوسائط (بعد HashidsEncode)
- فحص الصحة (`/api/health` و `/api/ping`) ونقاط التوثيق (`/apidoc`) تُتخطى التشفير/فك التشفير

### 4.2 عدم تطابق اسم الفئة/الملف (متوسطة، مُصلحة)

**الملف**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

الفئة `app\common\Encryption` معلنة في ملف `EncryptionHelper.php`، ما لا يتوافق مع معيار PSR-4 ويؤدي إلى فشل تحميل Composer التلقائي. في بيئات IDE وCLI قد يتعذر على أداة التحميل التلقائي إيجاد هذه الفئة.

**الإصلاح**: إعادة تسمية الملف إلى `Encryption.php` لمطابقة اسم الفئة.

### 4.3 JWT_SECRET_KEY فارغ (منخفضة، مُصلحة)

**الملفات**: `service/.env.example` و `service/.env` و `docker-compose.yml`

`JWT_SECRET_KEY` سلسلة فارغة، رغم أن وسيطة JWT لديها سلسلة تراجع `JWT_SECRET → JWT_SECRET_KEY` (تعطي الأولوية لـ `JWT_SECRET`)، لكن قيمة العلامة النائبة غير آمنة.

**الإصلاح**: توليد مفتاح base64 من 32 بايت وتعيين `JWT_SECRET` و `JWT_SECRET_KEY` معًا. تحديث `.env.example` و `.env` و `docker-compose.yml`.

## 5. مشكلات قيد الملاحظة (نقاط تحسين محتملة)

### 5.1 اعتماد SecurityGuard على ترويسات webman/Workerman (مخاطر منخفضة)

**التأثير**: تعتمد كواشف CSRF Origin وHost Header وDNS Rebinding وRequest Smuggling وCORS على بيانات HTTP في `$_SERVER`.

في بيئة Workerman غير CGI، قد لا يمتلئ `$_SERVER` بترويسات HTTP كاملة. لدى SecurityGuard منطق احتياطي (مثل تخطي الكشف عند خلو قيمة الترويسة)، لذلك **لن تحدث إنذارات كاذبة**، لكن قد **يُغفل بعض هجمات الترويسات**. التأثير منخفض لأن طبقة Nginx الوكيل العكسي ترشح عادةً الترويسات الخبيثة.

**الاقتراح**: إذا لزم فحص ترويسات أكثر اكتمالًا، يمكن تمرير قيم الترويسات صراحةً في معامل `$meta` الخاص بـ SecurityGuard. لا حاجة لتغيير حاليًا.

### 5.2 تأثير كاشف CSRF Origin على Admin (لا مخاطر)

كاشف `csrf_origin` في Admin بوضع `block` و`allowed_origins` فارغ. لكن الكاشف يُفعَّل فقط عند وجود ترويسة Origin وعدم تطابقها مع Host؛ عادةً لا توجد ترويسة Origin عند الوصول للوحة الإدارة (وصول من نفس الأصل)، لذلك **لن يكون هناك اعتراض خاطئ**.

### 5.3 تفعيل الكواشف الـ31 جميعها، الحمل لكل طلب (ملاحظة أداء)

تنفَّذ جميع الكواشف الـ31 لكل طلب (بما فيها JWT وWebSocket وGraphQL وCSV وprototype pollution وغيرها). ينفذ كل كاشف مطابقة تعبيرات نمطية على جميع حقول الطلب. بالنسبة لسيناريو استخدام هذا المشروع، الحمل ضمن الحدود المقبولة (webman عملية دائمة الذاكرة، لا حمل إقلاع CGI بارد).

### 5.4 استمرار القائمة السوداء IP (ملاحظة تشغيلية)

خلفية التخزين بوضع `file`، المسار الافتراضي `sys_get_temp_dir() . '/security_storage.json'`. في حاويات Docker قد يفقد الدليل المؤقت بعد إعادة التشغيل. إذا لزمت مشاركة القائمة السوداء في نشر متعدد الحاويات، يمكن التبديل إلى وضع `redis`.

## 6. ملخص الملفات المتغيرة

```
admin/.env.example                                (أُضيف ENCRYPTION_KEY)
admin/.env                                        (أُنشئ من .env.example)
admin/CLAUDE.md                                   (تحديث مكدس الوسائط + حزمة التقنيات)
admin/composer.json                               (اعتماد security-php)
admin/config/plugin/erikwang2013/encryptable/app.php  (توحيد القيم الافتراضية)
admin/config/plugin/erikwang2013/security-php/app.php  (جديد، 31 كاشفًا)
admin/app/middleware/SecurityMiddleware.php       (إعادة كتابة لاستخدام SecurityGuard)
service/.env.example                              (تحديث ENCRYPTION_KEY/CIPHER + مفتاح JWT)
service/.env                                      (أُنشئ من .env.example، مزامنة مفتاح JWT)
service/CLAUDE.md                                 (تحديث مكدس الوسائط + Encryption + حزمة التقنيات)
service/composer.json                             (اعتماد security-php)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (جديد، 31 كاشفًا)
service/app/common/Encryption.php                 (أُعيدت تسميته من EncryptionHelper.php)
service/app/middleware/EncryptionMiddleware.php   (جديد، تشفير/فك تشفير استجابات API)
service/app/middleware/SecurityMiddleware.php     (إعادة كتابة لاستخدام SecurityGuard + رفع الملفات)
docker-compose.yml                                (إكمال متغيرات بيئة encryption/jwt)
docs/security-review.md                           (هذا التقرير)
```

## 7. الخلاصة

**الحالة**: ناجح

- كشف WAF يعترض صحيحًا XSS وحقن SQL وغيرها من الهجمات (31 كاشفًا، واجهة SecurityGuard::guard API)
- إعداد تشفير الحقول الحساسة مكتمل (31 نموذجًا، 6 فئات بيانات حساسة، Encryptable trait)
- توصيل تشفير/فك تشفير نقل API بالوسائط (EncryptionMiddleware, AES-256-CBC, تفعيل عبر الترويسة)
- مفتاح JWT مُعيَّن (JWT_SECRET + JWT_SECRET_KEY كلاهما مُعيَّن)
- إصلاح كشف رفع الملفات (دمج بيانات $_FILES وتمريرها إلى SecurityGuard)
- لا تراجع وظيفي (22/22 اختبارًا ناجحًا)
- لا كشف مكرر في الوسائط
- متغيرات بيئة نشر Docker مكتملة
