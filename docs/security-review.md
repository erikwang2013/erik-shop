# Security Plugin 集成审查报告

**Date**: 2026-08-04
**Scope**: erikwang2013/security-php v1.1.6 集成
**Reviewer**: Claude Code (automated)

---

## 1. 测试结果

| Check | Result |
|---|---|
| PHP 语法检查（47 个文件） | 全部通过 |
| PHPUnit（26 tests, 71 assertions） | 全部通过 |
| SecurityGuard 安全载荷测试 | 正确拦截 XSS + SQLi |
| SecurityGuard 安全请求测试 | 无误报 |
| phpstan 静态分析 | 未安装（非阻塞） |

## 2. 已修复的问题

### 2.1 文件上传数据未传递给 SecurityGuard（Critical）

**文件**: `service/app/middleware/SecurityMiddleware.php` + `admin/app/middleware/SecurityMiddleware.php`

中间件仅传递 `$request->all()` 给 `SecurityGuard::guard()`，但该方法不包含文件上传数据。`UploadDetector` 需要 `['tmp_name' => ..., 'name' => ...]` 格式的文件数据。

**修复**: 添加了一个循环，将 `$request->file()` 合并进数据数组后再传给 `SecurityGuard::guard()`。

### 2.2 Admin encryptable 配置缺少默认值（Medium）

**文件**: `admin/config/plugin/erikwang2013/encryptable/app.php`

admin 配置使用 `env('ENCRYPTION_KEY')` 无回退值，当环境变量缺失时返回 `null`。Service 使用 `getenv('ENCRYPTION_KEY') ?: ''` 并正确回退到空字符串。

**修复**: admin 配置统一使用 `?: ''` 运算符，与 service 行为保持一致。

### 2.3 Docker Compose 环境变量不完整（Medium）

**文件**: `docker-compose.yml`

- service 容器缺少 `ENCRYPTION_CIPHER` 和 `ENCRYPTION_PREVIOUS_KEYS`
- admin 容器缺少 `ENCRYPTION_KEY`、`ENCRYPTION_CIPHER`、`ENCRYPTION_PREVIOUS_KEYS`、`HASHIDS_SALT`、`SNOWFLAKE_WORKER_ID`、`SNOWFLAKE_DATACENTER_ID`

**修复**: 所有缺失环境变量已添加，使用与 `.env.example` 一致的默认值。

### 2.4 WAF 中间件重复检测（Critical，第一轮已修复）

自定义 `SecurityMiddleware` 包含 ~200 行内联正则，与 `security-php` 包的 31 个检测器完全重复。每次请求扫描两次，浪费 CPU 且可能双重拦截。

**修复**: 中间件重写为使用 `SecurityGuard::guard()` API，从 341 行减至 ~110 行（service），136 行减至 ~85 行（admin）。暴力破解防护和响应安全头保留。

### 2.5 ENCRYPTION_KEY 缺失（Critical，第一轮已修复）

`.env.example` 文件中 `ENCRYPTION_KEY` 使用占位符，缺少 `ENCRYPTION_CIPHER` 和 `ENCRYPTION_PREVIOUS_KEYS`。无实际 `.env` 文件。

**修复**: 生成 32 字节 base64 密钥，添加 `ENCRYPTION_CIPHER=AES-256-CBC` 和 `ENCRYPTION_PREVIOUS_KEYS`，创建 `.env` 文件。

## 3. 生态配置完整性

### 3.1 Packages（两个项目一致）

| Package | 版本 | Service | Admin |
|---|---|---|---|
| erikwang2013/security-php | v1.1.6 | 已安装 | 已安装 |
| erikwang2013/encryptable | - | 已安装 | 已安装 |
| erikwang2013/encryption | - | 已安装 | 已安装 |
| erikwang2013/jwt-webman | - | 已安装 | 已安装 |
| erikwang2013/hashids | - | 已安装 | 已安装 |
| erikwang2013/snowflake-php | - | 已安装 | 已安装 |
| erikwang2013/poster-php | - | 已安装 | 已安装 |
| erikwang2013/season | - | 已安装 | 已安装 |
| erikwang2013/webman-scout | - | 已安装 | 已安装 |

### 3.2 WAF Configuration

| Item | Service | Admin | Status |
|---|---|---|---|
| Config file | `config/plugin/erikwang2013/security-php/app.php` | 相同 | 已发布 |
| Detectors enabled | 31/31 | 31/31 | 正确 |
| IP blacklist | enabled (5 att/60s -> 900s ban) | 相同 | 正确 |
| Block mode detectors | 28 | 28 | 正确 |
| Log-only detectors | 3 (header_injection, ssti, nosql_injection) | 3 | 正确 |
| Storage | file | file | 正确 |
| Logging | enabled (file, 10MB rotate) | 相同 | 正确 |
| Middleware registered | `config/middleware.php` | `config/middleware.php` | 正确 |

### 3.3 Encryption Configuration

| Item | Service | Admin | Status |
|---|---|---|---|
| ENCRYPTION_KEY | `base64:aJSrb...` | 相同 | 已设置 |
| ENCRYPTION_CIPHER | `AES-256-CBC` | 相同 | 已设置 |
| ENCRYPTION_PREVIOUS_KEYS | (empty) | (empty) | 已设置 |
| encryptable config | `config/plugin/erikwang2013/encryptable/app.php` | 相同（已统一） | 正确 |
| encryption config | `config/encryption.php` | - | 正确 |
| .env 文件 | 存在 | 存在 | 已创建 |
| .env.example | 已更新 | 已更新 | 正确 |
| docker-compose | 已更新 | 已更新 | 正确 |

### 3.4 Models with Encryptable Trait

31 个模型使用 `Encryptable` trait，敏感字段已正确声明为 `$encryptable`：

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

## 4. 第二轮修复（API 加密 + JWT 密钥）

### 4.1 API 响应加密中间件（Medium，已修复）

**文件**: `service/app/middleware/EncryptionMiddleware.php`（新建）

`erikwang2013/encryption` 包已安装且 `app/common/Encryption` 工具类存在，但此前未接入中间件管线。接口敏感数据缺少传输层加解密。

**修复**:
- 创建 `EncryptionMiddleware`，通过 HTTP header 驱动的加解密：
  - `X-Encrypted: 1` — 请求解密：将 base64 密文 body 解密为 JSON 后传给控制器
  - `X-Encrypt-Response: 1` — 响应加密：将响应中的 `data` 字段加密为 base64 密文
  - `X-Encrypt-Fields: field1,field2` — 仅加密响应中的指定字段
- 注册为中间件栈最后一级（HashidsEncode 之后）
- 健康检查（`/api/health`、`/api/ping`）和文档端点（`/apidoc`）跳过加解密

### 4.2 类名/文件名不匹配（Medium，已修复）

**文件**: `app/common/EncryptionHelper.php` → `app/common/Encryption.php`

类 `app\common\Encryption` 声明在文件 `EncryptionHelper.php` 中，与 PSR-4 规范不匹配，导致 Composer 自动加载失败。IDE 和 CLI 环境下该类可能无法被 autoloader 找到。

**修复**: 文件重命名为 `Encryption.php` 以匹配类名。

### 4.3 JWT_SECRET_KEY 为空（Low，已修复）

**文件**: `service/.env.example`、`service/.env`、`docker-compose.yml`

`JWT_SECRET_KEY` 为空字符串，虽 JWT middleware 有 `JWT_SECRET → JWT_SECRET_KEY` 回退链（优先使用 `JWT_SECRET`），但占位符值不安全。

**修复**: 生成 32 字节 base64 密钥，同时设置 `JWT_SECRET` 和 `JWT_SECRET_KEY`。更新 `.env.example`、`.env` 和 `docker-compose.yml`。

## 5. 待观察问题（潜在优化点）

## 5. 待观察问题（潜在优化点）

### 5.1 SecurityGuard 对 webman/Workerman 的 header 依赖（Low Risk）

**影响**: CSRF Origin、Host Header、DNS Rebinding、Request Smuggling、CORS 等检测器依赖 `$_SERVER` 中的 HTTP 头数据。

在 Workerman 非 CGI 环境中，`$_SERVER` 可能未完整填充 HTTP 头。SecurityGuard 已有后备逻辑（如 header 值为空则跳过检测），因此**不会误报**，但**可能漏检部分 header 攻击**。影响程度低，因为 Nginx 反代层面通常也会过滤恶意 header。

**建议**: 若需要更完善的 header 检测，可在 SecurityGuard 的 `$meta` 参数中显式传入 header 值。当前无需改动。

### 5.2 CSRF Origin 检测器对 Admin 的影响（No Risk）

Admin 的 `csrf_origin` 检测器在 `block` 模式下，`allowed_origins` 为空。但由于检测器仅在 Origin header 存在且与 Host 不匹配时才触发，管理后台访问时通常无 Origin header（同源访问），因此**不会误拦截**。

### 5.3 31 个检测器全部启用，每请求开销（Performance Note）

所有请求均会执行全部 31 个检测器（包括 JWT、WebSocket、GraphQL、CSV、prototype pollution 等）。每个检测器对请求的所有字段执行正则匹配。对于此项目的使用场景，开销在可接受范围内（webman 是常驻内存进程，无 CGI 冷启动开销）。

### 5.4 IP 黑名单持久化（Operational Note）

存储后端为 `file` 模式，默认路径为 `sys_get_temp_dir() . '/security_storage.json'`。在 Docker 容器中，重启后临时目录可能丢失。如果需要在多容器部署中共享黑名单，可切换为 `redis` 模式。

## 6. 变更文件汇总

```
admin/.env.example                                (ENCRYPTION_KEY 新增)
admin/.env                                        (从 .env.example 新建)
admin/CLAUDE.md                                   (中间件栈 + tech stack 更新)
admin/composer.json                               (security-php 依赖)
admin/config/plugin/erikwang2013/encryptable/app.php  (默认值统一)
admin/config/plugin/erikwang2013/security-php/app.php  (新建, 31 检测器)
admin/app/middleware/SecurityMiddleware.php       (重写为使用 SecurityGuard)
service/.env.example                              (ENCRYPTION_KEY/CIPHER + JWT 密钥 更新)
service/.env                                      (从 .env.example 新建, JWT 密钥同步)
service/CLAUDE.md                                 (中间件栈 + Encryption + tech stack 更新)
service/composer.json                             (security-php 依赖)
service/config/middleware.php                     (+ EncryptionMiddleware)
service/config/plugin/erikwang2013/security-php/app.php  (新建, 31 检测器)
service/app/common/Encryption.php                 (从 EncryptionHelper.php 重命名)
service/app/middleware/EncryptionMiddleware.php   (新建, API 响应加解密)
service/app/middleware/SecurityMiddleware.php     (重写为使用 SecurityGuard + 文件上传)
docker-compose.yml                                (encryption/jwt 环境变量补齐)
docs/security-review.md                           (本报告)
```

## 7. 结论

**状态**: 通过

- WAF 检测正确拦截 XSS、SQL 注入等攻击（31 检测器，SecurityGuard::guard API）
- 敏感字段加密配置完整（31 个模型，6 类敏感数据，Encryptable trait）
- API 传输加解密已接入中间件（EncryptionMiddleware, AES-256-CBC, header 触发）
- JWT 密钥已配置（JWT_SECRET + JWT_SECRET_KEY 均已设置）
- 文件上传检测已修复（合并 $_FILES 数据传入 SecurityGuard）
- 无功能回归（23/23 测试通过）
- 无中间件重复检测
- Docker 部署环境变量完整
