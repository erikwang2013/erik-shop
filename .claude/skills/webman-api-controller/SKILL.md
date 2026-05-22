---
name: webman-api-controller
description: Use when creating REST API controllers in shop-php service/ — extends BaseApiController, uses ApiResponse helper, follows naming conventions (v1/ClassController), API version via header not URL, and implements index/store/show/update/destroy methods
---

# Webman API 控制器

## Overview

控制器继承 `BaseApiController`，按版本放在 `app\controller\v1` 命名空间下，使用 `ApiResponse` 统一响应。API 版本通过 `API-Version` header 指定，不在 URL 中。

## When to Use

- 创建新的 API 端点
- 实现 RESTful 资源控制器
- 处理分页、筛选、排序

## 版本路由

```
客户端请求:  GET /api/products
            API-Version: 2026-05-20

VersionRoute 中间件:
  → 解析 API-Version header
  → 查 config/versions.php 映射表
  → 转发到 app\controller\v1\ProductController::index()
```

## Core Pattern

### BaseApiController

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller;

use app\common\ApiResponse;
use Webman\Http\Request;

class BaseApiController
{
    public function json($data): \support\Response
    {
        return json($data);
    }
}
```

### 标准 CRUD 控制器（v1）

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Product;
use Webman\Http\Request;

class ProductController extends \app\controller\BaseApiController
{
    // GET /api/products — 分页列表
    public function index(Request $request): \support\Response
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);
        $categoryId = $request->input('category_id');  // hashids 中间件已解码

        $query = Product::where('status', 2);  // 仅上架商品
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $items = $query->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::paginate(
            $items->items(),
            $items->total(),
            $items->currentPage(),
            $items->perPage()
        );
    }

    // GET /api/products/{id} — 详情
    public function show(Request $request, string $id): \support\Response
    {
        $product = Product::with(['skus', 'images', 'category'])->find($id);
        if (!$product) {
            return ApiResponse::fail('商品不存在', 404);
        }
        return ApiResponse::success($product);
    }
}
```

## 命名约定

| HTTP 方法 | URI 模式 | 方法名 | 说明 |
|-----------|---------|--------|------|
| GET | `/api/{resources}` | `index` | 分页列表 |
| GET | `/api/{resources}/{id}` | `show` | 资源详情 |
| POST | `/api/{resources}` | `store` | 创建资源 |
| PUT | `/api/{resources}/{id}` | `update` | 更新资源 |
| DELETE | `/api/{resources}/{id}` | `destroy` | 删除资源 |
| POST | `/api/{resources}/{id}/{action}` | `action` | 自定义动作 |

> 版本不在 URL 中，通过 `API-Version` header 传递（如 `2026-05-20`）

## 版本配置文件

`config/versions.php` — 版本号与控制器命名空间映射：
```php
return [
    '2026-05-20' => 'v1',    // 当前稳定版
    // '2026-08-01' => 'v2', // 未来版本
];
```

## 输入获取

```php
// 中间件已解码 hashids，直接使用原始 snowflake ID
$id = $request->input('product_id');       // body/query 中的 hashid → snowflake
$id = $request->route->param('id');        // URL 路径参数

// 当前用户（JwtAuth 中间件注入）
$userId = $request->userId;

// 当前 API 版本（VersionRoute 中间件注入）
$apiVersion = $request->apiVersion;
```

## API 响应

```php
// 成功
ApiResponse::success($data);                    // {"code":0, "msg":"ok", "data":{...}}
ApiResponse::success($data, '操作成功');          // 自定义消息（支持 trans() 翻译）

// 分页
ApiResponse::paginate($items, $total, $page, $perPage);
// {"code":0, "msg":"ok", "data":{"list":[...], "total":100, "page":1, "per_page":20}}

// 失败
ApiResponse::fail('错误信息');                    // {"code":1, "msg":"错误信息", "data":null}
ApiResponse::fail('验证失败', 422);               // 自定义错误码
```

## 路由注册

```php
// config/route.php
use Webman\Route;

// 所有 API 路由不带版本号前缀
Route::group('/api', function () {
    // 公开路由
    Route::get('/products', [app\controller\v1\ProductController::class, 'index']);
    Route::get('/products/{id:\w+}', [app\controller\v1\ProductController::class, 'show']);

    // 需要登录
    Route::group('', function () {
        Route::post('/orders', [app\controller\v1\OrderController::class, 'store']);
        Route::get('/orders', [app\controller\v1\OrderController::class, 'index']);
    })->middleware([app\middleware\JwtAuth::class]);

    // 需要登录 + 随机验证（敏感操作）
    Route::group('', function () {
        Route::post('/orders', [app\controller\v1\OrderController::class, 'store']);
    })->middleware([
        app\middleware\PosterVerify::class,
        app\middleware\JwtAuth::class,
    ]);
});
```

## Common Mistakes

- **手动编码 hashids**：不需要，中间件自动处理
- **忘记 with()**：关联数据需要预加载，避免 N+1
- **版本号放 URL**：版本统一用 `API-Version` header，不在路径中
- **直接输出模型**：必须通过 ApiResponse 包装
- **未限制查询范围**：index 方法必须加 status 过滤
