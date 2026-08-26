# Erik Shop 프로젝트 계획 (팀 산출)

> **생성 시점**: 2026-08
> **생성 방식**: 다중 에이전트 팀 협업 (7개 영역 병렬 조사 → 시스템 아키텍트 통합 → 리뷰 엔지니어 재검증)
> **근거**: `PLAN-RESEARCH.md`(7건 영역 조사 상세), `../../README.md`, 각 하위 프로젝트 `CLAUDE.md`
> **적용 기간**: 3-6개월 (4개 단계)
> **리뷰 기록**: 2026-08 리뷰 엔지니어가 코드 대조로 18개 주장 재검증(16건 정확, 2건은 작업 영역에서 이미 수정되어 부분 정확); 본 버전에 리뷰 조정 반영됨(PosterVerify 발급 인터페이스, 리스크 심사 출구, Flutter 경로, 구현 상태 표기 등).

## 0. 현재 구현 상태 (리뷰 시 대조)

> `git status`/`git diff` 실측 대조 기준; ✅=완료(작업 영역 미커밋), 🔄=진행 중, ⬜=미시작.

| 항목 | 상태 | 설명 |
|---|---|---|
| admin 치명적 컨트롤러 2개 시그니처 수정(ShopOrder/ShopPayment에 `: array`/`: Response` 추가) | ✅ | 수정 후 82/82 컨트롤러 리플렉션 로딩 성공(수정 전 Fatal 2건) |
| PHPStan 게이트 | ✅ | `make check` 실측 `[OK] No errors`; PHPStan 2.2.8이 neon `memoryLimit` 파라미터 제거, Makefile/CI에서 `--memory-limit=1G` 전달로 변경 |
| ShopDashboardController json 시그니처 + 뷰 fetch URL | ✅ | `$this->json(0,'ok',$data)` + `/ShopDashboard/kpi` 클래스명 라우팅 |
| CI에 composer audit + phpstan 추가 | ✅ | `.github/workflows/ci.yml`에 2단계 추가(YAML 검증 완료) |
| `scripts/smoke_controllers.php` 재발 방지 스모크 | 🔄 | 단계 1 산출물 참조 |
| PosterVerify 발급 인터페이스(`POST /api/poster/verify`) | ✅ | 리뷰 신규 발견 + 구현 완료: `PosterController`(math 산수 문제) + 라우팅; 8789 포트 실측 전 구간 통과(challenge→verify→미들웨어 통과→일회성 소비) |
| 🔄 신규 발견 P0: Encryptable 빈 IV가 가입 차단 | ✅ | 수정 완료: `app/common/SecureEncrypter.php`(명시적 16바이트 제로 IV, 기존 데이터와 바이트 단위 호환) + `support/bootstrap.php`에 resolver 등록; 실측 가입 성공, 로그인 복호화 정상 |
| 🔄 신규 발견 P0: 암호화 필드 조회 불가(email) | ✅ | 수정 완료: `erik_users.email_hash`(HMAC-SHA256 인덱스 컬럼, install.sql + ALTER + 백필); AuthController register/login과 SocialAuthController가 email_hash 조회로 변경; 실측: 가입 성공/중복 가입 422/로그인 성공/오답 비밀번호 401 |
| 🔄 신규 발견 P0: HASHIDS_SALT 플레이스홀더/미읽음 | ✅ | 수정 완료: `config/hashids.php` main.salt가 `getenv('HASHIDS_SALT')` 읽음; 본 환경 `.env`에 랜덤 salt 생성(기존 change_me 플레이스홀더였고, 설정이 빈 salt로 하드코딩돼 fail-closed 예외 발생) |
| Quick Win #3: 비즈니스 시드 데이터 자동 임포트 | ✅ | `service/database/seeders/run.php` 신규(멱등: countries 23 + logistics 3 + shipping zones 3 + rates 3 + gateways 2 + methods 2 + hs_codes 8 + tariff_rules 7); 실측 재실행 시 0 추가 51 스킵; /api/countries, /api/payment/methods, /api/shipping/calculate(북미 구역 DHL 12.24), /api/tariff/estimate 전부 사용 가능 |
| 🔄 신규 발견: 모델의 잘못된 encryptable(name류 비민감 필드) | ✅ | 30+ 모델이 name 등 공개 필드를 암호화: 이름 조회/정렬 파괴 및 짧은 필드에 암호문 미저장. 전량 정리: 시드 관련 4모델(지난 라운드) + 일괄 17모델(Categories/Currencies/Shops/Suppliers.name/Merchants.store_name 등), email/mobile/real_name/api_key/access_token 등 진짜 민감 필드는 유지 |
| 🔄 신규 발견: 모델 Eloquent 연관 누락 | ✅ | PaymentGatewayMethods.gateway, ShippingZoneRates.logistics/zone 누락으로 /api/payment/methods, /api/shipping/calculate 500 발생, 보완 완료 |
| 단계 1: OrderController 실제 청구 | ✅ | store()에 쿠폰(정액 할인/퍼센트/고정, user_coupons + used_qty 정산), 운임(구역+요율 계단 최저가), 관세/VAT(HS Code→목적국 세율) 연동; 실측 3×49.99=149.97 100 이상 20 할인 → discount 20 + shipping 12.24 + tax 0 = pay 142.21, 재고/정산/상세/로그 전 구간 검증 |
| 🔄 신규 발견 P0: HashidsDecode 파라미터 유실 | ✅ | 미들웨어 setPost($updates)가 전체 교체 방식이라 어떤 _id 필드를 디코딩하면 같은 요청의 다른 파라미터(coupon_id/weight_grams 등 전 사이트 영향)가 유실; array_merge 병합으로 변경, 실측 다중 파라미터 주문 정상 |
| 🔄 신규 발견: 주문 링크 동반 버그 | ✅ | CouponController::claim의 where 컬럼명이 값으로 오기재(whereColumn으로 수정); Orders.address_snapshot JSON 컬럼에 cast 누락(array cast 추가); OrderLogs 테이블에 updated_at 없음(모델 $timestamps=false) |
| 단계 1: InstallController seeder 통합 | ✅ | 설치 마법사가 install.sql 임포트 후 자동으로 service/database/seeders/run.php 실행(독립 자식 프로세스로 autoload 격리, 실패 시 경고만); 동시에 install.sql 경로 버그 수정(기존 base_path(false)가 admin/을 가리켜 상위 루트의 install.sql을 못 찾음, dirname으로 변경) |
| 단계 1: 하모니오스 주문 결제 연동 | ✅ | Checkout.ets에 주소 선택, PosterVerify(challenge→verify), 전체 주문 파라미터 + X-Poster-Token, 결제 발행(payment/create) 연동; ApiClient에 headers/파라미터 확장; **hvigor assembleHap 컴파일 통과** |
| 단계 1: Flutter 주문 결제 연동 | ⚠️ 코딩 완료, 컴파일 검증 대기 | checkout_screen에 주소/휴먼 인증/전체 주문/결제 발행 연동; register_screen에 PosterVerify 연동; api_client post에 headers 지원. **본 환경 flutter SDK 캐시가 읽기 전용이라 컴파일 불가**, 로컬 `flutter analyze`/`flutter test` 검증 필요(괄호/구조 정적 검사는 통과) |
| 단계 2 P1: 리스크 엔진 RiskEngine | ✅ | `app/common/RiskEngine.php` 신규(email_domain 임시 메일/velocity 빈도/amount 대금액/address_mismatch/ip_reputation, Redis 카운트); 주문/가입/결제에 바이패스 스코어링 + RiskLogs 연동; **실측**: 임시 메일+대금액 주문 → 80점 review → 주문 status=8 심사 대기, risk_score/risk_result/OrderLog 리스크 표기 완비 |
| 단계 2 P1: 리스크 심사 출구 | ✅ | `POST /api/admin/orders/{id}/review` 신규(AdminKeyMiddleware; approve→0 방출/reject→5 기각, status=8 원자 전이 + OrderLogs); **실측** approve/reject/잘못된 key 403/중복 심사 422 전부 정확 |
| 단계 2 P1: KYC 사용자측 클로즈드 루프 | ✅ | KycController 신규(POST /api/kyc 제출 + GET /api/kyc/status 조회, real_name/id_number Encryptable 암호화, status 0심사 대기/1통과/2기각); **실측** 제출/조회 정상 |
| 단계 2 P1: 결제 보강 | ✅ | StripeGateway에 명시적 `request_three_d_secure=automatic`(3DS); Klarna/Adyen은 `PaymentGateway::make` throw 플레이스홀더 유지(문서 수정 예정) |
| 🔴 **신규 발견 전역 버그: HashidsDecode 라우팅 파라미터 디코딩 무효** | ✅ | webman 컨트롤러 메서드 파라미터는 findRoute가 잡은 원본 hashid(미들웨어 setParams 미적용). 통일 수정: `BaseApiController::decodedId()` 헬퍼 + 17곳 {id} 라우팅 메서드 진입점 연동(주문/상품/장바구니/주소/위시리스트/평가/결제 상태/반품/쿠폰/알림/비교/환불 실행/심사); **실측** 주문 상세, 상품 상세, 주문 취소, 장바구니 update/destroy, 쿠폰 수령(hashid 경로) 전부 통과; 겸사 Orders의 items/logs/documents 연관, Carts의 sku 연관 누락 수정 |
| 단계 2 P1: 분배 요율 통일 + 셀러 분배 | ✅ | SettlementCron 요율 출처를 `payment.gateway_fee.{gateway}` + `payment.platform_rate`로 통일(webhook과 동일 출처, cron.*은 호환 폴백만); MerchantSettlements 기록 추가(order_items→MerchantProducts approved→merchant.commission_rate); **실측**: 162.21 주문 → 플랫폼 5% 수수료 8.11 + stripe 게이트웨이 수수료 5.00, 셀러 149.97@8% → 수수료 12.00 정산 137.97 |
| 단계 2 P1: 4라인 분배 보완(Supplier/Affiliate) | ✅ | 스키마 보완: `erik_products.supplier_id` + `erik_orders.affiliate_link_id`(install.sql + ALTER); SettlementCron에 공급업체 주기 정산(월별 upsert SupplierSettlements) + 유통 커미션(AffiliateCommissions + AffiliateLinks 카운트) 추가; **실측**: 상품 99.98이 공급업체 당월 정산에 귀속, 112.22@10% → 유통 커미션 11.22 및 링크 orders/commission 업데이트; AffiliateCommissions 테이블에 updated_at 없어 `$timestamps=false` 보완 |
| 단계 2 P1: InstallController 이중 소스 테이블 목록 검증 | ✅ | `scripts/check_install_tables.php` 신규(install.sql 테이블명 vs InstallController $tables_to_install 파싱 대조, wa_ 플러그인 테이블 면제), Makefile check 연동; **실측** 110 vs 110 일치 OK |
| 단계 2 P1: GDPR/CCPA 실행 계층 | ✅ | `PrivacyComplianceTask` 신규(매시간): data_delete 유예 기간 후 사용자 익명화(이메일/email_hash/mobile 비움, 닉네임 "탈퇴한 사용자", status=0, 세금 필드 유지), data_access/data_portability는 내보내기 JSON 생성, opt_out 표기; `POST /api/privacy/cookie-consent` 신규(CookieConsents 기록, version/preferences JSON); **실측**: 31일 전 data_delete 요청 → 사용자 익명화 + 요청 completed; cookie-consent 기록 완전 |
| 단계 2 P1: Klarna/Adyen 문서 수정 | ✅ | README.md(결제 행/원화 표시 차감/기능표)와 docs/VERSIONS.md에서 Klarna/Adyen/BNPL을 플레이스홀더로 표기, 실제 `PaymentGateway::make` throw와 일치 |
| 단계 4 P2: 재고 거래 내역 불변 장부 | ✅ | `InventoryLogger`가 주문 차감(outbound)/취소 복원(inbound) 연동, erik_inventory_logs 기록(balance_after 스냅샷); **실측** 주문 -2/취소 +2 거래 내역 완전 |
| 단계 4 P2: 상업 인보이스/포장 명세서 PDF | ✅ | DocumentController 재작성: dompdf가 필요 시 PDF 생성(상세+금액+통관 신고) public/documents/ + erik_order_documents(멱등); 파라미터명과 라우팅 {id} 불일치 수정; **실측** PDF 2종 생성 성공 |
| 단계 3 P1: admin 품질 게이트 | ✅ | admin/phpunit.xml + tests/UtilTest.php(2/7 통과), phpstan.neon(level 5), .php-cs-fixer.php(fix 무한 대기 수정), composer에 phpstan 추가, CI에 admin 단계 추가, Makefile test 양쪽 프로젝트 |
| 단계 4 P2: DB 읽기/쓰기 분리 | ✅ | 순수 조회 모델 6개에 `$connection='mysql_rw'` 활성화(Eloquent 자동 읽기/쓰기 분기 + sticky); **실측** 조회 연결=mysql_rw, 쓰기 정상; 프로덕션 DB_READ_HOST_1/2 설정 시 적용 |
| 단계 4 P2: 구독 정기 구매 API | ✅ | SubscriptionController(구독 생성+첫 회차 주문, 내 구독, 취소); **실측** 생성/목록/취소 전부 통과; SubscriptionOrders/Logs에 `$timestamps=false` 보완 |
| 단계 4 P2: 다중 플랫폼 등록 기록 | ✅ | `POST /api/admin/platform/listings`(AdminKeyMiddleware, PlatformListings draft/listed upsert); **실측** 등록 기록 성공 |
| 단계 4 P2: SubscriptionCron 자동 갱신 | ✅ | `service/app/process/SubscriptionCron.php`(매일): 만료 구독→트랜잭션으로 갱신 주문 생성/주기 수+1→next_billing 갱신→로그; SKU 하한선/재고 부족은 paused 처리; **실측** 스모크 7단언 전부 통과 |
| 단계 4 P2: WS 고객 서비스 실시간 IM | ✅ | `ChatController`(REST 세션/메시지) + `ChatWs`(WebSocket 8788, JWT+세션 귀속 인증, 이중 채널 동일 소스 기록); **실측** 엔드 투 엔드 5항(핸드셰이크/브로드캐스트/DB 기록/불법 token/타인 세션 거부); 공지: 고객 서비스측 인증 없음, 세션 종료 액션 미구현 |
| 단계 4 P2: ES 다국어 검색 | ✅ | webman-scout hosts를 `ELASTICSEARCH_HOST` env로 변경; Products `toSearchableArray()` 다국어 필드 + `scripts/es-index-products.php` 일괄 인덱서; 미설정 시 SQL 폴백; **실측** 폴백 경로/데이터 형상(ES 서비스 없음, 온라인 조회 미실측) |
| 단계 4 P2: Klarna/Adyen 결제 스켈레톤 | ✅ | `KlarnaGateway/AdyenGateway`(Guzzle 직접 연결: 생성/조회/환불/Webhook HMAC 서명 검증), 키 누락 시 env 명시 예외; `PaymentGatewayInterface` 분리; **실측** 서명 검증 알고리즘 양방향 + phpstan/phpunit 전부 통과; 실제 키 연동 후에만 사용 가능 |
| 단계 4 P2: cron 3개 URL env화 | ✅ | `config/cron.php`의 3개 *_url을 env로 변경(TRACKING/COMPLIANCE/PLATFORM_URL); 3개 cron 조회 로직 완비; 실제 외부 API 미연결 |
| 단계 4 P2: 하모니오스 KeyStore + 클라이언트 AES + 결제 완료 페이지 | ✅ | 하모니오스 `SecureStore.ets`(Asset Kit으로 preferences 대체) + Flutter/하모니오스 `SecureCrypto.ets`/`_SecureCrypto`(AES-256-CBC, X-Encrypted/X-Encrypt-Response, 키 비면 평문) + 양쪽 결제 완료 페이지; **컴파일 미검증**(도구 체인 없음), `flutter pub get`/hvigor 컴파일 대기 |
| 문서 수렴 | ✅ | README/VERSIONS/admin-CLAUDE.md의 과대 선언 8건 수정(HS 신고→계획 중, 주문 내보내기 컬럼 실제 기준, i18n 전환 버튼→계획 중 등); 포장 명세서/궤적 추적 구현 확인 후 유지; VERSIONS.md 7항(AB테스트/구매/검품/이관/보험/지식 베이스/포인트)을 "테이블 구조 생성 완료"(◐)로 표기, 코드 실상(테이블+모델만, 비즈니스 코드 없음)과 일치 |
| 2라운드: JWT 폐기 + 비밀번호 재설정 + 이메일 검증 | ✅ | Jwt에 `revoke()`/`isRevoked()` 추가(Redis 블랙리스트), JwtAuth 미들웨어 검증; AuthController logout/changePassword/passwordReset/emailVerify + 라우팅; install.sql에 `email_verified_at` 추가; JwtTest 단위 테스트 통과 |
| 2라운드: 부분 환불 + webhook 이벤트 보완 | ✅ | RefundHelper 부분 환불 금액 검증 지원; AdminOpsController::executeRefund; PaymentController webhook 이벤트 분기(refunded/failed); RefundHelperTest 통과 |
| 2라운드: DevOps 수렴 | ✅ | docker-compose 포트를 127.0.0.1로 수렴, .dockerignore×2, .gitignore에 하모니오스 빌드 산출물, CI에 Flutter/hvigor jobs, download-geoip.php 스크립트 |
| 2라운드: 통합 테스트 + admin P0 UI | ✅ | IntegrationTestCase(MySQL 가용성 스킵 + 기본 테스트 DB를 케이스마다 클리어) + OrderFlow/StripeWebhook/Hashids 테스트(phpunit 40 tests / 155 assertions 전부 그린); ShopOrder/ShopPayment 모델 초기화 수정; admin 주문/결제 LayUI 목록 페이지 |
| 🔴 신규 발견 버그: webhook 분배 기록이 NOT NULL 컬럼에 차단 | ✅ | PaymentController::handlePaymentSucceeded의 PlatformSettlements::create에 supplier_amount/affiliate_amount 누락(schema NOT NULL 기본값 없음→webhook 항상 500); max(0, 총액-플랫폼 수수료-게이트웨이 수수료) 계산으로 보완(SettlementCron과 동일 출처); StripeWebhook 통합 테스트 5/5 통과 |
| 3라운드: 환불 신청 클로즈드 루프 | ✅ | RefundController(POST /api/refunds 신청 + 목록/상세, 환불 가능 잔액=실결제-기환불-심사 중) + AdminOps approve(0→3 원자 게이트 + RefundHelper 연동)/reject(0→2); Refunds status 의미는 schema 기준: 0심사 대기/2기각/3기환불; RefundFlow 통합 테스트 3/34 |
| 3라운드: WS 고객 서비스 보완 | ✅ | ChatWs 고객 서비스측 인증(첫 프레임 {type:'auth',role:'agent',key} + hash_equals 일정 시간 비교, 핸드셰이크 pending 역할) + 세션 종료(REST close/adminClose + WS close 프레임, closed 시 REST 409/WS error, closeSession 멱등 + 브로드캐스트); ChatWs 테스트 5/21 |
| 3라운드: admin 핵심 관리 페이지 | ✅ | 상품/사용자/환불/쿠폰/분류 5페이지(LayUI로 order/payment 정렬, 목록+페이징+검색+상태 필터+심사 팝업); Crud.php 3곳 근본 수정(doFormat items()를 Collection으로 감싸 ShopOrder/ShopReturn 동형 latent 버그 커버, string 모델 인스턴스화, 뷰 경로 유추) + ShopProduct afterQuery 재고 집계; ShopUserController 신규 |
| 3라운드: QA 고정 | ✅ | SubscriptionCron(갱신 주문/billing_cycle+1/next_billing 순연/재고 부족과 하한선 paused) + ES 폴백(SQL LIKE + SearchLogs 기록) 테스트; 🔴 신규 발견 수정: SearchLogs에 $timestamps=false 없음 → 검색 로그 기록 시 SQLSTATE 1054 500; 전체 54 tests / 256 assertions 0 실패 |
| 4라운드: 입력 경계 수정 | ✅ | BaseApiController::clampPage(page≥1 / perPage∈[1,50])로 8개 컨트롤러 통일(Order/B2b/PriceAlert/Affiliate/Privacy/Notification/Return/Review, Search는 fix-search 단독 수정); AdminOps reason/remark ≤500 + createListing intval; json_decode 빈 값 폴백 5곳(SocialAuth×3/ExchangeRateCron/ComplianceCron); 미사용 임포트 4개 삭제(감사 목록 나머지 11개는 grep으로 사용 확인) |
| 4라운드: 검색 인젝션 방어 | ✅ | SearchController: Lucene 특수 문자 preg_replace 이스케이프(ES 문법 인젝션 DoS 방지) + keyword >64 → 422 + LIKE `%`/`_` addcslashes + per_page 클램프; 24줄 diff |
| 4라운드: DevOps 위생 | ✅ | admin composer.lock 동기화(phpstan 입고) + service `--lock` 갱신; ci.yml audit을 "CVE-2025-45769만 허용" 견고형으로 변경(종료 코드 유지, 실측 출력 형식 매칭) + workflow_dispatch; autoload `""` 빈 접두사 ×2 삭제 및 5개 명시 접두사 보완(dump-autoload 검증); Copyright 헤더 35개 보완; LICENSE를 proprietary로 선언(webman MIT 원문 유지); dockerignore에 tests/docs 추가; compose 플레이스홀더 키 가드(production + change_me → exit 1, 실측 3분기); **스킵**: cs-fixer CI 단계(238/247 파일 부적합, 포맷 커밋 선행 필요)와 admin audit(25건 사전 경고, 의존성 업그레이드 필요) |
| 4라운드: 문서/인덱스 일관성 | ✅ | VERSIONS 7항 허위 ✅→◐(실측 테이블+모델만) + 규모표(Cron 11, 유틸리티 15, 테스트 54/256); api.md에 DELETE /api/comparisons/{id} 추가; payment.php에 adyen 요율 2.99/0.30 추가; install.sql에 6개 인덱스 추가(refunds/return_orders idx_user_id, platform_listings idx_account_product, group_buys/flash_sales/coupons idx_status_time) + scripts/index-fixes.sql(미실행, 기존 DB용); 🔴 예정: service/CLAUDE.md 유틸리티 8→15, PHPUnit 22→54 카운트 만료 |
| 4라운드: 보안 강화 | ✅ | BaseModel `$guarded=['id','money','score','level','created_at','updated_at']`(감사 원래 목록에 user_id/status 등 6개 컬럼 포함, grep으로 40+곳 create() 일괄 할당 확인→전면 차단은 데이터 손상, 최소 파괴 목록으로 실행); admin 5페이지 table.render에 `escape: true` 추가; UploadController 블랙리스트→확장자 19종 화이트리스트; InstallController 이중 검증(설정 파일 + wa_options installed=1 마커, DB 도달 불가 fail-closed); 🔴 추가 신고 기존 버그: product/index.html 재고 컬럼 templet에 return 없음 → undefined 표시 |
| 4라운드: 테스트 보강 | ✅ | SubscriptionController 4/33(주기 검증/권한 초과/취소 멱등) + Kyc 6/27(Encryptable 복호화 복원/기각 재제출/통과 시 제출 금지) + RiskEngine 6/22(임시 메일/대금액/주소 불일치/velocity/ip_reputation) 통합 테스트; Kyc 테스트 메서드명 변경으로 PHPUnit 12 final status() 오버라이드 치명 오류 회피; 전체 70 tests / 338 assertions 0 실패(기존 vendor 경고 1건: encryptable 빈 IV) |
| 5라운드: 동시성 락 인프라 | ✅ | app/common/DistributedLock.php 신규(Redis SET NX EX 스핀락, Lua 원자 해제는 자기가 보유한 락만 삭제, fail-closed: Redis 예외 시 무방비로 내보내지 않음; 단일/분산 하나의 경로); webman/redis-queue v2.1.1 연동(db=2 prefix=erik_queue:, 소비 프로세스 count=8, consumer_dir=app/queue/redis); 컴포넌트 검증 스크립트 5항 전부 통과(이중 프로세스 경쟁/타임아웃/오삭제 방지) |
| 5라운드: 쓰기 작업 락 | ✅ | 주문 방중복 lock:order:{userId}(OrderController store 트랜잭션 전체 락, 락 타임아웃 429/비즈니스 예외 422); 결제 멱등 lock:payment:{orderId}(락 내에서 대기 결제 기록 조회 시 즉시 반환, 중복 대기 결제 방지); 환불 신청 lock:refund:{orderId}(락 내에서 주문+환불 가능 잔액 재조회, 동시 초과 신청 방지); 구독 store/cancel, 주소 is_default 선클리어 후설정, 소셜 로그인 바인딩, 즐겨찾기, 장바구니 추가 읽고쓰기, 평가(고유 인덱스 없음, 락이 유일한 방어선), 가입(email_hash 비UNIQUE) 각각 시나리오별 락 보완; B2b 견적은 순수 추가 판정이라 락 불필요 |
| 5라운드: PDF 생성 비동기화 | ✅ | DocumentController가 큐 푸시 후 즉시 processing 반환으로 변경; DocumentPdfConsumer(app/queue/redis/, 큐 document_pdf, payload order_id/type/user_id, 소비 내 원 dompdf 로직 전체 이관, 멱등 DB 기록, 실패 시 로그만 남기고 재시도 안 함——사용자 재요청이 자연 재시도); 상태 판정: 기록 존재+파일 존재=done, 아니면 processing |
| 기타 산출물 | ⬜ | 잔여: 실제 결제 SDK 온라인 연동(키 필요), ES 온라인 검증(ES 서비스 없음), Flutter/하모니오스 컴파일 검증(도구 체인 없음), 하모니오스 보안 스토리지 실기기 검증, cs-fixer 포맷 커밋 후 CI 단계 추가, admin 의존성 업그레이드 후 audit 단계 추가, PDF 비동기 엔드 투 엔드 검증(큐 프로세스 실행 필요) |

---

## 1. 총괄 판단

Erik Shop 인프라 스켈레톤은 탄탄합니다(117개 테이블, 39개 컨트롤러, Stripe/PayPal 실제 게이트웨이, WAF/JWT/AES 보안 스택, 22개 단위 테스트 전부 통과). 그러나 핵심 거래 메인 링크가 service/admin/Flutter/하모니오스 4개 단말에서 동시에 끊어져 있고, 약 10여 항목의 문서가 "완전"이라 주장하는 기능이 실제로는 테이블 구조 또는 CRUD 스텁이며, 품질 게이트(PHPStan/통합 테스트/클라이언트 CI)가 형식에 불과합니다. 전체적으로 **"스켈레톤 완전, 클로즈드 루프 부재, 문서가 앞서감"** 단계입니다. 3-6개월 내 먼저 지혈로 거래 클로즈드 루프를 뚫고, 그다음 컴플라이언스와 품질 기반을 보완하며, 마지막으로 증분 기능을 확장하고 문서를 수렴해야 합니다.

## 2. 전역 문제 5가지

1. **핵심 거래 메인 링크 3개 단말 동시 단절**(서버/Admin/양쪽 클라이언트 교차 확인): service `OrderController::store`가 쿠폰/운임/관세/리스크를 계산하지 않음(상품 소계만 합산); Flutter와 하모니오스 주문 모두 `address_id`가 없고 PosterVerify 40001에 거부되며, 결제는 `POST /payment/create`를 한 번도 호출하지 않음; admin `ShopOrderController`/`ShopPaymentController`는 PHP 8.3 메서드 시그니처 비호환으로 클래스 로딩 즉시 Fatal. 현 상태로 배포하면 구매 전환 전체 경로를 사용할 수 없고, 주문/결제 관리 메뉴는 여는 즉시 크래시합니다.
2. **문서가 코드를 체계적으로 앞서감**(문서/서버/보안/컴플라이언스 4개 영역 일치 확인): `features.md`/`VERSIONS.md`/`README`가 리스크 엔진(RiskEngine), Klarna/Adyen 결제, 4라인 분배, 상업 인보이스 PDF, 구독 정기 구매/AB 테스트, WebSocket 고객 서비스 IM, 다중 플랫폼 상품 등록을 전부 "완전/✅"로 표기했지만 실제로는 테이블 구조 + admin CRUD 또는 비즈니스 구현 제로로, 상용 고객에게 납품 기대와 신뢰 리스크가 됩니다.
3. **비즈니스 시드 데이터 부재 + 보안 컴플라이언스 실행 계층 공백**(서버/배포/컴플라이언스 3개 영역 동일 증언): `install.sql`에 시스템 테이블 시드만 있고, countries/currencies/payment_gateway_methods/hs_codes/shipping_zones는 새로 설치하면 전부 비어 있음(핵심 인터페이스가 개봉 즉시 빈 값 반환); 동시에 `blocked_countries` 기본값이 빈 배열, 리스크 호출 0건, KYC 제출 진입점 없음, GDPR/CCPA는 등록만 하고 실행 안 함——"개봉 즉시 빈 값 + 기본 방출"에 컴플라이언스 선언 부실이 겹칩니다.
4. **Admin 백엔드 비즈니스 계층이 "컨트롤러만 있고 페이지 없음"**: 59/67이 순수 CRUD 스텁, HTML 뷰 없음, 메뉴 클릭 404; 크로스보더 패널 kpi/chartData 라우팅과 json 시그니처 이중 손상; 컨트롤러 40개가 메뉴에 미연결, 쇼핑몰 관리 UI 전체가 실제로 사용 불가하며 문서가 주장하는 "완전한 관리 백엔드"와 심각하게 불일치합니다.
5. **품질 게이트가 명목상으로만 존재**(테스트/배포/문서 3개 영역 동일 증언): 단위 테스트 22개가 유틸리티 클래스 4개만 커버하고 비즈니스 컨트롤러/미들웨어/모델은 테스트 제로; PHPStan 기본 128M는 개봉 즉시 크래시, admin은 품질 설정 없음; CI에 phpstan/php-cs-fixer/composer audit 단계 없음, Flutter/HarmonyOS job 없음; 하모니오스 빌드 산출물 99개가 오입고되어 어떤 리팩터링 머지도 무방비입니다.

## 3. 단계별 로드맵

### 단계 1: 지혈과 거래 메인 링크 연결 — **P0 · 1-4주**

**목표**
- admin 치명적 컨트롤러 2개 수정과 재발 방지 스모크 메커니즘 구축, 주문/결제 관리 메뉴 가용성 복원
- service 주문 실제 청구(쿠폰/운임/관세/할인 DB 저장) 연결과 결제 멱등 보완으로 백엔드 주문 링크 클로즈드 루프
- 비즈니스 시드 데이터 자동 임포트 보완, 새로 설치한 핵심 인터페이스가 개봉 즉시 데이터 보유 보장
- Flutter와 하모니오스의 결제-주문-결제 링크(address_id + PosterVerify + payment create/status) 연결

**산출물**
- ✅ 완료: `admin/plugin/admin/app/controller/shop/ShopOrderController.php`와 `ShopPaymentController.php`에 `: array`/`: Response` 반환 타입 추가(82/82 리플렉션 로딩 통과); **잔여**: `scripts/smoke_controllers.php` 신규(php -l + 전체 82개 컨트롤러 리플렉션 로딩) 및 Makefile check와 CI 연동, 재발 방지 게이트로 사용
- 🔄 **리뷰 신규(고우선)**: PosterVerify 발급 인터페이스 `POST /api/poster/verify` —— 미들웨어가 Redis 키 `erik:poster:{token}`을 검증하지만 프로젝트 전체에 발급/키 기록 코드가 없어 클라이언트가 X-Poster-Token을 얻을 방법이 없음; poster-php를 호출해 인증 코드를 생성하고 Redis 키 기록(만료+일회성 소비 포함) 필요, Flutter/하모니오스 가입, 주문, 결제 휴먼 인증 연동의 **선행 의존성**
- `service/app/controller/v1/OrderController.php` store()에 coupon 할인 계산과 shipping_fee/tax_amount/discount_amount DB 저장 연동(api.md 5.3 / features.md 3.3 정렬), api.md 2.1의 min_price/max_price 필터 구현; `PaymentController::create`에 order_id+gateway 멱등 중복 제거 추가
- `admin/plugin/admin/app/controller/InstallController.php` step1 끝에 `service/database/seeders/countries.php` 실행 추가, erik_payment_gateway_methods(stripe/paypal 각 method 행), erik_hs_codes 기본 라이브러리, erik_tariff_rules/erik_shipping_zones 예제 시드 신규
- `apps/flutter/lib/features/order/checkout_screen.dart`(**주의: 실제 경로, lib/screens/ 아님**)에 주소 선택과 기본 주소 백필, address_id+currency_code 제출, PosterVerify(X-Poster-Token) 연동 후 `POST /payment/create` + `GET /payment/status` 폴링 결제 페이지 구현; `apps/harmonyos/entry/src/main/ets/pages/Checkout.ets`에 address_id + selectedShipping + currency_code와 결제 호출 동기 보완(하모니오스는 주소 관리 페이지 신규 필요, Profile 배송 주소 route가 현재 비어 있음)
- ✅ 완료: `ShopDashboardController.php` kpi/chartData 라우팅(kebab→클래스명 정확 매칭)과 `$this->json` 시그니처 충돌 수정, 하드코딩 예제 데이터 교체
- service 주문/결제/환불 핵심 인터페이스 통합 테스트 추가(트랜잭션/재고 차감/취소, webhook 서명 검증+멱등+분배, Hashids 인코딩/디코딩), CI에서 이미 기동한 MySQL/Redis 서비스 재사용
- 겸사: `docs/deployment.md`의 admin 포트 8787→8788 오기 2곳 수정

**담당 역할**: 백엔드 풀스택, 백엔드 엔지니어, 결제 정산, Flutter, 하모니오스, QA

### 단계 2: 컴플라이언스 클로즈드 루프와 결제 정산 확장 — **P1 · 5-10주**

**목표**
- 리스크 규칙 엔진 구현과 주문 상태 머신 "심사 대기(8)" 연결, "주문 리스크 없이 방출" 노출 제거
- KYC 사용자측 제출 클로즈드 루프와 GDPR/CCPA 실행 계층(삭제/내보내기/opt-out) 보완
- 분배 요율 출처 통일과 4라인 분배(Merchant/Supplier/Affiliate 기록) 보완
- 결제 방식 선언 수렴: Klarna/Adyen 구현 또는 명확한 플레이스홀더 표기와 문서 동기 수정, 3DS 명시 코드 보완

**산출물**
- `service/app/common/RiskEngine.php` 신규(config/risk.php checks/velocity 기준 score 구현), OrderController::store / PaymentController::create / AuthController에 바이패스 스코어링, erik_orders.risk_score/risk_result와 RiskLogs 기록, 고득점은 status=8; ShopRiskRule/ShopRiskLog를 admin 메뉴에 연결
- 🔄 **리뷰 신규**: 리스크 심사 출구 `POST /api/admin/orders/{id}/review`(AdminKeyMiddleware 보호, status=8 원자 게이트로 1 방출/5 기각 전이 + OrderLogs 기록)——현재 서버에 status=8 기록/전이 경로가 전혀 없어 메뉴만 달고 인터페이스를 연결하지 않으면 "심사 대기"는 여전히 막다른 길; admin ShopOrder 목록에 심사 동작 병행
- `service/config/route.php`에 `POST /api/kyc`와 `GET /api/kyc/status` 신규(real_name/id_number Encryptable 경유), admin 심사 통과 시 status=1로 설정해 OrderController 기존 검증과 연결(admin KYC 심사 진입점 명확화 병행)
- `service/app/task/PrivacyComplianceTask` 신규(config/privacy.php 기준 데이터 삭제 유예 기간/데이터 내보내기 파일/opt_out 차단 표기 실행) + `POST /api/privacy/cookie-consent`로 erik_cookie_consents 기록
- webhook과 SettlementCron을 단일 요율 설정 출처로 병합(gateway_fee 이중 출처 드리프트 제거), MerchantSettlements/SupplierSettlements/AffiliateCommissions 기록과 지급 프로세스 보완, docs/08-multi-currency-settlement 지원
- **Klarna/Adyen 기본 동작**: 우선 "명시적 throw 플레이스홀더 + api.md 6.1 / README / VERSIONS 표현 수정"(저비용, 당일 완료); 전체 구현(샌드박스 결제 성공 + webhook 서명 검증 + 환불 검수 포함)은 단계 4로 하향; `StripeGateway::createPayment`에 명시적 `request_three_d_secure='automatic'` 설정과 erik_payments.three_ds_status 기록

**담당 역할**: 보안 컴플라이언스, 결제 정산, 백엔드 엔지니어, 백엔드 풀스택, 크로스보더 i18n

### 단계 3: 품질 게이트와 백엔드 UI 보완 — **P1/P2 · 11-18주**

**목표**
- 정적 분석 게이트 수정(PHPStan 메모리 제한) 및 admin 전체 품질 설정과 테스트 스켈레톤 보완
- PHPUnit/phpstan/php-cs-fixer/composer audit/Flutter와 하모니오스 CI를 전부 게이트에 편입
- 쇼핑몰 관리 P0 모듈에 LayUI 목록 페이지 보완 또는 404 메뉴 정리, "JSON API only" 포지셔닝 명확화
- 배포와 런타임 노출면 수정(포트 바인딩, 소스 볼륨 마운트, GeoIP 데이터, dev 의존성)

**산출물**
- ✅ service측 완료: phpstan 명령에 `--memory-limit=1G`(Makefile/CI, PHPStan 2.x가 neon memoryLimit 파라미터 제거); **잔여**: admin/phpstan.neon(level 5) + admin/.php-cs-fixer.php + admin/phpunit.xml + admin/tests/(우선 Crud 베이스 클래스 inputFilter/doSelect/데이터 권한, AccessControl 인증, ShopRefundController mock 원격 환불 커버)
- ✅ 완료: ci.yml에 composer audit + phpstan 추가; **잔여**: php-cs-fixer --dry-run, service 통합 테스트(MySQL/Redis 서비스 직접 연결), Flutter analyze+test job과 하모니오스 hvigor 빌드 job
- `admin/plugin/admin/app/controller/shop/` UI 보완은 **우선순위 매트릭스** 기준 실행: P0(주문/환불/배송/결제)는 index()와 view/shop/ 아래 index.html(LayUI 목록) 필수 보완; 나머지 메뉴 항목은 기본적으로 config/menu.php에서 제거하고 "JSON API only" 표기(제거가 곧 404 제거, 제로 코스트), 페이지 보완은 이후 필요 시 증분으로 처리해 방치된 반제품 방지
- 🔄 리뷰 신규: 하모니오스 저장소 정리(.gitignore에 `apps/harmonyos/**/build`, `**/.hvigor`, `**/oh_modules` 추가 + `git rm --cached`로 입고된 빌드 산출물 99개 정리; hvigorw wrapper 보완)——CI에 하모니오스 빌드 job 연결의 선행 조건
- 🔄 리뷰 신규: install.sql과 InstallController `$tables_to_install` 충돌 테이블 목록 이중 소스 유지 검증 스크립트(install.sql의 CREATE TABLE 파싱으로 동적 생성 또는 양쪽 일치 대조)
- `docker-compose.yml`의 ES/Redis/MySQL 포트 바인딩을 127.0.0.1로 변경(nginx만 80/443 노출), `./service:/app`와 `./admin:/app` 소스 볼륨 마운트 제거 및 service/.dockerignore와 admin/.dockerignore 신규(vendor/runtime/.git 제외), 컨테이너가 --no-dev vendor로 실행되도록 보장
- GeoLite2-Country.mmdb 다운로드 스크립트 보완(또는 MAXMIND_LICENSE_KEY 자동 갱신 활성화) service/database/geoip/에 배치; config/cron.php의 빈 URL 3개 로그를 WARNING으로 올리고 눈에 띄는 주석 보완

**담당 역할**: QA, DevOps, 백엔드 풀스택, Flutter, 하모니오스

### 단계 4: 증분 기능과 문서 수렴 — **P2 · 19-26주**

**목표**
- 문서에 "완전"이라 표기했지만 실제로 누락된 증분 기능 구현(인보이스 PDF, 재고 거래 내역, 다중 플랫폼 등록, 구독 정기 구매)
- 읽기/쓰기 분리, 다중 통화 정산 클로즈드 루프와 ES 다국어 검색 강화 활성화
- 문서 3상태 표기 통일(구현됨/테이블 구조 생성됨/계획 중)과 엔드포인트 일관성 검사 구축, 추가 드리프트 원천 차단

**산출물**
- `service/app/controller/v1/DocumentController.php`가 이미 도입된 barryvdh/laravel-dompdf로 필요 시 상업 인보이스/포장 명세서 PDF 생성 후 erik_order_documents에 기록; OrderController가 재고 차감 시 erik_inventory_logs 불변 거래 내역 기록
- PlatformOrderSyncCron에 amazon/eBay/Shopee 어댑터 추가 및 상품 등록을 PlatformListings에 기록 구현; 구독 정기 구매 API 신규(erik_subscriptions 테이블 생성 완료, 최소 비즈니스 범위 먼저 정의: 구독 청구 주기 + 취소 + 갱신)와 WebSocket 고객 서비스 서버(ChatSessions/ChatMessages 테이블 생성 완료)
- config/database.php의 mysql_rw 읽기/쓰기 분리 활성화(읽기 전용 조회 명시적 전환, sticky 의미 포함), CurrencyExchangeGainsLosses 정산 환율 비교 기록 보완, 다중 통화 분배 정산 클로즈드 루프
- `Products::toSearchableArray()`를 다국어 title/description 인덱스 필드로 확장하고 locale별 가중치, ES 다국어 검색 강화
- Klarna/Adyen 전체 구현(필요에 따라 일정 배정, 검수 조건: 샌드박스 결제 성공 + webhook 서명 검증 + 환불 클로즈드 루프)
- 🔄 리뷰 신규: 결제 부분 환불 기능(Refunds 상태 머신 2/3 전이, 부분 환불 금액과 주문 상태 연동)과 webhook 이벤트 커버리지 확장(payment_intent.refunded/failed 등 비성공 이벤트의 명시적 처리 전략, 현재는 조용히 무시하고 PaymentReconcileCron 폴백에 의존)
- 🔄 리뷰 신규: 인증 강화——JWT 폐기(Redis 블랙리스트 또는 token 버전 번호, 비밀번호 변경/로그아웃 후 무효화), 비밀번호 재설정/이메일 검증 프로세스(연구 §5 제안, 로드맵에서 이전에 누락)
- ✅ 리뷰 신규: 클라이언트 AES 인터페이스 암호화 연동(Flutter/HarmonyOS X-Encrypted/X-Encrypt-Response 지원) + 하모니오스 token 보안 저장(KeyStore/security.asset으로 preferences 평문 대체)——아래「단계 4 P2: 하모니오스 KeyStore + 클라이언트 AES + 결제 완료 페이지」참조(코딩 완료, 컴파일 검증 대기)

**담당 역할**: 백엔드 엔지니어, 백엔드 풀스택, 결제 정산, 크로스보더 i18n, QA

## 4. 핵심 리스크(우선 처리 필수)

1. **결제 링크에 멱등 없음 + 분배 요율 이중 출처 드리프트**: payment/create 반복 요청이 여러 개의 대기 결제를 생성하고, webhook은 성공 이벤트만 처리; gateway_fee 요율이 두 곳에 독립 유지되어 분배 기준에 중복과 불일치 리스크.
2. **문서가 코드를 앞서가는 신뢰 리스크**: 리스크 엔진, Klarna/Adyen, 4라인 분배, 인보이스 PDF, 구독/AB, WS 고객 서비스 등 10여 항목이 "완전"을 주장하지만 실제로는 플레이스홀더 또는 CRUD 스텁, 상용 고객에게 납품 기대 차이로 작용.
3. **새로 설치하면 시드 데이터가 비어 있음 + 컴플라이언스 기본 방출**: countries/결제 방식/운임/관세 인터페이스가 개봉 즉시 빈 값 반환; blocked_countries 기본 빈 배열, KYC는 KR만, 누락 설정 시 완전 개방.
4. **품질 게이트 명목화**: 단위 테스트 22개가 유틸리티 클래스만 커버, PHPStan 기본 128M 개봉 즉시 크래시, admin에 테스트와 품질 설정 없음, CI에 phpstan/composer audit/클라이언트 job 없음, 리팩터링 머지 무방비.
5. **프로덕션 미들웨어 노출면**: ES 인증 없음 + 9200 노출, Redis 기본 비밀번호 없음, MySQL/서비스 포트 전면 노출, .env 미완전 설정으로도 무방비 기동 가능.

## 5. Quick Wins(즉시 할 수 있는 저비용 고효익 사항)

1. **✅ 완료** PHPStan 게이트: Makefile check와 CI의 phpstan 명령에 `--memory-limit=1G`(주의: PHPStan 2.2.8이 neon의 `memoryLimit` 파라미터를 제거했으므로 CLI 인자 전달 필수, neon에서 설정하면 `Unexpected item` 오류). 실측 `make check` → `[OK] No errors`.
2. **✅ 완료** ShopOrderController/ShopPaymentController에 `: array`/`: Response` 반환 타입 보완, 수정 후 82/82 컨트롤러 리플렉션 로딩 성공; 재발 방지 스모크 스크립트는 단계 1 산출물 참조.
3. InstallController step1 끝에 countries 시드와 결제 방식/HS Code/운임 관세 예제 자동 임포트, 새로 설치하면 개봉 즉시 데이터 보유.
4. **✅ 완료** ShopDashboardController의 kpi/chartData 라우팅(kebab→클래스명 정확 매칭)과 `$this->json` 시그니처 충돌 수정(`$this->json(0,'ok',$data)`로 변경), 하드코딩 예제 데이터 교체.
5. **✅ 완료** CI에 composer audit 단계(`||` 폴백으로 알려진 저위험 CVE로 차단하지 않음)와 phpstan 단계 추가, 의존성 보안을 게이트에 편입.

## 6. 기동 순서 제안

**먼저 단계 1(지혈과 거래 메인 링크 연결) 기동**: 4개 단말 거래 링크 단절과 admin 치명 오류는 출시 차단급 문제; 컨트롤러 시그니처 수정, 주문 청구, 시드 임포트, 양쪽 단말 결제 연결은 서로 독립적으로 병렬 가능하며 1-4주면 효과 확인; 먼저 메인 링크를 뚫어야 이후 컴플라이언스와 품질 게이트에 검증 가능한 베이스라인을 제공할 수 있습니다.

## 부록

- **팀 구조**: 조정 계층(Team Lead, 시스템 아키텍트) → 서버 소분대(백엔드/결제 정산/검색 추천/백엔드 풀스택) → 클라이언트 소분대(Flutter, 하모니오스) → 횡적 지원(보안 컴플라이언스, QA, DevOps, 크로스보더 i18n), 자세한 내용은 루트 `CLAUDE.md`와 팀 계획 논의 참조.
- **조사 상세**: `PLAN-RESEARCH.md`(7개 영역: 서버 API / 관리 백엔드 / Flutter / 하모니오스 / 보안 컴플라이언스 / 배포 데이터 테스트 / 문서 기능 커버리지).
