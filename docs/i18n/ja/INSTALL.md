# 越境ECプラットフォーム — インストールガイド

> Cross-border E-Commerce Platform Installation Guide
>
> [中国語 README](../../../README.md) | [English README](../../README-EN.md) | [審査レポート](../../AUDIT-REPORT.md)

---

## 環境要件 / Requirements

| コンポーネント | 最低バージョン | 推奨バージョン |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (オプション/optional) |

### PHP 拡張

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## インストール方法 / Installation Methods

### 方法1（推奨）：Web ワンクリックインストールウィザード

ブラウザでインストールページにアクセスし、データベース情報と管理者アカウントを入力すると、**テーブル作成・設定・管理者作成を全自動**で行います。

```bash
# 1. 依存関係をインストール
cd admin/
composer install

# 2. 管理バックエンドを起動
php start.php start

# 3. ブラウザでアクセス（初回は自動でインストールページへ遷移）
# http://127.0.0.1:8788/app/admin/install/step1
```

インストールウィザードは**自動で**以下を実行します：
- MySQL データベースを作成（存在しない場合）
- `install.sql` の全 117 テーブルをインポート（7 枚の `wa_` + 110 枚の `erik_`）
- 管理バックエンドのメニューをインポート
- `plugin/admin/config/database.php` と `thinkorm.php` を生成
- `service/.env` を生成（ランダム生成の JWT/Hashids/暗号化キーを含む）
- スーパー管理者アカウントを作成
- SIGUSR1 シグナルを送信してサービスをリロード

> インストール完了後、service/ API サービスも起動する必要があります（下記のステップ 5 を参照）。

---

### 方法2：手動インストール / Manual Installation

<details>
<summary>コマンドラインでのデプロイや既存データベース環境向け</summary>

### 1. データベースを作成

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. データベースをインポート

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` には **117 テーブル**とデフォルトのシードデータが含まれています。

### 3. service/.env を設定

```bash
cd service/
cp .env.example .env
# .env を編集して実際のデータベース/Redis/JWT などのパラメータを設定
```

**主要な設定項目：**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<ランダム32バイトキー>
HASHIDS_SALT=<ランダムソルト>
ENCRYPTION_KEY=<ランダム32バイトキー>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. admin/ を設定

```bash
cd admin/
cp .env.example .env
# .env を編集し、service と同じデータベース情報を入力
```

### 5. 管理者アカウントを作成

```sql
-- パスワードは bcrypt で生成する必要があります
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', 'スーパー管理者', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### 方法3：Docker デプロイ / Docker Deployment

```bash
# 1. 環境変数を設定
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. 全サービスを起動
docker-compose up -d

# 3. Web インストールウィザードを実行
# http://localhost/app/admin/install/step1
```

Docker サービス：Nginx(:80) → service(:8787) + admin(:8788)，MySQL(:3306)，Redis(:6379)，ES(:9200)

---

### サービスを起動 / Start Services

```bash
# 依存関係をインストール（両プロジェクトで必要）
cd service/ && composer install
cd admin/ && composer install

# API サービスを起動
cd service/
php start.php start -d

# 管理バックエンドを起動
cd admin/
php start.php start -d
```

| サービス | デフォルトポート | 確認方法 |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| 管理バックエンド | 8788 | ブラウザで `http://127.0.0.1:8788/app/admin` にアクセス |

### シードデータのインポート（オプション）/ Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # 国/地域
php start.php seed:currencies    # 通貨
php start.php seed:hs_codes      # HS Code コード
php start.php seed:compliance    # コンプライアンス分類
```

---

## ディレクトリ構造 / Directory Structure

```
shop-php/
├── install.sql              # 統合後の完全なインストール SQL
├── admin/                   # 管理バックエンド (webman-admin + LayUI)
│   ├── config/database.php  # データベース設定
│   ├── plugin/admin/        # webman-admin プラグイン
│   └── start.php
├── service/                 # API サービス (webman RESTful)
│   ├── config/              # 設定ファイル
│   ├── database/schema.sql  # 元の業務テーブル SQL (install.sql に置き換え済み)
│   ├── database/seeders/    # シードデータ
│   └── start.php
```

---

## データベース構造の概要 / Database Schema Overview

| モジュール | テーブルプレフィックス | テーブル数 | 説明 |
|------|--------|--------|------|
| 管理バックエンドシステム | `wa_` | 7 | 管理者/ロール/権限/設定/添付ファイル |
| ユーザーとアカウント | `erik_users_*` | 7 | ユーザー/住所/ソーシャル/KYC/お気に入り/会員 |
| 商品とカテゴリ | `erik_product_*` | 16 | 商品/SKU/多言語/多通貨/レビュー/コンプライアンス/HS |
| カートと注文 | `erik_order_*` | 9 | カート/注文/決済/返金/返品/通関 |
| 国/通貨/物流 | `erik_shipping_*` | 11 | 国/通貨/為替レート/物流/ゾーン/倉庫/在庫 |
| 税関と税務 | `erik_hs_*` | 5 | HSコード/関税/VAT/コンプライアンス制限 |
| 決済と資金 | `erik_payment_*` | 6 | 決済ゲートウェイ/プラットフォーム按分/サプライヤー決済/為替差損益 |
| マーケティング | `erik_coupon_*` | 9 | クーポン/タイムセール/グループ購入/販売代理店 |
| サプライチェーン | `erik_supplier_*` | 7 | サプライヤー/購買/品質検査 |
| リスクとコンプライアンス | `erik_risk_*` | 6 | リスクルール/GDPR/Cookie/プライバシー |
| マルチプラットフォーム | `erik_platform_*` | 8 | 多店舗/プラットフォームアカウント/出品/セラー |
| コンテンツと体験 | `erik_*` | 12 | CMS/Feed/サイズ/通知/メール/検索/操作ログ |
| サブスクリプション/ポイントなど | `erik_*` | 7 | サブスクリプション/ポイント/ギフトカード/B2B |
| ABテスト/API/設定 | `erik_*` | 7 | ABテスト/レート制限/APIドキュメント/システム設定 |

---

## よくある質問 / Troubleshooting

### MySQL エラー "Specified key was too long"

```sql
-- utf8mb4 + InnoDB を使用し、innodb_large_prefix を有効にしてください
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### ポート競合 / Port Conflict

`admin/.env` または `service/.env` の `APP_PORT` を変更します。

### Redis 接続失敗

Redis 拡張がインストールされ、Redis サービスが起動していることを確認します：
```bash
redis-cli ping  # 返ってくるべき値は PONG
```

### Snowflake ID 衝突

複数サーバーで同時にインスタンス化する場合、各サーバーの `SNOWFLAKE_WORKER_ID` が異なることを確認してください（0-31）。

---

## 開発コマンド早見表 / Development Commands

```bash
# service/ (API)
php start.php start          # 起動
php start.php start -d       # デーモン化
php start.php reload         # ホットリロード
php start.php stop           # 停止
php start.php status         # 状態確認

# admin/ (管理バックエンド)
php start.php start
php start.php start -d
php start.php reload
```
