# 크로스보더 전자상거래 플랫폼 — 설치 가이드

> Cross-border E-Commerce Platform Installation Guide
>
> [중국어 README](../../../README.md) | [English README](../../README-EN.md) | [감사 보고서](../../AUDIT-REPORT.md)

---

## 환경 요구사항 / Requirements

| 구성 요소 | 최소 버전 | 권장 버전 |
|------|----------|----------|
| PHP | 8.3+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (선택/optional) |

### PHP 확장

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## 설치 방법 / Installation Methods

### 방법 1（권장）: Web 원클릭 설치 마법사

브라우저로 설치 페이지에 접속하여 데이터베이스 정보와 관리자 계정을 입력하면, **테이블 생성, 설정, 관리자 생성이 전자동으로 완료**됩니다.

```bash
# 1. 의존성 설치
cd admin/
composer install

# 2. 관리 백엔드 시작
php start.php start

# 3. 브라우저 접속（최초 접속 시 설치 페이지로 자동 이동）
# http://127.0.0.1:8788/app/admin/install/step1
```

설치 마법사가 **자동으로 완료**합니다:
- MySQL 데이터베이스 생성（없을 경우）
- `install.sql`의 전체 117개 테이블 임포트（7개 `wa_` + 110개 `erik_`）
- 관리 백엔드 메뉴 임포트
- `plugin/admin/config/database.php` 및 `thinkorm.php` 생성
- `service/.env` 생성（랜덤 생성된 JWT/Hashids/암호화 키 포함）
- 슈퍼 관리자 계정 생성
- SIGUSR1 신호 전송으로 서비스 리로드

> 설치 완료 후에도 service/ API 서비스를 시작해야 합니다（아래 5단계 참조).

---

### 방법 2: 수동 설치 / Manual Installation

<details>
<summary>커맨드라인 배포 또는 기존 데이터베이스 환경에 적합</summary>

### 1. 데이터베이스 생성

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. 데이터베이스 임포트

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql`에는 **117개 테이블** 및 기본 시드 데이터가 포함되어 있습니다.

### 3. service/.env 설정

```bash
cd service/
cp .env.example .env
# .env를 편집하여 실제 데이터베이스/Redis/JWT 등 파라미터 설정
```

**핵심 설정 항목:**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<랜덤 32바이트 키>
HASHIDS_SALT=<랜덤 솔트 값>
ENCRYPTION_KEY=<랜덤 32바이트 키>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. admin/ 설정

```bash
cd admin/
cp .env.example .env
# .env를 편집하여 service와 동일한 데이터베이스 정보 입력
```

### 5. 관리자 계정 생성

```sql
-- 비밀번호는 bcrypt로 생성해야 합니다
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', '슈퍼 관리자', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### 방법 3: Docker 배포 / Docker Deployment

```bash
# 1. 환경 변수 설정
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. 전체 서비스 시작
docker-compose up -d

# 3. Web 설치 마법사 실행
# http://localhost/app/admin/install/step1
```

Docker 서비스: Nginx(:80) → service(:8787) + admin(:8788), MySQL(:3306), Redis(:6379), ES(:9200)

---

### 서비스 시작 / Start Services

```bash
# 의존성 설치（두 프로젝트 모두 필요）
cd service/ && composer install
cd admin/ && composer install

# API 서비스 시작
cd service/
php start.php start -d

# 관리 백엔드 시작
cd admin/
php start.php start -d
```

| 서비스 | 기본 포트 | 검증 방법 |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| 관리 백엔드 | 8788 | 브라우저로 `http://127.0.0.1:8788/app/admin` 접속 |

### 시드 데이터 임포트（선택）/ Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # 국가/지역
php start.php seed:currencies    # 통화
php start.php seed:hs_codes      # HS Code 코드
php start.php seed:compliance    # 컴플라이언스 분류
```

---

## 디렉터리 구조 / Directory Structure

```
shop-php/
├── install.sql              # 병합된 완전한 설치 SQL
├── admin/                   # 관리 백엔드 (webman-admin + LayUI)
│   ├── config/database.php  # 데이터베이스 설정
│   ├── plugin/admin/        # webman-admin 플러그인
│   └── start.php
├── service/                 # API 서비스 (webman RESTful)
│   ├── config/              # 설정 파일
│   ├── database/schema.sql  # 원본 비즈니스 테이블 SQL (install.sql로 대체됨)
│   ├── database/seeders/    # 시드 데이터
│   └── start.php
```

---

## 데이터베이스 구조 개요 / Database Schema Overview

| 모듈 | 테이블 접두사 | 테이블 수 | 설명 |
|------|--------|--------|------|
| 관리 백엔드 시스템 | `wa_` | 7 | 관리자/역할/권한/설정/첨부 |
| 사용자와 계정 | `erik_users_*` | 7 | 사용자/주소/소셜/KYC/즐겨찾기/회원 |
| 상품과 분류 | `erik_product_*` | 16 | 상품/SKU/다국어/다중 통화/평가/컴플라이언스/HS |
| 장바구니와 주문 | `erik_order_*` | 9 | 장바구니/주문/결제/환불/반품/통관 |
| 국가/통화/물류 | `erik_shipping_*` | 11 | 국가/통화/환율/물류/존/창고/재고 |
| 통관과 세금 | `erik_hs_*` | 5 | HS 코드/관세/VAT/컴플라이언스 제한 |
| 결제와 자금 | `erik_payment_*` | 6 | 결제 게이트웨이/플랫폼 분배/공급업체 정산/환율 손익 |
| 마케팅 | `erik_coupon_*` | 9 | 쿠폰/플래시 세일/공동구매/유통 |
| 공급망 | `erik_supplier_*` | 7 | 공급업체/구매/검품 |
| 리스크와 컴플라이언스 | `erik_risk_*` | 6 | 리스크 규칙/GDPR/Cookie/프라이버시 |
| 다중 플랫폼 | `erik_platform_*` | 8 | 다중 스토어/플랫폼 계정/등록/셀러 |
| 콘텐츠와 경험 | `erik_*` | 12 | CMS/Feed/사이즈/알림/메일/검색/운영 로그 |
| 구독/포인트 등 | `erik_*` | 7 | 구독/포인트/기프트 카드/B2B |
| AB 테스트/API/설정 | `erik_*` | 7 | AB 테스트/속도 제한/API 문서/시스템 설정 |

---

## 자주 묻는 질문 / Troubleshooting

### MySQL 오류 "Specified key was too long"

```sql
-- utf8mb4 + InnoDB를 사용하고 innodb_large_prefix가 활성화되어 있는지 확인
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### 포트 충돌 / Port Conflict

`admin/.env` 또는 `service/.env`의 `APP_PORT`를 수정하세요.

### Redis 연결 실패

Redis 확장이 설치되어 있고 Redis 서비스가 시작되었는지 확인:
```bash
redis-cli ping  # PONG이 반환되어야 함
```

### Snowflake ID 충돌

여러 서버가 동시에 인스턴스화되는 경우, 각 서버의 `SNOWFLAKE_WORKER_ID`가 서로 다르도록 설정하세요（0-31).

---

## 개발 명령어 참조 / Development Commands

```bash
# service/ (API)
php start.php start          # 시작
php start.php start -d       # 데몬 프로세스
php start.php reload         # 핫 리로드
php start.php stop           # 중지
php start.php status         # 상태

# admin/ (관리 백엔드)
php start.php start
php start.php start -d
php start.php reload
```
