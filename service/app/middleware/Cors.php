<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * CORS 跨域中间件
 * 允许Flutter客户端和管理后台跨域访问
 */
class Cors implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // OPTIONS 预检请求直接返回
        if ($request->method() === 'OPTIONS') {
            return $this->corsResponse(response(''));
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->corsResponse($response);
    }

    private function corsResponse(Response $response): Response
    {
        return $response->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, API-Version, Accept-Language, X-Device-Fingerprint',
            'Access-Control-Max-Age' => '86400',
        ]);
    }
}
