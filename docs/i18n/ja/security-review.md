# Security Plugin 統合審査レポート

**日付**: 2026-08-04
**対象範囲**: erikwang2013/security-php v1.1.6 統合
**レビュアー**: Claude Code (自動)

---

## 1. テスト結果

| チェック | 結果 |
|---|---|
| PHP 構文チェック（47 ファイル） | すべて通過 |
| PHPUnit（22 tests, 45 assertions） | すべて通過 |
| SecurityGuard セキュリティペイロードテスト | XSS + SQLi を正しくブロック |
| SecurityGuard セキュアリクエストテスト | 誤検知なし |
| phpstan 静的解析 | 未インストール（非ブロッキング） |

## 2. 修正済みの問題

### 2.1 ファイルアップロードデータが SecurityGuard に渡されていない（Critical）

**ファイル**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

ミドルウェアは `$request->all()` のみを `SecurityGuard::guard()` に渡していたが、このメソッドにはファイルアップロードデータが含まれません。`UploadDetector` は `['tmp_name' => ..., 'name' => ...]` 形式のファイルデータを必要とします。

**修正**: `$request->file()` をデータ配列にマージするループを追加し、`SecurityGuard::guard()` に渡すようにしました。

### 2.2 Admin の encryptable 設定にデフォルト値がない（Medium）

**ファイル**: `admin/config/plugin/erikwang2013/encryptable/app.php`

admin の設定は `env('ENCRYPTION_KEY')` をフォールバックなしで使用しており、環境変数が欠落すると `null` を返します。Service は `getenv('ENCRYPTION_KEY') ?: ''` を使用して空文字列に正しくフォールバックしています。

**修正**: admin の設定も `?: ''` 演算子を使用し、service の挙動と統一しました。

### 2.3 Docker Compose の環境変数が不完全（Medium）

**ファイル**: `docker-compose.yml`

- service コンテナに `ENCRYPTION_CIPHER` と `ENCRYPTION_PREVIOUS_KEYS` が欠落
- admin コンテナに `ENCRYPTION_KEY`、`ENCRYPTION_CIPHER`、`ENCRYPTION_PREVIOUS_KEYS`、`HASHIDS_SALT`、`SNOWFLAKE_WORKER_ID`、`SNOWFLAKE_DATACENTER_ID` が欠落

**修正**: `.env.example` と同じデフォルト値で、欠落していた環境変数をすべて追加しました。

### 2.4 WAF ミドルウェアの重複検知（Critical、第一ラウンドで修正済み）

カスタム `SecurityMiddleware` には約 200 行のインライン正規表現が含まれており、`security-php` パッケージの 31 個の検出器と完全に重複していました。リクエストごとに 2 回スキャンされ、CPU を浪費し、二重ブロックの可能性もありました。

**修正**: ミドルウェアを `SecurityGuard::guard()` API を使用するように書き換え、341 行から約 110 行（service）、136 行から約 85 行（admin）に削減。ブルートフォース防護とレスポンスセキュリティヘッダーは維持。

### 2.5 ENCRYPTION_KEY の欠落（Critical、第一ラウンドで修正済み）

`.env.example` ファイルの `ENCRYPTION_KEY` はプレースホルダーで、`ENCRYPTION_CIPHER` と `ENCRYPTION_PREVIOUS_KEYS` が欠落していました。実際の `.env` ファイルもありませんでした。

**修正**: 32 バイトの base64 キーを生成し、`ENCRYPTION_CIPHER=AES-256-CBC` と `ENCRYPTION_PREVIOUS_KEYS` を追加、`.env` ファイルを作成しました。

## 3. エコシステム設定の完全性

### 3.1 パッケージ（両プロジェクトで一致）

| Package | バージョン | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | インストール済み | インストール済み |
| erikwang2013/encryptable | - | インストール済み | インストール済み |
| erikwang2013/encryption | - | インストール済み | インストール済み |
| erikwang2013/jwt-webman | - | インストール済み | インストール済み |
| erikwang2013/hashids | - | インストール済み | インストール済み |
| erikwang2013/snowflake-php | - | インストール済み | インストール済み |
| erikwang2013/poster-php | - | インストール済み | インストール済み |
| erikwang2013/season | - | インストール済み | インストール済み |
| erikwang2013/webman-scout | - | インストール済み | インストール済み |

### 3.2 WAF 設定

| 項目 | Service | Admin | ステータス |
|---|---|---|---|
| 設定ファイル | `config/plugin/erikwang2013/security-php/app.php` | 同一 | 公開済み |
| 有効な検出器 | 31/31 | 31/31 | 正しい |
| IP ブラックリスト | 有効 (5回/60s -> 900s 禁止) | 同一 | 正しい |
| ブロックモード検出器 | 28 | 28 | 正しい |
| ログのみの検出器 | 3 (header_injection, ssti, nosql_injection) | 3 | 正しい |
| ストレージ | file | file | 正しい |
| ロギング | 有効 (file, 10MB ローテーション) | 同一 | 正しい |
| ミドルウェア登録 | `config/middleware.php` | `config/middleware.php` | 正しい |

### 3.3 暗号化設定

| 項目 | Service | Admin | ステータス |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | 同一 | 設定済み |
| ENCRYPTION_CIPHER | `AES-256-CBC` | 同一 | 設定済み |
| ENCRYPTION_PREVIOUS_KEYS | (empty) | (empty) | 設定済み |
| encryptable 設定 | `config/plugin/erikwang2013/encryptable/app.php` | 同一（統一済み） | 正しい |
| encryption 設定 | `config/encryption.php` | - | 正しい |
| .env ファイル | 存在 | 存在 | 作成済み |
| .env.example | 更新済み | 更新済み | 正しい |
| docker-compose | 更新済み | 更新済み | 正しい |

### 3.4 Encryptable Trait を使用するモデル

31 個のモデルが `Encryptable` trait を使用し、機密フィールドが `$encryptable` として正しく宣言されています：

| カテゴリ | モデル | 機密フィールド |
|---|---|---|
| ユーザーPII | Users | email, mobile |
| ユーザーPII | UserAddresses | name, phone, detail |
| ユーザーPII | UserKyc | real_name, id_number |
| ユーザーPII | UserSocialAccounts | access_token, refresh_token |
| プライバシー | PrivacyRequests | email |
| ファイナンス | GiftCards | receiver_email |
| ファイナンス | AffiliatePayouts | account |
| ファイナンス | PaymentGateways | name, api_key, api_secret, webhook_secret |
| プラットフォーム | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| プラットフォーム | PlatformAccounts | account_name, api_key, api_secret |
| プラットフォーム | PlatformListings | platform_account_id |
| 物流 | LogisticsCompanies | name, api_key |
| サプライヤー | Suppliers | name, email, phone |
| サプライヤー | B2bVerifications | company_name |
| マーチャント | Merchants | store_name, email, phone |
| その他 | EmailLogs | to_email |
| その他 | さらに15モデル | name フィールド |

## 4. 第二ラウンドの修正（API 暗号化 + JWT キー）

### 4.1 API レスポンス暗号化ミドルウェア（Medium、修正済み）

**ファイル**: `service/app/middleware/EncryptionMiddleware.php`（新規）

`erikwang2013/encryption` パッケージはインストール済みで `app/common/Encryption` ユーティリティクラスも存在しましたが、これまでミドルウェアパイプラインに組み込まれていませんでした。インターフェースの機密データに転送層の暗号化・復号がありませんでした。

**修正**:
- `EncryptionMiddleware` を作成し、HTTP header 駆動の暗号化・復号を実装：
  - `X-Encrypted: 1` — リクエスト復号：base64 暗号文ボディを復号して JSON としてコントローラーに渡す
  - `X-Encrypt-Response: 1` — レスポンス暗号化：レスポンスの `data` フィールドを base64 暗号文に暗号化
  - `X-Encrypt-Fields: field1,field2` — レスポンスの指定フィールドのみ暗号化
- ミドルウェアスタックの最後（HashidsEncode の後）として登録
- ヘルスチェック（`/api/health`、`/api/ping`）とドキュメントエンドポイント（`/apidoc`）は暗号化・復号をスキップ

### 4.2 クラス名/ファイル名の不一致（Medium、修正済み）

**ファイル**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

クラス `app\common\Encryption` が `EncryptionHelper.php` ファイルに宣言されており、PSR-4 仕様に一致しないため Composer のオートロードが失敗していました。IDE と CLI 環境でこのクラスが autoloader で見つからない可能性がありました。

**修正**: クラス名に一致するようファイルを `Encryption.php` にリネームしました。

### 4.3 JWT_SECRET_KEY が空（Low、修正済み）

**ファイル**: `service/.env.example`、`service/.env`、`docker-compose.yml`

`JWT_SECRET_KEY` が空文字列でした。JWT ミドルウェアには `JWT_SECRET → JWT_SECRET_KEY` のフォールバックチェーンがありますが（`JWT_SECRET` を優先）、プレースホルダー値は安全ではありません。

**修正**: 32 バイトの base64 キーを生成し、`JWT_SECRET` と `JWT_SECRET_KEY` の両方を設定しました。`.env.example`、`.env`、`docker-compose.yml` を更新。

## 5. 要観察問題（潜在的な最適化ポイント）

### 5.1 SecurityGuard の webman/Workerman の header 依存（Low Risk）

**影響**: CSRF Origin、Host Header、DNS Rebinding、Request Smuggling、CORS などの検出器は `$_SERVER` の HTTP ヘッダーデータに依存します。

Workerman の非 CGI 環境では、`$_SERVER` に HTTP ヘッダーが完全に設定されない可能性があります。SecurityGuard にはバックアップロジック（例：ヘッダー値が空なら検知をスキップ）があるため、**誤検知は発生しません**が、**一部のヘッダー攻撃を検知できない可能性があります**。Nginx リバースプロキシ層でも通常は悪意のあるヘッダーがフィルタされるため、影響は低いです。

**提案**: より完全なヘッダー検知が必要な場合は、SecurityGuard の `$meta` パラメータでヘッダー値を明示的に渡すことができます。現時点では変更不要です。

### 5.2 CSRF Origin 検出器の Admin への影響（No Risk）

Admin の `csrf_origin` 検出器は `block` モードで `allowed_origins` が空です。ただし、この検出器は Origin ヘッダーが存在し Host と一致しない場合にのみ発動するため、管理バックエンドへのアクセス時は通常 Origin ヘッダーがありません（同一オリジンアクセス）。したがって、**誤ブロックは発生しません**。

### 5.3 31 検出器すべてが有効、リクエストごとのオーバーヘッド（Performance Note）

すべてのリクエストで 31 個すべての検出器（JWT、WebSocket、GraphQL、CSV、prototype pollution などを含む）が実行されます。各検出器はリクエストのすべてのフィールドに対して正規表現マッチングを実行します。このプロジェクトの用途では、オーバーヘッドは許容範囲です（webman は常駐メモリプロセスで、CGI のコールドスタートオーバーヘッドなし）。

### 5.4 IP ブラックリストの永続化（Operational Note）

ストレージバックエンドは `file` モードで、デフォルトパスは `sys_get_temp_dir() . '/security_storage.json'` です。Docker コンテナでは、再起動後に一時ディレクトリが失われる可能性があります。マルチコンテナデプロイでブラックリストを共有する必要がある場合は、`redis` モードに切り替えられます。

## 6. 変更ファイルのまとめ

```
admin/.env.example                                (ENCRYPTION_KEY 追加)
admin/.env                                        (.env.example から新規作成)
admin/CLAUDE.md                                   (ミドルウェアスタック + tech stack 更新)
admin/composer.json                               (security-php 依存)
admin/config/plugin/erikwang2013/encryptable/app.php  (デフォルト値の統一)
admin/config/plugin/erikwang2013/security-php/app.php  (新規, 31 検出器)
admin/app/middleware/SecurityMiddleware.php       (SecurityGuard 使用に書き換え)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + JWT キー 更新)
service/.env                                      (.env.example から新規作成, JWT キー同期)
service/CLAUDE.md                                 (ミドルウェアスタック + Encryption + tech stack 更新)
service/composer.json                             (security-php 依存)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (新規, 31 検出器)
service/app/common/Encryption.php                 (EncryptionHelper.php からリネーム)
service/app/middleware/EncryptionMiddleware.php   (新規, API レスポンス暗号化・復号)
service/app/middleware/SecurityMiddleware.php     (SecurityGuard 使用 + ファイルアップロードに書き換え)
docker-compose.yml                                (encryption/jwt 環境変数の補完)
docs/security-review.md                           (本レポート)
```

## 7. 結論

**ステータス**: 通過

- WAF 検知が XSS、SQL インジェクションなどの攻撃を正しくブロック（31 検出器、SecurityGuard::guard API）
- 機密フィールドの暗号化設定が完全（31 モデル、6 カテゴリの機密データ、Encryptable trait）
- API 転送の暗号化・復号がミドルウェアに組み込み済み（EncryptionMiddleware, AES-256-CBC, header トリガー）
- JWT キーが設定済み（JWT_SECRET + JWT_SECRET_KEY の両方を設定）
- ファイルアップロード検知が修正済み（$_FILES データをマージして SecurityGuard に渡す）
- 機能回帰なし（22/22 テスト通過）
- ミドルウェアの重複検知なし
- Docker デプロイの環境変数が完全
