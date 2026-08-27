<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use support\Redis;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class PosterVerify implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
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

        $posterToken = $request->header('X-Poster-Token') ?? $request->input('poster_token');
        if (empty($posterToken)) {
            return json(['code' => 40001, 'msg' => '需要人机验证', 'data' => ['need_poster' => true]]);
        }

        // 验证token（Redis存储）
        $redisKey = "shop:poster:{$posterToken}";
        $verified = false;
        try {
            $verified = Redis::get($redisKey) === '1';
            if ($verified) Redis::del($redisKey);
        } catch (\Throwable $e) {
            // Redis不可用时降级放行
            $verified = true;
        }

        if (!$verified) {
            return json(['code' => 40002, 'msg' => '验证失败或已过期，请重试', 'data' => null]);
        }

        return $next($request);
    }
}
