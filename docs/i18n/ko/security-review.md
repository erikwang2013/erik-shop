# Security Plugin 통합 검토 보고서

**Date**: 2026-08-04
**Scope**: erikwang2013/security-php v1.1.6 통합
**Reviewer**: Claude Code (automated)

---

## 1. 테스트 결과

| Check | Result |
|---|---|
| PHP 문법 검사 (47개 파일) | 전부 통과 |
| PHPUnit (22 tests, 45 assertions) | 전부 통과 |
| SecurityGuard 보안 페이로드 테스트 | XSS + SQLi 정확히 차단 |
| SecurityGuard 안전 요청 테스트 | 오탐 없음 |
| phpstan 정적 분석 | 미설치 (비차단) |

## 2. 수정된 문제

### 2.1 파일 업로드 데이터가 SecurityGuard에 전달되지 않음 (Critical)

**파일**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

미들웨어가 `$request->all()`만 `SecurityGuard::guard()`에 전달하는데, 이 메서드에는 파일 업로드 데이터가 포함되지 않습니다. `UploadDetector`는 `['tmp_name' => ..., 'name' => ...]` 형식의 파일 데이터가 필요합니다.

**수정**: `$request->file()`을 데이터 배열에 병합한 뒤 `SecurityGuard::guard()`에 전달하는 루프를 추가했습니다.

### 2.2 Admin encryptable 설정에 기본값 없음 (Medium)

**파일**: `admin/config/plugin/erikwang2013/encryptable/app.php`

admin 설정이 `env('ENCRYPTION_KEY')`를 폴백 값 없이 사용해 환경 변수가 없으면 `null`을 반환합니다. Service는 `getenv('ENCRYPTION_KEY') ?: ''`를 사용해 빈 문자열로 올바르게 폴백합니다.

**수정**: admin 설정도 `?: ''` 연산자로 통일해 service 동작과 일치시켰습니다.

### 2.3 Docker Compose 환경 변수 불완전 (Medium)

**파일**: `docker-compose.yml`

- service 컨테이너에 `ENCRYPTION_CIPHER`와 `ENCRYPTION_PREVIOUS_KEYS` 누락
- admin 컨테이너에 `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID` 누락

**수정**: 누락된 환경 변수를 모두 추가했으며, `.env.example`과 일치하는 기본값을 사용합니다.

### 2.4 WAF 미들웨어 중복 탐지 (Critical, 1차 수정 완료)

커스텀 `SecurityMiddleware`가 ~200줄의 인라인 정규식을 포함해 `security-php` 패키지의 31개 탐지기와 완전히 중복됩니다. 요청마다 두 번 스캔해 CPU를 낭비하고 이중 차단이 발생할 수 있습니다.

**수정**: 미들웨어를 `SecurityGuard::guard()` API 사용으로 재작성해 service 341줄 → ~110줄, admin 136줄 → ~85줄로 축소했습니다. 무차별 대입 방어와 응답 보안 헤더는 유지됩니다.

### 2.5 ENCRYPTION_KEY 누락 (Critical, 1차 수정 완료)

`.env.example` 파일의 `ENCRYPTION_KEY`가 플레이스홀더이며 `ENCRYPTION_CIPHER`와 `ENCRYPTION_PREVIOUS_KEYS`가 없습니다. 실제 `.env` 파일도 없습니다.

**수정**: 32바이트 base64 키를 생성하고 `ENCRYPTION_CIPHER=AES-256-CBC`와 `ENCRYPTION_PREVIOUS_KEYS`를 추가했으며 `.env` 파일을 생성했습니다.

## 3. 생태계 설정 완전성

### 3.1 Packages (두 프로젝트 일치)

| Package | 버전 | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | 설치됨 | 설치됨 |
| erikwang2013/encryptable | - | 설치됨 | 설치됨 |
| erikwang2013/encryption | - | 설치됨 | 설치됨 |
| erikwang2013/jwt-webman | - | 설치됨 | 설치됨 |
| erikwang2013/hashids | - | 설치됨 | 설치됨 |
| erikwang2013/snowflake-php | - | 설치됨 | 설치됨 |
| erikwang2013/poster-php | - | 설치됨 | 설치됨 |
| erikwang2013/season | - | 설치됨 | 설치됨 |
| erikwang2013/webman-scout | - | 설치됨 | 설치됨 |

### 3.2 WAF 설정

| 항목 | Service | Admin | 상태 |
|---|---|---|---|
| 설정 파일 | `config/plugin/erikwang2013/security-php/app.php` | 동일 | 배포됨 |
| 활성화된 탐지기 | 31/31 | 31/31 | 정확 |
| IP 블랙리스트 | enabled (5회/60s → 900s 차단) | 동일 | 정확 |
| 차단 모드 탐지기 | 28 | 28 | 정확 |
| 로그 전용 탐지기 | 3 (header_injection, ssti, nosql_injection) | 3 | 정확 |
| 스토리지 | file | file | 정확 |
| 로깅 | enabled (file, 10MB 로테이션) | 동일 | 정확 |
| 미들웨어 등록 | `config/middleware.php` | `config/middleware.php` | 정확 |

### 3.3 암호화 설정

| 항목 | Service | Admin | 상태 |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | 동일 | 설정됨 |
| ENCRYPTION_CIPHER | `AES-256-CBC` | 동일 | 설정됨 |
| ENCRYPTION_PREVIOUS_KEYS | (empty) | (empty) | 설정됨 |
| encryptable 설정 | `config/plugin/erikwang2013/encryptable/app.php` | 동일 (통일됨) | 정확 |
| encryption 설정 | `config/encryption.php` | - | 정확 |
| .env 파일 | 존재 | 존재 | 생성됨 |
| .env.example | 업데이트됨 | 업데이트됨 | 정확 |
| docker-compose | 업데이트됨 | 업데이트됨 | 정확 |

### 3.4 Encryptable Trait 사용 모델

31개 모델이 `Encryptable` trait을 사용하며, 민감 필드가 `$encryptable`로 올바르게 선언되었습니다:

| 카테고리 | 모델 | 민감 필드 |
|---|---|---|
| User PII | Users | email, mobile |
| User PII | UserAddresses | name, phone, detail |
| User PII | UserKyc | real_name, id_number |
| User PII | UserSocialAccounts | access_token, refresh_token |
| Privacy | PrivacyRequests | email |
| Finance | GiftCards | receiver_email |
| Finance | AffiliatePayouts | account |
| Finance | PaymentGateways | name, api_key, api_secret, webhook_secret |
| Platform | PlatformOrders | platform_account_id, buyer_name, buyer_email |
| Platform | PlatformAccounts | account_name, api_key, api_secret |
| Platform | PlatformListings | platform_account_id |
| Logistics | LogisticsCompanies | name, api_key |
| Supplier | Suppliers | name, email, phone |
| Supplier | B2bVerifications | company_name |
| Merchant | Merchants | store_name, email, phone |
| Other | EmailLogs | to_email |
| Other | 15개 추가 모델 | name 필드 |

## 4. 2차 수정 (API 암호화 + JWT 키)

### 4.1 API 응답 암호화 미들웨어 (Medium, 수정됨)

**파일**: `service/app/middleware/EncryptionMiddleware.php` (신규)

`erikwang2013/encryption` 패키지가 설치되어 있고 `app/common/Encryption` 유틸리티 클래스가 존재하지만, 이전에는 미들웨어 파이프라인에 연결되지 않았습니다. 인터페이스 민감 데이터에 전송 계층 암복호화가 없었습니다.

**수정**:
- HTTP header 기반 암복호화를 수행하는 `EncryptionMiddleware` 생성:
  - `X-Encrypted: 1` — 요청 복호화: base64 암호문 body를 JSON으로 복호화해 컨트롤러에 전달
  - `X-Encrypt-Response: 1` — 응답 암호화: 응답의 `data` 필드를 base64 암호문으로 암호화
  - `X-Encrypt-Fields: field1,field2` — 응답의 지정 필드만 암호화
- 미들웨어 스택 마지막 단계(HashidsEncode 이후)로 등록
- 헬스 체크(`/api/health`, `/api/ping`)와 문서 엔드포인트(`/apidoc`)는 암복호화 제외

### 4.2 클래스명/파일명 불일치 (Medium, 수정됨)

**파일**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

클래스 `app\common\Encryption`이 `EncryptionHelper.php` 파일에 선언되어 PSR-4 규범과 일치하지 않아 Composer 자동 로딩이 실패합니다. IDE 및 CLI 환경에서 해당 클래스를 autoloader가 찾지 못할 수 있습니다.

**수정**: 파일명을 클래스명에 맞게 `Encryption.php`로 변경했습니다.

### 4.3 JWT_SECRET_KEY 빈 값 (Low, 수정됨)

**파일**: `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY`가 빈 문자열이며, JWT 미들웨어에 `JWT_SECRET → JWT_SECRET_KEY` 폴백 체인(우선 `JWT_SECRET` 사용)이 있지만 플레이스홀더 값은 안전하지 않습니다.

**수정**: 32바이트 base64 키를 생성해 `JWT_SECRET`과 `JWT_SECRET_KEY`를 모두 설정했습니다. `.env.example`, `.env`, `docker-compose.yml`을 업데이트했습니다.

## 5. 관찰 대기 문제 (잠재적 최적화 포인트)

### 5.1 SecurityGuard의 webman/Workerman header 의존 (Low Risk)

**영향**: CSRF Origin, Host Header, DNS Rebinding, Request Smuggling, CORS 등 탐지기가 `$_SERVER`의 HTTP 헤더 데이터에 의존합니다.

Workerman 비-CGI 환경에서는 `$_SERVER`에 HTTP 헤더가 완전히 채워지지 않을 수 있습니다. SecurityGuard에 폴백 로직(예: header 값이 비어 있으면 탐지 건너뜀)이 있어 **오탐은 없지만** 일부 header 공격을 **놓칠 수 있습니다**. Nginx 리버스 프록시 계층에서도 일반적으로 악성 헤더를 필터링하므로 영향도는 낮습니다.

**제안**: 더 완전한 header 탐지가 필요하다면 SecurityGuard의 `$meta` 파라미터에 header 값을 명시적으로 전달할 수 있습니다. 현재는 수정 불필요.

### 5.2 CSRF Origin 탐지기의 Admin 영향 (No Risk)

Admin의 `csrf_origin` 탐지기는 `block` 모드에서 `allowed_origins`가 비어 있습니다. 하지만 탐지기는 Origin header가 존재하고 Host와 일치하지 않을 때만 작동하며, 관리 백엔드 접속 시 일반적으로 Origin header가 없으므로(동일 출처 접속) **오탐 차단이 없습니다**.

### 5.3 31개 탐지기 전부 활성화, 요청당 오버헤드 (Performance Note)

모든 요청이 31개 탐지기 전체(JWT, WebSocket, GraphQL, CSV, prototype pollution 등 포함)를 실행합니다. 각 탐지기가 요청의 모든 필드에 대해 정규식 매칭을 수행합니다. 이 프로젝트의 사용 시나리오에서 오버헤드는 허용 범위입니다(webman은 상주 메모리 프로세스로 CGI 콜드 스타트 오버헤드 없음).

### 5.4 IP 블랙리스트 영구화 (Operational Note)

스토리지 백엔드가 `file` 모드이며 기본 경로는 `sys_get_temp_dir() . '/security_storage.json'`입니다. Docker 컨테이너에서 재시작 시 임시 디렉터리가 유실될 수 있습니다. 다중 컨테이너 배포에서 블랙리스트를 공유해야 한다면 `redis` 모드로 전환할 수 있습니다.

## 6. 변경 파일 요약

```
admin/.env.example                                (ENCRYPTION_KEY 추가)
admin/.env                                        (.env.example에서 생성)
admin/CLAUDE.md                                   (미들웨어 스택 + tech stack 업데이트)
admin/composer.json                               (security-php 의존성)
admin/config/plugin/erikwang2013/encryptable/app.php  (기본값 통일)
admin/config/plugin/erikwang2013/security-php/app.php  (신규, 31 탐지기)
admin/app/middleware/SecurityMiddleware.php       (SecurityGuard 사용으로 재작성)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + JWT 키 업데이트)
service/.env                                      (.env.example에서 생성, JWT 키 동기화)
service/CLAUDE.md                                 (미들웨어 스택 + Encryption + tech stack 업데이트)
service/composer.json                             (security-php 의존성)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (신규, 31 탐지기)
service/app/common/Encryption.php                 (EncryptionHelper.php에서 이름 변경)
service/app/middleware/EncryptionMiddleware.php   (신규, API 응답 암복호화)
service/app/middleware/SecurityMiddleware.php     (SecurityGuard + 파일 업로드 사용으로 재작성)
docker-compose.yml                                (encryption/jwt 환경 변수 보완)
docs/security-review.md                           (본 보고서)
```

## 7. 결론

**상태**: 통과

- WAF 탐지가 XSS, SQL 인젝션 등 공격을 정확히 차단 (31 탐지기, SecurityGuard::guard API)
- 민감 필드 암호화 설정 완전 (31개 모델, 6종 민감 데이터, Encryptable trait)
- API 전송 암복호화가 미들웨어에 연결됨 (EncryptionMiddleware, AES-256-CBC, header 트리거)
- JWT 키 설정 완료 (JWT_SECRET + JWT_SECRET_KEY 모두 설정)
- 파일 업로드 탐지 수정됨 ($_FILES 데이터 병합 후 SecurityGuard에 전달)
- 기능 회귀 없음 (22/22 테스트 통과)
- 미들웨어 중복 탐지 없음
- Docker 배포 환경 변수 완전
