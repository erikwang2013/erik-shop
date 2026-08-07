# 跨境电商平台 — 安装指南

> Cross-border E-Commerce Platform Installation Guide
>
> [中文 README](README.md) | [English README](README-EN.md) | [审查报告](AUDIT-REPORT.md)

---

## 环境要求 / Requirements

| 组件 | 最低版本 | 推荐版本 |
|------|----------|----------|
| PHP | 8.1+ | 8.3 |
| MySQL | 5.7+ | 8.0 |
| Redis | 6.0+ | 7.x |
| Composer | 2.x | 2.x |
| Elasticsearch | 7.x | 8.x (可选/optional) |

### PHP 扩展

```
curl, json, mbstring, pdo_mysql, redis, fileinfo, bcmath, gd, openssl, zip
```

---

## 安装方式 / Installation Methods

### 方式一（推荐）：Web 一键安装向导

通过浏览器访问安装页面，填入数据库信息和管理员账号，**全自动完成建表、配置、管理员创建**。

```bash
# 1. 安装依赖
cd admin/
composer install

# 2. 启动管理后台
php start.php start

# 3. 浏览器访问（首次自动跳转安装页）
# http://127.0.0.1:8788/app/admin/install/step1
```

安装向导会**自动完成**：
- 创建 MySQL 数据库（如不存在）
- 导入 `install.sql` 全部 117 张表（7 张 `wa_` + 110 张 `erik_`）
- 导入管理后台菜单
- 生成 `plugin/admin/config/database.php` 和 `thinkorm.php`
- 生成 `service/.env`（含随机生成的 JWT/Hashids/加密密钥）
- 创建超级管理员账号
- 发送 SIGUSR1 信号触发服务重载

> 安装完成后，还需启动 service/ API 服务（见下方步骤 5）。

---

### 方式二：手动安装 / Manual Installation

<details>
<summary>适用于命令行部署或已有数据库环境</summary>

### 1. 创建数据库

```sql
CREATE DATABASE IF NOT EXISTS `shop_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 2. 导入数据库

```bash
mysql -u root -p shop_db < install.sql
```

> `install.sql` 包含 **117 张表**及默认种子数据。

### 3. 配置 service/.env

```bash
cd service/
cp .env.example .env
# 编辑 .env 配置实际数据库/Redis/JWT等参数
```

**关键配置项：**

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=erik_shop
DB_USER=root
DB_PASS=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

JWT_SECRET=<随机32字节密钥>
HASHIDS_SALT=<随机盐值>
ENCRYPTION_KEY=<随机32字节密钥>
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

### 4. 配置 admin/

```bash
cd admin/
cp .env.example .env
# 编辑 .env，填入与 service 相同的数据库信息
```

### 5. 创建管理员账号

```sql
-- 密码需要通过 bcrypt 生成
INSERT INTO `wa_admins` (`username`, `nickname`, `password`, `status`)
VALUES ('admin', '超级管理员', '<bcrypt_hash>', 0);

INSERT INTO `wa_admin_roles` (`role_id`, `admin_id`) VALUES (1, 1);
```

</details>

### 方式三：Docker 部署 / Docker Deployment

```bash
# 1. 配置环境变量
export DB_PASS=your_db_password
export JWT_SECRET=$(openssl rand -hex 32)
export HASHIDS_SALT=$(openssl rand -hex 8)
export ENCRYPTION_KEY=$(openssl rand -hex 16)

# 2. 启动全部服务
docker-compose up -d

# 3. 运行 Web 安装向导
# http://localhost/app/admin/install/step1
```

Docker 服务：Nginx(:80) → service(:8787) + admin(:8788)，MySQL(:3306)，Redis(:6379)，ES(:9200)

---

### 启动服务 / Start Services

```bash
# 安装依赖（两个项目都需要）
cd service/ && composer install
cd admin/ && composer install

# 启动 API 服务
cd service/
php start.php start -d

# 启动管理后台
cd admin/
php start.php start -d
```

| 服务 | 默认端口 | 验证方式 |
|------|----------|----------|
| API | 8787 | `curl http://127.0.0.1:8787/api/health` |
| 管理后台 | 8788 | 浏览器访问 `http://127.0.0.1:8788/app/admin` |

### 导入种子数据（可选）/ Import Seed Data (Optional)

```bash
cd service/
php start.php seed:countries     # 国家/地区
php start.php seed:currencies    # 货币
php start.php seed:hs_codes      # HS Code 编码
php start.php seed:compliance    # 合规分类
```

---

## 目录结构 / Directory Structure

```
shop-php/
├── install.sql              # 合并后的完整安装 SQL
├── admin/                   # 管理后台 (webman-admin + LayUI)
│   ├── config/database.php  # 数据库配置
│   ├── plugin/admin/        # webman-admin 插件
│   └── start.php
├── service/                 # API 服务 (webman RESTful)
│   ├── config/              # 配置文件
│   ├── database/schema.sql  # 原始业务表 SQL (已被 install.sql 替代)
│   ├── database/seeders/    # 种子数据
│   └── start.php
```

---

## 数据库结构概览 / Database Schema Overview

| 模块 | 表前缀 | 表数量 | 说明 |
|------|--------|--------|------|
| 管理后台系统 | `wa_` | 7 | 管理员/角色/权限/配置/附件 |
| 用户与账户 | `erik_users_*` | 7 | 用户/地址/社交/KYC/收藏/会员 |
| 商品与分类 | `erik_product_*` | 16 | 商品/SKU/多语言/多币种/评价/合规/HS |
| 购物车与订单 | `erik_order_*` | 9 | 购物车/订单/支付/退款/退货/清关 |
| 国家/货币/物流 | `erik_shipping_*` | 11 | 国家/货币/汇率/物流/分区/仓库/库存 |
| 海关与税务 | `erik_hs_*` | 5 | HS编码/关税/VAT/合规限制 |
| 支付与资金 | `erik_payment_*` | 6 | 支付网关/平台分账/供应商结算/汇率损益 |
| 营销 | `erik_coupon_*` | 9 | 优惠券/秒杀/拼团/分销 |
| 供应链 | `erik_supplier_*` | 7 | 供应商/采购/质检 |
| 风控与合规 | `erik_risk_*` | 6 | 风控规则/GDPR/Cookie/隐私 |
| 多平台 | `erik_platform_*` | 8 | 多店铺/平台账号/刊登/卖家 |
| 内容与体验 | `erik_*` | 12 | CMS/Feed/尺码/通知/邮件/搜索/操作日志 |
| 订阅/积分等 | `erik_*` | 7 | 订阅/积分/礼品卡/B2B |
| AB测试/API/设置 | `erik_*` | 7 | AB测试/限流/API文档/系统配置 |

---

## 常见问题 / Troubleshooting

### MySQL 报错 "Specified key was too long"

```sql
-- 确保使用 utf8mb4 + InnoDB 并且启用了 innodb_large_prefix
SET GLOBAL innodb_large_prefix = ON;
SET GLOBAL innodb_file_format = Barracuda;
SET GLOBAL innodb_file_per_table = ON;
```

### 端口冲突 / Port Conflict

修改 `admin/.env` 或 `service/.env` 中的 `APP_PORT`。

### Redis 连接失败

检查 Redis 扩展已安装且 Redis 服务已启动：
```bash
redis-cli ping  # 应返回 PONG
```

### Snowflake ID 冲突

如果多台服务器同时实例化，确保每台服务器的 `SNOWFLAKE_WORKER_ID` 不同（0-31）。

---

## 开发命令速查 / Development Commands

```bash
# service/ (API)
php start.php start          # 启动
php start.php start -d       # 守护进程
php start.php reload         # 热重载
php start.php stop           # 停止
php start.php status         # 状态

# admin/ (管理后台)
php start.php start
php start.php start -d
php start.php reload
```
