# Erik Shop プロジェクト計画（チーム成果物）

> **生成時期**：2026-08
> **生成方法**：マルチエージェントチーム協働（7 領域並行調査 → システムアーキテクトが統合 → レビューエンジニアが再検証）
> **根拠**：`docs/PLAN-RESEARCH.md`（7 分野の調査明細）、`README.md`、各サブプロジェクトの `CLAUDE.md`
> **適用期間**：3-6 ヶ月（4 つのフェーズ）
> **レビュー記録**：2026-08 レビューエンジニアがコード照合で 18 項目の判断を再検証（16 項目正しい、2 項目はワークスペースで修正済みのため部分的に正しい）；本版はレビュー調整を反映済み（PosterVerify 発行インターフェース、風控審査の出口、Flutter のパス、実装状態の注記など）

## 〇、現在の実装状態（レビュー時の照合）

> `git status`/`git diff` の実測に基づく照合；✅=完了（ワークスペース未コミット）、🔄=進行中、⬜=未開始。

| 項目 | 状態 | 説明 |
|---|---|---|
| admin の致命的コントローラー署名修正 2 件（ShopOrder/ShopPayment に `: array`/`: Response` を追加） | ✅ | 修正後 82/82 コントローラーのリフレクションロード成功（修正前は 2 件 Fatal） |
| PHPStan ゲート | ✅ | `make check` 実測で `[OK] No errors`；PHPStan 2.2.8 は neon の `memoryLimit` パラメータを削除済みのため、Makefile/CI で `--memory-limit=1G` を渡す方式に変更 |
| ShopDashboardController の json シグネチャ + ビュー fetch URL | ✅ | `$this->json(0,'ok',$data)` + `/ShopDashboard/kpi` のクラス名ルーティング |
| CI に composer audit + phpstan を追加 | ✅ | `.github/workflows/ci.yml` に 2 ステップ追加（YAML 検証済み） |
| `scripts/smoke_controllers.php` 再発防止スモーク | 🔄 | フェーズ一の成果物を参照 |
| PosterVerify 発行インターフェース（`POST /api/poster/verify`） | ✅ | レビューで新発見 + 実装済み：`PosterController`（math 計算問題）+ ルート；8789 ポートで全チェーン実測通過（challenge→verify→ミドルウェア通過→一回限り消費） |
| 🔄 新発見 P0：Encryptable の空 IV が登録をブロック | ✅ | 修正済み：`app/common/SecureEncrypter.php`（明示的な 16 バイトゼロ IV、旧データとバイトレベル互換）+ `support/bootstrap.php` で resolver 登録；登録成功・ログイン復号を実測 |
| 🔄 新発見 P0：暗号化フィールドが検索不可（email） | ✅ | 修正済み：`erik_users.email_hash`（HMAC-SHA256 インデックス列、install.sql + ALTER + バックフィル）；AuthController register/login と SocialAuthController を email_hash クエリに変更；実測：登録成功/重複登録 422/ログイン成功/誤パスワード 401 |
| 🔄 新発見 P0：HASHIDS_SALT プレースホルダー/未読み込み | ✅ | 修正済み：`config/hashids.php` main.salt が `getenv('HASHIDS_SALT')` を読む；本環境の `.env` にランダム salt を生成（元は change_me プレースホルダーで、設定に空 salt がハードコードされ fail-closed 例外を起こしていた） |
| Quick Win #3：業務シードデータの自動インポート | ✅ | `service/database/seeders/run.php` を新設（冪等：countries 23 + logistics 3 + shipping zones 3 + rates 3 + gateways 2 + methods 2 + hs_codes 8 + tariff_rules 7）；再実行で 0 追加 51 スキップを実測；/api/countries、/api/payment/methods、/api/shipping/calculate（北美区 DHL 12.24）、/api/tariff/estimate すべて利用可能 |
| 🔄 新発見：モデルの誤った encryptable（name 等の非機密フィールド） | ✅ | 30+ モデルが name 等の公開フィールドを暗号化：名前での検索/ソートを壊し、短フィールドに暗号文が収まらない。全件クリーンアップ済み：シード関連 4 モデル（前ラウンド）+ 一括 17 モデル（Categories/Currencies/Shops/Suppliers.name/Merchants.store_name 等）、email/mobile/real_name/api_key/access_token 等の真に機密なフィールドは維持 |
| 🔄 新発見：モデルの Eloquent 関連付け欠落 | ✅ | PaymentGatewayMethods.gateway、ShippingZoneRates.logistics/zone の欠落で /api/payment/methods、/api/shipping/calculate が 500 になる問題を修正済み |
| フェーズ一：OrderController の実計費 | ✅ | store() にクーポン（満額減額/割引/固定、user_coupons + used_qty の核銷）、送料（分区+料率段階の最安値）、関税/VAT（HS Code→目的国税率）を接続；実測 3×49.99=149.97 満100減20 → discount 20 + shipping 12.24 + tax 0 = pay 142.21、在庫/核銷/明細/ログの全チェーン検証 |
| 🔄 新発見 P0：HashidsDecode のパラメータ欠落 | ✅ | ミドルウェアの setPost($updates) が全体置換で、いずれかの _id フィールドをデコードすると同一リクエストの他パラメータ（coupon_id/weight_grams 等、サイト全体に影響）が失われる問題を array_merge に変更して修正、多パラメータ注文の正常動作を実測 |
| 🔄 新発見：注文チェーンに付随するバグ | ✅ | CouponController::claim の where 列名が誤って値になっていた（whereColumn に修正）；Orders.address_snapshot JSON 列に cast 欠落（array cast を追加）；OrderLogs テーブルに updated_at なし（モデルで $timestamps=false） |
| フェーズ一：InstallController に seeder 統合 | ✅ | インストールウィザードが install.sql インポート後に service/database/seeders/run.php を自動実行（独立子プロセスで autoload を分離、失敗時は警告のみ）；あわせて install.sql パスのバグを修正（元の base_path(false) が admin/ を指してルートの install.sql を見つけられなかった → dirname に変更） |
| フェーズ一：鴻蒙の注文・決済接続 | ✅ | Checkout.ets に住所選択、PosterVerify（challenge→verify）、完全な注文パラメータ + X-Poster-Token、決済発起（payment/create）を接続；ApiClient を headers/パラメータ拡張；**hvigor assembleHap コンパイル通過** |
| フェーズ一：Flutter の注文・決済接続 | ⚠️ コード済み・コンパイル検証待ち | checkout_screen に住所/人機認証/完全注文/決済発起を接続；register_screen に PosterVerify を接続；api_client post が headers をサポート。**本環境の flutter SDK キャッシュは読み取り専用でコンパイル不可**、ローカルの `flutter analyze`/`flutter test` で検証が必要（括弧/構造の静的チェックは通過済み） |
| フェーズ二 P1：風控エンジン RiskEngine | ✅ | `app/common/RiskEngine.php` を新設（email_domain 使い捨てメール/velocity 頻度/amount 高額/address_mismatch/ip_reputation、Redis カウント）；注文/登録/決済にバイパス採点 + RiskLogs を接続；**実測**：使い捨てメール+高額注文 → 80 点 review → 注文 status=8 審査待ち、risk_score/risk_result/OrderLog の風控マーカー完備 |
| フェーズ二 P1：風控審査の出口 | ✅ | `POST /api/admin/orders/{id}/review` を新設（AdminKeyMiddleware；approve→0 通過/reject→5 却下、status=8 のアトミック遷移 + OrderLogs）；**実測** approve/reject/誤キー 403/重複審査 422 すべて正しい |
| フェーズ二 P1：KYC ユーザー側クローズループ | ✅ | KycController を新設（POST /api/kyc 提出 + GET /api/kyc/status 照会、real_name/id_number を Encryptable 暗号化、status 0審査待ち/1通過/2却下）；**実測**提出/照会が正常 |
| フェーズ二 P1：決済強化 | ✅ | StripeGateway に明示的な `request_three_d_secure=automatic`（3DS）；Klarna/Adyen は `PaymentGateway::make` throw のプレースホルダーを維持（ドキュメント修正待ち） |
| 🔴 **新発見のグローバルバグ：HashidsDecode のルートパラメータデコードが無効** | ✅ | webman のコントローラーメソッドパラメータは findRoute が捕捉した元の hashid 由来（ミドルウェアの setParams が効かない）。統一的に修正：`BaseApiController::decodedId()` ヘルパー + 17 箇所の {id} ルートメソッド入口に接続（注文/商品/カート/住所/ウィッシュリスト/レビュー/決済状態/返品/クーポン/通知/比較/返金実行/審査）；**実測**注文詳細、商品詳細、注文キャンセル、カート update/destroy、クーポン受取（hashid パス）すべて通過；あわせて Orders の items/logs/documents リレーション、Carts の sku リレーション欠落を修正 |
| フェーズ二 P1：統一按分料率 + セラー按分 | ✅ | SettlementCron の料率源を `payment.gateway_fee.{gateway}` + `payment.platform_rate` に統一（webhook と同源、cron.* は互換フォールバックのみ）；MerchantSettlements 書き込みを新設（order_items→MerchantProducts approved→merchant.commission_rate）；**実測**：162.21 注文 → プラットフォーム 5% 手数料 8.11 + stripe ゲートウェイ費 5.00、セラー 149.97@8% → 手数料 12.00 決済額 137.97 |
| フェーズ二 P1：四線按分の補完（Supplier/Affiliate） | ✅ | schema 補完：`erik_products.supplier_id` + `erik_orders.affiliate_link_id`（install.sql + ALTER）；SettlementCron にサプライヤー周期決済（月次 upsert SupplierSettlements）+ 販売代理店コミッション（AffiliateCommissions + AffiliateLinks カウント）を追加；**実測**：商品 99.98 がサプライヤーの当月決済に計上、112.22@10% → 販売代理店コミッション 11.22 かつリンクの orders/commission 更新；AffiliateCommissions テーブルに updated_at がないため `$timestamps=false` を追加 |
| フェーズ二 P1：InstallController の二重ソーステーブル一覧検証 | ✅ | `scripts/check_install_tables.php` を新設（install.sql のテーブル名 vs InstallController の $tables_to_install を解析、wa_ プラグインテーブルは除外）、Makefile check に接続；**実測** 110 vs 110 一致 OK |
| フェーズ二 P1：GDPR/CCPA 実行層 | ✅ | `PrivacyComplianceTask` を新設（毎時）：data_delete は猶予期間後にユーザーを匿名化（email/email_hash/mobile をクリア、ニックネーム「已注销用户」、status=0、税務フィールドは保持）、data_access/data_portability はエクスポート JSON を生成、opt_out はマーク；`POST /api/privacy/cookie-consent` を新設（CookieConsents 書き込み、version/preferences JSON）；**実測**：31 日前の data_delete リクエスト → ユーザー匿名化 + リクエスト completed；cookie-consent 記録は完全 |
| フェーズ二 P1：Klarna/Adyen のドキュメント修正 | ✅ | README.md（決済行/元通貨引き落とし/機能表）と docs/VERSIONS.md で Klarna/Adyen/BNPL をプレースホルダーと明記し、実際の `PaymentGateway::make` throw と一致 |
| フェーズ四 P2：在庫台帳の不変台帳 | ✅ | `InventoryLogger` を注文時減算(outbound)/キャンセル時回復(inbound)に接続し、erik_inventory_logs（balance_after スナップショット）に書き込み；**実測**注文 -2/キャンセル +2 の台帳が完全 |
| フェーズ四 P2：商業インボイス/梱包明細 PDF | ✅ | DocumentController を書き換え：dompdf で PDF をオンデマンド生成（明細+金額+税関申告）し public/documents/ + erik_order_documents に保存（冪等）；パラメータ名とルート {id} の不一致を修正；**実測**両 PDF の生成成功 |
| フェーズ三 P1：admin 品質ゲート | ✅ | admin/phpunit.xml + tests/UtilTest.php（2/7 通過）、phpstan.neon（level 5）、.php-cs-fixer.php（fix ハングを修正）、composer に phpstan 追加、CI に admin ステップ、Makefile test は両プロジェクト |
| フェーズ四 P2：DB 読み書き分離 | ✅ | 純クエリの 6 モデルが `$connection='mysql_rw'` を有効化（Eloquent の自動読み書き分流 + sticky）；**実測**クエリ接続=mysql_rw、書き込みは正常；本番は DB_READ_HOST_1/2 で有効化 |
| フェーズ四 P2：サブスクリプション周期購 API | ✅ | SubscriptionController（サブスクリプション作成+初回注文、マイサブスクリプション、キャンセル）；**実測**作成/一覧/キャンセルすべて通過；SubscriptionOrders/Logs に `$timestamps=false` を追加 |
| フェーズ四 P2：多プラットフォーム刊登書き込み | ✅ | `POST /api/admin/platform/listings`（AdminKeyMiddleware、PlatformListings の draft/listed upsert）；**実測**刊登レコードの書き込み成功 |
| フェーズ四 P2：SubscriptionCron 自動更新 | ✅ | `service/app/process/SubscriptionCron.php`（毎日）：期限切れサブスクリプション→トランザクションで更新注文生成/周期数+1→next_billing 更新→ログ；SKU 出品停止/在庫不足は paused に；**実測**スモーク 7 アサーション全通過 |
| フェーズ四 P2：WS 客服リアルタイム IM | ✅ | `ChatController`（REST セッション/メッセージ）+ `ChatWs`（WebSocket 8788、JWT+セッション帰属認証、二チャネル同源書き込み）；**実測**エンドツーエンド 5 項目（握手/ブロードキャスト/入库/不正 token/他人セッション拒否）；既知：客服側認証なし、セッションクローズ動作未実装 |
| フェーズ四 P2：ES 多言語検索 | ✅ | webman-scout hosts を `ELASTICSEARCH_HOST` env に変更；Products の `toSearchableArray()` 多言語フィールド + `scripts/es-index-products.php` 一括インデクサー；未設定時は SQL にフォールバック；**実測**フォールバック経路/データ形状（ES サービスなしのため、オンラインクエリは未実測） |
| フェーズ四 P2：Klarna/Adyen 決済スケルトン | ✅ | `KlarnaGateway/AdyenGateway`（Guzzle 直結：作成/照会/返金/Webhook HMAC 署名検証）、キー欠落時は例外で env を明示；`PaymentGatewayInterface` を分離；**実測**署名検証アルゴリズムの双方向 + phpstan/phpunit 全通過；実キー接続後にのみ利用可能 |
| フェーズ四 P2：cron 3 URL の env 化 | ✅ | `config/cron.php` の 3 つの *_url を env 読み込みに変更（TRACKING/COMPLIANCE/PLATFORM_URL）；3 つの cron 取得ロジックは完全；実外部 API には未接続 |
| フェーズ四 P2：鴻蒙 KeyStore + クライアント AES + 決済完了ページ | ✅ | 鴻蒙 `SecureStore.ets`（Asset Kit で preferences を置換）+ Flutter/鴻蒙 `SecureCrypto.ets`/`_SecureCrypto`（AES-256-CBC、X-Encrypted/X-Encrypt-Response、キー空なら平文）+ 両端の決済完了ページ；**未コンパイル検証**（ツールチェーンなし）、`flutter pub get`/hvigor コンパイル待ち |
| ドキュメント収束 | ✅ | README/VERSIONS/admin-CLAUDE.md の過剰宣言 8 項目を修正（HS 申告→計画中、注文エクスポート列を実装に合わせる、i18n 切替ボタン→計画中など）；梱包明細/追跡は実装済みと確認して維持；VERSIONS.md の 7 項目（ABテスト/購買/品質検査/調撥/保険/知識庫/ポイント）を「テーブル構造のみ」（◐）と明記し、コード実態（テーブル+モデルのみ、業務コードなし）と一致 |
| 第二ラウンド：JWT 失効 + パスワードリセット + メール検証 | ✅ | Jwt に `revoke()`/`isRevoked()`（Redis ブラックリスト）を追加、JwtAuth ミドルウェアで検証；AuthController logout/changePassword/passwordReset/emailVerify + ルート；install.sql に `email_verified_at` を追加；JwtTest 単体テスト通過 |
| 第二ラウンド：部分返金 + webhook イベント補完 | ✅ | RefundHelper が部分返金額の検証をサポート；AdminOpsController::executeRefund；PaymentController の webhook イベントディスパッチ（refunded/failed）；RefundHelperTest 通過 |
| 第二ラウンド：DevOps 収束 | ✅ | docker-compose のポートを 127.0.0.1 に収束、.dockerignore×2、.gitignore に鴻蒙ビルド成果物、CI に Flutter/hvigor jobs、download-geoip.php スクリプト |
| 第二ラウンド：統合テスト + admin P0 UI | ✅ | IntegrationTestCase（MySQL 利用可否でスキップ + デフォルトテスト DB をテストケースごとにクリア）+ OrderFlow/StripeWebhook/Hashids テスト（phpunit 40 tests / 155 assertions 全緑）；ShopOrder/ShopPayment モデル初期化を修正；admin の注文/決済 LayUI 一覧ページ |
| 🔴 新発見バグ：webhook 按分書き込みが NOT NULL 列にブロックされる | ✅ | PaymentController::handlePaymentSucceeded の PlatformSettlements::create に supplier_amount/affiliate_amount が欠落（schema の NOT NULL デフォルトなし→webhook が恒常 500）；max(0, 総額-プラットフォーム費-ゲートウェイ費) の計算を追加（SettlementCron と同源）；StripeWebhook 統合テスト 5/5 通過 |
| 第三ラウンド：返金申請クローズループ | ✅ | RefundController（POST /api/refunds 申請 + 一覧/詳細、返金可能残高=実支払額-返金済み-審査中）+ AdminOps approve（0→3 アトミックガード + RefundHelper 連動）/reject（0→2）；Refunds の status 意味を schema に合わせる：0審査待ち/2却下/3返金済み；RefundFlow 統合テスト 3/34 |
| 第三ラウンド：WS 客服の補完 | ✅ | ChatWs の客服側認証（初フレーム {type:'auth',role:'agent',key} + hash_equals 定数時間比較、握手 pending ロール）+ セッションクローズ（REST close/adminClose + WS close フレーム、closed は REST 409/WS error で遮断、closeSession 冪等 + ブロードキャスト）；ChatWs テスト 5/21 |
| 第三ラウンド：admin コア管理ページ | ✅ | 商品/ユーザー/返金/クーポン/カテゴリの 5 ページ（LayUI で order/payment に合わせる、一覧+ページング+検索+状態フィルタ+審査モーダル）；Crud.php の根因修正 3 箇所（doFormat items() を Collection に包み直し ShopOrder/ShopReturn 同型の潜在バグをカバー、string モデルインスタンス化、ビューパス推論）+ ShopProduct afterQuery の在庫集約；ShopUserController を新設 |
| 第三ラウンド：QA の定着 | ✅ | SubscriptionCron（更新注文/billing_cycle+1/next_billing 順延/在庫不足と出品停止は paused）+ ES フォールバック（SQL LIKE + SearchLogs 記録）テスト；🔴 新発見修正：SearchLogs に $timestamps=false 欠落 → 検索ログ書き込みで SQLSTATE 1054 500；全セット 54 tests / 256 assertions 0 失敗 |
| 第四ラウンド：入力境界修正 | ✅ | BaseApiController::clampPage（page≥1 / perPage∈[1,50]）で 8 コントローラーを統一（Order/B2b/PriceAlert/Affiliate/Privacy/Notification/Return/Review、Search は fix-search で単独修正）；AdminOps の reason/remark ≤500 + createListing intval；json_decode の空値フォールバック 5 箇所（SocialAuth×3/ExchangeRateCron/ComplianceCron）；真に未使用のインポート 4 つを削除（監査列の残り 11 個は grep で使用を確認） |
| 第四ラウンド：検索インジェクション防護 | ✅ | SearchController：Lucene 特殊文字を preg_replace でエスケープ（ES 構文インジェクション DoS 防止）+ keyword >64 → 422 + LIKE の `%`/`_` を addcslashes + per_page クランプ；24 行 diff |
| 第四ラウンド：DevOps 衛生 | ✅ | admin composer.lock を同期（phpstan 入库）+ service `--lock` 更新；ci.yml の audit を「CVE-2025-45769 のみ通過」の堅牢版に変更（終了コード保持、実測で出力形式一致）+ workflow_dispatch；autoload の `""` 空プレフィックス ×2 を削除し明示プレフィックス 5 つを追加（dump-autoload で検証）；Copyright ヘッダー 35 個を補完；LICENSE を proprietary と明記（webman の MIT 原文は維持）；dockerignore に tests/docs を追加；compose のプレースホルダーキーガード（production + change_me → exit 1、実測三分岐）；**スキップ**：cs-fixer CI ステップ（238/247 ファイルが非準拠、フォーマットコミットが必要）と admin audit（25 件の既存警告、依存アップグレードが必要） |
| 第四ラウンド：ドキュメント/インデックス整合性 | ✅ | VERSIONS の虚標 ✅→◐ 7 項目（実測はテーブル+モデルのみ）+ 規模表（Cron 11、ツールクラス 15、テスト 54/256）；api.md に DELETE /api/comparisons/{id} を追加；payment.php に adyen 料率 2.99/0.30 を追加；install.sql に 6 インデックスを追加（refunds/return_orders idx_user_id、platform_listings idx_account_product、group_buys/flash_sales/coupons idx_status_time）+ scripts/index-fixes.sql（未実行、既存 DB 用）；🔴 残タスク：service/CLAUDE.md のツールクラス 8→15、PHPUnit 22→54 のカウントが期限切れ |
| 第四ラウンド：セキュリティ強化 | ✅ | BaseModel の `$guarded=['id','money','score','level','created_at','updated_at']`（監査の元リストには user_id/status 等 6 列もあったが、grep で 40+ 箇所の create() 一括代入を確認→封鎖するとデータ破損になるため、最小破壊リストで実行）；admin 5 ページの table.render に `escape: true` を追加；UploadController のブラックリスト→19 種拡張子ホワイトリストに変更；InstallController の二重検証（設定ファイル + wa_options installed=1 マーク、DB 到達不可時は fail-closed）；🔴 既存バグも報告：product/index.html の在庫列 templet が return なしで undefined 表示 |
| 第四ラウンド：テスト補強 | ✅ | SubscriptionController 4/33（周期検証/越権/キャンセル冪等）+ Kyc 6/27（Encryptable 復号復元/却下後再提出/通過後提出禁止）+ RiskEngine 6/22（使い捨てメール/高額/住所不一致/velocity/ip_reputation）統合テスト；Kyc テストのメソッド名を変更して PHPUnit 12 の final status() オーバーライドによる致命的エラーを回避；全セット 70 tests / 338 assertions 0 失敗（既存の vendor warning 1 件：encryptable の空 IV）|
| 第五ラウンド：並行ロック基盤 | ✅ | app/common/DistributedLock.php を新設（Redis SET NX EX スピンロック、Lua で自持ロックのみアトミック解放、fail-closed：Redis 例外時も無防備にしない；単機/分散で同一経路）；webman/redis-queue v2.1.1 を接続（db=2 prefix=erik_queue:、消費プロセス count=8、consumer_dir=app/queue/redis）；コンポーネント検証スクリプト 5 項目すべて通過（二プロセス競合/タイムアウト/誤削除防止） |
| 第五ラウンド：書き込み操作のロック | ✅ | 注文重複防止 lock:order:{userId}（OrderController store のトランザクション全体をロック、ロックタイムアウト 429/業務例外 422）；決済冪等 lock:payment:{orderId}（ロック内で待支払いレコードを検索し命中時は返却、重複待支払い防止）；返金申請 lock:refund:{orderId}（ロック内で注文+返金可能残高を再検索、並行過剰申請防止）；サブスクリプション store/cancel、住所 is_default の先クリア後設定、ソーシャルログイン紐付け、お気に入り、カート追加の読み書き更新、レビュー（一意インデックスなし、ロックが唯一の防衛線）、登録（email_hash 非 UNIQUE）をそれぞれのシーンで補完；B2b 見積もりは純追記判定のためロック不要 |
| 第五ラウンド：PDF 生成の非同期化 | ✅ | DocumentController をキュー投入に変更し即時 processing を返却；DocumentPdfConsumer（app/queue/redis/、キュー document_pdf、payload order_id/type/user_id、消費内で元の dompdf ロジックを丸ごと移設、冪等入库、失敗はログ記録のみで再試行なし——ユーザーの再リクエストが自然な再試行になる）；状態判定：レコード存在かつファイル存在=done、それ以外は processing |
| その他の成果物 | ⬜ | 残り：実決済 SDK のオンライン接続（キー必要）、ES オンライン検証（ES サービスなし）、Flutter/鴻蒙コンパイル検証（ツールチェーンなし）、鴻蒙セキュアストレージの実機検証、cs-fixer フォーマットコミット後の CI ステップ追加、admin 依存アップグレード後の audit ステップ追加、PDF 非同期のエンドツーエンド検証（キュー処理プロセスの実行が必要） |

---

## 一、全体判断

Erik Shop のインフラ骨格は堅実（117 テーブル、39 コントローラー、Stripe/PayPal 実ゲートウェイ、WAF/JWT/AES セキュリティスタック、22 単体テスト全通過）ですが、コア取引のメインチェーンが service/admin/Flutter/鴻蒙の四端で同時に断裂し、約十数項目の「完全」と謳うドキュメントが実はテーブル構造か CRUD スタブであり、品質ゲート（PHPStan/統合テスト/クライアント CI）は形骸化しています——全体として**「骨格は完全、クローズループ欠落、ドキュメント先行」**の段階です。3-6 ヶ月以内にまず止血して取引クローズループを開通し、次にコンプライアンスと品質基盤を補い、最後に増分能力を拡張してドキュメントを収束させます。

## 二、五大グローバル問題

1. **コア取引メインチェーンが三端で同時断裂**（サーバーサイド/Admin/双クライアントの交差裏付け）：service 端の `OrderController::store` がクーポン/送料/関税/風控を計算しない（商品小計を足すだけ）；Flutter と鴻蒙の注文は両方とも `address_id` が欠落し PosterVerify 40001 で拒否され、決済は一度も `POST /payment/create` を呼んでいない；admin 端の `ShopOrderController`/`ShopPaymentController` は PHP 8.3 のメソッドシグネチャ非互換でクラスロード時に Fatal。現状のままリリースすれば購入変換の全経路が使えず、注文/決済管理メニューを開いた瞬間にクラッシュします。
2. **ドキュメントが体系的にコードに先行**（ドキュメント/サーバーサイド/セキュリティ/コンプライアンスの四領域で一致確認）：`features.md`/`VERSIONS.md`/`README` は風控エンジン(RiskEngine)、Klarna/Adyen 決済、四線按分、商業インボイス PDF、サブスクリプション周期購/AB テスト、WebSocket 客服 IM、多プラットフォーム商品刊登をすべて「完全/✅」と標記していますが、実際はテーブル構造 + admin CRUD か業務実装ゼロで、商業顧客への納品期待と信頼のリスクになります。
3. **業務シードデータ欠落 + セキュリティ/コンプライアンス実行層が空白**（サーバーサイド/デプロイ/コンプライアンスの三領域で一致確認）：`install.sql` はシステムテーブルのシードのみで、countries/currencies/payment_gateway_methods/hs_codes/shipping_zones は新規インストール後すべて空（コアインターフェースが開封即空を返す）；同時に `blocked_countries` のデフォルト空配列、風控ゼロ呼び出し、KYC 提出入口なし、GDPR/CCPA は登記のみで実行しない——「開封即空 + デフォルト通過」にコンプライアンス宣言の不実が重なります。
4. **Admin バックエンドの業務層が「コントローラーありページなし」**：59/67 が純 CRUD スタブで HTML ビューなし、メニュークリックで 404；越境パネルの kpi/chartData ルートと json シグネチャが二重に壊れている；40 コントローラーがメニュー未登録で、モール管理 UI 全体が実質使えず、ドキュメントの謳う「完全な管理バックエンド」と大きく乖離。
5. **品質ゲートが名ばかり**（テスト/デプロイ/ドキュメントの三領域で一致確認）：単体テストは 22 個だけで 4 つのツールクラスをカバー、業務コントローラー/ミドルウェア/モデルのテストはゼロ；PHPStan はデフォルト 128M で開封即クラッシュ、admin は品質設定なし；CI に phpstan/php-cs-fixer/composer audit ステップなし、Flutter/HarmonyOS job なし；鴻蒙のビルド成果物 99 個が誤ってコミットされ、いかなるリファクタリング統合も無防備。

## 三、フェーズ別ロードマップ

### フェーズ一：止血と取引メインチェーン開通 — **P0 · 第 1-4 週**

**目標**
- admin の致命的コントローラー 2 つを修正し再発防止スモークを確立、注文/決済管理メニューの利用可能性を回復
- service の注文実計費（クーポン/送料/関税/割引の入库）を開通し決済冪等を追加、バックエンドの注文チェーンをクローズ
- 業務シードデータの自動インポートを補完し、新規インストールのコアインターフェースが開封即有データになることを保証
- Flutter と鴻蒙のチェックアウト-注文-決済チェーン（address_id + PosterVerify + payment create/status）を開通

**成果物**
- ✅ 完了済み：`admin/plugin/admin/app/controller/shop/ShopOrderController.php` と `ShopPaymentController.php` に `: array`/`: Response` の戻り値型を追加（82/82 リフレクションロード通過）；**残り**：`scripts/smoke_controllers.php`（php -l + 全 82 コントローラーのリフレクションロード）を新設し Makefile check と CI に接続、再発防止ゲートとして
- 🔄 **レビュー追加（高優先）**：PosterVerify 発行インターフェース `POST /api/poster/verify` —— ミドルウェアは Redis キー `erik:poster:{token}` を検証するが、プロジェクト全体に発行/書き込みコードがなく、クライアントは X-Poster-Token を取得できない；poster-php で検証コードを生成し Redis キーに書き込む（有効期限と一回限り消費を含む）必要がある。これは Flutter/鴻蒙の登録、注文、決済に人機認証を接続するための**前提依存**
- `service/app/controller/v1/OrderController.php` store() に coupon 割引計算と shipping_fee/tax_amount/discount_amount の入库を接続（api.md 5.3 / features.md 3.3 に合わせる）、api.md 2.1 の min_price/max_price フィルタを実装；`PaymentController::create` に order_id+gateway の冪等重複排除を追加
- `admin/plugin/admin/app/controller/InstallController.php` step1 末尾で `service/database/seeders/countries.php` を追加実行し、erik_payment_gateway_methods（stripe/paypal 各 method 行）、erik_hs_codes 基礎庫、erik_tariff_rules/erik_shipping_zones のサンプルシードを新設
- `apps/flutter/lib/features/order/checkout_screen.dart`（**注意：実際のパス、lib/screens/ ではない**）に住所選択とデフォルト住所のフォールバック、address_id+currency_code の提出、PosterVerify（X-Poster-Token）接続後に `POST /payment/create` + `GET /payment/status` ポーリングの決済ページを実装；`apps/harmonyos/entry/src/main/ets/pages/Checkout.ets` にも address_id + selectedShipping + currency_code と決済呼び出しを同期追加（鴻蒙は住所管理ページが新規必要、Profile の受取住所 route は現在空）
- ✅ 完了済み：`ShopDashboardController.php` の kpi/chartData ルート（kebab→クラス名の完全一致）と `$this->json` シグネチャ競合を修正し、ハードコードされたサンプルデータを置換
- service の注文/決済/返金コアインターフェースに統合テストを追加（トランザクション/在庫減算/キャンセル、webhook 署名検証+冪等+按分、Hashids エンコード/デコード）、CI が起動済みの MySQL/Redis サービスを再利用
- ついでに：`docs/deployment.md` の admin ポート 8787→8788 の誤記 2 箇所を修正

**担当ロール**：バックエンドフルスタック、バックエンドエンジニア、決済決算、Flutter、鴻蒙、QA

### フェーズ二：コンプライアンスクローズループと決済決算の拡張 — **P1 · 第 5-10 週**

**目標**
- 風控ルールエンジンを実装し注文ステートマシンの「審査待ち(8)」と接続、「風控なしで注文が通過する」露出を解消
- KYC のユーザー側提出クローズループと GDPR/CCPA 実行層（削除/エクスポート/opt-out）を補完
- 按分料率源を統一し四線按分（Merchant/Supplier/Affiliate 書き込み）を補完
- 決済方法の宣言を収束：Klarna/Adyen を実装するか明示的にプレースホルダーとしドキュメントを同期修正、3DS の明示コードを追加

**成果物**
- `service/app/common/RiskEngine.php` を新設（config/risk.php の checks/velocity に従って score を実装）、OrderController::store / PaymentController::create / AuthController でバイパス採点し、erik_orders.risk_score/risk_result と RiskLogs に書き込み、ハイスコアは status=8 に；ShopRiskRule/ShopRiskLog を admin メニューに登録
- 🔄 **レビュー追加**：風控審査の出口 `POST /api/admin/orders/{id}/review`（AdminKeyMiddleware 保護、status=8 のアトミックガードで 1 通過/5 却下に遷移し OrderLogs を書き込み）——現在サーバーサイドに status=8 の書き込み/遷移経路がなく、メニューだけ登録してインターフェースを接続しなければ「審査待ち」は依然として行き止まり；admin 側の ShopOrder 一覧に審査操作を連携
- `service/config/route.php` に `POST /api/kyc` と `GET /api/kyc/status` を追加（real_name/id_number は Encryptable 経由）、admin 審査通過で status=1 とし OrderController の既存検証と接続（admin の KYC 審査入口を明確化）
- `service/app/task/PrivacyComplianceTask` を新設（config/privacy.php に従ってデータ削除の猶予期間/データエクスポートファイル/opt_out マスクマークを実行）+ `POST /api/privacy/cookie-consent` で erik_cookie_consents に書き込み
- webhook と SettlementCron を単一の料率設定ソースに統合（gateway_fee の二重ソースドリフト解消）、MerchantSettlements/SupplierSettlements/AffiliateCommissions の書き込みと支払いフローを補完し docs/08-multi-currency-settlement を支える
- **Klarna/Adyen のデフォルト対応**：まず「明示的 throw プレースホルダー + api.md 6.1 / README / VERSIONS の表現修正」（低コスト、当日完了）；完全実装（サンドボックス決済成功 + webhook 署名検証 + 返金検収を含む）はフェーズ四に降格；`StripeGateway::createPayment` に明示的な `request_three_d_secure='automatic'` を設定し erik_payments.three_ds_status に書き戻し

**担当ロール**：セキュリティコンプライアンス、決済決算、バックエンドエンジニア、バックエンドフルスタック、越境 i18n

### フェーズ三：品質ゲートとバックエンド UI 補完 — **P1/P2 · 第 11-18 週**

**目標**
- 静的解析ゲート（PHPStan メモリ制限）を修正し、admin に一揃いの品質設定とテスト骨格を追加
- PHPUnit/phpstan/php-cs-fixer/composer audit/Flutter と鴻蒙 CI をすべてゲートに組み込み
- モール管理の P0 モジュールに LayUI 一覧ページを追加するか 404 メニューを整理し、「JSON API only」の位置づけを明確化
- デプロイと実行時の露出面（ポートバインド、ソースボリュームマウント、GeoIP データ、dev 依存）を修正

**成果物**
- ✅ 完了済みの service 側：phpstan コマンドに `--memory-limit=1G`（Makefile/CI、PHPStan 2.x は neon の memoryLimit パラメータを削除済み）；**残り**：admin/phpstan.neon（level 5）+ admin/.php-cs-fixer.php + admin/phpunit.xml + admin/tests/（優先的に Crud 基底クラスの inputFilter/doSelect/データ権限、AccessControl 認証、ShopRefundController のモック遠隔返金をカバー）
- ✅ 完了済み：ci.yml に composer audit + phpstan を追加；**残り**：php-cs-fixer --dry-run、service 統合テスト（MySQL/Redis サービス直結）、Flutter analyze+test job と鴻蒙 hvigor ビルド job
- `admin/plugin/admin/app/controller/shop/` の UI 補完は**優先度マトリクス**で実行：P0（注文/返金/発送/決済）は index() と view/shop/ 配下の index.html（LayUI 一覧）を必須で追加；その他のメニュー項目はデフォルトで config/menu.php から削除し「JSON API only」と明記（削除すれば 404 をゼロコストで解消）、ページ追加は今後のオンデマンド増分とし、宙に浮いた半製品を避ける
- 🔄 レビュー追加：鴻蒙リポジトリ治理（.gitignore に `apps/harmonyos/**/build`、`**/.hvigor`、`**/oh_modules` を追加し `git rm --cached` で入库済みのビルド成果物 99 個をクリーンアップ；hvigorw wrapper を補完）——これは CI に鴻蒙ビルド job を接続する前提
- 🔄 レビュー追加：install.sql と InstallController の `$tables_to_install` の衝突テーブル一覧を二重ソースでメンテする検証スクリプト（install.sql の CREATE TABLE を解析して動的生成、または両者の一致を比較）
- `docker-compose.yml` で ES/Redis/MySQL のポートバインドを 127.0.0.1 に変更（nginx のみ 80/443 を公開）、`./service:/app` と `./admin:/app` のソースボリュームマウントを削除し、service/.dockerignore と admin/.dockerignore を新設（vendor/runtime/.git を除外）、コンテナが --no-dev vendor で実行されることを保証
- GeoLite2-Country.mmdb のダウンロードスクリプトを追加（または MAXMIND_LICENSE_KEY の自動更新を有効化）し service/database/geoip/ に配置；config/cron.php の 3 つの空 URL ログを WARNING に昇格し目立つコメントを追加

**担当ロール**：QA、DevOps、バックエンドフルスタック、Flutter、鴻蒙

### フェーズ四：増分能力とドキュメント収束 — **P2 · 第 19-26 週**

**目標**
- ドキュメントで「完全」と標記されているが実際には欠落している増分能力（インボイス PDF、在庫台帳、多プラットフォーム刊登、サブスクリプション周期購）を実装
- 読み書き分離、多通貨決算クローズループ、ES 多言語検索の強化を有効化
- ドキュメントの三態標記（実装済み/テーブル構造のみ/計画中）を統一しエンドポイント整合性チェックを確立、さらなるドリフトを防止

**成果物**
- `service/app/controller/v1/DocumentController.php` で導入済みの barryvdh/laravel-dompdf を使い商業インボイス/梱包明細 PDF をオンデマンド生成し erik_order_documents に保存；OrderController の在庫減算時に erik_inventory_logs の不変台帳を書き込み
- PlatformOrderSyncCron に amazon/eBay/Shopee アダプターを追加し商品刊登を PlatformListings に書き込み；サブスクリプション周期購 API（erik_subscriptions はテーブル作成済み、まず最小業務範囲を定義：サブスクリプション請求周期 + キャンセル + 更新）と WebSocket 客服サーバーサイド（ChatSessions/ChatMessages はテーブル作成済み）を新設
- config/database.php の mysql_rw 読み書き分離を有効化（読み取りクエリを明示的に切り替え、sticky セマンティクスを含む）、CurrencyExchangeGainsLosses の決算為替レート比較書き込みを追加し多通貨按分決算をクローズ
- `Products::toSearchableArray()` を多言語 title/description インデックスフィールドに拡張し locale で重み付け、ES 多言語検索を強化
- Klarna/Adyen の完全実装（オンデマンドでスケジュール、検収条件：サンドボックス決済成功 + webhook 署名検証 + 返金クローズループ）
- 🔄 レビュー追加：決済の部分返金能力（Refunds ステートマシンの 2/3 遷移、部分返金額と注文ステータスの連動）と webhook イベントカバレッジ拡張（payment_intent.refunded/failed 等の非成功イベントの明示的処理方針、現在は黙って無視し PaymentReconcileCron のフォールバックに依存）
- 🔄 レビュー追加：認証強化——JWT 失効（Redis ブラックリストまたは token バージョン番号、パスワード変更/ログアウト後に無効化）、パスワードリセット/メール検証フロー（調査 §5 の提案、ロードマップでこれまで見落とし）
- ✅ レビュー追加：クライアント AES インターフェース暗号化接続（Flutter/HarmonyOS が X-Encrypted/X-Encrypt-Response をサポート）+ 鴻蒙 token セキュアストレージ（KeyStore/security.asset で preferences 平文を置換）——下記「フェーズ四 P2：鴻蒙 KeyStore + クライアント AES + 決済完了ページ」を参照（コード済み、コンパイル検証待ち）

**担当ロール**：バックエンドエンジニア、バックエンドフルスタック、決済決算、越境 i18n、QA

## 四、重要リスク（最優先で対応必須）

1. **決済チェーンに冪等がなく按分料率が二重ソースでドリフト**：payment/create の重複リクエストが複数の待支払いレコードを生成、webhook は成功イベントのみ処理；gateway_fee 料率が二箇所で独立管理され、按分口径に重複と不整合のリスク。
2. **ドキュメントがコードに先行する信頼リスク**：風控エンジン、Klarna/Adyen、四線按分、インボイス PDF、サブスクリプション/AB、WS 客服など十数項目が「完全」と謳いつつ実はプレースホルダーか CRUD スタブで、商業顧客への納品期待の落差。
3. **新規インストールのシードデータが空 + コンプライアンスのデフォルト通過**：countries/決済方法/送料/関税インターフェースが開封即空；blocked_countries のデフォルト空配列、KYC は KR のみ、設定漏れで完全にオープン。
4. **品質ゲートの形骸化**：単体テスト 22 個がツールクラスのみカバー、PHPStan はデフォルト 128M で開封即クラッシュ、admin にテストと品質設定なし、CI に phpstan/composer audit/クライアント job なし、リファクタリング統合が無防備。
5. **本番ミドルウェアの露出面**：ES が認証なしで 9200 公開、Redis がデフォルトでパスワードなし、MySQL/サービスポートが全公開、.env を完全に設定しなくても裸で起動・リリース可能。

## 五、Quick Wins（今すぐできる低コスト高効果の事項）

1. **✅ 完了済み** PHPStan ゲート：Makefile check と CI の phpstan コマンドに `--memory-limit=1G`（注意：PHPStan 2.2.8 は neon の `memoryLimit` パラメータを削除済みで、CLI で渡す必要がある。neon で設定すると `Unexpected item` になる）。実測 `make check` → `[OK] No errors`。
2. **✅ 完了済み** ShopOrderController/ShopPaymentController に `: array`/`: Response` の戻り値型を追加、修正後 82/82 コントローラーのリフレクションロード成功；再発防止スモークスクリプトはフェーズ一の成果物を参照。
3. InstallController step1 末尾で countries シードと決済方法/HS Code/送料関税サンプルを自動インポート、新規インストールが開封即有データ。
4. **✅ 完了済み** ShopDashboardController の kpi/chartData ルート（kebab→クラス名の完全一致）と `$this->json` シグネチャ競合を修正（`$this->json(0,'ok',$data)` に変更）、ハードコードされたサンプルデータを置換。
5. **✅ 完了済み** CI に composer audit ステップ（`||` フォールバックで既知の低危険度 CVE で遮断しない）と phpstan ステップを追加、依存セキュリティをゲートに組み込み。

## 六、起動順序の提案

**まずフェーズ一（止血と取引メインチェーン開通）を起動**：四端の取引チェーン断裂と admin の致命的エラーはリリース遮断レベルの問題；コントローラーシグネチャ修正、注文計費、シードインポート、双端決済開通は互いに独立して並行可能で、1-4 週で効果が出ます；まずメインチェーンを動かしてから、その後のコンプライアンスと品質ゲートの検証可能なベースラインを確立します。

## 付録

- **チーム構成**：調整層（Team Lead、システムアーキテクト）→ サーバーサイド小隊（バックエンド/決済決算/検索レコメンド/バックエンドフルスタック）→ クライアント小隊（Flutter、鴻蒙）→ 横断サポート（セキュリティコンプライアンス、QA、DevOps、越境 i18n）、詳細はルート `CLAUDE.md` とチーム計画議論を参照。
- **調査明細**：`docs/PLAN-RESEARCH.md`（7 分野：サーバーサイド API / 管理バックエンド / Flutter / 鴻蒙 / セキュリティコンプライアンス / デプロイデータテスト / ドキュメント機能カバレッジ）。
