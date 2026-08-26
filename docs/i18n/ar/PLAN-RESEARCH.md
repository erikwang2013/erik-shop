# تفاصيل بحث فريق Erik Shop (7 مجالات)

> **تاريخ الإنشاء**: 2026-08 · **طريقة الإنشاء**: بحث متوازٍ لفريق متعدد الوكلاء (استنادًا إلى أدلة الكود الفعلية، يُمنع التخمين)
> **وثيقة مرافقة**: `docs/PLAN.md` (خطة المشروع المدمجة، تتضمن تعديلات المراجعة وحالة التنفيذ)
> **سجل المراجعة**: 2026-08 راجع مهندس المراجعة 18 ادعاءً مقابل الكود (16 صحيحة، 2 صحيحة جزئيًا بسبب إصلاحات مساحة العمل)؛ اقتراح إصلاح PHPStan في هذا التفصيل عُدل وفق قدرات PHPStan 2.x الفعلية (التمرير عبر CLI بديلًا عن إعداد neon)
> **هيكل كل مجال**: ملخص الوضع الحالي / ما تم تنفيذه / الفجوات / المخاطر / التوصيات (بادئة التوصيات [عالية]/[متوسطة]/[منخفضة] هي الأولوية)

---

## 1. API أعمال الخادم (service/)

### ملخص الوضع الحالي
البنية الأساسية وهياكل الأمان/الدفع/البحث/التوصية متينة (39 متحكمًا + 111 نموذجًا + 14 وسيطة + 10 مهام مجدولة، Stripe/PayPal قابلان للاستخدام فعليًا، 22 اختبار وحدة ناجحة)، لكن عدة قدرات تعلن الوثائق أنها "كاملة" هي في الواقع placeholder أو غير موصولة: بوابتا Klarna/Adyen إعداد فقط، الطلب لا يحسب القسائم/الشحن/الرسوم الجمركية/إدارة المخاطر، فصل القراءة/الكتابة غير مفعّل، البيانات الأولية للأعمال مفقودة فتعود واجهات التثبيت الجديد بلا بيانات.

### ما تم تنفيذه
- تنفيذ مزدوج لبوابات الدفع (كود حقيقي): في PaymentGateway.php ستripe (PaymentIntent + التحقق من توقيع webhook + الاسترداد) وباي بال (REST v2 OAuth2 + طلبات/التقاط/استرداد + التحقق من توقيع verify-webhook-signature بخمسة حقول + تحليل capture id للاسترداد) مكتملان وقابلان للتشغيل، و PaymentController::webhook يستخدم مزلاجًا ذريًا لحالة الطلب لمنع الإدخال المكرر ويولّد سجل تسوية PlatformSettlements داخل معاملة
- إغلاق حلقة سلسلة الدفع: PaymentController (create/status/methods/webhook)، AdminOpsController::executeRefund ينفذ استرداد البوابة فعليًا ويسجل في قاعدة البيانات داخل معاملة (سجل استرداد + سجل دفع + حالة طلب + سجل)، PaymentReconcileCron كل 6 ساعات يطابق الحالة الفعلية للبوابة لطلبات الدفع المعلقة الأقدم من ساعتين
- 14 وسيطة في المكدس تعمل بالترتيب (config/middleware.php): Cors→Security(security-php SecurityGuard بأكثر من 25 نوع كواشف + عدّاد قوة غاشمة في Redis)→RateLimit(نافذة منزلقة Redis، أكثر من 6 قواعد نقاط نهاية)→Platform(تحديد 8 منصات)→GeoIp(MaxMind)→Locale→HashidsDecode→VersionRoute(ترويسة API-Version)→HashidsEncode→Encryption، وعلى مستوى التوجيه PosterVerify/JwtAuth/AdminKey
- تسعير متعدد العملات ومنتجات متعددة اللغات بتنفيذ حقيقي: ProductSkuPrices تسعير مستقل لكل عملة + ترقية سعر صرف ExchangeRates، ProductTranslations تحميل مبكر حسب locale (ProductController يتضمن حساب سعر العرض شامل/غير شامل VAT)
- تقدير الرسوم الجمركية قابل للاستخدام فعليًا: TariffController وفق ProductHsCodes→TariffRules(dest_country+hs_code)→VatSettings يحسب duty/vat (شامل عتبة الإعفاء و disclaimer)؛ ShippingController يحسب الشحن وفق مناطق اللوجستيات + سلم الوزن
- البحث والتوصية بتنفيذ حقيقي: SearchController عبر webman-scout (نموذج Products Searchable + تعيين ES erik_shop_products) مع ترقية MySQL LIKE عند استثناء ES وكتابة SearchLogs؛ RecommendationCron يحسب التكرار المشترك للشراء خلال 90 يومًا ويكتب Top10 في product_recommendations، وRecommendationController CF قائم على الأصناف + ترقية الأكثر مبيعًا
- تدفق الطلبات الأساسي: store يخصم المخزون ذريًا داخل معاملة (where stock>=qty decrement، منع البيع الزائد)، استعادة المخزون عند الإلغاء، اعتراض دخول KYC/المحظور، آلة الحالة 0-8؛ CouponController::claim قفل صف lockForUpdate + مزلاج ذري لمنع الإصدار الزائد
- 10 مهام مجدولة كلها مسجلة في config/process.php (سعر الصرف/تتبع اللوجستيات/Feed/التوصية/الامتثال/انتهاء الإرجاع/تنبيه السعر/مطابقة الدفع/التسوية/مزامنة المنصات المتعددة)، جميعها مع تسجيل أخطاء ومنطق تخطي عند عدم الإعداد
- تصدير الوثائق قابل للاستخدام فعليًا: ExportController PhpSpreadsheet XLSX+CSV (شامل عمود HS Code)، DocumentController فاتورة تجارية/قائمة تعبئة (dompdf)، HealthController فحص الاستبقاء (تحقق مزدوج db/redis)
- سلسلة أدوات الجودة كاملة: PHPUnit 12.5 (22 tests/45 assertions، 4 ملفات Security/Jwt/ApiResponse/RedisFacade)، phpstan level 5 (phpstan.neon يشمل إعفاء أخطاء Eloquent الوهمية)، php-cs-fixer، .github/workflows/ci.yml (مصفوفة PHP 8.3/8.4 + MySQL + Redis)
- أنماط البنية التحتية مطبقة فعليًا: BaseModel مفتاح أساسي Snowflake، وسيطة ترميز/فك Hashids تحويل تلقائي، Jwt.php رموز access/refresh مزدوجة (JwtAuth يرفض refresh للواجهات الأعمال)، تشفير حقول encryptable، إعدادات تشغيل config/risk.php+country.php+geoip.php مكتملة

### الفجوات
- Klarna/Adyen/Afterpay placeholder فقط: match في PaymentGateway::make() يدعم stripe/paypal فقط (default يرمي استثناءً)، PaymentController::methods يفلتر صراحةً stripe/paypal فقط (تعليق يعترف "تجنب كشف بوابات غير منفذة")؛ لكن مثال استجابة docs/api.md 6.1 يتضمن سطر Klarna و features.md 1.0 يدّعي Klarna BNPL/Adyen — الوثائق لا تطابق الكود
- الطلب لا يدمج القسائم/الشحن/الرسوم الجمركية/إدارة المخاطر: OrderController::store يجمّع إجمالي المنتجات فقط، ولا يقرأ coupon_id الموثق (api.md 5.3)، ولا يحسب حقول shipping_fee/tax_amount/discount_amount/insurance_fee الموجودة أصلًا في erik_orders؛ config/risk.php موجود لكن لا يوجد أي استدعاء RiskEngine داخل app/ (features.md 3.3 يدّعي "حساب السعر (لكل عملة + قسيمة)" و"تقييم المخاطر (RiskEngine::score)")
- البيانات الأولية للأعمال مفقودة: install.sql فيه إدخالا INSERT فقط (نظاما wa_options/wa_roles)، و erik_hs_codes/erik_tariff_rules/erik_payment_gateway_methods/erik_shipping_zones/erik_countries كلها بلا بيانات؛ database/seeders فيه countries.php فقط ولا يوجد أي كود يحمّله (ملف ميت، CLAUDE.md يدّعي تغطية الدول/HS Code/أسعار الصرف/مناطق اللوجستيات/تصنيفات الامتثال/جداول المقاسات/قواعد المخاطر) — بعد التثبيت الجديد تعيد واجهات countries/طرق الدفع/الشحن/الرسوم الجمركية فارغًا
- إعداد فصل القراءة/الكتابة غير مفعّل: config/database.php يعرّف mysql_rw (نسختا قراءة + sticky) لكن لا يوجد أي كود في app/ أو config/ يشير إلى اسم الاتصال هذا، كل النماذج تستخدم mysql الافتراضية؛ ادعاء features.md 5.x "DB فصل القراءة/الكتابة (نسختا قراءة + sticky) كامل" لا يطابق الواقع
- الاشتراك الدوري والإدراج متعدد المنصات بنية جدول فقط: نماذج Subscriptions/SubscriptionOrders/SubscriptionLogs و PlatformListings موجودة لكن لا متحكم/توجيه/كود كتابة (متعدد المنصات لديه فقط PlatformOrderSyncCron يسحب الطلبات عبر URL خارجي)؛ features.md يدّعي أن الاثنين "كاملان"
- إعلان بحث ES متعدد اللغات مبالغ فيه: CLAUDE.md يدّعي "فهرس ES يتضمن title/description بكل اللغات ومرجّحًا حسب locale"، لكن Products::toSearchableArray() يفهرس الحقول الأساسية أحادية اللغة فقط؛ دليل app/search/ الذي يدّعيه CLAUDE.md غير موجود فعليًا (Searchable مضمّن في النموذج)
- WebSocket IM لخدمة العملاء غير منفذ: بنية جدولي ChatSessions/ChatMessages فقط (features.md يعترف "WS بانتظار التنفيذ"، متسق لكنه بالفعل غير مكتمل)، وحالتا "قيد المراجعة(8)/قيد الاسترداد(6)" في آلة حالة الطلب بلا أي مسار كتابة (لا تدفق مراجعة، والاسترداد فقط executeRefund في admin يمر مباشرة إلى مسترد(7))
- تغطية الاختبارات ضيقة وتنحرف عن عبارات الوثائق: 4 ملفات اختبار وحدة فقط (AUDIT-REPORT.md يعترف "لا اختبارات تكامل/لا تقرير تغطية")، ولا اختبارات تكامل للمتحكمين/الطلبات/الدفع/الوسائط؛ api.md 13.18 يقول التصدير يعيد CSV، والكود الفعلي XLSX افتراضيًا + CSV اختياريًا؛ معاملا فلتر min_price/max_price الموثقان في api.md 2.1 غير منفذين في ProductController::index

### المخاطر
- سلسلة الدفع تنقصها قوة التكرار وتغطية الأحداث: POST /api/payment/create بلا مفتاح قوة تكرار، الطلبات المكررة تولّد عدة سجلات دفع معلقة؛ webhook يعالج payment_intent.succeeded / PAYMENT.CAPTURE.COMPLETED فقط، وأحداث refunded/failed تُتجاهل بصمت، بالاعتماد على تغطية PaymentReconcileCron (لا يستعلم إلا الطلبات الأقدم من ساعتين)
- انجراف مصدرَي معيار التسوية: webhook و SettlementCron يقرآن النسب من config('payment.gateway_fee.*') و config('cron.payment_gateway_fee_*') على الترتيب، موضعا صيانة مستقلان؛ وبعد توليد webhook لـ PlatformSettlements(status=0) يعيد SettlementCron الحساب بإزالة التكرار حسب order_id، مع خطر التكرار/تناقض المعيار؛ التسوية عمولة المنصة + رسوم البوابة فقط، وsupplier_amount بلا عملية تحويل، وaffiliate_amount ثابت 0، وتسوية الدفع المقسوم متعدد العملات (docs/08-multi-currency-settlement) غير مغلقة
- نشر جديد = بيانات فارغة + إفراج افتراضي للامتثال: كل البيانات الأولية للأعمال مفقودة و blocked_countries في config/country.php مصفوفة فارغة افتراضيًا و kyc_required_countries يدعم KR فقط، واعتراضات الحظر/KYC في OrderController تعتمد على الإعداد اليدوي، فأي إغفال إعداد = إفراج كامل
- اعتماد البحث هش: عند تعطل ES تترقية try/catch كاملة إلى MySQL LIKE، ومزامنة scout بلا قائمة انتظار (config/scout.php sync.queue=false)، ولا تغطية CI للفهرس ومحللات متعددة اللغات، وانجراف الفهرس غير قابل للضبط
- فجوات دقة المالية وآلة الحالة: مبالغ الطلبات float تُجمّع وتُقرّب؛ الاسترداد كامل الطلب فقط status=7 بلا استرداد جزئي؛ آلة حالة Refunds 2(مرفوض)/3(مسترد) تنتقل في مسار واحد فقط عبر AdminOpsController، بلا واجهة طلب استرداد ومراجعة من جهة المستخدم (حلقة المراجعة/ملصق الإرجاع في تدفق features.md 3.5 تعتمد على جهة admin)

### التوصيات
- [عالية] إكمال البيانات الأولية للأعمال: ربط database/seeders/countries.php بمعالج التثبيت أو تدفق الإقلاع الأول، وإضافة مكتبة HS Code الأساسية وطرق الدفع الافتراضية (صف method لكل من stripe/paypal) وأمثلة قواعد VAT/الرسوم الجمركية وبيانات أولية لمناطق الشحن؛ وإلا تعيد واجهات التثبيت الجديد الأساسية (countries/طرق الدفع/الرسوم الجمركية/الشحن) فارغًا
- [عالية] فتح الفوترة الحقيقية للطلب: OrderController::store يدمج خصم القسيمة (coupon_id موثق أصلًا) وتسجيل shipping_fee/tax_amount/discount_amount (الحقول موجودة أصلًا)، موازاة features.md 3.3/api.md 5.3، وتنفيذ فلتر min_price/max_price في api.md 2.1
- [عالية] تقارب إعلانات طرق الدفع: خياران — تنفيذ بوابتي Klarna/Adyen (توسيع PaymentGateway::make، إعداد Klarna و gateway_fee جاهزان) أو تعليم "placeholder" صراحةً وتصحيح مثال api.md 6.1، لتجنب عرض طرق غير قابلة للاستخدام أمام الواجهة؛ مع إضافة مفتاح قوة تكرار لـ payment/create (إزالة تكرار order_id+gateway)
- [متوسطة] تطبيق محرك إدارة المخاطر: تنفيذ RiskEngine::score (بالإشارة إلى checks/velocity في config/risk.php)، التقييم الجانبي في أحداث الطلب/الدفع مع كتابة risk_logs + order.risk_score (الحقل موجود)، وربط حالة "قيد المراجعة(8)" وتدفق المراجعة اليدوية
- [متوسطة] تفعيل فصل القراءة/الكتابة أو تصحيح الوثائق: تحويل صريح للاستعلامات القرائية إلى اتصال mysql_rw (الإعداد جاهز)، أو على الأقل تعليم "إعداد فقط غير مفعّل" في features.md، لإزالة الانفصال بين الإعداد والتنفيذ
- [متوسطة] إضافة اختبارات التكامل وعتبة التغطية: لإنشاء الطلبات (معاملة/خصم مخزون/إلغاء) و webhook الدفع (تحقق التوقيع/قوة التكرار/التسوية) وحساب Tariff/Shipping وترميز/فك Hashids كتابة اختبارات تكامل PHPUnit (خدمات MySQL/Redis في CI جاهزة للاستخدام المباشر)، وإعداد عتبة تغطية
- [متوسطة] توحيد مصدر نسبة التسوية وإكمال التسوية: دمج webhook و SettlementCron في مصدر نسبة واحد؛ إكمال كتابة تسوية الموردين/التوزيع (MerchantSettlements/SupplierSettlements/AffiliateCommissions جداول جاهزة) وتدفق التحويل/السحب، لدعم تسوية docs/08-multi-currency-settlement متعددة العملات
- [منخفضة] توسعة المنصات و WS خدمة العملاء: لـ PlatformOrderSyncCron إضافة محولات amazon/eBay/Shopee وتنفيذ كتابة الإدراج في PlatformListings (الجدول جاهز)؛ IM خدمة العملاء تنفيذ خادم WebSocket وإرسال/استقبال الرسائل (جدولا ChatSessions/ChatMessages جاهزان)

---

## 2. خلفية الإدارة (admin/)

### ملخص الوضع الحالي
خلفية الإدارة مبنية على webman-admin + LayUI/Pear Admin ولديها معالج تثبيت كامل و RBAC أذونات ومكدس وسائط WAF وهيكل 82 متحكمًا/76 نموذجًا، لكن طبقة الأعمال "متحكمات بلا صفحات": من 67 متحكمًا للمتجر 59 عمود CRUD خالص يربط النموذج فقط، وبلا أي عرض HTML خارج لوحة العبر الحدودية (نقر القائمة 404)، ومتحكما ShopOrder/ShopPayment بسبب عدم توافق توقيع الدالة في PHP 8.3 يفشلان في تحميل الفئة بخطأ قاتل، وقائمتا الطلبات/الدفع غير قابلتين للاستخدام فعليًا.

### ما تم تنفيذه
- 82 متحكمًا (15 نظام + 67 متجر) و 76 نموذجًا (9 نظام + 67 متجر) كلها موجودة في أزواج مع ترويسة Copyright، والمساحات تتبع plugin\admin\app\controller|model
- فئة CRUD العامة الكاملة Crud تنفذ select/insert/update/delete وتنسيق tree/select/normal، شاملة أذونات البيانات (dataLimit: personal/auth)، فلتر القائمة البيضاء لحقول بنية desc في inputFilter، تجزئة كلمات المرور، نقاط التوسعة afterQuery/insertInput/updateInput وغيرها
- معالج تثبيت الويب InstallController قابل للاستخدام فعليًا: step1 إنشاء قاعدة البيانات + التحقق من جداول التعارض + استيراد install.sql الجذري (117 جدولًا) + توليد plugin/admin/config/database.php و thinkorm.php + توليد service/.env و admin/.env (JWT/Hashids/AES/ADMIN_API_KEY عشوائية) + إعادة تحميل SIGUSR1؛ step2 إنشاء مدير فائق وربط الدور 1؛ importMenu يستورد config/menu.php تكراريًا إلى wa_rules
- نظام الأذونات كامل: وسيطة AccessControl + plugin\admin\api\Auth::canAccess (noNeedLogin/noNeedAuth/مطابقة قواعد الأدوار/علامة المدير الفائق */تقسيم 401 و 403)، يعتمد جداول wa_roles/wa_rules/wa_admin_roles
- مكدس الوسائط متسق مع features.md 4.2: SecurityMiddleware (security-php SecurityGuard من erikwang2013 + قوة غاشمة لتسجيل الدخول 5 مرات/300 ثانية + ترويسات استجابة أمنية)، PlatformMiddleware (تحديد UA لـ 8 منصات)، HashidsDecode/HashidsEncode (فك الطلبات وترميز حقول *_id في الاستجابات)، AccessControl
- بنية القوائم config/menu.php (526 سطرًا): 6 مجموعات نظام + 7 مجموعات أعمال (تحليل البيانات/إدارة المتجر/إدارة الطلبات/الجمارك والضرائب/إدارة اللوجستيات/إدارة التسويق/إدارة سلسلة التوريد) بإجمالي 27 بند قائمة للمتجر، شاملة الوزن/الأيقونة/التوجيه
- لوحة العبر الحدودية ShopDashboardController + عرض ECharts (سمة Pear Admin، بطاقات KPI و 5 حاويات رسوم، تشير إلى CDN echarts@5.5.0)
- مراجعة الاسترداد ShopRefundController من المتحكمات القليلة ذات منطق الأعمال الحقيقي: آلة حالة 0 قيد المراجعة/1 مقبول/2 مرفوض/3 مسترد، قبل تعليم "مسترد" يستدعي الواجهة الداخلية POST /api/admin/refunds/{id}/execute في service (مصادقة X-Admin-Key، و AdminOpsController + AdminKeyMiddleware في جهة service موجودان فعليًا)، والفشل يرفض الكتابة
- تصدير الطلبات ShopExportController: PhpSpreadsheet يولّد Excel (رقم الطلب/التاريخ/الحالة/العملة/مبلغ المنتجات/الخصم/الشحن/المبلغ المدفوع)، و barryvdh/laravel-dompdf يولّد الفاتورة التجارية PDF (شاملة التفاصيل والعملة وملاحظة الإقرار الجمركي)
- النماذج موحدة على مفتاح أساسي Snowflake (Base::boot creating يولّد تلقائيًا ID من نوع string)، نماذج الأعمال تعلن أسماء جداول erik_ وتشترك مع service في نفس اتصال MySQL (plugin.admin.mysql)
- ملفات i18n الأساسية موجودة: تحت admin/resource/translations خمس لغات zh_CN/zh_HK/en/ja/ko لكل منها 48 مفتاحًا
- الجودة والنشر المرافق: composer.json يشمل phpunit ^12.5 و php-cs-fixer كتبعيات dev، و admin/Dockerfile + docker-compose (منفذ 8788) إعداد كامل

### الفجوات
- **العيب القاتل (أُعيد إنتاجه واختباره)**: ShopOrderController.php و ShopPaymentController.php عند إعادة كتابة دوال فئة Crud الأصلية التوقيع غير متوافق (ينقص : array / : Response لأنواع الإرجاع)، فتحميل الفئة في PHP 8.3 خطأ Fatal فوري — بمجرد زيارة «قائمة الطلبات» «سجلات الدفع» في القائمة تنهار، ويسبب خطأً في عملية webman
- من 67 متحكمًا للمتجر 59 عمود CRUD خالص يحوي protected $model فقط، وباستثناء ShopDashboardController لا توجد دالة index() ولا أي عرض HTML (تحت view/shop/ ملف واحد فقط dashboard/index.html)؛ روابط القائمة href /app/admin/shop/ShopProduct/index وغيرها تشير إلى action غير موجود، والمطابقة الدقيقة لتوجيه webman الافتراضي تسقط في fallback 404 — واجهة إدارة المتجر كلها (المنتجات/التصنيفات/اللوجستيات/التسويق وغيرها) غير قابلة للاستخدام فعليًا، JSON API فقط
- سلسلة بيانات لوحة العبر الحدودية تالفة مزدوجًا: العرض يجلب /app/admin/shop/shop-dashboard/kpi و /chartData (صيغة kebab) بلا توجيه مقابل (اسم الفئة ShopDashboardController، تحقق App::getController في webman يطابق بدقة حسب اسم الملف)؛ و kpi/chartData في ShopDashboardController يستدعي $this->json(['code'=>0,...]) بتمرير مصفوفة، يتعارض مع توقيع Base::json(int $code,...) ويجب أن يرمي TypeError؛ رسوم توزيع المناطق/نسبة العملات/حالة الطلبات الثلاثة بيانات مثال مكتوبة يدويًا (تعليق الكود يعلن «بيانات مثال»)، ورسوم «اللوجستيات الزمنية» التي يدّعيها CLAUDE.md غير موجودة
- ادعاءات الوثائق لا تطابق الكود: PDF قائمة التعبئة و PDF التقرير المالي (تجميع لكل عملة) بلا أي تنفيذ في admin؛ إدارة الشحن ShopShipmentController عمود خالص (بلا إقرار HS ومنطق تتبع)؛ أعمدة تصدير Excel للطلبات (ShopExportController.php الأسطر 44-60) بلا عمودي HS Code/الرسوم الجمركية، تخالف «شامل HS Code/الرسوم الجمركية/العملة» في CLAUDE.md؛ «تحرير متعدد اللغات + تسعير لكل عملة» للمنتجات بلا UI مقابل (ShopProductTranslation/ShopProductSkuPrice أعمدة وغير موجودة في القائمة)
- 40 متحكمًا للمتجر غير موجودين في menu.php (ShopMerchant/ShopPlatformAccount/ShopPlatformListing/ShopPlatformOrder/ShopRiskRule/ShopRiskLog/ShopCms/ShopGiftCard/ShopMembership/ShopPointRule/ShopSubscription/ShopB2b/ShopAbTest/ShopCountry/ShopCurrency/ShopExchangeRate/ShopEmailTemplate/ShopNotification/ShopOperationLog/ShopUserKyc/ShopSetting/ShopOrderDocument/ShopSizeChart/ShopKnowledgeBase/ShopFaq/ShopProductAttr/ShopProductCompliance/ShopProductFeed/ShopPriceAlert/ShopPrivacy/ShopInsurance/ShopInventoryTransfer/ShopApiDoc/ShopShop/ShopMerchantProduct/ShopMerchantSettlement/ShopCountryCompliance/ShopProductHsCode/ShopProductTranslation/ShopProductSkuPrice)، بلا مدخل قائمة لا يمكن الوصول إليها إلا عبر URL مباشر
- تغطية الاختبارات صفر: admin/ بلا دليل tests/ وبلا phpunit.xml، وphpunit ^12.5 مجرد require-dev في composer (AUDIT-REPORT.md يعترف أيضًا «اختبارات أتمتة جهة Admin ما زالت فارغة»)؛ php-cs-fixer في تبعيات dev لكن بلا إعداد .php-cs-fixer وبلا CI
- i18n غير موصول بالواجهة: ملفات الترجمة الخمس موجودة، لكن عروض الإضافات والمتحكمين بلا أي استدعاء trans()/__() (grep بلا نتيجة)، وindex.html بلا زر تبديل لغة في الأعلى، يخالف «نصوص واجهة LayUI تُترجم عبر دالة trans()، وزر تبديل اللغة في شريط التنقل العلوي» في CLAUDE.md
- منطق اعتراض insert/update/delete الذي قصدته ShopPaymentController «سجلات الدفع للقراءة فقط» معطّل كليًا بسبب خطأ التوقيع؛ وقيود الأعمال «لا يسمح بإنشاء/تعديل الطلبات مباشرة» في ShopOrderController عاجزة أيضًا

### المخاطر
- مستوى منع الإطلاق: ShopOrderController/ShopPaymentController تحميل الفئة خطأ Fatal فوري (اختبار فعلي في PHP 8.3)، فتح قائمتي الطلبات/سجلات الدفع يعطي خطأً فورًا، والخطأ الفادح في PHP يجعل عملية webman المقيمة تبلغ عن الخطأ وتعيد التشغيل
- «المتحكمات العمود» كثيرة (59/67) + القوائم والوثائق تدّعي وظائف كاملة، فيسهل على التطوير/التشغيل سوء تقدير أن الميزات أُطلقت (القائمة موجودة والجدول موجود لكن API 404 أو بيانات فارغة)، دين تقني عالي التضليل
- HashidsEncode يرمّز كل *_id/الأرقام المعرفية الرقمية في الاستجابات (شامل فرع العتبة الذي لا يرمّز int أقل من 40000)، إذا دخل حقل أعمال جديد خطأً إلى encodeFields أو وُجد ID رقمي غير snowflake في الجدول، تحدث فوضى دلالية لمعرفات الواجهة الأمامية/الخلفية بلا اختبار تغطية
- قائمة جداول التعارض بين install.sql و $tables_to_install المكتوبة يدويًا في InstallController (نحو 117 بندًا) تُصان في موضعين، وعند إضافة جدول يسهل تفويت تحديث كشف التعارض، وإذا احتوى install.sql إجراءات مخزنة/مشغلات قد تكسرها splitSqlFile بالتقطيع عند الفواصل المنقوطة (لا يُرى مثل هذا المحتوى في SQL الحالي، خطر كامن)
- Crud::selectInput يعيد 6 عناصر بينما select() يفكك 5 فقط (يُتجاهل $page، والترقيم يعتمد معاملات طلب Illuminate العامة)، و doSelect لا يعالج عوامل تشغيل السلسلة بخلاف like وغيرها من الحدود، مضافًا إليها غياب الاختبارات، وخطر تراجع تعديلات لاحقة مرتفع

### التوصيات
- [عالية] إصلاح عدم توافق التوقيع: لـ ShopOrderController::insertInput/updateInput إضافة (Request $request): array، ولـ ShopPaymentController::insert/update/delete إضافة : Response لأنواع الإرجاع، مع سكربت دخاني قبل الإرسال (php -l + تحميل انعكاسي لجميع المتحكمين الـ82) لمنع التكرار
- [عالية] إصلاح سلسلة بيانات لوحة العبر الحدودية: URL جلب العرض يُعدل إلى /app/admin/shop/ShopDashboard/kpi و /chartData (أو إضافة أسماء توجيه kebab)، و kpi/chartData يستخدمان $this->success()/Base::json(0,'ok',...) استدعاءً نظاميًا، حذف/استبدال الرسوم المثال المكتوبة يدويًا وإضافة رسم «اللوجستيات الزمنية» (إن كان مفقودًا يلزم تعليمه بأمانة في الوثائق)
- [عالية] توضيح تموضع إدارة المتجر والاختيار بين خيارين: لمتحكمات القائمة الـ27 إكمال صفحة قائمة LayUI القياسية index.html في webman-admin (كل متحكم يضيف index() تعرض العرض)، أو إزالة قوائم 404 من menu.php مع تعليم «JSON API only»؛ مع أولوية صفحات وحدات P0 مثل الطلبات/الاستردادات/الشحن
- [متوسطة] إنشاء هيكل اختبارات admin: إضافة phpunit.xml ودليل tests/، أولوية تغطية فئة Crud الأساسية (inputFilter/doSelect/أذونات البيانات)، فروع مصادقة AccessControl، InstallController (مكتبة مؤقتة + mock PDO)، واستدعاء الاسترداد عن بعد في ShopRefundController (mock لنقطة نهاية service)
- [متوسطة] تصحيح الإعلانات المبالغ فيها في الوثائق: الأوصاف في CLAUDE.md غير المطابقة للكود — PDF قائمة التعبئة، PDF التقرير المالي، إقرار HS/التتبع في الشحن، عمودي HS/الرسوم الجمركية في تصدير الطلبات، زر تبديل اللغة i18n وغيرها — تُحذف أو تُعلَّم TODO حسب الفعلي، لتجنب تضليل التخطيط
- [متوسطة] إزالة قائمة الجداول ثنائية المصدر: قائمة تعارض الجداول في InstallController تُعدل إلى توليد ديناميكي بتحليل CREATE TABLE في install.sql، أو توفير سكربت تحقق يقارن تطابق الموضعين
- [منخفضة] ربط i18n: استدعاء trans() في العروض/المتحكمين وإضافة زر تبديل اللغة أعلى index.html (الملفات جاهزة وتنقصها الأسلاك فقط)، أو توضيح أن i18n موجّه لقيم إرجاع API لخدمة فقط
- [منخفضة] إكمال أدوات الجودة: إضافة إعداد .php-cs-fixer.php وربطه بـ CI (تشغيل phpunit + php-cs-fixer --dry-run على admin)، واستلام بند «إضافة اختبارات Admin» المدرج أصلًا في AUDIT-REPORT.md

---

## 3. عميل Flutter (apps/flutter/)

### ملخص الوضع الحالي
هيكل عميل Flutter مكتمل (11 صفحة، 11 توجيهًا، جدول كلمات 5 لغات، 3 معترضات Dio موازية لوسائط الخلفية)، لكنه في حالة «مستوى عرض قابل للتصفح»: حلقات المعاملات الثلاث للطلب/التسجيل/الدفع تُرفض مباشرة من الخلفية بـ 422/40001 لغياب address_id والتحقق البشري PosterVerify، وi18n موصول بصفحة واحدة فقط، ومتعدد العملات غير ممتد عبر API.

### ما تم تنفيذه
- المكدس التقني والهيكل الهندسي موجودان فعليًا: pubspec.yaml/lock يثبّتان flutter_riverpod ^2.3.0 و go_router ^12.0.0 و dio ^5.3.0 و responsive_framework و cached_network_image و flutter_secure_storage و shared_preferences و intl ^0.20.2؛ lib/ إجمالًا 25 ملف Dart، ودلائل المنصات الست android/ios/macos/linux/windows/web كاملة
- GoRouter يكوّن 11 توجيهًا (app_router.dart): / و /products و /product/:id و /cart و /checkout و /orders و /profile و /addresses و /login و /register و /order/:id، ومقابلة 11 ملف صفحة كلها موجودة فعليًا
- بنية i18n التحتية: app_localizations.dart يرمّز 5 لغات (zh_CN/zh_HK/en/ja/ko) لكل منها 32 مفتاح ترجمة؛ locale_provider.dart عبر Riverpod StateNotifier + SharedPreferences يثبّت اللغة/العملة، localeProvider/currencyProvider مسجلان
- معترضات Dio موازية لوسائط الخلفية: _AuthInterceptor (Bearer token + عند 401 استدعاء /auth/refresh وإعادة المحاولة)، _LocaleInterceptor (Accept-Language + ترويسة API-Version، مقابل LocaleMiddleware/VersionRoute في الخلفية)، _PlatformInterceptor (ترويسة X-Platform، مقابل PlatformMiddleware في الخلفية)
- عقد طبقة API متسق: ApiResponse{code,msg,data} و PaginatedData{list,total,page,per_page} يطابقان تنسيق ApiResponse::success/paginate الموحد في الخلفية؛ apiBaseUrl يدعم التجاوز عبر --dart-define، مع معالجة خاصة لمحاكي Android 10.0.2.2
- home_screen ينفذ التكيف PC/اللوحي: أكبر من 1024 NavigationRail شريط جانبي + شبكة 4 أعمدة، الشاشات الضيقة NavigationBar تبويبات سفلية + شبكة عمودين (main.dart يعرّف ثلاث درجات MOBILE/TABLET/DESKTOP)؛ product_list سطح المكتب شريط جانبي أيسر 240px مع RangeSlider للسعر
- وحدة المنتجات قابلة للاستخدام: القائمة تدعم معاملات keyword/category_id/sort (ProductController::index في الخلفية يدعمها كلها، شاملة price_asc/desc و sales و newest)، صفحة التفاصيل تشمل SKU ChoiceChip، وإضافة للسلة POST /cart (CartController::store في الخلفية يتحقق من المخزون ويدمج كميات نفس SKU)؛ ProductCard قابل للنقر للتفاصيل
- سلة التسوق قابلة للاستخدام: حقول القائمة (id/title/image/price/quantity/selected) مطابقة لمخرجات CartController::index في الخلفية، تدعم الحذف DELETE /cart/{id}، ومدخل السداد يقفز إلى /checkout
- وحدة الطلبات أساسية قابلة للاستخدام: القائمة (order_no/status_text/pay_amount/currency_code موازية OrderController::index)، التفاصيل (شاملة تفاصيل items)، الإلغاء POST /orders/{id}/cancel (OrderController::cancel في الخلفية موجود)
- إدارة العناوين قابلة للاستخدام: قائمة/إضافة/حذف/تعيين افتراضي في /user/addresses موازية لواجهات UserController الأربع في الخلفية، النموذج يشمل علامة العنوان الافتراضي
- المصادقة الأساسية قابلة للاستخدام: login/register يستدعيان /auth/login و /auth/register و saveTokens إلى flutter_secure_storage (تخزين آمن للرموز)، و init() يستعيد حالة الدخول عند البدء؛ AuthService و ApiClient يشتركان في نفس مفتاح التخزين
- أدوات الاختبار والجودة: test/widget_test.dart اختبار دخاني (testWidgets واحد يتحقق من عرض ShopApp)؛ analysis_options.yaml يفعّل مجموعة قواعد flutter_lints الافتراضية

### الفجوات
- **حلقة الطلب مكسورة (قاتلة)**: CheckoutScreen._placeOrder يرسل {currency_code} فقط، لكن OrderController::store يتحقق إلزاميًا من address_id (غيابه 422 «عنوان الاستلام غير موجود»، و docs/api.md 5.3 يطلب address_id صراحةً)؛ و config/poster.php يدرج /api/orders في protected_routes، والتوجيه معلق عليه وسيطة PosterVerify، وFlutter لا يرسل X-Poster-Token → الطلب مرفوض حتمًا بـ 40001 «يلزم التحقق البشري»
- الدفع مفقود كليًا: checkout_screen فقط GET /payment/methods يعرض قائمة الطرق، ولا يستدعي أبدًا POST /payment/create و GET /payment/status، وبعد الطلب لا بدء دفع/استقصاء نتيجة، يخالف تسلسل الدفع في docs/features.md 2.2 (C→POST /payment/create→دفع SDK→webhook)
- التسجيل محجوب بالتحقق البشري: POST /auth/register محمي بـ PosterVerify (إعداد poster.php)، و RegisterScreen لم ينفذ الحصول/الترميز لـ X-Poster-Token، فطلب التسجيل مرفوض حتمًا بـ 40001
- i18n بنى القاعدة فقط دون تطبيق: AppLocalizations.of يُستدعى فعليًا في profile_screen.dart فقط (موقع واحد في lib كله)، وبقية الشاشات الـ11 نحو 66 نصًا مكتوبًا يدويًا خليط صيني/إنجليزي (home 'Home' و cart 'Shopping Cart' و register 'يرجى إدخال البريد الإلكتروني وكلمة المرور' و order_detail 'تم إلغاء الطلب' خليط ثنائي)، ووعد «واجهة 5 لغات» غير قابل للوفاء
- الوثائق لا تطابق الفعلي: apps/CLAUDE.md يدّعي «10 توجيهات» والفعلي 11 (زائد /order/:id)؛ يدّعي أن المكدس يشمل fl_chart و window_manager، لكن pubspec.yaml/lock لا يضمّان الحزمتين معًا؛ «Flutter 5 منصات» في features.md يدرج 6 منصات
- متعدد العملات غير ممتد عبر API: currencyProvider في العميل يُستخدم للتنسيق المحلي فقط، وطلبات قائمة/تفاصيل المنتجات وسلة التسوق لا تحمل معامل currency (الخلفية USD افتراضية)؛ ProductDetailScreen يستخدم '$' مكتوبًا يدويًا ويقرأ product.display_price (الخلفية تعلق display_price على مستوى sku فقط، ومستوى product ثابت null) → سطر VAT الشامل الضريبة لا يظهر أبدًا
- الترقيم والفلترة غير مكتملين: _page في ProductListScreen لا يزداد أبدًا وبلا تحميل بالتمرير (يعرض أول 20 بندًا فقط)؛ OrderListScreen بلا ترقيم؛ RangeSlider سعر سطح المكتب يمرر min_price/max_price لكن ProductController::index في الخلفية بلا أي منطق فلترة سعر (الفرز فقط يشير إلى min_price) → الشريط بلا أثر
- عيوب المتانة وحالة الدخول: شاشات _load عدا home كلها بلا try/catch، عند الوصول غير المسجل إلى /orders و /user/addresses وغيرها 401 تبقى DioException غير ملتقطة (حالة التحميل تعلق/استثناء غير معالج)؛ GoRouter بلا أي redirect لحارس الدخول (redirect-count=0)، غير المسجل يمكنه الوصول مباشرة إلى /cart /checkout /orders /addresses؛ «تسجيل الخروج» في Profile هو context.push('/login') وليس AuthService.logout()، ولا يمسح الرمز، خطأ وظيفي
- كود ميت وثغرات اختبار: ProductReviewList (product_review_list.dart) منفذ لكن بلا أي صفحة تشير إليه، وصفحة تفاصيل المنتج لا تعرض التقييمات؛ اختبار واحد دخاني فقط، بلا اختبارات نماذج/مكونات/تكامل؛ .github/workflows/ci.yml يغطي PHP فقط (phpunit + صياغة)، بلا مهمة flutter analyze/test؛ دليل assets/images فارغ لكن pubspec.yaml يعلن دليل الأصول هذا

### المخاطر
- سلسلة المعاملات الأساسية غير قابلة للاستخدام في Flutter: الطلب (نقص address_id + PosterVerify 40001) والدفع (بلا /payment/create) والتسجيل (PosterVerify 40001) ثلاثة مواضع تُرفض كلها من الخلفية، فالإطلاق وفق الكود الحالي يعطل تحويل الشراء مباشرة
- بلا حارس دخول + منطق تحديث 401 بلا إزالة تكرار متزامنة: طلبات متعددة 401 متزامنة تستدعي /auth/refresh بالتوازي (api_client.dart بلا قفل)، وعند فشل التحديث بلا تغطية تسجيل خروج، فقد تتعارض حالة الرمز
- النظام المزدوج i18n (جدول الكلمات + 66 نصًا مكتوبًا يدويًا) على المدى الطويل يسبب اختلاط لغة الواجهة، والنصوص الجديدة تُكتب يدويًا مباشرة، ووعد 5 لغات مع إعلان «تدويل ✅» في docs/VERSIONS.md غير قابل للوفاء، وتكلفة إعادة العمل تتراكم باستمرار
- عرض متعدد العملات منفصل عن الدفع الفعلي: الواجهة يمكنها التبديل JPY/KRW لكن السعر ما زال يعرض بالدولار مكتوبًا يدويًا وAPI ما زال يسعّر بالدولار، ومبالغ تسوية متعدد العملات لا تتطابق، مخاطرة اتساق معاملات
- بلا بوابة CI لـ Flutter و flutter/dart analyze في هذه البيئة لا يمكن تشغيلها للتحقق بسبب SDK للقراءة فقط: الاعتماد على مراجعة بشرية لـ 25 ملفًا، وخطر تراجع مشاكل الترجمة/الساكنة مرتفع (المشكلات التاريخية المسجلة في docs/VERSIONS.md مثل تعارض intl و Timer المعلق تفتقر للحماية الآلية)

### التوصيات
- [عالية] فتح حلقة الطلب: صفحة السداد تضيف اختيار العنوان (إعادة استخدام /user/addresses، إعادة تعبئة الافتراضي)، إرسال address_id+currency_code، وربط تدفق تحقق PosterVerify في الخلفية (بعد الحصول على X-Poster-Token إرسال POST /orders)، ثم تنفيذ صفحة دفع POST /payment/create + استقصاء GET /payment/status
- [عالية] ربط AppLocalizations كاملًا: استبدال نحو 66 نصًا مكتوبًا يدويًا عبر 11 شاشة بـ translate(key) وإكمال المفاتيح المفقودة (نماذج العناوين وحالات الطلبات وتلميحات الأخطاء وغيرها)، حذف التعريف المكرر AppTheme.supportedLocales و locale_provider.supportedLocales، وتوحيد المصدر الوحيد
- [عالية] إضافة redirect حارس دخول في GoRouter (الوصول غير المسجل إلى /cart /checkout /orders /addresses يعيد التوجيه إلى /login)، و«تسجيل الخروج» في Profile يُعدل إلى استدعاء AuthService.logout() ثم العودة للرئيسية، وتنظيف حالات الصفحات المرتبطة بحالة الدخول
- [متوسطة] شاشات _load كلها تضيف try/catch وحالات خطأ/فارغة UI (حاليًا home فقط يقوم بتدهور الاستثناء)؛ تحديث 401 في ApiClient يضيف قفلًا أحادي الطيران وتغطية فشل تسجيل الخروج؛ سلة التسوق تضيف زيادة/نقصان الكمية (PUT /cart/{id})
- [متوسطة] طلبات تفاصيل/قائمة المنتجات تحمل معامل currency، والسعر يقرأ sku.display_price أو حقل display_price، واستبدال كل '$' المكتوب يدويًا بـ CurrencyFormatter؛ ProductController::index في الخلفية يضيف فلتر min_price/max_price، والواجهة تنفذ ترقيم التمرير
- [متوسطة] RegisterScreen والعمليات الحساسة تربط PosterVerify: تنفيذ تحقق منزلق/لغز للحصول على X-Poster-Token (واجهة تحقق poster في الخلفية أو دمج واجهة أمامية)، لضمان عدم اعتراض التسجيل/الطلب بـ 40001
- [منخفضة] إكمال اختبارات Flutter: اختبارات وحدة fromJson لنموذجي Product/Order، اختبار دخاني للتوجيهات (إمكانية الوصول إلى 11 توجيهًا)، اختبارات widgets للسلة/العناوين، وإضافة مهمتَي flutter analyze + flutter test في GitHub Actions (موازاة ci.yml الخاص بـ PHP)
- [منخفضة] تصحيح الوثائق والكود الميت: apps/CLAUDE.md عدد التوجيهات 10→11، إزالة إعلاني fl_chart/window_manager؛ ربط ProductReviewList بصفحة تفاصيل المنتج أو حذفه؛ تنظيف دليل assets/images الفارغ أو إضافة موارد placeholder

---

## 4. عميل HarmonyOS (apps/harmonyos/)

### ملخص الوضع الحالي
عميل HarmonyOS (HarmonyOS NEXT API 12+، ArkTS + ArkUI) لديه هيكل قابل للترجمة من 9 صفحات + ApiClient/AppState/ProductCard كاملًا، ونقاط نهاية API الخلفية وبنية الاستجابات كلها متطابقة فعليًا (AUDIT-REPORT يسجل إصلاح 27 خطأ ArkTS ونجاح البناء)، لكن عمق الوظائف يقف عند «طبقة العرض»: سلسلة السداد-الطلب الرئيسية مكسورة (نقص address_id)، Profile قشرة ثابتة، بلا ربط متعدد العملات/اللغات، بلا اختبارات و CI، و99 منتج بناء دخل المستودع خطأً، والفجوة الإجمالية عن عميل Flutter واضحة.

### ما تم تنفيذه
- 9 صفحات ArkTS كلها موجودة ومسجلة في main_pages.json (Index/ProductDetail/Cart/OrderList/Checkout/Profile/Login/Register/Search)، مع EntryAbility و ApiClient و AppState و ProductCard، قابلة للترجمة (تدعمها ذاكرة التخزين المؤقت entry/build وسجل إصلاح M3 في AUDIT-REPORT.md، تقييم B+)
- ApiClient يغلّف @ohos.net.http: GET/POST/DELETE، Bearer token، API-Version(2026-05-20)، Accept-Language، ترويسة X-Platform: harmonyos، وواجهات QueryParams/RequestBody التصريحية تلبي قيود حرفية ArkTS
- AppState مفردة: token/locale/currency تُثبَّت عبر @ohos.data.preferences (تخزين erik_shop)، cartCount يُسحب عبر /cart ويُحسب، logout ينظف الرمز
- توجيهات الخلفية واستدعاءات العميل تطابق بندًا ببند: /auth/login و /auth/register و /products و /products/{id} و /banners و /search و /cart(GET/POST/DELETE) و /orders(GET/POST) و /shipping/calculate و /payment/methods كلها مسجلة في service/config/route.php والمتحكمات موجودة
- بنية الاستجابات متسقة مع تحليل العميل: products/orders/search تعيد data.list (شاملة خريطة status_text الصينية ودعم sort=sales)، cart يعيد مصفوفة items (title/image/price/quantity)، shipping يعيد data.options، payment/methods يكشف stripe/paypal فقط
- الصفحة الرئيسية تنفذ دائري Banner (/banners?position=home) + شبكة مزدوجة الأعمدة للسلع الساخنة (/products?per_page=10&sort=sales)، شاملة شريط البحث العلوي ومدخل أيقونة السلة
- صفحة البحث تنفذ البحث بالكلمات الرئيسية (/search?keyword=&per_page=40) وعدّاد النتائج والحالة الفارغة وحالة التحميل، وتعيد استخدام ProductCard
- صفحة السلة تنفذ القائمة/حساب الإجمالي/الحذف (DELETE /cart/{id}) والعرض الفارغ، وقابلة للانتقال إلى السداد
- صفحة تفاصيل المنتج تنفذ حالة التحميل وعرض الصورة الرئيسية/العنوان/السعر/الوصف والإضافة إلى السلة (أخذ أول SKU واستدعاء POST /cart)
- قائمة الطلبات تنفذ فلترة حالة Tabs (الكل/قيد الدفع/تم الشحن/مكتمل → status 0/2/4) وحالتي التحميل/الفارغ
- صفحتا الدخول/التسجيل تستدعيان /auth/login و /auth/register، والتسجيل يثبّت عبر AppState.setToken
- صفحة السداد تعرض منتجات الطلب/خيارات الشحن (اختيار Radio)/طرق الدفع وتحسب الإجمالي وتدعم إجراء الإرسال
- سلسلة تحديد المنصة كاملة: X-Platform: harmonyos تطابق قائمة البيضاء 8 منصات في service/app/middleware/PlatformMiddleware.php
- إعداد المشروع مستوفٍ: compatibleSdkVersion 5.0.0(12) (API 12+)، stageMode، deviceTypes phone/tablet/2in1، hvigor modelVersion 5.0.0

### الفجوات
- **سلسلة السداد-الطلب الرئيسية مكسورة**: placeOrder في Checkout.ets يرسل {currency_code:'USD'} فقط، والخلفية OrderController.php:88-96 تتطلب وتتحقق إلزاميًا من address_id (عدمه 422 عنوان الاستلام غير موجود)؛ CartController.php:113 يسوّي منتجات selected=1 فقط بينما Cart.ets بلا قدرة تحديد؛ والعميل بلا أي صفحة إدارة عناوين (توجيه قائمة عنوان الاستلام في Profile فارغ) — الطلب يفشل حتمًا
- تدفق الدفع غير موصول: صفحة Checkout تعرض وتختار طريقة الدفع، لكن placeOrder لا يمرر معاملات الدفع ولا يستدعي POST /payment/create، يخالف «الدفع (Stripe/PayPal) كامل» في docs/features.md
- Profile.ets قشرة ثابتة: isLoggedIn @State أولي false ولا يقرأ AppState أبدًا (بعد الدخول ما زال يعرض دخول/تسجيل)؛ بند «دخول/تسجيل» بلا onClick؛ 6 قوائم المفضلة/عنوان الاستلام/بطاقة الهدايا/اللغة/العملة/إعدادات الخصوصية كل توجيهاتها فارغة وغير قابلة للاستخدام؛ بلا مدخل تسجيل خروج
- إدارة حالة الدخول مزدوجة المسار وغير متسقة: Login.ets يكتب access_token/refresh_token مباشرة عبر getPreferences متجاوزًا AppState.setToken، ورموز AppState في الذاكرة غير متزامنة (isLoggedIn() يعيد false)؛ Register.ets يمر عبر AppState، مساران منفصلان؛ وكلاهما لا يحدّث cartCount بعد نجاح الدخول
- مدخل تصنيفات الصفحة الرئيسية فارغ دائمًا: loadData في Index.ets يطلب /banners و /products فقط، ومصفوفة categories بلا أي إسناد؛ Banner بلا انتقال بالنقر؛ مكوّن Search العلوي بلا onSubmit يدخل صفحة البحث
- متعدد العملات/اللغات غير موصول: currency في AppState يثبَّت لكن لا يُمرر إلى API أبدًا (Checkout يرمّز country:'US'/currency:'USD'، و shipping يرمّز dest_country_id:1/weight:500)؛ كل نصوص UI مكتوبة يدويًا بالصينية و '$' (docs/features.md سطر 293 يعترف أيضًا «ArkTS مكتوب يدويًا»)، وبلا دليل موارد en_US وغيرها، والفجوة عن 5 لغات في Flutter واضحة
- بلا اختبارات وبوابات جودة: تحت apps/harmonyos لا يوجد دليل ohosTest وبلا أي اختبار .ets؛ .github/workflows/ci.yml فحص صياغة PHP + اختبارات وحدة فقط، بلا job بناء هارموني؛ بلا إعداد أدوات lint/تنسيق
- مشكلات صحة المستودع: git يتتبع 99 منتج build لـ entry/build وذاكرة .hvigor المؤقتة (76% من 131 ملفًا متتبعًا، شاملة msgpack/tsbuildinfo/تقارير الترجمة)، و .gitignore بلا قواعد تجاهل هارموني؛ والمستودع بلا سكربت wrapper hvigorw (أمر `hvigorw assembleHap` الذي يدّعيه apps/CLAUDE.md لا يمكن تنفيذه مباشرة، يلزم hvigor عام أو DevEco Studio)
- متانة ApiClient غير كافية: request/JSON.parse بلا try-catch وبلا ضبط مهلة؛ delete() ينقصها ترويسة X-Platform؛ refresh_token مخزن لكن لا يُستخدم للترقية أبدًا؛ AppState.init() في EntryAbility.onCreate بلا await، فطلب إطار الصفحة الأول قد يسبق جاهزية الرمز (سباق)؛ baseUrl الافتراضي مكتوب يدويًا http://10.0.2.2:8787/api مخصص للمحاكي فقط

### المخاطر
- حلقة المعاملات الأساسية غير موصولة: السداد والطلب يعيدان 422 حتمًا (نقص address_id)، فإذا أُطلق وفق تموضع «كامل» في docs/features.md، يفشل المسار الرئيسي مباشرة، عيب على مستوى منع الإطلاق
- بلا اختبارات و CI بلا job هارموني: نوعية ArkTS الصارمة (قيود الحرفية/بناء الجذر الواحد) خطر تراجع مرتفع، و27 خطأ ترجمة في AUDIT-REPORT M3 مثال سابق، وأي تغيير لاحق في الصفحات بلا ضمان آلي
- منتجات البناء دخلت المستودع + بلا wrapper: انتفاخ حجم المستودع (ثنائيات مثل ذاكرة msgpack المؤقتة)، سهولة توليد diffs بلا معنى، والبيئة الجديدة لا يمكنها إعادة بناء بالتوثيق، وربط CI لهارموني يفتقر أيضًا مدخل بناء موحد
- إدارة الحالة مزدوجة المسار (مفردة AppState مقابل @State المحلي للصفحات + كتابة Login المباشرة إلى preferences) وبلا آلية تفاعلية: عند الربط لاحقًا بحالات مشتركة مثل المفضلة/العناوين/تبديل العملة يسهل حدوث تناقض بين الذاكرة والثبات
- تكيف الجهاز الحقيقي/الإصدار مفقود: baseUrl الافتراضي يشير إلى عنوان محاكي Android وبلا آلية إدراك منصة (Flutter لديها إصلاح M4)، والجهاز الحقيقي HarmonyOS وبيئة HTTPS الإنتاج غير قابلين للاستخدام

### التوصيات
- [عالية] فتح حلقة السداد-الطلب: إضافة صفحة قائمة/إنشاء عناوين الاستلام تربط واجهات UserAddresses ذات الصلة (الخلفية جاهزة)، صفحة Cart تضيف تحديد selected (الخلفية تسوّي منتجات selected=1 فقط)، Checkout.placeOrder يمرر address_id + selectedShipping + العملة، والتحقق من نجاح POST /orders
- [عالية] إصلاح Profile واتساق حالة الدخول: Profile.aboutToAppear يقرأ AppState.isLoggedIn() ويحدّث تفاعليًا، «دخول/تسجيل» بالنقر يقفز إلى Login، وإضافة تسجيل الخروج؛ توحيد Login/Register على AppState.setToken واستدعاء refreshCartCount
- [عالية] إنشاء بوابات الاختبار و CI: إضافة ohosTest (ArkXTest) تغطي على الأقل تحليل طلبات ApiClient (يمكن حقن mock server) وقراءة/كتابة ثبات AppState؛ ci.yml يضيف job بناء hvigor (باستخدام hvigor عام أو إكمال wrapper)، لمنع تراجع الترجمة
- [متوسطة] حوكمة المستودع: .gitignore يضيف قواعد apps/harmonyos/**/build و **/.hvigor و **/oh_modules مع git rm --cached لتنظيف 99 منتجًا دخل المستودع؛ إكمال wrapper hvigorw (تثبيت @ohos/hvigor عبر ohpm) ليصبح أمر apps/CLAUDE.md قابلًا للاستخدام
- [متوسطة] ربط متعدد العملات/اللغات: Checkout/Index/ProductDetail تستخدم AppState.currency ومعامل QueryParams.currency (الخلفية تدعم تسعيرًا متعدد العملات)؛ ترحيل نصوص UI إلى resources/base ودليل لغات en_US، وإضافة الإنجليزية أولًا لموازاة حل معترض i18n في Flutter
- [متوسطة] إكمال تجربة الرئيسية والبحث: loadData يضيف /categories لتحميل مدخل شبكة التصنيفات، و Banner بالنقر ينتقل إلى link_url، ومربع البحث العلوي onSubmit يدخل صفحة Search؛ نتائج Search تربط الترقيم (حاليًا per_page 40 سحب لمرة واحدة)
- [متوسطة] تعزيز متانة ApiClient: توحيد try/catch والمهلات (http.RequestOptions timeout)، عند 401 استخدام refresh_token المخزن للتحديث التلقائي وإعادة المحاولة، delete() يضيف ترويسة X-Platform، baseUrl يدعم إعداد وقت التشغيل (محاكاة إدراك منصة Flutter)
- [منخفضة] تصحيح سباق التهيئة وصفحة التفاصيل: في EntryAbility await AppState.init() ثم loadContent (أو الصفحة تنتظر الجاهزية)؛ ProductDetail يضيف UI لاختيار SKU متعدد و«شراء فوري» كإجراء طلب حقيقي، وربط صور المنتجات بذاكرة Image

---

## 5. الأمان والامتثال

### ملخص الوضع الحالي
المنصة لديها تنفيذ حقيقي ومكتمل نسبيًا في كشف هجمات WAF و JWT رموز مزدوجة وتشفير AES للواجهات وتشفير حقول Encryptable وخلط Hashids والتحقق من توقيع Webhook الدفع وإدارة المفاتيح (22 اختبارًا ناجحًا)، لكن محرك قواعد إدارة المخاطر و KYC وطبقة تنفيذ GDPR/CCPA لها بنية جدول و CRUD إداري فقط، ومنطق الأعمال الأساسي مفقود، لا يطابق ادعاء «كامل/✅» في docs/features.md و docs/VERSIONS.md.

### ما تم تنفيذه
- كشف هجمات WAF: SecurityMiddleware في service و admin كلاهما يغلّف SecurityGuard من erikwang2013/security-php v1.1.6، و config/plugin/erikwang2013/security-php/app.php يكوّن 31 كاشفًا (28 block و 3 log: header_injection/ssti/nosql_injection)، شامل XSS/SQLi/XXE/SSRF/عبور المسار/رفع الملفات/CSRF/Host/DNS rebinding وغيرها، مع قائمة سوداء IP (5 مرات/60 ثانية → حظر 900 ثانية) وترويسات استجابة أمنية (nosniff/DENY/Permissions-Policy/إخفاء Server)
- حماية القوة الغاشمة: عدّاد Redis في service erik_brute:{ip}:{login|register} 10 مرات/60 ثانية (SecurityMiddleware::checkBrute)، و5 مرات/300 ثانية في admin
- مصادقة JWT: config/jwt.php (HS256، access 7200s/refresh 1209600s، issuer/audience/leeway)، app/common/Jwt.php بمفتاح فارغ fail-closed (سلسلة تراجع JWT_SECRET→JWT_SECRET_KEY)، وسيطة JwtAuth ترفض رموز غير type access، AuthController التسجيل/الدخول يصدران رمزين، نقطة نهاية refresh تدوّر الرمزين (firebase/php-jwt v6.11.1 مثبّت)
- تشفير AES للواجهات: app/common/Encryption.php (AES-256-CBC، IV عشوائي في كل مرة، base64(iv+نص مشفر)، التحقق من طول المفتاح 16/24/32 بايت)، EncryptionMiddleware يدعم X-Encrypted:1 فك تشفير الطلبات و X-Encrypt-Response:1/X-Encrypt-Fields تشفير حقول الاستجابة، ويتخطى /api/health و /api/ping و /apidoc، مسجل كآخر مستوى في مكدس الوسائط العام
- تشفير حقول Encryptable: 31 نموذجًا يستخدم trait Erik\Encryptable\Encryptable (Users email/mobile، UserKyc real_name/id_number، UserAddresses، PrivacyRequests.email، PaymentGateways.api_key وغيرها)، كلمات المرور bcrypt(password+salt) مع ملح عشوائي لكل مستخدم، $hidden يمنع تسريب التسلسل
- خلط Hashids: config/hashids.php + إعداد الإضافة، HashidsHelper بملح فارغ fail-closed، وسيطتا HashidsDecode (معاملات التوجيه + فك تلقائي لحقول نهاية _id) و HashidsEncode مفعلتان في service و admin معًا، والمتحكمون يعيدون معرفات مشفرة
- أمان الدفع: StripeGateway يتحقق من التوقيع بـ Stripe\Webhook::constructEvent، PayPalGateway بـ /v1/notifications/verify-webhook-signature الرسمي (يتطلب PAYPAL_WEBHOOK_ID)؛ PaymentController::webhook التحقق من التوقيع → تحديث بقوة تكرار (مزلاج ذري لحالة الطلب status=0 لمنع الإدخال المكرر) → إنشاء PlatformSettlements للتسوية؛ واجهة create تكشف stripe/paypal المنفذة فقط؛ جدول erik_payments يشمل حقل three_ds_status؛ واجهة تنفيذ الاسترداد في admin عبر AdminKeyMiddleware (X-Admin-Key، مقارنة hash_equals)
- إدارة المفاتيح: .env.example/.env تشمل JWT_SECRET/JWT_SECRET_KEY/HASHIDS_SALT/ENCRYPTION_KEY/ADMIN_API_KEY/STRIPE_SECRET_KEY وغيرها، و .env في .gitignore؛ معالج تثبيت الويب (InstallController) يولّد مفاتيح عشوائية بـ random_bytes؛ Jwt/Encryption/HashidsHelper كلها fail-closed تجاه المفاتيح المفقودة
- تحديد المعدل والتحقق البشري: RateLimitMiddleware نافذة منزلقة Redis ZSET (افتراضي 60 ثانية/100 مرة، الدخول 60 ثانية/10، التسجيل 300 ثانية/5، الدفع 60 ثانية/5، الطلب 10 ثوانٍ/3، البحث ثانية واحدة/10)، PosterVerify يحمي التوجيهات الحساسة للتسجيل/الطلب/الدفع
- أدوات الاختبار والجودة: tests/ إجمالًا 22 tests/45 assertions اختبار فعلي ALL PASS (SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3)، phpunit.xml، phpstan.neon (level 5)، .php-cs-fixer.php، .github/workflows/ci.yml (مصفوفة PHP 8.3/8.4 + خدمات MySQL/Redis)؛ README يسجل CVE معروفًا واحدًا منخفض الخطورة في composer audit (firebase/php-jwt <7.0.0، مقيد بـ jwt-webman ^6.0)

### الفجوات
- محرك قواعد إدارة المخاطر غير منفذ: docs/features.md §3.3 يدّعي أن تدفق الطلب يشمل «تقييم المخاطر (RiskEngine::score)» و VERSIONS.md يدّعي «محرك قواعد إدارة المخاطر ✅»، لكن grep على المشروع كله RiskEngine 0 إصابة؛ حقلا erik_orders.risk_score/risk_result لم يُكتبا أبدًا بأي كود، و RiskRules/RiskLogs نموذجان فارغان فقط، و config/risk.php إعداد بلا نقاط تنفيذ؛ service/database/seeders/ فيه countries.php فقط، بلا بيانات أولية «قواعد إدارة المخاطر» التي يدّعيها CLAUDE.md؛ ShopRiskRuleController/ShopRiskLogController غير معلقين بقائمة admin
- KYC بلا مدخل إرسال من جهة المستخدم: جدول erik_user_kyc/نموذج UserKyc/متحكم CRUD إداري موجودون، و OrderController عند الطلب يتحقق من status=1 لدول kyc_required (config/country.php يدعم KR فقط)، لكن service/config/route.php بلا أي توجيه إرسال/استعلام KYC، فلا يمكن للمستخدم إرسال بيانات الهوية بنفسه، والحلقة مفقودة
- GDPR/CCPA تسجيل الطلبات فقط بلا منطق تنفيذ: PrivacyController يكتب privacy_requests فقط (status=pending) ويعد بالمعالجة خلال 30 يومًا، بلا كود تنفيذ فعلي لحذف/تصدير البيانات/opt-out؛ ShopPrivacyController CRUD حالة فقط؛ data_retention/retain_on_deletion في config/privacy.php بلا مهمة تنظيف مقابلة؛ جدول erik_cookie_consents بلا أي طرف كتابة (بلا مكوّن Cookie Consent أمامي أو API)
- العميل لا يستهلك تشفير AES للواجهات ورمز هارموني مخزن نصًا صريحًا: طلبات Flutter/HarmonyOS تحمل Authorization/Accept-Language/API-Version/X-Platform فقط، grep بلا دعم X-Encrypted/X-Encrypt-Response (تشفير الواجهات قدرة أحادية الاتجاه للخادم فقط، و«التشفير ثلاثي الطبقات» في docs غير فعّال على الطرف)؛ AppState في هارموني يخزن الرمز عبر @ohos.data.preferences (نص صريح)، وFlutter يستخدم flutter_secure_storage، أمان غير متسق عبر الأطراف
- 3DS بلا دليل كود صريح: StripeGateway::createPayment لا يضبط payment_method_options[card][request_three_d_secure]، وحقل three_ds_status لا يُكتب أبدًا، و3DS يعتمد سياسة Stripe الافتراضية؛ «التحقق 3DS» الذي يدّعيه README/features.md بلا دعم كود؛ Klarna/Adyen إعداد placeholder فقط (تعليق PaymentController يوضح الفلترة في الواجهة الأمامية)، لا يطابق «Stripe/PayPal/Klarna/Adyen و BNPL» في README
- بنود المصادقة المفقودة: بلا تدفق كلمة مرور منسية/إعادة تعيين (grep forgot/reset صفر إصابة)، بلا تحقق بريد، JWT بلا آلية إبطال (الرمز ما زال صالحًا بعد تغيير كلمة المرور/تسجيل الخروج)، نقطة نهاية refresh بلا تحديد معدل منفصل
- CI لم يُدخل تدقيق أمان التبعيات والتحليل الساكن: composer audit يظهر في توثيق README فقط، ci.yml فحص صياغة PHP + PHPUnit فقط، بلا خطوات composer audit/phpstan/php-cs-fixer؛ excludePaths في phpstan.neon يستثني config/plugin (شامل إعدادات الإضافة الأمنية)

### المخاطر
- حظر أمان التبعيات: firebase/php-jwt v6.11.1 ضمن نطاق تأثير CVE-2025-45769 المُعلن في الوثائق (<7.0.0)، ومقيد بإجبار erikwang2013/jwt-webman ^6.0 فلا يمكن ترقيته، ثغرة معروفة مفتوحة منذ فترة طويلة (استخدام HS256 المتناظر غير متأثر، لكن يلزم تتبع مستمر للأعلى)
- مناطق عمياء في تغطية الكشف: كواشف نوع الترويسات csrf_origin/host_header/dns_rebinding/request_smuggling تعتمد $_SERVER، وقد يفوّت الكشف في بيئة Workerman غير CGI (docs/security-review.md §5.1 يعترف بذلك)؛ قائمة سوداء IP بتخزين file في sys_get_temp_dir، تفقد بعد إعادة تشغيل Docker ولا تشترك بين مثيلات متعددة، والمهاجم بتبديل IP يتجاوزها (الإعداد يترك تخزين redis لكنه غير مفعّل)
- ثغرة تناقض إعلانات الامتثال مع التنفيذ: الإعلان الخارجي وفق الوثائق «إدارة مخاطر كاملة/KYC كامل/GDPR كامل»، والواقع إنشاء الطلب بلا أي تقييم مخاطر يُفرج مباشرة (خطر طلبات احتيالية)، وKYC لا يمكن إرساله ذاتيًا، وطلبات الحذف لا ينفذها أحد، فإذا وُعدت قدرات الامتثال بذلك خارجيًا يشكل خطر امتثال فعلي
- إدارة المفاتيح ضعيفة نسبيًا: معالج التثبيت يولّد ENCRYPTION_KEY بـ bin2hex(random_bytes(16)) (32 حرف hex = 128bit إنتروبيا، دون 256bit) و HASHIDS_SALT بـ bin2hex(random_bytes(8)) (64bit إنتروبيا)؛ ENCRYPTION_PREVIOUS_KEYS بلا أتمتة تدوير؛ عملية webman المقيمة تعديل .env يتطلب reload ليُفعّل

### التوصيات
- [عالية] تنفيذ محرك قواعد إدارة المخاطر: وفق config/risk.php + جدول erik_risk_rules تطبيق RiskEngine::score (أحداث user_register/user_login/order_create/payment_create/refund_request)، الاستدعاء في OrderController::store/PaymentController::create/AuthController، كتابة risk_score/risk_result و RiskLogs، وفي الوضع الجانبي طلبات النقاط العالية توضع status=8 قيد المراجعة (مقابل فرع «قيد المراجعة» في آلة حالة الطلب)، وتعليق ShopRiskRule/ShopRiskLog في قائمة admin
- [عالية] إكمال حلقة KYC: إضافة POST /api/kyc (إرسال بيانات الهوية، real_name/id_number عبر تشفير Encryptable) و GET /api/kyc/status، وقبول مراجعة admin يضع status=1، ربطًا بالتحقق الحالي في OrderController؛ إكمال بيانات أولية/أمثلة KYC
- [عالية] تنفيذ طبقة تنفيذ GDPR/CCPA: إضافة مهمة مجدولة لمعالجة طلبات الخصوصية (وفق retain_on_deletion الاحتفاظ بالحقول الضريبية، بعد فترة سماح deleted_user_grace 30 يومًا حذف بيانات المستخدم، توليد ملف تصدير data_portability، opt_out يكتب علامة الحجب)؛ إضافة مكوّن Cookie Consent و POST /api/privacy/cookie-consent كتابة erik_cookie_consents؛ إعداد data_retention يُطبق كمهمة تنظيف
- [عالية] ربط تشفير الواجهات والتخزين الآمن في العميل: Flutter/HarmonyOS يدعمان X-Encrypted/X-Encrypt-Response (المفتاح يُوزع عبر قناة آمنة)، ورمز هارموني ينتقل إلى تخزين KeyStore/security.asset بديلًا عن نص preferences الصريح
- [متوسطة] تقوية أمان الدفع: Stripe createPayment يضبط صراحةً request_three_d_secure='automatic' ويعيد كتابة three_ds_status؛ واجهات استعلام/استرداد payments تضيف التحقق من ملكية المستخدم (status محقق أصلًا، الاسترداد/التصدير يحتاج إعادة فحص)؛ تصحيح «كامل» في README/VERSIONS لـ Klarna/Adyen بالمزامنة أو إكمال التنفيذ
- [متوسطة] إدخال أمان التبعيات في CI: ci.yml يضيف خطوات composer audit و phpstan (فك config/plugin أو التحقق منفردًا) و php-cs-fixer --dry-run؛ إنشاء تتبع CVE، وعند دعم jwt-webman لـ php-jwt ^7 الترقية فورًا
- [متوسطة] تقوية المصادقة: تنفيذ تدفق إعادة تعيين كلمة المرور (رمز بريد + رمز إعادة تعيين لمرة واحدة)؛ إبطال JWT (قائمة حظر Redis أو رقم إصدار الرمز، يُبطل بعد تغيير كلمة المرور/تسجيل الخروج)؛ نقطة نهاية refresh تربط بتحديد المعدل وكشف إعادة التشغيل
- [منخفضة] تقوية المفاتيح والكشف: ENCRYPTION_KEY يُعدل إلى مفتاح base64 خام 32 بايت (256bit إنتروبيا)، وملح hashids يُقوّى إلى 16 بايت فأكثر؛ تخزين security-php ينتقل إلى وضع redis لمشاركة قائمة سوداء IP؛ وفق توصية security-review.md §5.1 تمرير الترويسات صراحةً إلى $meta الخاص بـ SecurityGuard لإكمال كشف نوع الترويسات

---

## 6. النشر / البيانات / جودة الاختبار

### ملخص الوضع الحالي
تنسيق نشر Erik Shop (nginx→service:8787/admin:8788 + MySQL/Redis/ES) وبنية 117 جدولًا واختبارات الوحدة (22 tests/45 assertions اختبار فعلي كلها ناجحة) أساسها متين والوثائق-الكود متطابقان تقريبًا، لكن أدوات التحليل الساكن تنهار فورًا (حد ذاكرة PHPStan 128M)، وإعدادات الجودة في جهة admin مفقودة كليًا، وملف بيانات GeoIP مفقود، وبعض المهام المجدولة تدور فعليًا فارغة لعدم إعداد API الخارجية، وحاويات الإنتاج تحمل مخاطر تعرض تبعيات dev ووسائط بلا مصادقة.

### ما تم تنفيذه
- docker-compose.yml ينسق 6 خدمات كاملًا (nginx/service/admin/mysql/redis/elasticsearch)، كلها مع healthcheck + بدء مشروط depends_on + أحجام بيانات مسماة + شبكة جسر، و `docker compose config` تحقق فعلي ناجح؛ nginx عبر docker/nginx/conf.d/shop.conf بوكيل عكسي upstream keepalive إلى service:8787 و admin:8788 (مضيفان افتراضيان api.erik.xyz/admin.erik.xyz)
- service/Dockerfile و admin/Dockerfile كلاهما على أساس php:8.3-cli-alpine، يثبّتان امتدادات pdo_mysql/bcmath/opcache/gd/intl/sockets/redis وغيرها مع composer install --no-dev --optimize-autoloader (جهة service تشمل أيضًا إعداد إنتاج OPCache docker/opcache.ini)
- CI (.github/workflows/ci.yml) يكوّن مصفوفة PHP 8.3/8.4 + حاويات خدمة MySQL 8.0/Redis 7، وينفذ composer install وفحص صياغة php -l (دليلا service+admin) و PHPUnit؛ Makefile يوفر 14 أمرًا start/stop/test/lint/check/fix/install/docker-up وغيرها
- PHPUnit اختبار فعلي ناجح: 22 tests / 45 assertions ALL PASS (SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3، phpunit.xml يستخدم schema 12.5)
- phpstan.neon يكوّن level 5 (paths=app+config، شامل ignoreErrors للدوال الديناميكية Eloquent/webman)؛ اختبار فعلي 0 خطأ تحت --memory-limit=1G
- service/.php-cs-fixer.php يكوّن PSR-12 وقواعد no_unused_imports/ordered_imports وغيرها، يغطي app+config؛ .editorconfig يوحد الترميز والإزاحة
- install.sql اختبار فعلي يشمل 117 جدولًا (7 جداول نظام wa_ + 110 جداول أعمال erik_، InnoDB/utf8mb4_unicode_ci)، و110 نماذج أعمال في جهة service تطابق 110 جداول erik_ واحدًا لواحد (جداول وحدات B2B/الاشتراك/سلسلة التوريد وغيرها من 12 وحدة مكتملة)، ويُستورد تلقائيًا عبر docker-entrypoint-initdb.d في صورة MySQL الرسمية
- معالج تثبيت الويب موجود فعليًا (admin/plugin/admin/app/controller/InstallController.php step1/step2)، يستورد install.sql الجذري ويولّد service/.env و admin/.env (شامل مفاتيح JWT/Hashids/AES العشوائية)؛ ملفات .env من نوع المفاتيح مستثناة في .gitignore وغير داخل المستودع
- 10 عمليات مهام مجدولة (config/process.php يسجل exchange_rate/shipment_tracking/product_feed/recommendation/compliance/return_expire/price_alert/payment_reconcile/settlement/platform_order_sync cron) لكل منها عملية مقيمة مستقلة ودورة
- مكدس الوسائط مسجل وفق الوثائق: Cors→Security→RateLimit→Platform→GeoIp→Locale→HashidsDecode→VersionRoute→HashidsEncode→Encryption (config/middleware.php اختبار فعلي 10 عامة + PosterVerify/JwtAuth/AdminKey على مستوى التوجيه، 14 وسيطة تطابق الوثائق)
- نقاط نهاية API الـ71 في docs/api.md تتطابق عمليًا مع service/config/route.php واحدًا لواحد تقريبًا (شامل /health فحص الاستبقاء الحقيقي db+redis)؛ ملفات Flutter الـ25 وملفات HarmonyOS الـ14 و82 متحكمًا/76 نموذجًا في admin في docs/features.md كلها متطابقة مع إحصائيات الكود

### الفجوات
- PHPStan غير قابل للاستخدام فورًا: phpstan.neon بلا إعداد memoryLimit، وعند 128M الافتراضية ينهار عامل التوازي مباشرة (اختبار فعلي إعادة إنتاج 'reached configured PHP memory limit: 128M')، وهدف Makefile check و CI كلاهما بلا --memory-limit، فبوابة التحليل الساكن لا تعمل فعليًا
- إعدادات الجودة في جهة admin مفقودة كلها: بلا phpstan.neon وبلا .php-cs-fixer.php وبلا phpunit.xml وبلا دليل tests/، و require-dev في composer.json بلا phpstan؛ اختبار فعلي مقطع admin في `make fix` (admin && vendor/bin/php-cs-fixer fix) بلا إعداد يدخل موجه تفاعلي 'create config file?' ويتعطل، و`make check` يغطي service فقط
- CI لم يدمج phpstan و php-cs-fixer (php -l + PHPUnit فقط)، ويختبر service فقط؛ حاويات خدمات MySQL/Redis التي يشغلها CI بلا أي اختبار تكامل يتصل بـ MySQL، وتغطية الاختبارات ما زالت عند 4 فئات أدوات، و111 نموذجًا/39 متحكمًا/14 وسيطة/10 مهام مجدولة صفر اختبار
- ملف بيانات GeoIP مفقود: config/geoip.php يشير إلى service/database/geoip/GeoLite2-Country.mmdb، لكن الدليل اختبار فعلي فارغ وبلا أي سكربت تنزيل/إرشاد، وGeoIpMiddleware يسير في فرع ترقية file_exists فقط، وادعاء 'GeoIP كامل' في features.md لا يطابق الواقع
- 3 مهام مجدولة تدور فارغة لعدم إعداد API الخارجية: في config/cron.php tracking_api_url و compliance_source_url و platform_sync_url كلها سلاسل فارغة، و ShipmentTrackingCron/ComplianceCron/PlatformOrderSyncCron تسجل سجل «تخطي» فقط (تعليق الكود يؤكد أيضًا)؛ WebSocket IM لخدمة العملاء غير منفذ (features.md يعترف 'WS بانتظار التنفيذ'، بلا متحكم chat/عملية WS)
- صور نشر الإنتاج تُغطى بأحجام المصدر: docker-compose.yml يثبت ./service:/app و ./admin:/app في الحاويات، يغطي نتاج COPY + composer install --no-dev في Dockerfile، و service/ و admin/ كلاهما بلا .dockerignore، فحاويات الإنتاج تعمل فعليًا بـ vendor المضيف (شامل تبعيات dev)
- تناقض الوثائق والإعدادات الخاملة: docs/deployment.md موضعان يكتبان استماع admin إلى 8787 / 'admin.erik.xyz → admin:8787' (الفعلي 8788)؛ nginx يثبت ./service/public:/var/www/static:ro لكن أي كتلة server لا تستخدم الدليل الثابت
- Elasticsearch و Redis ضعيفا الأمان: في compose ES يضبط xpack.security.enabled=false والمنفذ 9200 مكشوف على المضيف وبلا أي مصادقة؛ Redis requirepass يعتمد ${REDIS_PASS:-} افتراضيًا بلا كلمة مرور و6379 مكشوف، وعند عدم إعداد .env تعمل الوسائط عارية

### المخاطر
- مخاطر متسلسلة لنقص المفاتيح/المصادقة في الإنتاج: placeholder الافتراضية في compose (سلسلة change_me) بدون استبدال يمكنها الإقلاع، وES بلا مصادقة، وRedis افتراضيًا بلا كلمة مرور، ومنافذ الخدمة كلها مكشوفة، فبمجرد إطلاق غير مكتمل .env مباشرة، يغطي سطح الهجوم 9200/6379/3306/80
- خطر جودة الاختبارات الشكلية: 22 اختبار وحدة تغطي فئات الأدوات فقط، بلا أي اختبار تكامل للنماذج/المتحكمين/الوسائط/قاعدة البيانات، وCI بلا بوابة تحليل ساكن (PHPStan ينهار، php-cs-fixer خارج CI)، فإعادة الهيكلة والدمج غير محصّنين، ومشكلات التراجع تعتمد على الإنسان فقط
- حاويات الإنتاج تعمل بتبعيات dev: ربط أحجام المصدر يغطي الصورة + بلا .dockerignore، بعد تجاوز تحسين --no-dev يحتوي vendor داخل الحاوية على حزم dev مثل PHPUnit/phpstan، ينتفخ بالصورة ويخالف اتفاقية «الإنتاج بلا dev»
- مخاطر موثوقية البيانات من الدوران الفارغ للاعتماد الخارجي: ثلاثة cron لتتبع اللوجستيات/قواعد الامتثال/مزامنة طلبات المنصة لا تنفذ أي مزامنة حقيقية افتراضيًا، فإذا ظن المشغّل 'تمت الأتمتة' يحدث تتبع غير محدث وقواعد امتثال منتهية وفشل صامت في مزامنة طلبات المنصات المتعددة
- فشل التسعير الإقليمي/تحديد اللغة بسبب ترقية GeoIP: عند غياب mmdb كل الطلبات تتراجع إلى config('geoip.default') (ثابت US/USD/en)، فمستخدمو المناطق الأخرى يرون السعر واللغة الافتراضيين الأمريكيين، يؤثر مباشرة على دقة نقاط البيع الأساسية متعدد العملات/اللغات

### التوصيات
- [عالية] إصلاح بوابة PHPStan: **لا** تكوّن memoryLimit في phpstan.neon (PHPStan 2.2.8 أزال معامل neon هذا، والإعداد يسبب `Unexpected item`)، بل أوامر phpstan في `make check` و CI تحمل `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`، اختبار فعلي يمر 0 خطأ (طُبّق، انظر حالة تنفيذ docs/PLAN.md)
- [عالية] إكمال إعدادات جودة admin: إضافة admin/phpstan.neon (level 5، paths=app+plugin/admin/app) و admin/.php-cs-fixer.php (إعادة استخدام قواعد service)، وإدخال admin في فحوص CI phpstan/php-cs-fixer --dry-run؛ قبل تطبيق إعدادات جودة admin إزالة مقطع admin مؤقتًا من هدف Makefile fix لتجنب تعطل التفاعل
- [عالية] إصلاح بناء صورة الإنتاج: docker-compose.yml يزيل ربط المصدر ./service:/app و ./admin:/app (أو يثبت دليلي runtime/logs فقط)، وإضافة service/.dockerignore و admin/.dockerignore (استثناء vendor/runtime/.git وغيرها)، لضمان تشغيل الحاوية بـ vendor --no-dev فقط
- [عالية] إكمال اختبارات التكامل وربطها بـ CI: باستخدام حاويات خدمات MySQL/Redis التي يشغلها CI، إضافة تحت service/tests اختبارات دخانية لقاعدة البيانات/اختبارات تكامل مستوى التوجيه (مثل قابلية استيراد install.sql وفحص الاستبقاء وحلقة التسجيل-الدخول)، وتوسيع '22 tests' من اختبارات وحدة خالصة إلى بوابة تمنع التراجع
- [متوسطة] حل ملف بيانات GeoIP: توفير سكربت/إرشاد لتنزيل GeoLite2-Country.mmdb إلى service/database/geoip/ (أو تفعيل التحديث التلقائي MAXMIND_LICENSE_KEY في الإعداد)، وذكر أثر التراجع إلى قيمة US الافتراضية عند الغياب في README/INSTALL
- [متوسطة] تشديد أسطح تعرض أمان الوسائط: docker-compose.yml يغيّر ربط منافذ ES/Redis/MySQL إلى 127.0.0.1 (nginx فقط يكشف 80/443)، ويفعّل مصادقة xpack لـ ES أو يوضح في تعليقات compose وجوب تكوين REDIS_PASS/مجموعة أمان ES في الإنتاج، لتجنب الإطلاق العاري
- [متوسطة] إزالة الدوران الفارغ للاعتماد الخارجي: في config/cron.php عند 3 عناوين URL فارغة إضافة تعليقات بارزة ورفع السجلات إلى مستوى WARNING (أو توفير مدخل إعداد في خلفية الإدارة)، وفي features.md تحويل حالة 'تتبع اللوجستيات/تحديث الامتثال/مزامنة المنصات المتعددة' من 'كامل' إلى 'يعتمد على إعداد API الخارجية'، موازاة لحقيقة الكود
- [منخفضة] تنظيف الوثائق والإعدادات الخاملة: تصحيح خطأي كتابة منفذ admin 8787→8788 في docs/deployment.md؛ حذف حجم ./service/public:/var/www/static:ro غير المستخدم في تثبيت nginx أو إضافة كتلة server للملفات الثابتة؛ توضيح 'WebSocket IM لخدمة العملاء غير منفذ (بنية الجدول فقط)' في features.md/README لتجنب تضليل لغة المبيعات

---

## 7. تغطية الوثائق والميزات

### ملخص الوضع الحالي
نظام الوثائق مكتمل (8 رسوم بنية SVG+MMD و 9 وثائق api.md/architecture/design/deployment/VERSIONS/AUDIT وغيرها) ومعظم الأرقام تتطابق مع الكود (73 نقطة نهاية توجيه و117 جدولًا و22 tests/45 assertions اختبار فعلي ناجح و5 لغات × 45 ترجمة لكل من service/admin و19 عملة بيانات أولية)، لكن features.md/VERSIONS.md/README تعلم الإدراج متعدد المنصات ومحرك قواعد إدارة المخاطر ودفع Klarna/Adyen والتسوية الرباعية والفاتورة التجارية PDF والاشتراك الدوري/AB و WebSocket خدمة العملاء وغيرها «كامل/✅»، والواقع بنية جدول + CRUD إداري أو صفر تنفيذ أعمال، «وثائق متقدمة على الكود» منهجيًا.

### ما تم تنفيذه
- 8 رسوم بنية مكتملة (01-08 SVG كلها نتاج عرض حقيقي 15-153KB، شاملة مصادر .mmd المقابلة)، تطابق فهرس الأمثلة في docs/diagrams.md واحدًا لواحد
- توجيهات service الفعلية 73 (23 عامًا + 47 مصادقًا + 1 Webhook + 1 Admin + 1 /health)، متسقة تقريبًا مع 71 نقطة نهاية في docs/api.md و 73 في architecture-full.md؛ 23 نقطة نهاية عامة كلها موجودة في route.php
- 39 متحكمًا/111 نموذجًا/14 وسيطة في service و 76 نموذجًا/5 وسائط في admin و 10 عمليات Cron (process.php) و117 جدولًا في install.sql (110 erik_ + 7 wa_) كلها تطابق أرقام README
- الاختبارات تعمل فعليًا: phpunit اختبار فعلي 22 tests/45 assertions ALL PASS (SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3)؛ phpstan level5 و php-cs-fixer و 14 أمر Makefile و CI (مصفوفة PHP 8.3/8.4 + MySQL + Redis) كلها موجودة
- i18n مطبق: 5 لغات × 45 ملف ترجمة لكل من service/admin، و Flutter يكتب AppLocalizations 5 لغات يدويًا + ثبات SharedPreferences، و LocaleMiddleware مطابقة 5 لغات، وترويسات Accept-Language/API-Version/X-Platform نظامية مكتملة
- الدفع: تنفيذ بوابة كامل لـ Stripe (PaymentIntent + client_secret + 3DS) و PayPal (REST v2 OAuth2 + Webhook تحقق التوقيع بخمسة حقول)؛ Webhook تحقق التوقيع → تحديث الدفع/الطلب → توليد PlatformSettlements حلقة معاملات (شامل مزلاج ذري لمنع الإدخال المكرر)
- تسجيل الدخول الاجتماعي Google/Apple/Facebook تحقق id_token fail-closed (tokeninfo/JWKS/debug_token) + منطق الربط/منع استيلاء البريد؛ ExportController XLSX+CSV شامل عمود HS Code؛ استفسار B2B/شراء جماعي/بيع خاطف/أقفال قسائم أعمال موجودة فعليًا
- الأمان: إعداد 31 كاشفًا security-php (service/admin متطابقان)، 7 قواعد RateLimitMiddleware (افتراضي + 6 نقاط نهاية)، فصل قراءة/كتابة DB بنسختي قراءة + sticky=true، 45 نموذجًا SoftDeletes، PosterVerify (slide/click/rotate) تحقق Redis
- منتجات متعددة اللغات/تسعير متعدد العملات (19 عملة بيانات أولية)، بحث ES (scout + ترقية MySQL LIKE)، ProductFeedCron يولّد Google/Meta TSV، ExchangeRateCron يسحب أسعار الصرف كل ساعة، لوحة ECharts (6 KPI + 3 رسوم)، معالج تثبيت ويب (إنشاء قاعدة بيانات→استيراد install.sql→توليد .env→إنشاء مدير)
- العملاء: 25 ملف Dart/11 صفحة Flutter (Riverpod + GoRouter + responsive_framework تكيف PC/اللوحي، موضعا صيني مكتوب يدويًا فقط)؛ 9 صفحات ArkTS هارموني (شاملة ApiClient/ProductCard/AppState)

### الفجوات
- دفع Klarna/Adyen غير منفذ: PaymentGateway::make() يدعم stripe/paypal فقط (service/app/common/PaymentGateway.php)، و PaymentController.php:34 تعليق صريح 'يعيد البوابات المنفذة فقط، يتجنب كشف إعدادات Klarna/Adyen غير المنفذة'، لكن README.md و VERSIONS.md يدرجانهما ✅، وfeatures.md وحده يعترف 'Stripe كامل، والبقية placeholder'
- PDF الفاتورة التجارية/قائمة التعبئة غير منفذ: composer.json يشمل barryvdh/laravel-dompdf لكن المشروع كله (service/admin) صفر استدعاء Dompdf؛ DocumentController.php يقرأ سجلات erik_order_documents الموجودة فقط، بلا أي منطق توليد (وسجلات order_documents لا تُنشأ تلقائيًا أيضًا)، وfeatures.md يدّعي أن 'PDF الفاتورة التجارية/قائمة التعبئة' كاملة
- محرك قواعد إدارة المخاطر غير منفذ: بين 8 فئات في app/common لا يوجد RiskEngine، و OrderController::store بلا تقييم مخاطر، وحالة الطلب 8(قيد المراجعة) لا تُكتب أبدًا؛ features.md يدّعي 'محرك القواعد (تقييم جانبي: تحقق العنوان/مطابقة الرمز البريدي/3DS/تسجيل جماعي/شذوذ قيمة البضاعة) كامل' وآلة حالة الطلب تشمل فرع 'قيد الدفع→قيد المراجعة: مخاطر عالية'، والواقع غير قابل للوصول
- التسوية الرباعية تنفذ خطًا واحدًا فقط: webhook و SettlementCron ينشئان PlatformSettlements فقط؛ MerchantSettlements/SupplierSettlements/AffiliatePayouts بلا أي كتابة ::create في المشروع كله (جدول + CRUD إداري فقط)، ورسم README و 08-multi-currency-settlement يدّعي 'تسوية مستقلة رباعية'
- الاشتراك الدوري واختبار AB بلا API خادم (جدول + متحكم CRUD إداري فقط، وroute.php بلا توجيه مقابل)؛ 'إدراج Amazon/eBay/Shopee/Lazada/Temu + تجميع الطلبات' متعدد المنصات بلا تكامل منصة حقيقي، فقط PlatformOrderSyncCron يسحب وفق URL عام (PlatformListings بلا كتابة أعمال)؛ WebSocket IM غير منفذ لكن VERSIONS.md يعلمه ✅ (features.md/README معلّمان بأمانة)
- دفتر سجل المخزون غير القابل للتغيير غير مطبق: InventoryLogs بلا أي كتابة أعمال (خصم مخزون الطلب لا يسجل سلسلة)؛ وجدول CurrencyExchangeGainsLosses لخسائر/أرباح الصرف أيضًا بلا منطق كتابة، وادعاءا 'سجل المخزون (دفتر غير قابل للتغيير)' و'تتبع أرباح/خسائر الصرف' في README يقفان عند طبقة بنية الجدول
- البيانات الأولية لا تُستورد مع التثبيت: install.sql يشمل بنية الجداول والبيانات الأولية النظامية wa_ فقط (wa_options/wa_roles)، والبيانات الأساسية countries/currencies/payment_gateway_methods/hs_codes/shipping_zones تتطلب تنفيذًا يدويًا لـ service/database/seeders/countries.php (InstallController يستورد install.sql فقط)، فبعد التثبيت الجديد تكون المنتجات/طرق الدفع/حساب الشحن فارغة فورًا؛ لكن AUDIT-REPORT يعلم 'بيانات أولية قاعدة البيانات OK'
- الوثائق الديناميكية hg/apidoc لا تفي بالاسم: AuthController + ProductController فقط لديهما تعليقا @Apidoc (59 سطرًا)، وبقية المتحكمين الـ36 صفر تعليق، وتغطية الوثائق التلقائية لـ 6 مجموعات ناقصة بشدة؛ وتوجد انحرافات أرقام (المتحكمون الفعليون في admin 80 مقابل 82 في الوثائق، كود مصدر HarmonyOS 13 مقابل 14 في الوثائق، ترجمات 45 مقابل 48 في AUDIT، رسم خط أنابيب الوسائط في features.md يفوّت RateLimit/Encryption)

### المخاطر
- الوثائق تبالغ منهجيًا في تعليم 'كامل' (متعدد المنصات، محرك المخاطر، الاشتراك/AB، التسوية الرباعية، PDF الفاتورة، Klarna/Adyen، WS خدمة العملاء)، فتشكل فجوة توقعات تسليم الميزات لعملاء الترخيص التجاري، مع خطر تعاقدي وثقة
- بعد التثبيت الجديد البيانات الأساسية فارغة (البيانات الأولية لا تُستورد تلقائيًا والمعالج لا يشغّل seeder)، وجداول البيانات الأساسية countries/currencies/payment_gateway_methods بلا بيانات، وقائمة المنتجات وطرق الدفع وحساب الشحن/الرسوم الجمركية وغيرها من السلاسل الرئيسية غير قابلة للاستخدام فورًا
- تغطية وثائق API الديناميكية 2/38 متحكمًا فقط، وربط عملاء Flutter/HarmonyOS بلا أساس موثوق للواجهات؛ وثيقة docs/api.md الساكنة و route.php خطر انجراف نقاط النهاية (71 مقابل 73، ورسم خط الأنابيب الداخلي في features.md غير متسق)
- تغطية الاختبارات 22 اختبار وحدة فقط (الأمان + JWT + الاستجابة + Redis)، و38 متحكمًا للأعمال صفر اختبار، وadmin بلا اختبارات، وبلا اختبارات تكامل وتقارير تغطية، وخطر تراجع إعادة الهيكلة/الترقية الجماعية مرتفع
- payment_gateway_methods في DB ما زال يشمل صفوف بوابات غير منفذة مثل klarna/adyen، وعند تفعيل الإعداد خطأً يمكن للواجهة الأمامية عرضها لكن لا بوابة تعالج بعد الطلب، نقطة فشل كامنة في سلسلة الدفع

### التوصيات
- [عالية] توحيد التعليم ثلاثي الحالات 'منفذ/بنية الجدول مبنية/قيد التخطيط' في كل الوثائق: تصحيح حالات Klarna/Adyen والإدراج متعدد المنصات ومحرك المخاطر والاشتراك/AB والتسوية الرباعية وPDF الفاتورة وWS خدمة العملاء في features.md/VERSIONS.md/README، لإنهاء تقدم الوثائق على الكود
- [عالية] معالج التثبيت (admin/plugin/admin/app/controller/InstallController.php) يضيف الاستيراد التلقائي للبيانات الأولية الأساسية (countries/currencies/payment_gateway_methods/hs_codes/shipping_zones)، لضمان قابلية الاستخدام فورًا بعد التثبيت الجديد
- [عالية] إكمال حلقات الأعمال الأساسية: تنفيذ تقييم RiskEngine وحالة الطلب 8، استخدام dompdf المُدخل لتوليد PDF الفاتورة/قائمة التعبئة (DocumentController يُعدل إلى توليد عند الطلب + كتابة في القاعدة)، خصم المخزون يكتب InventoryLogs، وبعد webhook إكمال تسوية Merchant/Affiliate
- [متوسطة] إكمال تعليقات @Apidoc لجميع التوجيهات الـ73 لاستعادة تغطية وثائق hg/apidoc الحقيقية لـ 6 مجموعات؛ إن تعذر ذلك على المدى القصير، خفض إعلان apidoc في README أولًا وتوضيح أن docs/api.md هي الوثيقة الساكنة الموثوقة
- [متوسطة] إضافة اختبارات التكامل: باستخدام خدمات MySQL/Redis المكوّنة في CI إكمال سلسلة دخانية تسجيل→دخول→منتج→سلة→طلب→mock دفع، وإضافة اختبارات CRUD الأساسية في admin، لرفع حماية التراجع للمتحكمين الـ38 ذوي الصفر تغطية
- [متوسطة] تصحيح انحرافات الأرقام: متحكمو admin 82→80، عدد ملفات HarmonyOS، عدد مفاتيح الترجمة 48→45، وتوحيد رسم خط أنابيب الوسائط في features.md (إضافة RateLimit/Encryption) مع قائمة نقاط نهاية api.md (موازاة 73 توجيهًا)
- [منخفضة] لـ CurrencyExchangeGainsLosses (مقارنة سعر الصرف عند التسوية) و PlatformListings (كتابة الإدراج المنصات) إكمال منطق الأعمال الحقيقي، أو تغيير التعليم إلى 'بنية الجدول مبنية'؛ قبل التنفيذ لا يُعلن 'كامل' بعد الآن
- [منخفضة] إنشاء سكربت فحص اتساق نقاط نهاية route.php↔docs/api.md وإدخاله في CI، لاعتراض انجراف الوثائق والكود تلقائيًا
