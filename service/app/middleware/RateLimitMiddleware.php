<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 令牌桶限流中间件 (基于Redis)
 * 使用滑动窗口算法，按用户+端点限流
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    // 默认限流规则 (窗口秒数 => 最大请求数)
    private const RULES = [
        'default' => [60 => 100],       // 默认: 60秒100次
        '/api/auth/login' => [60 => 10], // 登录: 60秒10次
        '/api/auth/register' => [300 => 5], // 注册: 300秒5次
        '/api/payment' => [60 => 5],     // 支付: 60秒5次
        '/api/orders' => [10 => 3],      // 下单: 10秒3次
        '/api/search' => [1 => 10],      // 搜索: 1秒10次
    ];

    public function process(Request $request, callable $next): Response
    {
        $path = $request->path();

        // 匹配限流规则
        $rule = $this->matchRule($path);
        if ($rule === null) return $next($request);

        [$window, $limit] = $rule;

        // 构建Redis key
        $userId = $request->userId ?? '0';
        $ip = $request->getRealIp();
        $key = "erik_ratelimit:{$userId}:{$ip}:" . md5($path);

        try {
            $redis = redis();
            $now = microtime(true);
            $clearBefore = $now - $window;

            // 移除窗口外的记录
            $redis->zRemRangeByScore($key, 0, $clearBefore);

            // 统计窗口内请求数
            $count = $redis->zCard($key);

            if ($count >= $limit) {
                $retryAfter = $window - ($now - (float) $redis->zRange($key, 0, 0)[0]);
                return json([
                    'code' => 429,
                    'msg' => "请求过于频繁，请{$retryAfter}秒后重试",
                    'data' => ['retry_after' => max(1, (int) $retryAfter)],
                ], 429, ['X-RateLimit-Limit' => $limit, 'X-RateLimit-Remaining' => 0, 'Retry-After' => max(1, (int) $retryAfter)]);
            }

            // 添加当前请求时间戳到有序集合
            $redis->zAdd($key, $now, $now . '.' . uniqid());
            $redis->expire($key, $window + 1);
        } catch (\Throwable $e) {
            // Redis不可用时降级放行
        }

        return $next($request);
    }

    /** 匹配限流规则 */
    private function matchRule(string $path): ?array
    {
        foreach (self::RULES as $prefix => $rule) {
            if ($prefix === 'default') continue;
            if (str_starts_with($path, $prefix)) {
                return [array_key_first($rule), reset($rule)];
            }
        }
        // 默认规则
        $default = self::RULES['default'];
        return [array_key_first($default), reset($default)];
    }
}
