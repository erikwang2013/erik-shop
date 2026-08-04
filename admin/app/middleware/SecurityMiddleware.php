<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Erikwang2013\Security\SecurityGuard;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Admin WAF 安全防护中间件 — 基于 erikwang2013/security-php
 *
 * 25+ 类攻击检测器，可独立配置启用/拦截/日志模式。
 * 配置位于 config/plugin/erikwang2013/security-php/app.php
 */
class SecurityMiddleware implements MiddlewareInterface
{
    private static bool $guardInitialized = false;

    public function process(Request $request, callable $next): Response
    {
        if (!self::$guardInitialized) {
            SecurityGuard::init(config('plugin.erikwang2013.security-php.app'));
            self::$guardInitialized = true;
        }

        $threats = SecurityGuard::guard($request->all(), [
            'ip'     => $request->getRealIp(),
            'method' => $request->method(),
            'uri'    => $request->uri(),
        ]);

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            return \response(SecurityGuard::blockMessage(), SecurityGuard::blockStatusCode($threats));
        }

        // 暴力破解防护
        $path = $request->path();
        if (str_contains($path, '/login') || str_contains($path, '/auth/login')) {
            try {
                $key = 'erik_admin_brute:' . $request->getRealIp();
                $count = (int) redis()->get($key);
                if ($count >= 5) {
                    return \response('Too Many Attempts', 429);
                }
                redis()->incr($key);
                redis()->expire($key, 300);
            } catch (\Throwable $e) {
                // Redis 不可用时降级放行
            }
        }

        $response = $next($request);

        return $response->withHeaders([
            'X-Content-Type-Options'            => 'nosniff',
            'X-Frame-Options'                   => 'DENY',
            'X-XSS-Protection'                  => '1; mode=block',
            'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cache-Control'                     => 'no-store, no-cache, must-revalidate',
            'Server'                            => '',
        ]);
    }
}
