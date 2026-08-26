# Security Plugin Integration Review Report

**Date**: 2026-08-04
**Scope**: erikwang2013/security-php v1.1.6 integration
**Reviewer**: Claude Code (automated)

---

## 1. Test Results

| Check | Result |
|---|---|
| PHP syntax check (47 files) | All passed |
| PHPUnit (22 tests, 45 assertions) | All passed |
| SecurityGuard security payload test | Correctly blocks XSS + SQLi |
| SecurityGuard safe request test | No false positives |
| phpstan static analysis | Not installed (non-blocking) |

## 2. Fixed Issues

### 2.1 File upload data not passed to SecurityGuard (Critical)

**Files**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

The middleware only passed `$request->all()` to `SecurityGuard::guard()`, but that method does not include file upload data. The `UploadDetector` requires file data in `['tmp_name' => ..., 'name' => ...]` format.

**Fix**: Added a loop that merges `$request->file()` into the data array before passing it to `SecurityGuard::guard()`.

### 2.2 Admin encryptable config missing default value (Medium)

**File**: `admin/config/plugin/erikwang2013/encryptable/app.php`

The admin config uses `env('ENCRYPTION_KEY')` with no fallback value, returning `null` when the environment variable is missing. Service uses `getenv('ENCRYPTION_KEY') ?: ''` and correctly falls back to an empty string.

**Fix**: Admin config unified to use the `?: ''` operator, consistent with service behavior.

### 2.3 Docker Compose environment variables incomplete (Medium)

**File**: `docker-compose.yml`

- service container missing `ENCRYPTION_CIPHER` and `ENCRYPTION_PREVIOUS_KEYS`
- admin container missing `ENCRYPTION_KEY`, `ENCRYPTION_CIPHER`, `ENCRYPTION_PREVIOUS_KEYS`, `HASHIDS_SALT`, `SNOWFLAKE_WORKER_ID`, `SNOWFLAKE_DATACENTER_ID`

**Fix**: All missing environment variables added, using defaults consistent with `.env.example`.

### 2.4 WAF middleware duplicate detection (Critical, fixed in round 1)

The custom `SecurityMiddleware` contained ~200 lines of inline regexes fully duplicating the 31 detectors in the `security-php` package. Every request was scanned twice, wasting CPU and potentially double-blocking.

**Fix**: Middleware rewritten to use the `SecurityGuard::guard()` API, reduced from 341 to ~110 lines (service), 136 to ~85 lines (admin). Brute force protection and security response headers retained.

### 2.5 ENCRYPTION_KEY missing (Critical, fixed in round 1)

The `ENCRYPTION_KEY` in the `.env.example` file used a placeholder, and `ENCRYPTION_CIPHER` and `ENCRYPTION_PREVIOUS_KEYS` were missing. No actual `.env` file existed.

**Fix**: Generated a 32-byte base64 key, added `ENCRYPTION_CIPHER=AES-256-CBC` and `ENCRYPTION_PREVIOUS_KEYS`, created the `.env` file.

## 3. Ecosystem Configuration Completeness

### 3.1 Packages (consistent across both projects)

| Package | Version | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | Installed | Installed |
| erikwang2013/encryptable | - | Installed | Installed |
| erikwang2013/encryption | - | Installed | Installed |
| erikwang2013/jwt-webman | - | Installed | Installed |
| erikwang2013/hashids | - | Installed | Installed |
| erikwang2013/snowflake-php | - | Installed | Installed |
| erikwang2013/poster-php | - | Installed | Installed |
| erikwang2013/season | - | Installed | Installed |
| erikwang2013/webman-scout | - | Installed | Installed |

### 3.2 WAF Configuration

| Item | Service | Admin | Status |
|---|---|---|---|
| Config file | `config/plugin/erikwang2013/security-php/app.php` | Same | Published |
| Detectors enabled | 31/31 | 31/31 | Correct |
| IP blacklist | enabled (5 att/60s -> 900s ban) | Same | Correct |
| Block mode detectors | 28 | 28 | Correct |
| Log-only detectors | 3 (header_injection, ssti, nosql_injection) | 3 | Correct |
| Storage | file | file | Correct |
| Logging | enabled (file, 10MB rotate) | Same | Correct |
| Middleware registered | `config/middleware.php` | `config/middleware.php` | Correct |

### 3.3 Encryption Configuration

| Item | Service | Admin | Status |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | Same | Set |
| ENCRYPTION_CIPHER | `AES-256-CBC` | Same | Set |
| ENCRYPTION_PREVIOUS_KEYS | (empty) | (empty) | Set |
| encryptable config | `config/plugin/erikwang2013/encryptable/app.php` | Same (unified) | Correct |
| encryption config | `config/encryption.php` | - | Correct |
| .env file | Exists | Exists | Created |
| .env.example | Updated | Updated | Correct |
| docker-compose | Updated | Updated | Correct |

### 3.4 Models with Encryptable Trait

31 models use the `Encryptable` trait, with sensitive fields correctly declared as `$encryptable`:

| Category | Models | Sensitive Fields |
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
| Other | 15 more models | name fields |

## 4. Round 2 Fixes (API encryption + JWT keys)

### 4.1 API response encryption middleware (Medium, fixed)

**File**: `service/app/middleware/EncryptionMiddleware.php` (new)

The `erikwang2013/encryption` package was installed and the `app/common/Encryption` utility class existed, but had not been wired into the middleware pipeline. Interface sensitive data lacked transport-layer encryption/decryption.

**Fix**:
- Created `EncryptionMiddleware` with HTTP header-driven encryption/decryption:
  - `X-Encrypted: 1` — request decryption: decrypts the base64 ciphertext body into JSON before passing to the controller
  - `X-Encrypt-Response: 1` — response encryption: encrypts the `data` field of the response into base64 ciphertext
  - `X-Encrypt-Fields: field1,field2` — encrypts only the specified fields of the response
- Registered as the last level of the middleware stack (after HashidsEncode)
- Health checks (`/api/health`, `/api/ping`) and the documentation endpoint (`/apidoc`) skip encryption/decryption

### 4.2 Class/file name mismatch (Medium, fixed)

**File**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

The class `app\common\Encryption` was declared in the file `EncryptionHelper.php`, violating PSR-4, causing Composer autoloading to fail. The class could not be found by the autoloader in IDE and CLI environments.

**Fix**: File renamed to `Encryption.php` to match the class name.

### 4.3 JWT_SECRET_KEY empty (Low, fixed)

**Files**: `service/.env.example`, `service/.env`, `docker-compose.yml`

`JWT_SECRET_KEY` was an empty string. Although the JWT middleware has a `JWT_SECRET → JWT_SECRET_KEY` fallback chain (preferring `JWT_SECRET`), the placeholder value is insecure.

**Fix**: Generated a 32-byte base64 key, setting both `JWT_SECRET` and `JWT_SECRET_KEY`. Updated `.env.example`, `.env`, and `docker-compose.yml`.

## 5. Issues to Watch (Potential Optimization Points)

### 5.1 SecurityGuard dependency on webman/Workerman headers (Low Risk)

**Impact**: Detectors such as CSRF Origin, Host Header, DNS Rebinding, Request Smuggling, and CORS depend on HTTP header data in `$_SERVER`.

In the Workerman non-CGI environment, `$_SERVER` may not be fully populated with HTTP headers. SecurityGuard already has fallback logic (e.g., skipping detection when header values are empty), so it will **not cause false positives**, but it **may miss some header attacks**. The impact is low because Nginx reverse proxy layer usually filters malicious headers as well.

**Suggestion**: If more complete header detection is needed, header values can be explicitly passed in the `$meta` parameter of SecurityGuard. No changes needed currently.

### 5.2 CSRF Origin detector impact on Admin (No Risk)

Admin's `csrf_origin` detector runs in `block` mode with empty `allowed_origins`. However, since the detector only triggers when an Origin header exists and does not match the Host, admin console access normally has no Origin header (same-origin access), so it will **not falsely block**.

### 5.3 All 31 detectors enabled, per-request overhead (Performance Note)

All 31 detectors run on every request (including JWT, WebSocket, GraphQL, CSV, prototype pollution, etc.). Each detector executes regex matching against all request fields. For this project's usage scenario, the overhead is acceptable (webman is a resident-memory process with no CGI cold-start overhead).

### 5.4 IP blacklist persistence (Operational Note)

The storage backend is `file` mode, default path `sys_get_temp_dir() . '/security_storage.json'`. In Docker containers, the temp directory may be lost after restart. If blacklist sharing is needed in multi-container deployments, switch to `redis` mode.

## 6. Changed Files Summary

```
admin/.env.example                                (ENCRYPTION_KEY added)
admin/.env                                        (created from .env.example)
admin/CLAUDE.md                                   (middleware stack + tech stack updated)
admin/composer.json                               (security-php dependency)
admin/config/plugin/erikwang2013/encryptable/app.php  (default value unified)
admin/config/plugin/erikwang2013/security-php/app.php  (new, 31 detectors)
admin/app/middleware/SecurityMiddleware.php       (rewritten to use SecurityGuard)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + JWT keys updated)
service/.env                                      (created from .env.example, JWT keys synced)
service/CLAUDE.md                                 (middleware stack + Encryption + tech stack updated)
service/composer.json                             (security-php dependency)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (new, 31 detectors)
service/app/common/Encryption.php                 (renamed from EncryptionHelper.php)
service/app/middleware/EncryptionMiddleware.php   (new, API response encryption/decryption)
service/app/middleware/SecurityMiddleware.php     (rewritten to use SecurityGuard + file upload)
docker-compose.yml                                (encryption/jwt environment variables completed)
docs/security-review.md                           (this report)
```

## 7. Conclusion

**Status**: Passed

- WAF detection correctly blocks XSS, SQL injection, and other attacks (31 detectors, SecurityGuard::guard API)
- Sensitive field encryption fully configured (31 models, 6 categories of sensitive data, Encryptable trait)
- API transport encryption/decryption wired into the middleware (EncryptionMiddleware, AES-256-CBC, header-triggered)
- JWT keys configured (both JWT_SECRET and JWT_SECRET_KEY set)
- File upload detection fixed (merging $_FILES data into SecurityGuard)
- No functional regressions (22/22 tests passing)
- No duplicate middleware detection
- Docker deployment environment variables complete
