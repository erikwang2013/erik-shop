---
name: webman-middleware-pipeline
description: Use when adding middleware to webman projects in shop-php service/ — requires specific ordering (Cors → HashidsDecode → VersionRoute → PosterVerify → JwtAuth → HashidsEncode), correct namespace conventions, and proper config registration
---

# Webman 中间件管道

## Overview

中间件按固定顺序组成管道。每个中间件负责单一职责，通过 `config/middleware.php` 注册全局中间件，通过 `config/route.php` 注册路由级中间件。

## When to Use

- 创建新的中间件类
- 配置中间件执行顺序
- 调试请求/响应异常

## 中间件顺序（不可变）

```
请求 → Cors → HashidsDecode → VersionRoute → PosterVerify → JwtAuth → HashidsEncode → 控制器
                                                                                        ↓
响应 ←──────────────────────────────────────────────────────────────────────────────────
```

| 位置 | 中间件 | 类型 | 作用 |
|------|--------|------|------|
| 1 | `Cors` | 全局 | 跨域头处理 |
| 2 | `HashidsDecode` | 全局 | 请求参数 hashid → snowflake ID |
| 3 | `VersionRoute` | 全局 | 解析 `API-Version` header，路由到版本控制器 |
| 4 | `PosterVerify` | 路由 | 敏感操作随机验证（滑块/拼图/点击） |
| 5 | `JwtAuth` | 路由 | JWT token 验证，注入 user_id |
| 6 | `HashidsEncode` | 全局 | 响应数据 snowflake ID → hashid |

## Core Pattern

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class DemoMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // 前置处理：修改 request

        $response = $next($request);

        // 后置处理：修改 response

        return $response;
    }
}
```

## 注册中间件

全局中间件在 `config/middleware.php`：
```php
return [
    app\middleware\Cors::class,
    app\middleware\HashidsDecode::class,
    app\middleware\VersionRoute::class,    // API 版本路由
    app\middleware\HashidsEncode::class,
];
```

路由级中间件在 `config/route.php` 中按路由组注册：
```php
Route::group('/api', function () {
    // 需要验证的路由
})->middleware([
    app\middleware\PosterVerify::class,  // 敏感操作随机验证
    app\middleware\JwtAuth::class,       // JWT 认证
]);
```

## VersionRoute 中间件

读取 `API-Version` header，根据 `config/versions.php` 映射表将请求转发到对应版本控制器：

```php
class VersionRoute implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $version = $request->header('API-Version', '2026-05-20');
        $versions = config('versions');
        $namespace = $versions[$version] ?? 'v1';

        // 将版本命名空间注入 request，供路由使用
        $request->apiVersion = $namespace;

        return $next($request);
    }
}
```

## Poster 验证中间件

`PosterVerify` 中间件用于敏感操作（注册、下单、支付），通过 `erikwang2013/poster-php` 实现：
- 支持多种验证类型：滑块拼图、文字点击、图形旋转
- 随机选择验证类型增加安全性
- 验证结果通过 Redis 缓存，带过期时间

## Common Mistakes

- **顺序错误**：HashidsDecode 必须在 JwtAuth 之前（JWT 需要真实的 snowflake ID）
- **VersionRoute 位置**：必须在 HashidsDecode 之后、业务中间件之前
- **重复注册**：同一中间件不要同时在全局和路由中注册
- **return 遗漏**：中间件必须 return $response，否则请求中断
- **忘记 use**：MiddlewareInterface 通过 use 导入，不加前导 `\`
