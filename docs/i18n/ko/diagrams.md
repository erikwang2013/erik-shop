# 크로스보더 전자상거래 플랫폼 — 아키텍처 도면집

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 시스템 아키텍처 다이어그램

![1. 시스템 아키텍처 다이어그램](./diagrams/01-system-architecture.svg)

---

## 2. 요청 처리 흐름도 (미들웨어 파이프라인)

![2. 요청 처리 흐름도](./diagrams/02-request-processing-flow.svg)

---

## 3. 기능 모듈 전체도

![3. 기능 모듈 전체도](./diagrams/03-feature-module-map.svg)

---

## 4. 요청 라이프사이클 다이어그램

![4. 요청 라이프사이클 다이어그램](./diagrams/04-request-lifecycle.svg)

---

## 5. 주문 라이프사이클 다이어그램

![5. 주문 라이프사이클 다이어그램](./diagrams/05-order-lifecycle.svg)

---

## 6. 배포 아키텍처 다이어그램

![6. 배포 아키텍처 다이어그램](./diagrams/06-deployment-architecture.svg)

---

## 7. 보안 아키텍처 다이어그램

![7. 보안 아키텍처 다이어그램](./diagrams/07-security-architecture.svg)

### 보안 방어 총람

| 계층 | 방어선 | 기술/패키지 | 적용 범위 |
|------|------|---------|---------|
| 제1계층 | 네트워크 경계 | Nginx SSL + 리버스 프록시 + Host 검증 | Service + Admin |
| 제2계층 | WAF 공격 탐지 | `erikwang2013/security-php` 31개 탐지기 | XSS/SQLi/CRLF/경로 탐색/XXE/SSRF/파일 업로드/메서드/Host/Content-Type/Body 등 |
| 제3계층 | 트래픽 제어 + 의존성 복원력 | RateLimitMiddleware + 무차별 대입 Redis 카운터 + CircuitBreaker | 토큰 버킷 속도 제한(6엔드포인트) + 로그인/가입 폭주 방어 + 결제/소셜 로그인 서킷 브레이커(5회 실패→30s, 반개 복구) |
| 제4계층 | 신원 인증 | PosterVerify + JwtAuth HS256 | 휴먼 인증(슬라이더/퍼즐/클릭) + Bearer Token + 이중 token 갱신 |
| 제5계층 | 데이터 보안 | Hashids + AES-256-CBC + Encryptable | 3계층 암호화: ID 난독화/전송 암호화/DB 필드 암호화 |
| 제6계층 | 응답 보안 | HTTP 보안 헤더 + 민감 데이터 마스킹 | nosniff/DENY/XSS-Protection/Referrer-Policy/로그 마스킹 |
| 상시 | 감사 추적 | PlatformMiddleware + OperationLogs | 8플랫폼 출처 추적 + 6개 테이블 기록 + 운영 로그 |

---

## 8. 다중 통화 정산 흐름도

![8. 다중 통화 정산 흐름도](./diagrams/08-multi-currency-settlement.svg)

### 다중 통화 정산 설명

**다중 통화 가격 책정**: 상품 SKU를 `currency_code`별로 통화 단위 가격을 책정하며, 주문 시 결제 통화(USD / EUR / GBP / CNY 등)를 고정합니다.

**환율 서비스**: `erik_exchange_rates` 환율 테이블은 manual 수동 유지보수와 exchangerate-api 자동 연동을 지원하며, `effective_at` 적용 시점 기준으로 버전 관리되고, 정산 시 결제 시점의 환율 스냅샷을 사용합니다.

**원화 표시 통화 차감**: Stripe / PayPal / Klarna / Adyen은 주문 통화 기준 원화 그대로 차감하며, Webhook 서명 검증으로 입금을 확인한 후 결제 및 주문 상태를 업데이트합니다.

**분배 정산**: 결제 성공 후 자동으로 `PlatformSettlements` 플랫폼 분배(주문 총액 + 플랫폼 수수료 + 결제 게이트웨이 수수료, 주문 통화로 기장)가 생성됩니다. 셀러 정산 `MerchantSettlements`(주문 금액 → 수수료율 → 정산 금액), 공급업체 정산 `SupplierSettlements`, 유통 커미션 출금 `AffiliatePayouts` 4개 라인이 독립적으로 정산되며, 상태 0 정산 대기 / 1 정산 완료입니다.

**환차손익**: `CurrencyExchangeGainsLosses`가 결제 통화와 정산 통화의 차이를 추적하며, 결제 시점 환율과 정산 시점 환율을 비교합니다. 양수 = 환차익, 음수 = 환차손으로, 크로스보더 전자상거래 다중 통화 대사 및 감사를 지원합니다.

---

## 도면 색인

| 번호 | 도면명 | 유형 | 용도 |
|------|------|------|------|
| 1 | 시스템 아키텍처 다이어그램 | 아키텍처 도면 | 시스템 전체 모습: 클라이언트→게이트웨이→애플리케이션→데이터→외부 서비스 |
| 2 | 요청 처리 흐름도 | 흐름도 | HTTP 요청이 12단계 미들웨어 파이프라인(10글로벌+2라우팅)을 거치는 전체 경로 |
| 3 | 기능 모듈 전체도 | 기능 도면 | 17대 기능 모듈과 세부 기능 포인트 |
| 4 | 요청 라이프사이클 다이어그램 | 라이프사이클 | 요청부터 응답까지의 전체 시퀀스와 단계별 상호작용 |
| 5 | 주문 라이프사이클 다이어그램 | 라이프사이클 | 장바구니부터 완료/환불까지의 모든 상태 전이 |
| 6 | 배포 아키텍처 다이어그램 | 아키텍처 도면 | Docker Compose 컨테이너 오케스트레이션, 네트워크, 데이터 볼륨, CDN 엣지 계층(업로드 볼륨 admin_uploads/service_public 포함) |
| 7 | 보안 아키텍처 다이어그램 | 아키텍처 도면 | 6계층 심층 방어 체계: 경계→WAF→트래픽/복원력(속도 제한+서킷 브레이커)→인증→데이터→응답 |
| 8 | 다중 통화 정산 흐름도 | 흐름도 | 통화별 가격 책정→결제→분배→정산→환차손익 전체 체인 |
