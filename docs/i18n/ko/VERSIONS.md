# Erik Shop — 크로스보더 전자상거래 플랫폼
webman 풀스택 제품군 기반의 풀스택 크로스보더 전자상거래 플랫폼으로, B2C/B2B 시나리오와 제3자 셀러 입점을 지원합니다.

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 버전 개요

| | 간소화판 (Lite) | 표준판 (Standard) | 전체판 (Full) |
|---|:---:|:---:|:---:|
| **포지셔닝** | 개인 개발자 / 소규모 전자상거래 | 성장형 크로스보더 셀러 | 기업급 풀스택 플랫폼 |
| **라이선스** | MIT 오픈소스 | 상용 라이선스 | 상용 라이선스 |
| **획득 방식** | GitHub 공개 다운로드 | erik@erik.xyz 문의 | erik@erik.xyz 문의 |
| **브랜치** | `lite` | `standard` | `full` |
| **현재** | — | — | ✅ |

---

## 2026-08-27 서킷 브레이커와 디그레이션

- Redis 서킷 브레이커 `CircuitBreaker` 신규(`service/app/common/CircuitBreaker.php`): 결제 게이트웨이(Stripe/PayPal/Klarna/Adyen)와 소셜 로그인 외부 호출 통합 차단 — 5회 연속 실패→30초 차단, TTL 만료 후 반개(半開) 탐지로 자동 복구
- 비즈니스 예외 화이트리스트: 유효하지 않은 카드/유효하지 않은 토큰은 차단 실패로 집계하지 않음(악성 요청으로 의존 서비스 마비 방지)
- Redis 장애 시 자동 통과(fail-open); 차단 중 인터페이스는 503「서비스를 일시적으로 사용할 수 없음」반환
- 파라미터: `config/concurrency.php` → `circuit_breaker` (fail_threshold=5, open_seconds=30)

---

## 2026-08-29 CDN 지원

- **Origin-Pull 모델**: 업로드는 admin 오리진 로컬 디스크에 유지되고 DB에는 상대 경로만 저장(마이그레이션 제로); 출력 경계에서 `Cdn::url()`이 `https://{CDN_DOMAIN}{path}`로 재작성, CDN 도메인은 admin 도메인으로 CNAME 연결
- **통합 프로바이더 추상화**: `CdnProviderInterface`(purge / purgeByTag / preload)를 Cloudflare·AWS CloudFront·Aliyun·Tencent가 구현(Fastly/Akamai 예약); 능력 매트릭스: purge 4/4, preload 2/4(Aliyun/Tencent), purgeByTag 1/4(Cloudflare)
- **admin 패널 구성**: CDN 관리 페이지(Config/Purge/Logs 3개 탭) — 프로바이더 활성화 스위치, 자격 증명(설정 JSON은 저장 시 암호화), 연결 테스트, 수동 purge/preload, purge 로그(`wa_cdn_providers`/`wa_cdn_purge_logs` 테이블); DB 설정이 .env보다 우선, 전역 on/off는 공유 Redis(prefix `shop:`, TTL 60s)로 service에 전파
- **자동 purge (fail-open)**: 상품·배너 CRUD 시 자동 purge 트리거, CDN 장애가 admin CRUD를 차단하지 않음
- **엣지 캐싱**: nginx `location /app/admin/upload/` `expires 7d; Cache-Control public, max-age=604800, immutable`; 업로드 디렉터리는 Docker 볼륨으로 영속화(admin_uploads:/app/plugin/admin/public/upload, service_public:/app/public/documents)
- **설정**: `config/cdn.php`(admin+service) + CDN_* 관련 환경 변수 13개

---

## 2026-08-07 수정 기록

| # | 문제 | 심각도 | 수정 |
|---|------|--------|------|
| 1 | API 응답 암호화가 미들웨어에 연결되지 않음 | Medium | EncryptionMiddleware 신규(X-Encrypt-Response header 기반), service 파이프라인 10번째 단계로 등록 |
| 2 | 클래스명 Encryption / 파일명 EncryptionHelper.php 불일치 | Medium | Encryption.php로 이름 변경, PSR-4 자동 로딩 수정 |
| 3 | JWT_SECRET_KEY 빈 값 | Low | 32바이트 키 생성, JWT_SECRET과 JWT_SECRET_KEY 동시 설정 |
| 4 | config/middleware.php가 인덱스 배열이라 "Bad middleware config"로 전체 worker 크래시 | Critical | `'' => [...]` 표준 구조로 변경(webman은 appName => 목록 요구) |
| 5 | security-php 플러그인 설정에 enable 키가 없어 Config::loadFromDir가 조용히 건너뜀 | Critical | service/admin 플러그인 app.php에 `'enable' => true` 추가 |
| 6 | config/bootstrap.php가 존재하지 않는 support\bootstrap\Db/Redis를 참조 | Critical | 제거; Eloquent 초기화는 support/bootstrap.php가 vendor/webman/database의 Db.php를 require하도록 변경 |
| 7 | 전역 redis() 함수 없음(webman 2.x에 없음), 속도 제한/리스크가 조용히 무력화 | High | support\Redis 퍼사드 신규(illuminate/redis + phpredis), app/functions.php에 redis() 헬퍼 함수 등록 |
| 8 | RedisManager 생성자 파라미터 누락(3개 필요: app 컨테이너/driver/config) | High | stdClass 컨테이너 플레이스홀더 + phpredis 드라이버 + 연결 설정 전달 |
| 9 | 모델이 존재하지 않는 Erik\Encryptable\Encryptable trait 참조(패키지 내부는 Maize\Encryptable 네임스페이스의 CastsAttributes) | Critical | service/Erik/Encryptable/Encryptable.php 클래식 trait 호환 레이어 신규(내부는 패키지 Encryption::php 재사용) |
| 10 | composer 플러그인 Installer.php 최상위 함수 중복 선언 fatal | Medium | function_exists 멱등 가드(service/admin 두 vendor 모두 수정 완료) |
| 11 | HashidsEncode getHeader()가 string 반환으로 implode 오류 | High | (array) 강제 변환 |
| 12 | docker-compose/.env.example에 실제 JWT/암호화 키 하드코딩 | Critical | change_me 플레이스홀더로 교체, 설치 마법사가 랜덤 키 생성 |
| 13 | 주문 생성에 트랜잭션 없음, 재고 차감 비원자적(동시성 초과 판매) | Critical | Db::transaction + 조건부 decrement 원자 차감 |
| 14 | 쿠폰 수령 동시성 초과 발급/초과 수령 | High | 트랜잭션 + 행 잠금 lockForUpdate + received_qty 원자 게이트 |
| 15 | PayPal Webhook 서명 검증 필드가 항상 빈 값(verify-webhook-signature 필수 실패) | High | 5개 서명 검증 필드를 요청 header에서 전달 |
| 16 | 설치 마법사 SQL 인젝션(DB 이름/비밀번호 문자열 결합) | High | quote + 백틱 이스케이프 + var_export로 설정 작성 |
| 17 | 암호화/해시 키 누락 시 조용한 폴백 | High | Encryption/HashidsHelper가 빈 값 또는 길이 불법일 때 예외 발생 |
| 18 | 주문 내보내기 고정 파일명 동시성 덮어쓰기 | Medium | uniqid 파일명 + shutdown 정리 + try/catch |
| 19 | Hashids 디코딩이 요청 파라미터에 다시 기록되지 않음(라우팅 파라미터/GET/POST) | High | setParams/setGet/setPost 기록 |
| 20 | composer.lock이 gitignore됨(빌드 재현 불가) | Medium | 무시 해제 후 버전 관리에 포함 |
| 21 | 컨테이너에 헬스 체크 없음, 시작 의존성 없음 | Medium | 전체 서비스 healthcheck + depends_on condition |
| 22 | admin Dockerfile 실행 불가 | High | COPY + composer install + EXPOSE + CMD 추가 |
| 23 | Flutter 컴파일 오류(intl 충돌/생성자 제네릭/불필요한 괄호) + 테스트 pending Timer | High | intl ^0.20.2, 정적 팩토리, pump로 클럭 진행 |
| 24 | HarmonyOS ArkTS 컴파일 오류 27건으로 패키징 불가 | High | 명시적 인터페이스, 예약어 이름 변경, 단일 루트 build, @kit 임포트, hvigor 설정 |

---

## 기능 비교

> 참고: ◐ = 테이블 구조 생성 완료, 비즈니스 구현 대기(현재 데이터 테이블과 모델만 존재, API/비즈니스 코드 없음 또는 일부만 구현)

### 사용자 시스템

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 이메일 가입/로그인 (JWT) | ✅ | ✅ | ✅ |
| 소셜 로그인 (Google/Apple/Facebook) | — | ✅ | ✅ |
| 주소 관리 | ✅ | ✅ | ✅ |
| 회원 등급 + 포인트 | — | — | ◐ |
| 기프트 카드 | — | — | ✅ |
| KYC 실명 인증 | — | — | ✅ |

### 상품 시스템

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 분류 관리 (트리형) | ✅ | ✅ | ✅ |
| SKU + 속성 | ✅ | ✅ | ✅ |
| 상품 이미지 | ✅ | ✅ | ✅ |
| 다국어 콘텐츠 | — | ✅ | ✅ |
| 다통화 독립 가격 책정 | — | ✅ | ✅ |
| 상품 평가 | ✅ | ✅ | ✅ |
| 컴플라이언스 라벨 (FDA/CE/RoHS) | — | ✅ | ✅ |
| ES 다국어 검색 | — | ✅ | ✅ |
| 상품 Feed 동기화 (Google/Meta) | — | — | ✅ |
| 사이즈 대조표 | — | — | ✅ |

### 거래 시스템

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 장바구니 | ✅ | ✅ | ✅ |
| 주문 관리 | ✅ | ✅ | ✅ |
| 결제 (Stripe) | ✅ | ✅ | ✅ |
| 결제 (PayPal) | ✅ | ✅ | ✅ |
| 결제 (Klarna/Adyen) | — | 플레이스홀더 | 플레이스홀더 |
| BNPL 선구매 후결제 | — | 플레이스홀더 | 플레이스홀더 |
| 환불 | ✅ | ✅ | ✅ |
| 반품 관리 | — | ✅ | ✅ |
| 상업 인보이스/포장 명세서 | — | ✅ | ✅ |
| 물류 보험 | — | — | ◐ |

### 크로스보더 물류

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 국제 물류사 관리 | — | ✅ | ✅ |
| 물류 구역 + 계단형 요율 | — | ✅ | ✅ |
| 해외 창고 (출하+반품) | — | ✅ | ✅ |
| HS 신고 | — | 계획 중 | 계획 중 |
| 물류 궤적 추적 | — | ✅ | ✅ |
| 다중 창고 재고 관리 | — | — | ✅ |

### 통관 세금

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| HS Code 코드 라이브러리 | — | ✅ | ✅ |
| 관세 규칙 설정 | — | ✅ | ✅ |
| VAT/IOSS 설정 | — | ✅ | ✅ |
| 국가별 컴플라이언스 제한 | — | ✅ | ✅ |
| 가격 표시 컴플라이언스 (세금 포함/미포함) | — | ✅ | ✅ |

### 마케팅 도구

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 쿠폰 | ✅ | ✅ | ✅ |
| 캐러셀 배너 | ✅ | ✅ | ✅ |
| 플래시 세일 | — | ✅ | ✅ |
| 공동구매 | — | ✅ | ✅ |
| 유통 (링크+커미션+출금) | — | ✅ | ✅ |
| 지역 프로모션 | — | ✅ | ✅ |

### 공급망

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 공급업체 관리 | — | — | ✅ |
| 구매 주문 | — | — | ◐ |
| 검품 (입고+출고 게이트) | — | — | ◐ |
| 재고 거래 내역 (불변 장부) | — | — | ✅ |
| 재고 이관 | — | — | ◐ |

### 플랫폼 확장

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 다중 스토어 관리 | — | — | ✅ |
| 다중 셀러 입점 (제3자 셀러) | — | — | ✅ |
| Amazon/eBay/Shopee 등록 | — | — | ✅ |
| 다중 플랫폼 주문 집계 | — | — | ✅ |
| B2B 도매 (계단형 가격/견적) | — | — | ✅ |

### 리스크 컴플라이언스

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 기본 공격 탐지 (XSS/SQLi) | ✅ | ✅ | ✅ |
| 확장 공격 탐지 (XXE/SSRF 등) | — | — | ✅ |
| PosterVerify 휴먼 인증 | — | ✅ | ✅ |
| 리스크 규칙 엔진 | — | — | ✅ |
| GDPR/CCPA 데이터 요청 | — | — | ✅ |
| Cookie Consent 관리 | — | — | ✅ |
| 플랫폼 출처 추적 | — | ✅ | ✅ |
| 플랫폼 출처 추적 (8플랫폼) | — | ✅ | ✅ |

### 고동시성

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| DB 커넥션 풀 | ✅ | ✅ | ✅ |
| 토큰 버킷 속도 제한 | — | — | ✅ |
| DB 읽기/쓰기 분리 | — | — | ✅ |
| Cron 스케줄 작업 (11개) | — | — | ✅ |
| CDN 멀티 프로바이더 (Cloudflare/CloudFront/Aliyun/Tencent) | — | ✅ | ✅ |

### 콘텐츠와 성장

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 시스템 알림 | ✅ | ✅ | ✅ |
| 이메일 템플릿 | — | — | ✅ |
| CMS 다국어 페이지 | — | — | ✅ |
| FAQ + 지식 베이스 | — | — | ◐ |
| 구독 정기 구매 | — | — | ✅ |
| AB 테스트 | — | — | ◐ |
| 실시간 고객 서비스 (WebSocket IM) | — | — | ✅ |

### 클라이언트

| 기능 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| 국제화 (5개 언어 번역) | ✅ | ✅ | ✅ |
| API 문서 (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Admin ECharts 대시보드 | ✅ | ✅ | ✅ |
| Admin Excel/PDF 내보내기 | ✅ | ✅ | ✅ |
| 다국어 인터페이스 (5개 언어) | ✅ | ✅ | ✅ |

---

## 설계 비교

### 데이터베이스

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| 데이터 테이블 | **23** | **62** | **110** |
| 사용자 관련 | 3 | 5 | 7 |
| 상품 관련 | 6 | 15 | 19 |
| 거래 관련 | 6 | 9 | 9 |
| 물류 관련 | 0 | 7 | 9 |
| 통관 관련 | 0 | 5 | 5 |
| 마케팅 관련 | 4 | 8 | 8 |
| 공급망 | 0 | 0 | 5 |
| 리스크 컴플라이언스 | 0 | 0 | 5 |
| 다중 플랫폼 | 0 | 0 | 9 |
| 콘텐츠 성장 | 0 | 1 | 14 |
| 고객 서비스/AB/API | 0 | 0 | 5 |

### 미들웨어 파이프라인

```
Lite:      Cors → Security(4종) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security(4종) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security(31종) → RateLimit(토큰 버킷) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption(인터페이스 암호화)
```

### 코드 규모

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Service 모델 | 26 | 55 | 111 |
| Service 컨트롤러 | 15 | 24 | 39 |
| Service 미들웨어 | 7 | 9+2 | 12+2 |
| Service 유틸리티 클래스 | 5 | 5 | 15 |
| Admin 모델 | 15 | 34 | 76 |
| Admin 컨트롤러 | 15 | 27 | 82 |
| Flutter 페이지 | 11 | 11 | 11 |
| HarmonyOS | — | — | 9페이지 |
| PHPUnit 테스트 | 22 | 22 | 54 |

### 기술 스택

| 컴포넌트 | Lite | Standard | Full |
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

## 업그레이드 경로

```
Lite (오픈소스) ──→ Standard (상용) ──→ Full (상용)

업그레이드 방식:
  1. erik@erik.xyz에 연락해 해당 버전 코드 획득
  2. 증분 스키마 임포트 (lite→standard 약 +40테이블, standard→Full 약 +48테이블)
  3. 해당 버전의 컨트롤러/모델/미들웨어 복사
  4. composer require로 신규 의존성 패키지 설치
```

---

## 획득 방식

| 버전 | 방식 |
|------|------|
| **간소화판 (Lite)** | GitHub 오픈소스 [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php) `lite` 브랜치 |
| **표준판 (Standard)** | 상용 라이선스 — **erik@erik.xyz** 문의 |
| **전체판 (Full)** | 상용 라이선스 — **erik@erik.xyz** 문의 |

상용 라이선스 포함: 전체 소스 코드 / 배포 지원 / 우선 업데이트 / 기술 컨설팅
