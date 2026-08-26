# 越境ECプラットフォーム — API インターフェースドキュメント

> 動的ドキュメント: Service 起動後に http://localhost:8787/apidoc/ へアクセス（hg/apidoc 自動生成）



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 共通仕様

### リクエスト形式

| 項目 | 説明 |
|------|------|
| Base URL | `http://localhost:8787/api` |
| バージョン管理 | `API-Version: 2026-05-20` header（URL には含めない） |
| 認証 | `Authorization: Bearer <token>` header |
| 言語 | `Accept-Language: zh_CN|zh_HK|en|ja|ko` header |
| プラットフォーム | `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` header |
| Content-Type | `application/json` (POST/PUT) |
| 人機認証 | `X-Poster-Token: <token>` header（機密操作） |

### レスポンス形式

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

### ID の説明

すべてのインターフェースの ID フィールドは hashids エンコード済み文字列（例 `Ab3xK9pq`）で、ミドルウェアが自動でエンコード/デコードします。フロントエンド側での手動処理は不要です。

---

## 1. 認証インターフェース

### 1.1 登録 `POST /api/auth/register`

> 人機認証 `X-Poster-Token` が必要

**リクエスト:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**レスポンス:**
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

### 1.2 ログイン `POST /api/auth/login`

**リクエスト:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**レスポンス:**
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

### 1.3 Token 更新 `POST /api/auth/refresh`

**リクエスト:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**レスポンス:**
```json
{
  "code": 0, "msg": "Token已刷新",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 ソーシャルログイン `POST /api/auth/social`

**リクエスト:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**レスポンス:**
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

## 2. 商品インターフェース

### 2.1 商品リスト `GET /api/products`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| page | int | いいえ | ページ番号 (デフォルト1) |
| per_page | int | いいえ | 1ページあたりの件数 (デフォルト20, 最大100) |
| category_id | string | いいえ | カテゴリID (hashid、サブカテゴリ含む) |
| keyword | string | いいえ | 検索キーワード |
| sort | string | いいえ | 並び順: default/price_asc/price_desc/sales/newest |
| min_price | number | いいえ | 最低価格 |
| max_price | number | いいえ | 最高価格 |

**レスポンス:**
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

### 2.2 商品詳細 `GET /api/products/{id}`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| currency | string | いいえ | 通貨コード (デフォルトUSD) |
| dest_country | string | いいえ | 目的国の ISO2 (デフォルトUS) |

**レスポンス:**
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

### 2.3 商品レビュー `GET /api/reviews/{productId}`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| page | int | いいえ | ページ番号 |
| per_page | int | いいえ | 1ページあたり (デフォルト10) |
| rating | int | いいえ | 評価フィルタ (1-5) |

**レスポンス:**
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

## 3. カテゴリインターフェース

### 3.1 カテゴリリスト `GET /api/categories`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| parent_id | int | いいえ | 親カテゴリID (0=最上位) |

### 3.2 カテゴリツリー `GET /api/categories/tree`

完全なネスト構造のカテゴリツリーを返します。

**レスポンス:**
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

## 4. カートインターフェース `[JWT]`

### 4.1 カートリスト `GET /api/cart`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| currency | string | いいえ | 通貨 (デフォルトUSD) |

**レスポンス:**
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

### 4.2 カートに追加 `POST /api/cart`

**リクエスト:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 数量の更新 `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> quantity=0 で自動削除

### 4.4 削除 `DELETE /api/cart/{id}`

---

## 5. 注文インターフェース `[JWT]`

### 5.1 注文リスト `GET /api/orders`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| status | int | いいえ | 状態フィルタ:0支払い待ち/1支払い済み/2発送済み/3受取済み/4完了/5キャンセル済み/6返金処理中/7返金済み/8審査待ち |
| page | int | いいえ | ページ番号 (デフォルト1) |
| per_page | int | いいえ | 1ページあたり (デフォルト10) |

**レスポンス:**
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

### 5.2 注文詳細 `GET /api/orders/{id}`

items/logs/documents を含む完全な注文情報を返します。

### 5.3 注文の作成 `POST /api/orders` `[PosterVerify]`

**リクエスト:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**レスポンス:**
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

### 5.4 注文のキャンセル `POST /api/orders/{id}/cancel`

> 状態=0（支払い待ち）のみキャンセル可能

### 5.5 商業インボイス `GET /api/orders/{id}/documents/invoice`

PDF ファイルのダウンロードリンクを返します。

### 5.6 梱包明細 `GET /api/orders/{id}/documents/packing-list`

---

## 6. 決済インターフェース `[JWT]`

### 6.1 利用可能な決済方法 `GET /api/payment/methods`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| country | string | いいえ | ISO2 (デフォルトUS) |
| currency | string | いいえ | 通貨 (デフォルトUSD) |

**レスポンス:**
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

### 6.2 決済の作成 `POST /api/payment/create` `[PosterVerify]`

**リクエスト:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**レスポンス:**
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

### 6.3 決済状態 `GET /api/payment/status/{id}`

### 6.4 Webhook コールバック `POST /webhook/payment/{gateway}`

> JWT 不要。決済ゲートウェイから非同期で呼び出されます。署名検証が必要です。

---

## 7. 物流インターフェース

### 7.1 送料計算 `GET /api/shipping/calculate`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| dest_country_id | int | はい | 目的国ID (snowflake) |
| weight | int | いいえ | 重量(グラム) (デフォルト500) |

**レスポンス:**
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

## 8. 関税インターフェース

### 8.1 関税の見積もり `GET /api/tariff/estimate`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| product_id | string | はい | 商品ID (hashid) |
| dest_country_id | int | はい | 目的国ID |
| declared_value | number | はい | 申告価額 |

**レスポンス:**
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

## 9. 返品インターフェース `[JWT]`

### 9.1 返品リスト `GET /api/returns`

### 9.2 返品の申請 `POST /api/returns`

**リクエスト:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 返品用ラベル `GET /api/returns/{id}/label`

---

## 10. ユーザーインターフェース `[JWT]`

### 10.1 個人情報 `GET /api/user/profile`

### 10.2 情報の更新 `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 住所リスト `GET /api/user/addresses`

### 10.4 住所の追加 `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "家"
}
```

### 10.5 住所の更新 `PUT /api/user/addresses/{id}`

### 10.6 住所の削除 `DELETE /api/user/addresses/{id}`

### 10.7 言語・通貨 `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. マーケティングインターフェース

### 11.1 カルーセル `GET /api/banners?position=home`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| position | string | いいえ | 位置: home/category/product |

### 11.2 利用可能なクーポン `GET /api/coupons` `[JWT]`

### 11.3 クーポンの取得 `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 タイムセールリスト `GET /api/flash-sales`

### 11.5 グループ購入リスト `GET /api/group-buys`

### 11.6 販売代理店リンク `GET /api/affiliate/links` `[JWT]`

### 11.7 販売代理店コミッション `GET /api/affiliate/commissions` `[JWT]`

---

## 12. 会員インターフェース `[JWT]`

### 12.1 会員情報 `GET /api/membership`

**レスポンス:**
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

### 12.2 ポイント明細 `GET /api/points`

---

## 13. その他のインターフェース

### 13.1 国データ `GET /api/countries`

利用可能なすべての国/通貨/為替レート/デフォルト値を返します。

### 13.2 公開設定 `GET /api/settings?group=general`

### 13.3 ES検索 `GET /api/search?keyword=xxx`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| keyword | string | はい | 検索語 |
| category_id | string | いいえ | カテゴリフィルタ |
| page | int | いいえ | ページ番号 |

### 13.4 商品比較 `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE は比較レコードの id が必要です：`DELETE /api/comparisons/{id}`（`{id}` は比較レコードの ID、必須）

### 13.5 パーソナライズドレコメンド `GET /api/recommendations` `[JWT]`

### 13.6 値下げ通知 `GET/POST /api/price-alerts` `[JWT]`

### 13.7 お気に入り `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 通知 `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 CMSページ `GET /api/cms/{slug}`

### 13.11 サイズ換算表 `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 コンプライアンスチェック `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 GeoIP検出 `GET /api/geoip/detect`

### 13.14 レビュー投稿 `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 ギフトカード残高 `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 ギフトカードの引き換え `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 GDPRリクエスト `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 注文のエクスポート `GET /api/export/orders` `[JWT]`

| パラメータ | 型 | 必須 | 説明 |
|------|------|------|------|
| date_from | string | いいえ | 開始日 (YYYY-MM-DD) |
| date_to | string | いいえ | 終了日 |

CSV ファイルのダウンロードを返します。

### 13.19 B2B見積もり照会 `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 ヘルスチェック `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## 付録: ステータスコード対応表

### 注文ステータス

| 値 | 説明 |
|----|------|
| 0 | 支払い待ち |
| 1 | 支払い済み |
| 2 | 発送済み |
| 3 | 受取済み |
| 4 | 完了 |
| 5 | キャンセル済み |
| 6 | 返金処理中 |
| 7 | 返金済み |
| 8 | 審査待ち (風控) |

### 商品ステータス

| 値 | 説明 |
|----|------|
| 0 | 下書き |
| 1 | 審査待ち |
| 2 | 出品中 |
| 3 | 出品停止 |

### 決済ステータス

| 値 | 説明 |
|----|------|
| 0 | 未払い |
| 1 | 支払い済み |
| 2 | 返金済み |
| 3 | 失敗 |

### 国の価格表示モード

| 値 | 説明 |
|----|------|
| tax_inclusive | 税込価格 (EU/UK) |
| tax_exclusive | 税別価格 (US/CA) |
| both | 併記表示 (JP) |

---

## 付録: ミドルウェアパイプライン

```
请求 → Cors → Security(31类) → RateLimit(令牌桶) → Platform(8平台)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → 控制器
```

标识: `[JWT]` 需认证 | `[PosterVerify]` 需人机验证 | 无标记 = 公开接口

---

## 付録: エンドポイント統計の総覧

### A.1 公開インターフェース (23エンドポイント)

| メソッド | パス | 説明 |
|------|------|------|
| POST | /api/auth/register | 登録(PosterVerify) |
| POST | /api/auth/login | ログイン |
| POST | /api/auth/refresh | Token更新 |
| POST | /api/auth/social | ソーシャルログイン |
| GET | /api/products | 商品リスト(ページング+フィルタ+ソート) |
| GET | /api/products/{id} | 商品詳細(多言語+多通貨+コンプライアンス+HS) |
| GET | /api/categories | カテゴリリスト |
| GET | /api/categories/tree | カテゴリツリー |
| GET | /api/banners | カルーセル(位置+地域別) |
| GET | /api/countries | 国/通貨/為替レートリスト |
| GET | /api/search | ES多言語検索 |
| GET | /api/reviews/{productId} | 商品レビューリスト |
| GET | /api/flash-sales | 現在のタイムセール |
| GET | /api/group-buys | 現在のグループ購入 |
| GET | /api/faq | FAQ(言語+カテゴリ別) |
| GET | /api/cms/{slug} | CMSページ |
| GET | /api/settings | 公開設定 |
| GET | /api/size-charts | サイズ換算表 |
| GET | /api/tariff/estimate | 関税の見積もり |
| GET | /api/shipping/calculate | 送料計算 |
| GET | /api/payment/methods | 利用可能な決済方法 |
| GET | /api/geoip/detect | GeoIP検出 |
| GET | /api/compliance/check | コンプライアンスチェック |

### A.2 認証インターフェース (47エンドポイント)

| メソッド | パス | 説明 |
|------|------|------|
| GET/PUT | /api/user/profile | 個人情報 |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | 住所CRUD |
| PUT | /api/user/locale | 言語/通貨の更新 |
| GET/POST | /api/wishlist[/{id}] | お気に入り |
| GET/POST | /api/price-alerts | 値下げ通知 |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | カート |
| GET/POST | /api/orders | 注文リスト/作成(PosterVerify) |
| GET | /api/orders/{id} | 注文詳細 |
| POST | /api/orders/{id}/cancel | 注文のキャンセル |
| GET | /api/orders/{id}/documents/invoice | 商業インボイス |
| GET | /api/orders/{id}/documents/packing-list | 梱包明細 |
| POST | /api/payment/create | 決済の作成(PosterVerify) |
| GET | /api/payment/status/{id} | 決済状態 |
| GET/POST | /api/returns[/{id}] | 返品 |
| GET | /api/returns/{id}/label | 返品用ラベル |
| POST | /api/reviews | レビュー投稿 |
| GET/POST | /api/coupons[/{id}/claim] | クーポン |
| GET/PUT | /api/notifications[/{id}/read] | 通知 |
| GET/POST/DELETE | /api/comparisons[/{id}] | 商品比較 |
| GET | /api/recommendations | パーソナライズドレコメンド |
| GET | /api/affiliate/links | 販売代理店リンク |
| GET | /api/affiliate/commissions | 販売代理店コミッション |
| GET | /api/membership | 会員レベル |
| GET | /api/points | ポイント明細 |
| GET/POST | /api/gift-cards | ギフトカード |
| GET/POST | /api/b2b/quotes | B2B見積もり照会 |
| GET/POST | /api/privacy/request | GDPRリクエスト |
| GET | /api/export/orders | 注文のエクスポート |

### A.3 Webhook (1エンドポイント)

| メソッド | パス | 説明 |
|------|------|------|
| POST | /webhook/payment/{gateway} | 決済非同期通知(署名検証) |

### A.4 Admin とヘルスチェック (2エンドポイント)

| メソッド | パス | 説明 |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | 管理画面の返金実行 |
| GET | /health | ヘルスチェック |

---

## 付録: API 設計仕様

### バージョン管理

バージョンは `API-Version: 2026-05-20` header で渡し、URL には含めません。VersionRoute ミドルウェアでマッピングします。

### ミドルウェアパイプライン

```
Cors → Security(31类) → RateLimit(滑动窗口) → Platform(8平台) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### エンドポイント統計

- 公開インターフェース: 23個 (認証/商品/カテゴリ/コンテンツ/検索/サービス)
- 認証インターフェース: 47個 (ユーザー/カート/注文/決済/返品/レビュー/マーケティング)
- Webhook: 1個 (決済コールバック)
- Admin: 1個 (返金実行)
- Health: 1個 (/health ヘルスチェック)

### 統一レスポンス

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### hg/apidoc 動的ドキュメント

hg/apidoc を使用し、コントローラーのアノテーションから自動生成します。起動後に `/apidoc/` へアクセスします。

アノテーション例:
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
