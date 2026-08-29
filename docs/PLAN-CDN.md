# CDN 接入方案

> **生成时间**：2026-08
> **生成方式**：架构师 + 调研（多代理协作）
> **依据**：代码勘察（UploadController/Crud/ProductController 等）、`docs/PLAN.md` 既有风格
> **适用周期**：1 个月（4 个阶段）

## 一、现状分析

| 项 | 现状 | 出处 |
|---|---|---|
| 上传入口 | admin 唯一上传入口 `admin/plugin/admin/app/controller/UploadController.php`；service 侧无上传代码 | `insert()` L97 / `file()` L135 / `image()` L164（Intervention 缩放 max 1170）/ `avatar()` L203 / `base()` L291（落盘 `plugin/admin/public/upload/img|files/YYYYMMDD/`，10MB，扩展名白名单，文件名=时间戳+随机 hex） |
| URL 存储 | DB 存相对路径：`/app/admin/upload/img/...`、`/documents/...`（`shop_products.main_image`、`shop_product_images.url`、`shop_banners.image`、`shop_order_documents.file_path`、`wa_uploads.url`，`wa_uploads.storage` 默认 `'local'` 全仓无代码使用） | `install.sql`；`ProductController::show()` L96-133 原样返回 |
| 缓存现状 | 无任何 CDN/purge 代码；唯一缓存头 `service/app/middleware/SecurityMiddleware.php` L106-108（GET 60s/s-maxage 300） | 全仓 grep |
| 基础设施 | Redis 4 库（default/session/queue/rate_limit，前缀 `shop:`），已有队列（`Redis::send document_pdf`）、分布式锁、CircuitBreaker、Guzzle（admin/service 均已装 `guzzlehttp/guzzle ^7.0`） | `composer.json` ×2 |
| 配置体系 | `config/*.php` + `getenv()` 读 `.env`，35 个配置文件，`config/payment.php` 为多提供商配置范本 | `service/config/`、`service/.env.example` |
| 反向代理 | `docker/nginx/conf.d/shop.conf`：api.erik.xyz→8787、admin.erik.xyz→8788，无静态 location、无缓存头；`client_max_body_size 20m` | docker 目录 |
| 源站持久化 | **admin 上传目录无 docker volume（容器重启丢文件）**；service/public 为死挂载 | `docker-compose.yml` |

## 二、目标与约束

**目标**：接入 4 家 CDN（Cloudflare / AWS CloudFront / 阿里云 CDN / 腾讯云 CDN），统一 refresh/purge/preload 抽象，配置驱动切换默认提供商，管理端商品/图片增删改自动刷新，URL 输出改走 CDN 域名且旧地址兼容，凭据仅 env，可扩展预留 Fastly/Akamai。

**约束**：webman 惯例、PSR-12、Copyright 头部；单文件 <500 行；不引入不必要抽象；凭据仅 env/config；不引入新依赖（Guzzle 已装）。

## 三、总体架构

### 3.1 回源模型（Origin-Pull），输出时重写 URL

- 上传仍落 admin 源站本地磁盘；CDN 域名 CNAME 回源到 admin 域名（`/app/admin/upload/...` 路径与原站一致）。
- **DB 保持相对路径，零迁移**：旧地址、新地址、内部管理后台共用同一路径语义；CDN 启用时仅在**输出边界**重写为 `https://{CDN_DOMAIN}{path}`。
- 文件名本身唯一（时间戳+随机 hex），回源响应给 `immutable` 缓存头，删除靠 purge 立即失效 + TTL 自然过期兜底。
- 不做「上传到 CDN 桶」方案：4 家各有 SDK/签名，工作量数倍于回源模型，且回源模型天然覆盖 4 家（Cloudflare CNAME 接入 / CloudFront Custom Origin / 阿里云 CDN 源站 / 腾讯云 CDN 源站）。

### 3.2 双项目职责划分（关键决策）

| 项目 | 职责 | 代码 |
|---|---|---|
| admin/ | 上传与内容管理方 → **完整 Cdn 门面 + 4 个提供商适配器 + purge 触发** | `admin/app/common/` |
| service/ | 只输出 URL → **仅 `Cdn::url()` 重写辅助**（无适配器、无出站请求） | `service/app/common/Cdn.php`（约 40 行） |

理由：purge 全部由 admin 触发（上传删除、商品/轮播图 CRUD）；service 永不 purge，塞入 4 个适配器是纯浪费。两项目独立 composer 包，各自持有一个 ~40 行的 `Cdn::url()` 是诚实的重复，不共享。

### 3.3 URL 流转

```
上传 → base() 落盘相对路径 /app/admin/upload/img/20260829/xxxx.jpg（DB 存相对）
     → 响应给前端 Cdn::url() → https://cdn.erik.xyz/app/admin/upload/img/...
CDN 边缘缓存 → 回源 admin.erik.xyz/app/admin/upload/...（nginx 加 immutable 缓存头）
商品删除/改图 → Cdn::purge([相对路径...]) → 适配器调厂商 API 失效
service API 输出 → Cdn::url() 重写 main_image/images/banner → 前端直连 CDN
```

## 四、接口定义

### 4.1 `app\common\CdnProviderInterface`（admin 项目，新建）

```php
namespace app\common;

interface CdnProviderInterface
{
    /** 按 URL 失效。入参为相对路径（自动补全为 https://{CDN_DOMAIN} 绝对 URL）或本站绝对 URL */
    public function purge(array $urls): void;

    /** 按标签失效。仅 Cloudflare 支持（Cache-Tag），其余实现必须抛 LogicException */
    public function purgeByTag(string $tag): void;

    /** 预热。仅阿里云/腾讯云支持，其余实现必须抛 LogicException */
    public function preload(array $urls): void;
}
```

### 4.2 `app\common\Cdn` 门面（admin 项目，新建）

```php
namespace app\common;

final class Cdn
{
    /** URL 重写：CDN 关闭/已是完整 URL/空值 → 原样返回；否则 https://{cdn.domain}/{path} */
    public static function url(string $path): string;

    /** 按配置实例化提供商；provider 未知或凭据缺失 → 抛 CdnException（报错信息指明应配置的 env 变量名，仿 PaymentGateway::make） */
    public static function make(?string $provider = null): CdnProviderInterface;

    /** 以下三个操作 fail-open：CDN 关闭时直接返回；提供商调用失败仅 Log::error，不抛异常（绝不让管理端 CRUD 因 CDN 挂掉而失败） */
    public static function purge(array $urls, ?string $provider = null): void;
    public static function purgeByTag(string $tag, ?string $provider = null): void;
    public static function preload(array $urls, ?string $provider = null): void;
}
```

`CdnException extends \RuntimeException`（定义于 `admin/app/common/Cdn.php` 同文件顶部，仿 `GatewayBusinessException` 惯例）。

### 4.3 service 侧 `app\common\Cdn`（service 项目，新建，仅 url()）

与 4.2 的 `url()` 逻辑完全一致（约 40 行），无 make/purge/preload。

## 五、提供商适配器清单

统一构造签名 `__construct(array $config, ?ClientInterface $http = null)`——`$http` 缺省为 `new Client(['timeout' => 8, 'allow_redirects' => false, 'verify' => true])`，测试注入 MockHandler 客户端。单文件均 <500 行，自带签名实现，不引入 AWS SDK。

| 适配器 | 文件 | 失效 API | 预热 | 按标签 | 签名方案 |
|---|---|---|---|---|---|
| Cloudflare | `admin/app/common/cdn/CloudflareProvider.php` | POST `https://api.cloudflare.com/client/v4/zones/{zone_id}/purge_cache`，body `{"purge_everything":false,"files":[...]}` / `{"tags":[...]}`，Header `Authorization: Bearer {api_token}` | 不支持（抛 LogicException） | **支持**（`tags`） | 无（API Token） |
| AWS CloudFront | `admin/app/common/cdn/CloudFrontProvider.php` | POST `https://cloudfront.amazonaws.com/2020-05-31/distribution/{id}/invalidation`，body `{"InvalidationBatch":{"Paths":{"Quantity":n,"Items":[...]},"CallerReference":"{unix_ts}-{rand}"}}`（路径须 `/` 开头） | 不支持 | 不支持 | 自实现最小 SigV4（约 70 行） |
| 阿里云 CDN | `admin/app/common/cdn/AliyunProvider.php` | GET `https://cdn.aliyuncs.com/?Action=RefreshObjectCaches&ObjectPath={urls逗号拼接}&ObjectType=File`（RPC 风格，参数排序+RFC3986 编码+HMAC-SHA1 签名）；预热 `Action=PushObjectCache` | **支持** | 不支持 | RPC 签名（HMAC-SHA1） |
| 腾讯云 CDN | `admin/app/common/cdn/TencentProvider.php` | POST `https://cdn.tencentcloudapi.com/`，body `{"Action":"PurgeUrlsCache","Version":"2018-06-06",...}`，TC3-HMAC-SHA256 签名头；`PurgePathCache`（按目录）与 `PushUrlsCache`（预热）同理 | **支持** | 不支持 | TC3-HMAC-SHA256（约 70 行） |
| Fastly / Akamai | 预留，不实现 | — | — | — | 仅接口已可容纳，config 无对应项 |

能力矩阵：`purge` 4/4；`preload` 2/4（阿里云、腾讯云）；`purgeByTag` 1/4（Cloudflare）。

## 六、配置设计

### 6.1 `admin/config/cdn.php`（新建，仿 `config/payment.php` 风格）

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * CDN 配置
 * 支持 Cloudflare / AWS CloudFront / 阿里云 CDN / 腾讯云 CDN（Fastly/Akamai 预留）
 * 凭据一律来自 .env，禁止硬编码
 */
return [
    // 总开关：false 时 Cdn::url() 原样返回、purge/preload 为空操作（行为零变化）
    'enabled' => (bool) (getenv('CDN_ENABLED') ?: false),

    // 默认提供商：cloudflare / cloudfront / aliyun / tencent
    'default' => getenv('CDN_DEFAULT_PROVIDER') ?: 'cloudflare',

    // 回源 CDN 域名（不含 scheme），URL 重写目标；留空则关闭重写
    'domain' => getenv('CDN_DOMAIN') ?: '',

    'providers' => [
        'cloudflare' => [
            'api_token' => getenv('CF_API_TOKEN') ?: '',
            'zone_id'   => getenv('CF_ZONE_ID') ?: '',
        ],
        'cloudfront' => [
            'key_id'          => getenv('AWS_ACCESS_KEY_ID') ?: '',
            'secret_key'      => getenv('AWS_SECRET_ACCESS_KEY') ?: '',
            'distribution_id' => getenv('CLOUDFRONT_DISTRIBUTION_ID') ?: '',
            'region'          => getenv('AWS_REGION') ?: 'us-east-1',
        ],
        'aliyun' => [
            'access_key_id'     => getenv('ALIYUN_ACCESS_KEY_ID') ?: '',
            'access_key_secret' => getenv('ALIYUN_ACCESS_KEY_SECRET') ?: '',
        ],
        'tencent' => [
            'secret_id'  => getenv('TENCENT_SECRET_ID') ?: '',
            'secret_key' => getenv('TENCENT_SECRET_KEY') ?: '',
        ],
    ],
];
```

### 6.2 `service/config/cdn.php`（新建，仅重写所需子集）

```php
return [
    'enabled' => (bool) (getenv('CDN_ENABLED') ?: false),
    'domain'  => getenv('CDN_DOMAIN') ?: '',
];
```

### 6.3 `.env` 追加（admin/.env.example 与 service/.env.example 均需）

```
# ===== CDN =====
CDN_ENABLED=false
CDN_DEFAULT_PROVIDER=cloudflare
CDN_DOMAIN=                # 例如 cdn.erik.xyz（CNAME 回源 admin 域名）
CF_API_TOKEN=
CF_ZONE_ID=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_REGION=us-east-1
CLOUDFRONT_DISTRIBUTION_ID=
ALIYUN_ACCESS_KEY_ID=
ALIYUN_ACCESS_KEY_SECRET=
TENCENT_SECRET_ID=
TENCENT_SECRET_KEY=
```

## 七、集成点改动清单

### 新建文件

| 文件 | 说明 |
|---|---|
| `admin/config/cdn.php` | 见 6.1 |
| `admin/app/common/Cdn.php` | 门面 + CdnException + url() 重写（约 120 行） |
| `admin/app/common/CdnProviderInterface.php` | 见 4.1 |
| `admin/app/common/cdn/CloudflareProvider.php` | 约 80 行 |
| `admin/app/common/cdn/CloudFrontProvider.php` | 含最小 SigV4，约 180 行 |
| `admin/app/common/cdn/AliyunProvider.php` | 含 RPC 签名，约 140 行 |
| `admin/app/common/cdn/TencentProvider.php` | 含 TC3 签名，约 160 行 |
| `service/config/cdn.php` | 见 6.2 |
| `service/app/common/Cdn.php` | 仅 url()（约 40 行） |
| `admin/tests/CdnTest.php` | 见九、测试计划 |
| `service/tests/CdnUrlTest.php` | url() 重写断言 |
| `admin/scripts/cdn_preload.php` | 预热 CLI（从参数/文件读 URL 列表调 Cdn::preload），不建后台 UI |

### 修改文件（admin）

| 文件 | 改动 |
|---|---|
| `admin/plugin/admin/app/controller/UploadController.php` | ① `insert()` L122、`file()` L151、`image()` L190、`avatar()` L246 四处响应 `'url' => Cdn::url($data['url'])`（DB 仍存相对路径，仅响应改写）；② `delete()` L259：`parent::delete` + unlink 后追加 `Cdn::purge($file_list)`（$file_list 即已计算的 `/app/admin/...` URL 列表） |
| `admin/plugin/admin/app/controller/shop/ShopProductController.php` | 覆写 `update()`：先取旧 `main_image` → `parent::update` → `Cdn::purge([旧, 新])`（去重过滤）；覆写 `delete()`：先 `pluck('main_image')` → `parent::delete` → `Cdn::purge`（实现见第 2 节 coder 指令） |
| `admin/plugin/admin/app/controller/shop/ShopBannerController.php` | 同 ShopProductController 模式，字段 `image` |
| `admin/.env.example` | 追加 6.3 段 |
| `docker-compose.yml` | 为 admin 容器 `plugin/admin/public` 挂持久化 volume（**修复源站重启丢文件**）；service 容器 `public` 同理 |
| `docker/nginx/conf.d/shop.conf` | admin server 块内新增 `location /app/admin/upload/ { expires 7d; add_header Cache-Control "public, max-age=604800, immutable"; }`（唯一文件名 → immutable 正确，删除靠 purge 即时失效，purge 失败 7 天自然过期兜底） |

### 修改文件（service）

| 文件 | 改动 |
|---|---|
| `service/.env.example` | 追加 6.3 段 |
| `service/app/controller/v1/ProductController.php` | `index()` 与 `show()`（L96-133）输出前 `main_image` 与 `images[].url` 经 `Cdn::url()` 重写 |
| `service/app/controller/v1/CategoryController.php` | L34 `'image' => Cdn::url($c->image)`（icon 同） |
| `service/app/controller/v1/BannerController.php` | 返回集合前对 `image` 字段批量 `Cdn::url()` |
| `service/app/controller/v1/CartController.php` | L37 `'image' => ...` 重写 |

## 八、安全设计

- **凭据**：只经 `getenv()` 进 `config/cdn.php`，不进代码/DB；`.gitignore` 已有 `.env` 排除；`make()` 凭据缺失抛错并指明 env 变量名（fail-fast 于配置层）。
- **防 SSRF**：本方案 CDN 调用全部是**出站**到固定厂商端点（api.cloudflare.com / cloudfront.amazonaws.com / cdn.aliyuncs.com / cdn.tencentcloudapi.com），不将用户输入拼进 URL 发起站内请求。purge 入参白名单化：仅接受相对路径（自动补全本站域名）或 host 等于 `cdn.domain` 的绝对 URL，其余丢弃并告警日志；Guzzle 固定 `timeout=8`、`allow_redirects=false`、`verify=true`。
- **签名字节**：CloudFront SigV4、阿里云 RPC、腾讯云 TC3 均实现为可测纯函数（输入请求要素 → 输出签名），密钥只用于计算不落日志。
- **fail-open 原则**：CDN 故障绝不阻断管理端 CRUD；缓存最长 7 天自愈。

## 九、测试计划

`admin/tests/CdnTest.php`（Guzzle `MockHandler` 注入，无网络）：

1. `url()`：关闭→原样；开启相对→`https://{domain}/{path}`；绝对 http(s) 与空值→原样。
2. Cloudflare purge：断言 method=POST、URI=`/client/v4/zones/{zone}/purge_cache`、Header `Authorization: Bearer`、body files JSON；`purgeByTag` 断言 tags JSON。
3. CloudFront：断言 URI、body `InvalidationBatch` 含 `CallerReference`；**SigV4 已知答案测试**（用 AWS 官方文档示例输入/日期/密钥，断言 canonical request 与最终签名精确相等——这是签名实现的最小可运行校验）。
4. 阿里云：断言 query 含 `Action=RefreshObjectCaches`、`ObjectType=File`、`Signature`；preload 断言 `Action=PushObjectCache`。
5. 腾讯云：断言 `Authorization` 以 `TC3-HMAC-SHA256` 开头、body Action 正确。
6. 能力矩阵：Cloudflare/CloudFront 的 `preload()`、CloudFront/Aliyun/Tencent 的 `purgeByTag()` 抛 `LogicException`。
7. `make()`：未知提供商抛错；凭据缺失抛错且信息含 env 名。
8. fail-open：MockHandler 返回 500 → `purge()` 不抛异常（仅日志）。
9. SSRF 白名单：purge 传入外域绝对 URL → 被丢弃且不发起请求。

`service/tests/CdnUrlTest.php`：3 条 url() 断言。回归：`make test`（service 70 tests / 338 assertions 全绿，admin 套件全绿）。

## 十、分阶段实施步骤

| 阶段 | 内容 | 验收 |
|---|---|---|
| 一（基础设施+URL 改造） | config/cdn.php ×2、`Cdn::url()` ×2、UploadController 响应改 CDN 域名、nginx 缓存头、docker volume、service 5 处控制器输出重写 | CDN_ENABLED=true 后：上传响应返回 CDN URL；API 输出 CDN URL；CDN 域名回源可打开图片；CDN_ENABLED=false 全量回归零变化 |
| 二（Cloudflare + purge 接线） | CdnProviderInterface、Cdn 门面、CloudflareProvider、UploadController.delete / ShopProductController / ShopBannerController 触发 purge | CdnTest 1/2/6/7/8/9 过；admin 删图/删商品后 CF zone purge_cache 收到请求（curl 日志/mock 验证） |
| 三（其余三家适配器） | CloudFront（SigV4）/ 阿里云 / 腾讯云 + 各自签名单测 | CdnTest 3/4/5 过；`CDN_DEFAULT_PROVIDER` 切换三家后 purge 请求形状正确 |
| 四（收尾） | `cdn_preload.php` 脚本、按标签失效说明、文档、Makefile 回归 | `make test` + `make check`（phpstan）全绿 |

## 十一、已知取舍

- **purge 同步执行**（厂商 API 单次 <1s）：`ponytail: 同步 fail-open，若未来刷新量变大（秒级批量操作），升级为 Redis queue 异步（基础设施已就绪，仅需在 Cdn::purge 内换 push+consumer）。`
- **回源模型**而非上传到 CDN 桶：换厂商只需改 CNAME + config，业务代码零感知；代价是源站带宽仍承担首次回源。
- **不引 AWS SDK**：自实现最小 SigV4（有官方测试向量兜底）；若未来要接 S3/更多 AWS 服务，换 `aws/aws-sdk-php` 并删除 CloudFrontProvider 内签名代码。
- **byTag/preload 为部分厂商能力**：接口统一声明，不支持者抛 LogicException（调用方已知能力矩阵），不做能力探测。
- **immutable 缓存 7 天**：删除文件 purge 失败时最长残留 7 天（可接受；文件名唯一所以内容永不变）。
