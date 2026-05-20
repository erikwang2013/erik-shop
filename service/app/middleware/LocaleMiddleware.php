<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 国际化语言中间件
 * 解析 Accept-Language header，设置当前语言
 * 支持：zh_CN, zh_HK, en, ja, ko
 */
class LocaleMiddleware implements MiddlewareInterface
{
    private static array $supported = ['zh_CN', 'zh_HK', 'en', 'ja', 'ko'];

    public function process(Request $request, callable $next): Response
    {
        $locale = $this->parseLocale($request);

        // 设置到请求属性
        $request->locale = $locale;

        // 设置PHP翻译语言
        if (function_exists('locale')) {
            locale($locale);
        }

        return $next($request);
    }

    private function parseLocale(Request $request): string
    {
        // 优先使用用户手动设置的语言
        $userLocale = $request->header('Accept-Language', 'en');
        $primaryLang = substr($userLocale, 0, 2);

        // 精确匹配优先，降级到同语系
        $locale = $this->matchLocale($userLocale)
            ?? $this->matchLocale($primaryLang . '_')
            ?? config('country.default.language', 'en');

        return $locale;
    }

    private function matchLocale(string $prefix): ?string
    {
        foreach (self::$supported as $locale) {
            if (str_starts_with($locale, $prefix)) {
                return $locale;
            }
        }
        return null;
    }
}
