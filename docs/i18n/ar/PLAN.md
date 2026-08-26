# خطة مشروع Erik Shop (إنتاج الفريق)

> **تاريخ الإنشاء**: 2026-08
> **طريقة الإنشاء**: تعاون فريق متعدد الوكلاء (7 مجالات بحث متوازٍ → دمج مهندس البنية → مراجعة مهندس المراجعة)
> **الاستناد**: `docs/PLAN-RESEARCH.md` (تفاصيل بحث 7 مجالات)، `README.md`، `CLAUDE.md` لكل مشروع فرعي
> **فترة التطبيق**: 3-6 أشهر (4 مراحل)
> **سجل المراجعة**: 2026-08 راجع مهندس المراجعة 18 ادعاءً مقابل الكود (16 صحيحة، 2 صحيحة جزئيًا بسبب إصلاحات مساحة العمل)؛ هذه النسخة تتضمن تعديلات المراجعة (واجهة إصدار PosterVerify، منفذ مراجعة إدارة المخاطر، مسار Flutter، وضع العلامات على حالة التنفيذ وغيرها).

## 0. حالة التنفيذ الحالية (تم التحقق عند المراجعة)

> الاستناد إلى `git status`/`git diff` للتحقق الفعلي؛ ✅ = مكتمل (غير ملتزم به في مساحة العمل)، 🔄 = قيد التنفيذ، ⬜ = لم يبدأ.

| البند | الحالة | الشرح |
|---|---|---|
| إصلاح توقيع المتحكمين القاتلين في admin (ShopOrder/ShopPayment أُضيف `: array`/`: Response`) | ✅ | بعد الإصلاح نجح التحميل الانعكاسي لـ 82/82 متحكمًا (قبل الإصلاح 2 Fatal) |
| بوابة PHPStan | ✅ | `make check` الفعلي `[OK] No errors`؛ PHPStan 2.2.8 أزال معامل neon `memoryLimit`، يُمرر الآن عبر Makefile/CI بـ `--memory-limit=1G` |
| توقيع json في ShopDashboardController + URL جلب العرض | ✅ | `$this->json(0,'ok',$data)` + توجيه باسم الفئة `/ShopDashboard/kpi` |
| CI أضاف composer audit + phpstan | ✅ | `.github/workflows/ci.yml` خطوتان جديدتان (تم التحقق من YAML) |
| `scripts/smoke_controllers.php` اختبار دخاني لمنع التكرار | 🔄 | انظر مخرجات المرحلة الأولى |
| واجهة إصدار PosterVerify (`POST /api/poster/verify`) | ✅ | اكتشاف جديد من المراجعة + تم التنفيذ: `PosterController` (مسألة حسابية math) + توجيه؛ اختبر المسار الكامل فعليًا على منفذ 8789 (challenge→verify→تجاوز الوسيطة→استهلاك لمرة واحدة) |
| 🔄 اكتشاف P0 جديد: IV فارغ في Encryptable يعطل التسجيل | ✅ | تم الإصلاح: `app/common/SecureEncrypter.php` (IV صريح 16 بايت صفري، متوافق بايتًا بايتًا مع البيانات القديمة) + تسجيل resolver في `support/bootstrap.php`؛ اختُبر فعليًا نجاح التسجيل وفك تشفير تسجيل الدخول بشكل طبيعي |
| 🔄 اكتشاف P0 جديد: الحقول المشفرة غير قابلة للاستعلام (email) | ✅ | تم الإصلاح: `erik_users.email_hash` (عمود فهرسي HMAC-SHA256، install.sql + ALTER + تعبئة رجعية)؛ AuthController register/login و SocialAuthController يستخدمان استعلام email_hash؛ اختُبر فعليًا: تسجيل ناجح/تسجيل مكرر 422/دخول ناجح/كلمة مرور خاطئة 401 |
| 🔄 اكتشاف P0 جديد: HASHIDS_SALT placeholder/غير مقروء | ✅ | تم الإصلاح: `config/hashids.php` main.salt يقرأ `getenv('HASHIDS_SALT')`؛ `.env` في هذه البيئة يولّد salt عشوائيًا (كان في الأصل placeholder change_me، والإعداد كان salt فارغًا مكتوبًا يسبب استثناء fail-closed) |
| Quick Win #3: استيراد تلقائي للبيانات الأولية للأعمال | ✅ | جديد `service/database/seeders/run.php` (قوة تكرار: countries 23 + logistics 3 + shipping zones 3 + rates 3 + gateways 2 + methods 2 + hs_codes 8 + tariff_rules 7)؛ اختُبر فعليًا إعادة التشغيل 0 جديد و51 تخطيًا؛ /api/countries و /api/payment/methods و /api/shipping/calculate (DHL منطقة أمريكا الشمالية 12.24) و /api/tariff/estimate كلها متاحة |
| 🔄 اكتشاف جديد: encryptable خاطئ في النماذج (حقول name غير حساسة) | ✅ | أكثر من 30 نموذجًا تشفر حقول name العامة: يكسر البحث/الفرز بالاسم والنصوص القصيرة لا تسع النص المشفر. نُظف الكل: 4 نماذج للبيانات الأولية (الجولة السابقة) + 17 نموذجًا بالجملة (Categories/Currencies/Shops/Suppliers.name/Merchants.store_name وغيرها)، مع الإبقاء على الحقول الحساسة الحقيقية email/mobile/real_name/api_key/access_token |
| 🔄 اكتشاف جديد: النماذج تنقصها علاقات Eloquent | ✅ | PaymentGatewayMethods.gateway و ShippingZoneRates.logistics/zone المفقودة تسبب 500 في /api/payment/methods و /api/shipping/calculate، أُضيفت |
| المرحلة الأولى: الفوترة الحقيقية في OrderController | ✅ | store() ربطت قسائم الخصم (خصم بالحد الأدنى/نسبة/ثابت، إلغاء تنشيط user_coupons + used_qty) والشحن (المناطق + السعر المتدرج الأدنى) والرسوم الجمركية/VAT (HS Code→ضريبة الدولة الوجهة)؛ اختُبر فعليًا 3×49.99=149.97 خصم 20 عند 100+ → discount 20 + shipping 12.24 + tax 0 = pay 142.21، مع التحقق من السلسلة الكاملة مخزون/إلغاء تنشيط/تفاصيل/سجلات |
| 🔄 اكتشاف P0 جديد: فقدان معاملات HashidsDecode | ✅ | setPost($updates) للوسيطة كان استبدالًا كليًا، فك تشفير أي حقل _id يتجاهل معاملات أخرى في نفس الطلب (coupon_id/weight_grams وغيرها متأثرة في كل الموقع)؛ عُدل إلى array_merge، اختُبر فعليًا طلب متعدد المعاملات يعمل |
| 🔄 اكتشاف جديد: أخطاء مصاحبة لسلسلة الطلب | ✅ | CouponController::claim حيث اسم العمود مكتوب كقيمة (عُدل إلى whereColumn)؛ Orders.address_snapshot عمود JSON ينقصه cast (أُضيف array cast)؛ جدول OrderLogs بلا updated_at (النموذج $timestamps=false) |
| المرحلة الأولى: InstallController يدمج seeder | ✅ | معالج التثبيت بعد استيراد install.sql ينفذ تلقائيًا service/database/seeders/run.php (عملية فرعية معزولة للتحميل التلقائي، الفشل إنذار فقط)؛ أُصلح أيضًا خطأ مسار install.sql (كان base_path(false) يشير إلى admin/ ولا يجد install.sql في جذر الدليل الأعلى، عُدل إلى dirname) |
| المرحلة الأولى: ربط الطلب والدفع في HarmonyOS | ✅ | Checkout.ets ربط اختيار العنوان و PosterVerify (challenge→verify) ومعاملات الطلب الكاملة + X-Poster-Token وبدء الدفع (payment/create)؛ ApiClient يوسع headers/المعاملات؛ **hvigor assembleHap ترجمة ناجحة** |
| المرحلة الأولى: ربط الطلب والدفع في Flutter | ⚠️ مبرمج بانتظار التحقق من الترجمة | checkout_screen ربط العنوان/التحقق البشري/الطلب الكامل/بدء الدفع؛ register_screen ربط PosterVerify؛ api_client post يدعم headers. **ذاكرة التخزين المؤقت لـ flutter SDK في هذه البيئة للقراءة فقط لا تسمح بالترجمة**، يلزم تحقق محلي عبر `flutter analyze`/`flutter test` (الفحص الساكن للأقواس/البنية اجتاز) |
| المرحلة الثانية P1: محرك إدارة المخاطر RiskEngine | ✅ | جديد `app/common/RiskEngine.php` (email_domain بريد مؤقت/velocity تردد/amount مبلغ كبير/address_mismatch/ip_reputation، عدّاد Redis)؛ ربط التقييم المساعد في الطلب/التسجيل/الدفع + RiskLogs؛ **اختبار فعلي**: بريد مؤقت + طلب كبير → 80 نقطة review → الطلب status=8 قيد المراجعة، مع علامات risk_score/risk_result/OrderLog كاملة |
| المرحلة الثانية P1: منفذ مراجعة إدارة المخاطر | ✅ | جديد `POST /api/admin/orders/{id}/review` (حماية AdminKeyMiddleware؛ approve→0 إفراج/reject→5 رفض، status=8 انتقال ذري + OrderLogs)؛ **اختبار فعلي**: approve/reject/مفتاح خاطئ 403/مراجعة مكررة 422 كلها صحيحة |
| المرحلة الثانية P1: إغلاق حلقة KYC من جهة المستخدم | ✅ | جديد KycController (POST /api/kyc إرسال + GET /api/kyc/status استعلام، real_name/id_number مشفران بـ Encryptable، status 0 قيد المراجعة/1 مقبول/2 مرفوض)؛ **اختبار فعلي**: الإرسال/الاستعلام طبيعيان |
| المرحلة الثانية P1: تقوية الدفع | ✅ | StripeGateway يحدد صراحةً `request_three_d_secure=automatic` (3DS)؛ Klarna/Adyen يبقيان `PaymentGateway::make` throw placeholder (تصحيح التوثيق معلق) |
| 🔴 **خطأ عام جديد: فك HashidsDecode لمعاملات التوجيه غير فعّال** | ✅ | معاملات دوال المتحكم في webman تأتي من hashid الأصلي الملتقط في findRoute (setParams للوسيطة لا يعمل). أُصلح موحدًا: helper `BaseApiController::decodedId()` + دخول 17 موضعًا من دوال توجيه {id} (الطلبات/المنتجات/سلة التسوق/العناوين/قائمة الرغبات/التقييمات/حالة الدفع/الإرجاع/القسائم/الإشعارات/المقارنة/تنفيذ الاسترداد/المراجعة)؛ **اختبار فعلي**: تفاصيل الطلب/تفاصيل المنتج/إلغاء الطلب/سلة التسوق update/destroy/استلام القسيمة (مسار hashid) كلها ناجحة؛ أُصلح أيضًا علاقات Orders الناقصة items/logs/documents وعلاقة sku الناقصة في Carts |
| المرحلة الثانية P1: توحيد نسبة التسوية + تسوية البائع | ✅ | مصدر نسبة SettlementCron موحد إلى `payment.gateway_fee.{gateway}` + `payment.platform_rate` (نفس مصدر webhook، cron.* توافق رجعي فقط)؛ جديد كتابة MerchantSettlements (order_items→MerchantProducts approved→merchant.commission_rate)؛ **اختبار فعلي**: طلب 162.21 → عمولة المنصة 5% = 8.11 + رسوم بوابة stripe 5.00، البائع 149.97@8% → عمولة 12.00 تسوية 137.97 |
| المرحلة الثانية P1: إكمال خطوط التسوية الأربعة (Supplier/Affiliate) | ✅ | اكتمال الـ schema: `erik_products.supplier_id` + `erik_orders.affiliate_link_id` (install.sql + ALTER)؛ SettlementCron أضاف تسوية دورية للموردين (upsert شهري لـ SupplierSettlements) + عمولة التوزيع (AffiliateCommissions + عدّاد AffiliateLinks)؛ **اختبار فعلي**: منتج 99.98 يُسوّى للمورد في نفس الشهر، 112.22@10% → عمولة توزيع 11.22 مع تحديث orders/commission للرابط؛ جدول AffiliateCommissions بلا updated_at أُضيف `$timestamps=false` |
| المرحلة الثانية P1: التحقق من قائمة الجداول ثنائية المصدر في InstallController | ✅ | جديد `scripts/check_install_tables.php` (يحلل أسماء الجداول في install.sql مقابل $tables_to_install في InstallController، جداول wa_ للإضافات معفاة)، ربط بـ Makefile check؛ **اختبار فعلي** 110 مقابل 110 متطابقة OK |
| المرحلة الثانية P1: طبقة تنفيذ GDPR/CCPA | ✅ | جديد `PrivacyComplianceTask` (كل ساعة): بعد فترة السماح لـ data_delete تتم إخفاء هوية المستخدم (مسح email/email_hash/mobile، اللقب "مستخدم محذوف"، status=0، الحقول الضريبية محفوظة)؛ data_access/data_portability يولّدان ملف تصدير JSON؛ opt_out يُعلَّم؛ جديد `POST /api/privacy/cookie-consent` (كتابة CookieConsents، version/preferences JSON)؛ **اختبار فعلي**: طلب data_delete قبل 31 يومًا → إخفاء هوية المستخدم + request completed؛ سجل cookie-consent كامل |
| المرحلة الثانية P1: تصحيح توثيق Klarna/Adyen | ✅ | README.md (سطر الدفع/الخصم بالعملة الأصلية/جدول الميزات) و docs/VERSIONS.md يعلمان Klarna/Adyen/BNPL كـ placeholder، متسق مع `PaymentGateway::make` throw الفعلي |
| المرحلة الرابعة P2: دفتر المخزون غير القابل للتغيير | ✅ | `InventoryLogger` ربط خصم الطلب (outbound)/استعادة الإلغاء (inbound)، يكتب erik_inventory_logs (لقطة balance_after)؛ **اختبار فعلي**: طلب -2/إلغاء +2 سلسلة كاملة |
| المرحلة الرابعة P2: PDF الفاتورة التجارية/قائمة التعبئة | ✅ | إعادة كتابة DocumentController: dompdf يولّد PDF عند الطلب (التفاصيل + المبالغ + إقرار جمركي) في public/documents/ + erik_order_documents (قوة تكرار)؛ إصلاح عدم تطابق اسم المعامل مع {id} في التوجيه؛ **اختبار فعلي** توليد PDF كليهما بنجاح |
| المرحلة الثالثة P1: بوابة جودة admin | ✅ | admin/phpunit.xml + tests/UtilTest.php (2/7 ناجحة) و phpstan.neon (level 5) و .php-cs-fixer.php (إصلاح تعليق fix) و composer يضيف phpstan و CI يضيف خطوة admin و Makefile test لمشروعين |
| المرحلة الرابعة P2: فصل القراءة/الكتابة في DB | ✅ | 6 نماذج استعلام خالصة تفعّل `$connection='mysql_rw'` (Eloquent توزيع تلقائي للقراءة/الكتابة + sticky)؛ **اختبار فعلي** اتصال الاستعلام=mysql_rw والكتابة طبيعية؛ في الإنتاج DB_READ_HOST_1/2 سارٍ |
| المرحلة الرابعة P2: API الاشتراك الدوري | ✅ | SubscriptionController (إنشاء اشتراك + طلب الدفعة الأولى، اشتراكاتي، إلغاء)؛ **اختبار فعلي** إنشاء/قائمة/إلغاء كلها ناجحة؛ SubscriptionOrders/Logs أُضيف `$timestamps=false` |
| المرحلة الرابعة P2: كتابة الإدراج متعدد المنصات | ✅ | `POST /api/admin/platform/listings` (حماية AdminKeyMiddleware، upsert لـ PlatformListings draft/listed)؛ **اختبار فعلي** كتابة سجل الإدراج بنجاح |
| المرحلة الرابعة P2: SubscriptionCron التجديد التلقائي | ✅ | `service/app/process/SubscriptionCron.php` (يوميًا): اشتراكات منتهية → معاملة تولّد طلب تجديد/عدد الدورات +1 → تحديث next_billing → سجل؛ SKU مُزال/مخزون غير كافٍ → paused؛ **اختبار فعلي** اختبار دخاني 7 تأكيدات كلها ناجحة |
| المرحلة الرابعة P2: WS دعم العملاء IM فوري | ✅ | `ChatController` (REST جلسات/رسائل) + `ChatWs` (WebSocket 8788، JWT + مصادقة ملكية الجلسة، كتابة ثنائية القناة من نفس المصدر)؛ **اختبار فعلي** 5 بنود شاملة (مصافحة/بث/إدراج/رمز غير صالح/رفض جلسات الآخرين)؛ معروف: لا مصادقة لطرف الدعم، إجراء إغلاق الجلسة غير منفذ |
| المرحلة الرابعة P2: بحث ES متعدد اللغات | ✅ | webman-scout hosts يقرأ env `ELASTICSEARCH_HOST`؛ `toSearchableArray()` في Products حقول متعددة اللغات + `scripts/es-index-products.php` فهرس جماعي؛ عند غياب الإعداد ترقية SQL؛ **اختبار فعلي** مسار الترقية/شكل البيانات (لا خدمة ES، استعلامات الإنتاج غير مختبرة) |
| المرحلة الرابعة P2: هيكل دفع Klarna/Adyen | ✅ | `KlarnaGateway/AdyenGateway` (اتصال مباشر Guzzle: إنشاء/استعلام/استرداد/التحقق من توقيع Webhook HMAC)، نقص المفاتيح يرمي استثناءً يحدد env؛ استُخرج `PaymentGatewayInterface`؛ **اختبار فعلي** خوارزمية التحقق من التوقيع ثنائية الاتجاه + phpstan/phpunit كلها ناجحة؛ قابل للاستخدام بعد ربط مفاتيح حقيقية |
| المرحلة الرابعة P2: تحويل 3 URLs في cron إلى env | ✅ | `config/cron.php` ثلاثة *_url تقرأ env (TRACKING/COMPLIANCE/PLATFORM_URL)؛ منطق السحب في cron الثلاثة مكتمل؛ غير متصل بـ API خارجي حقيقي |
| المرحلة الرابعة P2: KeyStore في HarmonyOS + AES للعميل + صفحة إتمام الدفع | ✅ | `SecureStore.ets` في HarmonyOS (Asset Kit بديلاً عن preferences) + `SecureCrypto.ets`/`_SecureCrypto` في Flutter/هارموني (AES-256-CBC، X-Encrypted/X-Encrypt-Response، مفتاح فارغ → نص عادي) + صفحتا إتمام الدفع في الطرفين؛ **لم يُتحقق من الترجمة** (لا أدوات)، بانتظار `flutter pub get`/ترجمة hvigor |
| تقارب التوثيق | ✅ | README/VERSIONS/admin-CLAUDE.md تصحيح 8 ادعاءات مبالغ فيها (HS إقرار→قيد التخطيط، أعمدة تصدير الطلبات حسب الفعلي، زر تبديل i18n→قيد التخطيط وغيرها)؛ قائمة التعبئة/تتبع المسار مؤكدا التنفيذ ومحفوظان؛ 7 بنود في VERSIONS.md (AB/شراء/فحص الجودة/تحويل/تأمين/قاعدة المعرفة/النقاط) معلّمة "بُنيت بنية الجدول" (◐)، متسقة مع الكود الفعلي (جدول + نموذج فقط، بلا كود أعمال) |
| الجولة الثانية: إبطال JWT + إعادة تعيين كلمة المرور + التحقق من البريد | ✅ | Jwt أضاف `revoke()`/`isRevoked()` (قائمة حظر Redis)، تتحقق منها وسيطة JwtAuth؛ AuthController logout/changePassword/passwordReset/emailVerify + توجيه؛ install.sql أضاف `email_verified_at`؛ اختبار وحدة JwtTest ناجح |
| الجولة الثانية: الاسترداد الجزئي + إكمال أحداث webhook | ✅ | RefundHelper يدعم التحقق من مبالغ الاسترداد الجزئي؛ AdminOpsController::executeRefund؛ PaymentController توزيع أحداث webhook (refunded/failed)؛ RefundHelperTest ناجح |
| الجولة الثانية: تقارب DevOps | ✅ | docker-compose توحيد المنافذ إلى 127.0.0.1، .dockerignore ×2، .gitignore منتجات بناء HarmonyOS، CI يضيف jobs لـ Flutter/hvigor، سكربت download-geoip.php |
| الجولة الثانية: اختبار التكامل + واجهة P0 في admin | ✅ | IntegrationTestCase (تخطي حسب توفر MySQL + مكتبة اختبار افتراضية تُمسح في كل حالة) + اختبارات OrderFlow/StripeWebhook/Hashids (phpunit 40 tests / 155 assertions كلها خضراء)؛ إصلاح تهيئة نموذجي ShopOrder/ShopPayment؛ صفحتا قائمة LayUI للطلبات/الدفع في admin |
| 🔴 خطأ جديد: كتابة تسوية webhook معطلة بعمود NOT NULL | ✅ | PaymentController::handlePaymentSucceeded كان ينقص PlatformSettlements::create حقلَي supplier_amount/affiliate_amount (schema NOT NULL بلا قيمة افتراضية → webhook 500 دائمًا)؛ أُضيف حساب max(0, الإجمالي-رسوم المنصة-رسوم البوابة) (نفس مصدر SettlementCron)؛ اختبار تكامل StripeWebhook 5/5 ناجح |
| الجولة الثالثة: إغلاق حلقة طلب الاسترداد | ✅ | RefundController (POST /api/refunds طلب + قائمة/تفاصيل، الرصيد القابل للاسترداد = المدفوع-المسترد-قيد المراجعة) + AdminOps approve (مزلاج ذري 0→3 + تكامل RefundHelper)/reject (0→2)؛ دلالات حالة Refunds حسب schema: 0 قيد المراجعة/2 مرفوض/3 مسترد؛ اختبار تكامل RefundFlow 3/34 |
| الجولة الثالثة: إكمال WS دعم العملاء | ✅ | مصادقة طرف الدعم في ChatWs (الإطار الأول {type:'auth',role:'agent',key} + مقارنة زمنية ثابتة hash_equals، المصافحة بدور pending) + إغلاق الجلسة (REST close/adminClose + إطار WS close، closed يعترض REST 409/WS error، closeSession قوة تكرار + بث)؛ اختبار ChatWs 5/21 |
| الجولة الثالثة: صفحات الإدارة الأساسية في admin | ✅ | 5 صفحات منتجات/مستخدمين/استردادات/قسائم/تصنيفات (LayUI موازية لـ order/payment، قائمة+ترقيم+بحث+فلتر حالة+نافذة مراجعة)؛ 3 إصلاحات جذرية في Crud.php (doFormat items() يُغلّف مرة أخرى في Collection ليغطي خطأ latent مطابق في ShopOrder/ShopReturn، instantiate نماذج string، اشتقاق مسار العرض) + تجميع المخزون في ShopProduct afterQuery؛ جديد ShopUserController |
| الجولة الثالثة: تثبيت ضمان الجودة | ✅ | اختبارات SubscriptionCron (طلب تجديد/billing_cycle+1/تمديد next_billing/نقص المخزون والإزالة paused) + ترقية ES (SQL LIKE + تسجيل SearchLogs)؛ 🔴 اكتشاف وإصلاح جديد: SearchLogs ينقصه $timestamps=false → كتابة سجل البحث SQLSTATE 1054 500؛ المجموعة كاملة 54 tests / 256 assertions 0 فشل |
| الجولة الرابعة: إصلاح حدود الإدخال | ✅ | BaseApiController::clampPage (page≥1 / perPage∈[1,50]) موحد عبر 8 متحكمات (Order/B2b/PriceAlert/Affiliate/Privacy/Notification/Return/Review، Search عُدل منفردًا في fix-search)؛ AdminOps reason/remark ≤500 + createListing intval؛ تغطية قيم json_decode الفارغة في 5 مواضع (SocialAuth×3/ExchangeRateCron/ComplianceCron)؛ حذف 4 استيرادات غير مستخدمة فعلاً (بقية 11 في عمود المراجعة أُثبت استخدامها عبر grep) |
| الجولة الرابعة: حماية حقن البحث | ✅ | SearchController: preg_replace يهرب الأحرف الخاصة في Lucene (منع حقن صيغة ES DoS) + keyword >64 → 422 + `%`/`_` في LIKE عبر addcslashes + ضبط per_page؛ diff من 24 سطرًا |
| الجولة الرابعة: صحة DevOps | ✅ | مزامنة admin composer.lock (إدخال phpstan) + تحديث `--lock` في service؛ ci.yml audit عُدل إلى نسخة متينة "تسمح فقط بـ CVE-2025-45769" (إبقاء كود الخروج، اختبار فعلي تنسيق الإخراج مطابق) + workflow_dispatch؛ حذف بادئة autoload `""` الفارغة ×2 وإضافة 5 بادئات صريحة (تحقق dump-autoload)؛ إكمال 35 ترويسة Copyright؛ LICENSE يعلن proprietary (الإبقاء على نص webman MIT الأصلي)؛ dockerignore يضيف tests/docs؛ حارس مفاتيح placeholder في compose (production + change_me → exit 1، اختبار فعلي 3 فروع)؛ **تخطي**: خطوة cs-fixer في CI (238/247 ملفًا غير متوافق، يتطلب التنسيق أولاً) و admin audit (25 تحذيرًا قائمًا مسبقًا، يتطلب ترقية التبعيات) |
| الجولة الرابعة: اتساق التوثيق/الفهرس | ✅ | VERSIONS 7 بنود ✅→◐ (اختبار فعلي جدول + نموذج فقط) + جدول الأحجام (Cron 11، فئات أدوات 15، اختبارات 54/256)؛ api.md أضاف DELETE /api/comparisons/{id}؛ payment.php أضاف نسبة adyen 2.99/0.30؛ install.sql أضاف 6 فهارس (refunds/return_orders idx_user_id، platform_listings idx_account_product، group_buys/flash_sales/coupons idx_status_time) + scripts/index-fixes.sql (لم يُنفذ، للقواعد المخزنة)؛ 🔴 معلق: في service/CLAUDE.md عدادات فئات الأدوات 8→15 و PHPUnit 22→54 منتهية الصلاحية |
| الجولة الرابعة: تقوية الأمان | ✅ | BaseModel `$guarded=['id','money','score','level','created_at','updated_at']` (القائمة الأصلية في المراجعة شملت 6 أعمدة مثل user_id/status، grep أثبت أن 40+ موضع create() بإسناد جماعي → الإغلاق الكامل يفسد البيانات، نُفذ وفق الحد الأدنى القابل للإتلاف)؛ 5 صفحات في admin table.render أضيف `escape: true`؛ UploadController قائمة سوداء → قائمة بيضاء 19 امتدادًا؛ InstallController تحقق مزدوج (ملف الإعداد + علامة wa_options installed=1، DB غير قابل للوصول fail-closed)؛ 🔴 بلاغ إضافي عن خطأ قائم: عمود المخزون في product/index.html templet بلا return يعرض undefined |
| الجولة الرابعة: تقوية الاختبارات | ✅ | اختبارات تكامل SubscriptionController 4/33 (التحقق من الدورة/تجاوز الصلاحية/قوة تكرار الإلغاء) + Kyc 6/27 (فك تشفير Encryptable وإعادة الإرسال بعد الرفض/منع إعادة الإرسال بعد القبول) + RiskEngine 6/22 (بريد مؤقت/مبلغ كبير/عدم تطابق العنوان/velocity/ip_reputation)؛ إعادة تسمية طرق اختبار Kyc لتجنب خطأ PHPUnit 12 final status() القاتل؛ المجموعة كاملة 70 tests / 338 assertions 0 فشل (تحذير vendor واحد قائم: encryptable IV فارغ) |
| الجولة الخامسة: بنية أقفال التزامن | ✅ | جديد app/common/DistributedLock.php (Redis SET NX EX قفل دوراني، إطلاق ذري عبر Lua يحذف القفل المملوك فقط، fail-closed: استثناء Redis لا يعرّي؛ مسار واحد للمضيف الواحد/الموزع)؛ ربط webman/redis-queue v2.1.1 (db=2 prefix=erik_queue:، عمليات الاستهلاك count=8، consumer_dir=app/queue/redis)؛ سكربتات التحقق الخمسة للمكوّن كلها ناجحة (تنافس عمليتين/انتهاء المهلة/منع الحذف الخاطئ) |
| الجولة الخامسة: قفل عمليات الكتابة | ✅ | منع تكرار الطلب lock:order:{userId} (عملية store في OrderController داخل القفل كاملًا، انتهاء القفل 429/استثناء الأعمال 422)؛ قوة تكرار الدفع lock:payment:{orderId} (استعلام داخل القفل عن سجل قيد الدفع، إن وُجد يُعاد مباشرة، منع سجلات دفع معلقة مكررة)؛ طلب الاسترداد lock:refund:{orderId} (إعادة استعلام داخل القفل للطلب + الرصيد القابل للاسترداد، منع طلبات متزامنة زائدة)؛ اشتراك store/cancel و is_default في العناوين (مسح أولاً ثم تعيين) وربط تسجيل الدخول الاجتماعي والمفضلة وإضافة إلى السلة (قراءة-تعديل-كتابة) والتقييم (لا فهرس فريد، القفل خط الدفاع الوحيد) والتسجيل (email_hash غير UNIQUE) كلها تُقفل حسب السيناريو؛ استفسار B2b إضافة خالصة لا يحتاج قفلًا |
| الجولة الخامسة: توليد PDF غير متزامن | ✅ | DocumentController عُدل إلى دفع إلى قائمة الانتظار مع إرجاع processing فورًا؛ DocumentPdfConsumer (app/queue/redis/، قائمة document_pdf، payload order_id/type/user_id، منطق dompdf الأصلي منقول كاملًا داخل الاستهلاك، قوة تكرار في الكتابة، الفشل يُسجل دون إعادة محاولة — إعادة طلب المستخدم هي إعادة المحاولة الطبيعية)؛ الحالة: سجل موجود وملف موجود=done، وإلا processing |
| المخرجات المتبقية | ⬜ | المتبقي: ربط SDK الدفع الحقيقي عبر الإنترنت (يتطلب مفاتيح)، تحقق ES عبر الإنترنت (لا خدمة ES)، تحقق ترجمة Flutter/هارموني (لا أدوات)، تحقق التخزين الآمن لهارموني على الجهاز الحقيقي، خطوة CI بعد تنسيق cs-fixer، خطوة audit بعد ترقية تبعيات admin، تحقق شامل للـ PDF غير المتزامن (يتطلب تشغيل عملية القائمة) |

---

## 1. الحكم العام

بنية Erik Shop الأساسية متينة (117 جدولًا، 39 متحكمًا، بوابات Stripe/PayPal حقيقية، مجموعة أمان WAF/JWT/AES، 22 اختبار وحدة ناجحة)، لكن السلسلة الرئيسية للمعاملات الأساسية مكسورة في الأطراف الأربعة service/admin/Flutter/هارموني في نفس الوقت، ونحو عشرة بنود تدّعي الوثائق أنها "كاملة" هي في الحقيقة بنية جدول أو أعمدة CRUD، وبوابات الجودة (PHPStan/اختبار التكامل/CI العملاء) شكلية — الوضع العام **"الهيكل مكتمل، الحلقات مفقودة، الوثائق متقدمة"**. خلال 3-6 أشهر يجب أولًا إيقاف النزيف وفتح حلقة المعاملات، ثم إكمال قاعدة الامتثال والجودة، وأخيرًا توسيع القدرات الإضافية وتقارب الوثائق.

## 2. خمس مشكلات عامة

1. **السلسلة الرئيسية للمعاملات الأساسية مكسورة في الأطراف الثلاثة** (تأكيد متقاطع عبر service/Admin/العميلين): `OrderController::store` في جهة service لا يحسب القسائم/الشحن/الرسوم الجمركية/إدارة المخاطر (يجمّع إجمالي المنتجات فقط)؛ طلب Flutter و هارموني كلاهما ينقصه `address_id` ومرفوض بـ 40001 من PosterVerify، والدفع لم يُستدعَ أبدًا عبر `POST /payment/create`؛ على جهة admin، `ShopOrderController`/`ShopPaymentController` يفشلان في تحميل الفئة بسبب عدم توافق توقيع الدالة في PHP 8.3. عند الإطلاق الحالي مسار الشراء الكامل غير قابل للاستخدام، وقوائم إدارة الطلبات/الدفع تنهار بمجرد فتحها.
2. **الوثائق متقدمة منهجيًا على الكود** (تأكيد موحد عبر مجالات التوثيق/الخدمة/الأمان/الامتثال): `features.md`/`VERSIONS.md`/`README` تعلم محرك إدارة المخاطر (RiskEngine) ودفع Klarna/Adyen والتسوية الرباعية والفاتورة التجارية PDF والاشتراك الدوري/AB و WebSocket خدمة عملاء IM وإدراج المنتجات متعدد المنصات كلها "كاملة/✅"، والواقع جدول + CRUD في admin أو صفر تنفيذ أعمال، ما يشكل مخاطرة في توقعات التسليم والثقة للعملاء التجاريين.
3. **غياب البيانات الأولية للأعمال + فراغ طبقة تنفيذ الأمان/الامتثال** (دليل مشترك عبر service/النشر/الامتثال): `install.sql` يحتوي بيانات أولية للجداول النظامية فقط، countries/currencies/payment_gateway_methods/hs_codes/shipping_zones كلها فارغة بعد تثبيت جديد (الواجهات الأساسية تعيد فارغًا فورًا)؛ في الوقت نفسه `blocked_countries` مصفوفة فارغة افتراضيًا، صفر استدعاء لإدارة المخاطر، KYC بلا مدخل إرسال، GDPR/CCPA تسجيل فقط دون تنفيذ — "فارغ فورًا + إفراج افتراضي" مضافًا إليه ادعاء امتثال غير حقيقي.
4. **طبقة الأعمال في خلفية Admin "متحكمات بلا صفحات"**: 59/67 أعمدة CRUD خالصة بلا عرض HTML، نقر القائمة 404؛ مسارات kpi/chartData في لوحة العبر الحدودية وتوقيع json تالفان معًا؛ 40 متحكمًا غير معلق على القائمة، واجهة إدارة المتجر كاملة غير قابلة للاستخدام فعليًا، تتعارض بشدة مع "خلفية إدارة كاملة" التي تدعيها الوثائق.
5. **بوابات الجودة شكلية فقط** (دليل مشترك عبر الاختبار/النشر/التوثيق): 22 اختبار وحدة فقط تغطي 4 فئات أدوات، صفر اختبارات للمتحكمين/الوسائط/النماذج؛ PHPStan الافتراضي 128M ينهار فورًا، وadmin بلا أي إعداد جودة؛ CI بلا خطوات phpstan/php-cs-fixer/composer audit وبلا job لـ Flutter/HarmonyOS؛ 99 منتج بناء لهارموني دخل المستودع خطأً، أي دمج إعادة هيكلة غير محصّن.

## 3. خارطة الطريق المرحلية

### المرحلة الأولى: إيقاف النزيف وفتح السلسلة الرئيسية للمعاملات — **P0 · الأسابيع 1-4**

**الأهداف**
- إصلاح المتحكمين القاتلين في admin وإنشاء آلية اختبار دخاني لمنع التكرار، واستعادة قابلية استخدام قائمتي إدارة الطلبات/الدفع
- فتح الفوترة الحقيقية لطلب service (القسائم/الشحن/الرسوم الجمركية/الخصم تُسجل) وإكمال قوة تكرار الدفع، لإغلاق حلقة سلسلة الطلبات في الخلفية
- إكمال الاستيراد التلقائي للبيانات الأولية للأعمال، لضمان توفر بيانات الواجهات الأساسية فورًا بعد التثبيت الجديد
- فتح سلسلة السداد-الطلب-الدفع في Flutter و هارموني (address_id + PosterVerify + payment create/status)

**المخرجات**
- ✅ مكتمل: `admin/plugin/admin/app/controller/shop/ShopOrderController.php` و `ShopPaymentController.php` أُضيف لهما نوعا الإرجاع `: array`/`: Response` (تحميل انعكاسي 82/82 ناجح)؛ **المتبقي**: جديد `scripts/smoke_controllers.php` (php -l + تحميل انعكاسي لجميع المتحكمين الـ82) وربطه بـ Makefile check و CI كبوابة منع تكرار
- 🔄 **جديد من المراجعة (أولوية عالية)**: واجهة إصدار PosterVerify `POST /api/poster/verify` — الوسيطة تتحقق من مفتاح Redis `erik:poster:{token}` لكن لا يوجد أي كود إصدار/كتابة مفتاح في المشروع كله، والعميل لا يمكنه الحصول على X-Poster-Token؛ يلزم استدعاء poster-php لتوليد رمز التحقق وكتابة مفتاح Redis (مع انتهاء الصلاحية واستهلاك لمرة واحدة)، وهذا هو **الاعتماد المسبق** لربط التحقق البشري في تسجيل/طلب/دفع Flutter و هارموني
- `service/app/controller/v1/OrderController.php` store() يربط حساب خصم القسيمة وتسجيل shipping_fee/tax_amount/discount_amount (موازاة api.md 5.3 / features.md 3.3)، وتنفيذ فلتر min_price/max_price في api.md 2.1؛ `PaymentController::create` يضيف إزالة تكرار order_id+gateway
- `admin/plugin/admin/app/controller/InstallController.php` في نهاية step1 ينفذ إضافيًا `service/database/seeders/countries.php`، ويضيف erik_payment_gateway_methods (صف method لكل من stripe/paypal) ومكتبة erik_hs_codes الأساسية وأمثلة أولية erik_tariff_rules/erik_shipping_zones
- `apps/flutter/lib/features/order/checkout_screen.dart` (**انتبه: المسار الفعلي، وليس lib/screens/**) يضيف اختيار العنوان وإعادة تعبئة العنوان الافتراضي، إرسال address_id+currency_code، وبعد ربط PosterVerify (X-Poster-Token) تنفيذ `POST /payment/create` + `GET /payment/status` لصفحة دفع الاستقصاء؛ `apps/harmonyos/entry/src/main/ets/pages/Checkout.ets` يضيف بالمزامنة address_id + selectedShipping + currency_code واستدعاء الدفع (هارموني تحتاج صفحة إدارة عناوين جديدة، مسار عنوان الاستلام في Profile فارغ حاليًا)
- ✅ مكتمل: `ShopDashboardController.php` إصلاح مساري kpi/chartData (kebab→مطابقة دقيقة باسم الفئة) وتعارض توقيع `$this->json`، واستبدال بيانات المثال المكتوبة يدويًا
- إضافة اختبارات تكامل لواجهات الطلبات/الدفع/الاسترداد الأساسية في service (معاملة/خصم مخزون/إلغاء، تحقق توقيع webhook+قوة تكرار+تسوية، ترميز/فك Hashids)، باستخدام خدمات MySQL/Redis التي يشغّلها CI
- بنود جانبية: تصحيح خطأي كتابة منفذ admin 8787→8788 في `docs/deployment.md`

**الأدوار المسؤولة**: كامل المكدس الخلفي، مهندس الخلفية، الدفع والتسوية، Flutter، هارموني، QA

### المرحلة الثانية: إغلاق حلقة الامتثال وتوسيع الدفع والتسوية — **P1 · الأسابيع 5-10**

**الأهداف**
- تطبيق محرك قواعد إدارة المخاطر وربطه بحالة الطلب "قيد المراجعة(8)"، لإزالة ثغرة "الطلب بلا إدارة مخاطر يُفرج"
- إكمال حلقة إرسال KYC من جهة المستخدم وطبقة تنفيذ GDPR/CCPA (حذف/تصدير/opt-out)
- توحيد مصدر نسبة التسوية وإكمال التسوية الرباعية (Merchant/Supplier/Affiliate)
- تقارب إعلانات طرق الدفع: تنفيذ Klarna/Adyen أو تحديد placeholder صريح مع تصحيح الوثائق بالمزامنة، وإضافة كود 3DS صريح

**المخرجات**
- جديد `service/app/common/RiskEngine.php` (وفق checks/velocity في config/risk.php يحسب score)، التقييم الجانبي في OrderController::store / PaymentController::create / AuthController، كتابة erik_orders.risk_score/risk_result و RiskLogs، النقاط العالية تضع status=8؛ ShopRiskRule/ShopRiskLog يُعلّقان في قائمة admin
- 🔄 **جديد من المراجعة**: منفذ مراجعة إدارة المخاطر `POST /api/admin/orders/{id}/review` (حماية AdminKeyMiddleware، مزلاج ذري من status=8 إلى 1 إفراج/5 رفض مع كتابة OrderLogs) — حاليًا لا يوجد في service أي مسار كتابة/انتقال status=8، ومجرد تعليق القائمة دون ربط الواجهة يجعل "قيد المراجعة" طريقًا مسدودًا؛ قائمة ShopOrder في admin ترفق عملية المراجعة
- `service/config/route.php` يضيف `POST /api/kyc` و `GET /api/kyc/status` (real_name/id_number عبر Encryptable)، قبول admin يضع status=1 لربط التحقق الحالي في OrderController (مع توضيح مدخل مراجعة KYC في admin)
- جديد `service/app/task/PrivacyComplianceTask` (وفق config/privacy.php تنفيذ فترة سماح حذف البيانات/ملف تصدير البيانات/علامة حجب opt_out) + `POST /api/privacy/cookie-consent` كتابة erik_cookie_consents
- webhook و SettlementCron يدمجان في مصدر إعداد نسبة واحد (إزالة انجراف مصدري gateway_fee)، إكمال كتابة MerchantSettlements/SupplierSettlements/AffiliateCommissions وعملية الدفع، لدعم docs/08-multi-currency-settlement
- **الإجراء الافتراضي لـ Klarna/Adyen**: أولًا اعتماد "throw placeholder صريح + تصحيح صياغة api.md 6.1 / README / VERSIONS" (منخفض التكلفة، يُنجز اليوم)؛ التنفيذ الكامل (شامل نجاح الدفع في بيئة الاختبار + التحقق من توقيع webhook + قبول الاسترداد) يُنزل إلى المرحلة الرابعة؛ `StripeGateway::createPayment` يحدد صراحةً `request_three_d_secure='automatic'` ويعيد كتابة erik_payments.three_ds_status

**الأدوار المسؤولة**: الأمان والامتثال، الدفع والتسوية، مهندس الخلفية، كامل المكدس الخلفي، عبر الحدود i18n

### المرحلة الثالثة: بوابات الجودة وإكمال واجهة الخلفية — **P1/P2 · الأسابيع 11-18**

**الأهداف**
- إصلاح بوابة التحليل الساكن (حد ذاكرة PHPStan) وإكمال مجموعة إعدادات الجودة وهيكل الاختبارات لـ admin
- إدخال PHPUnit/phpstan/php-cs-fixer/composer audit/CI لـ Flutter و هارموني كلها في البوابات
- إكمال صفحات قائمة LayUI لوحدات P0 في إدارة المتجر أو تنظيف قوائم 404، وتوضيح تحديد "JSON API only"
- إصلاح أسطح التعرض للنشر والتشغيل (ربط المنافذ، ربط أحجام المصدر، بيانات GeoIP، تبعيات dev)

**المخرجات**
- ✅ مكتمل في جهة service: أمر phpstan مع `--memory-limit=1G` (Makefile/CI، PHPStan 2.x أزال معامل neon memoryLimit)؛ **المتبقي**: جديد admin/phpstan.neon (level 5) + admin/.php-cs-fixer.php + admin/phpunit.xml + admin/tests/ (تغطية أولوية لـ Crud base class inputFilter/doSelect/أذونات البيانات، مصادقة AccessControl، ShopRefundController mock للاسترداد عن بعد)
- ✅ مكتمل: ci.yml أضاف composer audit + phpstan؛ **المتبقي**: php-cs-fixer --dry-run، اختبارات تكامل service (اتصال مباشر بخدمات MySQL/Redis)، job لـ Flutter analyze+test و job بناء hvigor لهارموني
- إكمال واجهة `admin/plugin/admin/app/controller/shop/` وفق **مصفوفة الأولويات**: P0 (الطلبات/الاستردادات/الشحن/الدفع) يجب أن تكمل index() و index.html تحت view/shop/ (قائمة LayUI)؛ بنود القائمة المتبقية تُزال افتراضيًا من config/menu.php مع تعليم "JSON API only" (الإزالة تزيل 404 بتكلفة صفر)، وإضافة الصفحات كزيادات لاحقة عند الحاجة، لتجنب تعليق منتجات نصف منجزة
- 🔄 جديد من المراجعة: حوكمة مستودع هارموني (.gitignore يضيف `apps/harmonyos/**/build` و `**/.hvigor` و `**/oh_modules` مع `git rm --cached` لتنظيف 99 منتج بناء مضافًا؛ إكمال hvigorw wrapper) — هذا هو الاعتماد المسبق لربط job بناء هارموني في CI
- 🔄 جديد من المراجعة: سكربت التحقق من قائمة الجداول ثنائية المصدر بين install.sql و `$tables_to_install` في InstallController (تحليل CREATE TABLE في install.sql لإنشاء ديناميكي أو مقارنة تطابق الموضعين)
- `docker-compose.yml` يغيّر ربط منافذ ES/Redis/MySQL إلى 127.0.0.1 (nginx فقط يكشف 80/443)، يزيل ربط حجم المصدر `./service:/app` و `./admin:/app` ويضيف service/.dockerignore و admin/.dockerignore (استثناء vendor/runtime/.git)، لضمان تشغيل الحاوية بـ vendor --no-dev
- إكمال سكربت تنزيل GeoLite2-Country.mmdb (أو تفعيل التحديث التلقائي MAXMIND_LICENSE_KEY) ووضعه في service/database/geoip/؛ ثلاثة URLs فارغة في config/cron.php يرتفع تسجيلها إلى WARNING مع تعليقات بارزة

**الأدوار المسؤولة**: QA، DevOps، كامل المكدس الخلفي، Flutter، هارموني

### المرحلة الرابعة: القدرات الإضافية وتقارب الوثائق — **P2 · الأسابيع 19-26**

**الأهداف**
- تنفيذ القدرات الإضافية التي تعلن الوثائق أنها "كاملة" وهي مفقودة فعليًا (فاتورة PDF، سجل المخزون، الإدراج متعدد المنصات، الاشتراك الدوري)
- تفعيل فصل القراءة/الكتابة وإغلاق حلقة التسوية متعددة العملات وتعزيز البحث ES متعدد اللغات
- توحيد تعليم الوثائق ثلاثي الحالات (منفذ/بنية الجدول مبنية/قيد التخطيط) وإنشاء فحص اتساق نقاط النهاية، لوقف المزيد من الانجراف

**المخرجات**
- `service/app/controller/v1/DocumentController.php` يستخدم barryvdh/laravel-dompdf المُدخل سابقًا لتوليد الفاتورة التجارية/قائمة التعبئة PDF عند الطلب وكتابتها في erik_order_documents؛ OrderController عند خصم المخزون يكتب سلسلة erik_inventory_logs غير القابلة للتغيير
- PlatformOrderSyncCron يضيف محولات amazon/eBay/Shopee وينفذ كتابة الإدراج في PlatformListings؛ جديد API الاشتراك الدوري (erik_subscriptions جاهز، حدد أولًا الحد الأدنى للأعمال: دورة الفوترة للاشتراك + الإلغاء + التجديد) وخادم WebSocket لخدمة العملاء (ChatSessions/ChatMessages جاهزان)
- تفعيل فصل القراءة/الكتابة mysql_rw في config/database.php (تحويل صريح للاستعلامات القرائية، شامل دلالة sticky)، إكمال كتابة مقارنة سعر الصرف للتسوية في CurrencyExchangeGainsLosses، إغلاق حلقة التسوية المقسومة متعددة العملات
- `Products::toSearchableArray()` يوسع حقول فهرس title/description متعددة اللغات ويرجّح حسب locale، لتعزيز البحث ES متعدد اللغات
- تنفيذ كامل لـ Klarna/Adyen (جدولة عند الحاجة، شروط القبول: نجاح الدفع في بيئة الاختبار + التحقق من توقيع webhook + إغلاق حلقة الاسترداد)
- 🔄 جديد من المراجعة: قدرة الاسترداد الجزئي للدفع (انتقال حالة Refunds 2/3، مبالغ الاسترداد الجزئي وربط حالة الطلب) وتوسيع تغطية أحداث webhook (استراتيجية معالجة صريحة للأحداث غير الناجحة مثل payment_intent.refunded/failed، حاليًا التجاهل الصامت يعتمد على تغطية PaymentReconcileCron)
- 🔄 جديد من المراجعة: تقوية المصادقة — إبطال JWT (قائمة حظر Redis أو رقم إصدار الرمز، يُبطل بعد تغيير كلمة المرور/تسجيل الخروج)، مسارات إعادة تعيين كلمة المرور/التحقق من البريد (اقتراحات البحث §5، غابت عن الخارطة سابقًا)
- ✅ جديد من المراجعة: ربط تشفير AES للواجهات من جهة العميل (Flutter/HarmonyOS يدعمان X-Encrypted/X-Encrypt-Response) + التخزين الآمن للرموز في هارموني (KeyStore/security.asset بديلاً عن preferences نصًا صريحًا) — انظر أدناه «المرحلة الرابعة P2: KeyStore في هارموني + AES للعميل + صفحة إتمام الدفع» (مُرمّز، بانتظار تحقق الترجمة)

**الأدوار المسؤولة**: مهندس الخلفية، كامل المكدس الخلفي، الدفع والتسوية، عبر الحدود i18n، QA

## 4. المخاطر الرئيسية (يجب معالجتها أولًا)

1. **سلسلة الدفع تنقصها قوة التكرار وانجراف مصدرَي نسبة التسوية**: طلبات payment/create المكررة تولّد عدة سجلات دفع معلقة، webhook يعالج أحداث النجاح فقط؛ نسبة gateway_fee تُصان في موضعين مستقلين، مع خطر تكرار وتناقض في معايير التسوية.
2. **مخاطر الثقة من تقدم الوثائق على الكود**: محرك إدارة المخاطر و Klarna/Adyen والتسوية الرباعية والفاتورة PDF والاشتراك/AB و WS خدمة العملاء وغيرها من عشرة بنود تدّعي "الكاملة" وهي في الحقيقة placeholder أو أعمدة CRUD، ما يشكل فجوة في توقعات التسليم للعملاء التجاريين.
3. **بيانات أولية فارغة للتثبيت الجديد + إفراج افتراضي في الامتثال**: واجهات countries/طرق الدفع/الشحن/الرسوم الجمركية تعيد فارغًا فورًا؛ `blocked_countries` مصفوفة فارغة افتراضيًا، و KYC يدعم KR فقط، أي إغفال إعداد يعني إفراجًا كاملًا.
4. **بوابات الجودة شكلية**: 22 اختبار وحدة فقط تغطي فئات الأدوات، PHPStan الافتراضي 128M ينهار فورًا، وadmin بلا اختبارات وإعدادات جودة، وCI بلا phpstan/composer audit/jobs العملاء، وإعادة هيكلة الدمج غير محصّنة.
5. **أسطح تعرض وسائط الإنتاج**: ES بلا مصادقة ومنفذه 9200 مكشوف، Redis افتراضيًا بلا كلمة مرور، منافذ MySQL/الخدمة كلها مكشوفة، و .env غير المكتمل يمكن أن يُطلق في العراء.

## 5. انتصارات سريعة (أمور منخفضة التكلفة وعالية العائد يمكن تنفيذها فورًا)

1. **✅ مكتمل** بوابة PHPStan: أمر phpstan في Makefile check و CI مع `--memory-limit=1G` (انتبه: PHPStan 2.2.8 أزال معامل `memoryLimit` في neon، يجب التمرير عبر CLI، والإعداد في neon يسبب `Unexpected item`). اختبار فعلي `make check` → `[OK] No errors`.
2. **✅ مكتمل** إضافة `: array`/`: Response` لأنواع إرجاع ShopOrderController/ShopPaymentController، بعد الإصلاح التحميل الانعكاسي 82/82 للمتحكمين ناجح؛ سكربت الاختبار الدخاني لمنع التكرار انظر مخرجات المرحلة الأولى.
3. InstallController في نهاية step1 يستورد تلقائيًا بيانات أولية countries وأمثلة طرق الدفع/HS Code/الشحن والرسوم الجمركية، ليكون التثبيت الجديد متاحًا فورًا.
4. **✅ مكتمل** إصلاح مساري kpi/chartData في ShopDashboardController (kebab→مطابقة دقيقة باسم الفئة) وتعارض توقيع `$this->json` (عُدل إلى `$this->json(0,'ok',$data)`) واستبدال بيانات المثال المكتوبة يدويًا.
5. **✅ مكتمل** CI أضاف خطوة composer audit (تغطية `||` حتى لا توقف CVE معروفة منخفضة الخطورة) وخطوة phpstan، دخول أمان التبعيات في البوابة.

## 6. ترتيب البدء المقترح

**ابدأ بالمرحلة الأولى (إيقاف النزيف وفتح السلسلة الرئيسية للمعاملات) أولًا**: سلسلة معاملات الأطراف الأربعة المكسورة والأخطاء القاتلة في admin مشاكل على مستوى منع الإطلاق؛ وإصلاحات توقيع المتحكمين و فوترة الطلب واستيراد البيانات الأولية وفتح دفع الطرفين مستقلة وقابلة للتوازي ويمكن رؤية النتائج خلال 1-4 أسابيع؛ فتح السلسلة الرئيسية أولًا هو الشرط لتوفير أساس قابل للتحقق لاحقًا للامتثال وبوابات الجودة.

## الملحق

- **هيكل الفريق**: طبقة التنسيق (Team Lead، مهندس البنية) → فرقة الخلفية الصغيرة (الخلفية/الدفع والتسوية/البحث والتوصية/كامل المكدس الخلفي) → فرقة العملاء الصغيرة (Flutter، هارموني) → الدعم العرضي (الأمان والامتثال، QA، DevOps، عبر الحدود i18n)، التفاصيل في `CLAUDE.md` الجذر ومناقشات تخطيط الفريق.
- **تفاصيل البحث**: `docs/PLAN-RESEARCH.md` (7 مجالات: API الخادم / خلفية الإدارة / Flutter / هارموني / الأمان والامتثال / النشر والبيانات والاختبار / تغطية الميزات في الوثائق).
