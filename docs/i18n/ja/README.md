> この文書は元の中国語ドキュメントの機械翻訳です。原文: [中文原版](../../../README.md)。

# Erik Shop — 越境ECプラットフォーム 完全版(Full)

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## バージョン

> 簡易版 (MITオープンソース): `lite` | 標準版 (商用): `standard` | 完全版 (商用): `full`
>
> 商用ライセンスのお問い合わせ: **erik@erik.xyz** | バージョン比較: [VERSIONS.md](VERSIONS.md)

## 言語 / Languages

| 言語 | リンク |
|------|------|
| 中国語 | [README.md](README.md) |
| 英語 | [docs/i18n/en/README.md](../en/README.md) |
| 韓国語 | [docs/i18n/ko/README.md](../ko/README.md) |
| ロシア語 | [docs/i18n/ru/README.md](../ru/README.md) |
| ドイツ語 | [docs/i18n/de/README.md](../de/README.md) |
| フランス語 | [docs/i18n/fr/README.md](../fr/README.md) |
| スペイン語 | [docs/i18n/es/README.md](../es/README.md) |
| ポルトガル語 | [docs/i18n/pt/README.md](../pt/README.md) |
| ヒンディー語 | [docs/i18n/hi/README.md](../hi/README.md) |
| アラビア語 | [docs/i18n/ar/README.md](../ar/README.md) |
| ベンガル語 | [docs/i18n/bn/README.md](../bn/README.md) |
| インドネシア語 | [docs/i18n/id/README.md](../id/README.md) |
| 日本語 | [docs/i18n/ja/README.md](../ja/README.md) |

## プロジェクト概要

webman ファミリーパッケージで構築したフルスタック越境ECプラットフォーム。B2C/B2B のシーンと第三者セラーの出店に対応します。

### 技術アーキテクチャ

| 層 | 技術 | ディレクトリ |
|------|------|------|
| 業務 API | webman + illuminate/database + erikwang2013/* | `service/` |
| 管理バックエンド | webman-admin + LayUI + ECharts | `admin/` |
| クライアント | Flutter (iOS/Android/macOS/Windows/Linux) | `apps/flutter/` |
| 鴻蒙クライアント | ArkTS + ArkUI (HarmonyOS NEXT) | `apps/harmonyos/` |

### 技術スタック

**サーバーサイド：** PHP 8.3+, webman 2.1, MySQL 8.0, Redis 7, Elasticsearch 8
**コアパッケージ：** snowflake-php, hashids, jwt-webman, encryption, encryptable, poster-php, webman-scout, season
**決済：** Stripe, PayPal（完全実装）；Klarna, Adyen（プレースホルダー、`PaymentGateway::make` 未実装、docs/PLAN.md 参照）
**クライアント：** Flutter 3.x (Riverpod + GoRouter + Dio), HarmonyOS API 12+ (ArkTS + ArkUI)

## アーキテクチャ図集

> 全図集と拡大図の閲覧はこちら：[diagrams.md](diagrams.md)

### システムアーキテクチャ図

![システムアーキテクチャ図](diagrams/01-system-architecture.svg)

### リクエスト処理フロー図

![リクエスト処理フロー図](diagrams/02-request-processing-flow.svg)

### 機能モジュール全景図

![機能モジュール全景図](diagrams/03-feature-module-map.svg)

### リクエストライフサイクル図

![リクエストライフサイクル図](diagrams/04-request-lifecycle.svg)

> 詳細は [完全アーキテクチャ図集](diagrams.md)（注文ライフサイクル・デプロイアーキテクチャ・セキュリティアーキテクチャ・多通貨決済など全 8 図）

### セキュリティアーキテクチャ図

![セキュリティアーキテクチャ図](diagrams/07-security-architecture.svg)

### 多通貨決済フロー図

![多通貨決済フロー図](diagrams/08-multi-currency-settlement.svg)

### 多通貨決済の説明

**多通貨価格設定**：商品 SKU は `currency_code` ごとに通貨別で価格設定し、注文時に受取通貨（USD / EUR / GBP / CNY など）を確定します。

**為替レートサービス**：`erik_exchange_rates` レートテーブルは manual での手動メンテナンスと exchangerate-api の自動取得に対応し、`effective_at` の有効日時でバージョン管理、決済時は支払い時点のレートスナップショットを使用します。

**元通貨での引き落とし**：Stripe / PayPal は注文通貨で元通貨引き落とし（Klarna/Adyen はプレースホルダーで未接続）、Webhook の署名検証で入金を確認後、支払いと注文のステータスを更新します。

**按分決済**：支払い成功後に `PlatformSettlements` プラットフォーム按分を自動生成（注文総額 + プラットフォーム手数料 + 決済ゲートウェイ手数料、注文通貨で記帳）；セラー決済 `MerchantSettlements`（注文金額 → 抽成率 → 決済金額）、サプライヤー決済 `SupplierSettlements`、販売代理店コミッション引き出し `AffiliatePayouts` の 4 系統を独立して決済し、ステータスは 0 未決済 / 1 決済済み。

**為替差損益**：`CurrencyExchangeGainsLosses` で受取通貨と決済通貨の差を追跡し、支払い時レートと決済時レートを比較、正の値 = 為替益、負の値 = 為替損となり、越境ECの多通貨照合と監査を支えます。

## クイックスタート

### 方法1：Webワンクリックインストール（推奨）

```bash
# 1. admin の依存関係をインストール
cd admin && composer install

# 2. 管理バックエンドを起動
php start.php start -d

# 3. ブラウザでインストールウィザードを開く
# http://127.0.0.1:8788/app/admin/install/step1
# データベース情報を入力 → 管理者アカウントを設定 → 完了

# 4. 依存関係をインストールして API を起動
cd ../service && composer install && php start.php start -d
```

> インストールウィザードが自動で完了：DB作成 → 117 テーブルをインポート → service/.env と admin/.env を生成（ランダムキーを含む） → 管理者を作成 → サービスをリロード

### 方法2：コマンドライン手動インストール

詳細は [INSTALL.md](../../INSTALL.md) を参照

### Docker デプロイ

```bash
# 環境変数を設定
cp .env.example .env  # または DB_PASS / JWT_SECRET などの変数を設定

# 全サービスをワンコマンドで起動
docker-compose up -d
# nginx:80 → service:8787 + admin:8788
# MySQL:3306, Redis:6379, ES:9200
```

詳細は [デプロイドキュメント](deployment.md) を参照

## プロジェクト構成

```
shop-php/
  install.sql       # ワンクリックインストール SQL（117 テーブル）、Web インストールウィザードが自動インポート
  service/          PHP業務API (webman)        — 39コントローラー + 111モデル + 14ミドルウェア
  admin/            管理バックエンド (webman-admin)      — 82コントローラー + 76モデル + EChartsダッシュボード + Webインストールウィザード
  apps/flutter/     Flutterクライアント              — 11ページ + 5言語 + PCレスポンシブ
  apps/harmonyos/   鴻蒙クライアント                  — 9ページ + ArkTS
  docker/           Dockerデプロイ                  — Nginx + PHP + MySQL + Redis + ES
  docs/             設計ドキュメント
```

## 機能カバレッジ

| 分類 | カバー内容 |
|------|---------|
| **B2C小売** | 多言語商品、通貨別価格設定、SKU、カート、注文、決済、返金、返品 |
| **B2B卸売** | 段階価格(MOQ)、法人認証(税番号/営業許可証)、見積依頼 |
| **マルチセラー出店** | セラー審査、商品審査、按分決済 |
| **越境コンプライアンス** | HS Code コード庫、関税ルール、VAT/IOSS、各国コンプライアンスラベル(FDA/CE/RoHS) |
| **国際物流** | 物流ゾーン別送料、海外倉庫(発送倉+返品倉)、商業インボイス/梱包明細、HS申告（計画中） |
| **決済** | Stripe/PayPal（完全実装）、Klarna/Adyen（プレースホルダー）、BNPL後払い（プレースホルダー）、3DS認証 |
| **マーケティング** | クーポン(ゾーン別+新旧顧客)、カルーセル(地域別表示)、タイムセール、グループ購入、販売代理店(リンク+コミッション+引き出し) |
| **マルチプラットフォーム** | Amazon/eBay/Shopee/Lazada/Temu 商品出品+注文集約 |
| **サプライチェーン** | サプライヤー評価、購買→品質検査→入庫、在庫台帳(不変台帳)、在庫移動 |
| **リスクコンプライアンス** | ルールエンジン(バイパス採点)、KYC実名、GDPR/CCPAデータリクエスト、Cookie Consent |
| **セキュリティ防護** | 31種の攻撃検知(XSS/SQLインジェクション/XXE/SSRF/CRLF/パストラバーサル/ファイルアップロード/ブルートフォース/HTTPメソッド/Host/CORSなど) |
| **高並行処理** | トークンバケットレート制限、サーキットブレーカー(決済/ソーシャルログイン)、DB読み書き分離、コネクションプール最適化 |
| **CDN** | マルチプロバイダーOrigin-Pull (Cloudflare/CloudFront/Aliyun/Tencent)、`Cdn::url()` が `https://{CDN_DOMAIN}{path}` へ書き換え、admin CDN管理ページ(Config/Purge/Logs)、自動パージfail-open、エッジキャッシュ7日immutable |
| **会員成長** | ポイントルール、会員レベル特典、ギフトカード、値下げ通知、定期購入、ABテスト |
| **コンテンツ管理** | CMS多言語ページ、FAQ、ナレッジベース、サイズ換算表、メールテンプレート、商品Feed同期 |
| **カスタマーサポート** | WebSocketリアルタイムIM、ナレッジベース(テーブル構造は作成済み) |
| **インフラ** | Snowflake分散ID、Hashidsインターフェース難読化、JWT認証、AES暗号化、GeoIP地域識別 |
| **マルチエンド対応** | Flutter(iOS/Android/macOS/Windows/Linux/iPadOS)+HarmonyOS(ArkTS)+Web Admin |
| **プラットフォーム追跡** | 8プラットフォームの出所識別(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web)+DB記録 |
| **テスト** | 22 tests / 45 assertions — ALL PASS (Security+Jwt+ApiResponse+Redis) |

## コア設計

- **Snowflake主キー**：117テーブルすべてで `erikwang2013/snowflake-php` 生成の bigint ID を使用
- **Hashidsインターフェース**：ミドルウェアが自動でエンコード/デコード、コントローラーは透過
- **Encryptable暗号化**：email/mobile/address などの機密フィールドをデータベースレベルで暗号化
- **JWT認証**：HS256 + access/refresh デュアルトークン自動リフレッシュ
- **APIバージョン**：`API-Version` header でルーティング、URLには含めない
- **Poster認証**：機密操作(登録/注文/決済)にランダム人機認証

## ドキュメント

| ドキュメント | 説明 |
|------|------|
| [README-EN.md](../../README-EN.md) | English documentation |
| [INSTALL.md](../../INSTALL.md) | インストールガイド（Web ワンクリックインストール + 手動インストール） |
| [AUDIT-REPORT.md](../../AUDIT-REPORT.md) | インストールシステム審査レポート |
| [プロジェクト計画](PLAN.md) | チームが作成した段階別プロジェクト計画（4 段階ロードマップ + 重要リスク + Quick Wins） |
| [チーム調査詳細](PLAN-RESEARCH.md) | 7 領域の現状調査：実装済み / ギャップ / リスク / 提案 |
| [機能設計ドキュメント](features.md) | 完全な機能マトリックス、業務フロー、ステートマシン |
| [アーキテクチャ図集](diagrams.md) | アーキテクチャ図、フロー図、機能図、ライフサイクル図、デプロイ図、多通貨決済図（8枚のMermaid図） |
| [アーキテクチャ設計ドキュメント](architecture-full.md) | システムアーキテクチャ図、ミドルウェアパイプライン、データアーキテクチャ、セキュリティアーキテクチャ、決済アーキテクチャ |
| [設計ドキュメント](design.md) | データベーステーブル設計、API仕様、セキュリティ方案、国際化 |
| [アーキテクチャドキュメント](architecture.md) | ディレクトリ構造、モデル継承チェーン、主要パッケージ |
| [APIインターフェースドキュメント](api.md) | 71個のAPIエンドポイント (静的ドキュメント) |
| [hg/apidocインターフェースドキュメント](http://localhost:8787/apidoc/) | hg/apidoc自動生成 (6グループ: 認証/商品/取引/物流税関/ユーザーマーケティング/運営) |
| [デプロイドキュメント](deployment.md) | Docker/手動デプロイ、環境変数（CDN含む）、運用コマンド |


## オープンソース開発の継続にご支援をお願いします

| WeChat | Alipay |
|:---:|:---:|
| ![微信](../../weixinpay.png "微信") | ![支付宝](../../alipay.png "支付宝") |

### 海外銀行振込 (ZA Bank)

**受取人情報**

- 受取人名：WANG KEXUN
- 受取口座番号：881015918251

**受取銀行**

- SWIFT Code：AABLHKHHXXX
- 銀行名：ZA Bank Limited
- 銀行番号：387
- 銀行所在地：Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**クロスボーダー送金の代理銀行（必要な場合）**

> これはクロスボーダー送金の代理銀行（中継銀行）情報であり、受取銀行の情報ではありません。送金銀行にお問い合わせのうえ、提供が必要かどうかご確認ください。

- **香港ドル・人民元・米ドルの受取**（代理銀行 Citibank）：
  - 銀行名：Citibank N.A. Hong Kong
  - SWIFT Code：CITIHKHXXXX
  - 銀行番号：006
  - 支店名：Hong Kong Branch
  - 支店番号：391
  - 銀行所在地：Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **その他の通貨の受取**（代理銀行 BNY Mellon）：
  - 銀行名：THE BANK OF NEW YORK MELLON
  - SWIFT Code：IRVTUS3NXXX
  - 銀行所在地：THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### 仮想通貨の寄付 (Crypto Donation)

このプロジェクトがお役に立ったら、QRコードをスキャンして寄付してください。ありがとうございます！

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---


## テスト

```bash
make test             # 推奨方法
cd service && php vendor/bin/phpunit tests/   # ネイティブコマンド
# 22 tests, 45 assertions — ALL PASS

# 依存関係セキュリティ監査（既知の低危険度 CVE が 1 件：CVE-2025-45769 firebase/php-jwt <7.0.0、
# jwt-webman ^6.0 の制約でアップグレード不可、HS256 対称署名の使い方には影響なし）
composer audit
```

## 開発ツール

```bash
make help             # すべてのコマンドを表示
make lint             # PHP 構文チェック
make check            # phpstan 静的解析
make fix              # php-cs-fixer コードフォーマット
```

CI/CD: `.github/workflows/ci.yml` — PHP 8.3/8.4 マトリックステスト

## License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
