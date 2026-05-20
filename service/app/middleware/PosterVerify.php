<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 敏感操作随机验证中间件
 * 注册、下单、支付等操作需要人机验证
 * 使用 erikwang2013/poster-php 实现
 */
class PosterVerify implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // 检查当前路由是否需要验证
        $path = $request->path();
        $protectedRoutes = config('poster.protected_routes', []);

        $needsVerify = false;
        foreach ($protectedRoutes as $route) {
            if (str_starts_with($path, $route)) {
                $needsVerify = true;
                break;
            }
        }

        if (!$needsVerify) {
            return $next($request);
        }

        // 验证poster token
        $posterToken = $request->header('X-Poster-Token') ?? $request->input('poster_token');
        if (empty($posterToken)) {
            return json([
                'code' => 40001,
                'msg' => '需要人机验证，请完成验证后再试',
                'data' => ['need_poster' => true],
            ]);
        }

        // TODO: 集成 erikwang2013/poster-php 验证token
        // $verified = Poster::verify($posterToken);
        $verified = true; // 占位

        if (!$verified) {
            return json([
                'code' => 40002,
                'msg' => '验证失败，请重试',
                'data' => null,
            ]);
        }

        return $next($request);
    }
}
