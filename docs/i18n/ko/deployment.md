# 크로스보더 전자상거래 플랫폼 — 배포 문서

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Docker 배포 (권장)

### 1.1 환경 요구사항

- Docker 24.0+ / Docker Compose v2
- 호스트: Linux (권장 Ubuntu 22.04+)
- 메모리: 최소 4GB, 권장 8GB+

### 1.2 배포 절차

```bash
# 1. 프로젝트 클론
git clone https://github.com/erikwang2013/shop-php.git
cd shop-php

# 2. 환경 변수 설정
cp .env.example .env
# .env를 편집하여 모든 비밀번호와 키 변경:
#   DB_PASS, JWT_SECRET, HASHIDS_SALT, ENCRYPTION_KEY
#   STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET 등

# 3. 전체 서비스 시작
docker compose up -d

# 4. 로그 확인
docker compose logs -f service
docker compose logs -f admin

# 5. 접속
# API: http://localhost/api
# 관리 백엔드: http://admin.localhost
```

### 1.3 서비스 목록

| 서비스 | 포트 | 설명 |
|------|------|------|
| nginx | 80, 443 | 리버스 프록시 |
| service | 8787 (내부) | PHP 비즈니스 API |
| admin | 8788 (내부) | 관리 백엔드 |
| mysql | 3306 | MySQL 8.0 |
| redis | 6379 | Redis 7 |
| elasticsearch | 9200 | ES 8 |

### 1.4 프로덕션 환경 체크리스트

- [ ] `.env`의 모든 키가 랜덤 값으로 변경되었는지
- [ ] `STRIPE_MODE=live` (프로덕션 환경)
- [ ] `APP_ENV=production`
- [ ] `config/app.php`의 `debug`를 `false`로 설정
- [ ] SSL 인증서 설정 (nginx+Let's Encrypt)
- [ ] 루트 `install.sql` 임포트 완료 (117개 테이블, Web 설치 마법사가 자동 임포트)
- [ ] ES 인덱스 생성: `php start.php scout:import "app\model\Products"`
- [ ] MySQL/Redis/ES 데이터 볼륨 백업 설정

## 2. 수동 배포

### 2.1 환경 의존성

- PHP 8.3+ (ext: pdo_mysql, bcmath, opcache, redis, gd, zip, intl, sockets, pcntl)
- MySQL 8.0+
- Redis 7+
- Elasticsearch 8+ (선택, 검색 기능에 필요)
- Composer 2.x

### 2.2 Service API

```bash
cd service
cp ../.env.example .env
# .env 편집
composer install --no-dev --optimize-autoloader
php start.php start -d
# 리슨: http://0.0.0.0:8787
```

### 2.3 Admin 관리 백엔드

```bash
cd admin
composer install --no-dev --optimize-autoloader
php start.php start -d
# 리슨: http://0.0.0.0:8787 (다른 포트는 Nginx 리버스 프록시로 구분 필요)
```

### 2.4 Nginx 리버스 프록시

```nginx
# docker/nginx/conf.d/shop.conf 참조
# api.erik.xyz → service:8787
# admin.erik.xyz → admin:8787
```

## 3. 데이터베이스 초기화

```bash
# 데이터베이스 생성
mysql -u root -p -e "CREATE DATABASE erik_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 테이블 구조 임포트
mysql -u root -p erik_shop < install.sql

# 시드 데이터 임포트 (선택)
php -r "
require 'vendor/autoload.php';
// 국가/통화/HS Code/물류 구역 등 시드 데이터 임포트
"
```

## 4. 환경 변수 참조

| 변수 | 기본값 | 설명 |
|------|--------|------|
| APP_ENV | production | 애플리케이션 환경 |
| DB_HOST | 127.0.0.1 | 데이터베이스 주소 |
| DB_PORT | 3306 | 데이터베이스 포트 |
| DB_NAME | erik_shop | 데이터베이스 이름 |
| DB_USER | erik | 데이터베이스 사용자 |
| DB_PASS | (필수) | 데이터베이스 비밀번호 |
| REDIS_HOST | 127.0.0.1 | Redis 주소 |
| JWT_SECRET | (필수) | JWT 서명 키(256bit) |
| HASHIDS_SALT | (필수) | Hashids 솔트 값 |
| ENCRYPTION_KEY | (필수) | AES 암호화 키 |
| SNOWFLAKE_WORKER_ID | 1 | Snowflake worker ID (service=1, admin=2) |
| STRIPE_SECRET_KEY | - | Stripe 키 |
| STRIPE_WEBHOOK_SECRET | - | Stripe Webhook 서명 검증 |

## 5. 운영 명령어

```bash
# Service API
cd service
php start.php status        # 상태 확인
php start.php reload        # 무중단 재시작
php start.php stop          # 중지

# Admin
cd admin
php start.php status
php start.php reload
php start.php stop

# Docker
docker compose ps           # 컨테이너 상태 확인
docker compose logs -f      # 로그 확인
docker compose restart      # 전체 재시작
docker compose down         # 중지
```
