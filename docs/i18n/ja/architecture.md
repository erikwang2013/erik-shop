# 越境ECプラットフォーム — アーキテクチャ概要

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 技術スタック

| レイヤー | 技術 | バージョン |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| クライアント | Flutter (5プラットフォーム) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| データベース | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| 決済 | Stripe / PayPal / Klarna / Adyen | — |

## 2. ディレクトリ構造

```
shop-php/
  service/           業務API (251 PHPファイル)
    config/            35設定 (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/    39コントローラー (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111モデル (BaseModel + 110業務モデル)
    app/middleware/     14ミドルウェア (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8ユーティリティクラス (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (ルートの install.sql に置き換え済み) + seeders
    tests/              4テストクラス (22 tests, 45 assertions)
  admin/             管理バックエンド (239 PHPファイル)
    plugin/admin/app/controller/shop/ 82コントローラー
    plugin/admin/app/model/shop/      76モデル
    plugin/admin/app/view/shop/       EChartsダッシュボード
    app/middleware/    5ミドルウェア (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/              クライアント
    flutter/lib/      25 Dart (11ページ + コア層 + ルーティング)
    harmonyos/        14 ArkTS (9ページ + APIクライアント + グローバル状態)
  docs/               5設計ドキュメント
  .claude/skills/     38開発規範Skills
```

## 3. ミドルウェアパイプライン

```
Service: Cors → Security(31種の攻撃検知) → RateLimit(トークンバケット限流) → Platform(8プラットフォーム識別)
        → GeoIp(地域) → Locale(言語) → HashidsDecode → VersionRoute
        → (PosterVerify 人機認証) → (JwtAuth Token) → HashidsEncode → Encryption(インターフェース暗号化)

Admin:  Security → Platform → HashidsDecode → AccessControl(内蔵RBAC) → HashidsEncode
```

## 4. セキュリティ

- **31種の攻撃検知**: XSS/SQLインジェクション/コマンドインジェクション/CRLF/パストラバーサル/Body/ContentType/ファイルアップロード/ブルートフォース/XXE/SSRF/デシリアライゼーション/LDAP/メールヘッダー/SSTI/NoSQL/オープンリダイレクト/JWT攻撃/Host/リクエストスマグリング/GraphQL/XPATH/Log4Shell/SSI/CSV数式/データ漏洩/プロトタイプ汚染/WebSocket/CORS/DNSリバインディング/HTTPメソッド/CSRF Origin
- **三層暗号化**: インターフェース層(AES-256-CBC) + データベース層(Encryptable trait) + ID難読化(Hashids)
- **プラットフォーム追跡**: 8プラットフォーム(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + X-Platform header + 6テーブル記録

## 5. 高並行処理

- **限流**: トークンバケットスライディングウィンドウ(Redis ZSET)、6エンドポイントルール
- **サーキットブレーカー/降級**: Redis サーキットブレーカー — 決済ゲートウェイ/ソーシャルログインの外部API呼び出し、連続5回失敗→30s遮断、ハーフオープンプローブで自動復旧; 業務例外は失敗にカウントしない; Redis 障害時は自動降級して通過させる(503)
- **DB**: 読み書き分離(2リードレプリカ+sticky) + コネクションプール(50/10)
- **遅い処理**: 独立した Cron プロセスが処理（Feed同期/レコメンド計算/決済対帳/按分決済など）

## 6. テスト

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+データ漏洩
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

## 7. デプロイ

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# 手動
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **多言語 (i18n)**: 5言語翻訳ファイル + LocaleMiddleware + Flutter AppLocalizations
- **APIドキュメント**: hg/apidoc自動生成 (6グループ, コントローラーアノテーション駆動)
- **プラットフォーム追跡**: 8プラットフォーム X-Platform header + DB記録

詳しくは: [デプロイドキュメント](deployment.md) | [完全アーキテクチャドキュメント](architecture-full.md) | [機能設計ドキュメント](features.md)
