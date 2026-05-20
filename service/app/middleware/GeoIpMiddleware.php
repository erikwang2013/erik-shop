<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * GeoIP 区域识别中间件
 * 未登录用户自动识别区域/语言/币种
 * 已登录用户不覆盖手动选择
 */
class GeoIpMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // 已登录用户不覆盖（已有手动选择）
        if (!empty($request->userId)) {
            return $next($request);
        }

        $ip = $request->getRealIp();
        $geo = $this->lookup($ip);

        $request->geoCountry = $geo['country_iso_code'] ?? config('geoip.default.country_iso_code');
        $request->geoCurrency = $geo['currency'] ?? config('geoip.default.currency');
        $request->geoLanguage = $geo['language'] ?? config('geoip.default.language');

        return $next($request);
    }

    private function lookup(string $ip): array
    {
        $config = config('geoip.default');
        // 内网IP直接返回默认值
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return $config;
        }

        // TODO: 集成 MaxMind GeoLite2 数据库
        // $reader = new \MaxMind\Db\Reader(config('geoip.database_path'));
        // $record = $reader->get($ip);

        return $config;
    }
}
