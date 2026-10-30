<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests\Unit;

use app\middleware\LocaleMiddleware;
use app\middleware\PlatformMiddleware;
use app\middleware\RateLimitMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;

/**
 * 中间件请求解析纯逻辑回归测试（Reflection 调私有解析方法，不连 Redis/DB）
 */
class HttpParsingTest extends TestCase
{
    /**
     * 构造原始 HTTP 请求
     *
     * @param array<string, string> $headers
     */
    private function request(array $headers = []): Request
    {
        $lines = ['GET / HTTP/1.1', 'Host: localhost'];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        return new Request(implode("\r\n", $lines) . "\r\n\r\n");
    }

    private function invokePrivate(object $target, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod($target, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($target, ...$args);
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function locale_parsing_returns_supported_locale(string $header, string $expected): void
    {
        $middleware = new LocaleMiddleware();
        $request = $this->request(['Accept-Language' => $header]);

        $this->assertSame($expected, $this->invokePrivate($middleware, 'parseLocale', $request));
    }

    public static function localeProvider(): array
    {
        return [
            'exact en' => ['en', 'en'],
            'exact ko' => ['ko', 'ko'],
            'ja with region' => ['ja-JP', 'ja'],
            'q weights ignored' => ['ja-JP,ja;q=0.9,en;q=0.8', 'ja'],
            'semicolon without comma' => ['ja;q=0.9', 'ja'],
            'zh CN region' => ['zh-CN', 'zh_CN'],
            'zh CN underscore' => ['zh_CN', 'zh_CN'],
            'zh HK region' => ['zh-HK', 'zh_HK'],
            'zh TW falls back to HK' => ['zh-TW', 'zh_HK'],
            'en US region' => ['en-US', 'en'],
            'case insensitive' => ['EN-us', 'en'],
            'surrounding whitespace' => [' en ', 'en'],
            // 未收录的地区变体按主语言降级（zh 首选资源包为 zh_CN）
            'zh SG primary fallback' => ['zh-SG', 'zh_CN'],
        ];
    }

    #[Test]
    #[DataProvider('platformProvider')]
    public function platform_detection_prefers_header_then_user_agent(array $headers, string $expected): void
    {
        $middleware = new PlatformMiddleware();
        $request = $this->request($headers);

        $this->assertSame($expected, $this->invokePrivate($middleware, 'detect', $request));
    }

    public static function platformProvider(): array
    {
        return [
            'x-platform ios' => [['X-Platform' => 'ios'], 'ios'],
            'x-platform is case folded' => [['X-Platform' => 'IOS'], 'ios'],
            'x-platform wins over UA' => [
                ['X-Platform' => 'android', 'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'],
                'android',
            ],
            'invalid x-platform falls back to UA' => [
                ['X-Platform' => 'symbian', 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'],
                'windows',
            ],
            // UA 顺序敏感：iPad/iPhone/Android 的 UA 同时含 Mac OS X/Linux
            'ipad' => [['User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'], 'ipados'],
            'iphone' => [['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'], 'ios'],
            'android' => [['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8)'], 'android'],
            'harmonyos' => [['User-Agent' => 'Mozilla/5.0 (Phone; HarmonyOS 4.0)'], 'harmonyos'],
            'macintosh' => [['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0)'], 'macos'],
            'windows' => [['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'], 'windows'],
            'linux' => [['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64)'], 'linux'],
            'no headers' => [[], 'web'],
        ];
    }

    #[Test]
    #[DataProvider('rateRuleProvider')]
    public function rate_limit_matches_prefix_rule(string $path, array $expected): void
    {
        $middleware = new RateLimitMiddleware();

        $this->assertSame($expected, $this->invokePrivate($middleware, 'matchRule', $path));
    }

    public static function rateRuleProvider(): array
    {
        return [
            'login' => ['/api/auth/login', [60, 10]],
            'login subpath' => ['/api/auth/login/verify', [60, 10]],
            'password reset' => ['/api/auth/password/reset', [300, 5]],
            'password reset confirm' => ['/api/auth/password/reset/confirm', [300, 5]],
            'register' => ['/api/auth/register', [300, 5]],
            'social' => ['/api/auth/social', [300, 5]],
            'payment webhook' => ['/api/payment/webhook', [60, 5]],
            'orders list' => ['/api/orders', [10, 3]],
            'orders detail' => ['/api/orders/123', [10, 3]],
            'search' => ['/api/search', [1, 10]],
            'root default' => ['/', [60, 100]],
            'products default' => ['/api/products', [60, 100]],
            // '/api/order' 不是 '/api/orders' 的前缀，落默认规则
            'singular order default' => ['/api/order', [60, 100]],
        ];
    }
}
