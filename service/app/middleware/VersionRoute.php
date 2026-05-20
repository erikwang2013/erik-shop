<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * API版本路由中间件
 * 读取 API-Version header，映射到对应版本的控制器命名空间
 * 版本不在URL中，而在header中传递
 */
class VersionRoute implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // 获取版本号（默认使用配置中的最新版本）
        $version = $request->header('API-Version', '');
        $versions = config('versions', []);

        $namespace = $versions[$version] ?? $versions['default'] ?? 'v1';

        // 将版本命名空间注入request
        $request->apiVersion = $namespace;

        return $next($request);
    }
}
