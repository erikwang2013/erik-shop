# 크로스보더 전자상거래 플랫폼 — 설계 문서

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 1. 데이터베이스 설계

### 1.1 명명 규칙

- 테이블 접두사: `erik_`
- 기본 키: `id BIGINT UNSIGNED NOT NULL` (snowflake 생성, 비자동증가)
- 타임스탬프: `created_at`, `updated_at`, `deleted_at` (소프트 삭제)
- 엔진: InnoDB, 문자셋: utf8mb4_unicode_ci

### 1.2 모듈 구분 (110개 테이블)

| 모듈 | 테이블 수 | 핵심 테이블 |
|------|------|--------|
| 사용자와 계정 | 7 | users, user_addresses, user_social_accounts, user_kyc, user_wishlists, membership_levels, membership_benefits |
| 상품과 분류 | 19 | products, product_translations, product_skus, product_sku_prices, product_images, product_attrs, product_reviews, product_compliance, product_hs_codes, compliance_categories |
| 거래 | 8 | orders, order_items, order_logs, payments, refunds, return_orders, return_labels, order_documents |
| 결제와 자금 | 5 | payment_gateways, payment_gateway_methods, platform_settlements, supplier_settlements, currency_exchange_gains_losses |
| 물류 | 9 | logistics_companies, shipping_zones, shipping_zone_rates, warehouses, shipments, shipping_insurances, inventory_logs, inventory_transfers |
| 통관과 세금 | 5 | hs_codes, tariff_rules, vat_settings, country_compliance_rules |
| 마케팅 | 8 | coupons, user_coupons, flash_sales, flash_sale_skus, group_buys, affiliate_links, affiliate_commissions, affiliate_payouts |
| 공급망 | 5 | suppliers, purchase_orders, purchase_order_items, quality_inspections, quality_inspection_items |
| 리스크와 컴플라이언스 | 5 | risk_rules, risk_logs, privacy_requests, cookie_consents, privacy_policy_versions |
| 다중 플랫폼 | 9 | shops, platform_accounts, platform_listings, platform_orders, platform_order_items, merchants, merchant_products, merchant_settlements |
| 콘텐츠와 경험 | 14 | cms_pages, cms_page_translations, size_charts, size_chart_values, product_feeds, notifications, email_templates, price_alerts, search_logs, operation_logs, settings |
| 구독과 B2B | 9 | subscriptions, subscription_orders, point_rules, point_logs, gift_cards, b2b_prices, b2b_verifications, b2b_quotes |
| 고객 서비스 | 5 | chat_sessions, chat_messages, knowledge_base, knowledge_base_translations, faq_translations |
| AB 테스트 | 3 | ab_tests, ab_test_variants, ab_test_results |
| API 관리 | 2 | api_rate_limits, api_docs |
| 기초 데이터 | 3 | countries, currencies, exchange_rates |

### 1.3 플랫폼 추적 필드

| 테이블 | 필드 | 설명 |
|----|------|------|
| orders | platform VARCHAR(16) | 주문 플랫폼 |
| payments | platform VARCHAR(16) | 결제 플랫폼 |
| operation_logs | platform VARCHAR(16) | 운영 플랫폼 |
| users | last_login_platform VARCHAR(16) | 마지막 로그인 플랫폼 |
| search_logs | platform VARCHAR(16) | 검색 플랫폼 |
| chat_messages | platform VARCHAR(16) | 메시지 출처 |

---

## 2. API 설계

API 버전 관리, 미들웨어 파이프라인, 엔드포인트 통계와 통일 응답 규범은 [API 인터페이스 문서](api.md)를 참조하세요.

---

## 3. 보안 설계

### 3.1 SecurityMiddleware가 security-php 31개 탐지기 래핑

| # | 유형 | 오류 코드 | Service | Admin |
|---|------|--------|---------|-------|
| 1 | XSS | 40001 | ✅ | ✅ |
| 2 | SQL 인젝션 | 40002 | ✅ | ✅ |
| 3 | CRLF | 40003 | ✅ | ✅ |
| 4 | 경로 탐색 | 40004 | ✅ | ✅ |
| 5 | Body 과대 | 40005 | ✅ | ✅ |
| 6 | Content-Type | 40006 | ✅ | ✅ |
| 7 | 파일 업로드 | 40009 | ✅ | ✅ |
| 8 | 보안 응답 헤더 | — | ✅ | ✅ |
| 9 | 무차별 대입 | 40008 | ✅ | ✅ |
| 10 | XXE | 40010 | ✅ | ✅ |
| 11 | SSRF | 40011 | ✅ | ✅ |
| 12 | HTTP 메서드 | 40012 | ✅ | ✅ |
| 13 | Host 헤더 | 40013 | ✅ | — |
| 14 | 민감 데이터 마스킹 | — | ✅ | ✅ |
| 15 | CORS 화이트리스트 | — | ⚠️ | ⚠️ |

### 3.2 3계층 암호화

| 계층 | 기술 | 패키지 |
|------|------|-----|
| 전송 계층 | AES-256-CBC | erikwang2013/encryption |
| 데이터베이스 계층 | Encryptable trait | erikwang2013/encryptable (Maize) |
| ID 난독화 | Hashids | erikwang2013/hashids |

---

## 4. 고동시성 설계

### 4.1 속도 제한

토큰 버킷 슬라이딩 윈도우(Redis ZSET, support\Redis 퍼사드 경유): 기본 60s/100회, 로그인 10회/60s, 가입 5회/300s, 소셜 로그인 5회/300s, 결제 5회/60s, 주문 3회/10s, 검색 10회/1s

### 4.2 Redis 용도

Redis는 속도 제한 토큰 버킷(`support\Redis` 퍼사드), 휴먼 인증 코드와 Session 저장에 사용됩니다. 비즈니스 데이터는 애플리케이션 계층 캐시를 하지 않고 MySQL(읽기/쓰기 분리 + 커넥션 풀)을 직접 읽습니다.

### 4.3 커넥션 풀

MySQL: 50max/10min/2s 타임아웃 | 읽기/쓰기 분리: 30max/5min (2개 읽기 복제본, sticky=true) | Redis: 30max/5min



---

## 5. 국제화

- 인터페이스: zh_CN, zh_HK, en, ja, ko
- 콘텐츠: erik_product_translations locale별 독립 행
- 가격: erik_product_sku_prices 통화별 독립 가격 책정
- Header: Accept-Language + API-Version

## 6. API 문서

hg/apidoc을 사용해 컨트롤러 어노테이션 기반으로 자동 생성하며, 자세한 내용은 [API 인터페이스 문서](api.md)를 참조하세요. 시작 후 `/apidoc/`으로 접속합니다.

## 7. 테스트

22 tests / 45 assertions — ALL PASS

```bash
cd service && php vendor/bin/phpunit tests/
# SecurityTest (12) + JwtTest (4) + ApiResponseTest (3) + RedisFacadeTest (3)
```

자세한 내용: [기능 설계 문서](features.md) | [전체 아키텍처 문서](architecture-full.md) | [배포 문서](deployment.md)
