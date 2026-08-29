# 跨境电商平台 — API 接口文档

> 动态文档: 启动Service后访问 http://localhost:8787/apidoc/ (hg/apidoc自动生成)



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
// 401 未登录  403 禁止访问  422 参数验证失败  429 请求过频  503 服务暂不可用(熔断/降级)
```

### ID说明

所有接口中的ID字段均为hashids编码字符串（如 `Ab3xK9pq`），由中间件自动编码/解码。前端无需手动处理。

### 资源URL（CDN）

商品图片、SKU 图、评价图、轮播图与 PDF 文档等资源 URL 在接口输出时经 `Cdn::url()` 重写：开启 CDN（`CDN_ENABLED=true` 且配置 `CDN_DOMAIN`）后统一输出为 `https://{CDN_DOMAIN}{相对路径}`（如 `https://cdn.erik.xyz/app/admin/upload/p1.jpg`）；未开启时返回 admin 源站相对/绝对路径。资源为 Origin-Pull 回源，CDN 域名 CNAME 回源到 admin 域名，前端无需区分两种形态。

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

## 7. 物流接口

### 7.1 运费计算 `GET /api/shipping/calculate`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| dest_country_id | int | 是 | 目的国ID (snowflake) |
| weight | int | 否 | 重量(克) (默认500) |

**响应:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "北美区",
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

## 8. 关税接口

### 8.1 关税估算 `GET /api/tariff/estimate`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| product_id | string | 是 | 商品ID (hashid) |
| dest_country_id | int | 是 | 目的国ID |
| declared_value | number | 是 | 申报价值 |

**响应:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "仅供参考，实际以海关核定为准"
  }
}
```

---

## 9. 退货接口 `[JWT]`

### 9.1 退货列表 `GET /api/returns`

### 9.2 申请退货 `POST /api/returns`

**请求:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 退货面单 `GET /api/returns/{id}/label`

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

### 11.4 秒杀列表 `GET /api/flash-sales`

### 11.5 拼团列表 `GET /api/group-buys`

### 11.6 分销链接 `GET /api/affiliate/links` `[JWT]`

### 11.7 分销佣金 `GET /api/affiliate/commissions` `[JWT]`

---

## 12. 会员接口 `[JWT]`

### 12.1 会员信息 `GET /api/membership`

**响应:**
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

### 12.2 积分流水 `GET /api/points`

---

## 13. 其它接口

### 13.1 国家数据 `GET /api/countries`

返回全部可用国家/货币/汇率/默认值。

### 13.2 公开配置 `GET /api/settings?group=general`

### 13.3 ES搜索 `GET /api/search?keyword=xxx`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| keyword | string | 是 | 搜索词 |
| category_id | string | 否 | 分类筛选 |
| page | int | 否 | 页码 |

### 13.4 商品对比 `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE 需携带对比记录 id：`DELETE /api/comparisons/{id}`（`{id}` 为对比记录 ID，必填）

### 13.5 个性化推荐 `GET /api/recommendations` `[JWT]`

### 13.6 降价提醒 `GET/POST /api/price-alerts` `[JWT]`

### 13.7 收藏夹 `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 通知 `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 CMS页面 `GET /api/cms/{slug}`

### 13.11 尺码对照表 `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 合规检查 `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 GeoIP检测 `GET /api/geoip/detect`

### 13.14 评价发表 `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 礼品卡余额 `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 礼品卡兑换 `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 GDPR请求 `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 导出订单 `GET /api/export/orders` `[JWT]`

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| date_from | string | 否 | 开始日期 (YYYY-MM-DD) |
| date_to | string | 否 | 结束日期 |

返回CSV文件下载。

### 13.19 B2B询价 `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 健康检查 `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

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
请求 → Cors → Security(31类) → RateLimit(令牌桶) → Platform(8平台)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → 控制器
```

标识: `[JWT]` 需认证 | `[PosterVerify]` 需人机验证 | 无标记 = 公开接口

---

## 附录: 端点统计总览

### A.1 公开接口 (23端点)

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/auth/register | 注册(PosterVerify) |
| POST | /api/auth/login | 登录 |
| POST | /api/auth/refresh | 刷新Token |
| POST | /api/auth/social | 社交登录 |
| GET | /api/products | 商品列表(分页+筛选+排序) |
| GET | /api/products/{id} | 商品详情(多语言+多币种+合规+HS) |
| GET | /api/categories | 分类列表 |
| GET | /api/categories/tree | 分类树 |
| GET | /api/banners | 轮播图(按位置+区域) |
| GET | /api/countries | 国家/货币/汇率列表 |
| GET | /api/search | ES多语言搜索 |
| GET | /api/reviews/{productId} | 商品评价列表 |
| GET | /api/flash-sales | 当前秒杀 |
| GET | /api/group-buys | 当前拼团 |
| GET | /api/faq | FAQ(按语言+分类) |
| GET | /api/cms/{slug} | CMS页面 |
| GET | /api/settings | 公开配置 |
| GET | /api/size-charts | 尺码对照表 |
| GET | /api/tariff/estimate | 关税估算 |
| GET | /api/shipping/calculate | 运费计算 |
| GET | /api/payment/methods | 可用支付方式 |
| GET | /api/geoip/detect | GeoIP检测 |
| GET | /api/compliance/check | 合规检查 |

### A.2 认证接口 (47端点)

| 方法 | 路径 | 说明 |
|------|------|------|
| GET/PUT | /api/user/profile | 个人信息 |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | 地址CRUD |
| PUT | /api/user/locale | 更新语言/币种 |
| GET/POST | /api/wishlist[/{id}] | 收藏夹 |
| GET/POST | /api/price-alerts | 降价提醒 |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | 购物车 |
| GET/POST | /api/orders | 订单列表/创建(PosterVerify) |
| GET | /api/orders/{id} | 订单详情 |
| POST | /api/orders/{id}/cancel | 取消订单 |
| GET | /api/orders/{id}/documents/invoice | 商业发票 |
| GET | /api/orders/{id}/documents/packing-list | 装箱单 |
| POST | /api/payment/create | 创建支付(PosterVerify) |
| GET | /api/payment/status/{id} | 支付状态 |
| GET/POST | /api/returns[/{id}] | 退货 |
| GET | /api/returns/{id}/label | 退货面单 |
| POST | /api/reviews | 发表评价 |
| GET/POST | /api/coupons[/{id}/claim] | 优惠券 |
| GET/PUT | /api/notifications[/{id}/read] | 通知 |
| GET/POST/DELETE | /api/comparisons[/{id}] | 商品对比 |
| GET | /api/recommendations | 个性化推荐 |
| GET | /api/affiliate/links | 分销链接 |
| GET | /api/affiliate/commissions | 分销佣金 |
| GET | /api/membership | 会员等级 |
| GET | /api/points | 积分流水 |
| GET/POST | /api/gift-cards | 礼品卡 |
| GET/POST | /api/b2b/quotes | B2B询价 |
| GET/POST | /api/privacy/request | GDPR请求 |
| GET | /api/export/orders | 导出订单 |

### A.3 Webhook (1端点)

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /webhook/payment/{gateway} | 支付异步通知(验签) |

### A.4 Admin 与健康检查 (2端点)

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | 后台退款执行 |
| GET | /health | 健康检查 |

---

## 附录: API 设计规范

### 版本控制

版本通过 `API-Version: 2026-05-20` header传递，不在URL中。VersionRoute中间件映射。

### 中间件管道

```
Cors → Security(31类) → RateLimit(滑动窗口) → Platform(8平台) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### 端点统计

- 公开接口: 23个 (认证/商品/分类/内容/搜索/服务)
- 认证接口: 47个 (用户/购物车/订单/支付/退货/评价/营销)
- Webhook: 1个 (支付回调)
- Admin: 1个 (退款执行)
- Health: 1个 (/health 健康检查)

### 统一响应

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### hg/apidoc 动态文档

使用 hg/apidoc 根据控制器注解自动生成。启动后访问 `/apidoc/`。

注解示例:
```php
/**
 * @Apidoc\Title("用户登录")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
