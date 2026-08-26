# Erik Shop チーム調査明細（7 領域）

> **生成時期**：2026-08 · **生成方法**：マルチエージェントチーム並行調査（実際のコード証拠に基づく、憶測禁止）
> **関連ドキュメント**：`docs/PLAN.md`（統合後のプロジェクト計画、レビュー調整と実装状態を含む）
> **レビュー記録**：2026-08 レビューエンジニアがコード照合で 18 項目の判断を再検証（16 項目正しい、2 項目はワークスペースで修正済みのため部分的に正しい）；本明細の PHPStan 修正提案は PHPStan 2.x の実能力に合わせて修正済み（neon 設定に代えて CLI 引数で渡す）
> **各領域の構成**：現状まとめ / 実装済み / ギャップ / リスク / 提案（提案の接頭辞 [高]/[中]/[低] は優先度）

---

## 1. サーバーサイド業務 API（service/）

### 現状まとめ
基盤アーキテクチャとセキュリティ/決済/検索/レコメンドの骨格は堅実（39 コントローラー + 111 モデル + 14 ミドルウェア + 10 定期タスク、Stripe/PayPal 実利用可、22 単体テスト通過）ですが、ドキュメントで「完全」と謳う複数の能力は実はプレースホルダーか未接続です：Klarna/Adyen ゲートウェイは設定のみ、注文がクーポン/送料/関税/風控を計算しない、読み書き分離が未有効、業務シードデータ欠落で新規インストールのコアインターフェースにデータがありません。

### 実装済み
- 決済ゲートウェイ二重実装（実コード）：PaymentGateway.php の Stripe（PaymentIntent + webhook 署名検証 + 返金）と PayPal（REST v2 OAuth2 + 注文/キャプチャ/返金 + verify-webhook-signature 五フィールド署名検証 + 返金 capture id 解析）が完全動作、PaymentController::webhook は注文ステータスのアトミックガードで重複入金を防ぎトランザクション内で PlatformSettlements 按分レコードを生成
- 決済チェーンクローズループ：PaymentController（create/status/methods/webhook）、AdminOpsController::executeRefund が実ゲートウェイ返金を実行しトランザクションで入库（返金レコード + 決済レコード + 注文ステータス + ログ）、PaymentReconcileCron が 6 時間ごとにゲートウェイ実状態で 2 時間超の待支払いレコードを対帳
- ミドルウェアスタック 14 個が順序どおり有効（config/middleware.php）：Cors→Security(security-php SecurityGuard 25+ 類検出器 + Redis ブルートフォースカウント)→RateLimit(Redis スライディングウィンドウ,6+ エンドポイントルール)→Platform(8 プラットフォーム識別)→GeoIp(MaxMind)→Locale→HashidsDecode→VersionRoute(API-Version header)→HashidsEncode→Encryption、ルート級の PosterVerify/JwtAuth/AdminKey
- 多通貨価格設定と多言語商品の実実装：ProductSkuPrices の通貨別独立価格設定 + ExchangeRates 為替レートフォールバック、ProductTranslations の locale 別 eager load（ProductController は VAT 税込/税別表示価格の計算を含む）
- 関税見積もり実利用可：TariffController が ProductHsCodes→TariffRules(dest_country+hs_code)→VatSettings で duty/vat を計算（免税額しきい値と disclaimer を含む）；ShippingController は物流分区 + 重量段階で送料を計算
- 検索とレコメンドの実実装：SearchController は webman-scout（Products モデル Searchable + ES マッピング erik_shop_products）経由、ES 例外時は MySQL LIKE にフォールバックし SearchLogs に書き込み；RecommendationCron が直近 90 日の購入共起で Top10 を計算し product_recommendations に書き込み、RecommendationController は item-based CF + 人気フォールバック
- 注文コアフロー：store のトランザクション内でアトミックに在庫を減算（where stock>=qty decrement、オーバーセル防止）、キャンセルで在庫回復、KYC/販売禁止遮断入口、ステートマシン 0-8；CouponController::claim は行ロック lockForUpdate + アトミックガードで過剰発行防止
- 10 個の定期タスクすべて config/process.php に登録（為替レート/物流軌跡/Feed/レコメンド/コンプライアンス/返品タイムアウト/価格通知/決済対帳/按分/多プラットフォーム同期）、すべてエラーログと未設定時スキップロジック付き
- ドキュメントエクスポート実利用可：ExportController の PhpSpreadsheet XLSX+CSV（HS Code 列含む）、DocumentController の商業インボイス/梱包明細（dompdf）、HealthController のヘルスチェック（db/redis 二重チェック）
- 品質ツールチェーン完備：PHPUnit 12.5（22 tests/45 assertions、Security/Jwt/ApiResponse/RedisFacade の 4 ファイル）、phpstan level 5（phpstan.neon は Eloquent 誤報の豁免含む）、php-cs-fixer、.github/workflows/ci.yml（PHP 8.3/8.4 + MySQL + Redis マトリクス）
- インフラパターン実装済み：BaseModel の Snowflake 主キー、Hashids エンコード/デコードミドルウェアの自動変換、Jwt.php の access/refresh デュアルトークン（JwtAuth は業務インターフェースでの refresh を拒否）、encryptable フィールド暗号化、config/risk.php+country.php+geoip.php の運用設定完備

### ギャップ
- Klarna/Adyen/Afterpay はプレースホルダーのみ：PaymentGateway::make() の match は stripe/paypal のみサポート（default で例外スロー）、PaymentController::methods の明示 filter は stripe/paypal のみ返却（コメントで「未実装ゲートウェイの露出回避」と自認）；しかし docs/api.md 6.1 のレスポンス例に Klarna 行が含まれ、features.md 1.0 は Klarna BNPL/Adyen を主張しており、ドキュメントとコードが不一致
- 注文がクーポン/送料/関税/風控を統合していない：OrderController::store は商品小計を足すだけで、ドキュメント化された coupon_id（api.md 5.3）を読まず、erik_orders に既存の shipping_fee/tax_amount/discount_amount/insurance_fee フィールドを計算しない；config/risk.php は存在するが app/ 内に RiskEngine 呼び出しなし（features.md 3.3 は「計算价格(分币种+优惠券)」と「风控打分(RiskEngine::score)」を主張）
- 業務シードデータ欠落：install.sql の INSERT は 2 条のみ（wa_options/wa_roles システムテーブル）、erik_hs_codes/erik_tariff_rules/erik_payment_gateway_methods/erik_shipping_zones/erik_countries はすべてデータなし；database/seeders は countries.php のみでこれを読み込むコードはどこにもない（デッドファイル、CLAUDE.md は国家/HS Code/為替レート/物流分区/コンプライアンス分類/尺码表/風控ルールのカバーを主張）——新規インストール後 countries/payment methods/送料/関税インターフェースは空を返す
- 読み書き分離設定が未有効：config/database.php は mysql_rw（2 リードレプリカ + sticky）を定義するが app/ と config/ にこの接続名を参照するコードがなく、全モデルがデフォルトの mysql 経由；features.md 5.x の「DB 读写分离(2 读副本+sticky) 完整」は名ばかり
- サブスクリプション周期購と多プラットフォーム商品刊登はテーブル構造のみ：Subscriptions/SubscriptionOrders/SubscriptionLogs、PlatformListings モデルは存在するがコントローラー/ルート/書き込みコードなし（多プラットフォームは外部 URL 依存の PlatformOrderSyncCron の引き落としのみ）；features.md は両者を「完全」と主張
- ES 多言語検索の宣言過剰：CLAUDE.md は「ES 索引包含所有语言 title/description 并按 locale 加权」を主張するが、Products::toSearchableArray() は基本の単一言語フィールドのみ索引；CLAUDE.md が主張する app/search/ ディレクトリは実際には存在しない（Searchable はモデルにインライン）
- 客服 WebSocket IM 未実装：ChatSessions/ChatMessages のテーブル構造のみ（features.md は「WS 待实现」と自認、一致しているが確かに未完成）、かつ注文ステートマシンの「審査待ち(8)/返金処理中(6)」に書き込み経路なし（審査フローなし、返金は admin executeRefund の一括で返金済み(7)に直行のみ）
- テストカバレッジが狭く文書表現とズレ：単体テストファイルは 4 つのみ（AUDIT-REPORT.md は「統合テストなし/カバレッジレポートなし」と自認）、コントローラー/注文/決済/ミドルウェアの統合テストなし；api.md 13.18 はエクスポートが CSV を返すとするが、コードは実際 XLSX デフォルト + CSV 任意；api.md 2.1 で文書化された min_price/max_price フィルタパラメータは ProductController::index で未実装

### リスク
- 決済チェーンに冪等とイベントカバレッジなし：POST /api/payment/create に冪等キーがなく、重複リクエストが複数の待支払いレコードを生成；webhook は payment_intent.succeeded / PAYMENT.CAPTURE.COMPLETED のみ処理し、refunded/failed 等のイベントは黙って無視、PaymentReconcileCron（2 時間超のみ照会）のフォールバックに依存
- 按分口径の二重ソースドリフト：webhook と SettlementCron がそれぞれ config('payment.gateway_fee.*') と config('cron.payment_gateway_fee_*') から料率を読む、二箇所が独立管理；かつ webhook が PlatformSettlements(status=0) を生成した後、SettlementCron が order_id で重複排除して再計算するため、重複/口径不一致のリスク；按分はプラットフォーム手数料 + ゲートウェイ費のみで、supplier_amount に支払いフローなし、affiliate_amount は恒に 0、多通貨按分決算（docs/08-multi-currency-settlement）は未クローズ
- 新規デプロイ即空データ + コンプライアンスのデフォルト通過：業務シードが全欠落かつ config/country.php の blocked_countries デフォルト空配列、kyc_required_countries は KR のみ、OrderController の販売禁止/KYC 遮断は手動設定依存、設定漏れで完全にオープン
- 検索依存が脆弱：ES 利用不可時は try/catch 全体で MySQL LIKE にフォールバック、scout 同期にキューなし（config/scout.php sync.queue=false）、インデックスと多言語アナライザーに CI カバレッジなし、インデックスドリフトが制御不能
- 財務精度とステートマシンのギャップ：注文金額を float で累積 round；返金は注文全体の status=7 のみで部分返金なし；Refunds ステートマシン 2(却下)/3(返金済み) は AdminOpsController の単一路径のみで、ユーザー側の返金申請・審査インターフェースなし（features.md 3.5 の返品フローの審査/ラベルは admin 端に依存）

### 提案
- [高] 業務シードデータを補完：database/seeders/countries.php を Web インストールウィザードまたは初回起動フローに接続し、HS Code 基礎庫、デフォルト決済方法（stripe/paypal 各 method 行）、VAT/関税ルールサンプル、物流分区シードを追加；そうしなければ新規デプロイのコアインターフェース（countries/payment methods/tariff/shipping）が空を返す
- [高] 注文の実計費を開通：OrderController::store にクーポン割引（coupon_id は文書化済み）、shipping_fee/tax_amount/discount_amount の入库（フィールドは既存）を接続し、features.md 3.3/api.md 5.3 と整合、api.md 2.1 の min_price/max_price フィルタを実装
- [高] 決済方法の宣言を収束：二者択一——Klarna/Adyen ゲートウェイを実装（PaymentGateway::make 拡張、Klarna 設定と gateway_fee は準備済み）するか、「プレースホルダー」と明示して api.md 6.1 のサンプルを修正し、フロントで利用不可な方法が表示されるのを防ぐ；同時に payment/create に冪等キー（order_id+gateway 重複排除）を追加
- [中] 風控エンジンを実装：RiskEngine::score を実装（config/risk.php の checks/velocity を参照）、注文/決済イベントでバイパス採点し risk_logs + order.risk_score（フィールドは既存）に書き込み、「審査待ち(8)」状態と人手審査フローを接続
- [中] 読み書き分離を有効化するかドキュメントを修正：読み取りクエリに mysql_rw 接続を明示的に切り替える（設定は準備済み）か、少なくとも features.md に「設定のみ、未有効」と明記し、設定と実装の乖離を解消
- [中] 統合テストとカバレッジしきい値を追加：注文作成（トランザクション/在庫減算/キャンセル）、決済 webhook（署名検証/冪等/按分）、Tariff/Shipping 計算、Hashids エンコード/デコードの PHPUnit 統合テストを書く（CI に MySQL/Redis サービスがあり直接再利用可能）、coverage しきい値を設定
- [中] 按分料率源を統一し按分を補完：webhook と SettlementCron を単一の料率設定に統合；サプライヤー/販売代理店決算書き込み（MerchantSettlements/SupplierSettlements/AffiliateCommissions は建表済み）と支払い/引き出しフローを補完し、docs/08-multi-currency-settlement の多通貨決算を支える
- [低] プラットフォーム化と客服 WS 拡張：PlatformOrderSyncCron に amazon/eBay/Shopee アダプターを追加し商品刊登を PlatformListings に書き込み（テーブル準備済み）；客服 IM は WebSocket サーバーサイドとメッセージ送受信を実装（ChatSessions/ChatMessages テーブル準備済み）

---

## 2. 管理バックエンド（admin/）

### 現状まとめ
管理バックエンドは webman-admin + LayUI/Pear Admin ベースで完全なインストールウィザード、RBAC 権限、WAF ミドルウェアスタック、82 コントローラー/76 モデルの骨格を持ちますが、業務層は「コントローラーありページなし」：67 個のモールコントローラーのうち 59 個がモデルにバインドされた純 CRUD スタブ、越境パネル以外に HTML ビューなし（メニュークリックで 404）、かつ ShopOrder/ShopPayment の 2 コントローラーはメソッドシグネチャ非互換で PHP 8.3 でクラスロード即致命的エラーになり、注文/決済メニューは実質利用不可です。

### 実装済み
- 82 コントローラー（15 システム + 67 shop）と 76 モデル（9 システム + 67 shop）がすべて対で存在し Copyright ヘッダー付き、名前空間は plugin\admin\app\controller|model に準拠
- 汎用 CRUD 基底クラス Crud が select/insert/update/delete と tree/select/normal フォーマットを完全実装、データ権限（dataLimit: personal/auth）、desc テーブル構造フィールドホワイトリストの inputFilter、パスワード Hash、afterQuery/insertInput/updateInput などの拡張ポイント含む
- Web インストールウィザード InstallController 実利用可：step1 建庫 + 衝突テーブル検証 + ルート install.sql（117 テーブル）のインポート + plugin/admin/config/database.php と thinkorm.php の生成 + service/.env と admin/.env の生成（ランダム JWT/Hashids/AES/ADMIN_API_KEY）+ SIGUSR1 リロード；step2 スーパー管理者を作成しロール 1 をバインド；importMenu が config/menu.php を wa_rules に再帰インポート
- 権限体系完全：AccessControl ミドルウェア + plugin\admin\api\Auth::canAccess（noNeedLogin/noNeedAuth/ロールルール一致/スーパー管理者 * ワイルドカード/401 と 403 の分流）、wa_roles/wa_rules/wa_admin_roles テーブルに依存
- ミドルウェアスタックが features.md 4.2 と一致：SecurityMiddleware（erikwang2013/security-php SecurityGuard + ログインブルートフォース 5 回/300s + セキュリティレスポンスヘッダー）、PlatformMiddleware（8 プラットフォーム UA 識別）、HashidsDecode/HashidsEncode（リクエストデコードとレスポンス *_id フィールドのエンコード）、AccessControl
- メニュー構造 config/menu.php（526 行）：6 システムグループ + データ分析/モール管理/注文管理/税関税務/物流管理/マーケティング管理/サプライチェーン管理の 7 業務グループで計 27 個のモールメニュー項目、ウェイト/アイコン/ルート含む
- 越境パネル ShopDashboardController + ECharts ビュー（Pear Admin テーマ、KPI カードと 5 つのチャートコンテナ、echarts@5.5.0 CDN 参照）
- 返金審査 ShopRefundController は少数の実業務ロジックを含むコントローラー：ステートマシン 0 審査待ち/1 通過/2 却下/3 返金済み、返金済みマーク前に service 内部インターフェース POST /api/admin/refunds/{id}/execute を呼び出し（X-Admin-Key 認証、service 端の AdminOpsController + AdminKeyMiddleware は実在）、失敗時は入库を拒否
- 注文エクスポート ShopExportController：PhpSpreadsheet で Excel 生成（注文番号/日付/ステータス/通貨/商品金額/割引/送料/実支払額）、barryvdh/laravel-dompdf で商業インボイス PDF 生成（明細、通貨、税関申告ヒント含む）
- モデルが一様に Snowflake 主キー（Base::boot creating で string 型 ID を自動生成）、業務モデルは erik_ テーブル名を宣言し service と同じ MySQL 接続（plugin.admin.mysql）を共有
- i18n 基礎ファイル存在：admin/resource/translations 配下の zh_CN/zh_HK/en/ja/ko 5 言語各 48 キー
- 品質とデプロイの付帯：composer.json に phpunit ^12.5 と php-cs-fixer dev 依存、admin/Dockerfile + docker-compose（8788 ポート）設定完全

### ギャップ
- **致命的欠陥（実測再現済み）**：ShopOrderController.php と ShopPaymentController.php が親クラス Crud メソッドをオーバーライドする際にシグネチャ非互換（: array / : Response 戻り値型欠落）、PHP 8.3 でクラスロード即 Fatal error——メニューの「注文リスト」「決済記録」にアクセスすると即クラッシュし、webman プロセスにもエラーを波及
- 67 個のモールコントローラーのうち 59 個が protected $model のみの純 CRUD スタブ、かつ ShopDashboardController 以外に index() メソッドも HTML ビューもない（view/shop/ 配下は dashboard/index.html のみ）；メニュー href /app/admin/shop/ShopProduct/index 等は存在しない action を指し、webman のデフォルトルートが完全一致後に fallback 404 に落ちる——モール管理 UI 全体（商品/カテゴリ/物流/マーケティング等）が実質利用不可で、JSON API のみ
- 越境パネルのデータチェーンが二重に破損：ビュー fetch /app/admin/shop/shop-dashboard/kpi と /chartData（kebab 書き方）に対応ルートなし（クラス名は ShopDashboardController、webman App::getController がファイル名で完全一致することを確認済み）；かつ ShopDashboardController::kpi/chartData が $this->json(['code'=>0,...]) で配列を渡し、Base::json(int $code,...) シグネチャと衝突して必ず TypeError；地域分布/通貨比率/注文ステータスの 3 図はハードコードされたサンプルデータ（コードコメントに「示例数据」と明記）、CLAUDE.md が主張する「物流時効」図は存在しない
- ドキュメント主張がコードと不一致：梱包明細 PDF、財務レポート PDF（通貨別集計）は admin に実装なし；発送管理 ShopShipmentController は純スタブ（HS 申告と軌跡ロジックなし）；注文エクスポート Excel 列（ShopExportController.php 第 44-60 行）に HS Code/関税列なし、CLAUDE.md の「HS Code/関税/通貨含む」と不一致；商品「多言語編集+通貨別価格設定」に対応 UI なし（ShopProductTranslation/ShopProductSkuPrice はスタブでメニューにもない）
- 40 個の shop コントローラーが menu.php にない（ShopMerchant/ShopPlatformAccount/ShopPlatformListing/ShopPlatformOrder/ShopRiskRule/ShopRiskLog/ShopCms/ShopGiftCard/ShopMembership/ShopPointRule/ShopSubscription/ShopB2b/ShopAbTest/ShopCountry/ShopCurrency/ShopExchangeRate/ShopEmailTemplate/ShopNotification/ShopOperationLog/ShopUserKyc/ShopSetting/ShopOrderDocument/ShopSizeChart/ShopKnowledgeBase/ShopFaq/ShopProductAttr/ShopProductCompliance/ShopProductFeed/ShopPriceAlert/ShopPrivacy/ShopInsurance/ShopInventoryTransfer/ShopApiDoc/ShopShop/ShopMerchantProduct/ShopMerchantSettlement/ShopCountryCompliance/ShopProductHsCode/ShopProductTranslation/ShopProductSkuPrice）、メニュー入口がなく裸の URL アクセスのみ可能
- テストカバレッジゼロ：admin/ に tests/ ディレクトリなし、phpunit.xml なし、phpunit ^12.5 は composer require-dev に留まる（AUDIT-REPORT.md も「Admin 端自动化测试仍为空」を認める）；php-cs-fixer は dev 依存にあるが .php-cs-fixer 設定なし、CI なし
- i18n がインターフェースに未接続：5 言語翻訳ファイルは存在するが、プラグインビューとコントローラーに trans()/__() 呼び出しなし（grep 結果なし）、index.html 上部に言語切替ボタンなし、CLAUDE.md の「LayUI 界面文本通过 trans() 函数翻译、语言切换按钮位于顶部导航栏」と不一致
- ShopPaymentController の意図した「決済記録読み取り専用」insert/update/delete 遮断ロジックはシグネチャエラーのため完全に無効；ShopOrderController の意図した「注文の直接作成/変更を許可しない」業務制約も同様に無効

### リスク
- リリース遮断級：ShopOrderController/ShopPaymentController がクラスロード即 Fatal error（PHP 8.3 実測）、注文リスト/決済記録の 2 メニューを開くと即エラー、かつ PHP 致命的エラーは webman 常駐プロセス全体をエラー再起動させる
- 「スタブコントローラー」が大量（59/67）+ メニューとドキュメントが完全機能を主張するため、開発/運用が機能がリリース済みと誤判断しやすい（メニューはあり、テーブルはあり、API は 404 か空データ）、高い誤誘導性の技術負債
- HashidsEncode がレスポンス内のすべての *_id/id 数値文字列をエンコード（40000 未満の int はエンコードしないしきい値分岐を含む）、将来新しい業務フィールドが誤って encodeFields に入るか、テーブル内に非 snowflake の数値 ID があると、前後端の ID 意味が混乱しテストのフォールバックもない
- install.sql と InstallController にハードコードされた $tables_to_install の衝突テーブル一覧（約 117 項目）が二箇所で管理され、テーブル追加時に衝突検出の修正漏れが起きやすい；install.sql にストアドプロシージャ/トリガーが含まれると splitSqlFile のセミコロン分割で壊れる可能性もある（現在の SQL にそのような内容はなく、潜在リスク）
- Crud::selectInput は 6 要素を返すのに select() は 5 つしかデストラクトしない（$page が捨てられ、ページングは Illuminate グローバルリクエストパラメータ依存）、doSelect は like 以外の文字列演算子を未処理などのエッジがあり、テストなしが重なり後続変更の回帰リスクが高い

### 提案
- [高] シグネチャ非互換を修正：ShopOrderController::insertInput/updateInput に (Request $request): array、ShopPaymentController::insert/update/delete に : Response 戻り値型を追加し、コミット前スモークスクリプト（php -l + 全 82 コントローラーのリフレクションロード）を新設して再発を防止
- [高] 越境パネルのデータチェーンを修正：ビュー fetch URL を /app/admin/shop/ShopDashboard/kpi と /chartData に変更（または kebab ルートエイリアスを追加）、kpi/chartData は $this->success()/Base::json(0,'ok',...) の規範呼び出しに変更、ハードコードされたサンプルチャートを削除/置換し「物流時効」図を追加（欠ける場合はドキュメントに正直に明記）
- [高] モール管理の位置づけを明確化し二者択一：メニュー内 27 コントローラーに webman-admin 標準の index.html LayUI 一覧ページを追加（各コントローラーに index() レンダリングビュー）するか、menu.php から 404 メニューを削除して「JSON API only」と明記；注文/返金/発送などの P0 モジュールページを優先
- [中] admin テスト骨格を確立：phpunit.xml と tests/ ディレクトリを新設、優先的に Crud 基底クラス（inputFilter/doSelect/データ権限）、AccessControl 認証分岐、InstallController（一時 DB + mock PDO 可）、ShopRefundController の遠隔返金呼び出し（mock service エンドポイント）をカバー
- [中] ドキュメントの過剰宣言を修正：CLAUDE.md の梱包明細 PDF、財務レポート PDF、発送 HS 申告/軌跡、注文エクスポート HS/関税列、i18n 言語切替ボタンなどコードと不一致の記述を実態に合わせ削除または TODO と明記し、誤誘導を防ぐ
- [中] 二重ソーステーブル一覧を解消：InstallController のテーブル衝突一覧を install.sql の CREATE TABLE 解析による動的生成に変更するか、両者の一致を比較する検証スクリプトを提供
- [低] i18n を接続：ビュー/コントローラーで trans() を呼び index.html 上部に言語切替ボタンを追加（ファイルは準備済み、配線のみ）するか、i18n は service API の戻り値のみ対象と明確化
- [低] 品質ツールを補完：.php-cs-fixer.php 設定を新設し CI に接続（admin に phpunit + php-cs-fixer --dry-run を実行）、AUDIT-REPORT.md に列挙済みの「Admin 添加测试」後続項目を引き継ぐ

---

## 3. Flutter クライアント（apps/flutter/）

### 現状まとめ
Flutter クライアントの骨格は完全（11 ページ、11 ルート、5 言語の文言表、Dio 3 インターセプターとバックエンドミドルウェアの整合）ですが、「閲覧可能なデモレベル」の状態：注文/登録/決済の三大取引クローズループは address_id と PosterVerify 人機認証の欠落でバックエンドに 422/40001 で直接拒否され、i18n は 1 ページのみ接続、多通貨は API に未貫通です。

### 実装済み
- 技術スタックとエンジン骨格は実在：pubspec.yaml/lock が flutter_riverpod ^2.3.0、go_router ^12.0.0、dio ^5.3.0、responsive_framework、cached_network_image、flutter_secure_storage、shared_preferences、intl ^0.20.2 をロック；lib/ は計 25 の Dart ファイル、android/ios/macos/linux/windows/web の 6 プラットフォームディレクトリ完備
- GoRouter 設定 11 ルート（app_router.dart）：/、/products、/product/:id、/cart、/checkout、/orders、/profile、/addresses、/login、/register、/order/:id、対応する 11 ページファイルはすべて実在
- i18n 基盤：app_localizations.dart に 5 言語（zh_CN/zh_HK/en/ja/ko）各 32 翻訳キーをハードコード；locale_provider.dart は Riverpod StateNotifier + SharedPreferences で言語/通貨を永続化、localeProvider/currencyProvider 登録済み
- Dio インターセプターとバックエンドミドルウェアの整合：_AuthInterceptor（Bearer token + 401 時に /auth/refresh 呼び出しリトライ）、_LocaleInterceptor（Accept-Language + API-Version header、バックエンドの LocaleMiddleware/VersionRoute に対応）、_PlatformInterceptor（X-Platform header、バックエンドの PlatformMiddleware に対応）
- API 層の契約一致：ApiResponse{code,msg,data} と PaginatedData{list,total,page,per_page} がバックエンドの ApiResponse::success/paginate の統一形式に一致；apiBaseUrl は --dart-define 上書きをサポート、Android エミュレータ 10.0.2.2 の特判
- home_screen が PC/タブレットレスポンシブ実装：>1024 は NavigationRail サイドバー + 4 列グリッド、狭幅は NavigationBar 下部タブ + 2 列グリッド（main.dart で MOBILE/TABLET/DESKTOP の三段ブレークポイント定義）；product_list はデスクトップ左 240px 価格 RangeSlider サイドバー
- 商品モジュール利用可：一覧は keyword/category_id/sort パラメータをサポート（バックエンド ProductController::index がすべてサポート、price_asc/desc、sales、newest ソート含む）、詳細ページは SKU ChoiceChip、カート追加 POST /cart（バックエンド CartController::store が在庫検証し同一 SKU 数量を統合）；ProductCard クリックで詳細へ
- カート利用可：一覧フィールド（id/title/image/price/quantity/selected）がバックエンド CartController::index の出力と一致、DELETE /cart/{id} 削除サポート、決済入口は /checkout へ
- 注文モジュール基礎利用可：一覧（order_no/status_text/pay_amount/currency_code が OrderController::index と整合）、詳細（items 明細含む）、キャンセル POST /orders/{id}/cancel（バックエンド OrderController::cancel 存在）
- 住所管理利用可：/user/addresses の一覧/追加/削除/デフォルト設定がバックエンド UserController の 4 インターフェースと整合、フォームにデフォルト住所マーク
- 認証基礎利用可：login/register が /auth/login、/auth/register を呼び saveTokens で flutter_secure_storage に保存（Token セキュアストレージ）、init() 起動時にログイン状態を復元；AuthService と ApiClient が同じストレージキーを共有
- テストと品質ツール：test/widget_test.dart スモークテスト（testWidgets 1 個、ShopApp レンダリング検証）；analysis_options.yaml が flutter_lints デフォルトルールセットを有効化

### ギャップ
- **注文クローズループ断裂（致命的）**：CheckoutScreen._placeOrder は {currency_code} のみ提出するが、OrderController::store は address_id を強制検証（欠落即 422「受取住所不存在」、docs/api.md 5.3 も address_id を明示要求）；かつ config/poster.php が /api/orders を protected_routes に含めルートに PosterVerify ミドルウェアが掛かり、Flutter が X-Poster-Token を送らない→注文は必ず 40001「人機認証が必要」で拒否される
- 決済が完全欠落：checkout_screen は GET /payment/methods で方法リストを表示するのみで、POST /payment/create と GET /payment/status を一度も呼ばず、注文後に決済発起/結果ポーリングなし、docs/features.md 2.2 の決済シーケンス（C→POST /payment/create→SDK 決済→webhook）と不一致
- 登録が人機認証にブロック：POST /auth/register は PosterVerify 保護（poster.php 設定）、RegisterScreen は X-Poster-Token の取得/携帯を未実装、登録リクエストは必ず 40001 で拒否
- i18n は建庫のみで未着地：AppLocalizations.of は profile_screen.dart のみが実呼び出し（lib 全体で 1 箇所のみ）、残り 11 画面の約 66 箇所はハードコードされた中英文案（home 'Home'、cart 'Shopping Cart'、register '请填写邮箱和密码'、order_detail '订单已取消' 中英混在）、「5 言語インターフェース」の約束を実現できない
- ドキュメントと実態が不一致：apps/CLAUDE.md は「10 条路由」を主張するが実際は 11 条（/order/:id が多い）；技術スタックに fl_chart、window_manager を含むと主張するが、pubspec.yaml/lock の両方にこの 2 パッケージなし；features.md の「Flutter 5 平台」が 6 項目を列挙
- 多通貨が API に未貫通：クライアントの currencyProvider はローカルフォーマットのみに使用、商品一覧/詳細/カートのリクエストはすべて currency パラメータなし（バックエンドデフォルト USD）；ProductDetailScreen はハードコードされた '$' を使い product.display_price を読む（バックエンドは display_price を sku 級にのみ付け、product 級は恒に null）→ VAT 税込行が永遠に表示されない
- ページングとフィルタが不完全：ProductListScreen の _page は決してインクリメントせずスクロールロードなし（初回 20 件のみ表示可）；OrderListScreen にページングなし；デスクトップ価格 RangeSlider は min_price/max_price を渡すがバックエンド ProductController::index に価格フィルタロジックなし（ソートのみ min_price を参照）→ スライダーが無効
- 堅牢性とログイン状態の欠陥：home 以外の各画面の _load に try/catch なし、未ログインで /orders、/user/addresses 等にアクセスし 401 のとき DioException が未捕捉（ローディング状態でフリーズ/未処理例外）；GoRouter にログインガードの redirect なし（redirect-cnt=0）、未ログインで /cart /checkout /orders /addresses に直行可能；Profile の「ログアウト」は context.push('/login') で AuthService.logout() ではなく、token をクリアしない機能バグ
- デッドコードとテストギャップ：ProductReviewList（product_review_list.dart）は実装済みだがどのページからも参照されず、商品詳細にレビューを表示しない；テストはスモーク 1 個のみ、モデル/コンポーネント/統合テストなし；.github/workflows/ci.yml は PHP のみカバー（phpunit+構文）、Flutter analyze/test タスクなし；assets/images ディレクトリは空だが pubspec.yaml がこの asset ディレクトリを宣言

### リスク
- コア取引チェーンが Flutter 端で利用不可：注文（address_id 欠落 + PosterVerify 40001）、決済（/payment/create なし）、登録（PosterVerify 40001）の 3 箇所すべてバックエンドに拒否され、現行コードのままリリースすれば購入変換を直接ブロック
- ログインガードなし + 401 更新ロジックに並行重複排除なし：複数リクエストが同時に 401 になると /auth/refresh を並行呼び出し（api_client.dart にロックなし）、かつ refresh 失敗時にログアウトのフォールバックがないため token 状態が不整合になる可能性
- i18n の二重トラック（文言表 + 66 箇所ハードコード）が長期併存するとインターフェース言語が混在し、新規文言が直接ハードコードされ、5 言語の約束と docs/VERSIONS.md の「国际化 ✅」宣言を実現できず、やり直しコストが累積
- 多通貨表示と実決済の乖離：インターフェースは JPY/KRW に切り替えられるが価格は米ドルでハードコード表示され、API も USD 計価、多通貨決算額が合わず、取引一貫性のリスク
- Flutter CI ゲートなしかつ flutter/dart analyze は本環境で SDK 読み取り専用のため実行検証不能：人手レビューで 25 ファイルに依存、コンパイル/静的問題の回帰リスクが高い（docs/VERSIONS.md 記載の intl 衝突、pending Timer などの歴史的問題が自動化防護を欠く）

### 提案
- [高] 注文クローズループを開通：決算ページに住所選択を追加（/user/addresses を再利用、デフォルト住所フォールバック）、address_id+currency_code を提出し、バックエンドの PosterVerify 検証フローに接続（X-Poster-Token 取得後に POST /orders）、その後 POST /payment/create + GET /payment/status ポーリングの決済ページを実装
- [高] AppLocalizations を全面接続：11 画面の 66 箇所のハードコード文言を translate(key) に置換し欠落キーを補完（住所フォーム、注文ステータス、エラーヒント等）、AppTheme.supportedLocales と locale_provider.supportedLocales の重複定義を削除し単一の唯一ソースに統一
- [高] GoRouter の redirect ログインガードを追加（未ログインで /cart /checkout /orders /addresses アクセス時は /login にリダイレクト）、Profile の「ログアウト」を AuthService.logout() 呼び出し後にホームへ戻るよう変更し、ログイン状態関連ページの状態をクリーンアップ
- [中] 全画面の _load に try/catch とエラー状態/空状態 UI を追加（現在は home のみ例外フォールバック）；ApiClient の 401 更新にシングルフライトロックと失敗時ログアウトのフォールバック；カートに数量増減を追加（PUT /cart/{id}）
- [中] 商品詳細/一覧リクエストに currency パラメータを携帯、価格は sku.display_price または display_price フィールドを読むよう変更、ハードコードされた '$' をすべて CurrencyFormatter に置換；バックエンド ProductController::index に min_price/max_price フィルタを追加し、フロントでスクロールページングを実装
- [中] RegisterScreen および機密操作を PosterVerify に接続：スライダー/パズル検証で X-Poster-Token を取得（バックエンド poster 検証インターフェースまたはフロント統合）、登録/注文が 40001 でブロックされないことを保証
- [低] Flutter テストを補完：Product/Order モデルの fromJson 単体テスト、ルートスモーク（11 ルートの到達性）、カート/住所 widget テスト、さらに GitHub Actions に flutter analyze + flutter test タスクを追加（PHP の ci.yml に合わせる）
- [低] ドキュメントとデッドコードを修正：apps/CLAUDE.md のルート数を 10→11 に、fl_chart/window_manager 宣言を削除；ProductReviewList を商品詳細ページに接続するか削除；空の assets/images ディレクトリをクリーンアップするかプレースホルダーリソースを補充

---

## 4. 鴻蒙クライアント（apps/harmonyos/）

### 現状まとめ
鴻蒙クライアント（HarmonyOS NEXT API 12+、ArkTS + ArkUI）はコンパイル可能な 9 ページ + ApiClient/AppState/ProductCard の完全骨格を持ち、バックエンド API エンドポイントとレスポンス構造がすべて実マッチ（AUDIT-REPORT 記載の 27 個の ArkTS エラーは修正済み、ビルド成功）ですが、機能の深さは「表示レイヤー」に留まります：決算-注文のメインチェーン断裂（address_id 欠落）、Profile は静的シェル、多通貨/多言語未接続、テストと CI なし、ビルド成果物 99 個が誤ってコミットされ、全体として Flutter クライアントとの差が明確です。

### 実装済み
- 9 個の ArkTS ページがすべて存在し main_pages.json に登録（Index/ProductDetail/Cart/OrderList/Checkout/Profile/Login/Register/Search）、他に EntryAbility、ApiClient、AppState、ProductCard、コンパイル可能（entry/build キャッシュと AUDIT-REPORT.md M3 修正記録が裏付け、B+ 評価）
- ApiClient が @ohos.net.http をカプセル化：GET/POST/DELETE、Bearer token、API-Version(2026-05-20)、Accept-Language、X-Platform: harmonyos header、宣言的 QueryParams/RequestBody インターフェースが ArkTS リテラル制約を満たす
- AppState シングルトン：token/locale/currency を @ohos.data.preferences で永続化（erik_shop ストレージ）、cartCount は /cart を取得して計算、logout は token をクリア
- バックエンドルートとクライアント呼び出しが逐条一致：/auth/login、/auth/register、/products、/products/{id}、/banners、/search、/cart(GET/POST/DELETE)、/orders(GET/POST)、/shipping/calculate、/payment/methods がすべて service/config/route.php に登録されコントローラー存在
- レスポンス構造とクライアント解析が一致：products/orders/search は data.list を返す（status_text 中文マッピング、sort=sales サポート含む）、cart は items 配列を返す（title/image/price/quantity）、shipping は data.options を返す、payment/methods は stripe/paypal のみ公開
- ホームが Banner カルーセル（/banners?position=home）+ 人気商品 2 列 Grid（/products?per_page=10&sort=sales）を実装、上部検索バーとカートアイコン入口含む
- 検索ページがキーワード検索（/search?keyword=&per_page=40）、結果カウント、空状態と loading 状態を実装、ProductCard を再利用
- カートページが一覧/合計計算/削除（DELETE /cart/{id}）と空状態表示を実装、決算へ遷移可
- 商品詳細ページが loading 状態、主画像/タイトル/価格/説明表示、カート追加（先頭 SKU を取得し POST /cart を呼ぶ）を実装
- 注文リストが Tabs 状態フィルタ（全部/支払い待ち/発送済み/完了 → status 0/2/4）と loading/空状態を実装
- ログイン/登録ページが /auth/login、/auth/register を呼び、登録は AppState.setToken で永続化
- 決算ページが注文商品/送料オプション（Radio 選択）/決済方法を表示し合計を計算、提出アクションをサポート
- プラットフォーム識別チェーン完全：X-Platform: harmonyos と service/app/middleware/PlatformMiddleware.php の 8 プラットフォームホワイトリストが一致
- エンジン設定达标：compatibleSdkVersion 5.0.0(12)（API 12+）、stageMode、deviceTypes phone/tablet/2in1、hvigor modelVersion 5.0.0

### ギャップ
- **決算-注文メインチェーン断裂**：Checkout.ets の placeOrder は {currency_code:'USD'} のみ渡し、バックエンド OrderController.php:88-96 は address_id を必須検証（なしで 422 受取住所不存在）；CartController.php:113 は selected=1 の商品のみ決算するのに Cart.ets にチェック能力なし；クライアントに住所管理ページなし（Profile の受取住所メニュー route は空）——注文は必然的に失敗
- 決済フロー未接続：Checkout ページは決済方法を表示・選択するが、placeOrder が決済パラメータを渡さず POST /payment/create を呼ばない、docs/features.md の「決済(Stripe/PayPal)完全」と不一致
- Profile.ets が静的シェル：isLoggedIn @State は初期 false で AppState を決して読まない（ログイン後もログイン/登録を表示）；「ログイン/登録」項目に onClick なし；お気に入り/受取住所/ギフトカード/言語/通貨/プライバシー設定の 6 メニュー route がすべて空で利用不可；ログアウト入口なし
- ログイン状態管理の二重トラック不一致：Login.ets は直接 getPreferences で access_token/refresh_token を書き、AppState.setToken を迂回、AppState のメモリ token が同期しない（isLoggedIn() が false を返す）；Register.ets は AppState 経由で、二箇所のパスが分裂；ログイン成功時にどちらも cartCount を更新しない
- ホームのカテゴリ入口が永遠に空白：Index.ets の loadData は /banners と /products のみリクエストし、categories 配列に一切代入しない；Banner にクリック遷移なし；上部 Search コンポーネントに onSubmit がなく検索ページへ入らない
- 多通貨/多言語未接続：AppState.currency は永続化された後、一度も API に渡されない（Checkout は country:'US'/currency:'USD' をハードコード、shipping は dest_country_id:1/weight:500 をハードコード）；UI 文言はすべて中文と '$' をハードコード（docs/features.md 293 行も「ArkTS 硬编码」を認める）、en_US 等のリソースディレクトリなし、Flutter の 5 言語との差が明確
- テストと品質ゲートなし：apps/harmonyos 配下に ohosTest ディレクトリなし、.ets テストなし；.github/workflows/ci.yml は PHP 構文チェック + 単体テストのみ、鴻蒙ビルド job なし；lint/フォーマットツール設定なし
- リポジトリ衛生問題：git が entry/build と .hvigor キャッシュ成果物 99 個を追跡（追跡ファイル 131 個の 76% を占め、msgpack/tsbuildinfo/コンパイルレポート含む）、.gitignore に鴻蒙無視ルールなし；かつリポジトリに hvigorw wrapper スクリプトなし（apps/CLAUDE.md が主張する `hvigorw assembleHap` は直接実行不可、グローバル hvigor か DevEco Studio が必要）
- ApiClient の堅牢性不足：request/JSON.parse に try-catch なし、タイムアウト設定なし；delete() に X-Platform header 欠落；refresh_token は保存後一度も更新に使用されず；EntryAbility.onCreate の AppState.init() に await なし、初フレームページリクエストが token 準備より先行する可能性（競合）；デフォルト baseUrl は http://10.0.2.2:8787/api をハードコードしエミュレータのみ適合

### リスク
- コア取引クローズループ未開通：決算注文は必ず 422 を返す（address_id 欠落）、docs/features.md の「完全」位置づけで対外リリースすれば主経路が直接失敗、リリース遮断級の欠陥
- テストなし且つ CI に鴻蒙 job なし：ArkTS の厳格型（リテラル/単一根 build 制約）の回帰リスクが高く、AUDIT-REPORT M3 の 27 個のコンパイルエラーが前例、以降のページ変更に自動化保障なし
- ビルド成果物のコミット + wrapper なし：リポジトリが肥大化（msgpack キャッシュ等バイナリ）、無意味な diff が発生しやすく、新環境でドキュメントコマンドによりビルドを再現できず、CI に鴻蒙を接続するにも統一ビルド入口がない
- 状態管理の二重トラック（AppState シングルトン vs ページローカル @State + Login の preferences 直書き）とレスポンシブ機構なし：今後お気に入り/住所/通貨切替などの共有状態を接続する際、メモリと永続化の不整合が起きやすい
- 実機/リリース適応の欠落：デフォルト baseUrl が Android エミュレータアドレスを指しプラットフォーム認識機構なし（Flutter は M4 で修正済み）、HarmonyOS 実機と本番 HTTPS 環境で利用不可

### 提案
- [高] 決算-注文クローズループを開通：受取住所一覧/新規ページを新設し UserAddresses 関連 API に接続（バックエンド準備済み）、Cart ページに selected チェックを追加（バックエンドは selected=1 の商品のみ決算）、Checkout.placeOrder に address_id + selectedShipping + 通貨を渡し POST /orders 成功を検証
- [高] Profile とログイン状態の一貫性を修正：Profile.aboutToAppear で AppState.isLoggedIn() を読みレスポンシブに更新、「ログイン/登録」クリックで Login へ遷移、ログアウトを追加；Login/Register を統一して AppState.setToken 経由とし refreshCartCount を呼び出し
- [高] テストと CI ゲートを確立：ohosTest（ArkXTest）を新設し、少なくとも ApiClient のリクエスト解析（mock server 注入可）と AppState の永続化読み書きをカバー；ci.yml に hvigor ビルド job を追加（グローバル hvigor 使用か wrapper を補完）、コンパイル回帰を阻止
- [中] リポジトリ治理：.gitignore に apps/harmonyos/**/build、**/.hvigor、**/oh_modules ルールを追加し git rm --cached で入库済みの 99 個の成果物をクリーンアップ；hvigorw wrapper を補完（ohpm で @ohos/hvigor をインストール）し apps/CLAUDE.md のコマンドを利用可能に
- [中] 多通貨/多言語接続：Checkout/Index/ProductDetail を AppState.currency と QueryParams.currency の引数渡しに変更（バックエンドは多通貨価格設定をサポート）；UI 文言を resources/base と en_US 言語ディレクトリに移行、まず英語を補って Flutter の i18n インターセプター方式に整合
- [中] ホームと検索体験の補完：loadData に /categories でカテゴリ入口 Grid を追加、Banner クリックで link_url へ遷移、上部検索ボックス onSubmit で Search ページへ；Search 結果にページングを接続（現在は per_page 40 の一回取得）
- [中] ApiClient の堅牢性強化：try/catch とタイムアウト（http.RequestOptions timeout）を統一、401 時に保存済みの refresh_token で自動更新リトライ、delete() に X-Platform header を追加、baseUrl をランタイム設定可能に（Flutter のプラットフォーム認識に倣う）
- [低] 初期化競合と詳細ページを修正：EntryAbility で await AppState.init() 後に loadContent（またはページの準備完了待ち）；ProductDetail に複数 SKU 選択 UI と「今すぐ購入」の実注文動作を追加、商品画像に Image キャッシュを接続

---

## 5. セキュリティとコンプライアンス

### 現状まとめ
プラットフォームは WAF 攻撃検知、JWT デュアルトークン、AES インターフェース暗号化、Encryptable フィールド暗号化、Hashids 難読化、決済 Webhook 署名検証とキー管理に実在かつ比較的完全な実装（22 テスト全通過）がありますが、風控ルールエンジン、KYC、GDPR/CCPA 実行層はテーブル構造と管理端 CRUD のみで、コア業務ロジックが欠落し、docs/features.md、docs/VERSIONS.md の主張する「完全/✅」と一致しません。

### 実装済み
- WAF 攻撃検知：service と admin の SecurityMiddleware がともに erikwang2013/security-php v1.1.6 SecurityGuard をカプセル化、config/plugin/erikwang2013/security-php/app.php に 31 検出器を設定（28 個 block、3 個 log：header_injection/ssti/nosql_injection）、XSS/SQLi/XXE/SSRF/パストラバーサル/ファイルアップロード/CSRF/Host/DNS rebinding などを含み、IP ブラックリスト（5 回/60s→900s 封鎖）、セキュリティレスポンスヘッダー（nosniff/DENY/Permissions-Policy/Server 非表示）も追加
- ブルートフォース防護：service 端 Redis カウンター erik_brute:{ip}:{login|register} 10 回/60s（SecurityMiddleware::checkBrute）、admin 端 5 回/300s
- JWT 認証：config/jwt.php（HS256、access 7200s/refresh 1209600s、issuer/audience/leeway）、app/common/Jwt.php は空キーで fail-closed（JWT_SECRET→JWT_SECRET_KEY フォールバックチェーン）、JwtAuth ミドルウェアは非 access 型トークンを拒否、AuthController の登録/ログインでデュアルトークン発行、refresh エンドポイントでデュアルトークンローテーション（firebase/php-jwt v6.11.1 ロック）
- AES インターフェース暗号化：app/common/Encryption.php（AES-256-CBC、毎回ランダム IV、base64(iv+暗号文)、キー長検証 16/24/32 バイト）、EncryptionMiddleware が X-Encrypted:1 のリクエスト復号、X-Encrypt-Response:1/X-Encrypt-Fields のレスポンスフィールド暗号化をサポート、/api/health、/api/ping、/apidoc をスキップし、グローバルミドルウェアスタック末段として登録
- Encryptable フィールド暗号化：31 モデルが Erik\Encryptable\Encryptable trait を使用（Users の email/mobile、UserKyc の real_name/id_number、UserAddresses、PrivacyRequests.email、PaymentGateways.api_key など）、パスワードは bcrypt(password+salt) でユーザーごとにランダムソルト、$hidden がシリアライズ漏洩をブロック
- Hashids 難読化：config/hashids.php + プラグイン設定、HashidsHelper は空ソルトで fail-closed、HashidsDecode（ルートパラメータ + _id 終端フィールドの自動デコード）と HashidsEncode ミドルウェアが service/admin の両方で有効、コントローラーはエンコード済み ID を対外返却
- 決済セキュリティ：StripeGateway は Stripe\Webhook::constructEvent で署名検証、PayPalGateway は公式 /v1/notifications/verify-webhook-signature で署名検証（PAYPAL_WEBHOOK_ID 必要）；PaymentController::webhook は署名検証→冪等更新（注文 status=0 のアトミックガードで重複入金防止）→PlatformSettlements 按分作成；create インターフェースは実装済みの stripe/paypal のみ公開；erik_payments テーブルに three_ds_status フィールド；admin 返金実行インターフェースは AdminKeyMiddleware（X-Admin-Key、hash_equals 比較）経由
- キー管理：.env.example/.env に JWT_SECRET/JWT_SECRET_KEY/HASHIDS_SALT/ENCRYPTION_KEY/ADMIN_API_KEY/STRIPE_SECRET_KEY など、.env は .gitignore 内；Web インストールウィザード（InstallController）は random_bytes でランダムキーを生成；Jwt/Encryption/HashidsHelper は欠落キーに対してすべて fail-closed
- 限流と人機認証：RateLimitMiddleware の Redis ZSET スライディングウィンドウ（デフォルト 60s/100 回、ログイン 60s/10、登録 300s/5、決済 60s/5、注文 10s/3、検索 1s/10）、PosterVerify が登録/注文/決済などの機密ルートを保護
- テストと品質ツール：tests/ の計 22 tests/45 assertions を実測 ALL PASS（SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3）、phpunit.xml、phpstan.neon（level 5）、.php-cs-fixer.php、.github/workflows/ci.yml（PHP 8.3/8.4 マトリクス + MySQL/Redis サービス）；README は composer audit の既知低危険度 CVE 1 件を記録（firebase/php-jwt <7.0.0、jwt-webman ^6.0 の制約）

### ギャップ
- 風控ルールエンジン未実装：docs/features.md §3.3 は注文フローに「风控打分(RiskEngine::score)」を含むと主張、VERSIONS.md は「风控规则引擎 ✅」を主張するが、プロジェクト全体の grep RiskEngine は 0 ヒット；erik_orders.risk_score/risk_result フィールドはどのコードからも一度も書き込まれず、RiskRules/RiskLogs は空モデルのみ、config/risk.php は設定のみで実行ポイントなし；service/database/seeders/ は countries.php のみで、CLAUDE.md が主張する「風控ルール」シードなし；ShopRiskRuleController/ShopRiskLogController は admin メニューに未登録
- KYC にユーザー側提出入口なし：erik_user_kyc テーブル/UserKyc モデル/admin CRUD コントローラーは存在し、OrderController は注文時に kyc_required 国家（config/country.php は KR のみ）の status=1 を検証するが、service/config/route.php に KYC 提出/照会ルートが一切なく、ユーザーは本人確認資料を自助提出できずクローズループ欠落
- GDPR/CCPA はリクエスト登記のみで実行ロジックなし：PrivacyController は privacy_requests（status=pending）への書き込みと 30 日以内処理の約束のみで、データ削除/エクスポート/opt-out の実実行コードなし；ShopPrivacyController はステータス CRUD のみ；config/privacy.php の data_retention/retain_on_deletion に対応するクリーンアップ定期タスクなし；erik_cookie_consents テーブルに書き込み端なし（Cookie Consent フロントコンポーネントも API もない）
- クライアントが AES インターフェース暗号化を消費せず、鴻蒙端は token を平文保存：Flutter/HarmonyOS のリクエストは Authorization/Accept-Language/API-Version/X-Platform のみ携帯、grep で X-Encrypted/X-Encrypt-Response サポートなし（インターフェース暗号化はサーバー単方向の能力で、docs のいう「三層暗号化」は端上で未効果）；鴻蒙 AppState は @ohos.data.preferences で token を保存（平文）、Flutter は flutter_secure_storage、クロスエンドのセキュリティ不一致
- 3DS に明示コード証拠なし：StripeGateway::createPayment が payment_method_options[card][request_three_d_secure] を設定せず、three_ds_status フィールドは一度も書き込まれず、3DS は Stripe のデフォルト戦略依存；README/features.md が主張する「3DS 验证」にコード根拠なし；Klarna/Adyen は config プレースホルダーのみ（PaymentController のコメントがフロントでフィルタ済みと説明）、README の主張する「Stripe/PayPal/Klarna/Adyen、BNPL」と不一致
- 認証の欠落項目：パスワード忘れ/リセットフローなし（grep forgot/reset 0 ヒット）、メール検証なし、JWT に失効機構なし（パスワード変更/ログアウト後も token 有効）、refresh エンドポイントに個別限流なし
- CI に依存セキュリティ監査と静的解析が未組み込み：composer audit は README ドキュメントにのみ登場、ci.yml は PHP 構文チェック + PHPUnit のみ、composer audit/phpstan/php-cs-fixer ステップなし；phpstan.neon の excludePaths が config/plugin（セキュリティプラグイン設定含む）を除外

### リスク
- 依存セキュリティのブロック：firebase/php-jwt v6.11.1 はドキュメント宣言の CVE-2025-45769（<7.0.0）の影響範囲内、erikwang2013/jwt-webman ^6.0 のハード制約でアップグレード不可、長期間未解消の既知脆弱性の露出（HS256 対称用法は影響なし、ただし上流の継続追跡が必要）
- 検知カバレッジの盲点：csrf_origin/host_header/dns_rebinding/request_smuggling などのヘッダー類検出器は $_SERVER 依存、Workerman 非 CGI 環境で見逃す可能性（docs/security-review.md §5.1 自述）；IP ブラックリストの file ストレージは sys_get_temp_dir で、Docker 再起動で消失、多インスタンス非共有、攻撃者が IP をローテーションすれば回避可能（設定に redis ストレージは予約済みだが未有効）
- コンプライアンス宣言と実装の乖離の露出：ドキュメントで「風控完全/KYC 完全/GDPR 完全」を対外主張するが、実際は注文作成に風控採点が一切なく即通過（不正注文リスク）、KYC を自助提出できない、削除リクエストを誰も実行しない、これで対外にコンプライアンス能力を約束すれば実質的なコンプライアンスリスクになる
- キー管理が弱い：インストールウィザードが bin2hex(random_bytes(16)) で ENCRYPTION_KEY を生成（32 個の hex 文字=128bit エントロピー、256bit 未達）、bin2hex(random_bytes(8)) で HASHIDS_SALT を生成（64bit エントロピー）；ENCRYPTION_PREVIOUS_KEYS にローテーション自動化なし；webman 常駐プロセスは .env 変更後に reload が必要

### 提案
- [高] 風控ルールエンジンを実装：config/risk.php + erik_risk_rules テーブルに従い RiskEngine::score を実装（イベント user_register/user_login/order_create/payment_create/refund_request）、OrderController::store/PaymentController::create/AuthController で呼び出し、risk_score/risk_result と RiskLogs に書き込み、バイパスモードでは高スコア注文を status=8 審査待ちに（注文ステートマシンの「審査待ち」分岐に対応）、ShopRiskRule/ShopRiskLog を admin メニューに登録
- [高] KYC クローズループを補完：POST /api/kyc（本人確認資料提出、real_name/id_number は Encryptable 暗号化）、GET /api/kyc/status を新設、admin 審査通過で status=1 とし OrderController の既存検証と接続；KYC シード/サンプルデータを補完
- [高] GDPR/CCPA 実行層を実装：プライバシーリクエスト処理の定期タスクを新設（retain_on_deletion に従い税務フィールド保持、deleted_user_grace 30 日猶予後にユーザーデータ削除、data_portability エクスポートファイル生成、opt_out にマスクマーク書き込み）；Cookie Consent コンポーネントと POST /api/privacy/cookie-consent で erik_cookie_consents に書き込み；data_retention 設定をクリーンアップタスクとして実装
- [高] クライアントにインターフェース暗号化とセキュアストレージを接続：Flutter/HarmonyOS が X-Encrypted/X-Encrypt-Response をサポート（キーはセキュアチャネルで協議し配布）、鴻蒙端の token は KeyStore/security.asset 保存に変更し preferences 平文を置換
- [中] 決済セキュリティ強化：Stripe createPayment に明示的な request_three_d_secure='automatic' を設定し three_ds_status に書き戻し；payments 照会/返金インターフェースにユーザー帰属検証を追加（status は検証済み、返金/エクスポートは再査が必要）；README/VERSIONS の Klarna/Adyen「完全」表現を同期修正するか実装を補完
- [中] 依存セキュリティを CI に組み込み：ci.yml に composer audit、phpstan（config/plugin を解放するか個別検証）と php-cs-fixer --dry-run ステップを追加；CVE 追跡を確立し、jwt-webman が php-jwt ^7 をサポートしたら即アップグレード
- [中] 認証強化：パスワードリセットフロー（メール検証コード + 一回限りリセットトークン）を実装；JWT 失効（Redis ブラックリストまたはトークンバージョン番号、パスワード変更/ログアウト後に無効化）；refresh エンドポイントに限流とリプレイ検出を接続
- [低] キーと検知の強化：ENCRYPTION_KEY を raw 32 バイト base64 キー（256bit エントロピー）に変更、hashids salt を ≥16 バイトに強化；security-php storage を redis モードに切り替え IP ブラックリストを共有；security-review.md §5.1 の提案に従い SecurityGuard の $meta に header を明示的に渡し、ヘッダー類検知を補完

---

## 6. デプロイ / データ / テスト品質

### 現状まとめ
Erik Shop のデプロイオーケストレーション（nginx→service:8787/admin:8788 + MySQL/Redis/ES）、117 テーブル構造、単体テスト（22 tests/45 assertions 実測全通過）の基礎は堅実でドキュメント-コードも概ね一致していますが、静的解析ツールが開封即クラッシュ（PHPStan 128M メモリ制限）、admin 側の品質設定が完全欠落、GeoIP データファイル欠落、一部の定期タスクが外部 API 未設定で実質空回転、本番コンテナに dev 依存と無認証ミドルウェアの露出リスクがあります。

### 実装済み
- docker-compose.yml が 6 サービス（nginx/service/admin/mysql/redis/elasticsearch）を完全オーケストレーション、すべて healthcheck + depends_on 条件起動 + 名前付きデータボリューム + ブリッジネットワーク付き、`docker compose config` 実測検証通過；nginx は docker/nginx/conf.d/shop.conf で keepalive upstream として service:8787 と admin:8788 をリバースプロキシ（api.erik.xyz/admin.erik.xyz の 2 仮想ホスト）
- service/Dockerfile と admin/Dockerfile はともに php:8.3-cli-alpine ベース、pdo_mysql/bcmath/opcache/gd/intl/sockets/redis などの拡張をインストールし composer install --no-dev --optimize-autoloader（service 側は OPCache 本番設定 docker/opcache.ini も含む）
- CI（.github/workflows/ci.yml）は PHP 8.3/8.4 マトリクス + MySQL 8.0/Redis 7 サービスコンテナを設定、composer install、php -l 構文チェック（service+admin ディレクトリ）と PHPUnit を実行；Makefile は start/stop/test/lint/check/fix/install/docker-up など 14 コマンドを提供
- PHPUnit 実測実行通過：22 tests / 45 assertions ALL PASS（SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3、phpunit.xml は 12.5 schema 使用）
- phpstan.neon は level 5 を設定（paths=app+config、Eloquent/webman 動的メソッドの ignoreErrors 含む）；実測 --memory-limit=1G で 0 エラー
- service/.php-cs-fixer.php は PSR-12 + no_unused_imports/ordered_imports などのルールを設定、app+config をカバー；.editorconfig でエンコーディングとインデントを統一
- install.sql は実測で 117 テーブル（7 枚の wa_ システムテーブル + 110 枚の erik_ 業務テーブル、InnoDB/utf8mb4_unicode_ci）、service 側の 110 業務モデルが 110 枚の erik_ テーブルと一対一対応（B2B/サブスクリプション/サプライチェーンなど 12 モジュールのテーブル完備）、MySQL 公式イメージの docker-entrypoint-initdb.d が自動インポート
- Web インストールウィザード実在（admin/plugin/admin/app/controller/InstallController.php step1/step2）、ルート install.sql をインポートし service/.env と admin/.env を生成（ランダム JWT/Hashids/AES キー含む）；キー類の .env ファイルは .gitignore で除外され入库しない
- 10 個の定期タスクプロセス（config/process.php 登録の exchange_rate/shipment_tracking/product_feed/recommendation/compliance/return_expire/price_alert/payment_reconcile/settlement/platform_order_sync cron）がそれぞれ独立常駐プロセスと周期を持つ
- ミドルウェアスタックがドキュメントどおり登録：Cors→Security→RateLimit→Platform→GeoIp→Locale→HashidsDecode→VersionRoute→HashidsEncode→Encryption（config/middleware.php 実測 10 グローバル + PosterVerify/JwtAuth/AdminKey ルート級、14 ミドルウェアがドキュメントと一致）
- docs/api.md の 71 個の API エンドポイントが service/config/route.php と実測で概ね一対一対応（/health ヘルスチェックの db+redis 実検知含む）；docs/features.md の Flutter 25 ファイル、HarmonyOS 14 ファイル、Admin 82 コントローラー/76 モデルはすべてコード統計と一致

### ギャップ
- PHPStan 開封即利用不可：phpstan.neon に memoryLimit 未設定、デフォルト 128M で並行 worker が直接クラッシュ（実測再現 'reached configured PHP memory limit: 128M'）、Makefile check ターゲットも CI も --memory-limit 未携帯、静的解析ゲートが実際には通らない
- admin 側の品質設定が全欠落：phpstan.neon なし、.php-cs-fixer.php なし、phpunit.xml なし、tests/ ディレクトリなし、composer.json の require-dev に phpstan なし；実測 `make fix` の admin 段（admin && vendor/bin/php-cs-fixer fix）が設定なしで対話式 'create config file?' プロンプトに入りハング、`make check` も service のみカバー
- CI に phpstan と php-cs-fixer が未統合（php -l + PHPUnit のみ）、かつ service のみテスト；CI は MySQL/Redis サービスコンテナを起動済みだが、MySQL に接続する統合テストが一切なく、テストカバレッジは 4 ツールクラスに留まり、111 モデル/39 コントローラー/14 ミドルウェア/10 定期タスクはゼロテスト
- GeoIP データファイル欠落：config/geoip.php は service/database/geoip/GeoLite2-Country.mmdb を指すが、該当ディレクトリは実測で空、ダウンロード/導入手順スクリプトなし、GeoIpMiddleware は file_exists フォールバック分岐のみ、features.md の主張する 'GeoIP 完整' は名ばかり
- 3 個の定期タスクが外部 API 未設定で空回転：config/cron.php の tracking_api_url、compliance_source_url、platform_sync_url がすべて空文字列、ShipmentTrackingCron/ComplianceCron/PlatformOrderSyncCron は 'スキップ' ログを記録するのみ（コードコメントも確認）；客服 WebSocket リアルタイム IM 未実装（features.md 自述 'WS 待实现'、chat コントローラー/WS プロセスなし）
- 本番デプロイイメージがソースボリュームに上書き：docker-compose.yml が ./service:/app、./admin:/app をコンテナにマウントし、Dockerfile の COPY + composer install --no-dev の成果物を上書き、かつ service/、admin/ に .dockerignore がないため、本番コンテナが実際にホストの vendor（dev 依存含む）を実行
- ドキュメント不一致と遊休設定：docs/deployment.md の 2 箇所が admin を 8787 / 'admin.erik.xyz → admin:8787' と記載（実際は 8788）；nginx は ./service/public:/var/www/static:ro をマウントするが、どの server ブロックもこの静的ディレクトリを使用していない
- Elasticsearch と Redis のセキュリティが弱い：compose で ES は xpack.security.enabled=false かつ 9200 ポートをホストに公開、認証なし；Redis の requirepass は ${REDIS_PASS:-} 依存のデフォルト空パスワードかつ 6379 公開、.env 未設定時はミドルウェアが無防備

### リスク
- 本番環境のキー/認証欠落の連鎖リスク：compose デフォルトプレースホルダー（change_me 系）を未置換で起動可能、ES 無認証、Redis デフォルト無パスワード、サービスポート全公開、.env 設定が不完全なまま直接リリースすると攻撃面は 9200/6379/3306/80 をカバー
- テスト品質が形骸化するリスク：22 個の単体テストはツールクラスのみカバー、モデル/コントローラー/ミドルウェア/データベースの統合テストなし、CI に静的解析ゲートなし（PHPStan クラッシュ、php-cs-fixer 未入 CI）、リファクタリングと統合が無防備で、回帰問題は人手に頼るしかない
- 本番コンテナが dev 依存を実行：ソースボリュームマウントがイメージを上書き + .dockerignore なし、--no-dev 最適化が迂回された後コンテナ内の vendor に PHPUnit/phpstan などの dev パッケージが含まれ、イメージが肥大化し「本番は dev 依存なし」の約束に反する
- 外部依存の空回転によるデータ信頼性リスク：物流軌跡/コンプライアンスルール/プラットフォーム注文同期の 3 cron がデフォルトで実同期を一切実行せず、運営が「自動化済み」と誤認すると軌跡未更新、コンプライアンスルール期限切れ、多プラットフォーム注文同期漏れのサイレント障害が発生
- GeoIP フォールバックによる地域価格設定/言語識別の機能不全：mmdb 欠落時は全リクエストが config('geoip.default')（固定 US/USD/en）にフォールバック、越境ユーザーはアメリカデフォルトの価格と言語で表示され、多通貨/多言語のコアセールスポイントの正確性に直接影響

### 提案
- [高] PHPStan ゲートを修正：phpstan.neon に memoryLimit を設定**しない**（PHPStan 2.2.8 はこの neon パラメータを削除済み、設定すると `Unexpected item` エラーになる）、`make check` と CI の phpstan コマンドに `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` を持たせる、実測 0 エラーで通過可（着地済み、docs/PLAN.md 実施状態参照）
- [高] admin 品質設定を補完：admin/phpstan.neon（level 5、paths=app+plugin/admin/app）と admin/.php-cs-fixer.php（service ルールを再利用）を新設し、admin を CI の phpstan/php-cs-fixer --dry-run 検査に組み込み；admin 品質設定が着地するまでは Makefile fix ターゲットから一時的に admin 段を外し対話ハングを回避
- [高] 本番イメージビルドを修正：docker-compose.yml から ./service:/app と ./admin:/app のソースマウントを削除（または runtime/logs ディレクトリのみマウントに変更）、service/.dockerignore と admin/.dockerignore を新設（vendor/runtime/.git などを除外）、コンテナ内で --no-dev vendor のみ実行されることを保証
- [高] 統合テストを補完し CI に接続：CI が起動済みの MySQL/Redis サービスコンテナを利用し、service/tests 配下にデータベーススモーク/ルート級統合テストを新設（例：install.sql のインポート可否、ヘルスチェック、登録-ログインクローズループ）、'22 tests' を純単体テストから回帰防止可能なゲートに拡張
- [中] GeoIP データファイルを解決：Geo-Lite2-Country.mmdb を service/database/geoip/ にダウンロードするスクリプト/ドキュメントを提供（または config の MAXMIND_LICENSE_KEY 自動更新を有効化）、README/INSTALL に欠落時は US デフォルト値にフォールバックする影響を明記
- [中] ミドルウェアのセキュリティ露出面を引き締め：docker-compose.yml で ES/Redis/MySQL のポートバインドを 127.0.0.1 に変更（nginx のみ 80/443 を公開）、ES に xpack 認証を有効化するか compose コメントで本番は REDIS_PASS/ES セキュリティグループの設定が必須と明記、無防備リリースを回避
- [中] 外部依存の空回転を解消：config/cron.php の 3 つの空 URL に目立つコメントを追加しログを WARNING 級に昇格（または管理バックエンドの設定入口を提供）、同時に features.md で '物流軌跡/コンプライアンス更新/多プラットフォーム同期' の状態を '完全' から '外部 API 設定依存' に変更し、コード実態と整合
- [低] ドキュメントと遊休設定をクリーンアップ：docs/deployment.md の admin ポート 8787→8788 の誤記 2 箇所を修正；nginx マウントで未使用の ./service/public:/var/www/static:ro ボリュームを削除するか静的ファイル server ブロックを追加；features.md/README で '客服 WebSocket IM 未実装（テーブル構造のみ）' を明確化し営業口径の誤誘導を回避

---

## 7. ドキュメントと機能カバレッジ

### 現状まとめ
ドキュメント体系は完備（8 枚のアーキテクチャ図 SVG+MMD、api.md/architecture/design/deployment/VERSIONS/AUDIT など 9 文書）で、数値口径の多くがコードと一致（73 ルートエンドポイント、117 テーブル、22 tests/45 assertions 実測通過、service/admin 各 5 言語 × 45 条翻訳、19 通貨シード）していますが、features.md/VERSIONS.md/README が多プラットフォーム刊登、風控ルールエンジン、Klarna/Adyen 決済、四線按分、商業インボイス PDF、サブスクリプション周期購/AB テスト、WebSocket 客服などに「完全/✅」と標記する機能は、実際はテーブル構造 + admin CRUD か完全に業務実装なしで、体系的な「ドキュメントがコードに先行」状態です。

### 実装済み
- 8 枚のアーキテクチャ図完備（01-08 SVG はすべて実際のレンダリング成果物 15-153KB、対応 .mmd ソース含む）、docs/diagrams.md の図例インデックスと一対一対応
- service ルートは実測 73 個（23 公開 + 47 認証 + 1 Webhook + 1 Admin + 1 /health）、docs/api.md の 71 エンドポイント、architecture-full.md の 73 エンドポイントと概ね一致；23 個の公開エンドポイントはすべて route.php に存在
- service 39 コントローラー/111 モデル/14 ミドルウェア、admin 76 モデル/5 ミドルウェア、10 個の Cron プロセス（process.php）、install.sql 117 テーブル（110 erik_ + 7 wa_）が README の数字とすべて一致
- テスト実実行可：phpunit 実測 22 tests/45 assertions ALL PASS（SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3）；phpstan level5、php-cs-fixer、Makefile 14 コマンド、CI（PHP 8.3/8.4 マトリクス + MySQL + Redis）すべて存在
- i18n 着地：service/admin 各 5 言語 × 45 条翻訳ファイル、Flutter 手書き AppLocalizations 5 言語 + SharedPreferences 永続化、LocaleMiddleware 5 言語一致、Accept-Language/API-Version/X-Platform header 規範完備
- 決済：Stripe（PaymentIntent + client_secret + 3DS）と PayPal（REST v2 OAuth2 + Webhook 五フィールド署名検証）の完全ゲートウェイ実装；Webhook 署名検証→決済/注文更新→PlatformSettlements 生成のトランザクションクローズループ（アトミックガードの重複入金防止含む）
- ソーシャルログイン Google/Apple/Facebook の id_token fail-closed 検証（tokeninfo/JWKS/debug_token）+ 紐付け/メール乗っ取り防止ロジック；ExportController XLSX+CSV に HS Code 列含む；B2B 見積もり照会/グループ購入/タイムセール/クーポンロックなどの業務が実在
- セキュリティ：security-php 31 検出器設定（service/admin 一致）、RateLimitMiddleware 7 ルール（デフォルト + 6 エンドポイント）、DB 読み書き分離 2 リードレプリカ + sticky=true、45 モデル SoftDeletes、PosterVerify（slide/click/rotate）Redis 検証
- 多言語商品/多通貨価格設定（19 通貨シード）、ES 検索（scout + MySQL LIKE フォールバック）、ProductFeedCron の Google/Meta TSV 生成、ExchangeRateCron の毎時為替レート取得、ECharts ダッシュボード（6 KPI + 3 チャート）、Web インストールウィザード（建庫→install.sql インポート→.env 生成→管理者作成）
- クライアント：Flutter 25 Dart ファイル/11 ページ（Riverpod + GoRouter + responsive_framework の PC/タブレットレスポンシブ、ハードコード中文 2 箇所のみ）；HarmonyOS 9 ページ ArkTS（ApiClient/ProductCard/AppState 含む）

### ギャップ
- Klarna/Adyen 決済未実装：PaymentGateway::make() は stripe/paypal のみサポート（service/app/common/PaymentGateway.php）、PaymentController.php:34 に '実装済みゲートウェイのみ返却し、Klarna/Adyen などの未実装設定の露出を避ける' と明示コメント、しかし README.md と VERSIONS.md はこれを ✅ に列挙、features.md のみ 'Stripe 完全、他はプレースホルダー' と認める
- 商業インボイス/梱包明細 PDF 未実装：composer.json に barryvdh/laravel-dompdf があるがプロジェクト全体（service/admin）で Dompdf 呼び出しゼロ；DocumentController.php は既存の erik_order_documents レコードを読むのみで、生成ロジック一切なし（order_documents レコードも自動作成されない）、features.md は '商業インボイス PDF/梱包明細' を完全と主張
- 風控ルールエンジン未実装：app/common の 8 クラスに RiskEngine なし、OrderController::store に風控採点なし、注文状態 8(審査待ち) は永遠に書き込まれない；features.md は 'ルールエンジン(旁路打分:アドレス検証/郵便番号一致/3DS/一括登録/貨物価額異常) 完全' と主張し注文ステートマシンに '支払い待ち→審査待ち:風控ハイスコア' 分岐を含むが、実際は到達不能
- 四線按分は一線のみ実装：webhook と SettlementCron は PlatformSettlements のみ作成；MerchantSettlements/SupplierSettlements/AffiliatePayouts はプロジェクト全体で ::create 書き込みなし（テーブル + admin CRUD のみ）、README と 08-multi-currency-settlement 図は '四線独立決算' を主張
- サブスクリプション周期購と AB テストにサーバーサイド API なし（テーブル + admin CRUD コントローラーのみ、route.php に対応ルートなし）；多プラットフォーム 'Amazon/eBay/Shopee/Lazada/Temu 商品刊登 + 注文集約' に実プラットフォーム統合なし、PlatformOrderSyncCron が汎用 URL で取得するのみ（PlatformListings に業務書き込みなし）；WebSocket IM 未実装だが VERSIONS.md は ✅ 標記（features.md/README は正直に標記済み）
- 在庫台帳の不変台帳が未着地：InventoryLogs に業務書き込みなし（注文時の在庫減算で台帳を記録しない）；CurrencyExchangeGainsLosses 為替損益テーブルにも書き込みロジックなし、README の主張する '在庫台帳(不変台帳)' と '為替損益追跡' はテーブル構造層に留まる
- シードデータがインストールに随伴しない：install.sql はテーブル構造と wa_ システムシード（wa_options/wa_roles）のみ、countries/currencies/payment_gateway_methods/hs_codes/shipping_zones などの基礎データは service/database/seeders/countries.php の手動実行が必要（InstallController は install.sql のみインポート）、新規インストール後は商品/決済方法/送料計算が開封即空；AUDIT-REPORT は 'データベースシードデータ OK' と標記
- hg/apidoc 動的ドキュメントが名ばかり：@Apidoc アノテーションは AuthController + ProductController のみ（59 行）、残り 36 コントローラーはゼロアノテーション、6 グループの自動ドキュメントカバレッジが著しく不足；かつ数値口径のズレあり（admin コントローラー実測 80 vs ドキュメント 82、HarmonyOS ソース 13 個 vs ドキュメント 14、翻訳 45 条 vs AUDIT のいう 48 条、features.md のミドルウェアパイプライ図に RateLimit/Encryption 欠落）

### リスク
- ドキュメントが体系的に '完全' 標記を誇張（多プラットフォーム、風控エンジン、サブスクリプション/AB、四線按分、インボイス PDF、Klarna/Adyen、WS 客服）、商業ライセンス顧客に機能納品期待の落差を生み、契約と信頼のリスク
- 新規インストール後は基礎データが空（シードが自動インポートされず、ウィザードも seeder を実行しない）、countries/currencies/payment_gateway_methods などのコアデータテーブルにデータなし、商品一覧、決済方法、送料/関税計算などの主チェーンが開封即利用不可
- 動的 API ドキュメントのカバレッジは 2/38 コントローラーのみ、Flutter/HarmonyOS クライアント連携に権威あるインターフェース根拠を欠く；docs/api.md 静的ドキュメントと route.php にエンドポイントドリフトのリスク（71 vs 73、かつ features.md 内部のパイプライン図も不一致）
- テストカバレッジは単体テスト 22 個のみ（セキュリティ + JWT + レスポンス + Redis）、38 業務コントローラーのテストゼロ、admin テストなし、統合テストとカバレッジレポートなし、一括リファクタリング/アップグレードの回帰リスクが高い
- DB の payment_gateway_methods に klarna/adyen などの未実装ゲートウェイ行が残り、設定が誤って有効化されるとフロントで表示されるが注文後にゲートウェイ処理がなく、決済チェーンに潜在的な失敗点

### 提案
- [高] 全ドキュメントで '実装済み/テーブル構造のみ/計画中' の三態標記を統一：features.md/VERSIONS.md/README の Klarna/Adyen、多プラットフォーム刊登、風控エンジン、サブスクリプション/AB、四線按分、インボイス PDF、WS 客服の状態を修正し、ドキュメントがコードに先行するのを根絶
- [高] インストールウィザード（admin/plugin/admin/app/controller/InstallController.php）に基礎シードデータの自動インポートを追加（countries/currencies/payment_gateway_methods/hs_codes/shipping_zones）、新規インストールが開封即利用可能であることを保証
- [高] コア業務クローズループを補完：RiskEngine 採点と注文状態 8 を実装、導入済みの dompdf で invoice/packing-list PDF 生成を実装（DocumentController をオンデマンド生成 + 入库に変更）、在庫減算で InventoryLogs を書き込み、webhook 後に Merchant/Affiliate 按分を補完
- [中] 全 73 ルートに @Apidoc アノテーションを補完し hg/apidoc 6 グループのドキュメント実カバレッジを回復；短期で完了できない場合は、まず README の apidoc 宣言を下げ、docs/api.md を権威ある静的ドキュメントと明確化
- [中] 統合テストを追加：CI 設定済みの MySQL/Redis サービスを利用し登録→ログイン→商品→カート→注文→決済 mock スモークチェーンを補い、admin のコア CRUD テストも補い、38 個のゼロカバレッジコントローラーの回帰防護を向上
- [中] 数値口径を修正：admin コントローラー 82→80、HarmonyOS ファイル数、翻訳キー数 48→45、features.md のミドルウェアパイプライン図を統一（RateLimit/Encryption を補完）し api.md エンドポイント一覧を整合（73 ルートに合わせる）
- [低] CurrencyExchangeGainsLosses（決算時為替レート比較）と PlatformListings（プラットフォーム刊登書き込み）に実業務ロジックを追加するか、'テーブル構造のみ' に変更；未実装の間は '完全' と宣言しない
- [低] route.php↔docs/api.md のエンドポイント整合性チェックスクリプトを確立し CI に組み込み、ドキュメントとコードのさらなるドリフトを自動で遮断
