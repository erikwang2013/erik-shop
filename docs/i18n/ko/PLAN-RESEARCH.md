# Erik Shop 팀 조사 상세 (7개 영역)

> **생성 시점**: 2026-08 · **생성 방식**: 다중 에이전트 팀 병렬 조사(실제 코드 증거 기반, 추측 금지)
> **동반 문서**: `PLAN.md`(통합 후 프로젝트 계획, 리뷰 조정과 구현 상태 포함)
> **리뷰 기록**: 2026-08 리뷰 엔지니어가 코드 대조로 18개 주장 재검증(16건 정확, 2건은 작업 영역에서 이미 수정되어 부분 정확); 본 상세의 PHPStan 수정 제안은 PHPStan 2.x 실제 능력에 맞게 수정됨(CLI 인자 전달로 neon 설정 대체)
> **각 영역 구조**: 현황 요약 / 구현됨 / 격차 / 리스크 / 제안(제안 접두사 [고]/[중]/[저]는 우선순위)

---

## 1. 서버 비즈니스 API (service/)

### 현황 요약
기초 아키텍처와 보안/결제/검색/추천 스켈레톤은 탄탄합니다(39 컨트롤러 + 111 모델 + 14 미들웨어 + 10 스케줄 작업, Stripe/PayPal 실제 사용 가능, 단위 테스트 22개 통과). 그러나 여러 문서가 "완전"을 주장하는 기능이 실제로는 플레이스홀더이거나 연결되지 않았습니다: Klarna/Adyen 게이트웨이는 설정만 존재, 주문 시 쿠폰/운임/관세/리스크 미계산, 읽기/쓰기 분리 미활성화, 비즈니스 시드 데이터 부재로 새로 설치하면 핵심 인터페이스에 데이터가 없습니다.

### 구현됨
- 결제 게이트웨이 이중 구현(실제 코드): PaymentGateway.php의 Stripe(PaymentIntent + webhook 서명 검증 + 환불)와 PayPal(REST v2 OAuth2 + 주문/캡처/환불 + verify-webhook-signature 5필드 서명 검증 + 환불 capture id 파싱)이 완전 실행 가능하며, PaymentController::webhook이 주문 상태 원자 게이트로 중복 입금을 방지하고 트랜잭션 내에서 PlatformSettlements 분배 기록을 생성
- 결제 링크 클로즈드 루프: PaymentController(create/status/methods/webhook), AdminOpsController::executeRefund가 실제 게이트웨이 환불 실행 후 트랜잭션 DB 저장(환불 건 + 결제 기록 + 주문 상태 + 로그), PaymentReconcileCron이 6시간마다 게이트웨이 실제 상태로 2시간 초과 대기 결제 대사
- 미들웨어 스택 14개가 순서대로 동작(config/middleware.php): Cors→Security(security-php SecurityGuard 25+ 클래스 탐지기 + Redis 무차별 대입 카운트)→RateLimit(Redis 슬라이딩 윈도우, 6+ 엔드포인트 규칙)→Platform(8플랫폼 식별)→GeoIp(MaxMind)→Locale→HashidsDecode→VersionRoute(API-Version header)→HashidsEncode→Encryption, 라우팅 레벨 PosterVerify/JwtAuth/AdminKey
- 다통화 가격 책정과 다국어 상품 실제 구현: ProductSkuPrices 통화별 독립 가격 + ExchangeRates 환율 폴백, ProductTranslations를 locale 기준 eager load(ProductController에 VAT 세금 포함/미포함 표시 가격 계산 포함)
- 관세 추정 실제 사용 가능: TariffController가 ProductHsCodes→TariffRules(dest_country+hs_code)→VatSettings로 duty/vat 계산(면세 한도 임계값과 disclaimer 포함); ShippingController가 물류 구역 + 중량 계단으로 운임 계산
- 검색과 추천 실제 구현: SearchController가 webman-scout(Products 모델 Searchable + ES 매핑 erik_shop_products) 사용, ES 예외 시 MySQL LIKE 폴백 및 SearchLogs 기록; RecommendationCron이 최근 90일 구매 동시발생 계산으로 Top10을 product_recommendations에 기록, RecommendationController가 item-based CF + 인기 폴백
- 주문 핵심 프로세스: store 트랜잭션 내 원자 재고 차감(where stock>=qty decrement, 초과 판매 방지), 취소 시 재고 복원, KYC/판매 금지 차단 진입점, 상태 머신 0-8; CouponController::claim 행 잠금 lockForUpdate + 원자 게이트로 초과 발급 방지
- 스케줄 작업 10개가 전부 config/process.php에 등록(환율/물류 궤적/Feed/추천/컴플라이언스/반품 타임아웃/가격 알림/결제 대사/분배/다중 플랫폼 동기화), 모두 오류 로그와 미설정 시 스킵 로직 포함
- 문서 내보내기 실제 사용 가능: ExportController PhpSpreadsheet XLSX+CSV(HS Code 컬럼 포함), DocumentController 상업 인보이스/포장 명세서(dompdf), HealthController 헬스 체크(db/redis 이중 검사)
- 품질 도구 체인 완비: PHPUnit 12.5(22 tests/45 assertions, Security/Jwt/ApiResponse/RedisFacade 4개 파일), phpstan level 5(phpstan.neon에 Eloquent 오탐 면제 포함), php-cs-fixer, .github/workflows/ci.yml(PHP 8.3/8.4 + MySQL + Redis 매트릭스)
- 인프라 패턴 실제 적용: BaseModel Snowflake 기본 키, Hashids 인코딩/디코딩 미들웨어 자동 변환, Jwt.php access/refresh 이중 token(JwtAuth가 refresh를 비즈니스 인터페이스에 거부), encryptable 필드 암호화, config/risk.php+country.php+geoip.php 운영 설정 완비

### 격차
- Klarna/Adyen/Afterpay 플레이스홀더뿐: PaymentGateway::make()의 match가 stripe/paypal만 지원(default 예외), PaymentController::methods가 명시적 filter로 stripe/paypal만 반환(주석 "미구현 게이트웨이 노출 방지" 자인); 그러나 docs/api.md 6.1 응답 예제에 Klarna 행 포함, features.md 1.0이 Klarna BNPL/Adyen 주장, 문서와 코드 불일치
- 주문 시 쿠폰/운임/관세/리스크 미통합: OrderController::store가 상품 소계만 합산, 문서화된 coupon_id 읽지 않음(api.md 5.3), erik_orders에 이미 존재하는 shipping_fee/tax_amount/discount_amount/insurance_fee 필드 미계산; config/risk.php는 존재하지만 app/ 내 RiskEngine 호출 0건(features.md 3.3이 "가격 계산(통화별+쿠폰)"과 "리스크 스코어링(RiskEngine::score)" 주장)
- 비즈니스 시드 데이터 부재: install.sql에 INSERT 2건뿐(wa_options/wa_roles 시스템 테이블), erik_hs_codes/erik_tariff_rules/erik_payment_gateway_methods/erik_shipping_zones/erik_countries 전부 데이터 없음; database/seeders는 countries.php 하나뿐이고 이를 로드하는 코드 없음(죽은 파일, CLAUDE.md가 국가/HS Code/환율/물류 구역/컴플라이언스 분류/사이즈 표/리스크 규칙 커버 주장)——새로 설치하면 countries/payment methods/운임/관세 인터페이스가 빈 값 반환
- 읽기/쓰기 분리 설정 미활성화: config/database.php가 mysql_rw 정의(2개 읽기 복제본 + sticky)하지만 app/과 config/에 해당 연결명 참조 코드 없음, 모든 모델이 기본 mysql 사용; features.md 5.x의 "DB 읽기/쓰기 분리(2개 읽기 복제본+sticky) 완전" 주장은 명실상부하지 않음
- 구독 정기 구매와 다중 플랫폼 상품 등록은 테이블 구조만: Subscriptions/SubscriptionOrders/SubscriptionLogs, PlatformListings 모델은 존재하지만 컨트롤러/라우팅/기록 코드 없음(다중 플랫폼은 외부 URL 의존 PlatformOrderSyncCron 조회만); features.md가 둘 다 "완전" 주장
- ES 다국어 검색 선언 과대: CLAUDE.md가 "ES 인덱스가 모든 언어 title/description 포함 및 locale별 가중치" 주장하지만 Products::toSearchableArray()는 기본 단일 언어 필드만 인덱싱; CLAUDE.md가 주장하는 app/search/ 디렉터리는 실제로 존재하지 않음(Searchable이 모델에 인라인)
- 고객 서비스 WebSocket IM 미구현: ChatSessions/ChatMessages 테이블 구조만(features.md가 "WS 구현 대기" 자인, 일치하지만 미완성 확실), 주문 상태 머신 "심사 대기(8)/환불 진행 중(6)"에 기록 경로 없음(심사 프로세스 없음, 환불은 admin executeRefund만으로 기환불(7) 직행)
- 테스트 커버리지 좁고 문서 표현과 차이: 단위 테스트 파일 4개뿐(AUDIT-REPORT.md가 "통합 테스트 없음/커버리지 보고서 없음" 자인), 컨트롤러/주문/결제/미들웨어 통합 테스트 없음; api.md 13.18이 내보내기 CSV 반환 주장, 코드는 실제 XLSX 기본 + CSV 옵션; api.md 2.1의 문서화된 min_price/max_price 필터 파라미터가 ProductController::index에 미구현

### 리스크
- 결제 링크 멱등 부재와 이벤트 커버리지 부족: POST /api/payment/create에 멱등 키 없음, 반복 요청이 대기 결제 여러 건 생성; webhook이 payment_intent.succeeded / PAYMENT.CAPTURE.COMPLETED만 처리, refunded/failed 등 이벤트 조용히 무시, PaymentReconcileCron(2시간 초과 건만 조회) 폴백 의존
- 분배 기준 이중 출처 드리프트: webhook과 SettlementCron이 각각 config('payment.gateway_fee.*')와 config('cron.payment_gateway_fee_*')에서 요율을 읽어 두 곳이 독립 유지; webhook이 PlatformSettlements(status=0) 즉시 생성 후 SettlementCron이 order_id 기준 중복 제거 재계산, 중복/기준 불일치 리스크; 분배가 플랫폼 수수료 + 게이트웨이 수수료뿐, supplier_amount에 지급 프로세스 없음, affiliate_amount 항상 0, 다중 통화 분배 정산(docs/08-multi-currency-settlement) 미클로즈드
- 새로 배포하면 데이터 공백 + 컴플라이언스 기본 방출: 비즈니스 시드 전부 부재 + config/country.php blocked_countries 기본 빈 배열, kyc_required_countries는 KR만, OrderController의 판매 금지/KYC 차단이 수동 설정 의존, 설정 누락 시 완전 개방
- 검색 의존 취약: ES 불가 시 전체 try/catch가 MySQL LIKE로 폴백, scout 동기화에 큐 없음(config/scout.php sync.queue=false), 인덱스와 다국어 분석기에 CI 커버리지 없음, 인덱스 드리프트 통제 불가
- 재무 정밀도와 상태 머신 격차: 주문 금액을 float 누적 round; 환불은 전체 주문 status=7뿐 부분 환불 없음; Refunds 상태 머신 2(기각)/3(기환불)이 AdminOpsController 단일 경로로만 전이, 사용자측 환불 신청과 심사 인터페이스 없음(features.md 3.5 반품 프로세스의 심사/면단계는 admin 단말 의존)

### 제안
- [고] 비즈니스 시드 데이터 보완: database/seeders/countries.php를 Web 설치 마법사 또는 최초 기동 프로세스에 연결하고, HS Code 기본 라이브러리, 기본 결제 방식(stripe/paypal 각 method 행), VAT/관세 규칙 예제, 물류 구역 시드 신규; 아니면 새 배포 핵심 인터페이스(countries/payment methods/tariff/shipping)가 빈 값 반환
- [고] 주문 실제 청구 연결: OrderController::store에 쿠폰 할인(coupon_id 문서화됨), shipping_fee/tax_amount/discount_amount DB 저장(필드 이미 존재) 연동, features.md 3.3/api.md 5.3 정렬, api.md 2.1의 min_price/max_price 필터 구현
- [고] 결제 방식 선언 수렴: 둘 중 하나——Klarna/Adyen 게이트웨이 구현(PaymentGateway::make 확장, Klarna 설정과 gateway_fee 준비됨) 또는 "플레이스홀더" 명시 표기 후 api.md 6.1 예제 수정, 프론트엔드의 사용 불가 방식 노출 방지; 동시에 payment/create에 멱등 키 추가(order_id+gateway 중복 제거)
- [중] 리스크 엔진 적용: RiskEngine::score 구현(config/risk.php의 checks/velocity 참조), 주문/결제 이벤트에 바이패스 스코어링으로 risk_logs + order.risk_score 기록(필드 존재), "심사 대기(8)" 상태와 수동 심사 프로세스 연결
- [중] 읽기/쓰기 분리 활성화 또는 문서 수정: 읽기 전용 조회에 명시적으로 mysql_rw 연결 전환(설정 준비됨), 또는 최소한 features.md에 "설정만 있고 미활성화" 표기, 설정과 구현 단절 제거
- [중] 통합 테스트와 커버리지 임계값 보완: 주문 생성(트랜잭션/재고 차감/취소), 결제 webhook(서명 검증/멱등/분배), Tariff/Shipping 계산, Hashids 인코딩/디코딩에 PHPUnit 통합 테스트 작성(CI에 MySQL/Redis 서비스 있음, 직접 재사용 가능), coverage 임계값 설정
- [중] 분배 요율 출처 통일과 분배 보완: webhook과 SettlementCron을 단일 요율 설정으로 병합; 공급업체/유통 정산 기록(MerchantSettlements/SupplierSettlements/AffiliateCommissions 테이블 생성됨)과 지급/출금 프로세스 보완, docs/08-multi-currency-settlement 다중 통화 정산 지원
- [저] 플랫폼화와 고객 서비스 WS 확장: PlatformOrderSyncCron에 amazon/eBay/Shopee 어댑터 추가 및 상품 등록을 PlatformListings에 기록(테이블 준비됨); 고객 서비스 IM에 WebSocket 서버와 메시지 송수신 구현(ChatSessions/ChatMessages 테이블 준비됨)

---

## 2. 관리 백엔드 (admin/)

### 현황 요약
관리 백엔드는 webman-admin + LayUI/Pear Admin 기반으로 완전한 설치 마법사, RBAC 권한, WAF 미들웨어 스택, 82개 컨트롤러/76개 모델의 스켈레톤을 갖추고 있지만, 비즈니스 계층이 "컨트롤러만 있고 페이지 없음": 쇼핑몰 컨트롤러 67개 중 59개가 모델만 바인딩한 순수 CRUD 스텁, 크로스보더 패널 외에 HTML 뷰 전혀 없음(메뉴 클릭 404), ShopOrder/ShopPayment 컨트롤러 2개는 메서드 시그니처 비호환으로 PHP 8.3에서 클래스 로딩 즉시 치명 오류, 주문/결제 메뉴가 실제로 사용 불가.

### 구현됨
- 컨트롤러 82개(시스템 15 + shop 67)와 모델 76개(시스템 9 + shop 67)가 전부 쌍으로 존재하고 Copyright 헤더 포함, 네임스페이스는 plugin\admin\app\controller|model 준수
- 범용 CRUD 베이스 클래스 Crud가 select/insert/update/delete와 tree/select/normal 포맷 완전 구현, 데이터 권한(dataLimit: personal/auth), desc 테이블 구조 필드 화이트리스트 필터 inputFilter, 비밀번호 Hash, afterQuery/insertInput/updateInput 등 확장 포인트 포함
- Web 설치 마법사 InstallController 실제 사용 가능: step1 DB 생성 + 충돌 테이블 검증 + 루트 install.sql 임포트(117개 테이블) + plugin/admin/config/database.php와 thinkorm.php 생성 + service/.env와 admin/.env 생성(랜덤 JWT/Hashids/AES/ADMIN_API_KEY) + SIGUSR1 리로드; step2 슈퍼 관리자 생성 후 역할 1 바인딩; importMenu가 config/menu.php를 wa_rules에 재귀 임포트
- 권한 체계 완전: AccessControl 미들웨어 + plugin\admin\api\Auth::canAccess(noNeedLogin/noNeedAuth/역할 규칙 매칭/슈퍼 관리자 * 와일드카드/401과 403 분기), wa_roles/wa_rules/wa_admin_roles 테이블 의존
- 미들웨어 스택이 features.md 4.2와 일치: SecurityMiddleware(erikwang2013/security-php SecurityGuard + 로그인 무차별 대입 5회/300s + 보안 응답 헤더), PlatformMiddleware(8플랫폼 UA 식별), HashidsDecode/HashidsEncode(요청 디코딩과 응답 *_id 필드 인코딩), AccessControl
- 메뉴 구조 config/menu.php(526줄): 시스템 그룹 6개 + 데이터 분석/쇼핑몰 관리/주문 관리/통관 세금/물류 관리/마케팅 관리/공급망 관리 7개 비즈니스 그룹 총 27개 쇼핑몰 메뉴 항목, 가중치/아이콘/라우팅 포함
- 크로스보더 패널 ShopDashboardController + ECharts 뷰(Pear Admin 테마, KPI 카드와 차트 컨테이너 5개, echarts@5.5.0 CDN 참조)
- 환불 심사 ShopRefundController가 소수의 실제 비즈니스 로직 포함 컨트롤러: 상태 머신 0 심사 대기/1 통과/2 기각/3 기환불, 기환불 표기 전에 service 내부 인터페이스 POST /api/admin/refunds/{id}/execute 호출(X-Admin-Key 인증, service측 AdminOpsController + AdminKeyMiddleware 실제 존재), 실패 시 DB 저장 거부
- 주문 내보내기 ShopExportController: PhpSpreadsheet로 Excel 생성(주문 번호/날짜/상태/통화/상품 금액/할인/운임/실결제 금액), barryvdh/laravel-dompdf로 상업 인보이스 PDF 생성(상세, 통화, 통관 신고 안내 포함)
- 모델이 Snowflake 기본 키 기반 통일(Base::boot creating이 string형 ID 자동 생성), 비즈니스 모델이 erik_ 테이블명 선언하고 service와 동일 MySQL 연결 공유(plugin.admin.mysql)
- i18n 기초 파일 존재: admin/resource/translations 아래 zh_CN/zh_HK/en/ja/ko 5개 언어 각 48키
- 품질과 배포 동반: composer.json에 phpunit ^12.5와 php-cs-fixer dev 의존성, admin/Dockerfile + docker-compose(8788 포트) 설정 완전

### 격차
- **치명 결함(실측 재현)**: ShopOrderController.php와 ShopPaymentController.php가 부모 클래스 Crud 메서드 재정의 시 시그니처 비호환(`: array`/`: Response` 반환 타입 누락), PHP 8.3 클래스 로딩 즉시 Fatal error——메뉴의「주문 목록」「결제 기록」에 접속하는 순간 크래시하고 webman 프로세스까지 오류 전파
- 쇼핑몰 컨트롤러 67개 중 59개가 `protected $model`만 있는 순수 CRUD 스텁이며, ShopDashboardController 외에 index() 메서드와 HTML 뷰 없음(view/shop/ 아래는 dashboard/index.html 하나뿐); 메뉴 href /app/admin/shop/ShopProduct/index 등이 존재하지 않는 action을 가리켜 webman 기본 라우팅 정확 매칭 후 fallback 404로 떨어짐——쇼핑몰 관리 UI 전체(상품/분류/물류/마케팅 등)가 실제로 사용 불가, JSON API만 존재
- 크로스보더 패널 데이터 링크 이중 손상: 뷰 fetch /app/admin/shop/shop-dashboard/kpi와 /chartData(kebab 표기)에 대응 라우팅 없음(클래스명 ShopDashboardController, webman App::getController가 파일명 정확 매칭 확인); ShopDashboardController::kpi/chartData가 `$this->json(['code'=>0,...])` 배열 전달로 Base::json(int $code,...) 시그니처와 충돌해 TypeError 필수 발생; 지역 분포/통화 비중/주문 상태 3개 차트가 하드코딩 예제 데이터(코드 주석 "예제 데이터" 표기), CLAUDE.md가 주장하는「물류 소요 시간」차트는 존재하지 않음
- 문서 주장과 코드 불일치: 포장 명세서 PDF, 재무 보고서 PDF(통화별 합계)가 admin에 구현 전혀 없음; 배송 관리 ShopShipmentController는 순수 스텁(HS 신고와 궤적 로직 없음); 주문 내보내기 Excel 컬럼(ShopExportController.php 44-60행)에 HS Code/관세 컬럼 없음, CLAUDE.md의「HS Code/관세/통화 포함」과 불일치; 상품「다국어 편집+통화별 가격 책정」에 대응 UI 없음(ShopProductTranslation/ShopProductSkuPrice는 스텁이고 메뉴에도 없음)
- shop 컨트롤러 40개가 menu.php에 없음(ShopMerchant/ShopPlatformAccount/ShopPlatformListing/ShopPlatformOrder/ShopRiskRule/ShopRiskLog/ShopCms/ShopGiftCard/ShopMembership/ShopPointRule/ShopSubscription/ShopB2b/ShopAbTest/ShopCountry/ShopCurrency/ShopExchangeRate/ShopEmailTemplate/ShopNotification/ShopOperationLog/ShopUserKyc/ShopSetting/ShopOrderDocument/ShopSizeChart/ShopKnowledgeBase/ShopFaq/ShopProductAttr/ShopProductCompliance/ShopProductFeed/ShopPriceAlert/ShopPrivacy/ShopInsurance/ShopInventoryTransfer/ShopApiDoc/ShopShop/ShopMerchantProduct/ShopMerchantSettlement/ShopCountryCompliance/ShopProductHsCode/ShopProductTranslation/ShopProductSkuPrice), 메뉴 진입점 없이 URL 직접 접속만 가능
- 테스트 커버리지 제로: admin/에 tests/ 디렉터리 없음, phpunit.xml 없음, phpunit ^12.5는 composer require-dev에만 존재(AUDIT-REPORT.md도 "Admin측 자동화 테스트는 여전히 비어 있음" 인정); php-cs-fixer가 dev 의존성에 있지만 .php-cs-fixer 설정 없음, CI 없음
- i18n이 인터페이스에 미연결: 5개 언어 번역 파일은 존재하지만 플러그인 뷰와 컨트롤러에 trans()/__() 호출 0건(grep 결과 없음), index.html 상단에 언어 전환 버튼 없음, CLAUDE.md의「LayUI 인터페이스 텍스트가 trans() 함수로 번역, 언어 전환 버튼은 상단 내비게이션 바에」와 불일치
- ShopPaymentController의 의도「결제 기록 읽기 전용」의 insert/update/delete 차단 로직이 시그니처 오류로 완전 무력화; ShopOrderController의 의도「주문 직접 생성/수정 불가」비즈니스 제약도 동일하게 적용 불가

### 리스크
- 출시 차단급: ShopOrderController/ShopPaymentController가 클래스 로딩 즉시 Fatal error(PHP 8.3 실측), 주문 목록/결제 기록 메뉴 2개가 여는 즉시 오류, PHP 치명 오류는 webman 상주 프로세스 전체를 오류 후 재시작하게 함
- 「스텁 컨트롤러」다수 존재(59/67) + 메뉴와 문서의 완전 기능 주장이 개발/운영의 기능 출시 오판 유발(메뉴 있고, 테이블 있고, API는 404 또는 빈 데이터), 고오해성 기술 부채
- HashidsEncode가 응답의 모든 *_id/id 숫자 문자열을 인코딩(40000 미만 int 미인코딩 임계값 분기 포함), 향후 비즈니스 필드가 실수로 encodeFields에 들어가거나 테이블에 non-snowflake 숫자 ID가 있으면 프론트/백엔드 ID 의미 혼란 + 테스트 폴백 없음
- install.sql과 InstallController의 하드코딩 $tables_to_install 충돌 테이블 목록(약 117항)이 이중 유지, 새 테이블 추가 시 충돌 탐지 누락 쉬움, install.sql에 저장 프로시저/트리거 포함 시 splitSqlFile이 세미콜론으로 잘라 파괴할 수도 있음(현재 SQL에 해당 내용 없음, 잠재 리스크)
- Crud::selectInput이 6개 요소 반환인데 select()는 5개만 구조 분해($page 유실, 페이징은 Illuminate 전역 요청 파라미터 의존), doSelect가 like 외 문자열 연산자 미처리 등 경계 조건, 테스트 부재와 겹쳐 이후 변경 회귀 리스크 높음

### 제안
- [고] 시그니처 비호환 수정: ShopOrderController::insertInput/updateInput에 (Request $request): array, ShopPaymentController::insert/update/delete에 : Response 반환 타입 보완, 커밋 전 스모크 스크립트(php -l + 전체 82개 컨트롤러 리플렉션 로딩) 신규로 재발 방지
- [고] 크로스보더 패널 데이터 링크 수정: 뷰 fetch URL을 /app/admin/shop/ShopDashboard/kpi와 /chartData로 변경(또는 kebab 라우팅 별칭 추가), kpi/chartData를 `$this->success()`/Base::json(0,'ok',...) 규범 호출로 변경, 하드코딩 예제 차트 삭제/교체 및「물류 소요 시간」차트 보완(부재 시 문서에 사실대로 표기)
- [고] 쇼핑몰 관리 포지셔닝 명확화 후 둘 중 하나: 메뉴 내 27개 컨트롤러에 webman-admin 표준 index.html LayUI 목록 페이지 보완(컨트롤러마다 index() 뷰 렌더 추가), 또는 menu.php에서 404 메뉴 제거 후 "JSON API only" 표기; 우선 주문/환불/배송 등 P0 모듈 페이지 처리
- [중] admin 테스트 스켈레톤 구축: phpunit.xml과 tests/ 디렉터리 신규, 우선 Crud 베이스 클래스(inputFilter/doSelect/데이터 권한), AccessControl 인증 분기, InstallController(임시 DB + mock PDO), ShopRefundController의 원격 환불 호출(mock service 엔드포인트) 커버
- [중] 문서 과대 선언 수정: CLAUDE.md의 포장 명세서 PDF, 재무 보고서 PDF, 배송 HS 신고/궤적, 주문 내보내기 HS/관세 컬럼, i18n 언어 전환 버튼 등 코드와 불일치하는 설명을 실제 기준 삭제 또는 TODO 표기, 계획 오해 방지
- [중] 이중 소스 테이블 목록 제거: InstallController의 테이블 충돌 목록을 install.sql의 CREATE TABLE 파싱으로 동적 생성하거나, 검증 스크립트 제공으로 양쪽 일치 대조
- [저] i18n 연결: 뷰/컨트롤러에서 trans() 호출하고 index.html 상단에 언어 전환 버튼 추가(파일 준비됨, 배선만 남음), 또는 i18n이 service API 반환값만 대상임을 명확화
- [저] 품질 도구 보완: .php-cs-fixer.php 설정 신규 후 CI 연결(admin에 phpunit + php-cs-fixer --dry-run 실행), AUDIT-REPORT.md가 이미 나열한「Admin 테스트 추가」후속 항목 이어받기

---

## 3. Flutter 클라이언트 (apps/flutter/)

### 현황 요약
Flutter 클라이언트 스켈레톤은 완전합니다(11페이지, 라우팅 11개, 5개 언어 단어장 테이블, Dio 인터셉터 3개와 백엔드 미들웨어 정렬) 하지만 "브라우징 가능한 데모급" 상태입니다: 주문/가입/결제 3대 거래 클로즈드 루프가 address_id와 PosterVerify 휴먼 인증 부재로 백엔드에서 422/40001로 직접 거부되고, i18n은 1개 페이지만 연결, 다통화는 API에 미연결.

### 구현됨
- 기술 스택과 엔지니어링 스켈레톤 실제 존재: pubspec.yaml/lock이 flutter_riverpod ^2.3.0, go_router ^12.0.0, dio ^5.3.0, responsive_framework, cached_network_image, flutter_secure_storage, shared_preferences, intl ^0.20.2 잠금; lib/에 Dart 파일 25개, android/ios/macos/linux/windows/web 6개 플랫폼 디렉터리 완비
- GoRouter 라우팅 11개(app_router.dart): /, /products, /product/:id, /cart, /checkout, /orders, /profile, /addresses, /login, /register, /order/:id, 대응 페이지 파일 11개 모두 실제 존재
- i18n 인프라: app_localizations.dart에 하드코딩 5개 언어(zh_CN/zh_HK/en/ja/ko) 각 32개 번역 키; locale_provider.dart가 Riverpod StateNotifier + SharedPreferences로 언어/통화 영구화, localeProvider/currencyProvider 등록됨
- Dio 인터셉터와 백엔드 미들웨어 정렬: _AuthInterceptor(Bearer token + 401 시 /auth/refresh 호출 재시도), _LocaleInterceptor(Accept-Language + API-Version header, 백엔드 LocaleMiddleware/VersionRoute 대응), _PlatformInterceptor(X-Platform header, 백엔드 PlatformMiddleware 대응)
- API 계층 계약 일치: ApiResponse{code,msg,data}와 PaginatedData{list,total,page,per_page}가 백엔드 ApiResponse::success/paginate 통일 형식 매칭; apiBaseUrl이 --dart-define 덮어쓰기 지원, Android 에뮬레이터 10.0.2.2 특수 처리
- home_screen PC/태블릿 적응 구현: >1024는 NavigationRail 사이드바 + 4열 그리드, 좁은 화면은 NavigationBar 하단 Tab + 2열 그리드(main.dart에서 MOBILE/TABLET/DESKTOP 3단계 브레이크포인트 정의); product_list 데스크톱 왼쪽 240px 가격 RangeSlider 사이드바
- 상품 모듈 사용 가능: 목록이 keyword/category_id/sort 파라미터 지원(백엔드 ProductController::index 모두 지원, price_asc/desc, sales, newest 정렬 포함), 상세 페이지에 SKU ChoiceChip, 장바구니 추가 POST /cart(백엔드 CartController::store가 재고 검증 후 동일 SKU 수량 병합); ProductCard 클릭으로 상세 진입
- 장바구니 사용 가능: 목록 필드(id/title/image/price/quantity/selected)가 백엔드 CartController::index 출력과 일치, DELETE /cart/{id} 지원, 결제 진입이 /checkout으로 이동
- 주문 모듈 기초 사용 가능: 목록(order_no/status_text/pay_amount/currency_code가 OrderController::index 정렬), 상세(items 상세 포함), 취소 POST /orders/{id}/cancel(백엔드 OrderController::cancel 존재)
- 주소 관리 사용 가능: /user/addresses의 목록/추가/삭제/기본 설정이 백엔드 UserController 4개 인터페이스와 정렬, 폼에 기본 주소 표기 포함
- 인증 기초 사용 가능: login/register가 /auth/login, /auth/register 호출 후 saveTokens로 flutter_secure_storage에 저장(Token 안전 저장), init() 기동 시 로그인 상태 복원; AuthService와 ApiClient가 동일 스토리지 키 공유
- 테스트와 품질 도구: test/widget_test.dart 스모크 테스트(testWidgets 1건, ShopApp 렌더 검증); analysis_options.yaml이 flutter_lints 기본 규칙 세트 활성화

### 격차
- **주문 클로즈드 루프 단절(치명)**: CheckoutScreen._placeOrder가 {currency_code}만 제출하지만 OrderController::store가 address_id 강제 검증(기본값이면 422「배송 주소 없음」, docs/api.md 5.3도 address_id 명시 요구); config/poster.php가 /api/orders를 protected_routes에 포함해 라우팅에 PosterVerify 미들웨어 연결, Flutter가 X-Poster-Token 미전송 → 주문은 반드시 40001「휴먼 인증 필요」거부
- 결제 완전 부재: checkout_screen이 GET /payment/methods로 방식 목록만 표시, POST /payment/create와 GET /payment/status를 한 번도 호출하지 않음, 주문 후 결제 발행/결과 폴링 없음, docs/features.md 2.2 결제 시퀀스(C→POST /payment/create→SDK 결제→webhook)와 불일치
- 가입이 휴먼 인증에 차단: POST /auth/register가 PosterVerify 보호(poster.php 설정), RegisterScreen이 X-Poster-Token 획득/휴대 미구현, 가입 요청은 반드시 40001 거부
- i18n이 테이블만 만들고 미적용: AppLocalizations.of가 profile_screen.dart에서만 실제 호출(전체 lib에서 1곳), 나머지 11개 화면 약 66곳이 하드코딩 중/영문 문구(home 'Home', cart 'Shopping Cart', register '이메일과 비밀번호를 입력해 주세요', order_detail '주문이 취소되었습니다' 중영 혼합),「5개 언어 인터페이스」약속 실현 불가
- 문서와 실제 불일치: apps/CLAUDE.md가「라우팅 10개」주장하지만 실제 11개(/order/:id 추가); 기술 스택에 fl_chart, window_manager 포함 주장하지만 pubspec.yaml/lock 모두 해당 패키지 없음; features.md「Flutter 5플랫폼」인데 6개 플랫폼 항목 나열
- 다통화가 API에 미연결: 클라이언트 currencyProvider가 로컬 포맷에만 사용, 상품 목록/상세/장바구니 요청 모두 currency 파라미터 미포함(백엔드 기본 USD); ProductDetailScreen이 하드코딩 '$' 사용 및 product.display_price 읽기(백엔드가 display_price를 sku 레벨에만 붙여 product 레벨은 항상 null)→ VAT 세금 포함 행이 영원히 표시되지 않음
- 페이징과 필터 불완전: ProductListScreen의 _page가 절대 증가하지 않음, 스크롤 로딩 없음(첫 페이지 20건만 볼 수 있음); OrderListScreen 페이징 없음; 데스크톱 가격 RangeSlider가 min_price/max_price 전달하지만 백엔드 ProductController::index에 가격 필터 로직 없음(정렬이 min_price 참조만)→ 슬라이더 무효
- 견고성과 로그인 상태 결함: home 외 각 화면 _load에 try/catch 없음, 미로그인 /orders, /user/addresses 접속 시 401 DioException 미포착(로딩 상태 멈춤/미처리 예외); GoRouter에 redirect 로그인 가드 전혀 없음(redirect-cnt=0), 미로그인 상태로 /cart /checkout /orders /addresses 직행 가능; Profile「로그아웃」이 context.push('/login')로 AuthService.logout() 호출이 아님, token을 지우지 않음, 기능 버그
- 죽은 코드와 테스트 격차: ProductReviewList(product_review_list.dart)가 구현됐지만 어떤 페이지에서도 참조하지 않음, 상품 상세가 평가 미표시; 테스트는 스모크 1건뿐, 모델/컴포넌트/통합 테스트 없음; .github/workflows/ci.yml이 PHP만 커버(phpunit+문법), Flutter analyze/test 작업 없음; assets/images 디렉터리가 비어 있는데 pubspec.yaml이 해당 asset 디렉터리 선언

### 리스크
- 핵심 거래 링크가 Flutter측에서 사용 불가: 주문(address_id 부재 + PosterVerify 40001), 결제(/payment/create 없음), 가입(PosterVerify 40001) 3곳 모두 백엔드 거부, 현 코드대로 출시하면 구매 전환 직접 차단
- 로그인 가드 없음 + 401 갱신 로직에 동시성 중복 제거 없음: 다중 요청 동시 401 시 /auth/refresh 동시 호출(api_client.dart 미잠금), refresh 실패 시 로그아웃 폴백 없음, token 상태 불일치 가능
- i18n 이중 체제(단어장 테이블 + 66곳 하드코딩)가 장기 공존하면 인터페이스 언어 혼란, 신규 문구가 바로 하드코딩되고, 5개 언어 약속과 docs/VERSIONS.md「국제화 ✅」선언 실현 불가, 재작업 비용 누적
- 다통화 표시와 실제 결제 단절: 인터페이스에서 JPY/KRW 전환 가능하지만 가격은 여전히 달러 하드코딩 표시, API도 USD 기준 계산, 다통화 정산 금액이 맞지 않아 거래 일관성 리스크
- Flutter CI 게이트 없음 + flutter/dart analyze가 본 환경에서 SDK 읽기 전용으로 실행 검증 불가: 25개 파일을 수동 리뷰에 의존, 컴파일/정적 문제 회귀 리스크 높음(docs/VERSIONS.md에 기록된 intl 충돌, pending Timer 등 역사적 문제가 자동화 방어 부재)

### 제안
- [고] 주문 클로즈드 루프 연결: 결제 페이지에 주소 선택(/user/addresses 재사용, 기본 주소 백필), address_id+currency_code 제출, 백엔드 PosterVerify 검증 프로세스 연동(X-Poster-Token 획득 후 POST /orders), 이어서 POST /payment/create + GET /payment/status 폴링 결제 페이지 구현
- [고] AppLocalizations 전량 연결: 11개 화면 66곳 하드코딩 문구를 translate(key)로 교체하고 누락 키 보완(주소 폼, 주문 상태, 오류 안내 등), AppTheme.supportedLocales와 locale_provider.supportedLocales 중복 정의 삭제, 단일 소스 통일
- [고] GoRouter redirect 로그인 가드 추가(미로그인 /cart /checkout /orders /addresses 접속 시 /login 리다이렉트), Profile「로그아웃」을 AuthService.logout() 호출 후 홈 복귀로 변경, 로그인 상태 관련 페이지 상태 정리
- [중] 전체 화면 _load에 try/catch와 오류 상태/빈 상태 UI 추가(현재 home만 예외 폴백); ApiClient 401 갱신에 단발 잠금과 실패 로그아웃 폴백; 장바구니 수량 증감 보완(PUT /cart/{id})
- [중] 상품 상세/목록 요청에 currency 파라미터 포함, 가격을 sku.display_price 또는 display_price 필드 읽기로 변경, 하드코딩 '$' 전부 CurrencyFormatter로 교체; 백엔드 ProductController::index에 min_price/max_price 필터 추가, 프론트엔드 스크롤 페이징 구현
- [중] RegisterScreen 및 민감 작업 PosterVerify 연동: 슬라이더/퍼즐 검증으로 X-Poster-Token 획득(백엔드 poster 검증 인터페이스 또는 프론트엔드 통합), 가입/주문이 40001에 차단되지 않도록 보장
- [저] Flutter 테스트 보완: Product/Order 모델 fromJson 단위 테스트, 라우팅 스모크(11개 라우팅 도달 가능성), 장바구니/주소 widget 테스트, GitHub Actions에 flutter analyze + flutter test 작업 신규(PHP ci.yml 정렬)
- [저] 문서와 죽은 코드 수정: apps/CLAUDE.md 라우팅 수 10→11, fl_chart/window_manager 선언 제거; ProductReviewList를 상품 상세 페이지에 연결하거나 삭제; 빈 assets/images 디렉터리 정리 또는 플레이스홀더 리소스 보완

---

## 4. 하모니오스 클라이언트 (apps/harmonyos/)

### 현황 요약
하모니오스 클라이언트(HarmonyOS NEXT API 12+, ArkTS + ArkUI)가 컴파일 가능한 9페이지 + ApiClient/AppState/ProductCard 완전 스켈레톤을 갖추고, 백엔드 API 엔드포인트와 응답 구조가 전부 실제 매칭됩니다(AUDIT-REPORT에 ArkTS 오류 27건 수정, 빌드 성공 기록). 그러나 기능 깊이는 "표시 계층"에 머무릅니다: 결제-주문 메인 링크 단절(address_id 부재), Profile이 정적 셸, 다통화/다국어 미연결, 테스트와 CI 없음, 빌드 산출물 99개 오입고, 전반적으로 Flutter 클라이언트와 격차가 뚜렷합니다.

### 구현됨
- ArkTS 페이지 9개가 모두 존재하고 main_pages.json에 등록(Index/ProductDetail/Cart/OrderList/Checkout/Profile/Login/Register/Search), EntryAbility, ApiClient, AppState, ProductCard 추가, 컴파일 가능(entry/build 캐시와 AUDIT-REPORT.md M3 수정 기록이 증거, B+ 등급)
- ApiClient가 @ohos.net.http 래핑: GET/POST/DELETE, Bearer token, API-Version(2026-05-20), Accept-Language, X-Platform: harmonyos header, 선언형 QueryParams/RequestBody 인터페이스로 ArkTS 리터럴 제약 충족
- AppState 싱글턴: token/locale/currency를 @ohos.data.preferences로 영구화(erik_shop 저장소), cartCount를 /cart 조회로 계산, logout이 token 정리
- 백엔드 라우팅과 클라이언트 호출이 항목별 매칭: /auth/login, /auth/register, /products, /products/{id}, /banners, /search, /cart(GET/POST/DELETE), /orders(GET/POST), /shipping/calculate, /payment/methods가 전부 service/config/route.php에 등록되고 컨트롤러 존재
- 응답 구조와 클라이언트 파싱 일치: products/orders/search가 data.list 반환(status_text 중국어 매핑, sort=sales 지원 포함), cart가 items 배열 반환(title/image/price/quantity), shipping이 data.options 반환, payment/methods가 stripe/paypal만 노출
- 홈 구현: Banner 캐러셀(/banners?position=home) + 인기 상품 2열 Grid(/products?per_page=10&sort=sales), 상단 검색 바와 장바구니 아이콘 진입점 포함
- 검색 페이지 구현: 키워드 검색(/search?keyword=&per_page=40), 결과 카운트, 빈 상태와 loading 상태, ProductCard 재사용
- 장바구니 페이지 구현: 목록/합계 계산/삭제(DELETE /cart/{id})와 빈 상태 표시, 결제로 이동 가능
- 상품 상세 페이지 구현: 로딩 상태, 메인 이미지/제목/가격/설명 표시, 장바구니 추가(첫 번째 SKU로 POST /cart)
- 주문 목록 구현: Tabs 상태 필터(전체/결제 대기/배송 완료/주문 완료 → status 0/2/4)와 loading/빈 상태
- 로그인/가입 페이지가 /auth/login, /auth/register 호출, 가입은 AppState.setToken으로 영구화
- 결제 페이지가 주문 상품/운임 옵션(Radio 선택)/결제 방식 표시 및 합계 계산, 제출 액션 지원
- 플랫폼 식별 링크 완전: X-Platform: harmonyos가 service/app/middleware/PlatformMiddleware.php의 8플랫폼 화이트리스트와 매칭
- 엔지니어링 설정 충족: compatibleSdkVersion 5.0.0(12)(API 12+), stageMode, deviceTypes phone/tablet/2in1, hvigor modelVersion 5.0.0

### 격차
- **결제-주문 메인 링크 단절**: Checkout.ets placeOrder가 {currency_code:'USD'}만 전달, 백엔드 OrderController.php:88-96이 address_id 필수+검증(없으면 422 배송 주소 없음); CartController.php:113이 selected=1 상품만 정산하는데 Cart.ets에 체크 기능 없음; 클라이언트에 주소 관리 페이지 전혀 없음(Profile 배송 주소 메뉴 route가 비어 있음)——주문은 반드시 실패
- 결제 프로세스 미연결: Checkout 페이지가 결제 방식을 표시·선택하지만 placeOrder에 결제 파라미터를 전달하지 않고 POST /payment/create를 호출하지 않음, docs/features.md「결제(Stripe/PayPal) 완전」과 불일치
- Profile.ets가 정적 셸: isLoggedIn @State 초기 false이고 AppState를 전혀 읽지 않음(로그인 후에도 로그인/가입 표시);「로그인/가입」항목에 onClick 없음; 즐겨찾기/배송 주소/기프트 카드/언어/통화/프라이버시 설정 6개 메뉴 route 전부 비어 있어 사용 불가; 로그아웃 진입점 없음
- 로그인 상태 관리 이중 체제 불일치: Login.ets가 getPreferences로 access_token/refresh_token을 직접 작성해 AppState.setToken을 우회, AppState 메모리 token 미동기화(isLoggedIn()이 false 반환); Register.ets는 AppState 경유, 두 경로 분열; 로그인 성공 후 cartCount 미갱신
- 홈 분류 진입점이 영원히 공백: Index.ets loadData가 /banners와 /products만 요청, categories 배열에 아무 할당 없음; Banner에 클릭 이동 없음; 상단 Search 컴포넌트에 onSubmit 없어 검색 페이지 진입 불가
- 다통화/다국어 미연결: AppState.currency가 영구화된 후 API에 전달된 적 없음(Checkout이 country:'US'/currency:'USD' 하드코딩, shipping이 dest_country_id:1/weight:500 하드코딩); UI 문구 전부 중국어와 '$' 하드코딩(docs/features.md 293행도「ArkTS 하드코딩」인정), en_US 등 리소스 디렉터리 없음, Flutter 5개 언어와 격차 뚜렷
- 테스트와 품질 게이트 없음: apps/harmonyos 아래 ohosTest 디렉터리 없음, .ets 테스트 없음; .github/workflows/ci.yml이 PHP 문법 검사 + 단위 테스트만, 하모니오스 빌드 job 없음; lint/포맷 도구 설정 없음
- 저장소 위생 문제: git이 빌드 산출물 99개(entry/build와 .hvigor 캐시) 추적(추적 파일 131개의 76% 차지, msgpack/tsbuildinfo/컴파일 보고서 포함), .gitignore에 하모니오스 무시 규칙 없음; 저장소에 hvigorw wrapper 스크립트 없음(apps/CLAUDE.md가 주장하는 `hvigorw assembleHap`를 직접 실행 불가, 전역 hvigor 또는 DevEco Studio 필요)
- ApiClient 견고성 부족: request/JSON.parse에 try-catch 없음, 타임아웃 설정 없음; delete()에 X-Platform header 누락; refresh_token 저장 후 갱신에 사용된 적 없음; EntryAbility.onCreate에서 AppState.init()에 await 없음, 첫 프레임 페이지 요청이 token 준비 전에 발생할 수 있음(경쟁); 기본 baseUrl이 http://10.0.2.2:8787/api 하드코딩으로 에뮬레이터에만 적합

### 리스크
- 핵심 거래 클로즈드 루프 미연결: 결제 주문이 반드시 422 반환(address_id 부재), docs/features.md의「완전」포지셔닝대로 외부 출시하면 메인 경로가 직접 실패, 출시 차단급 결함
- 테스트 없음 + CI에 하모니오스 job 없음: ArkTS 엄격 타입(리터럴/단일 루트 build 제약) 회귀 리스크 높음, AUDIT-REPORT M3의 컴파일 오류 27건이 선례, 이후 어떤 페이지 수정도 자동화 보장 없음
- 빌드 산출물 입고 + wrapper 없음: 저장소 볼륨 비대(msgpack 캐시 등 바이너리), 무의미한 diff 발생 쉬움, 새 환경에서 문서 명령으로 빌드 재현 불가, CI의 하모니오스 연결도 통일 빌드 진입점 부족
- 상태 관리 이중 체제(AppState 싱글턴 vs 페이지 로컬 @State + Login의 preferences 직접 기록)와 반응형 메커니즘 부재: 이후 즐겨찾기/주소/통화 전환 등 공유 상태 연결 시 메모리와 영구화 불일치 발생 쉬움
- 실기기/출시 적응 부재: 기본 baseUrl이 Android 에뮬레이터 주소이고 플랫폼 인지 메커니즘 없음(Flutter는 M4 수정 완료), HarmonyOS 실기기와 프로덕션 HTTPS 환경 사용 불가

### 제안
- [고] 결제-주문 클로즈드 루프 연결: 배송 주소 목록/신규 페이지 신규로 UserAddresses 관련 API 연동(백엔드 구비), Cart 페이지에 selected 체크 추가(백엔드가 selected=1 상품만 정산), Checkout.placeOrder에 address_id + selectedShipping + 통화 전달, POST /orders 성공 검증
- [고] Profile과 로그인 상태 일관성 수정: Profile.aboutToAppear에서 AppState.isLoggedIn() 읽고 반응형 갱신,「로그인/가입」클릭으로 Login 이동, 로그아웃 신규; Login/Register 통일로 전부 AppState.setToken 경유 및 refreshCartCount 호출
- [고] 테스트와 CI 게이트 구축: ohosTest(ArkXTest) 신규로 최소한 ApiClient 요청 파싱(mock server 주입 가능)과 AppState 영구화 읽기/쓰기 커버; ci.yml에 hvigor 빌드 job 추가(전역 hvigor 사용 또는 wrapper 보완), 컴파일 회귀 차단
- [중] 저장소 정리: .gitignore에 apps/harmonyos/**/build, **/.hvigor, **/oh_modules 규칙 추가 및 git rm --cached로 입고된 산출물 99개 정리; hvigorw wrapper 보완(ohpm으로 @ohos/hvigor 설치)으로 apps/CLAUDE.md 명령 사용 가능하게
- [중] 다통화/다국어 연결: Checkout/Index/ProductDetail이 AppState.currency와 QueryParams.currency 전달로 변경(백엔드 다통화 가격 책정 지원); UI 문구를 resources/base 및 en_US 언어 디렉터리로 이전, 우선 영어 보완으로 Flutter의 i18n 인터셉터 방식 정렬
- [중] 홈과 검색 경험 보완: loadData에 /categories 추가로 분류 진입점 Grid 로드, Banner 클릭 시 link_url 이동, 상단 검색창 onSubmit으로 Search 페이지 진입; Search 결과 페이징 연결(현재 per_page 40 일괄 조회)
- [중] ApiClient 견고성 강화: try/catch와 타임아웃(http.RequestOptions timeout) 통일, 401 시 저장된 refresh_token으로 자동 갱신 재시도, delete()에 X-Platform header 보완, baseUrl 런타임 설정 지원(Flutter 플랫폼 인지 방식 모방)
- [저] 초기화 경쟁과 상세 페이지 수정: EntryAbility에서 await AppState.init() 후 loadContent(또는 페이지 대기 준비); ProductDetail에 다중 SKU 선택 UI와「즉시 구매」실제 주문 액션 추가, 상품 이미지 Image 캐시 연결

---

## 5. 보안과 컴플라이언스

### 현황 요약
플랫폼이 WAF 공격 탐지, JWT 이중 token, AES 인터페이스 암호화, Encryptable 필드 암호화, Hashids 난독화, 결제 Webhook 서명 검증과 키 관리에서 실제적이고 비교적 완전한 구현을 갖추고 있습니다(테스트 22개 전부 통과). 그러나 리스크 규칙 엔진, KYC, GDPR/CCPA 실행 계층은 테이블 구조와 관리 단말 CRUD만 있고 핵심 비즈니스 로직이 부재하며, docs/features.md, docs/VERSIONS.md가 주장하는 "완전/✅"와 불일치합니다.

### 구현됨
- WAF 공격 탐지: service와 admin의 SecurityMiddleware가 모두 erikwang2013/security-php v1.1.6 SecurityGuard를 래핑, config/plugin/erikwang2013/security-php/app.php가 탐지기 31개 설정(28개 block, 3개 log: header_injection/ssti/nosql_injection), XSS/SQLi/XXE/SSRF/경로 탐색/파일 업로드/CSRF/Host/DNS rebinding 등 포함, 추가로 IP 블랙리스트(5회/60s→900s 차단), 보안 응답 헤더(nosniff/DENY/Permissions-Policy/Server 숨김)
- 무차별 대입 방어: service측 Redis 카운터 erik_brute:{ip}:{login|register} 10회/60s(SecurityMiddleware::checkBrute), admin측 5회/300s
- JWT 인증: config/jwt.php(HS256, access 7200s/refresh 1209600s, issuer/audience/leeway), app/common/Jwt.php 빈 키 fail-closed(JWT_SECRET→JWT_SECRET_KEY 폴백 체인), JwtAuth 미들웨어가 non-access 유형 token 거부, AuthController 가입/로그인 이중 token 발급, refresh 엔드포인트가 이중 token 교체(firebase/php-jwt v6.11.1 잠금)
- AES 인터페이스 암호화: app/common/Encryption.php(AES-256-CBC, 요청마다 랜덤 IV, base64(iv+암호문), 키 길이 검증 16/24/32바이트), EncryptionMiddleware가 X-Encrypted:1 요청 복호화, X-Encrypt-Response:1/X-Encrypt-Fields 응답 필드 암호화 지원, /api/health, /api/ping, /apidoc 제외, 전역 미들웨어 스택 마지막 단계로 등록
- Encryptable 필드 암호화: 모델 31개가 Erik\Encryptable\Encryptable trait 사용(Users의 email/mobile, UserKyc의 real_name/id_number, UserAddresses, PrivacyRequests.email, PaymentGateways.api_key 등), 비밀번호는 bcrypt(password+salt) + 사용자별 랜덤 솔트, $hidden으로 직렬화 유출 차단
- Hashids 난독화: config/hashids.php + 플러그인 설정, HashidsHelper 빈 솔트 fail-closed, HashidsDecode(라우팅 파라미터 + _id 끝 필드 자동 디코딩)와 HashidsEncode 미들웨어가 service/admin 모두 활성화, 컨트롤러가 외부에 인코딩 ID 반환
- 결제 보안: StripeGateway가 Stripe\Webhook::constructEvent로 서명 검증, PayPalGateway가 공식 /v1/notifications/verify-webhook-signature로 서명 검증(PAYPAL_WEBHOOK_ID 필요); PaymentController::webhook 서명 검증→멱등 업데이트(주문 status=0 원자 게이트로 중복 입금 방지)→PlatformSettlements 분배 생성; create 인터페이스가 구현된 stripe/paypal만 노출; erik_payments 테이블에 three_ds_status 필드 포함; admin 환불 실행 인터페이스가 AdminKeyMiddleware(X-Admin-Key, hash_equals 비교) 경유
- 키 관리: .env.example/.env에 JWT_SECRET/JWT_SECRET_KEY/HASHIDS_SALT/ENCRYPTION_KEY/ADMIN_API_KEY/STRIPE_SECRET_KEY 등, .env는 .gitignore에 포함; Web 설치 마법사(InstallController)가 random_bytes로 랜덤 키 생성; Jwt/Encryption/HashidsHelper가 누락 키에 전부 fail-closed
- 속도 제한과 휴먼 인증: RateLimitMiddleware Redis ZSET 슬라이딩 윈도우(기본 60s/100회, 로그인 60s/10, 가입 300s/5, 결제 60s/5, 주문 10s/3, 검색 1s/10), PosterVerify가 가입/주문/결제 등 민감 라우팅 보호
- 테스트와 품질 도구: tests/ 총 22 tests/45 assertions 실측 ALL PASS(SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3), phpunit.xml, phpstan.neon(level 5), .php-cs-fixer.php, .github/workflows/ci.yml(PHP 8.3/8.4 매트릭스 + MySQL/Redis 서비스); README가 composer audit 알려진 저위험 CVE 1건 기록(firebase/php-jwt <7.0.0, jwt-webman ^6.0 제약)

### 격차
- 리스크 규칙 엔진 미구현: docs/features.md §3.3이 주문 프로세스에「리스크 스코어링(RiskEngine::score)」포함 주장, VERSIONS.md가「리스크 규칙 엔진 ✅」주장하지만 전 프로젝트 grep RiskEngine 0건; erik_orders.risk_score/risk_result 필드가 어떤 코드에도 기록된 적 없음, RiskRules/RiskLogs는 빈 모델뿐, config/risk.php는 설정만 있고 실행 지점 없음; service/database/seeders/에 countries.php만, CLAUDE.md가 주장하는「리스크 규칙」시드 없음; ShopRiskRuleController/ShopRiskLogController가 admin 메뉴에 미연결
- KYC 사용자측 제출 진입점 없음: erik_user_kyc 테이블/UserKyc 모델/admin CRUD 컨트롤러 존재, OrderController가 주문 시 kyc_required 국가(config/country.php KR만) status=1 검증하지만 service/config/route.php에 KYC 제출/조회 라우팅 전혀 없음, 사용자가 자가 실명 자료 제출 불가, 클로즈드 루프 부재
- GDPR/CCPA가 요청 등록만 있고 실행 로직 없음: PrivacyController가 privacy_requests 기록(status=pending)과 30일 내 처리 약속만, 데이터 삭제/내보내기/opt-out 실제 실행 코드 없음; ShopPrivacyController는 상태 CRUD만; config/privacy.php의 data_retention/retain_on_deletion에 대응 정리 스케줄 작업 없음; erik_cookie_consents 테이블에 기록 엔드포인트 없음(Cookie Consent 프론트 컴포넌트나 API 없음)
- 클라이언트가 AES 인터페이스 암호화를 소비하지 않음 + 하모니오스측 token 평문 저장: Flutter/HarmonyOS 요청이 Authorization/Accept-Language/API-Version/X-Platform만 휴대, grep에 X-Encrypted/X-Encrypt-Response 지원 없음(인터페이스 암호화는 서버측 단방향 능력뿐, docs가 말하는「3계층 암호화」가 단말에서 미적용); 하모니오스 AppState가 @ohos.data.preferences로 token 저장(평문), Flutter는 flutter_secure_storage, 단말 간 보안 불일치
- 3DS 명시 코드 증거 없음: StripeGateway::createPayment가 payment_method_options[card][request_three_d_secure]를 설정하지 않음, three_ds_status 필드가 기록된 적 없음, 3DS는 Stripe 기본 정책 의존; README/features.md가 주장하는「3DS 검증」에 코드 근거 없음; Klarna/Adyen은 config 플레이스홀더뿐(PaymentController 주석이 프론트엔드 필터 설명), README의「Stripe/PayPal/Klarna/Adyen, BNPL」주장과 불일치
- 인증 누락 항목: 비밀번호 찾기/재설정 프로세스 없음(grep forgot/reset 0건), 이메일 검증 없음, JWT 폐기 메커니즘 없음(비밀번호 변경/로그아웃 후에도 token 유효), refresh 엔드포인트에 별도 속도 제한 없음
- CI에 의존성 보안 감사와 정적 분석 미편입: composer audit이 README 문서에만 존재, ci.yml은 PHP 문법 검사 + PHPUnit뿐, composer audit/phpstan/php-cs-fixer 단계 없음; phpstan.neon excludePaths가 config/plugin 제외(보안 플러그인 설정 포함)

### 리스크
- 의존성 보안 차단: firebase/php-jwt v6.11.1이 문서 선언 CVE-2025-45769(<7.0.0) 영향 범위, erikwang2013/jwt-webman ^6.0 하드 제약으로 업그레이드 불가, 장기 미해결 알려진 취약점 노출(HS256 대칭 사용 방식은 영향 없지만 상위 추적 지속 필요)
- 탐지 커버리지 사각: csrf_origin/host_header/dns_rebinding/request_smuggling 등 header류 탐지기가 $_SERVER 의존, Workerman 비-CGI 환경에서 누락 가능(docs/security-review.md §5.1 자술); IP 블랙리스트 file 저장이 sys_get_temp_dir에 있어 Docker 재시작 시 유실, 다중 인스턴스 비공유, 공격자가 IP 교체로 우회 가능(설정에 redis 저장 예약되어 있으나 미활성화)
- 컴플라이언스 선언과 구현 불일치 노출: 문서로「리스크 완전/KYC 완전/GDPR 완전」대외 주장, 실제 주문 생성에 리스크 스코어링 전혀 없이 방출(사기 주문 리스크), KYC 자가 제출 불가, 삭제 요청 실행자 없음, 이를 컴플라이언스 능력으로 대외 약속하면 실질 컴플라이언스 리스크
- 키 관리 취약: 설치 마법사가 bin2hex(random_bytes(16))로 ENCRYPTION_KEY 생성(hex 문자 32개=128bit 엔트로피, 256bit 미달), bin2hex(random_bytes(8))로 HASHIDS_SALT 생성(64bit 엔트로피); ENCRYPTION_PREVIOUS_KEYS에 순환 자동화 없음; webman 상주 프로세스는 .env 수정 시 reload 필요

### 제안
- [고] 리스크 규칙 엔진 구현: config/risk.php + erik_risk_rules 테이블 기준 RiskEngine::score 적용(이벤트 user_register/user_login/order_create/payment_create/refund_request), OrderController::store/PaymentController::create/AuthController에서 호출, risk_score/risk_result와 RiskLogs 기록, 바이패스 모드에서 고득점 주문 status=8 심사 대기 설정(주문 상태 머신「심사 대기」분기 대응), ShopRiskRule/ShopRiskLog를 admin 메뉴에 연결
- [고] KYC 클로즈드 루프 보완: POST /api/kyc 신규(실명 자료 제출, real_name/id_number Encryptable 암호화), GET /api/kyc/status, admin 심사 통과 시 status=1 설정, OrderController 기존 검증과 연결; KYC 시드/예제 데이터 보완
- [고] GDPR/CCPA 실행 계층 구현: 프라이버시 요청 처리 스케줄 작업 신규(retain_on_deletion 기준 세금 필드 유지, deleted_user_grace 30일 유예 후 사용자 데이터 삭제, data_portability 내보내기 파일 생성, opt_out 차단 표기); Cookie Consent 컴포넌트와 POST /api/privacy/cookie-consent로 erik_cookie_consents 기록; data_retention 설정을 정리 작업으로 적용
- [고] 클라이언트 인터페이스 암호화와 안전 저장 연결: Flutter/HarmonyOS가 X-Encrypted/X-Encrypt-Response 지원(키는 안전 채널 협상 하달), 하모니오스측 token을 KeyStore/security.asset 저장으로 변경해 preferences 평문 대체
- [중] 결제 보안 강화: Stripe createPayment에 명시적 request_three_d_secure='automatic' 설정 및 three_ds_status 기록; payments 조회/환불 인터페이스에 사용자 귀속 검증 보완(status는 검증됨, 환불/내보내기 재검토 필요); README/VERSIONS의 Klarna/Adyen「완전」표현 동기 수정 또는 구현 보완
- [중] 의존성 보안 CI 편입: ci.yml에 composer audit, phpstan(config/plugin 해제 또는 별도 검증)과 php-cs-fixer --dry-run 단계 추가; CVE 추적 구축, jwt-webman이 php-jwt ^7 지원 시 즉시 업그레이드
- [중] 인증 강화: 비밀번호 재설정 프로세스 구현(이메일 인증 코드 + 일회성 재설정 token); JWT 폐기(Redis 블랙리스트 또는 token 버전 번호, 비밀번호 변경/로그아웃 후 무효); refresh 엔드포인트에 속도 제한과 재생 탐지 연결
- [저] 키와 탐지 강화: ENCRYPTION_KEY를 raw 32바이트 base64 키(256bit 엔트로피)로 변경, hashids 솔트를 ≥16바이트로 강화; security-php storage를 redis 모드로 전환해 IP 블랙리스트 공유; security-review.md §5.1 제안대로 SecurityGuard의 $meta에 header 명시 전달, header류 탐지 보완

---

## 6. 배포 / 데이터 / 테스트 품질

### 현황 요약
Erik Shop의 배포 오케스트레이션(nginx→service:8787/admin:8788 + MySQL/Redis/ES), 117개 테이블 구조와 단위 테스트(22 tests/45 assertions 실측 전부 통과) 기반이 탄탄하고 문서-코드가 대체로 일치합니다. 그러나 정적 분석 도구가 개봉 즉시 크래시(PHPStan 128M 메모리 제한), admin측 품질 설정 완전 부재, GeoIP 데이터 파일 부재, 일부 스케줄 작업이 외부 API 미설정으로 실제 공회전, 프로덕션 컨테이너에 dev 의존성과 무인증 미들웨어 노출 리스크가 있습니다.

### 구현됨
- docker-compose.yml이 6개 서비스 완전 오케스트레이션(nginx/service/admin/mysql/redis/elasticsearch), 전부 healthcheck + depends_on 조건 시작 + 명명 데이터 볼륨 + 브리지 네트워크, `docker compose config` 실측 검증 통과; nginx가 docker/nginx/conf.d/shop.conf로 keepalive upstream 리버스 프록시 service:8787과 admin:8788(api.erik.xyz/admin.erik.xyz 두 가상 호스트)
- service/Dockerfile과 admin/Dockerfile 모두 php:8.3-cli-alpine 기반, pdo_mysql/bcmath/opcache/gd/intl/sockets/redis 등 확장 설치 및 composer install --no-dev --optimize-autoloader(service측에 OPCache 프로덕션 설정 docker/opcache.ini 포함)
- CI(.github/workflows/ci.yml)가 PHP 8.3/8.4 매트릭스 + MySQL 8.0/Redis 7 서비스 컨테이너 설정, composer install, php -l 문법 검사(service+admin 디렉터리)와 PHPUnit 실행; Makefile이 start/stop/test/lint/check/fix/install/docker-up 등 14개 명령 제공
- PHPUnit 실측 실행 통과: 22 tests / 45 assertions ALL PASS(SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3, phpunit.xml이 12.5 스키마 사용)
- phpstan.neon level 5 설정(paths=app+config, Eloquent/webman 동적 메서드 ignoreErrors 포함); 실측 --memory-limit=1G에서 0 오류
- service/.php-cs-fixer.php가 PSR-12 + no_unused_imports/ordered_imports 등 규칙 설정, app+config 커버; .editorconfig가 인코딩/들여쓰기 통일
- install.sql 실측 117개 테이블 포함(wa_ 시스템 테이블 7개 + erik_ 비즈니스 테이블 110개, InnoDB/utf8mb4_unicode_ci), service측 비즈니스 모델 110개와 erik_ 테이블 110개 일대일 대응(B2B/구독/공급망 등 12개 모듈 테이블 완비), MySQL 공식 이미지 docker-entrypoint-initdb.d가 자동 임포트
- Web 설치 마법사 실제 존재(admin/plugin/admin/app/controller/InstallController.php step1/step2), 루트 install.sql 임포트 및 service/.env와 admin/.env 생성(랜덤 JWT/Hashids/AES 키 포함); 키류 .env 파일은 .gitignore 제외, 미입고
- 스케줄 작업 프로세스 10개(config/process.php에 exchange_rate/shipment_tracking/product_feed/recommendation/compliance/return_expire/price_alert/payment_reconcile/settlement/platform_order_sync cron 등록)가 각각 독립 상주 프로세스와 주기 보유
- 미들웨어 스택이 문서대로 등록: Cors→Security→RateLimit→Platform→GeoIp→Locale→HashidsDecode→VersionRoute→HashidsEncode→Encryption(config/middleware.php 실측 전역 10개 + PosterVerify/JwtAuth/AdminKey 라우팅 레벨, 미들웨어 14개가 문서와 일치)
- docs/api.md의 API 엔드포인트 71개가 service/config/route.php와 실측 대체로 일대일 대응(/health 헬스 체크가 db+redis 실제 탐지 포함); docs/features.md의 Flutter 25파일, HarmonyOS 14파일, Admin 82컨트롤러/76모델이 전부 코드 통계와 일치

### 격차
- PHPStan 개봉 즉시 사용 불가: phpstan.neon에 memoryLimit 미설정, 기본 128M에서 병렬 worker가 직접 크래시(실측 재현 'reached configured PHP memory limit: 128M'), Makefile check 대상과 CI 모두 --memory-limit 미포함, 정적 분석 게이트가 실제로 실행 불가
- admin측 품질 설정 전부 부재: phpstan.neon 없음, .php-cs-fixer.php 없음, phpunit.xml 없음, tests/ 디렉터리 없음, composer.json require-dev에 phpstan 없음; 실측 `make fix`의 admin 구간(admin && vendor/bin/php-cs-fixer fix)이 설정 없을 때 'create config file?' 인터랙티브 프롬프트 진입으로 멈춤, `make check`도 service만 커버
- CI가 phpstan과 php-cs-fixer 미통합(php -l + PHPUnit뿐), service만 테스트; CI가 MySQL/Redis 서비스 컨테이너를 기동했지만 MySQL에 연결하는 통합 테스트 전혀 없음, 테스트 커버리지는 유틸리티 클래스 4개에 머물고 모델 111/컨트롤러 39/미들웨어 14/스케줄 작업 10은 테스트 제로
- GeoIP 데이터 파일 부재: config/geoip.php가 service/database/geoip/GeoLite2-Country.mmdb를 가리키지만 해당 디렉터리 실측 빈 상태, 다운로드/유도 스크립트 없음, GeoIpMiddleware는 file_exists 폴백 분기만 실행, features.md의 'GeoIP 완전' 주장은 명실상부하지 않음
- 스케줄 작업 3개가 외부 API 미설정으로 공회전: config/cron.php의 tracking_api_url, compliance_source_url, platform_sync_url이 모두 빈 문자열, ShipmentTrackingCron/ComplianceCron/PlatformOrderSyncCron이 '스킵' 로그만 기록 가능(코드 주석도 확인); 고객 서비스 WebSocket 실시간 IM 미구현(features.md 'WS 구현 대기' 자술, chat 컨트롤러/WS 프로세스 없음)
- 프로덕션 배포 이미지가 소스 볼륨에 덮어써짐: docker-compose.yml이 ./service:/app, ./admin:/app을 컨테이너에 마운트해 Dockerfile의 COPY + composer install --no-dev 산출물을 덮어쓰고, service/, admin/ 모두 .dockerignore 없음, 프로덕션 컨테이너가 실제로 호스트 vendor(dev 의존성 포함) 실행
- 문서 불일치와 유휴 설정: docs/deployment.md 두 곳이 admin 리슨 8787 / 'admin.erik.xyz → admin:8787' 표기(실제 8788); nginx가 ./service/public:/var/www/static:ro 마운트하지만 어떤 server 블록도 해당 정적 디렉터리를 사용하지 않음
- Elasticsearch와 Redis 보안 취약: compose에서 ES가 xpack.security.enabled=false이고 9200 포트를 호스트에 노출, 인증 전혀 없음; Redis requirepass가 ${REDIS_PASS:-} 의존 기본 빈 비밀번호이고 6379 노출, .env 미설정 시 미들웨어가 무방비

### 리스크
- 프로덕션 환경 키/인증 부재 연쇄 리스크: compose 기본 플레이스홀더(change_me류)를 교체하지 않아도 기동 가능, ES 무인증, Redis 기본 비밀번호 없음, 서비스 포트 전면 노출, .env 설정이 불완전해도 바로 출시되면 공격면이 9200/6379/3306/80 커버
- 테스트 품질 명목화 리스크: 단위 테스트 22개가 유틸리티 클래스만 커버, 모델/컨트롤러/미들웨어/DB 통합 테스트 전혀 없음, CI에 정적 분석 게이트 없음(PHPStan 크래시, php-cs-fixer CI 미진입), 리팩터링과 머지 무방비, 회귀 문제는 수동에 의존
- 프로덕션 컨테이너 dev 의존성 실행: 소스 볼륨 마운트가 이미지를 덮어쓰고 .dockerignore 없음, --no-dev 최적화가 우회된 후 컨테이너 내 vendor에 PHPUnit/phpstan 등 dev 패키지 포함, 이미지 비대 + '프로덕션 dev 의존성 없음' 규약 위반
- 외부 의존 공회전으로 데이터 신뢰도 리스크: 물류 궤적/컴플라이언스 규칙/플랫폼 주문 동기화 cron 3개가 기본적으로 실제 동기화를 실행하지 않음, 운영진이 '이미 자동화됨'으로 오판하면 궤적 미갱신, 컴플라이언스 규칙 만료, 다중 플랫폼 주문 동기화 누락의 조용한 장애 발생
- GeoIP 폴백으로 지역 가격/언어 식별 무력화: mmdb 부재 시 모든 요청이 config('geoip.default')로 폴백(고정 US/USD/en), 타지역 사용자가 미국 기본 가격과 언어로 표시되어 다통화/다국어 핵심 강점 정확도에 직접 영향

### 제안
- [고] PHPStan 게이트 수정: **하지 말 것** phpstan.neon에 memoryLimit 설정(PHPStan 2.2.8이 해당 neon 파라미터 제거, 설정 시 `Unexpected item` 오류), 대신 `make check`와 CI의 phpstan 명령에 `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` 포함, 실측 0 오류 통과 가능(적용 완료, docs/PLAN.md 구현 상태 참조)
- [고] admin 품질 설정 보완: admin/phpstan.neon(level 5, paths=app+plugin/admin/app)과 admin/.php-cs-fixer.php(service 규칙 재사용) 신규, admin을 CI의 phpstan/php-cs-fixer --dry-run 검사에 편입; admin 품질 설정 적용 전까지 Makefile fix 대상에서 admin 구간 임시 제거로 인터랙티브 멈춤 방지
- [고] 프로덕션 이미지 빌드 수정: docker-compose.yml에서 ./service:/app과 ./admin:/app 소스 마운트 제거(또는 runtime/logs 디렉터리만 마운트), service/.dockerignore와 admin/.dockerignore 신규(vendor/runtime/.git 등 제외), 컨테이너 내 --no-dev vendor만 실행 보장
- [고] 통합 테스트 보완 및 CI 연결: CI가 이미 기동한 MySQL/Redis 서비스 컨테이너 활용, service/tests 아래 DB 스모크/라우팅 레벨 통합 테스트 신규(예: install.sql 임포트 가능성, 헬스 체크, 가입-로그인 클로즈드 루프), '22 tests'를 순수 단위 테스트에서 회귀 방지 게이트로 확장
- [중] GeoIP 데이터 파일 해결: 스크립트/문서로 GeoLite2-Country.mmdb 다운로드 유도(service/database/geoip/ 배치) 또는 config의 MAXMIND_LICENSE_KEY 자동 갱신 활성화, README/INSTALL에 부재 시 US 기본값 폴백 영향 명기
- [중] 미들웨어 보안 노출면 축소: docker-compose.yml에서 ES/Redis/MySQL 포트 바인딩을 127.0.0.1로 변경(nginx만 80/443 노출), ES xpack 인증 활성화 또는 compose 주석에 프로덕션 필수 REDIS_PASS/ES 보안 그룹 명시, 무방비 출시 방지
- [중] 외부 의존 공회전 제거: config/cron.php의 빈 URL 3곳에 눈에 띄는 주석 보완 및 로그를 WARNING 레벨로 상향(또는 관리 백엔드 설정 진입점 제공), features.md에서 '물류 궤적/컴플라이언스 갱신/다중 플랫폼 동기화' 상태를 '완전'에서 '외부 API 설정 의존'으로 변경, 코드 사실과 정렬
- [저] 문서와 유휴 설정 정리: docs/deployment.md의 admin 포트 8787→8788 오기 2곳 수정; nginx 마운트의 미사용 ./service/public:/var/www/static:ro 볼륨 삭제 또는 정적 파일 server 블록 보완; features.md/README에서 '고객 서비스 WebSocket IM 미구현(테이블 구조만)' 명확화로 영업 구두 오해 방지

---

## 7. 문서와 기능 커버리지

### 현황 요약
문서 체계가 완비되어 있습니다(아키텍처 다이어그램 8장 SVG+MMD, api.md/architecture/design/deployment/VERSIONS/AUDIT 등 문서 9건) 있고 대부분 숫자 기준이 코드와 일치합니다(라우팅 엔드포인트 73개, 테이블 117개, 22 tests/45 assertions 실측 통과, service/admin 각 5개 언어 × 45개 번역, 통화 시드 19종). 그러나 features.md/VERSIONS.md/README가 다중 플랫폼 등록, 리스크 규칙 엔진, Klarna/Adyen 결제, 4라인 분배, 상업 인보이스 PDF, 구독 정기 구매/AB 테스트, WebSocket 고객 서비스 등을 "완전/✅"로 표기한 기능이 실제로는 테이블 구조 + admin CRUD 또는 비즈니스 구현 전무로, 체계적인 "문서가 코드를 앞서감" 상태입니다.

### 구현됨
- 아키텍처 다이어그램 8장 완비(01-08 SVG가 모두 실제 렌더링 산출물 15-153KB, 대응 .mmd 소스 포함), docs/diagrams.md 도면 색인과 일대일 대응
- service 라우팅 실제 73개(공개 23 + 인증 47 + Webhook 1 + Admin 1 + /health 1), docs/api.md 71개 엔드포인트, architecture-full.md 73개 엔드포인트와 대체로 일치; 공개 엔드포인트 23개가 전부 route.php에 존재
- service 컨트롤러 39/모델 111/미들웨어 14, admin 모델 76/미들웨어 5, Cron 프로세스 10개(process.php), install.sql 테이블 117개(erik_ 110 + wa_ 7)가 README 숫자와 전부 일치
- 테스트 실제 실행 가능: phpunit 실측 22 tests/45 assertions ALL PASS(SecurityTest 12 + JwtTest 4 + ApiResponseTest 3 + RedisFacadeTest 3); phpstan level5, php-cs-fixer, Makefile 14개 명령, CI(PHP 8.3/8.4 매트릭스 + MySQL + Redis) 전부 존재
- i18n 적용: service/admin 각 5개 언어 × 45개 번역 파일, Flutter 수기 AppLocalizations 5개 언어 + SharedPreferences 영구화, LocaleMiddleware 5개 언어 매칭, Accept-Language/API-Version/X-Platform header 규범 완비
- 결제: Stripe(PaymentIntent + client_secret + 3DS)와 PayPal(REST v2 OAuth2 + Webhook 5필드 서명 검증) 완전 게이트웨이 구현; Webhook 서명 검증→결제/주문 업데이트→PlatformSettlements 생성 트랜잭션 클로즈드 루프(원자 게이트로 중복 입금 방지 포함)
- 소셜 로그인 Google/Apple/Facebook id_token fail-closed 검증(tokeninfo/JWKS/debug_token) + 바인딩/이메일 탈취 방지 로직; ExportController XLSX+CSV에 HS Code 컬럼 포함; B2B 견적/공동구매/플래시 세일/쿠폰 락 등 비즈니스 실제 존재
- 보안: security-php 탐지기 31개 설정(service/admin 일치), RateLimitMiddleware 7개 규칙(기본 + 6개 엔드포인트), DB 읽기/쓰기 분리 읽기 복제본 2개 + sticky=true, SoftDeletes 모델 45개, PosterVerify(slide/click/rotate) Redis 검증
- 다국어 상품/다통화 가격 책정(통화 시드 19종), ES 검색(scout + MySQL LIKE 폴백), ProductFeedCron Google/Meta TSV 생성, ExchangeRateCron 매시간 환율 조회, ECharts 대시보드(KPI 6개 + 차트 3개), Web 설치 마법사(DB 생성→install.sql 임포트→.env 생성→관리자 생성)
- 클라이언트: Flutter Dart 파일 25개/11페이지(Riverpod + GoRouter + responsive_framework PC/태블릿 적응, 하드코딩 중국어 2곳뿐); HarmonyOS ArkTS 9페이지(ApiClient/ProductCard/AppState 포함)

### 격차
- Klarna/Adyen 결제 미구현: PaymentGateway::make()가 stripe/paypal만 지원(service/app/common/PaymentGateway.php), PaymentController.php:34가 명시 주석 '구현된 게이트웨이만 반환, Klarna/Adyen 등 미구현 설정 노출 방지', 하지만 README.md와 VERSIONS.md가 이를 ✅로 나열, features.md만 'Stripe 완전, 기타 플레이스홀더' 인정
- 상업 인보이스/포장 명세서 PDF 미구현: composer.json에 barryvdh/laravel-dompdf 포함하지만 전 프로젝트(service/admin) Dompdf 호출 0건; DocumentController.php가 기존 erik_order_documents 기록만 읽고 생성 로직 전혀 없음(order_documents 기록도 자동 생성되지 않음), features.md는 '상업 인보이스 PDF/포장 명세서' 완전 주장
- 리스크 규칙 엔진 미구현: app/common의 클래스 8개에 RiskEngine 없음, OrderController::store에 리스크 스코어링 없음, 주문 상태 8(심사 대기)이 영원히 기록되지 않음; features.md가 '규칙 엔진(바이패스 스코어링: 주소 검증/우편번호 매칭/3DS/대량 가입/화물 가액 이상) 완전' 주장하고 주문 상태 머신에 '결제 대기→심사 대기: 리스크 고득점' 분기 포함, 실제로 도달 불가
- 4라인 분배가 1라인만 구현: webhook과 SettlementCron이 PlatformSettlements만 생성; MerchantSettlements/SupplierSettlements/AffiliatePayouts에 전 프로젝트 ::create 기록 0건(테이블 + admin CRUD만), README와 08-multi-currency-settlement 다이어그램이 '4라인 독립 정산' 주장
- 구독 정기 구매와 AB 테스트에 서버 API 없음(테이블 + admin CRUD 컨트롤러만, route.php에 대응 라우팅 없음); 다중 플랫폼 'Amazon/eBay/Shopee/Lazada/Temu 상품 등록 + 주문 집계'에 실제 플랫폼 통합 없음, PlatformOrderSyncCron이 범용 URL로 조회만(PlatformListings에 비즈니스 기록 없음); WebSocket IM 미구현인데 VERSIONS.md가 ✅ 표기(features.md/README는 정직하게 표기)
- 재고 거래 내역 불변 장부 미적용: InventoryLogs에 비즈니스 기록 전혀 없음(주문 재고 차감이 거래 내역을 기록하지 않음); CurrencyExchangeGainsLosses 환차손익 테이블도 기록 로직 없음, README가 주장하는 '재고 거래 내역(불변 장부)'과 '환차손익 추적'은 테이블 구조 계층에 머무름
- 시드 데이터가 설치와 함께 임포트되지 않음: install.sql에 테이블 구조와 wa_ 시스템 시드(wa_options/wa_roles)만, countries/currencies/payment_gateway_methods/hs_codes/shipping_zones 등 기초 데이터는 service/database/seeders/countries.php 수동 실행 필요(InstallController는 install.sql만 임포트), 새로 설치하면 상품/결제 방식/운임 계산이 개봉 즉시 빈 값; AUDIT-REPORT는 '데이터베이스 시드 데이터 OK' 표기
- hg/apidoc 동적 문서가 명실상부하지 않음: AuthController + ProductController만 @Apidoc 어노테이션 보유(59행), 나머지 컨트롤러 36개 어노테이션 0건, 6개 그룹 자동 문서 커버리지 심각하게 부족; 숫자 기준 편차 존재(admin 컨트롤러 실제 80 vs 문서 82, HarmonyOS 소스 13개 vs 문서 14, 번역 45개 vs AUDIT 48개 주장, features.md 미들웨어 파이프라인 다이어그램에 RateLimit/Encryption 누락)

### 리스크
- 문서가 체계적으로 '완전' 표기 과장(다중 플랫폼, 리스크 엔진, 구독/AB, 4라인 분배, 인보이스 PDF, Klarna/Adyen, WS 고객 서비스), 상용 라이선스 고객에게 기능 납품 기대 차이 형성, 계약과 신뢰 리스크
- 새로 설치하면 기초 데이터가 빈 값(시드 자동 임포트 없음, 마법사가 seeder 실행 안 함), countries/currencies/payment_gateway_methods 등 핵심 데이터 테이블에 데이터 없음, 상품 목록, 결제 방식, 운임/관세 계산 등 메인 링크 개봉 즉시 사용 불가
- 동적 API 문서 커버리지가 컨트롤러 2/38, Flutter/HarmonyOS 클라이언트 연동이 권위 있는 인터페이스 근거 부족; docs/api.md 정적 문서와 route.php 간 엔드포인트 드리프트 리스크(71 vs 73, features.md 내부 파이프라인 다이어그램도 불일치)
- 테스트 커버리지가 단위 테스트 22개뿐(보안 + JWT + 응답 + Redis), 비즈니스 컨트롤러 38개 테스트 0건, admin 테스트 없음, 통합 테스트와 커버리지 보고서 없음, 대량 리팩터링/업그레이드 회귀 리스크 높음
- DB의 payment_gateway_methods에 klarna/adyen 등 미구현 게이트웨이 행 여전히 포함, 설정이 실수로 활성화되면 프론트엔드에서 표시되지만 주문 후 처리 게이트웨이 없음, 결제 링크의 은닉 실패 포인트

### 제안
- [고] 전 문서에 '구현됨/테이블 구조 생성됨/계획 중' 3상태 표기 통일: features.md/VERSIONS.md/README의 Klarna/Adyen, 다중 플랫폼 등록, 리스크 엔진, 구독/AB, 4라인 분배, 인보이스 PDF, WS 고객 서비스 상태 수정, 문서가 코드를 앞서는 상황 원천 차단
- [고] 설치 마법사(admin/plugin/admin/app/controller/InstallController.php)에 기초 시드 데이터 자동 임포트 추가(countries/currencies/payment_gateway_methods/hs_codes/shipping_zones), 새로 설치 시 개봉 즉시 사용 가능 보장
- [고] 핵심 비즈니스 클로즈드 루프 보완: RiskEngine 스코어링과 주문 상태 8 구현, 이미 도입된 dompdf로 invoice/packing-list PDF 생성 구현(DocumentController를 필요 시 생성 + DB 저장으로 변경), 재고 차감 시 InventoryLogs 기록, webhook 후 Merchant/Affiliate 분배 보완
- [중] 라우팅 73개 전부에 @Apidoc 어노테이션 보완으로 hg/apidoc 6개 그룹 문서 실제 커버리지 복원; 단기 완료 불가 시 README의 apidoc 선언 하향 수정 및 docs/api.md를 권위 정적 문서로 명확화
- [중] 통합 테스트 추가: CI에 이미 설정된 MySQL/Redis 서비스 활용해 가입→로그인→상품→장바구니→주문→결제 mock 스모크 체인 보완, admin 핵심 CRUD 테스트 보완, 커버리지 0인 컨트롤러 38개의 회귀 방어 향상
- [중] 숫자 기준 수정: admin 컨트롤러 82→80, HarmonyOS 파일 수, 번역 key 수 48→45, features.md 미들웨어 파이프라인 다이어그램 통일(RateLimit/Encryption 보완)과 api.md 엔드포인트 목록 정렬(라우팅 73개 기준)
- [저] CurrencyExchangeGainsLosses(정산 시 환율 비교)와 PlatformListings(플랫폼 등록 기록)에 실제 비즈니스 로직 보완, 또는 '테이블 구조 생성됨'으로 표기 변경; 구현 전까지 '완전' 주장 금지
- [저] route.php↔docs/api.md 엔드포인트 일관성 검사 스크립트 구축 후 CI 편입, 문서와 코드의 추가 드리프트 자동 차단
