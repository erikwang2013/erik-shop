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
        // 只取第一个语言项，忽略 q 权重；统一 '-' 为 '_'
        $first = explode(',', $request->header('Accept-Language', 'en'))[0];
        $userLocale = str_replace('-', '_', trim(explode(';', $first)[0]));

        // 精确匹配（忽略大小写），zh_TW 无资源包，归入 zh_HK
        foreach (self::$supported as $locale) {
            if (strcasecmp($locale, $userLocale) === 0) {
                return $locale;
            }
        }
        if (strcasecmp($userLocale, 'zh_TW') === 0) {
            return 'zh_HK';
        }

        // 降级到主语言：ja-JP -> ja，en-US -> en，zh-CN -> zh_CN
        return $this->matchLocale(explode('_', $userLocale)[0])
            ?? config('country.default.language', 'en');
    }

    private function matchLocale(string $primaryLang): ?string
    {
        foreach (self::$supported as $locale) {
            if (strcasecmp(explode('_', $locale)[0], $primaryLang) === 0) {
                return $locale;
            }
        }
        return null;
    }
}
