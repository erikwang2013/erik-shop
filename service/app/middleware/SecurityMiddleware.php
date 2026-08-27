<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Erikwang2013\Security\SecurityGuard;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Http\UploadFile;
use Webman\MiddlewareInterface;

/**
 * WAF 安全防护中间件 — 基于 erikwang2013/security-php
 *
 * 25+ 类攻击检测器，可独立配置启用/拦截/日志模式。
 * 配置位于 config/plugin/erikwang2013/security-php/app.php
 */
class SecurityMiddleware implements MiddlewareInterface
{
    private const BRUTE_LIMIT = 10;
    private const BRUTE_WINDOW = 60;

    private static bool $guardInitialized = false;

    public function process(Request $request, callable $next): Response
    {
        if (!self::$guardInitialized) {
            SecurityGuard::init(config('plugin.erikwang2013.security-php.app'));
            self::$guardInitialized = true;
        }

        $data = $request->all();
        foreach ($request->file() as $key => $file) {
            if ($file instanceof UploadFile) {
                $data[$key] = ['name' => $file->getUploadName() ?? ''];
            }
        }

        $threats = SecurityGuard::guard($data, [
            'ip'     => $request->getRealIp(),
            'method' => $request->method(),
            'uri'    => $request->uri(),
        ]);

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            return json([
                'code' => SecurityGuard::blockStatusCode($threats),
                'msg'  => SecurityGuard::blockMessage(),
                'data' => null,
            ]);
        }

        // 暴力破解防护（框架相关逻辑，保留）
        if ($r = $this->checkBrute($request)) {
            return $r;
        }

        $response = $next($request);

        return $this->addSecurityHeaders($response, $request);
    }

    private function checkBrute(Request $request): ?Response
    {
        $path = $request->path();
        if (!str_contains($path, '/auth/login') && !str_contains($path, '/auth/register')) {
            return null;
        }

        try {
            $ip = $request->getRealIp();
            $key = 'shop_brute:' . $ip . ':' . (str_contains($path, 'login') ? 'login' : 'register');
            $redis = redis();
            $count = (int) $redis->get($key);
            if ($count >= self::BRUTE_LIMIT) {
                return json([
                    'code' => 40008,
                    'msg'  => 'Too many attempts, please try later',
                    'data' => null,
                ]);
            }
            $redis->incr($key);
            $redis->expire($key, self::BRUTE_WINDOW);
        } catch (\Throwable $e) {
            // Redis 不可用时降级放行
        }
        return null;
    }

    private function addSecurityHeaders(Response $response, Request $request): Response
    {
        $headers = [
            'X-Content-Type-Options'             => 'nosniff',
            'X-Frame-Options'                    => 'DENY',
            'X-XSS-Protection'                   => '1; mode=block',
            'Referrer-Policy'                    => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies'  => 'none',
            'X-Download-Options'                 => 'noopen',
            'Permissions-Policy'                 => 'camera=(), microphone=(), geolocation=()',
            'Server'                             => '',
        ];

        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'])) {
            $headers['Cache-Control'] = 'public, max-age=60, s-maxage=300';
        } else {
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate';
        }

        return $response->withHeaders($headers);
    }
}
