# 크로스보더 전자상거래 플랫폼 — 아키텍처 개요

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 기술 스택

| 계층 | 기술 | 버전 |
|------|------|------|
| API | webman + illuminate/database | 2.1 / 11.x |
| Admin | webman-admin + LayUI + ECharts | 2.0 |
| 클라이언트 | Flutter (5플랫폼) + HarmonyOS (ArkTS) | 3.x / API 12+ |
| 데이터베이스 | MySQL + Redis + Elasticsearch | 8.0 / 7 / 8 |
| 결제 | Stripe / PayPal / Klarna / Adyen | — |

## 2. 디렉터리 구조

```
shop-php/
  service/            비즈니스 API (251 PHP파일)
    config/            35개 설정 (database/redis/jwt/snowflake/hashids/encryption/poster/scout/concurrency/...)
    app/controller/    39컨트롤러 (38 v1 + BaseApiController: Auth/Product/Order/Payment/Shipping/Tariff/Health/...)
    app/model/         111모델 (BaseModel + 110비즈니스 모델)
    app/middleware/     14미들웨어 (Cors/Security/RateLimit/Platform/GeoIp/Locale/HashidsDecode/VersionRoute/PosterVerify/JwtAuth/HashidsEncode/Encryption/StaticFile/AdminKey)
    app/common/          8유틸리티 클래스 (Snowflake/HashidsHelper/ApiResponse/Encryption/Jwt/PaymentGateway/SocialAuth/Definitions)
    database/          schema.sql (루트 install.sql로 대체됨) + seeders
    tests/              4테스트 클래스 (22 tests, 45 assertions)
  admin/              관리 백엔드 (239 PHP파일)
    plugin/admin/app/controller/shop/ 82컨트롤러
    plugin/admin/app/model/shop/      76모델
    plugin/admin/app/view/shop/       ECharts 대시보드
    app/middleware/    5미들웨어 (Security/Platform/HashidsDecode/HashidsEncode/StaticFile)
  apps/               클라이언트
    flutter/lib/      25 Dart (11페이지 + 핵심 계층 + 라우팅)
    harmonyos/        14 ArkTS (9페이지 + API 클라이언트 + 전역 상태)
  docs/               5개 설계 문서
  .claude/skills/     38개 개발 규범 Skills
```

## 3. 미들웨어 파이프라인

```
Service: Cors → Security(31종 공격 탐지) → RateLimit(토큰 버킷 속도 제한) → Platform(8플랫폼 식별)
        → GeoIp(지역) → Locale(언어) → HashidsDecode → VersionRoute
        → (PosterVerify 휴먼 인증) → (JwtAuth Token) → HashidsEncode → Encryption(인터페이스 암호화)

Admin:  Security → Platform → HashidsDecode → AccessControl(내장 RBAC) → HashidsEncode
```

## 4. 보안

- **31종 공격 탐지**: XSS/SQL 인젝션/명령 인젝션/CRLF/경로 탐색/Body/ContentType/파일 업로드/무차별 대입/XXE/SSRF/역직렬화/LDAP/메일 헤더/SSTI/NoSQL/오픈 리다이렉트/JWT 공격/Host/요청 스머글링/GraphQL/XPATH/Log4Shell/SSI/CSV 수식/데이터 유출/프로토타입 오염/WebSocket/CORS/DNS 리바인딩/HTTP 메서드/CSRF Origin
- **3계층 암호화**: 인터페이스 계층(AES-256-CBC) + 데이터베이스 계층(Encryptable trait) + ID 난독화(Hashids)
- **플랫폼 추적**: 8플랫폼(iOS/iPadOS/macOS/Windows/Linux/Android/HarmonyOS/Web) + X-Platform header + 6개 테이블 기록

## 5. 고동시성

- **속도 제한**: 토큰 버킷 슬라이딩 윈도우(Redis ZSET), 6엔드포인트 규칙
- **서킷 브레이커/디그레이션**: Redis 서킷 브레이커 — 결제 게이트웨이/소셜 로그인 외부 API 호출, 5회 연속 실패→30초 차단, 반개(半開) 탐지로 자동 복구; 비즈니스 예외는 실패로 집계하지 않음; Redis 장애 시 자동 통과(503)
- **DB**: 읽기/쓰기 분리(2개 읽기 복제본+sticky) + 커넥션 풀(50/10)
- **느린 작업**: 독립 Cron 프로세스가 처리(Feed 동기화/추천 계산/결제 대사/분배 정산 등)

## 6. 테스트

22 tests / 45 assertions — ALL PASS
- SecurityTest (12): XSS+SQLi+XXE+SSRF+Path+데이터 유출
- JwtTest (4): encode/decode validation
- ApiResponseTest (3): success/fail/paginate

## 7. 배포

```bash
# Docker
docker compose up -d  # nginx + service + admin + mysql + redis + es

# 수동
cd service && php start.php start -d
cd admin && php start.php start -d
```

- **다국어 (i18n)**: 5개 언어 번역 파일 + LocaleMiddleware + Flutter AppLocalizations
- **API 문서**: hg/apidoc 자동 생성 (6개 그룹, 컨트롤러 어노테이션 기반)
- **플랫폼 추적**: 8플랫폼 X-Platform header + DB 기록

자세한 내용: [배포 문서](deployment.md) | [전체 아키텍처 문서](architecture-full.md) | [기능 설계 문서](features.md)
