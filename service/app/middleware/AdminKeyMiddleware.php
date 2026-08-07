<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * admin 后台内部接口密钥校验
 * 仅允许持有共享密钥（X-Admin-Key = config('admin.api_key')）的后台服务调用
 */
class AdminKeyMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $apiKey = config('admin.api_key', '');
        if ($apiKey === '') {
            return json(['code' => 500, 'msg' => '服务端未配置 ADMIN_API_KEY', 'data' => null])->withStatus(500);
        }

        $provided = $request->header('X-Admin-Key', '');
        if ($provided === '' || !hash_equals($apiKey, $provided)) {
            return json(['code' => 403, 'msg' => 'Forbidden', 'data' => null])->withStatus(403);
        }

        return $next($request);
    }
}
