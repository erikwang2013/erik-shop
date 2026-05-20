<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * JWT 认证中间件
 * 验证 Bearer token，提取user_id注入request
 * 使用 erikwang2013/jwt-webman 实现
 */
class JwtAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // 从Header中提取token
        $token = $this->extractToken($request);

        if (empty($token)) {
            return json([
                'code' => 401,
                'msg' => '请先登录',
                'data' => null,
            ]);
        }

        // TODO: 集成 erikwang2013/jwt-webman 验证token
        // $payload = Jwt::verify($token);
        // if (!$payload) { return 401; }

        // 注入用户ID（hashids中间件已解码）
        // $request->userId = $payload['sub'];

        // 占位：临时放行
        $request->userId = '1';

        return $next($request);
    }

    /**
     * 从请求中提取JWT token
     */
    private function extractToken(Request $request): ?string
    {
        // Authorization: Bearer xxx
        $header = $request->header('Authorization', '');
        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return $matches[1];
        }

        // 也支持query参数（WebSocket等场景）
        return $request->input('token');
    }
}
