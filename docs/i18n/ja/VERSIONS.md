# Erik Shop — 越境ECプラットフォーム
webman ファミリーパッケージで構築したフルスタック越境ECプラットフォーム。B2C/B2B のシーンと第三者セラーの出店に対応します。

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## バージョン概要

| | 簡易版 (Lite) | 標準版 (Standard) | 完全版 (Full) |
|---|:---:|:---:|:---:|
| **位置づけ** | 個人開発者 / 小規模EC | 成長型越境セラー | エンタープライズ級フルスタックプラットフォーム |
| **ライセンス** | MIT オープンソース | 商用ライセンス | 商用ライセンス |
| **入手方法** | GitHub 公開ダウンロード | erik@erik.xyz に連絡 | erik@erik.xyz に連絡 |
| **ブランチ** | `lite` | `standard` | `full` |
| **現在** | — | — | ✅ |

---

## 2026-08-27 サーキットブレーカーと降級

- Redis サーキットブレーカー `CircuitBreaker` を新設（`service/app/common/CircuitBreaker.php`）: 決済ゲートウェイ（Stripe/PayPal/Klarna/Adyen）とソーシャルログインの外部呼び出しを統一遮断 — 連続5回失敗→30s遮断、TTL期限切れでハーフオープンプローブによる自動復旧
- 業務例外ホワイトリスト: 無効カード/無効トークンはサーキットブレーカーの失敗にカウントしない（悪意あるリクエストによる依存サービス停止を防止）
- Redis 障害時は自動降級して通過; 遮断中はインターフェースが 503「サービス一時利用不可」を返す
- パラメータ: `config/concurrency.php` → `circuit_breaker` (fail_threshold=5, open_seconds=30)

---

## 2026-08-07 修正記録

| # | 問題 | 深刻度 | 修正 |
|---|------|--------|------|
| 1 | API レスポンス暗号化がミドルウェアに組み込まれていない | Medium | EncryptionMiddleware を新設（X-Encrypt-Response header 駆動）、service パイプラインの第 10 段として登録 |
| 2 | クラス名 Encryption / ファイル名 EncryptionHelper.php の不一致 | Medium | Encryption.php にリネームし、PSR-4 オートロードを修正 |
| 3 | JWT_SECRET_KEY が空 | Low | 32 バイトキーを生成し、JWT_SECRET と JWT_SECRET_KEY の両方を設定 |
| 4 | config/middleware.php が索引配列で "Bad middleware config" により全 worker がクラッシュ | Critical | `'' => [...]` の標準構造に変更（webman は appName => リストを要求） |
| 5 | security-php プラグイン設定に enable キーがなく Config::loadFromDir が黙ってスキップ | Critical | service/admin のプラグイン app.php に `'enable' => true` を追加 |
| 6 | config/bootstrap.php が存在しない support\bootstrap\Db/Redis を参照 | Critical | 削除；Eloquent 初期化は support/bootstrap.php が vendor/webman/database の Db.php を require する方式に変更 |
| 7 | グローバル redis() 関数が存在しない（webman 2.x にこの関数はない）、限流/風控が黙って無効 | High | support\Redis ファサードを新設（illuminate/redis + phpredis）、app/functions.php に redis() ヘルパー関数を登録 |
| 8 | RedisManager のコンストラクタ引数が不足（3引数が必要：appコンテナ/driver/config） | High | stdClass コンテナのプレースホルダー + phpredis ドライバー + 接続設定を渡す |
| 9 | モデルが存在しない Erik\Encryptable\Encryptable trait を参照（パッケージ内は Maize\Encryptable 名前空間の CastsAttributes） | Critical | service/Erik/Encryptable/Encryptable.php の従来型 trait 互換レイヤーを新設（内部ではパッケージの Encryption::php を再利用） |
| 10 | composer プラグイン Installer.php のトップレベル関数が重複宣言で fatal | Medium | function_exists による冪等ガード（service/admin 両方の vendor を修正済み） |
| 11 | HashidsEncode の getHeader() が string を返し implode でエラー | High | (array) 強制キャスト |
| 12 | docker-compose/.env.example に実在の JWT/暗号化キーがハードコード | Critical | change_me プレースホルダーに置換、インストールウィザードがランダムキーを生成 |
| 13 | 注文作成にトランザクションなし、在庫減算が非アトミック（並行時オーバーセル） | Critical | Db::transaction + 条件付き decrement によるアトミック減算 |
| 14 | クーポン受取の並行時過剰発行/過剰受取 | High | トランザクション + 行ロック lockForUpdate + received_qty のアトミックガード |
| 15 | PayPal Webhook の署名検証フィールドが常に空（verify-webhook-signature が必ず失敗） | High | 5 つの検証フィールドをリクエスト header から透過 |
| 16 | インストールウィザードの SQL インジェクション（DB名/パスワードの連結） | High | quote + バッククォートエスケープ + var_export で設定を書き込み |
| 17 | 暗号化/ハッシュキー欠落時に黙ってフォールバック | High | Encryption/HashidsHelper で空値や長さ不正時に例外をスロー |
| 18 | 注文エクスポートの固定ファイル名が並行時に上書き | Medium | uniqid ファイル名 + shutdown でのクリーンアップ + try/catch |
| 19 | Hashids デコードがリクエストパラメータ（ルートパラメータ/GET/POST）に書き戻されない | High | setParams/setGet/setPost で書き戻し |
| 20 | composer.lock が gitignore（ビルドが再現不可） | Medium | ignore を解除してバージョン管理に含める |
| 21 | コンテナにヘルスチェックなし、起動依存なし | Medium | 全サービスの healthcheck + depends_on condition |
| 22 | admin の Dockerfile が実行不可 | High | COPY + composer install + EXPOSE + CMD を追加 |
| 23 | Flutter コンパイルエラー（intl 衝突/コンストラクタジェネリクス/余分な括弧）+ テストの pending Timer | High | intl ^0.20.2、静的ファクトリ、pump によるクロック進め |
| 24 | HarmonyOS の ArkTS コンパイルエラー 27 個で出包不可 | High | 明示的インターフェース、予約語の改名、単一ルート build、@kit インポート、hvigor 設定 |

---

## 機能比較

> 注：◐ = テーブル構造は作成済み、業務は実装待ち（現在はデータテーブルとモデルのみで、API/業務コードがないか一部のみ実装）

### ユーザーシステム

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| メール登録/ログイン (JWT) | ✅ | ✅ | ✅ |
| ソーシャルログイン (Google/Apple/Facebook) | — | ✅ | ✅ |
| 住所管理 | ✅ | ✅ | ✅ |
| 会員レベル + ポイント | — | — | ◐ |
| ギフトカード | — | — | ✅ |
| KYC実名認証 | — | — | ✅ |

### 商品システム

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| カテゴリ管理 (ツリー型) | ✅ | ✅ | ✅ |
| SKU + 属性 | ✅ | ✅ | ✅ |
| 商品画像 | ✅ | ✅ | ✅ |
| 多言語コンテンツ | — | ✅ | ✅ |
| 多通貨の独立価格設定 | — | ✅ | ✅ |
| 商品レビュー | ✅ | ✅ | ✅ |
| コンプライアンスラベル (FDA/CE/RoHS) | — | ✅ | ✅ |
| ES多言語検索 | — | ✅ | ✅ |
| 商品Feed同期 (Google/Meta) | — | — | ✅ |
| サイズ換算表 | — | — | ✅ |

### 取引システム

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| カート | ✅ | ✅ | ✅ |
| 注文管理 | ✅ | ✅ | ✅ |
| 決済 (Stripe) | ✅ | ✅ | ✅ |
| 決済 (PayPal) | ✅ | ✅ | ✅ |
| 決済 (Klarna/Adyen) | — | プレースホルダー | プレースホルダー |
| BNPL後払い | — | プレースホルダー | プレースホルダー |
| 返金 | ✅ | ✅ | ✅ |
| 返品管理 | — | ✅ | ✅ |
| 商業インボイス/梱包明細 | — | ✅ | ✅ |
| 物流保険 | — | — | ◐ |

### 越境物流

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 国際物流会社管理 | — | ✅ | ✅ |
| 物流分区 + 段階料金 | — | ✅ | ✅ |
| 海外倉庫 (発送+返品) | — | ✅ | ✅ |
| HS申告 | — | 計画中 | 計画中 |
| 物流追跡 | — | ✅ | ✅ |
| 多倉庫在庫管理 | — | — | ✅ |

### 税関税務

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| HS Codeコード庫 | — | ✅ | ✅ |
| 関税ルール設定 | — | ✅ | ✅ |
| VAT/IOSS設定 | — | ✅ | ✅ |
| 各国コンプライアンス制限 | — | ✅ | ✅ |
| 価格表示コンプライアンス (税込/税別) | — | ✅ | ✅ |

### マーケティングツール

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| クーポン | ✅ | ✅ | ✅ |
| カルーセル | ✅ | ✅ | ✅ |
| タイムセール | — | ✅ | ✅ |
| グループ購入 | — | ✅ | ✅ |
| 販売代理店 (リンク+コミッション+引き出し) | — | ✅ | ✅ |
| 地域限定プロモーション | — | ✅ | ✅ |

### サプライチェーン

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| サプライヤー管理 | — | — | ✅ |
| 購買注文 | — | — | ◐ |
| 品質検査 (入庫+出庫ゲート) | — | — | ◐ |
| 在庫台帳 (不変台帳) | — | — | ✅ |
| 在庫調撥 | — | — | ◐ |

### プラットフォーム拡張

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 多店舗管理 | — | — | ✅ |
| 多セラー出店 (第三者セラー) | — | — | ✅ |
| Amazon/eBay/Shopee刊登 | — | — | ✅ |
| 多プラットフォーム注文集約 | — | — | ✅ |
| B2B卸売 (段階価格/見積もり照会) | — | — | ✅ |

### 風控コンプライアンス

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 基本攻撃検知 (XSS/SQLi) | ✅ | ✅ | ✅ |
| 拡張攻撃検知 (XXE/SSRFなど) | — | — | ✅ |
| PosterVerify人機認証 | — | ✅ | ✅ |
| 風控ルールエンジン | — | — | ✅ |
| GDPR/CCPAデータリクエスト | — | — | ✅ |
| Cookie Consent管理 | — | — | ✅ |
| プラットフォーム出所追跡 | — | ✅ | ✅ |
| プラットフォーム出所追跡 (8プラットフォーム) | — | ✅ | ✅ |

### 高並行処理

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| DBコネクションプール | ✅ | ✅ | ✅ |
| トークンバケット限流 | — | — | ✅ |
| DB読み書き分離 | — | — | ✅ |
| Cron 定時タスク (11個) | — | — | ✅ |

### コンテンツと成長

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| システム通知 | ✅ | ✅ | ✅ |
| メールテンプレート | — | — | ✅ |
| CMS多言語ページ | — | — | ✅ |
| FAQ + 知識庫 | — | — | ◐ |
| サブスクリプション周期購 | — | — | ✅ |
| ABテスト | — | — | ◐ |
| リアルタイムカスタマーサポート (WebSocket IM) | — | — | ✅ |

### クライアント

| 機能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| 国際化 (5言語翻訳) | ✅ | ✅ | ✅ |
| APIドキュメント (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Admin EChartsダッシュボード | ✅ | ✅ | ✅ |
| Admin Excel/PDFエクスポート | ✅ | ✅ | ✅ |
| 多言語インターフェース (5言語) | ✅ | ✅ | ✅ |

---

## 設計比較

### データベース

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| データテーブル | **23** | **62** | **110** |
| ユーザー関連 | 3 | 5 | 7 |
| 商品関連 | 6 | 15 | 19 |
| 取引関連 | 6 | 9 | 9 |
| 物流関連 | 0 | 7 | 9 |
| 税関関連 | 0 | 5 | 5 |
| マーケティング関連 | 4 | 8 | 8 |
| サプライチェーン | 0 | 0 | 5 |
| 風控コンプライアンス | 0 | 0 | 5 |
| 多プラットフォーム | 0 | 0 | 9 |
| コンテンツ成長 | 0 | 1 | 14 |
| カスタマーサポート/AB/API | 0 | 0 | 5 |

### ミドルウェアパイプライン

```
Lite:      Cors → Security(4類) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security(4類) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security(31類) → RateLimit(令牌桶) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption(接口加密)
```

### コード規模

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Service モデル | 26 | 55 | 111 |
| Service コントローラー | 15 | 24 | 39 |
| Service ミドルウェア | 7 | 9+2 | 12+2 |
| Service ユーティリティクラス | 5 | 5 | 15 |
| Admin モデル | 15 | 34 | 76 |
| Admin コントローラー | 15 | 27 | 82 |
| Flutter ページ | 11 | 11 | 11 |
| HarmonyOS | — | — | 9ページ |
| PHPUnitテスト | 22 | 22 | 54 |

### 技術スタック

| コンポーネント | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| snowflake-php | ✅ | ✅ | ✅ |
| hashids | ✅ | ✅ | ✅ |
| jwt-webman | ✅ | ✅ | ✅ |
| encryption | ✅ | ✅ | ✅ |
| encryptable | ✅ | ✅ | ✅ |
| season | — | ✅ | ✅ |
| poster-php | — | ✅ | ✅ |
| webman-scout | — | ✅ | ✅ |
| phpspreadsheet | ✅ | ✅ | ✅ |
| dompdf | ✅ | ✅ | ✅ |
| stripe/stripe-php | — | ✅ | ✅ |
| maxmind/GeoIP2 | — | ✅ | ✅ |
| guzzlehttp/guzzle | — | ✅ | ✅ |

---

## アップグレードパス

```
Lite (オープンソース) ──→ Standard (商用) ──→ Full (商用)

アップグレード方法:
  1. erik@erik.xyz に連絡して該当バージョンのコードを入手
  2. 差分 schema をインポート (lite→standard は約40テーブル追加, standard→Full は約48テーブル追加)
  3. 該当バージョンのコントローラー/モデル/ミドルウェアをコピー
  4. composer require で依存パッケージを追加
```

---

## 入手方法

| バージョン | 方法 |
|------|------|
| **簡易版 (Lite)** | GitHub オープンソース [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php) `lite` ブランチ |
| **標準版 (Standard)** | 商用ライセンス — **erik@erik.xyz** に連絡 |
| **完全版 (Full)** | 商用ライセンス — **erik@erik.xyz** に連絡 |

商用ライセンスに含まれるもの: 完全なソースコード / デプロイサポート / 優先アップデート / 技術コンサルティング
