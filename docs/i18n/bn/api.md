# ক্রস-বর্ডার ই-কমার্স প্ল্যাটফর্ম — API ইন্টারফেস ডকুমেন্ট

> এই নথিটি মূল চীনা ডকুমেন্টেশনের মেশিন অনুবাদ। মূল: [চীনা মূল](../../api.md).
>
> ডায়নামিক ডকুমেন্ট: Service চালু করার পর http://localhost:8787/apidoc/ অ্যাক্সেস করুন (hg/apidoc স্বয়ংক্রিয় জেনারেশন)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## সাধারণ নিয়ম

### রিকোয়েস্ট ফরম্যাট

| আইটেম | বিবরণ |
|------|------|
| Base URL | `http://localhost:8787/api` |
| ভার্সন কন্ট্রোল | `API-Version: 2026-05-20` header (URL-এ নয়) |
| অথেনটিকেশন | `Authorization: Bearer <token>` header |
| ভাষা | `Accept-Language: zh_CN|zh_HK|en|ja|ko` header |
| প্ল্যাটফর্ম | `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` header |
| Content-Type | `application/json` (POST/PUT) |
| হিউম্যান ভেরিফিকেশন | `X-Poster-Token: <token>` header (সংবেদনশীল অপারেশন) |

### রেসপন্স ফরম্যাট

```json
// সফল
{"code": 0, "msg": "ok", "data": {}}

// ব্যর্থ
{"code": 1, "msg": "ত্রুটির তথ্য", "data": null}

// পেজিনেশন
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// এরর কোড
// 40001 XSS অ্যাটাক  40002 SQL ইনজেকশন  40003 CRLF ইনজেকশন  40004 পাথ ট্রাভার্সাল
// 40005 বডি বড়  40006 Content-Type এরর  40008 ব্রুট ফোর্স
// 40009 ফাইল আপলোড লঙ্ঘন  40010 XXE ইনজেকশন  40011 SSRF অ্যাটাক
// 40012 HTTP মেথড এরর  40013 Host হেডার এরর
// 401 লগইন নেই  403 অ্যাক্সেস নিষিদ্ধ  422 প্যারামিটার ভ্যালিডেশন ব্যর্থ  429 রিকোয়েস্ট বেশি
```

### ID ব্যাখ্যা

সব ইন্টারফেসের ID ফিল্ড hashids এনকোডেড স্ট্রিং (যেমন `Ab3xK9pq`), মিডলওয়্যার স্বয়ংক্রিয়ভাবে এনকোড/ডিকোড করে। ফ্রন্টএন্ডে ম্যানুয়াল প্রসেসিংয়ের প্রয়োজন নেই।

---

## 1. অথেনটিকেশন ইন্টারফেস

### 1.1 রেজিস্ট্রেশন `POST /api/auth/register`

> হিউম্যান ভেরিফিকেশন `X-Poster-Token` প্রয়োজন

**রিকোয়েস্ট:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**রেসপন্স:**
```json
{
  "code": 0, "msg": "রেজিস্ট্রেশন সফল",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 লগইন `POST /api/auth/login`

**রিকোয়েস্ট:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**রেসপন্স:**
```json
{
  "code": 0, "msg": "লগইন সফল",
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

### 1.3 Token রিফ্রেশ `POST /api/auth/refresh`

**রিকোয়েস্ট:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**রেসপন্স:**
```json
{
  "code": 0, "msg": "টোকেন রিফ্রেশ হয়েছে",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 সোশ্যাল লগইন `POST /api/auth/social`

**রিকোয়েস্ট:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**রেসপন্স:**
```json
{
  "code": 0, "msg": "লগইন সফল",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. প্রোডাক্ট ইন্টারফেস

### 2.1 প্রোডাক্ট তালিকা `GET /api/products`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| page | int | না | পেজ নম্বর (ডিফল্ট 1) |
| per_page | int | না | প্রতি পেজ সংখ্যা (ডিফল্ট 20, সর্বোচ্চ 100) |
| category_id | string | না | ক্যাটাগরি ID (hashid, সাব-ক্যাটাগরি সহ) |
| keyword | string | না | সার্চ কীওয়ার্ড |
| sort | string | না | সর্ট: default/price_asc/price_desc/sales/newest |
| min_price | number | না | সর্বনিম্ন মূল্য |
| max_price | number | না | সর্বোচ্চ মূল্য |

**রেসপন্স:**
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

### 2.2 প্রোডাক্ট ডিটেইল `GET /api/products/{id}`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| currency | string | না | কারেন্সি কোড (ডিফল্ট USD) |
| dest_country | string | না | গন্তব্য দেশ ISO2 (ডিফল্ট US) |

**রেসপন্স:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (মাল্টি-ল্যাঙ্গুয়েজ ম্যাচ)",
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
      {"category": "CE মার্ক", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 প্রোডাক্ট রিভিউ `GET /api/reviews/{productId}`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| page | int | না | পেজ নম্বর |
| per_page | int | না | প্রতি পেজ (ডিফল্ট 10) |
| rating | int | না | রেটিং ফিল্টার (1-5) |

**রেসপন্স:**
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

## 3. ক্যাটাগরি ইন্টারফেস

### 3.1 ক্যাটাগরি তালিকা `GET /api/categories`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| parent_id | int | না | প্যারেন্ট ক্যাটাগরি ID (0=টপ-লেভেল) |

### 3.2 ক্যাটাগরি ট্রি `GET /api/categories/tree`

সম্পূর্ণ নেস্টেড ক্যাটাগরি ট্রি রিটার্ন করে।

**রেসপন্স:**
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

## 4. কার্ট ইন্টারফেস `[JWT]`

### 4.1 কার্ট তালিকা `GET /api/cart`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| currency | string | না | কারেন্সি (ডিফল্ট USD) |

**রেসপন্স:**
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

### 4.2 কার্টে যোগ `POST /api/cart`

**রিকোয়েস্ট:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 সংখ্যা আপডেট `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> quantity=0 হলে স্বয়ংক্রিয়ভাবে মুছে যায়

### 4.4 ডিলিট `DELETE /api/cart/{id}`

---

## 5. অর্ডার ইন্টারফেস `[JWT]`

### 5.1 অর্ডার তালিকা `GET /api/orders`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| status | int | না | স্ট্যাটাস ফিল্টার: 0 অপেক্ষমাণ/1 পরিশোধিত/2 প্রেরিত/3 গৃহীত/4 সম্পন্ন/5 বাতিল/6 রিফান্ডিং/7 রিফান্ডেড/8 পর্যালোচনাধীন |
| page | int | না | পেজ নম্বর (ডিফল্ট 1) |
| per_page | int | না | প্রতি পেজ (ডিফল্ট 10) |

**রেসপন্স:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "পরিশোধিত",
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

### 5.2 অর্ডার ডিটেইল `GET /api/orders/{id}`

সম্পূর্ণ অর্ডার তথ্য রিটার্ন করে, items/logs/documents সহ।

### 5.3 অর্ডার তৈরি `POST /api/orders` `[PosterVerify]`

**রিকোয়েস্ট:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**রেসপন্স:**
```json
{
  "code": 0, "msg": "অর্ডার তৈরি সফল",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 অর্ডার বাতিল `POST /api/orders/{id}/cancel`

> শুধুমাত্র status=0 (অপেক্ষমাণ) হলে বাতিল করা যায়

### 5.5 কমার্শিয়াল ইনভয়েস `GET /api/orders/{id}/documents/invoice`

PDF ফাইল ডাউনলোড লিংক রিটার্ন করে।

### 5.6 প্যাকিং লিস্ট `GET /api/orders/{id}/documents/packing-list`

---

## 6. পেমেন্ট ইন্টারফেস `[JWT]`

### 6.1 উপলব্ধ পেমেন্ট পদ্ধতি `GET /api/payment/methods`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| country | string | না | ISO2 (ডিফল্ট US) |
| currency | string | না | কারেন্সি (ডিফল্ট USD) |

**রেসপন্স:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "ক্রেডিট/ডেবিট কার্ড",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna এখন কিনে পরে পরিশোধ",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 পেমেন্ট তৈরি `POST /api/payment/create` `[PosterVerify]`

**রিকোয়েস্ট:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**রেসপন্স:**
```json
{
  "code": 0, "msg": "পেমেন্ট তৈরি সফল",
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

### 6.3 পেমেন্ট স্ট্যাটাস `GET /api/payment/status/{id}`

### 6.4 Webhook কলব্যাক `POST /webhook/payment/{gateway}`

> JWT প্রয়োজন নেই। পেমেন্ট গেটওয়ে অ্যাসিঙ্ক্রোনাসভাবে কল করে। সিগনেচার ভেরিফিকেশন প্রয়োজন।

---

## 7. লজিস্টিক ইন্টারফেস

### 7.1 শিপিং ফি গণনা `GET /api/shipping/calculate`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| dest_country_id | int | হ্যাঁ | গন্তব্য দেশ ID (snowflake) |
| weight | int | না | ওজন (গ্রাম) (ডিফল্ট 500) |

**রেসপন্স:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "উত্তর আমেরিকা অঞ্চল",
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

## 8. শুল্ক ইন্টারফেস

### 8.1 শুল্ক এস্টিমেট `GET /api/tariff/estimate`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| product_id | string | হ্যাঁ | প্রোডাক্ট ID (hashid) |
| dest_country_id | int | হ্যাঁ | গন্তব্য দেশ ID |
| declared_value | number | হ্যাঁ | ডিক্লেয়ারড ভ্যালু |

**রেসপন্স:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "শুধুমাত্র রেফারেন্সের জন্য, চূড়ান্ত শুল্ক নির্ধারণ প্রযোজ্য"
  }
}
```

---

## 9. রিটার্ন ইন্টারফেস `[JWT]`

### 9.1 রিটার্ন তালিকা `GET /api/returns`

### 9.2 রিটার্ন অনুরোধ `POST /api/returns`

**রিকোয়েস্ট:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 রিটার্ন লেবেল `GET /api/returns/{id}/label`

---

## 10. ইউজার ইন্টারফেস `[JWT]`

### 10.1 ব্যক্তিগত তথ্য `GET /api/user/profile`

### 10.2 তথ্য আপডেট `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 ঠিকানা তালিকা `GET /api/user/addresses`

### 10.4 নতুন ঠিকানা `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "বাড়ি"
}
```

### 10.5 ঠিকানা আপডেট `PUT /api/user/addresses/{id}`

### 10.6 ঠিকানা ডিলিট `DELETE /api/user/addresses/{id}`

### 10.7 ভাষা/কারেন্সি `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. মার্কেটিং ইন্টারফেস

### 11.1 ব্যানার `GET /api/banners?position=home`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| position | string | না | পজিশন: home/category/product |

### 11.2 উপলব্ধ কুপন `GET /api/coupons` `[JWT]`

### 11.3 কুপন ক্লেইম `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 ফ্ল্যাশ সেল তালিকা `GET /api/flash-sales`

### 11.5 গ্রুপ বাই তালিকা `GET /api/group-buys`

### 11.6 অ্যাফিলিয়েট লিংক `GET /api/affiliate/links` `[JWT]`

### 11.7 অ্যাফিলিয়েট কমিশন `GET /api/affiliate/commissions` `[JWT]`

---

## 12. মেম্বারশিপ ইন্টারফেস `[JWT]`

### 12.1 মেম্বারশিপ তথ্য `GET /api/membership`

**রেসপন্স:**
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

### 12.2 পয়েন্ট ট্রানজেকশন `GET /api/points`

---

## 13. অন্যান্য ইন্টারফেস

### 13.1 দেশের ডেটা `GET /api/countries`

সব উপলব্ধ দেশ/মুদ্রা/এক্সচেঞ্জ রেট/ডিফল্ট মান রিটার্ন করে।

### 13.2 পাবলিক কনফিগ `GET /api/settings?group=general`

### 13.3 ES সার্চ `GET /api/search?keyword=xxx`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| keyword | string | হ্যাঁ | সার্চ টার্ম |
| category_id | string | না | ক্যাটাগরি ফিল্টার |
| page | int | না | পেজ নম্বর |

### 13.4 প্রোডাক্ট তুলনা `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE-তে তুলনা রেকর্ড id পাঠাতে হবে: `DELETE /api/comparisons/{id}` (`{id}` তুলনা রেকর্ড ID, বাধ্যতামূলক)

### 13.5 পার্সোনালাইজড রেকমেন্ডেশন `GET /api/recommendations` `[JWT]`

### 13.6 প্রাইস ড্রপ অ্যালার্ট `GET/POST /api/price-alerts` `[JWT]`

### 13.7 উইশলিস্ট `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 নোটিফিকেশন `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 CMS পেজ `GET /api/cms/{slug}`

### 13.11 সাইজ চার্ট `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 কমপ্লায়েন্স চেক `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 GeoIP ডিটেকশন `GET /api/geoip/detect`

### 13.14 রিভিউ পোস্ট `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 গিফট কার্ড ব্যালেন্স `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 গিফট কার্ড রিডিম `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 GDPR রিকোয়েস্ট `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 অর্ডার এক্সপোর্ট `GET /api/export/orders` `[JWT]`

| প্যারামিটার | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| date_from | string | না | শুরু তারিখ (YYYY-MM-DD) |
| date_to | string | না | শেষ তারিখ |

CSV ফাইল ডাউনলোড রিটার্ন করে।

### 13.19 B2B কোয়োট `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 হেলথ চেক `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## অ্যাপেন্ডিক্স: স্ট্যাটাস কোড রেফারেন্স

### অর্ডার স্ট্যাটাস

| মান | বিবরণ |
|----|------|
| 0 | অপেক্ষমাণ (পেমেন্ট মুলতুবি) |
| 1 | পরিশোধিত (পেমেন্ট সম্পন্ন) |
| 2 | প্রেরিত (শিপ করা হয়েছে) |
| 3 | গৃহীত (রিসিভ হয়েছে) |
| 4 | সম্পন্ন (সম্পন্ন) |
| 5 | বাতিল (বাতিল) |
| 6 | রিফান্ডিং (রিফান্ড চলছে) |
| 7 | রিফান্ডেড (রিফান্ড সম্পন্ন) |
| 8 | পর্যালোচনাধীন (রিভিউ মুলতুবি, রিস্ক) |

### প্রোডাক্ট স্ট্যাটাস

| মান | বিবরণ |
|----|------|
| 0 | ড্রাফট (ড্রাফট) |
| 1 | পর্যালোচনাধীন (রিভিউ মুলতুবি) |
| 2 | লাইভ (লাইভ) |
| 3 | অফলাইন (অফলাইন) |

### পেমেন্ট স্ট্যাটাস

| মান | বিবরণ |
|----|------|
| 0 | অপেক্ষমাণ পেমেন্ট (পেমেন্ট মুলতুবি) |
| 1 | পরিশোধিত (পেমেন্ট সম্পন্ন) |
| 2 | রিফান্ডেড (রিফান্ড সম্পন্ন) |
| 3 | ব্যর্থ (ব্যর্থ) |

### দেশের প্রাইস ডিসপ্লে মোড

| মান | বিবরণ |
|----|------|
| tax_inclusive | করসহ মূল্য (EU/UK) |
| tax_exclusive | করবিহীন মূল্য (US/CA) |
| both | সমান্তরাল ডিসপ্লে (JP) |

---

## অ্যাপেন্ডিক্স: মিডলওয়্যার পাইপলাইন

```
রিকোয়েস্ট → Cors → Security(31 প্রকার) → RateLimit(টোকেন বাকেট) → Platform(8 প্ল্যাটফর্ম)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → কন্ট্রোলার
```

চিহ্ন: `[JWT]` অথেনটিকেশন প্রয়োজন | `[PosterVerify]` হিউম্যান ভেরিফিকেশন প্রয়োজন | কোনো চিহ্ন নেই = পাবলিক ইন্টারফেস

---

## অ্যাপেন্ডিক্স: এন্ডপয়েন্ট পরিসংখ্যান ওভারভিউ

### A.1 পাবলিক ইন্টারফেস (23 এন্ডপয়েন্ট)

| মেথড | পাথ | বিবরণ |
|------|------|------|
| POST | /api/auth/register | রেজিস্ট্রেশন (PosterVerify) |
| POST | /api/auth/login | লগইন |
| POST | /api/auth/refresh | Token রিফ্রেশ |
| POST | /api/auth/social | সোশ্যাল লগইন |
| GET | /api/products | প্রোডাক্ট তালিকা (পেজিনেশন+ফিল্টার+সর্ট) |
| GET | /api/products/{id} | প্রোডাক্ট ডিটেইল (মাল্টি-ল্যাঙ্গুয়েজ+মাল্টি-কারেন্সি+কমপ্লায়েন্স+HS) |
| GET | /api/categories | ক্যাটাগরি তালিকা |
| GET | /api/categories/tree | ক্যাটাগরি ট্রি |
| GET | /api/banners | ব্যানার (পজিশন+অঞ্চল অনুযায়ী) |
| GET | /api/countries | দেশ/মুদ্রা/এক্সচেঞ্জ রেট তালিকা |
| GET | /api/search | ES মাল্টি-ল্যাঙ্গুয়েজ সার্চ |
| GET | /api/reviews/{productId} | প্রোডাক্ট রিভিউ তালিকা |
| GET | /api/flash-sales | চলমান ফ্ল্যাশ সেল |
| GET | /api/group-buys | চলমান গ্রুপ বাই |
| GET | /api/faq | FAQ (ভাষা+ক্যাটাগরি অনুযায়ী) |
| GET | /api/cms/{slug} | CMS পেজ |
| GET | /api/settings | পাবলিক কনফিগ |
| GET | /api/size-charts | সাইজ চার্ট |
| GET | /api/tariff/estimate | শুল্ক এস্টিমেট |
| GET | /api/shipping/calculate | শিপিং ফি গণনা |
| GET | /api/payment/methods | উপলব্ধ পেমেন্ট পদ্ধতি |
| GET | /api/geoip/detect | GeoIP ডিটেকশন |
| GET | /api/compliance/check | কমপ্লায়েন্স চেক |

### A.2 অথেনটিকেশন ইন্টারফেস (47 এন্ডপয়েন্ট)

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET/PUT | /api/user/profile | ব্যক্তিগত তথ্য |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | ঠিকানা CRUD |
| PUT | /api/user/locale | ভাষা/কারেন্সি আপডেট |
| GET/POST | /api/wishlist[/{id}] | উইশলিস্ট |
| GET/POST | /api/price-alerts | প্রাইস ড্রপ অ্যালার্ট |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | কার্ট |
| GET/POST | /api/orders | অর্ডার তালিকা/তৈরি (PosterVerify) |
| GET | /api/orders/{id} | অর্ডার ডিটেইল |
| POST | /api/orders/{id}/cancel | অর্ডার বাতিল |
| GET | /api/orders/{id}/documents/invoice | কমার্শিয়াল ইনভয়েস |
| GET | /api/orders/{id}/documents/packing-list | প্যাকিং লিস্ট |
| POST | /api/payment/create | পেমেন্ট তৈরি (PosterVerify) |
| GET | /api/payment/status/{id} | পেমেন্ট স্ট্যাটাস |
| GET/POST | /api/returns[/{id}] | রিটার্ন |
| GET | /api/returns/{id}/label | রিটার্ন লেবেল |
| POST | /api/reviews | রিভিউ পোস্ট |
| GET/POST | /api/coupons[/{id}/claim] | কুপন |
| GET/PUT | /api/notifications[/{id}/read] | নোটিফিকেশন |
| GET/POST/DELETE | /api/comparisons[/{id}] | প্রোডাক্ট তুলনা |
| GET | /api/recommendations | পার্সোনালাইজড রেকমেন্ডেশন |
| GET | /api/affiliate/links | অ্যাফিলিয়েট লিংক |
| GET | /api/affiliate/commissions | অ্যাফিলিয়েট কমিশন |
| GET | /api/membership | মেম্বারশিপ লেভেল |
| GET | /api/points | পয়েন্ট ট্রানজেকশন |
| GET/POST | /api/gift-cards | গিফট কার্ড |
| GET/POST | /api/b2b/quotes | B2B কোয়োট |
| GET/POST | /api/privacy/request | GDPR রিকোয়েস্ট |
| GET | /api/export/orders | অর্ডার এক্সপোর্ট |

### A.3 Webhook (1 এন্ডপয়েন্ট)

| মেথড | পাথ | বিবরণ |
|------|------|------|
| POST | /webhook/payment/{gateway} | পেমেন্ট অ্যাসিঙ্ক নোটিফিকেশন (সিগনেচার ভেরিফিকেশন) |

### A.4 Admin ও হেলথ চেক (2 এন্ডপয়েন্ট)

| মেথড | পাথ | বিবরণ |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | ব্যাকএন্ড রিফান্ড এক্সিকিউশন |
| GET | /health | হেলথ চেক |

---

## অ্যাপেন্ডিক্স: API ডিজাইন স্ট্যান্ডার্ড

### ভার্সন কন্ট্রোল

ভার্সন `API-Version: 2026-05-20` header দিয়ে পাস হয়, URL-এ নয়। VersionRoute মিডলওয়্যার ম্যাপিং করে।

### মিডলওয়্যার পাইপলাইন

```
Cors → Security(31 প্রকার) → RateLimit(স্লাইডিং উইন্ডো) → Platform(8 প্ল্যাটফর্ম) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### এন্ডপয়েন্ট পরিসংখ্যান

- পাবলিক ইন্টারফেস: 23টি (অথেনটিকেশন/প্রোডাক্ট/ক্যাটাগরি/কনটেন্ট/সার্চ/সার্ভিস)
- অথেনটিকেশন ইন্টারফেস: 47টি (ইউজার/কার্ট/অর্ডার/পেমেন্ট/রিটার্ন/রিভিউ/মার্কেটিং)
- Webhook: 1টি (পেমেন্ট কলব্যাক)
- Admin: 1টি (রিফান্ড এক্সিকিউশন)
- Health: 1টি (/health হেলথ চেক)

### ইউনিফাইড রেসপন্স

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### hg/apidoc ডায়নামিক ডকুমেন্ট

hg/apidoc দিয়ে কন্ট্রোলার অ্যানোটেশন অনুযায়ী স্বয়ংক্রিয়ভাবে তৈরি হয়। চালু হলে `/apidoc/` অ্যাক্সেস করুন।

অ্যানোটেশন উদাহরণ:
```php
/**
 * @Apidoc\Title("ইউজার লগইন")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
