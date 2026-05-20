<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 操作来源端识别中间件
 * 从 X-Platform header 识别客户端平台
 *
 * 支持平台:
 *   ipados, macos, windows, linux, ios, android, harmonyos, web
 */
class PlatformMiddleware implements MiddlewareInterface
{
    // 有效平台列表
    private const VALID_PLATFORMS = [
        'ipados', 'macos', 'windows', 'linux',
        'ios', 'android', 'harmonyos', 'web',
    ];

    // User-Agent 备选识别 (当X-Platform未传递时)
    private const UA_PATTERNS = [
        'iPad' => 'ipados',
        'Macintosh' => 'macos',
        'Mac OS X' => 'macos',
        'Windows' => 'windows',
        'Linux' => 'linux',
        'iPhone' => 'ios',
        'Android' => 'android',
        'HarmonyOS' => 'harmonyos',
    ];

    public function process(Request $request, callable $next): Response
    {
        $platform = $this->detect($request);
        $request->platform = $platform;

        return $next($request);
    }

    /**
     * 优先从 X-Platform header，降级User-Agent，再降级'web'
     */
    private function detect(Request $request): string
    {
        // 1. 自定义 Header（Flutter/HarmonyOS 客户端会发）
        $platform = strtolower($request->header('X-Platform', ''));
        if (in_array($platform, self::VALID_PLATFORMS)) {
            return $platform;
        }

        // 2. User-Agent 解析（浏览器访问 admin）
        $ua = $request->header('User-Agent', '');
        foreach (self::UA_PATTERNS as $keyword => $pf) {
            if (str_contains($ua, $keyword)) {
                return $pf;
            }
        }

        // 3. 默认
        return 'web';
    }
}
