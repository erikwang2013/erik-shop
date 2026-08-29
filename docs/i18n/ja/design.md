# 越境ECプラットフォーム — 設計ドキュメント

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. データベース設計

### 1.1 命名規則

- テーブルプレフィックス: `erik_`
- 主キー: `id BIGINT UNSIGNED NOT NULL` (snowflake生成、非オートインクリメント)
- タイムスタンプ: `created_at`, `updated_at`, `deleted_at` (ソフトデリート)
- エンジン: InnoDB、文字セット: utf8mb4_unicode_ci

### 1.2 モジュール構成 (110テーブル)

| モジュール | テーブル数 | コアテーブル |
|------|------|--------|
| ユーザーとアカウント | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| 商品とカテゴリ | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| 取引 | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| 決済と資金 | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| 物流 | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| 税関と税務 | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| マーケティング | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| サプライチェーン | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| 風控とコンプライアンス | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| マルチプラットフォーム | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| コンテンツと体験 | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| サブスクリプションとB2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| カスタマーサポート | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| ABテスト | 3 | ab_tests, ab_test_variants, ab_test_results |
| APIガバナンス | 2 | api_rate_limits, api_docs |
| 基礎データ | 3 | countries, currencies, exchange_rates |

### 1.3 プラットフォーム追跡フィールド

| テーブル | フィールド | 説明 |
|----|------|------|
| orders | platform VARCHAR(16) | 注文プラットフォーム |
| payments | platform VARCHAR(16) | 決済プラットフォーム |
| operation_logs | platform VARCHAR(16) | 操作プラットフォーム |
| users | last_login_platform VARCHAR(16) | 最終ログインプラットフォーム |
| search_logs | platform VARCHAR(16) | 検索プラットフォーム |
| chat_messages | platform VARCHAR(16) | メッセージの出所 |

---

## 2. API設計

API のバージョン管理、ミドルウェアパイプライン、エンドポイント統計と統一レスポンス仕様については、[APIインターフェースドキュメント](api.md) を参照。

---

## 3. セキュリティ設計

### 3.1 SecurityMiddleware が security-php の31検出器をカプセル化

| # | タイプ | エラーコード | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | SQLインジェクション | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | パストラバーサル | 40004 | ✅ | ✅ |
| 5 | Body過大 | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | ファイルアップロード | 40009 | ✅ | ✅ |
| 8 | セキュリティレスポンスヘッダー | — | ✅ | ✅ |
| 9 | ブルートフォース | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | HTTPメソッド | 40012 | ✅ | ✅ |
| 13 | Hostヘッダー | 40013 | ✅ | — |
| 14 | 機密情報マスキング | — | ✅ | ✅ |
| 15 | CORSホワイトリスト | — | ⚠️ | ⚠️ |

### 3.2 三層暗号化

| レイヤー | 技術 | パッケージ |
|------|------|-----|
| 転送層 | AES-256-CBC | erikwang2013/encryption |
| データベース層 | Encryptable trait | erikwang2013/encryptable (Maize) |
| ID難読化 | Hashids | erikwang2013/hashids |

---

## 4. 高並行処理設計

### 4.1 限流

トークンバケットスライディングウィンドウ(Redis ZSET、support\Redis ファサード経由): デフォルト60s/100回、ログイン10回/60s、登録5回/300s、ソーシャルログイン5回/300s、決済5回/60s、注文3回/10s、検索10回/1s

### 4.2 サーキットブレーカーと降級

Redis サーキットブレーカー（`app\common\CircuitBreaker`）: 決済ゲートウェイ/ソーシャルログインなどの外部API呼び出しはすべて `CircuitBreaker::call()` を経由 — 連続5回失敗で30s間遮断、TTL期限切れ後は次のリクエストが自動でハーフオープンプローブを送り、成功すれば即リセット。業務例外ホワイトリスト（無効カード/無効トークン）は失敗にカウントせず、攻撃者が無効リクエストで依存サービスを落とすのを防止; Redis 利用不可時は自動降級して通過（fail-open）。遮断中はインターフェースが 503「サービス一時利用不可」を返します。

### 4.3 Redis の用途

Redis は限流トークンバケット（`support\Redis` ファサード）、人機認証コードと Session 保存に使用します。業務データはアプリケーション層キャッシュをせず、MySQL を直接読み取ります（読み書き分離 + コネクションプール）。

また、admin の CDN 全体オン/オフ状態は共有 Redis（prefix `shop:`、TTL 60s）経由で service に伝播します。

### 4.4 コネクションプール

MySQL: 50max/10min/2sタイムアウト | 読み書き分離: 30max/5min (2リードレプリカ、sticky=true) | Redis: 30max/5min



---

## 5. 国際化

- インターフェース: zh_CN, zh_HK, en, ja, ko
- コンテンツ: erik_product_translations が locale ごとに独立行
- 価格: erik_product_sku_prices が通貨ごとに独立価格設定
- ヘッダー: Accept-Language + API-Version

## 6. APIドキュメント

hg/apidoc を使用し、コントローラーのアノテーションから自動生成します。詳細は [APIインターフェースドキュメント](api.md) を参照。起動後に `/apidoc/` へアクセス。

## 7. テスト

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

詳しくは: [機能設計ドキュメント](features.md) | [完全アーキテクチャドキュメント](architecture-full.md) | [デプロイドキュメント](deployment.md)
