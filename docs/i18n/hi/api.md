# क्रॉस-बॉर्डर ई-कॉमर्स प्लेटफ़ॉर्म — API इंटरफ़ेस दस्तावेज़

> डायनामिक दस्तावेज़: Service शुरू करने के बाद http://localhost:8787/apidoc/ देखें (hg/apidoc स्वचालित उत्पादन)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## सामान्य मानक

### अनुरोध फ़ॉर्मेट

| आइटम | विवरण |
|------|------|
| Base URL | `http://localhost:8787/api` |
| संस्करण नियंत्रण | `API-Version: 2026-05-20` header (URL में नहीं) |
| प्रमाणीकरण | `Authorization: Bearer <token>` header |
| भाषा | `Accept-Language: zh_CN|zh_HK|en|ja|ko` header |
| प्लेटफ़ॉर्म | `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` header |
| Content-Type | `application/json` (POST/PUT) |
| मानव-सत्यापन | `X-Poster-Token: <token>` header (संवेदनशील ऑपरेशन) |
| संसाधन URL | संसाधन URL (प्रोडक्ट/बैनर चित्र, दस्तावेज़) `Cdn::url()` से आउटपुट होते हैं और CDN सक्षम होने पर `https://{CDN_DOMAIN}{path}` में रीराइट होते हैं |

### रिस्पॉन्स फ़ॉर्मेट

```json
// सफलता
{"code": 0, "msg": "ok", "data": {}}

// विफलता
{"code": 1, "msg": "त्रुटि संदेश", "data": null}

// पेजिनेशन
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// त्रुटि कोड
// 40001 XSS हमला  40002 SQL इंजेक्शन  40003 CRLF इंजेक्शन  40004 पाथ ट्रैवर्सल
// 40005 अनुरोध बॉडी बहुत बड़ी  40006 Content-Type त्रुटि  40008 ब्रूट फ़ोर्स
// 40009 फ़ाइल अपलोड उल्लंघन  40010 XXE इंजेक्शन  40011 SSRF हमला
// 40012 HTTP विधि त्रुटि  40013 Host हेडर त्रुटि
// 401 लॉगिन नहीं  403 एक्सेस अस्वीकृत  422 पैरामीटर सत्यापन विफल  429 अनुरोध बहुत बार-बार  503 सेवा अस्थायी रूप से अनुपलब्ध (मेल्ट डाउन/डिग्रेडेशन)
```

### ID विवरण

सभी इंटरफ़ेस में ID फ़ील्ड hashids-एन्कोडेड स्ट्रिंग हैं (जैसे `Ab3xK9pq`), जिन्हें मिडलवेयर स्वचालित रूप से एन्कोड/डीकोड करता है। फ्रंटएंड को मैन्युअल प्रोसेसिंग की आवश्यकता नहीं है।

---

## 1. प्रमाणीकरण इंटरफ़ेस

### 1.1 पंजीकरण `POST /api/auth/register`

> मानव-सत्यापन `X-Poster-Token` आवश्यक

**अनुरोध:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "पंजीकरण सफल",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 लॉगिन `POST /api/auth/login`

**अनुरोध:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "लॉगिन सफल",
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

### 1.3 टोकन रिफ़्रेश `POST /api/auth/refresh`

**अनुरोध:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "टोकन रिफ़्रेश हुआ",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 सोशल लॉगिन `POST /api/auth/social`

**अनुरोध:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "लॉगिन सफल",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. उत्पाद इंटरफ़ेस

### 2.1 उत्पाद सूची `GET /api/products`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| page | int | नहीं | पृष्ठ संख्या (डिफ़ॉल्ट 1) |
| per_page | int | नहीं | प्रति पृष्ठ संख्या (डिफ़ॉल्ट 20, अधिकतम 100) |
| category_id | string | नहीं | श्रेणी ID (hashid, उप-श्रेणियाँ सहित) |
| keyword | string | नहीं | खोज कीवर्ड |
| sort | string | नहीं | क्रमबद्धता: default/price_asc/price_desc/sales/newest |
| min_price | number | नहीं | न्यूनतम मूल्य |
| max_price | number | नहीं | अधिकतम मूल्य |

**रिस्पॉन्स:**
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

### 2.2 उत्पाद विवरण `GET /api/products/{id}`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| currency | string | नहीं | मुद्रा कोड (डिफ़ॉल्ट USD) |
| dest_country | string | नहीं | गंतव्य देश ISO2 (डिफ़ॉल्ट US) |

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (बहुभाषी मिलान)",
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
      {"category": "CE चिह्न", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 उत्पाद समीक्षा `GET /api/reviews/{productId}`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| page | int | नहीं | पृष्ठ संख्या |
| per_page | int | नहीं | प्रति पृष्ठ (डिफ़ॉल्ट 10) |
| rating | int | नहीं | रेटिंग फ़िल्टर (1-5) |

**रिस्पॉन्स:**
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

## 3. श्रेणी इंटरफ़ेस

### 3.1 श्रेणी सूची `GET /api/categories`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| parent_id | int | नहीं | मूल श्रेणी ID (0=शीर्ष स्तर) |

### 3.2 श्रेणी वृक्ष `GET /api/categories/tree`

पूर्ण नेस्टेड श्रेणी वृक्ष लौटाता है।

**रिस्पॉन्स:**
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

## 4. कार्ट इंटरफ़ेस `[JWT]`

### 4.1 कार्ट सूची `GET /api/cart`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| currency | string | नहीं | मुद्रा (डिफ़ॉल्ट USD) |

**रिस्पॉन्स:**
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

### 4.2 कार्ट में जोड़ें `POST /api/cart`

**अनुरोध:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 मात्रा अपडेट करें `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> quantity=0 होने पर स्वतः हट जाता है

### 4.4 हटाएँ `DELETE /api/cart/{id}`

---

## 5. ऑर्डर इंटरफ़ेस `[JWT]`

### 5.1 ऑर्डर सूची `GET /api/orders`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| status | int | नहीं | स्थिति फ़िल्टर:0 भुगतान लंबित/1 भुगतान किया गया/2 शिप हो गया/3 प्राप्त हुआ/4 पूर्ण/5 रद्द/6 रिफंड में/7 रिफंड किया गया/8 समीक्षा लंबित |
| page | int | नहीं | पृष्ठ संख्या (डिफ़ॉल्ट 1) |
| per_page | int | नहीं | प्रति पृष्ठ (डिफ़ॉल्ट 10) |

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "भुगतान किया गया",
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

### 5.2 ऑर्डर विवरण `GET /api/orders/{id}`

पूर्ण ऑर्डर जानकारी लौटाता है, items/logs/documents सहित।

### 5.3 ऑर्डर बनाएँ `POST /api/orders` `[PosterVerify]`

**अनुरोध:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "ऑर्डर सफलतापूर्वक बनाया गया",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 ऑर्डर रद्द करें `POST /api/orders/{id}/cancel`

> केवल स्थिति=0 (भुगतान लंबित) पर रद्द किया जा सकता है

### 5.5 वाणिज्यिक इनवॉइस `GET /api/orders/{id}/documents/invoice`

PDF फ़ाइल डाउनलोड लिंक लौटाता है।

### 5.6 पैकिंग लिस्ट `GET /api/orders/{id}/documents/packing-list`

---

## 6. भुगतान इंटरफ़ेस `[JWT]`

### 6.1 उपलब्ध भुगतान विधियाँ `GET /api/payment/methods`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| country | string | नहीं | ISO2 (डिफ़ॉल्ट US) |
| currency | string | नहीं | मुद्रा (डिफ़ॉल्ट USD) |

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "क्रेडिट/डेबिट कार्ड",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna अभी खरीदें बाद में भुगतान",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 भुगतान बनाएँ `POST /api/payment/create` `[PosterVerify]`

**अनुरोध:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "भुगतान सफलतापूर्वक बनाया गया",
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

### 6.3 भुगतान स्थिति `GET /api/payment/status/{id}`

### 6.4 Webhook कॉलबैक `POST /webhook/payment/{gateway}`

> JWT आवश्यक नहीं। भुगतान गेटवे द्वारा एसिंक्रोनस रूप से कॉल किया जाता है। हस्ताक्षर सत्यापन आवश्यक।

---

## 7. लॉजिस्टिक्स इंटरफ़ेस

### 7.1 शिपिंग शुल्क गणना `GET /api/shipping/calculate`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| dest_country_id | int | हाँ | गंतव्य देश ID (snowflake) |
| weight | int | नहीं | वज़न (ग्राम) (डिफ़ॉल्ट 500) |

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "उत्तरी अमेरिका क्षेत्र",
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

## 8. टैरिफ इंटरफ़ेस

### 8.1 टैरिफ अनुमान `GET /api/tariff/estimate`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| product_id | string | हाँ | उत्पाद ID (hashid) |
| dest_country_id | int | हाँ | गंतव्य देश ID |
| declared_value | number | हाँ | घोषित मूल्य |

**रिस्पॉन्स:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "केवल संदर्भ हेतु, वास्तविक कस्टम निर्धारण मान्य होगा"
  }
}
```

---

## 9. रिटर्न इंटरफ़ेस `[JWT]`

### 9.1 रिटर्न सूची `GET /api/returns`

### 9.2 रिटर्न अनुरोध `POST /api/returns`

**अनुरोध:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 रिटर्न लेबल `GET /api/returns/{id}/label`

---

## 10. उपयोगकर्ता इंटरफ़ेस `[JWT]`

### 10.1 व्यक्तिगत जानकारी `GET /api/user/profile`

### 10.2 जानकारी अपडेट करें `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 पता सूची `GET /api/user/addresses`

### 10.4 नया पता `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "घर"
}
```

### 10.5 पता अपडेट करें `PUT /api/user/addresses/{id}`

### 10.6 पता हटाएँ `DELETE /api/user/addresses/{id}`

### 10.7 भाषा/मुद्रा `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. मार्केटिंग इंटरफ़ेस

### 11.1 बैनर `GET /api/banners?position=home`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| position | string | नहीं | स्थिति: home/category/product |

### 11.2 उपलब्ध कूपन `GET /api/coupons` `[JWT]`

### 11.3 कूपन प्राप्त करें `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 फ़्लैश सेल सूची `GET /api/flash-sales`

### 11.5 ग्रुप बाय सूची `GET /api/group-buys`

### 11.6 एफिलिएट लिंक `GET /api/affiliate/links` `[JWT]`

### 11.7 एफिलिएट कमीशन `GET /api/affiliate/commissions` `[JWT]`

---

## 12. सदस्यता इंटरफ़ेस `[JWT]`

### 12.1 सदस्यता जानकारी `GET /api/membership`

**रिस्पॉन्स:**
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

### 12.2 पॉइंट इतिहास `GET /api/points`

---

## 13. अन्य इंटरफ़ेस

### 13.1 देश डेटा `GET /api/countries`

सभी उपलब्ध देश/मुद्रा/विनिमय दर/डिफ़ॉल्ट मान लौटाता है।

### 13.2 सार्वजनिक कॉन्फ़िग `GET /api/settings?group=general`

### 13.3 ES खोज `GET /api/search?keyword=xxx`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| keyword | string | हाँ | खोज शब्द |
| category_id | string | नहीं | श्रेणी फ़िल्टर |
| page | int | नहीं | पृष्ठ संख्या |

### 13.4 उत्पाद तुलना `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE में तुलना रिकॉर्ड id भेजना होगा: `DELETE /api/comparisons/{id}` (`{id}` तुलना रिकॉर्ड ID है, आवश्यक)

### 13.5 व्यक्तिगत अनुशंसा `GET /api/recommendations` `[JWT]`

### 13.6 मूल्य अलर्ट `GET/POST /api/price-alerts` `[JWT]`

### 13.7 पसंदीदा सूची `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 सूचनाएँ `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 CMS पृष्ठ `GET /api/cms/{slug}`

### 13.11 साइज़ चार्ट `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 अनुपालन जाँच `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 GeoIP पहचान `GET /api/geoip/detect`

### 13.14 समीक्षा लिखें `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 गिफ्ट कार्ड शेष `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 गिफ्ट कार्ड रिडीम `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 GDPR अनुरोध `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 ऑर्डर निर्यात `GET /api/export/orders` `[JWT]`

| पैरामीटर | प्रकार | आवश्यक | विवरण |
|------|------|------|------|
| date_from | string | नहीं | आरंभ तिथि (YYYY-MM-DD) |
| date_to | string | नहीं | समाप्ति तिथि |

CSV फ़ाइल डाउनलोड लौटाता है।

### 13.19 B2B मूल्य पूछताछ `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 स्वास्थ्य जाँच `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## परिशिष्ट: स्थिति कोड तुलना

### ऑर्डर स्थिति

| मान | विवरण |
|----|------|
| 0 | भुगतान लंबित |
| 1 | भुगतान किया गया |
| 2 | शिप हो गया |
| 3 | प्राप्त हुआ |
| 4 | पूर्ण |
| 5 | रद्द |
| 6 | रिफंड में |
| 7 | रिफंड किया गया |
| 8 | समीक्षा लंबित (जोखिम नियंत्रण) |

### उत्पाद स्थिति

| मान | विवरण |
|----|------|
| 0 | ड्राफ़्ट |
| 1 | समीक्षा लंबित |
| 2 | प्रकाशित |
| 3 | अनप्रकाशित |

### भुगतान स्थिति

| मान | विवरण |
|----|------|
| 0 | भुगतान लंबित |
| 1 | भुगतान किया गया |
| 2 | रिफंड किया गया |
| 3 | विफल |

### देश मूल्य प्रदर्शन मोड

| मान | विवरण |
|----|------|
| tax_inclusive | कर सहित मूल्य (EU/UK) |
| tax_exclusive | कर रहित मूल्य (US/CA) |
| both | समानांतर प्रदर्शन (JP) |

---

## परिशिष्ट: मिडलवेयर पाइपलाइन

```
अनुरोध → Cors → Security(31 प्रकार) → RateLimit(टोकन बकेट) → Platform(8 प्लेटफ़ॉर्म)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → कंट्रोलर
```

चिह्न: `[JWT]` प्रमाणीकरण आवश्यक | `[PosterVerify]` मानव-सत्यापन आवश्यक | कोई चिह्न नहीं = सार्वजनिक इंटरफ़ेस

---

## परिशिष्ट: एंडपॉइंट सांख्यिकी अवलोकन

### A.1 सार्वजनिक इंटरफ़ेस (23 एंडपॉइंट)

| विधि | पथ | विवरण |
|------|------|------|
| POST | /api/auth/register | पंजीकरण (PosterVerify) |
| POST | /api/auth/login | लॉगिन |
| POST | /api/auth/refresh | टोकन रिफ़्रेश |
| POST | /api/auth/social | सोशल लॉगिन |
| GET | /api/products | उत्पाद सूची (पेजिनेशन+फ़िल्टर+क्रमबद्धता) |
| GET | /api/products/{id} | उत्पाद विवरण (बहुभाषी+बहु-मुद्रा+अनुपालन+HS) |
| GET | /api/categories | श्रेणी सूची |
| GET | /api/categories/tree | श्रेणी वृक्ष |
| GET | /api/banners | बैनर (स्थिति+क्षेत्र के अनुसार) |
| GET | /api/countries | देश/मुद्रा/विनिमय दर सूची |
| GET | /api/search | ES बहुभाषी खोज |
| GET | /api/reviews/{productId} | उत्पाद समीक्षा सूची |
| GET | /api/flash-sales | वर्तमान फ़्लैश सेल |
| GET | /api/group-buys | वर्तमान ग्रुप बाय |
| GET | /api/faq | FAQ (भाषा+श्रेणी के अनुसार) |
| GET | /api/cms/{slug} | CMS पृष्ठ |
| GET | /api/settings | सार्वजनिक कॉन्फ़िग |
| GET | /api/size-charts | साइज़ चार्ट |
| GET | /api/tariff/estimate | टैरिफ अनुमान |
| GET | /api/shipping/calculate | शिपिंग शुल्क गणना |
| GET | /api/payment/methods | उपलब्ध भुगतान विधियाँ |
| GET | /api/geoip/detect | GeoIP पहचान |
| GET | /api/compliance/check | अनुपालन जाँच |

### A.2 प्रमाणीकरण इंटरफ़ेस (47 एंडपॉइंट)

| विधि | पथ | विवरण |
|------|------|------|
| GET/PUT | /api/user/profile | व्यक्तिगत जानकारी |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | पता CRUD |
| PUT | /api/user/locale | भाषा/मुद्रा अपडेट |
| GET/POST | /api/wishlist[/{id}] | पसंदीदा सूची |
| GET/POST | /api/price-alerts | मूल्य अलर्ट |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | कार्ट |
| GET/POST | /api/orders | ऑर्डर सूची/निर्माण (PosterVerify) |
| GET | /api/orders/{id} | ऑर्डर विवरण |
| POST | /api/orders/{id}/cancel | ऑर्डर रद्द करें |
| GET | /api/orders/{id}/documents/invoice | वाणिज्यिक इनवॉइस |
| GET | /api/orders/{id}/documents/packing-list | पैकिंग लिस्ट |
| POST | /api/payment/create | भुगतान बनाएँ (PosterVerify) |
| GET | /api/payment/status/{id} | भुगतान स्थिति |
| GET/POST | /api/returns[/{id}] | रिटर्न |
| GET | /api/returns/{id}/label | रिटर्न लेबल |
| POST | /api/reviews | समीक्षा लिखें |
| GET/POST | /api/coupons[/{id}/claim] | कूपन |
| GET/PUT | /api/notifications[/{id}/read] | सूचनाएँ |
| GET/POST/DELETE | /api/comparisons[/{id}] | उत्पाद तुलना |
| GET | /api/recommendations | व्यक्तिगत अनुशंसा |
| GET | /api/affiliate/links | एफिलिएट लिंक |
| GET | /api/affiliate/commissions | एफिलिएट कमीशन |
| GET | /api/membership | सदस्यता स्तर |
| GET | /api/points | पॉइंट इतिहास |
| GET/POST | /api/gift-cards | गिफ्ट कार्ड |
| GET/POST | /api/b2b/quotes | B2B मूल्य पूछताछ |
| GET/POST | /api/privacy/request | GDPR अनुरोध |
| GET | /api/export/orders | ऑर्डर निर्यात |

### A.3 Webhook (1 एंडपॉइंट)

| विधि | पथ | विवरण |
|------|------|------|
| POST | /webhook/payment/{gateway} | भुगतान एसिंक्रोनस अधिसूचना (हस्ताक्षर सत्यापन) |

### A.4 Admin और स्वास्थ्य जाँच (2 एंडपॉइंट)

| विधि | पथ | विवरण |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | बैकएंड रिफंड निष्पादन |
| GET | /health | स्वास्थ्य जाँच |

---

## परिशिष्ट: API डिज़ाइन मानक

### संस्करण नियंत्रण

संस्करण `API-Version: 2026-05-20` header से पारित होता है, URL में नहीं। VersionRoute मिडलवेयर मैपिंग करता है।

### मिडलवेयर पाइपलाइन

```
Cors → Security(31 प्रकार) → RateLimit(स्लाइडिंग विंडो) → Platform(8 प्लेटफ़ॉर्म) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### एंडपॉइंट सांख्यिकी

- सार्वजनिक इंटरफ़ेस: 23 (प्रमाणीकरण/उत्पाद/श्रेणी/सामग्री/खोज/सेवाएँ)
- प्रमाणीकरण इंटरफ़ेस: 47 (उपयोगकर्ता/कार्ट/ऑर्डर/भुगतान/रिटर्न/समीक्षा/मार्केटिंग)
- Webhook: 1 (भुगतान कॉलबैक)
- Admin: 1 (रिफंड निष्पादन)
- Health: 1 (/health स्वास्थ्य जाँच)

### समान रिस्पॉन्स

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### hg/apidoc डायनामिक दस्तावेज़

hg/apidoc कंट्रोलर एनोटेशन के आधार पर स्वचालित उत्पादन करता है। शुरू करने के बाद `/apidoc/` देखें।

एनोटेशन उदाहरण:
```php
/**
 * @Apidoc\Title("उपयोगकर्ता लॉगिन")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
