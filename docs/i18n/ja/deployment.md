# 越境ECプラットフォーム — デプロイドキュメント

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Docker デプロイ (推奨)

### 1.1 環境要件

- Docker 24.0+ / Docker Compose v2
- ホストOS: Linux (推奨 Ubuntu 22.04+)
- メモリ: 最低 4GB、推奨 8GB+

### 1.2 デプロイ手順

```bash
# 1. プロジェクトをクローン
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. 環境変数を設定
cp .env.example .env
# .env を編集してすべてのパスワードとキーを変更:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET など

# 3. 全サービスを起動
docker compose up -d

# 4. ログを確認
docker compose logs -f service
docker compose logs -f admin

# 5. アクセス
# API: http://localhost/api
# 管理バックエンド: http://admin.localhost
```

### 1.3 サービス一覧

| サービス | ポート | 説明 |
|------|------|------|
| nginx | 80, 443 | リバースプロキシ |
| service | 8787 (内部) | PHP業務API |
| admin | 8788 (内部) | 管理バックエンド |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 本番環境チェックリスト

- [ ] `.env` のすべてのキーをランダム値に変更済み
- [ ] `STRIPE_MODE=live` (本番環境)
- [ ] `APP_ENV=production`
- [ ] `config/app.php` の `debug` を `false` に設定
- [ ] SSL証明書の設定 (nginx+Let's Encrypt)
- [ ] ルートディレクトリの `install.sql` をデータベースにインポート済み（117 テーブル、Web インストールウィザードが自動インポート）
- [ ] ESインデックス作成済み: `php start.php scout:import "app\model\Products"`
- [ ] MySQL/Redis/ES データボリュームのバックアップを設定済み

## 2. 手動デプロイ

### 2.1 環境依存

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (任意、検索機能に必要)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# .env を編集
composer install --no-dev --optimize-autoloader
php start.php start -d
# 監視: http://0.0.0.0:8787
```

### 2.3 Admin 管理バックエンド

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# 監視: http://0.0.0.0:8787 (別ポートは Nginx リバースプロキシで振り分け)
```

### 2.4 Nginx リバースプロキシ

```nginx
# docker/nginx/conf.d/shop.conf を参照
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
```

## 3. データベース初期化

```bash
# データベースを作成
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# テーブル構造をインポート
mysql -u root -p erik_shop < install.sql

# シードデータをインポート（任意）
php -r "
require 'vendor/autoload.php';
// 国/通貨/HS Code/物流ゾーンなどのシードデータをインポート
"
```

## 4. 環境変数リファレンス

| 変数 | デフォルト値 | 説明 |
|------|--------|------|
| APP_ENV | production | アプリケーション環境 |
| DB_HOST | 127.0.0.1 | データベースアドレス |
| DB_PORT | 3306 | データベースポート |
| DB_NAME | erik_shop | データベース名 |
| DB_USER | erik | データベースユーザー |
| DB_PASS | (必須) | データベースパスワード |
| REDIS_HOST | 127.0.0.1 | Redisアドレス |
| JWT_SECRET | (必須) | JWT署名キー(256bit) |
| HASHIDS_SALT | (必須) | Hashidsソルト値 |
| ENCRYPTION_KEY | (必須) | AES暗号化キー |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Stripeキー |
| STRIPE_WEBHOOK_SECRET | - | Stripe Webhook署名検証 |

## 5. 運用コマンド

```bash
# Service API
cd service
php start.php status        # 状態確認
php start.php reload        # 平滑再起動
php start.php stop          # 停止

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # コンテナ状態確認
docker compose logs -f      # ログ確認
docker compose restart      # 全サービス再起動
docker compose down         # 停止
```
