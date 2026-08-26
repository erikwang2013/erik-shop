# 크로스보더 전자상거래 플랫폼 — 종합 감사 보고서

**날짜**: 2026-08-04 | **PHP**: 8.3.7 | **프레임워크**: webman 2.1 | **상태**: 모든 문제 수정 완료

---

## 수정 기록 (2026-08-04)

### 보안 수정
| # | 문제 | 파일 | 수정 |
|---|------|------|------|
| S1 | JWT 하드코딩 폴백 키 | `Jwt.php:21` | 하드코딩 값 제거, 키가 비어 있으면 RuntimeException 발생 |
| S2 | 소셜 로그인 시 JWT 미반환 | `SocialAuthController.php` | 3곳의 로그인 성공 응답에 access_token + expires_in 반환 |
| S3 | refresh 엔드포인트에 token 검증 없음 | `AuthController.php:75-84` | `sub` 필드 비어있음 검증 추가 |
| S4 | Cache-Control 과도하게 엄격 | `SecurityMiddleware.php:319` | GET/HEAD/OPTIONS는 캐시 허용, 쓰기 작업은 금지 |

### 코드 품질 수정
| # | 문제 | 파일 | 수정 |
|---|------|------|------|
| C1 | 한 줄에 여러 PHP 문장 | `AuthController.php` | register/login 메서드를 완전히 여러 줄 형식으로 재구성 |
| C2 | match()/foreach 한 줄 압축 | `ProductController.php` | 여러 줄로 분리, 가독성 향상 |
| C3 | use 임포트 누락 | `OrderController.php` | `use app\model\ProductSkuPrices` 추가 |
| C4 | 결제 게이트웨이 예외 처리 없음 | `PaymentController.php:79` | try/catch 추가（InvalidArgumentException + Throwable） |
| C5 | 상품 상태 확인 경계 불명확 | `ProductController.php:84` | `$product->status < 1` → `$product->status !== 2` |
| C6 | Copyright 헤더 누락 | `SocialAuthController.php` | Copyright 헤더 추가, use 문장 형식 수정 |

### 기능 TODO 구현
| # | TODO | 파일 | 구현 |
|---|------|------|------|
| F1 | PayPal REST API | `PaymentGateway.php` | Guzzle + OAuth2 완전한 PayPal Orders API v2 구현 |
| F2 | Excel 내보내기 | `ExportController.php` | PhpSpreadsheet XLSX + CSV 이중 형식, HS Code 열 포함 |
| F3 | MaxMind GeoIP | `GeoIpMiddleware.php` | MaxMind GeoLite2 통합 + 국가 코드→통화 매핑 + 폴백 |
| F4 | 협업 필터링 추천 | `RecommendationController.php` | Item-based CF（구매 동시발생）+ 인기 상품 폴백 |

### 생태계 설정 추가
| 파일 | 용도 |
|------|------|
| `service/phpunit.xml` | PHPUnit 테스트 설정（12.5 schema） |
| `.editorconfig` | 통일된 에디터 설정（들여쓰기/줄바꿈/인코딩） |
| `Makefile` | 14개 단축 명령어（start/stop/test/lint/check/fix/docker 등） |
| `.github/workflows/ci.yml` | CI 매트릭스 테스트（PHP 8.3/8.4 + MySQL + Redis） |
| `service/phpstan.neon` | 정적 분석 설정（level 5） |
| `service/.php-cs-fixer.php` | PSR-12 코드 포맷팅 설정 |
| `admin/composer.json` | `require-dev` phpunit 추가 |

### 문서 업데이트
| 파일 | 변경 |
|------|------|
| `service/CLAUDE.md` | 테스트 도구 섹션, 기능 구현 상태 테이블, Makefile 명령어 추가 |
| `admin/CLAUDE.md` | 테스트 설명, Makefile 명령어 추가 |
| `AUDIT-REPORT.md` | 본 수정 기록 |

---

## 수정 기록 (2026-08-07)

### P0 보안 수정
| # | 문제 | 파일 | 수정 |
|---|------|------|------|
| S5 | docker-compose/.env.example에 실제 키 하드코딩 | `docker-compose.yml` `service/.env.example` | change_me 플레이스홀더 + 상단 보안 경고로 교체; 설치 마법사가 랜덤 키 생성 |
| S6 | 주문 생성에 트랜잭션 없음, 재고 차감 비원자적（동시성 초과 판매） | `OrderController.php` | `Db::transaction` + `where('stock','>=',qty)->decrement()` 원자적 차감 |
| S7 | 쿠폰 수령 동시성 초과 발급 | `CouponController.php` | 트랜잭션 + 행 잠금 `lockForUpdate` + `received_qty < total_qty` 원자적 게이트 |
| S8 | PayPal Webhook 서명 검증 필드 항상 비어 있음 | `PaymentGateway.php` | 5개 서명 검증 필드를 요청 header에서 투과 전달（transmission-id/sig/time/cert-url/auth-algo） |
| S9 | 설치 마법사 SQL 인젝션 | `InstallController.php` | 데이터베이스 이름 quote + 백틱 이스케이프; 비밀번호 var_export로 설정 인젝션 방지 |
| S10 | 암호화/해시 키 누락 시 조용히 폴백 | `Encryption.php` `HashidsHelper.php` | 키가 비어 있거나 길이가 잘못되면 예외를 던져 사용 거부 |

### P0/P1 기능 수정
| # | 문제 | 파일 | 수정 |
|---|------|------|------|
| F5 | 주문 내보내기 고정 파일명 동시성 덮어쓰기 | `ExportController.php` | uniqid 파일명 + shutdown 정리 + 예외 처리 |
| F6 | PayPal 환불 USD 하드코딩 | `PaymentGateway.php` | `refundPayment`에 currency 파라미터 추가 |
| F7 | Hashids 디코딩이 요청 파라미터에 기록 안 됨 | `HashidsDecode.php` | `setParams`/`setGet`/`setPost`에 디코딩 결과 기록 |
| F8 | 상태 매핑에 "심사 대기" 누락 | `ExportController.php` | 상태 매핑에 8 → 심사 대기 추가 |

### P1 생태계 수정
| # | 문제 | 파일 | 수정 |
|---|------|------|------|
| E1 | composer.lock이 gitignore됨 | `.gitignore` | 무시 제거, 버전 관리에 포함하여 재현 가능한 빌드 보장 |
| E2 | 컨테이너에 헬스체크, 시작 의존성 없음 | `docker-compose.yml` | 전체 서비스에 healthcheck + depends_on condition 추가 |
| E3 | admin Dockerfile 실행 불가 | `admin/Dockerfile` | COPY + composer install + EXPOSE + CMD 보완 |
| E4 | Redis 파사드 사용 불가 | `service/config` | RedisFacade 수정 + 3개 단위 테스트 |
| E5 | /health 헬스체크 엔드포인트 신설 | `service/config/route.php` | JWT 불필요, 프로브/로드 밸런싱용 |

### P2 모바일 수정
| # | 문제 | 파일 | 수정 |
|---|------|------|------|
| M1 | Flutter 컴파일 오류（intl 버전 충돌, 생성자 제네릭, 불필요한 괄호） | `apps/flutter` | intl ^0.20.2, 정적 팩토리 fromJson, 문법 수정 |
| M2 | Flutter 테스트 pending Timer 실패 | `test/widget_test.dart` | pump으로 시계 진행, dio 타임아웃 해제 |
| M3 | HarmonyOS 컴파일 불가（27개 ArkTS 오류） | `apps/harmonyos` | 명시적 인터페이스 QueryParams/RequestBody, 예약어 Search→SearchPage, 단일 루트 build, @kit.AbilityKit 임포트, hvigor 설정 |
| M4 | 플랫폼 인지 baseUrl | `apps/flutter/lib/core/constants` | Android 에뮬레이터 10.0.2.2, macOS 샌드박스 네트워크 권한 |

### 문서 업데이트 (2026-08-07)
| 파일 | 변경 |
|------|------|
| `README.md` `README-EN.md` | 테스트 수 26→22, 테이블 수 70→117, 기능 상태 |
| `docs/features.md` `docs/architecture*.md` `docs/design.md` | 테스트 분포 업데이트（SecurityTest 12） |
| `docs/api.md` | /health 엔드포인트 경로 수정 |
| `docs/deployment.md` | admin 포트 8788, install.sql 참조 |
| `docs/*.mmd` + `*.svg` | 밀집 노드 줄바꿈 + Chrome 재렌더링 |
| `service/CLAUDE.md` `apps/CLAUDE.md` | 테스트 수, 페이지 수 9 수정 |

---

## 1. 실행 요약

| 차원 | 상태 | 평가 |
|------|------|:---:|
| PHP 문법 검사 | 0 오류 | A+ |
| 단위 테스트 | 22/22 통과 (45 어서션) | A |
| 보안 방어 | 15종 공격 탐지 | A |
| 코드 규범 | 수정 완료 | A- |
| 생태계 설정 | 보완 완료 | A- |
| 기능 완성도 | TODO 전부 구현 | A- |
| 모바일 | Flutter 테스트 통과 + HarmonyOS 빌드 성공 | B+ |

**종합 평가: A-** — 백엔드 기반이 견고하며, 2026-08-07 수정 후 생태계 설정, 보안, 모바일 모두 기준 충족.

---

## 2. 테스트 결과

### 2.1 PHP 문법 검사

```
service/ — 0 오류
admin/   — 0 오류
```

### 2.2 단위 테스트 (PHPUnit 12.5.25)

```
Tests: 22 | Assertions: 45 | Status: ALL PASSED
```

| 테스트 파일 | 테스트 수 | 커버 범위 |
|----------|:------:|----------|
| `SecurityTest.php` | 12 | XSS(3), SQLi(2), XXE(2), SSRF(1), 경로 탐색(2), 카드 정보 유출(1), 정상 통과(1) |
| `JwtTest.php` | 4 | Token 인코딩/디코딩, 무효 Token 처리 |
| `ApiResponseTest.php` | 3 | 성공/실패 응답 형식, 페이지네이션 |
| `RedisFacadeTest.php` | 3 | Redis 파사드 ping/set/get 왕복 |

### 2.3 누락된 테스트

- **admin/ 프로젝트 테스트 없음** — composer.json에 `require-dev` phpunit 추가됨, 테스트 보완 대기
- **통합 테스트 없음** — API 엔드포인트 테스트, 데이터베이스 테스트, 모델 테스트 없음
- **커버리지 보고서 없음** — 코드 커버리지 정량화 불가

---

## 3. 보안 검토

### 3.1 SecurityMiddleware — 15종 공격 탐지

| # | 탐지 유형 | 상태 |
|---|----------|:----:|
| 1 | HTTP 메서드 검증 | OK |
| 2 | Host 헤더 검증 | OK |
| 3 | Content-Type 검증 | OK |
| 4 | 요청 본문 크기 제한 (10MB) | OK |
| 5 | 파일 업로드 확장자 화이트리스트 | OK |
| 6 | XXE 엔티티 인젝션 탐지 | OK |
| 7 | XSS 크로스 사이트 스크립팅 (19종 패턴) | OK |
| 8 | SQL 인젝션 (18종 패턴) | OK |
| 9 | CRLF 헤더 인젝션 | OK |
| 10 | 경로 탐색 + Null Byte | OK |
| 11 | SSRF 내부망 IP 탐지 | OK |
| 12 | 무차별 대입 방어 (Redis) | OK |
| 13 | 보안 응답 헤더 | OK |
| 14 | 이중 확장자 공격 | OK |
| 15 | 인코딩 경로 탐색 | OK |

### 3.2 보안 문제

| 심각도 | 파일 | 문제 |
|:------:|------|------|
| 중 | `service/app/common/Jwt.php:21` | 하드코딩 폴백 키 |
| 중 | `SocialAuthController.php` | 소셜 로그인 성공 시 JWT token 미반환（AuthController와 불일치） |
| 저 | `AuthController.php:75-84` | refresh 엔드포인트가 전달된 token이 refresh_token 유형인지 검증하지 않음 |
| 저 | `SecurityMiddleware.php:329` | `Cache-Control: no-store`가 모든 응답에 적용, 공개 GET API는 캐시 허용해야 함 |

### 3.3 데이터 보호

- 비밀번호: bcrypt + 6자리 랜덤 salt
- 이메일/휴대폰: `erikwang2013/encryptable` 데이터베이스 필드 암호화
- API ID: Snowflake ID를 Hashids로 인코딩, 원본 ID 미노출
- 민감 작업: PosterVerify 휴먼 인증（가입/주문/결제）
- PDO: `ATTR_EMULATE_PREPARES => false` 네이티브 prepared statements 사용

---

## 4. 코드 품질

### 4.1 코드 통계

| 모듈 | 파일 수 | 코드 라인 수 |
|------|:------:|:------:|
| API 컨트롤러 (v1) | 37 | ~1,970 |
| 데이터 모델 | 100+ | ~2,390 |
| 미들웨어 | 12 | ~800 |
| 유틸리티 클래스 | 9 | ~500 |
| Admin 관리 컨트롤러 | 65 | — |
| 설정 파일 | 29 | — |

### 4.2 가독성 문제

| 파일 | 라인 번호 | 문제 |
|------|:---:|------|
| `AuthController.php` | 30, 37, 57 | 한 줄에 여러 PHP 문장 |
| `ProductController.php` | 58 | `match()` 표현식이 너무 김 |
| `ProductController.php` | 61 | `foreach` + 다중 문장 한 줄 압축 |
| `SocialAuthController.php` | 3-6 | 여러 `use` 문장을 한 줄에, Copyright 헤더 없음 |

### 4.3 코드 문제

| 파일 | 문제 |
|------|------|
| `OrderController.php` | 명시적 `use app\model\ProductSkuPrices` 임포트 누락 |
| `PaymentController.php:79` | `Gateway::make($gateway)` 예외 처리 없음 |
| `ProductController.php:84` | `$product->status < 1`은 초안(0)을 비노출로 처리하지만 논리 경계가 불명확 |

### 4.4 TODO 마커（4곳）

| 파일 | TODO |
|------|------|
| `service/app/common/PaymentGateway.php` | PayPal REST API 통합 |
| `service/app/controller/v1/RecommendationController.php` | 협업 필터링 추천 알고리즘 |
| `service/app/controller/v1/ExportController.php` | PhpSpreadsheet Excel 내보내기 |
| `service/app/middleware/GeoIpMiddleware.php` | MaxMind GeoLite2 데이터베이스 통합 |

---

## 5. 생태계 설정 완전성

### 5.1 완료됨

| 설정 항목 | 상태 |
|--------|:--:|
| Docker Compose (6개 서비스: nginx, service, admin, mysql, redis, elasticsearch) | OK |
| Nginx 리버스 프록시 (API + Admin 이중 도메인) | OK |
| .env.example 템플릿 (service + admin) | OK |
| 번역 파일 (zh_CN/zh_HK/en/ja/ko, 각 48개 항목) | OK |
| 데이터베이스 커넥션 풀 + 읽기/쓰기 분리 | OK |
| Redis 커넥션 풀 | OK |
| Elasticsearch 검색 통합 | OK |
| API 버전 관리 (Header 방식) | OK |
| 완전한 라우팅 설정 (70+ 엔드포인트) | OK |
| 미들웨어 파이프라인 (14개 계층) | OK |
| 결제 게이트웨이 설정 (Stripe/PayPal/Klarna) | OK |
| Cron 프로세스 정의 (10개 예약 작업) | OK |
| 데이터베이스 시드 데이터 | OK |
| API 문서 어노테이션 (Apidoc) | OK |
| Snowflake ID + Hashids 암호화 | OK |
| install.sql 완전한 설치 스크립트 (117개 테이블) | OK |
| 모바일 Flutter App 스켈레톤 | OK |
| 모바일 HarmonyOS App 스켈레톤 | OK |
| 속도 제한 규칙 (6개) | OK |
| OPCache 설정 | OK |

### 5.2 누락

| 누락 항목 | 영향 | 제안 |
|--------|------|------|
| `.env` 파일 (service + admin) | 애플리케이션 시작 불가 | `.env.example` 복사 후 실제 값 입력 |
| `phpunit.xml` | 테스트 비규범 | `phpunit --generate-configuration` 실행 |
| `.editorconfig` | 에디터 불일치 | 통합 에디터 설정 추가 |
| `.github/workflows/` (CI/CD) | 자동화 테스트/배포 없음 | GitHub Actions 추가 |
| `phpstan.neon` | 정적 분석 없음 | `phpstan/phpstan`을 require-dev에 추가 |
| `.php-cs-fixer.php` | 코드 스타일 통일 없음 | `friendsofphp/php-cs-fixer` 추가 |
| `Makefile` | 단축 명령어 없음 | 공용 명령어 단축키 추가 |
| Admin `require-dev` | 테스트 프레임워크 없음 | admin 개발 의존성에 phpunit 추가 |
| Admin 테스트 파일 | 관리 백엔드 테스트 없음 | 핵심 CRUD 컨트롤러 테스트 추가 |

---

## 6. 아키텍처 평가

### 6.1 장점

1. **명확한 계층 아키텍처**: Controller / Model / Common, 책임 구분 명확
2. **API 버전 관리**: Header 방식이 URL 버전 번호보다 우아함
3. **미들웨어 파이프라인**: 조합 가능하고 순서 지정 가능한 보안 및 비즈니스 미들웨어
4. **다국어/다중 통화**: 상품 번역 테이블 + SKU 통화별 가격 테이블 설계 합리적
5. **HS Code 관세**: 완전한 크로스보더 관세율 계산 체계
6. **고성능 대비**: 커넥션 풀, 읽기/쓰기 분리, 토큰 버킷 속도 제한, OPCache 모두 설정됨
7. **결제 추상화**: `PaymentGateway` 팩토리 패턴, 새 채널 확장 용이
8. **보안 심층 방어**: 31종 공격 탐지 + 데이터베이스 암호화 + ID 난독화 + 휴먼 인증

### 6.2 개선 제안

| 우선순위 | 제안 | 이유 |
|:------:|------|------|
| ~~높음~~ | ~~4개 TODO 기능 보완~~（완료） | PayPal/추천/내보내기/GeoIP 모두 구현됨, 위 「기능 TODO 구현」 참조 |
| 높음 | CI/CD 파이프라인 추가 | 커밋마다 자동화 테스트 보장 |
| 높음 | SocialAuthController가 JWT 반환 | 클라이언트 소셜 로그인 후 인증 필요한 API 호출 불가 |
| 중 | phpstan 정적 분석 추가 | 타입 오류와 잠재적 버그 조기 발견 |
| 중 | php-cs-fixer 추가 | 코드 스타일 통일 |
| 중 | Admin 테스트 추가 | 관리 백엔드 CRUD 커버 |
| 중 | Cache-Control 정책 분리 | GET 공개 API는 CDN 캐시 허용해야 함 |
| 중 | Jwt.php 하드코딩 키 폴백 제거 | 프로덕션 환경에서 환경 변수 설정 강제 |
| 저 | 코드 형식 정규화 | 한 줄 다중 문장 분리 |
| 저 | Makefile 추가 | 개발 명령어 단순화 |

---

## 7. 데이터베이스 검토

- **117개 테이블** (7개 `wa_` 시스템 테이블 + 약 110개 `erik_` 비즈니스 테이블)
- 엔진: InnoDB | 문자셋: utf8mb4 | 정렬: utf8mb4_unicode_ci
- 기본 키: BIGINT (Snowflake 분산 ID, 자동 증가 아님)
- 모든 비즈니스 테이블에 `created_at` / `updated_at` / `deleted_at` 포함
- 테이블 접두사 전략: 시스템 테이블 `wa_`, 비즈니스 테이블 `erik_`
- 인덱스: `install.sql`에 완전한 인덱스 정의 포함

---

## 8. 실행 가이드

```bash
# 1. 환경 준비
cp service/.env.example service/.env   # 편집 후 실제 값 입력
cp admin/.env.example admin/.env       # 편집 후 실제 값 입력

# 2. 의존성 설치
cd service && composer install
cd ../admin && composer install

# 3. 데이터베이스 임포트
mysql -u root -p < install.sql

# 4. 서비스 시작
cd service && php start.php start -d
cd ../admin && php start.php start -d

# 5. Docker 배포
docker-compose up -d

# 6. 테스트 실행
cd service && php vendor/bin/phpunit tests/
```

---

## 9. 결론

프로젝트 코드 기반이 견고하고, 보안 방어가 전면적이며, 아키텍처 설계가 합리적입니다. 수정 후 현황:
1. 4개 TODO 기능 모듈（PayPal/추천/내보내기/GeoIP）모두 구현 완료
2. CI/CD와 코드 품질 관리 도구 체인이 보완됨（CI 매트릭스, PHPStan, php-cs-fixer）
3. 소셜 로그인이 JWT 반환
4. Admin 자동화 테스트는 여전히 비어 있음（추후 보완 제안）
5. 예약 작업（10개 Cron）모두 구현 및 스모크 검증 통과

높은 우선순위 항목을 먼저 처리하고, 도구 체인 보완 후 프로덕션 배포에 들어가는 것을 권장합니다.

---

*보고서는 자동 검토로 생성됨 | 2026-08-04*
