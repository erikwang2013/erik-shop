# منصة التجارة الإلكترونية عبر الحدود — وثيقة التصميم

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. تصميم قاعدة البيانات

### 1.1 قواعد التسمية

- بادئة الجداول: `erik_`
- المفتاح الأساسي: `id BIGINT UNSIGNED NOT NULL` (يولّده snowflake، غير ذاتي الزيادة)
- الطوابع الزمنية: `created_at`, `updated_at`, `deleted_at` (حذف ناعم)
- المحرك: InnoDB، الترميز: utf8mb4_unicode_ci

### 1.2 تقسيم الوحدات (110 جدولًا)

| الوحدة | عدد الجداول | الجداول الأساسية |
|------|------|--------|
| المستخدمون والحسابات | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| المنتجات والتصنيفات | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| المعاملات | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| الدفع والأموال | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| اللوجستيات | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| الجمارك والضرائب | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| التسويق | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| سلسلة التوريد | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| المخاطر والامتثال | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| متعدد المنصات | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| المحتوى والتجربة | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| الاشتراكات وB2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| خدمة العملاء | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| اختبار AB | 3 | ab_tests, ab_test_variants, ab_test_results |
| حوكمة API | 2 | api_rate_limits, api_docs |
| البيانات الأساسية | 3 | countries, currencies, exchange_rates |

### 1.3 حقول تتبع المنصات

| الجدول | الحقل | الوصف |
|----|------|------|
| orders | platform VARCHAR(16) | منصة إصدار الطلب |
| payments | platform VARCHAR(16) | منصة الدفع |
| operation_logs | platform VARCHAR(16) | منصة العملية |
| users | last_login_platform VARCHAR(16) | منصة آخر تسجيل دخول |
| search_logs | platform VARCHAR(16) | منصة البحث |
| chat_messages | platform VARCHAR(16) | مصدر الرسالة |

---

## 2. تصميم API

التحكم في إصدار API وخط أنابيب الوسائط وإحصائيات النقاط ومواصفات الاستجابة الموحدة، انظر [توثيق واجهات API](api.md).

---

## 3. التصميم الأمني

### 3.1 يغلف SecurityMiddleware الكواشف الـ31 من security-php

| # | النوع | رمز الخطأ | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | حقن SQL | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | عبور المسار | 40004 | ✅ | ✅ |
| 5 | جسم الطلب كبير | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | رفع الملفات | 40009 | ✅ | ✅ |
| 8 | ترويسات الاستجابة الأمنية | — | ✅ | ✅ |
| 9 | القوة الغاشمة | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | طرق HTTP | 40012 | ✅ | ✅ |
| 13 | ترويسة Host | 40013 | ✅ | — |
| 14 | إخفاء البيانات الحساسة | — | ✅ | ✅ |
| 15 | القائمة البيضاء CORS | — | ⚠️ | ⚠️ |

### 3.2 التشفير ثلاثي الطبقات

| الطبقة | التقنية | الحزمة |
|------|------|-----|
| طبقة النقل | AES-256-CBC | erikwang2013/encryption |
| طبقة قاعدة البيانات | Encryptable trait | erikwang2013/encryptable (Maize) |
| إخفاء المعرّفات | Hashids | erikwang2013/hashids |

---

## 4. تصميم الالتزام العالي

### 4.1 تحديد المعدل

دلو الرموز بنافذة منزلقة (Redis ZSET، عبر واجهة support\Redis): الافتراضي 60 ثانية/100 مرة، تسجيل الدخول 10 مرات/60 ثانية، التسجيل 5 مرات/300 ثانية، تسجيل الدخول الاجتماعي 5 مرات/300 ثانية، الدفع 5 مرات/60 ثانية، الطلب 3 مرات/10 ثوانٍ، البحث 10 مرات/1 ثانية

### 4.2 استخدامات Redis

يُستخدم Redis لتحديد المعدل بدلو الرموز (واجهة `support\Redis`) والتحقق البشري وتخزين Session؛ بيانات الأعمال لا تخضع للتخزين المؤقت على مستوى التطبيق، وتُقرأ مباشرة من MySQL (فصل القراءة/الكتابة + تجمع الاتصالات).

### 4.3 تجمع الاتصالات

MySQL: 50 كحد أقصى/10 أدنى/2 ثانية مهلة | فصل القراءة/الكتابة: 30 كحد أقصى/5 دقائق (نسختا قراءة، sticky=true) | Redis: 30 كحد أقصى/5 دقائق



---

## 5. التدويل

- الواجهات: zh_CN, zh_HK, en, ja, ko
- المحتوى: erik_product_translations صفوف مستقلة حسب locale
- الأسعار: erik_product_sku_prices تسعير مستقل حسب العملة
- الترويسات: Accept-Language + API-Version

## 6. توثيق API

يُولَّد تلقائيًا باستخدام hg/apidoc وفقًا لتعليقات المتحكمين، انظر [توثيق واجهات API](api.md). زر `/apidoc/` بعد التشغيل.

## 7. الاختبارات

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

انظر: [وثيقة تصميم الميزات](features.md) | [وثيقة البنية الكاملة](architecture-full.md) | [وثيقة النشر](deployment.md)
