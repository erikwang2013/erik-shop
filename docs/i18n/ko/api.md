# 크로스보더 전자상거래 플랫폼 — API 인터페이스 문서

> 동적 문서: Service 시작 후 http://localhost:8787/apidoc/ 접속 (hg/apidoc 자동 생성)



Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 공통 규범

### 요청 형식

| 항목 | 설명 |
|------|------|
| Base URL | `http://localhost:8787/api` |
| 버전 관리 | `API-Version: 2026-05-20` header (URL에 없음) |
| 인증 | `Authorization: Bearer <token>` header |
| 언어 | `Accept-Language: zh_CN|zh_HK|en|ja|ko` header |
| 플랫폼 | `X-Platform: ios|ipados|macos|windows|linux|android|harmonyos|web` header |
| Content-Type | `application/json` (POST/PUT) |
| 휴먼 인증 | `X-Poster-Token: <token>` header (민감 작업) |

### 응답 형식

```json
// 성공
{"code": 0, "msg": "ok", "data": {}}

// 실패
{"code": 1, "msg": "오류 메시지", "data": null}

// 페이지네이션
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}

// 오류 코드
// 40001 XSS 공격  40002 SQL 인젝션  40003 CRLF 인젝션  40004 경로 탐색
// 40005 요청 본문 과다  40006 Content-Type 오류  40008 무차별 대입
// 40009 파일 업로드 위반  40010 XXE 인젝션  40011 SSRF 공격
// 40012 HTTP 메서드 오류  40013 Host 헤더 오류
// 401 미로그인  403 접근 금지  422 파라미터 검증 실패  429 요청 과다  503 서비스 일시적으로 사용 불가(서킷 브레이커/디그레이션)
```

### ID 설명

모든 인터페이스의 ID 필드는 hashids 인코딩 문자열입니다（예: `Ab3xK9pq`）, 미들웨어가 자동으로 인코딩/디코딩합니다. 프론트엔드에서 수동 처리할 필요가 없습니다.

---

## 1. 인증 인터페이스

### 1.1 회원가입 `POST /api/auth/register`

> 휴먼 인증 `X-Poster-Token` 필요

**요청:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "nickname": "UserNick"
}
```

**응답:**
```json
{
  "code": 0, "msg": "가입 성공",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.2 로그인 `POST /api/auth/login`

**요청:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**응답:**
```json
{
  "code": 0, "msg": "로그인 성공",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "UserNick",
    "email": "user@example.com",
    "level": 1,
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.3 Token 갱신 `POST /api/auth/refresh`

**요청:**
```json
{
  "refresh_token": "eyJhbGciOi..."
}
```

**응답:**
```json
{
  "code": 0, "msg": "Token이 갱신되었습니다",
  "data": {
    "access_token": "eyJhbGciOi...",
    "expires_in": 7200
  }
}
```

### 1.4 소셜 로그인 `POST /api/auth/social`

**요청:**
```json
{
  "provider": "google",
  "provider_user_id": "1234567890",
  "email": "user@gmail.com",
  "name": "User Name"
}
```

**응답:**
```json
{
  "code": 0, "msg": "로그인 성공",
  "data": {
    "user_id": "Ab3xK9pq",
    "nickname": "User Name",
    "email": "user@gmail.com",
    "is_new": false
  }
}
```

---

## 2. 상품 인터페이스

### 2.1 상품 목록 `GET /api/products`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| page | int | 아니오 | 페이지 번호 (기본 1) |
| per_page | int | 아니오 | 페이지당 수량 (기본 20, 최대 100) |
| category_id | string | 아니오 | 분류 ID (hashid, 하위 분류 포함) |
| keyword | string | 아니오 | 검색 키워드 |
| sort | string | 아니오 | 정렬: default/price_asc/price_desc/sales/newest |
| min_price | number | 아니오 | 최저가 |
| max_price | number | 아니오 | 최고가 |

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Ab3xK9pq",
        "title": "Product Title",
        "subtitle": "Subtitle",
        "main_image": "https://img.example.com/p1.jpg",
        "brand": "BrandName",
        "min_price": 29.99,
        "max_price": 49.99,
        "status": 2,
        "is_hot": true,
        "is_new": false,
        "sales_count": 1000
      }
    ],
    "total": 100, "page": 1, "per_page": 20
  }
}
```

### 2.2 상품 상세 `GET /api/products/{id}`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| currency | string | 아니오 | 통화 코드 (기본 USD) |
| dest_country | string | 아니오 | 목적지 국가 ISO2 (기본 US) |

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "id": "Ab3xK9pq",
    "title": "Product Title (다국어 매칭)",
    "subtitle": "Subtitle",
    "description": "Full description...",
    "brand": "BrandName",
    "main_image": "https://img.example.com/p1.jpg",
    "min_price": 29.99,
    "max_price": 49.99,
    "weight": 500,
    "unit": "piece",
    "status": 2,
    "is_hot": true,
    "is_new": false,
    "sales_count": 1000,
    "view_count": 5000,
    "skus": [
      {
        "id": "Cd4yL8rq",
        "sku_code": "SKU-RED-M",
        "attrs": {"color": "Red", "size": "M"},
        "default_price": 29.99,
        "stock": 100,
        "image": "https://img.example.com/sku1.jpg",
        "display_price": {
          "tax_exclusive": 29.99,
          "tax_inclusive": 35.99,
          "vat_amount": 6.00,
          "vat_rate": 20,
          "currency": "USD",
          "display_mode": "tax_exclusive"
        }
      }
    ],
    "images": [
      {"id": "Ef5zM9ns", "url": "https://img.example.com/p1.jpg", "is_main": true}
    ],
    "compliance_info": [
      {"category": "CE 마크", "code": "CE", "cert_no": "CE2024001"}
    ],
    "hs_codes": [
      {"code": "620442", "is_primary": true}
    ]
  }
}
```

### 2.3 상품 평가 `GET /api/reviews/{productId}`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| page | int | 아니오 | 페이지 번호 |
| per_page | int | 아니오 | 페이지당 (기본 10) |
| rating | int | 아니오 | 평점 필터 (1-5) |

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Re1v2W3x",
        "user_id": "Ab3xK9pq",
        "product_id": "Ab3xK9pq",
        "rating": 5,
        "content": "Great product!",
        "images": ["https://img.example.com/review1.jpg"],
        "is_anonymous": false,
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 50, "page": 1, "per_page": 10
  }
}
```

---

## 3. 분류 인터페이스

### 3.1 분류 목록 `GET /api/categories`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| parent_id | int | 아니오 | 상위 분류 ID (0=최상위) |

### 3.2 분류 트리 `GET /api/categories/tree`

완전한 중첩 분류 트리를 반환합니다.

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ct1g2H3i",
      "parent_id": 0,
      "name": "Clothing",
      "slug": "clothing",
      "icon": "icon-url",
      "level": 1,
      "is_hot": true,
      "children": [
        {
          "id": "Ct4j5K6l",
          "parent_id": "Ct1g2H3i",
          "name": "Dresses", "slug": "dresses",
          "level": 2, "is_hot": false,
          "children": []
        }
      ]
    }
  ]
}
```

---

## 4. 장바구니 인터페이스 `[JWT]`

### 4.1 장바구니 목록 `GET /api/cart`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| currency | string | 아니오 | 통화 (기본 USD) |

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Ca1r2T3s",
      "sku_id": "Cd4yL8rq",
      "product_id": "Ab3xK9pq",
      "title": "Product Title",
      "image": "https://img.example.com/sku1.jpg",
      "attrs": {"color":"Red","size":"M"},
      "price": 29.99,
      "currency": "USD",
      "quantity": 2,
      "selected": true,
      "stock": 100
    }
  ]
}
```

### 4.2 장바구니 추가 `POST /api/cart`

**요청:**
```json
{
  "sku_id": "Cd4yL8rq",
  "quantity": 1
}
```

### 4.3 수량 변경 `PUT /api/cart/{id}`

```json
{"quantity": 3}
```

> quantity=0 이면 자동 삭제

### 4.4 삭제 `DELETE /api/cart/{id}`

---

## 5. 주문 인터페이스 `[JWT]`

### 5.1 주문 목록 `GET /api/orders`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| status | int | 아니오 | 상태 필터:0결제 대기/1결제 완료/2배송 완료/3수령 완료/4완료/5취소됨/6환불 진행 중/7환불 완료/8심사 대기 |
| page | int | 아니오 | 페이지 번호 (기본 1) |
| per_page | int | 아니오 | 페이지당 (기본 10) |

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "list": [
      {
        "id": "Or1d2E3r",
        "order_no": "ORD20260521A1B2C3D4",
        "status": 1, "status_text": "결제 완료",
        "total_amount": 59.98, "pay_amount": 59.98,
        "currency_code": "USD",
        "created_at": "2026-05-21 10:30:00",
        "paid_at": "2026-05-21 10:31:00"
      }
    ],
    "total": 10, "page": 1, "per_page": 10
  }
}
```

### 5.2 주문 상세 `GET /api/orders/{id}`

items/logs/documents를 포함한 완전한 주문 정보를 반환합니다.

### 5.3 주문 생성 `POST /api/orders` `[PosterVerify]`

**요청:**
```json
{
  "address_id": "Ad1d2R3s",
  "coupon_id": "Co1u2P3n",
  "currency_code": "USD",
  "remark": "Please gift wrap"
}
```

**응답:**
```json
{
  "code": 0, "msg": "주문 생성 성공",
  "data": {
    "order_id": "Or1d2E3r",
    "order_no": "ORD20260521A1B2C3D4",
    "total_amount": 59.98,
    "currency_code": "USD"
  }
}
```

### 5.4 주문 취소 `POST /api/orders/{id}/cancel`

> 상태=0(결제 대기)인 경우에만 취소 가능

### 5.5 상업 송장 `GET /api/orders/{id}/documents/invoice`

PDF 파일 다운로드 링크를 반환합니다.

### 5.6 포장 명세서 `GET /api/orders/{id}/documents/packing-list`

---

## 6. 결제 인터페이스 `[JWT]`

### 6.1 사용 가능한 결제 수단 `GET /api/payment/methods`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| country | string | 아니오 | ISO2 (기본 US) |
| currency | string | 아니오 | 통화 (기본 USD) |

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": [
    {
      "id": "Pg1a2T3e",
      "gateway": "stripe", "gateway_name": "Stripe",
      "method_code": "card", "method_name": "신용카드/직불카드",
      "min_amount": 1.00, "max_amount": 999999.00,
      "is_bnpl": false
    },
    {
      "id": "Pg4a5T6e",
      "gateway": "klarna", "gateway_name": "Klarna",
      "method_code": "klarna_paylater", "method_name": "Klarna 선구매 후결제",
      "min_amount": 35.00, "max_amount": 5000.00,
      "is_bnpl": true
    }
  ]
}
```

### 6.2 결제 생성 `POST /api/payment/create` `[PosterVerify]`

**요청:**
```json
{
  "order_id": "Or1d2E3r",
  "gateway": "stripe",
  "method": "card"
}
```

**응답:**
```json
{
  "code": 0, "msg": "결제 생성 성공",
  "data": {
    "payment_id": "Pa1y2M3t",
    "order_no": "ORD20260521A1B2C3D4",
    "amount": 59.98,
    "currency": "USD",
    "gateway": "stripe",
    "method": "card",
    "client_secret": "pi_3Nxxxx_secret_xxxx",
    "txn_id": "pi_3Nxxxxxxxxxxxx"
  }
}
```

### 6.3 결제 상태 `GET /api/payment/status/{id}`

### 6.4 Webhook 콜백 `POST /webhook/payment/{gateway}`

> JWT 불필요. 결제 게이트웨이가 비동기 호출. 서명 검증 필요.

---

## 7. 물류 인터페이스

### 7.1 운임 계산 `GET /api/shipping/calculate`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| dest_country_id | int | 예 | 목적지 국가 ID (snowflake) |
| weight | int | 아니오 | 무게(그램) (기본 500) |

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "zone_name": "북미 존",
    "weight_kg": 0.5,
    "dest_country": "US",
    "options": [
      {
        "logistics_name": "DHL Express",
        "logistics_code": "DHL",
        "fee": 25.50,
        "estimated_days": "3-5",
        "tracking_url": "https://www.dhl.com/track?num="
      }
    ]
  }
}
```

---

## 8. 관세 인터페이스

### 8.1 관세 추정 `GET /api/tariff/estimate`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| product_id | string | 예 | 상품 ID (hashid) |
| dest_country_id | int | 예 | 목적지 국가 ID |
| declared_value | number | 예 | 신고 가액 |

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "duty_rate": 12.0, "vat_rate": 20.0,
    "estimated_duty": 12.00, "estimated_vat": 22.40,
    "estimated_total": 34.40,
    "is_estimate": true,
    "disclaimer": "참고용일 뿐, 실제는 세관 심사 기준"
  }
}
```

---

## 9. 반품 인터페이스 `[JWT]`

### 9.1 반품 목록 `GET /api/returns`

### 9.2 반품 신청 `POST /api/returns`

**요청:**
```json
{
  "order_id": "Or1d2E3r",
  "reason_id": 1
}
```

### 9.3 반품 라벨 `GET /api/returns/{id}/label`

---

## 10. 사용자 인터페이스 `[JWT]`

### 10.1 개인 정보 `GET /api/user/profile`

### 10.2 정보 수정 `PUT /api/user/profile`

```json
{"nickname": "NewName", "avatar": "url", "sex": 1, "birthday": "1990-01-01"}
```

### 10.3 주소 목록 `GET /api/user/addresses`

### 10.4 주소 추가 `POST /api/user/addresses`

```json
{
  "name": "John Doe", "phone": "+1234567890",
  "country_id": 1, "province": "CA", "city": "Los Angeles",
  "district": "", "detail": "123 Main St",
  "postal_code": "90001", "is_default": 1, "tag": "집"
}
```

### 10.5 주소 수정 `PUT /api/user/addresses/{id}`

### 10.6 주소 삭제 `DELETE /api/user/addresses/{id}`

### 10.7 언어 통화 `PUT /api/user/locale`

```json
{"locale": "ja", "currency": "JPY"}
```

---

## 11. 마케팅 인터페이스

### 11.1 캐러셀 배너 `GET /api/banners?position=home`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| position | string | 아니오 | 위치: home/category/product |

### 11.2 사용 가능한 쿠폰 `GET /api/coupons` `[JWT]`

### 11.3 쿠폰 수령 `POST /api/coupons/{id}/claim` `[JWT]`

### 11.4 플래시 세일 목록 `GET /api/flash-sales`

### 11.5 공동구매 목록 `GET /api/group-buys`

### 11.6 유통 링크 `GET /api/affiliate/links` `[JWT]`

### 11.7 유통 커미션 `GET /api/affiliate/commissions` `[JWT]`

---

## 12. 회원 인터페이스 `[JWT]`

### 12.1 회원 정보 `GET /api/membership`

**응답:**
```json
{
  "code": 0, "msg": "ok",
  "data": {
    "current_level": {"id": "Lv1", "name": "Gold", "level": 2},
    "current_benefits": [{"benefit_type": "discount", "benefit_value": "5%"}],
    "all_levels": [],
    "current_score": 1500
  }
}
```

### 12.2 포인트 내역 `GET /api/points`

---

## 13. 기타 인터페이스

### 13.1 국가 데이터 `GET /api/countries`

사용 가능한 모든 국가/통화/환율/기본값을 반환합니다.

### 13.2 공개 설정 `GET /api/settings?group=general`

### 13.3 ES 검색 `GET /api/search?keyword=xxx`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| keyword | string | 예 | 검색어 |
| category_id | string | 아니오 | 분류 필터 |
| page | int | 아니오 | 페이지 번호 |

### 13.4 상품 비교 `GET/POST/DELETE /api/comparisons[/{id}]` `[JWT]`

DELETE는 비교 기록 id를 전달해야 합니다: `DELETE /api/comparisons/{id}`（`{id}`는 비교 기록 ID, 필수）

### 13.5 개인화 추천 `GET /api/recommendations` `[JWT]`

### 13.6 가격 인하 알림 `GET/POST /api/price-alerts` `[JWT]`

### 13.7 즐겨찾기 `GET/POST/DELETE /api/wishlist[/{id}]` `[JWT]`

### 13.8 알림 `GET /api/notifications` `PUT /api/notifications/{id}/read` `[JWT]`

### 13.9 FAQ `GET /api/faq?category=shipping`

### 13.10 CMS 페이지 `GET /api/cms/{slug}`

### 13.11 사이즈 표 `GET /api/size-charts?category_id=1&type=clothing`

### 13.12 컴플라이언스 확인 `GET /api/compliance/check?product_id=xxx&dest_country_id=xxx`

### 13.13 GeoIP 탐지 `GET /api/geoip/detect`

### 13.14 평가 작성 `POST /api/reviews` `[JWT]`

```json
{"product_id":"x","order_id":"x","rating":5,"content":"Good","images":[]}
```

### 13.15 기프트 카드 잔액 `GET /api/gift-cards/balance?code=xxx` `[JWT]`

### 13.16 기프트 카드 교환 `POST /api/gift-cards/redeem` `[JWT]`

```json
{"code": "GIFT-CODE-HERE"}
```

### 13.17 GDPR 요청 `POST /api/privacy/request` `[JWT]`

```json
{"type": "data_access|data_delete|opt_out|data_portability"}
```

### 13.18 주문 내보내기 `GET /api/export/orders` `[JWT]`

| 파라미터 | 타입 | 필수 | 설명 |
|------|------|------|------|
| date_from | string | 아니오 | 시작 날짜 (YYYY-MM-DD) |
| date_to | string | 아니오 | 종료 날짜 |

CSV 파일 다운로드를 반환합니다.

### 13.19 B2B 견적 `GET/POST /api/b2b/quotes` `[JWT]`

```json
{"product_id":"x","sku_id":"x","quantity":1000,"target_price":15.00,"currency_code":"USD"}
```

### 13.20 헬스체크 `GET /health`

```json
{"code":0,"msg":"ok","data":{"status":"ok","timestamp":"...","db":"ok","redis":"ok"}}
```

---

## 부록: 상태 코드 대조

### 주문 상태

| 값 | 설명 |
|----|------|
| 0 | 결제 대기 |
| 1 | 결제 완료 |
| 2 | 배송 완료 |
| 3 | 수령 완료 |
| 4 | 완료 |
| 5 | 취소됨 |
| 6 | 환불 진행 중 |
| 7 | 환불 완료 |
| 8 | 심사 대기 (리스크) |

### 상품 상태

| 값 | 설명 |
|----|------|
| 0 | 초안 |
| 1 | 심사 대기 |
| 2 | 판매 중 |
| 3 | 판매 중지 |

### 결제 상태

| 값 | 설명 |
|----|------|
| 0 | 결제 대기 |
| 1 | 결제 완료 |
| 2 | 환불 완료 |
| 3 | 실패 |

### 국가별 가격 표시 모드

| 값 | 설명 |
|----|------|
| tax_inclusive | 세금 포함 가격 (EU/UK) |
| tax_exclusive | 세금 미포함 가격 (US/CA) |
| both | 병행 표시 (JP) |

---

## 부록: 미들웨어 파이프라인

```
요청 → Cors → Security(31종) → RateLimit(토큰 버킷) → Platform(8개 플랫폼)
     → GeoIp → Locale → HashidsDecode → VersionRoute
     → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → 컨트롤러
```

표시: `[JWT]` 인증 필요 | `[PosterVerify]` 휴먼 인증 필요 | 표시 없음 = 공개 인터페이스

---

## 부록: 엔드포인트 통계 총괄

### A.1 공개 인터페이스 (23개 엔드포인트)

| 메서드 | 경로 | 설명 |
|------|------|------|
| POST | /api/auth/register | 가입(PosterVerify) |
| POST | /api/auth/login | 로그인 |
| POST | /api/auth/refresh | Token 갱신 |
| POST | /api/auth/social | 소셜 로그인 |
| GET | /api/products | 상품 목록(페이지네이션+필터+정렬) |
| GET | /api/products/{id} | 상품 상세(다국어+다중 통화+컴플라이언스+HS) |
| GET | /api/categories | 분류 목록 |
| GET | /api/categories/tree | 분류 트리 |
| GET | /api/banners | 캐러셀 배너(위치+지역별) |
| GET | /api/countries | 국가/통화/환율 목록 |
| GET | /api/search | ES 다국어 검색 |
| GET | /api/reviews/{productId} | 상품 평가 목록 |
| GET | /api/flash-sales | 현재 플래시 세일 |
| GET | /api/group-buys | 현재 공동구매 |
| GET | /api/faq | FAQ(언어+분류별) |
| GET | /api/cms/{slug} | CMS 페이지 |
| GET | /api/settings | 공개 설정 |
| GET | /api/size-charts | 사이즈 표 |
| GET | /api/tariff/estimate | 관세 추정 |
| GET | /api/shipping/calculate | 운임 계산 |
| GET | /api/payment/methods | 사용 가능한 결제 수단 |
| GET | /api/geoip/detect | GeoIP 탐지 |
| GET | /api/compliance/check | 컴플라이언스 확인 |

### A.2 인증 인터페이스 (47개 엔드포인트)

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET/PUT | /api/user/profile | 개인 정보 |
| GET/POST/PUT/DELETE | /api/user/addresses[/{id}] | 주소 CRUD |
| PUT | /api/user/locale | 언어/통화 변경 |
| GET/POST | /api/wishlist[/{id}] | 즐겨찾기 |
| GET/POST | /api/price-alerts | 가격 인하 알림 |
| GET/POST/PUT/DELETE | /api/cart[/{id}] | 장바구니 |
| GET/POST | /api/orders | 주문 목록/생성(PosterVerify) |
| GET | /api/orders/{id} | 주문 상세 |
| POST | /api/orders/{id}/cancel | 주문 취소 |
| GET | /api/orders/{id}/documents/invoice | 상업 송장 |
| GET | /api/orders/{id}/documents/packing-list | 포장 명세서 |
| POST | /api/payment/create | 결제 생성(PosterVerify) |
| GET | /api/payment/status/{id} | 결제 상태 |
| GET/POST | /api/returns[/{id}] | 반품 |
| GET | /api/returns/{id}/label | 반품 라벨 |
| POST | /api/reviews | 평가 작성 |
| GET/POST | /api/coupons[/{id}/claim] | 쿠폰 |
| GET/PUT | /api/notifications[/{id}/read] | 알림 |
| GET/POST/DELETE | /api/comparisons[/{id}] | 상품 비교 |
| GET | /api/recommendations | 개인화 추천 |
| GET | /api/affiliate/links | 유통 링크 |
| GET | /api/affiliate/commissions | 유통 커미션 |
| GET | /api/membership | 회원 등급 |
| GET | /api/points | 포인트 내역 |
| GET/POST | /api/gift-cards | 기프트 카드 |
| GET/POST | /api/b2b/quotes | B2B 견적 |
| GET/POST | /api/privacy/request | GDPR 요청 |
| GET | /api/export/orders | 주문 내보내기 |

### A.3 Webhook (1개 엔드포인트)

| 메서드 | 경로 | 설명 |
|------|------|------|
| POST | /webhook/payment/{gateway} | 결제 비동기 알림(서명 검증) |

### A.4 Admin 및 헬스체크 (2개 엔드포인트)

| 메서드 | 경로 | 설명 |
|------|------|------|
| POST | /api/admin/refunds/{id}/execute | 백엔드 환불 실행 |
| GET | /health | 헬스체크 |

---

## 부록: API 설계 규범

### 버전 관리

버전은 `API-Version: 2026-05-20` header로 전달되며 URL에 없습니다. VersionRoute 미들웨어가 매핑합니다.

### 미들웨어 파이프라인

```
Cors → Security(31종) → RateLimit(슬라이딩 윈도우) → Platform(8개 플랫폼) → GeoIp → Locale
    → HashidsDecode → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption
```

### 엔드포인트 통계

- 공개 인터페이스: 23개 (인증/상품/분류/콘텐츠/검색/서비스)
- 인증 인터페이스: 47개 (사용자/장바구니/주문/결제/반품/평가/마케팅)
- Webhook: 1개 (결제 콜백)
- Admin: 1개 (환불 실행)
- Health: 1개 (/health 헬스체크)

### 통일 응답

```json
{"code": 0, "msg": "ok", "data": {}}
{"code": 1, "msg": "error", "data": null}
{"code": 0, "msg": "ok", "data": {"list":[], "total":100, "page":1, "per_page":20}}
```

### hg/apidoc 동적 문서

hg/apidoc을 사용해 컨트롤러 어노테이션에 따라 자동 생성됩니다. 시작 후 `/apidoc/`에 접속하세요.

어노테이션 예시:
```php
/**
 * @Apidoc\Title("사용자 로그인")
 * @Apidoc\Method("POST")
 * @Apidoc\Url("/api/auth/login")
 * @Apidoc\Param(name="email", type="string", require=true)
 * @Apidoc\Returned(name="access_token", type="string")
 */
public function login(Request $request) { ... }
```
