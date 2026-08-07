# Erik Shop — 跨境电商平台
基于 webman 全家桶构建的全栈跨境电商平台，覆盖 B2C/B2B 场景和第三方卖家入驻。

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 版本概览

| | 简化版 (Lite) | 标准版 (Standard) | 完整版 (Full) |
|---|:---:|:---:|:---:|
| **定位** | 个人开发者 / 小型电商 | 成长型跨境商家 | 企业级全栈平台 |
| **许可证** | MIT 开源 | 商业授权 | 商业授权 |
| **获取方式** | GitHub 公开下载 | 联系 erik@erik.xyz | 联系 erik@erik.xyz |
| **分支** | `lite` | `standard` | `full` |
| **当前** | — | — | ✅ |

---

## 2026-08-07 修复记录

| # | 问题 | 严重度 | 修复 |
|---|------|--------|------|
| 1 | API 响应加密未接入中间件 | Medium | 新建 EncryptionMiddleware（X-Encrypt-Response header 驱动），注册为 service 管线第 10 级 |
| 2 | 类名 Encryption / 文件名 EncryptionHelper.php 不匹配 | Medium | 重命名为 Encryption.php，修复 PSR-4 自动加载 |
| 3 | JWT_SECRET_KEY 为空 | Low | 生成 32 字节密钥，同时设置 JWT_SECRET 和 JWT_SECRET_KEY |
| 4 | config/middleware.php 为索引数组导致 "Bad middleware config" 全 worker 崩溃 | Critical | 改为 `'' => [...]` 标准结构（webman 要求 appName => 列表） |
| 5 | security-php 插件配置缺 enable 键被 Config::loadFromDir 静默跳过 | Critical | service/admin 的插件 app.php 补 `'enable' => true` |
| 6 | config/bootstrap.php 引用不存在的 support\bootstrap\Db/Redis | Critical | 移除；Eloquent 初始化改由 support/bootstrap.php require vendor/webman/database 的 Db.php |
| 7 | 全局 redis() 函数不存在（webman 2.x 无此函数），限流/风控静默失效 | High | 新建 support\Redis 门面（illuminate/redis + phpredis），app/functions.php 注册 redis() 辅助函数 |
| 8 | RedisManager 构造参数缺失（需 3 参：app容器/driver/config） | High | 传 stdClass 容器占位 + phpredis 驱动 + 连接配置 |
| 9 | 模型引用 Erik\Encryptable\Encryptable trait 不存在（包内是 Maize\Encryptable 命名空间的 CastsAttributes） | Critical | 新建 service/Erik/Encryptable/Encryptable.php 经典 trait 兼容层（底层复用包的 Encryption::php） |
| 10 | composer 插件 Installer.php 顶层函数重复声明 fatal | Medium | function_exists 幂等守卫（service/admin 两个 vendor 均已修复） |
| 11 | HashidsEncode getHeader() 返回 string 导致 implode 报错 | High | (array) 强制转换 |
| 12 | docker-compose/.env.example 硬编码真实 JWT/加密密钥 | Critical | 替换为 change_me 占位符，安装向导生成随机密钥 |
| 13 | 订单创建无事务、库存扣减非原子（并发超卖） | Critical | Db::transaction + 条件 decrement 原子扣减 |
| 14 | 优惠券领取并发超发/超领 | High | 事务 + 行锁 lockForUpdate + received_qty 原子门闩 |
| 15 | PayPal Webhook 验签字段恒为空（verify-webhook-signature 必失败） | High | 五个验签字段从请求 header 透传 |
| 16 | 安装向导 SQL 注入（数据库名/密码拼接） | High | quote + 反引号转义 + var_export 写配置 |
| 17 | 加密/哈希密钥缺失时静默降级 | High | Encryption/HashidsHelper 空值或长度非法抛异常 |
| 18 | 订单导出固定文件名并发覆盖 | Medium | uniqid 文件名 + shutdown 清理 + try/catch |
| 19 | Hashids 解码不写回请求参数（路由参数/GET/POST） | High | setParams/setGet/setPost 写回 |
| 20 | composer.lock 被 gitignore（构建不可复现） | Medium | 移除忽略纳入版本控制 |
| 21 | 容器无健康检查、无启动依赖 | Medium | 全服务 healthcheck + depends_on condition |
| 22 | admin Dockerfile 不可运行 | High | 补 COPY + composer install + EXPOSE + CMD |
| 23 | Flutter 编译错误（intl 冲突/构造器泛型/多余括号）+ 测试 pending Timer | High | intl ^0.20.2、静态工厂、pump 推进时钟 |
| 24 | HarmonyOS 27 个 ArkTS 编译错误无法出包 | High | 显式接口、保留字改名、单根 build、@kit 导入、hvigor 配置 |

---

## 功能对比

### 用户系统

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 邮箱注册/登录 (JWT) | ✅ | ✅ | ✅ |
| 社交登录 (Google/Apple/Facebook) | — | ✅ | ✅ |
| 地址管理 | ✅ | ✅ | ✅ |
| 会员等级 + 积分 | — | — | ✅ |
| 礼品卡 | — | — | ✅ |
| KYC实名认证 | — | — | ✅ |

### 商品系统

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 分类管理 (树形) | ✅ | ✅ | ✅ |
| SKU + 属性 | ✅ | ✅ | ✅ |
| 商品图片 | ✅ | ✅ | ✅ |
| 多语言内容 | — | ✅ | ✅ |
| 多币种独立定价 | — | ✅ | ✅ |
| 商品评价 | ✅ | ✅ | ✅ |
| 合规标签 (FDA/CE/RoHS) | — | ✅ | ✅ |
| ES多语言搜索 | — | ✅ | ✅ |
| 商品Feed同步 (Google/Meta) | — | — | ✅ |
| 尺码对照表 | — | — | ✅ |

### 交易系统

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 购物车 | ✅ | ✅ | ✅ |
| 订单管理 | ✅ | ✅ | ✅ |
| 支付 (Stripe) | ✅ | ✅ | ✅ |
| 支付 (PayPal/Klarna/Adyen) | — | ✅ | ✅ |
| BNPL先买后付 | — | ✅ | ✅ |
| 退款 | ✅ | ✅ | ✅ |
| 退货管理 | — | ✅ | ✅ |
| 商业发票/装箱单 | — | ✅ | ✅ |
| 物流保险 | — | — | ✅ |

### 跨境物流

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 国际物流商管理 | — | ✅ | ✅ |
| 物流分区 + 阶梯费率 | — | ✅ | ✅ |
| 海外仓 (发货+退货) | — | ✅ | ✅ |
| HS申报 | — | ✅ | ✅ |
| 物流轨迹追踪 | — | ✅ | ✅ |
| 多仓库库存管理 | — | — | ✅ |

### 海关税务

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| HS Code编码库 | — | ✅ | ✅ |
| 关税规则配置 | — | ✅ | ✅ |
| VAT/IOSS设置 | — | ✅ | ✅ |
| 各国合规限制 | — | ✅ | ✅ |
| 价格展示合规 (含/不含税) | — | ✅ | ✅ |

### 营销工具

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 优惠券 | ✅ | ✅ | ✅ |
| 轮播图 | ✅ | ✅ | ✅ |
| 秒杀 | — | ✅ | ✅ |
| 拼团 | — | ✅ | ✅ |
| 分销 (链接+佣金+提现) | — | ✅ | ✅ |
| 区域促销 | — | ✅ | ✅ |

### 供应链

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 供应商管理 | — | — | ✅ |
| 采购单 | — | — | ✅ |
| 质检 (入库+出库门禁) | — | — | ✅ |
| 库存流水 (不可变账本) | — | — | ✅ |
| 库存调拨 | — | — | ✅ |

### 平台扩展

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 多店铺管理 | — | — | ✅ |
| 多商家入驻 (第三方卖家) | — | — | ✅ |
| Amazon/eBay/Shopee刊登 | — | — | ✅ |
| 多平台订单聚合 | — | — | ✅ |
| B2B批发 (阶梯定价/询价) | — | — | ✅ |

### 风控合规

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 基础攻击检测 (XSS/SQLi) | ✅ | ✅ | ✅ |
| 扩展攻击检测 (XXE/SSRF等) | — | — | ✅ |
| PosterVerify人机验证 | — | ✅ | ✅ |
| 风控规则引擎 | — | — | ✅ |
| GDPR/CCPA数据请求 | — | — | ✅ |
| Cookie Consent管理 | — | — | ✅ |
| 平台来源追踪 | — | ✅ | ✅ |
| 平台来源追踪 (8平台) | — | ✅ | ✅ |

### 高并发

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| OPCache | ✅ | ✅ | ✅ |
| DB连接池 | ✅ | ✅ | ✅ |
| 令牌桶限流 | — | — | ✅ |
| Cache-Aside缓存 | — | — | ✅ |
| 熔断器 | — | — | ✅ |
| DB读写分离 | — | — | ✅ |
| 异步队列 | — | — | ✅ |

### 内容与增长

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| 系统通知 | ✅ | ✅ | ✅ |
| 邮件模板 | — | — | ✅ |
| CMS多语言页面 | — | — | ✅ |
| FAQ + 知识库 | — | — | ✅ |
| 订阅周期购 | — | — | ✅ |
| AB测试 | — | — | ✅ |
| 实时客服 (WebSocket IM) | — | — | ✅ |

### 客户端

| 功能 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Flutter (iOS/Android) | ✅ | ✅ | ✅ |
| Flutter (macOS/Windows/Linux) | ✅ | ✅ | ✅ |
| Flutter iPadOS | ✅ | ✅ | ✅ |
| 国际化 (5语言翻译) | ✅ | ✅ | ✅ |
| API文档 (hg/apidoc) | ✅ | ✅ | ✅ |
| HarmonyOS (ArkTS) | — | — | ✅ |
| Web Admin | ✅ | ✅ | ✅ |
| Admin ECharts仪表盘 | ✅ | ✅ | ✅ |
| Admin Excel/PDF导出 | ✅ | ✅ | ✅ |
| 多语言界面 (5语言) | ✅ | ✅ | ✅ |

---

## 设计对比

### 数据库

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| 数据表 | **23** | **62** | **110** |
| 用户相关 | 3 | 5 | 7 |
| 商品相关 | 6 | 15 | 19 |
| 交易相关 | 6 | 9 | 9 |
| 物流相关 | 0 | 7 | 9 |
| 海关相关 | 0 | 5 | 5 |
| 营销相关 | 4 | 8 | 8 |
| 供应链 | 0 | 0 | 5 |
| 风控合规 | 0 | 0 | 5 |
| 多平台 | 0 | 0 | 9 |
| 内容增长 | 0 | 1 | 14 |
| 客服/AB/API | 0 | 0 | 5 |

### 中间件管道

```
Lite:      Cors → Security(4类) → Locale → HashidsDecode
          → VersionRoute → (JwtAuth) → HashidsEncode

Standard:  Cors → Security(4类) → Platform → GeoIp → Locale
          → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode

Full:       Cors → Security(31类) → RateLimit(令牌桶) → Platform → GeoIp
          → Locale → HashidsDecode → VersionRoute
          → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption(接口加密)
```

### 代码规模

| | Lite | Standard | Full |
|---|:---:|:---:|:---:|
| Service 模型 | 26 | 55 | 112 |
| Service 控制器 | 15 | 24 | 37 |
| Service 中间件 | 7 | 9+2 | 11+2 |
| Service 工具类 | 5 | 5 | 8 |
| Admin 模型 | 15 | 34 | 65 |
| Admin 控制器 | 15 | 27 | 67 |
| Flutter 页面 | 12 | 12 | 12 |
| HarmonyOS | — | — | 8页面 |
| PHPUnit测试 | 23 | 23 | 23 |

### 技术栈

| 组件 | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| snowflake-php | ✅ | ✅ | ✅ |
| hashids | ✅ | ✅ | ✅ |
| jwt-webman | ✅ | ✅ | ✅ |
| encryption | ✅ | ✅ | ✅ |
| encryptable | ✅ | ✅ | ✅ |
| season | — | ✅ | ✅ |
| poster-php | — | ✅ | ✅ |
| webman-scout | — | ✅ | ✅ |
| phpspreadsheet | ✅ | ✅ | ✅ |
| dompdf | ✅ | ✅ | ✅ |
| stripe/stripe-php | — | ✅ | ✅ |
| maxmind/GeoIP2 | — | ✅ | ✅ |
| guzzlehttp/guzzle | — | ✅ | ✅ |

---

## 升级路径

```
Lite (开源) ──→ Standard (商业) ──→ Full (商业)

升级方式:
  1. 联系 erik@erik.xyz 获取对应版本代码
  2. 导入增量 schema (lite→standard 增加~40表, standard→Full 增加~48表)
  3. 复制对应版本控制器/模型/中间件
  4. composer require 新增依赖包
```

---

## 获取方式

| 版本 | 方式 |
|------|------|
| **简化版 (Lite)** | GitHub 开源 [github.com/erikwang2013/shop-php](https://github.com/erikwang2013/shop-php) `lite` 分支 |
| **标准版 (Standard)** | 商业授权 — 联系 **erik@erik.xyz** |
| **完整版 (Full)** | 商业授权 — 联系 **erik@erik.xyz** |

商业授权包含: 完整源代码 / 部署支持 / 优先更新 / 技术咨询
