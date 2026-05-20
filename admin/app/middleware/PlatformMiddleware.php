<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Admin 操作来源端识别中间件
 * 管理后台通常来自浏览器(web)，但也支持API调用
 */
class PlatformMiddleware implements MiddlewareInterface
{
    private const VALID_PLATFORMS = [
        'ipados', 'macos', 'windows', 'linux',
        'ios', 'android', 'harmonyos', 'web',
    ];

    private const UA_PATTERNS = [
        'iPad' => 'ipados', 'Macintosh' => 'macos', 'Mac OS X' => 'macos',
        'Windows' => 'windows', 'Linux' => 'linux',
        'iPhone' => 'ios', 'Android' => 'android', 'HarmonyOS' => 'harmonyos',
    ];

    public function process(Request $request, callable $next): Response
    {
        $platform = strtolower($request->header('X-Platform', ''));
        if (!in_array($platform, self::VALID_PLATFORMS)) {
            $ua = $request->header('User-Agent', '');
            foreach (self::UA_PATTERNS as $keyword => $pf) {
                if (str_contains($ua, $keyword)) { $platform = $pf; break; }
            }
            if (!in_array($platform, self::VALID_PLATFORMS)) {
                $platform = 'web';
            }
        }

        $request->platform = $platform;
        return $next($request);
    }
}
