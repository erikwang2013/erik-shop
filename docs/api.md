# 跨境电商平台 — API 接口文档 (简化版)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 通用规范

### 请求格式

| 项目 | 说明 |
|------|------|
| Base URL | `http://localhost:8787/api` |
| 版本控制 | `API-Version: 2026-05-20` header (不在URL中) |
| 认证 | `Authorization: Bearer <token>` header |
| 语言 | `Accept-Language: zh_CN|zh_HK|en|ja|ko` header |
| 平台 | `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` header |
| Content-Type | `application/json` (POST/PUT) |
| 人机验证 | `X-Poster-Token: <token>` header (敏感操作) |

### 响应格式

```json
// 成功
{"code": 0, "msg": "ok", "data": {}}

// 失败
{"code": 1, "msg": "错误信息", "data": null}

// 分页
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// 错误码
// 40001 XSS攻击  40002 SQL注入  40003 CRLF注入  40004 路径遍历
// 40005 请求体过大  40006 Content-Type错误  40008 暴力破解
// 40009 文件上传违规  40010 XXE注入  40011 SSRF攻击
// 40012 HTTP方法错误  40013 Host头错误
// 401 未登录  403 禁止访问  422 参数验证失败  429 请求过频
```

### ID说明

所有接口中的ID字段均为hashids编码字符串（如 `Ab3xK9pq`），由中间件自动编码/解码。前端无需手动处理。

---

## 1. 认证接口

### 1.1 注册 `POST /api/auth/register`

> 需要人机验证 `X-Poster-Token`

**请求:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**响应:**
```json
{
  "code": 0, "msg": "注册成功",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 登录 `POST /api/auth/login`

**请求:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**响应:**
```json
{
  "code": 0, "msg": "登录成功",
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

### 1.3 刷新Token `POST /api/auth/refresh`

**请求:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**响应:**
```json
{
  "code": 0, "msg": "Token已刷新",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 社交登录 `POST /api/auth/social`

**请求:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**响应:**
```json
{
  "code": 0, "msg": "登录成功",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. 商品接口

### 2.1 商品列表 `GET /api/products`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码 (默认1) |
| per_page | int | 否 | 每页数量 (默认20, 最大100) |
| category_id | string | 否 | 分类ID (hashid, 含子分类) |
| keyword | string | 否 | 搜索关键词 |
| sort | string | 否 | 排序: default/price_asc/price_desc/sales/newest |
| min_price | number | 否 | 最低价 |
| max_price | number | 否 | 最高价 |

**响应:**
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

### 2.2 商品详情 `GET /api/products/{id}`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| currency | string | 否 | 币种代码 (默认USD) |
| dest_country | string | 否 | 目的国ISO2 (默认US) |

**响应:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (多语言匹配)",
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
      {"category": "CE标志", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 商品评价 `GET /api/reviews/{productId}`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码 |
| per_page | int | 否 | 每页 (默认10) |
| rating | int | 否 | 评分筛选 (1-5) |

**响应:**
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

## 3. 分类接口

### 3.1 分类列表 `GET /api/categories`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| parent_id | int | 否 | 父分类ID (0=顶级) |

### 3.2 分类树 `GET /api/categories/tree`

返回完整嵌套分类树。

**响应:**
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

## 4. 购物车接口 `[JWT]`

### 4.1 购物车列表 `GET /api/cart`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| currency | string | 否 | 币种 (默认USD) |

**响应:**
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

### 4.2 添加购物车 `POST /api/cart`

**请求:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 更新数量 `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> quantity=0 时自动删除

### 4.4 删除 `DELETE /api/cart/{id}`

---

## 5. 订单接口 `[JWT]`

### 5.1 订单列表 `GET /api/orders`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 否 | 状态筛选:0待付款/1已付款/2已发货/3已收货/4已完成/5已取消/6退款中/7已退款/8待审核 |
| page | int | 否 | 页码 (默认1) |
| per_page | int | 否 | 每页 (默认10) |

**响应:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "已付款",
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

### 5.2 订单详情 `GET /api/orders/{id}`

返回完整订单信息，含 items/logs/documents。

### 5.3 创建订单 `POST /api/orders` `[PosterVerify]`

**请求:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**响应:**
```json
{
  "code": 0, "msg": "订单创建成功",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 取消订单 `POST /api/orders/{id}/cancel`

> 仅状态=0(待付款)可取消

### 5.5 商业发票 `GET /api/orders/{id}/documents/invoice`

返回PDF文件下载链接。

### 5.6 装箱单 `GET /api/orders/{id}/documents/packing-list`

---

## 6. 支付接口 `[JWT]`

### 6.1 可用支付方式 `GET /api/payment/methods`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| country | string | 否 | ISO2 (默认US) |
| currency | string | 否 | 币种 (默认USD) |

**响应:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "信用卡/借记卡",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna先买后付",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 创建支付 `POST /api/payment/create` `[PosterVerify]`

**请求:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**响应:**
```json
{
  "code": 0, "msg": "支付创建成功",
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

### 6.3 支付状态 `GET /api/payment/status/{id}`

### 6.4 Webhook回调 `POST /webhook/payment/{gateway}`

> 无需JWT。由支付网关异步调用。需验签。

---

## 10. 用户接口 `[JWT]`

### 10.1 个人信息 `GET /api/user/profile`

### 10.2 更新信息 `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 地址列表 `GET /api/user/addresses`

### 10.4 新增地址 `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "家"
}
```

### 10.5 更新地址 `PUT /api/user/addresses/{id}`

### 10.6 删除地址 `DELETE /api/user/addresses/{id}`

### 10.7 语言币种 `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. 营销接口

### 11.1 轮播图 `GET /api/banners?position=home`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| position | string | 否 | 位置: home/category/product |

### 11.2 可用优惠券 `GET /api/coupons` `[JWT]`

### 11.3 领取优惠券 `POST /api/coupons/{id}/claim` `[JWT]`

### 13.1 国家数据 `GET /api/countries`

返回全部可用国家/货币/汇率/默认值。

### 13.2 公开配置 `GET /api/settings?group=general`

### 13.3 ES搜索 `GET /api/search?keyword=xxx`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 是 | 搜索词 |
| category_id | string | 否 | 分类筛选 |
| page | int | 否 | 页码 |

### 13.8 通知 `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.14 评价发表 `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.16 礼品卡兑换 `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

## 附录: 状态码对照

### 订单状态

| 值 | 说明 |
|----|------|
| 0 | 待付款 |
| 1 | 已付款 |
| 2 | 已发货 |
| 3 | 已收货 |
| 4 | 已完成 |
| 5 | 已取消 |
| 6 | 退款中 |
| 7 | 已退款 |
| 8 | 待审核 (风控) |

### 商品状态

| 值 | 说明 |
|----|------|
| 0 | 草稿 |
| 1 | 待审核 |
| 2 | 已上架 |
| 3 | 已下架 |

### 支付状态

| 值 | 说明 |
|----|------|
| 0 | 待支付 |
| 1 | 已支付 |
| 2 | 已退款 |
| 3 | 失败 |

### 国家价格展示模式

| 值 | 说明 |
|----|------|
| tax_inclusive | 含税价 (EU/UK) |
| tax_exclusive | 不含税价 (US/CA) |
| both | 并列显示 (JP) |

---

## 附录: 中间件管道

```
请求 → Cors → Security(15类) → RateLimit(令牌桶) → Platform(8平台)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → 控制器
```

标识: `[JWT]` 需认证 | `[PosterVerify]` 需人机验证 | 无标记 = 公开接口
