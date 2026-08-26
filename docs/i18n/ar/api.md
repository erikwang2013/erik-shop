# منصة التجارة الإلكترونية عبر الحدود — توثيق واجهات API

> توثيق ديناميكي: بعد تشغيل Service زر http://localhost:8787/apidoc/ (يولّد تلقائيًا عبر hg/apidoc)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## المواصفات العامة

### تنسيق الطلب

| البند | الوصف |
|------|------|
| Base URL | `http://localhost:8787/api` |
| التحكم في الإصدار | ترويسة `API-Version: 2026-05-20` (ليست في URL) |
| المصادقة | ترويسة `Authorization: Bearer <token>` |
| اللغة | ترويسة `Accept-Language: zh_CN|zh_HK|en|ja|ko` |
| المنصة | ترويسة `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` |
| Content-Type | `application/json` (POST/PUT) |
| التحقق البشري | ترويسة `X-Poster-Token: <token>` (للعمليات الحساسة) |

### تنسيق الاستجابة

```json
// نجاح
{"code": 0, "msg": "ok", "data": {}}

// فشل
{"code": 1, "msg": "رسالة خطأ", "data": null}

// ترقيم الصفحات
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// أكواد الأخطاء
// 40001 هجوم XSS  40002 حقن SQL  40003 حقن CRLF  40004 اجتياز المسار
// 40005 حجم الطلب كبير جدًا  40006 خطأ Content-Type  40008 هجوم القوة العمياء
// 40009 مخالفة رفع الملفات  40010 حقن XXE  40011 هجوم SSRF
// 40012 طريقة HTTP خاطئة  40013 خطأ في ترويسة Host
// 401 غير مسجل الدخول  403 الوصول ممنوع  422 فشل التحقق من المعاملات  429 طلبات متكررة
```

### شرح المعرّفات

جميع حقول المعرّفات في الواجهات هي سلاسل مشفرة بـ hashids (مثل `Ab3xK9pq`)، تُرمَّز/تُفك تلقائيًا بواسطة الوسيطة. لا حاجة لأي معالجة يدوية من الطرف الأمامي.

---

## 1. واجهات المصادقة

### 1.1 التسجيل `POST /api/auth/register`

> يتطلب التحقق البشري `X-Poster-Token`

**الطلب:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**الاستجابة:**
```json
{
  "code": 0, "msg": "تم التسجيل بنجاح",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 تسجيل الدخول `POST /api/auth/login`

**الطلب:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**الاستجابة:**
```json
{
  "code": 0, "msg": "تم تسجيل الدخول بنجاح",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "level": 1,
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.3 تحديث Token `POST /api/auth/refresh`

**الطلب:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**الاستجابة:**
```json
{
  "code": 0, "msg": "تم تحديث Token",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 تسجيل الدخول الاجتماعي `POST /api/auth/social`

**الطلب:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**الاستجابة:**
```json
{
  "code": 0, "msg": "تم تسجيل الدخول بنجاح",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. واجهات المنتجات

### 2.1 قائمة المنتجات `GET /api/products`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| page | int | لا | رقم الصفحة (الافتراضي 1) |
| per_page | int | لا | عدد العناصر في الصفحة (الافتراضي 20، الحد الأقصى 100) |
| category_id | string | لا | معرّف التصنيف (hashid، يشمل التصنيفات الفرعية) |
| keyword | string | لا | كلمة البحث |
| sort | string | لا | الترتيب: default/price_asc/price_desc/sales/newest |
| min_price | number | لا | الحد الأدنى للسعر |
| max_price | number | لا | الحد الأقصى للسعر |

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Ab3xK9pq",
        "title": "Product Title",
        "subtitle": "Subtitle",
        "main_image": "https://img.example.com/p1.jpg",
        "brand": "BrandName",
        "min_price": 29.99,
        "max_price": 49.99,
        "status": 2,
        "is_hot": true,
        "is_new": false,
        "sales_count": 1000
      }
    ],
    "total": 100, "page": 1, "per_page": 20
  }
}
```

### 2.2 تفاصيل المنتج `GET /api/products/{id}`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| currency | string | لا | رمز العملة (الافتراضي USD) |
| dest_country | string | لا | الدولة الوجهة ISO2 (الافتراضي US) |

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (مطابقة متعددة اللغات)",
    "subtitle": "Subtitle",
    "description": "Full description...",
    "brand": "BrandName",
    "main_image": "https://img.example.com/p1.jpg",
    "min_price": 29.99,
    "max_price": 49.99,
    "weight": 500,
    "unit": "piece",
    "status": 2,
    "is_hot": true,
    "is_new": false,
    "sales_count": 1000,
    "view_count": 5000,
    "skus": [
      {
        "id": "Cd4yL8rq",
        "sku_code": "SKU-RED-M",
        "attrs": {"color": "Red", "size": "M"},
        "default_price": 29.99,
        "stock": 100,
        "image": "https://img.example.com/sku1.jpg",
        "display_price": {
          "tax_exclusive": 29.99,
          "tax_inclusive": 35.99,
          "vat_amount": 6.00,
          "vat_rate": 20,
          "currency": "USD",
          "display_mode": "tax_exclusive"
        }
      }
    ],
    "images": [
      {"id": "Ef5zM9ns", "url": "https://img.example.com/p1.jpg", "is_main": true}
    ],
    "compliance_info": [
      {"category": "علامة CE", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 تقييمات المنتج `GET /api/reviews/{productId}`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| page | int | لا | رقم الصفحة |
| per_page | int | لا | عدد العناصر في الصفحة (الافتراضي 10) |
| rating | int | لا | فلترة التقييم (1-5) |

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Re1v2W3x",
        "user_id": "Ab3xK9pq",
        "product_id": "Ab3xK9pq",
        "rating": 5,
        "content": "Great product!",
        "images": ["https://img.example.com/review1.jpg"],
        "is_anonymous": false,
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 50, "page": 1, "per_page": 10
  }
}
```

---

## 3. واجهات التصنيفات

### 3.1 قائمة التصنيفات `GET /api/categories`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| parent_id | int | لا | معرّف التصنيف الأب (0=أعلى مستوى) |

### 3.2 شجرة التصنيفات `GET /api/categories/tree`

تُرجع شجرة التصنيفات المتداخلة الكاملة.

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ct1g2H3i",
      "parent_id": 0,
      "name": "Clothing",
      "slug": "clothing",
      "icon": "icon-url",
      "level": 1,
      "is_hot": true,
      "children": [
        {
          "id": "Ct4j5K6l",
          "parent_id": "Ct1g2H3i",
          "name": "Dresses", "slug": "dresses",
          "level": 2, "is_hot": false,
          "children": []
        }
      ]
    }
  ]
}
```

---

## 4. واجهات سلة التسوق `[JWT]`

### 4.1 قائمة سلة التسوق `GET /api/cart`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| currency | string | لا | العملة (الافتراضي USD) |

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ca1r2T3s",
      "sku_id": "Cd4yL8rq",
      "product_id": "Ab3xK9pq",
      "title": "Product Title",
      "image": "https://img.example.com/sku1.jpg",
      "attrs": {"color":"Red","size":"M"},
      "price": 29.99,
      "currency": "USD",
      "quantity": 2,
      "selected": true,
      "stock": 100
    }
  ]
}
```

### 4.2 إضافة إلى سلة التسوق `POST /api/cart`

**الطلب:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 تحديث الكمية `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> عند quantity=0 يُحذف تلقائيًا

### 4.4 الحذف `DELETE /api/cart/{id}`

---

## 5. واجهات الطلبات `[JWT]`

### 5.1 قائمة الطلبات `GET /api/orders`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| status | int | لا | فلترة الحالة: 0 بانتظار الدفع / 1 مدفوع / 2 تم الشحن / 3 تم الاستلام / 4 مكتمل / 5 ملغي / 6 قيد الاسترداد / 7 تم الاسترداد / 8 بانتظار المراجعة |
| page | int | لا | رقم الصفحة (الافتراضي 1) |
| per_page | int | لا | عدد العناصر في الصفحة (الافتراضي 10) |

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "مدفوع",
        "total_amount": 59.98, "pay_amount": 59.98,
        "currency_code": "USD",
        "created_at": "2026-05-21 10:30:00",
        "paid_at": "2026-05-21 10:31:00"
      }
    ],
    "total": 10, "page": 1, "per_page": 10
  }
}
```

### 5.2 تفاصيل الطلب `GET /api/orders/{id}`

تُرجع معلومات الطلب الكاملة، بما فيها items/logs/documents.

### 5.3 إنشاء طلب `POST /api/orders` `[PosterVerify]`

**الطلب:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**الاستجابة:**
```json
{
  "code": 0, "msg": "تم إنشاء الطلب بنجاح",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 إلغاء الطلب `POST /api/orders/{id}/cancel`

> يمكن الإلغاء فقط عندما تكون الحالة =0 (بانتظار الدفع)

### 5.5 الفاتورة التجارية `GET /api/orders/{id}/documents/invoice`

تُرجع رابط تنزيل ملف PDF.

### 5.6 قائمة التعبئة `GET /api/orders/{id}/documents/packing-list`

---

## 6. واجهات الدفع `[JWT]`

### 6.1 طرق الدفع المتاحة `GET /api/payment/methods`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| country | string | لا | ISO2 (الافتراضي US) |
| currency | string | لا | العملة (الافتراضي USD) |

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "بطاقة ائتمان/خصم",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna اشترِ الآن وادفع لاحقًا",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 إنشاء الدفع `POST /api/payment/create` `[PosterVerify]`

**الطلب:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**الاستجابة:**
```json
{
  "code": 0, "msg": "تم إنشاء الدفع بنجاح",
  "data": {
    "payment_id": "Pa1y2M3t",
    "order_no": "ORD20260521A1B2C3D4",
    "amount": 59.98,
    "currency": "USD",
    "gateway": "stripe",
    "method": "card",
    "client_secret": "pi_3Nxxxx_secret_xxxx",
    "txn_id": "pi_3Nxxxxxxxxxxxx"
  }
}
```

### 6.3 حالة الدفع `GET /api/payment/status/{id}`

### 6.4 استدعاء Webhook `POST /webhook/payment/{gateway}`

> بدون JWT. يُستدعى بشكل غير متزامن من بوابة الدفع. يتطلب التحقق من التوقيع.

---

## 7. واجهات اللوجستيات

### 7.1 حساب رسوم الشحن `GET /api/shipping/calculate`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| dest_country_id | int | نعم | معرّف الدولة الوجهة (snowflake) |
| weight | int | لا | الوزن (بالجرام) (الافتراضي 500) |

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "منطقة أمريكا الشمالية",
    "weight_kg": 0.5,
    "dest_country": "US",
    "options": [
      {
        "logistics_name": "DHL Express",
        "logistics_code": "DHL",
        "fee": 25.50,
        "estimated_days": "3-5",
        "tracking_url": "https://www.dhl.com/track?num="
      }
    ]
  }
}
```

---

## 8. واجهات الرسوم الجمركية

### 8.1 تقدير الرسوم الجمركية `GET /api/tariff/estimate`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| product_id | string | نعم | معرّف المنتج (hashid) |
| dest_country_id | int | نعم | معرّف الدولة الوجهة |
| declared_value | number | نعم | القيمة المصرّح بها |

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "للمرجعية فقط، المبلغ الفعلي يعتمد على تقدير الجمارك"
  }
}
```

---

## 9. واجهات الإرجاع `[JWT]`

### 9.1 قائمة الإرجاعات `GET /api/returns`

### 9.2 طلب الإرجاع `POST /api/returns`

**الطلب:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 ملصق الإرجاع `GET /api/returns/{id}/label`

---

## 10. واجهات المستخدم `[JWT]`

### 10.1 المعلومات الشخصية `GET /api/user/profile`

### 10.2 تحديث المعلومات `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 قائمة العناوين `GET /api/user/addresses`

### 10.4 إضافة عنوان `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "المنزل"
}
```

### 10.5 تحديث العنوان `PUT /api/user/addresses/{id}`

### 10.6 حذف العنوان `DELETE /api/user/addresses/{id}`

### 10.7 اللغة والعملة `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. واجهات التسويق

### 11.1 الصور الدائرية `GET /api/banners?position=home`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| position | string | لا | الموقع: home/category/product |

### 11.2 القسائم المتاحة `GET /api/coupons` `[JWT]`

### 11.3 استلام قسيمة `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 قائمة البيع الخاطف `GET /api/flash-sales`

### 11.5 قائمة الشراء الجماعي `GET /api/group-buys`

### 11.6 روابط التوزيع `GET /api/affiliate/links` `[JWT]`

### 11.7 عمولات التوزيع `GET /api/affiliate/commissions` `[JWT]`

---

## 12. واجهات العضوية `[JWT]`

### 12.1 معلومات العضوية `GET /api/membership`

**الاستجابة:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "current_level": {"id": "Lv1", "name": "Gold", "level": 2},
    "current_benefits": [{"benefit_type": "discount", "benefit_value": "5%"}],
    "all_levels": [],
    "current_score": 1500
  }
}
```

### 12.2 سجل النقاط `GET /api/points`

---

## 13. واجهات أخرى

### 13.1 بيانات الدول `GET /api/countries`

تُرجع جميع الدول/العملات/أسعار الصرف/القيم الافتراضية المتاحة.

### 13.2 الإعدادات العامة `GET /api/settings?group=general`

### 13.3 بحث ES `GET /api/search?keyword=xxx`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| keyword | string | نعم | كلمة البحث |
| category_id | string | لا | فلترة التصنيف |
| page | int | لا | رقم الصفحة |

### 13.4 مقارنة المنتجات `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE يتطلب معرّف سجل المقارنة: `DELETE /api/comparisons/{id}` (حيث `{id}` هو معرّف سجل المقارنة، إلزامي)

### 13.5 التوصيات المخصصة `GET /api/recommendations` `[JWT]`

### 13.6 تنبيهات انخفاض السعر `GET/POST /api/price-alerts` `[JWT]`

### 13.7 المفضلة `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 الإشعارات `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 الأسئلة الشائعة `GET /api/faq?category=shipping`

### 13.10 صفحات CMS `GET /api/cms/{slug}`

### 13.11 جدول مقاسات الملابس `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 فحص الامتثال `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 كشف GeoIP `GET /api/geoip/detect`

### 13.14 نشر تقييم `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 رصيد بطاقة الهدايا `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 استبدال بطاقة الهدايا `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 طلبات GDPR `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 تصدير الطلبات `GET /api/export/orders` `[JWT]`

| المعامل | النوع | إلزامي | الوصف |
|------|------|------|------|
| date_from | string | لا | تاريخ البداية (YYYY-MM-DD) |
| date_to | string | لا | تاريخ النهاية |

تُرجع تنزيل ملف CSV.

### 13.19 استفسار B2B `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 فحص الصحة `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## الملحق: جدول أكواد الحالات

### حالات الطلب

| القيمة | الوصف |
|----|------|
| 0 | بانتظار الدفع |
| 1 | مدفوع |
| 2 | تم الشحن |
| 3 | تم الاستلام |
| 4 | مكتمل |
| 5 | ملغي |
| 6 | قيد الاسترداد |
| 7 | تم الاسترداد |
| 8 | بانتظار المراجعة (مراقبة المخاطر) |

### حالات المنتج

| القيمة | الوصف |
|----|------|
| 0 | مسودة |
| 1 | بانتظار المراجعة |
| 2 | منشور |
| 3 | غير منشور |

### حالات الدفع

| القيمة | الوصف |
|----|------|
| 0 | بانتظار الدفع |
| 1 | مدفوع |
| 2 | تم الاسترداد |
| 3 | فشل |

### وضع عرض السعر حسب الدولة

| القيمة | الوصف |
|----|------|
| tax_inclusive | السعر شامل الضريبة (EU/UK) |
| tax_exclusive | السعر غير شامل الضريبة (US/CA) |
| both | عرض متوازٍ (JP) |

---

## الملحق: خط أنابيب الوسائط

```
طلب → Cors → Security(31 نوعًا) → RateLimit(دلو الرموز) → Platform(8 منصات)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → المتحكم
```

العلامات: `[JWT]` يتطلب مصادقة | `[PosterVerify]` يتطلب تحققًا بشريًا | بدون علامة = واجهة عامة

---

## الملحق: نظرة عامة على إحصائيات النقاط

### A.1 الواجهات العامة (23 نقطة)

| الطريقة | المسار | الوصف |
|------|------|------|
| POST | /api/auth/register | تسجيل(PosterVerify) |
| POST | /api/auth/login | تسجيل الدخول |
| POST | /api/auth/refresh | تحديث Token |
| POST | /api/auth/social | تسجيل الدخول الاجتماعي |
| GET | /api/products | قائمة المنتجات(ترقيم+فلترة+ترتيب) |
| GET | /api/products/{id} | تفاصيل المنتج(متعدد اللغات+متعدد العملات+الامتثال+HS) |
| GET | /api/categories | قائمة التصنيفات |
| GET | /api/categories/tree | شجرة التصنيفات |
| GET | /api/banners | الصور الدائرية(حسب الموقع+المنطقة) |
| GET | /api/countries | قائمة الدول/العملات/أسعار الصرف |
| GET | /api/search | بحث ES متعدد اللغات |
| GET | /api/reviews/{productId} | قائمة تقييمات المنتجات |
| GET | /api/flash-sales | البيع الخاطف الحالي |
| GET | /api/group-buys | الشراء الجماعي الحالي |
| GET | /api/faq | FAQ(حسب اللغة+التصنيف) |
| GET | /api/cms/{slug} | صفحة CMS |
| GET | /api/settings | الإعدادات العامة |
| GET | /api/size-charts | جدول المقاسات |
| GET | /api/tariff/estimate | تقدير الرسوم الجمركية |
| GET | /api/shipping/calculate | حساب الشحن |
| GET | /api/payment/methods | طرق الدفع المتاحة |
| GET | /api/geoip/detect | كشف GeoIP |
| GET | /api/compliance/check | فحص الامتثال |

### A.2 الواجهات المصادقة (47 نقطة)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET/PUT | /api/user/profile | المعلومات الشخصية |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | CRUD للعناوين |
| PUT | /api/user/locale | تحديث اللغة/العملة |
| GET/POST | /api/wishlist[/{id}] | المفضلة |
| GET/POST | /api/price-alerts | تنبيه انخفاض السعر |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | سلة التسوق |
| GET/POST | /api/orders | قائمة الطلبات/إنشاء(PosterVerify) |
| GET | /api/orders/{id} | تفاصيل الطلب |
| POST | /api/orders/{id}/cancel | إلغاء الطلب |
| GET | /api/orders/{id}/documents/invoice | الفاتورة التجارية |
| GET | /api/orders/{id}/documents/packing-list | قائمة التعبئة |
| POST | /api/payment/create | إنشاء الدفع(PosterVerify) |
| GET | /api/payment/status/{id} | حالة الدفع |
| GET/POST | /api/returns[/{id}] | الإرجاع |
| GET | /api/returns/{id}/label | ملصق الإرجاع |
| POST | /api/reviews | نشر تقييم |
| GET/POST | /api/coupons[/{id}/claim] | القسائم |
| GET/PUT | /api/notifications[/{id}/read] | الإشعارات |
| GET/POST/DELETE | /api/comparisons[/{id}] | مقارنة المنتجات |
| GET | /api/recommendations | توصيات مخصصة |
| GET | /api/affiliate/links | روابط التوزيع |
| GET | /api/affiliate/commissions | عمولات التوزيع |
| GET | /api/membership | مستوى العضوية |
| GET | /api/points | سجل النقاط |
| GET/POST | /api/gift-cards | بطاقات الهدايا |
| GET/POST | /api/b2b/quotes | استفسار B2B |
| GET/POST | /api/privacy/request | طلبات GDPR |
| GET | /api/export/orders | تصدير الطلبات |

### A.3 Webhook (نقطة واحدة)

| الطريقة | المسار | الوصف |
|------|------|------|
| POST | /webhook/payment/{gateway} | إشعار الدفع غير المتزامن(التحقق من التوقيع) |

### A.4 Admin والفحص الصحي (نقطتان)

| الطريقة | المسار | الوصف |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | تنفيذ استرداد الإدارة |
| GET | /health | فحص الصحة |

---

## الملحق: مواصفات تصميم API

### التحكم في الإصدار

يُنقل الإصدار عبر ترويسة `API-Version: 2026-05-20`، وليس في URL. تُرسمه وسيطة VersionRoute.

### خط أنابيب الوسائط

```
Cors → Security(31 نوعًا) → RateLimit(نافذة منزلقة) → Platform(8 منصات) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### إحصائيات النقاط

- الواجهات العامة: 23 (المصادقة/المنتجات/التصنيفات/المحتوى/البحث/الخدمات)
- الواجهات المصادقة: 47 (المستخدم/سلة التسوق/الطلبات/الدفع/الإرجاع/التقييمات/التسويق)
- Webhook: 1 (استدعاء الدفع)
- Admin: 1 (تنفيذ الاسترداد)
- Health: 1 (/health فحص الصحة)

### الاستجابة الموحدة

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### التوثيق الديناميكي hg/apidoc

يُولَّد تلقائيًا باستخدام hg/apidoc وفقًا لتعليقات المتحكمين. زر `/apidoc/` بعد التشغيل.

مثال على التعليقات:
```php
/**
 * @Apidoc\Title("تسجيل الدخول")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
