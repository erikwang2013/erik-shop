# 跨境电商平台 — 功能设计文档

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 功能总览

### 1.1 模块矩阵

| 一级模块 | 二级模块 | 优先级 | 状态 |
|---------|---------|--------|------|
| 用户系统 | 注册/登录/社交登录/KYC实名 | P0 | ✅ |
| | 地址管理/收藏夹/浏览历史/商品对比 | P1 | ✅ |
| | 会员等级/积分/礼品卡 | P2 | ✅ |
| 商品系统 | 分类管理(树形)/商品CRUD/SKU变体 | P0 | ✅ |
| | 多语言内容/分币种定价/商品图片 | P0 | ✅ |
| | 属性管理/品牌/合规标签/HS Code关联 | P0 | ✅ |
| | 商品Feed(Google/Meta)/ES多语言搜索 | P1 | ✅ |
| 交易系统 | 购物车/订单创建/订单状态流转 | P0 | ✅ |
| | 支付(Stripe/PayPal/Klarna)/退款/3DS | P0 | ✅ |
| | 退货(双通道)/商业发票/装箱单 | P0 | ✅ |
| 物流系统 | 国际物流商/物流分区/分区费率 | P0 | ✅ |
| | 海外仓(发货仓+退货仓)/库存流水 | P1 | ✅ |
| | 发货(HS申报)/物流轨迹/物流保险 | P1 | ✅ |
| 海关税务 | HS Code编码库/商品HS关联 | P0 | ✅ |
| | 关税规则/增值税VAT/IOSS | P0 | ✅ |
| | 各国合规限制(禁售/限售/允许) | P0 | ✅ |
| 营销系统 | 优惠券(分区+新老客)/轮播图(区域可见) | P1 | ✅ |
| | 秒杀/拼团/分销(链接+佣金+提现) | P2 | ✅ |
| 供应链 | 供应商管理/采购单/质检(入库+出库) | P1 | ✅ |
| | 库存流水(不可变账本)/库存调拨 | P1 | ✅ |
| 风控合规 | 风控规则引擎/风控日志(旁路打分) | P1 | ✅ |
| | GDPR/CCPA数据请求/Cookie Consent | P1 | ✅ |
| 多平台 | Amazon/eBay/Shopee刊登/订单聚合 | P2 | ✅ |
| | 多店铺管理/多商家入驻(第三方卖家) | P2 | ✅ |
| 内容管理 | CMS页面(多语言)/FAQ/知识库 | P2 | ✅ |
| | 邮件模板/系统通知/降价提醒 | P2 | ✅ |
| | 尺码对照表/评价翻译 | P2 | ✅ |
| 增长工具 | B2B批发(阶梯定价/企业认证/询价) | P2 | ✅ |
| | 订阅周期购/AB测试 | P3 | ✅ |
| 客服 | WebSocket实时IM/知识库 | P3 | ✅ |
| 基础设施 | Snowflake ID/JWT认证/Hashids编解码 | P0 | ✅ |
| | Encryption(接口+数据库)/Poster人机验证 | P0 | ✅ |
| | API版本控制/国际化/GeoIP识别 | P0 | ✅ |
| | API限流/OpenAPI文档/操作日志 | P2 | ✅ |

---

## 2. 核心业务流程

### 2.1 用户注册登录

```
注册流程:
  EMAIL注册: 输入email+password → Poster验证 → bcrypt(password+salt)
           → Snowflake生成ID → JWT签发(access+refresh) → 返回token
  社交登录: Google/Apple/Facebook OAuth → 获取id_token
          → 验证id_token → 查erik_user_social_accounts
          → 已绑定: 直接登录 / 未绑定: 自动创建用户+绑定
          → JWT签发 → 返回token

登录流程:
  email+password → 查erik_users(email) → password_verify(password+salt)
  → 更新last_login_at/last_login_ip → JWT签发 → 返回token

Token刷新:
  refresh_token → Jwt::decode验证 → 签发新access_token → 返回
```

### 2.2 商品浏览与搜索

```
商品列表:
  GET /api/products
  → 筛选: category_id(含子分类)/status/keyword/price_range
  → 排序: default/price_asc/price_desc/sales/newest
  → 多语言: ProductTranslations按Accept-Language locale过滤
  → 分币种: ProductSkuPrices按currency_code匹配
  → 分页返回(20条/页)

ES搜索:
  GET /api/search?keyword=xxx
  → Erikwang2013\WebmanScout\Searchable
  → 多语言分析器(中文/英文/日文/韩文)
  → 聚合: category_id/price_range/brand
  → 降级: ES不可用时自动切换MySQL LIKE

商品详情:
  GET /api/products/{hashid}
  → HashidsDecode中间件解码
  → Eager Load: skus.prices, images, translations, compliance, hsCodes
  → 多语言匹配 + 分币种价格计算 + VAT含/不含税
  → 合规信息 + HS Code + 尺码转换
  → view_count +1
```

### 2.3 购物车与下单

```
购物车:
  POST /api/cart {sku_id, quantity}
  → 校验SKU存在|上架|库存充足
  → 已存在同SKU: 累加数量 / 不存在: 创建
  → 返回最新购物车列表(含价格/图片/库存)

下单流程:
  POST /api/orders {address_id, coupon_id, currency_code}
  → 1. 校验收货地址存在且属于当前用户
  → 2. 获取购物车已选中商品(Carts.where(selected=1))
  → 3. 逐商品校验:
       - SKU存在且已上架
       - 库存充足(>=quantity)
       - 合规检查(Compliance::check → 禁售商品阻止下单)
  → 4. 计算价格:
       - 按currency_code查ProductSkuPrices
       - 无独立定价时降级汇率换算
       - 应用优惠券(满减/折扣/固定金额)
  → 5. 生成订单号 ORD20260521XXXXXX
  → 6. 创建Order → 创建OrderItems(价格/属性快照)
  → 7. 扣减库存(InventoryManager::outbound)
  → 8. 写OrderLog(创建)
  → 9. 风控打分(RiskEngine::score) — 旁路不阻塞
  → 10. 清除购物车已购商品
  → 返回order_id + order_no + total_amount

取消订单:
  POST /api/orders/{id}/cancel
  → 校验订单属于当前用户且状态=0(待付款)
  → 恢复库存(InventoryManager::inbound)
  → 更新status=5(已取消) + canceled_at
  → 写OrderLog(取消)
```

### 2.4 支付流程

```
可用支付方式:
  GET /api/payment/methods?country=DE&currency=EUR
  → 查PaymentGatewayMethods表(按country+currency过滤)
  → 返回: card, ideal, sofort, paypal, klarna_paylater, afterpay...

创建支付:
  POST /api/payment/create {order_id, gateway, method}
  → 校验订单状态=0(待付款)
  → 创建Payment记录(状态=待支付)
  → PaymentGateway::make(gateway)→createPayment()
  → Stripe: 创建PaymentIntent → 返回client_secret
  → 前端: 调用Stripe SDK完成支付(+3DS)

Webhook回调:
  POST /webhook/payment/stripe
  → 验签(PaymentGateway::verifyWebhook)
  → payment_intent.succeeded:
     → 更新Payment.status=已支付, paid_at=now
     → 更新Order.status=已付款, pay_at=now
     → 创建PlatformSettlement(平台佣金+支付手续费+供应商金额+分销佣金)
  → 返回200

BNPL分期(Klarna/Afterpay):
  → Klarna创建订单 → 前端渲染分期选择器 → 用户选分期方案
  → Klarna回调 → 更新支付状态 → 后续自动扣款由Klarna处理
```

### 2.5 订单状态机

```
                  ┌──────────────┐
                  │  0: 待付款   │
                  └──┬───┬───┬──┘
           支付成功  │   │   │  用户取消
                    │   │   └──────────────┐
                    ▼   │                  ▼
            ┌──────────┐│          ┌──────────┐
            │ 1: 已付款 ││          │ 5: 已取消 │
            └────┬─────┘│          └──────────┘
           发货  │      │退款申请
                 ▼      ▼
         ┌──────────┐ ┌──────────┐
         │ 2: 已发货 │ │ 6: 退款中 │
         └────┬─────┘ └────┬─────┘
       收货   │        退款│
              ▼            ▼
      ┌──────────┐  ┌──────────┐
      │ 3: 已收货 │  │ 7: 已退款 │
      └────┬─────┘  └──────────┘
    完成   │
           ▼
    ┌──────────┐      ┌──────────┐
    │ 4: 已完成 │      │ 8: 待审核 │ ← 风控高分订单
    └──────────┘      └──────────┘
```

### 2.6 退货流程

```
用户申请退货:
  POST /api/returns {order_id, reason_id}
  → 校验订单状态(已发货/已收货/已完成可退)
  → 判断退货通道:
     1. 目的国有退货仓 → type=1(当地仓), 生成return_warehouse_id
     2. 无当地退货仓 → type=2(退回国内)
     3. 货值<阈值 → 建议仅退款不退货
  → 创建ReturnOrder(status=待审)

管理员审核:
  → 通过: status=已通过 → 生成ReturnLabel(物流面单)
  → 驳回: status=已驳回 + 驳回原因

用户退货寄回:
  → 下载退货面单 → 寄回 → 物流更新
  → 仓库收货 → status=已收货

退款处理:
  → status=已完成 → 关联Refund创建退款
  → PaymentGateway::refundPayment → 原路退回
```

### 2.7 关税估算

```
GET /api/tariff/estimate?product_id=xxx&dest_country_id=xxx&declared_value=100

估算逻辑:
  1. 查商品HS Code: ProductHsCodes → HsCode
  2. 查关税规则: TariffRules(dest_country_id + hs_code_id)
     → duty_rate: 关税率%
     → duty_free_threshold: 关税起征点
  3. 查VAT设置: VatSettings(country_id)
     → vat_rate: 增值税率%
     → vat_free_threshold: 增值税起征点
  4. 计算:
     duty = declared_value >= duty_free_threshold
            ? declared_value * duty_rate / 100
            : 0
     vat  = (declared_value + duty) >= vat_free_threshold
            ? (declared_value + duty) * vat_rate / 100
            : 0
  5. 返回: {duty_rate, vat_rate, estimated_duty, estimated_vat, estimated_total,
            hs_code, is_estimate: true,
            disclaimer: "仅供参考，实际以海关核定为准"}
```

---

## 3. 数据表关系图

```
erik_users ──┬── erik_user_addresses (user_id)
             ├── erik_user_social_accounts (user_id)
             ├── erik_user_wishlists (user_id)
             ├── erik_user_kyc (user_id)
             ├── erik_carts (user_id)
             ├── erik_orders (user_id)
             ├── erik_product_reviews (user_id)
             ├── erik_coupons (via erik_user_coupons)
             ├── erik_notifications (user_id)
             ├── erik_subscriptions (user_id)
             ├── erik_point_logs (user_id)
             ├── erik_affiliate_links (user_id)
             ├── erik_chat_sessions (user_id)
             ├── erik_b2b_verifications (user_id)
             └── erik_privacy_requests (user_id)

erik_products ──┬── erik_product_translations (product_id, locale)
                ├── erik_product_skus (product_id)
                │      └── erik_product_sku_prices (sku_id, currency_code)
                ├── erik_product_images (product_id)
                ├── erik_product_reviews (product_id)
                ├── erik_product_compliance (product_id)
                │      └── erik_compliance_categories (compliance_category_id)
                ├── erik_product_hs_codes (product_id)
                │      └── erik_hs_codes (hs_code_id)
                ├── erik_product_recommendations (product_id)
                ├── erik_b2b_prices (sku_id)
                └── erik_platform_listings (product_id)

erik_orders ──┬── erik_order_items (order_id)
              ├── erik_order_logs (order_id)
              ├── erik_payments (order_id)
              ├── erik_refunds (order_id)
              ├── erik_return_orders (order_id)
              │      └── erik_return_labels (return_id)
              ├── erik_order_documents (order_id)
              ├── erik_shipments (order_id)
              ├── erik_platform_settlements (order_id)
              ├── erik_subscription_orders (order_id)
              └── erik_risk_logs (order_id)

erik_countries ──┬── erik_vat_settings (country_id)
                 ├── erik_tariff_rules (dest_country_id)
                 ├── erik_country_compliance_rules (country_id)
                 ├── erik_shipping_zones (via JSON countries)
                 └── erik_warehouses (country_id)

erik_suppliers ──┬── erik_purchase_orders (supplier_id)

erik_purchase_orders ──┬── erik_purchase_order_items (po_id)
                       └── erik_quality_inspections (po_id)
```

---

## 4. API端点设计

### 4.1 公开接口 (25端点)

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/auth/register | 注册 (PosterVerify) |
| POST | /api/auth/login | 登录 |
| POST | /api/auth/refresh | 刷新Token |
| POST | /api/auth/social | 社交登录 |
| GET | /api/products | 商品列表 (分页+筛选+排序) |
| GET | /api/products/{id} | 商品详情 (多语言+多币种+合规+HS) |
| GET | /api/categories | 分类列表 |
| GET | /api/categories/tree | 分类树 |
| GET | /api/banners | 轮播图 (按位置+区域) |
| GET | /api/countries | 国家/货币/汇率 |
| GET | /api/search | ES多语言搜索 |
| GET | /api/reviews/{productId} | 商品评价列表 |
| GET | /api/flash-sales | 当前秒杀 |
| GET | /api/group-buys | 当前拼团 |
| GET | /api/faq | FAQ (按语言+分类) |
| GET | /api/cms/{slug} | CMS页面 |
| GET | /api/settings | 公开配置 |
| GET | /api/size-charts | 尺码对照表 |
| GET | /api/tariff/estimate | 关税估算 |
| GET | /api/shipping/calculate | 运费计算 |
| GET | /api/payment/methods | 可用支付方式 |
| GET | /api/geoip/detect | GeoIP检测 |
| GET | /api/compliance/check | 合规检查 |

### 4.2 认证接口 (45端点)

| 方法 | 路径 | 说明 |
|------|------|------|
| GET/PUT | /api/user/profile | 个人信息 |
| GET | /api/user/addresses | 地址列表 |
| POST/PUT/DELETE | /api/user/addresses/{id} | 地址CRUD |
| PUT | /api/user/locale | 更新语言/币种 |
| GET/POST | /api/wishlist | 收藏夹 |
| GET/POST | /api/price-alerts | 降价提醒 |
| GET/POST/PUT/DELETE | /api/cart | 购物车 |
| GET/POST | /api/orders | 订单列表/创建 (PosterVerify) |
| GET | /api/orders/{id} | 订单详情 |
| POST | /api/orders/{id}/cancel | 取消订单 |
| GET | /api/orders/{id}/documents/invoice | 商业发票 |
| GET | /api/orders/{id}/documents/packing-list | 装箱单 |
| POST | /api/payment/create | 创建支付 (PosterVerify) |
| GET | /api/payment/status/{id} | 支付状态 |
| GET/POST | /api/returns | 退货 |
| GET | /api/returns/{id}/label | 退货面单 |
| POST | /api/reviews | 发表评价 |
| GET/POST | /api/coupons | 优惠券 |
| GET | /api/notifications | 通知 |
| GET/POST | /api/comparisons | 商品对比 |
| GET | /api/recommendations | 个性化推荐 |
| GET | /api/affiliate/links | 分销链接 |
| GET | /api/affiliate/commissions | 分销佣金 |
| GET | /api/membership | 会员等级 |
| GET | /api/points | 积分流水 |
| GET/POST | /api/gift-cards | 礼品卡 |
| GET/POST | /api/b2b/quotes | B2B询价 |
| GET/POST | /api/privacy/request | GDPR请求 |
| GET | /api/export/orders | 导出订单 |

### 4.3 Webhook (1端点)

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /webhook/payment/{gateway} | 支付异步通知 |
