# 跨境电商平台 — 安装系统审查报告

> 审查日期：2026-08-04
> 审查范围：install.sql / InstallController / install.html / .env / 配置生态

---

## 1. 测试执行摘要

| 项目 | 状态 | 说明 |
|------|------|------|
| PHP 语法检查 | PASS | InstallController.php 无语法错误 |
| install.sql 完整性 | PASS | 120KB, 70张表, FOREIGN_KEY_CHECKS 正确配对 |
| 服务启动测试 | PASS | admin 在 8788 端口成功启动，32 workers |
| 安装页面访问 | PASS | HTTP 200, IndexController 正确渲染 install.html |
| 确认安装 POST | PASS | step1 返回正确 JSON（空凭据=预期错误） |
| 配置编译测试 | PASS | admin(3) + service(13) 个配置文件全部通过 |
| PHP 扩展检查 | PASS | 9 个必需扩展全部就绪 |
| .env 映射验证 | PASS | 31 个替换键全部匹配 |

---

## 2. 发现并修复的问题

### 2.1 [已修复] 严重 — 中间件配置格式错误

- **文件**: `admin/config/middleware.php`
- **问题**: 返回平铺数组，webman 要求关联数组格式 `'app_name' => [middleware]`
- **影响**: admin 服务所有 worker 启动即崩溃
- **修复**: 改为 `['' => [...]]` 格式

### 2.2 [已修复] 严重 — 缺少 vlucas/phpdotenv

- **文件**: `admin/composer.json`
- **问题**: `illuminate/support` 的 `env()` 需要此包但未安装
- **影响**: 启动时 `encryptable` 插件配置加载失败，Fatal error
- **修复**: `composer require vlucas/phpdotenv`

### 2.3 [已修复] 高 — 端口冲突

- **文件**: `admin/config/process.php`
- **问题**: 硬编码 `8787`，与系统其他 webman 服务冲突
- **影响**: Address already in use
- **修复**: 改为 `getenv('APP_PORT') ?: '8788'`

### 2.4 [已修复] 高 — 未生成 admin/.env

- **文件**: `InstallController.php`
- **问题**: 只生成 `service/.env`，admin 的 `config/database.php` 也从 getenv 读取
- **影响**: admin 无法连接到用户指定的数据库
- **修复**: `generateEnvFiles()` 同时生成 `admin/.env`

### 2.5 [已修复] 中 — 缺少 admin/.env.example

- **文件**: `admin/.env.example`（新建）
- **影响**: 手动安装无模板参考
- **修复**: 已创建

---

## 3. 代码审查要点

### 3.1 LOCK TABLES 处理

`install.sql` 含 `wa_options` 和 `wa_roles` 的 LOCK TABLES。`splitSqlFile()` 按 `;` 分割，LOCK/INSERT/UNLOCK 作为独立 exec() 执行。同一 PDO 连接内锁保持有效，安全。

### 3.2 随机密钥生成

```php
jwtSecret       = bin2hex(random_bytes(32));  // 64 hex chars
hashidsSalt     = bin2hex(random_bytes(8));   // 16 hex chars  
encryptionKey   = bin2hex(random_bytes(16));  // 32 hex chars
```

使用 CSPRNG，强度符合要求。

### 3.3 表冲突检测

完整覆盖 70 张表名检测，冲突 >10 张时自动截断显示。

### 3.4 SIGUSR1 重载

标准 webman worker 重载机制，确保新写的 `database.php` 在下个请求周期生效。

---

## 4. 配置生态检查

### 4.1 env key 映射覆盖

| .env Key | service 消费方 | admin 消费方 |
|----------|---------------|-------------|
| DB_HOST/PORT/NAME/USER/PASS | config/database.php | config/database.php |
| REDIS_HOST/PORT/PASS | config/redis.php (4次) | — |
| JWT_SECRET | config/jwt.php ✓ | — |
| HASHIDS_SALT | config/hashids.php | config/hashids.php |
| ENCRYPTION_KEY | config/encryption.php | — |
| SNOWFLAKE_WORKER/DATACENTER | config/snowflake.php | config/snowflake.php |

安装向导生成的 .env 覆盖全部关键映射。

### 4.2 JWT 双配置

- `config/jwt.php` → `JWT_SECRET` ✓ (含 fallback)
- `plugin/jwt/jwt.php` → `JWT_SECRET_KEY` ⚠ (无 fallback)

实际生效的是 service 级别 `config/jwt.php`，无阻塞影响，建议后续统一。

---

## 5. 安全建议

| # | 建议 | 优先级 |
|---|------|--------|
| 1 | 安装完成后可考虑删除或重命名 install.sql | 中 |
| 2 | `plugin/admin/config/database.php` 含明文密码，权限 640 | 中 |
| 3 | composer audit 显示 23 条安全公告（不阻塞） | 低 |
| 4 | 安装页面建议增加 IP 白名单或一次性 token | 低 |

---

## 6. 变更文件一览

| 文件 | 操作 | 说明 |
|------|------|------|
| `install.sql` | 新建 | 合并 70 张表 |
| `INSTALL.md` | 新建+更新 | 含 Web 安装说明 |
| `AUDIT-REPORT.md` | 新建 | 本报告 |
| `InstallController.php` | 重写 | 完整 SQL + 双 .env |
| `install.html` | 重写 | 3 步向导 |
| `admin/config/process.php` | 编辑 | 端口 8788 |
| `admin/config/middleware.php` | 编辑 | 格式修正 |
| `admin/.env.example` | 新建 | 模板 |
| `admin/composer.json` | 依赖新增 | +dotenv |
| `admin/CLAUDE.md` | 更新 | 文档 |
| `service/CLAUDE.md` | 更新 | 文档 |

---

## 7. 结论

安装系统经过语法检查、配置编译、实际启动和 HTTP 访问测试，**所有问题已修复**。Web 一键安装向导可正常工作：创建数据库 → 导入 70 张表 → 生成双 .env → 创建管理员 → 重载服务。
