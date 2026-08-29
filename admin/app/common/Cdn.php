<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use app\common\cdn\AliyunProvider;
use app\common\cdn\CloudflareProvider;
use app\common\cdn\CloudFrontProvider;
use app\common\cdn\TencentProvider;
use support\Log;

class CdnException extends \RuntimeException
{
}

/**
 * CDN 统一门面（admin 侧：上传方负责 purge/preload）
 *
 * 用法：
 *   Cdn::url('/app/admin/upload/img/xxx.jpg');      // 输出边界重写 URL
 *   Cdn::purge(['/app/admin/upload/img/xxx.jpg']);  // 删文件后失效缓存
 *   Cdn::preload([...]); Cdn::purgeByTag('tag');    // 部分厂商能力（不支持者抛 LogicException）
 *
 * 原则：
 *   - make() 未知提供商/缺凭据 → CdnException（指明应配置的 env 变量名），配置层 fail-fast
 *   - purge/purgeByTag/preload 全部 fail-open：CDN 关闭或调用失败仅记日志，绝不让管理端 CRUD 失败
 */
final class Cdn
{
    /**
     * URL 重写：总开关关闭/未配置域名/空值/已是完整 URL → 原样返回；否则 https://{cdn.domain}/{path}
     */
    public static function url(string $path): string
    {
        if (!(config('cdn.enabled') ?: false)) {
            return $path;
        }
        $domain = (string) (config('cdn.domain') ?: '');
        if ($domain === '' || $path === '' || str_starts_with($path, 'http')) {
            return $path;
        }
        return 'https://' . rtrim($domain, '/') . '/' . ltrim($path, '/');
    }

    /**
     * 解析提供商配置：DB（wa_cdn_providers）优先，env 兜底；DB 停用或不存在返回 null。
     * DB 不可用时静默回退 env（管理端未建表/未配置不阻断）。
     */
    public static function providerConfig(string $code): ?array
    {
        try {
            $row = \plugin\admin\app\model\shop\CdnProviders::where('code', $code)->first();
            if ($row) {
                if (!$row->enabled) {
                    return null;
                }
                $db = (array) json_decode((string) $row->config, true);
                return array_merge((array) config("cdn.providers.$code", []), $db); // DB 覆盖 env
            }
        } catch (\Throwable $e) {
            // DB 不可用 → 静默回退 env
        }
        return config("cdn.providers.$code", []);
    }

    /**
     * 按配置实例化提供商；未配置/停用/未知或凭据缺失 → 抛 CdnException
     */
    public static function make(?string $provider = null): CdnProviderInterface
    {
        $code = $provider ?: (string) (config('cdn.default') ?: 'cloudflare');
        $config = self::providerConfig($code);
        if ($config === null) {
            throw new CdnException("CDN 提供商 {$code} 已停用或未配置");
        }
        return match ($code) {
            'cloudflare' => new CloudflareProvider($config),
            'cloudfront' => new CloudFrontProvider($config),
            'aliyun'     => new AliyunProvider($config),
            'tencent'    => new TencentProvider($config),
            default      => throw new CdnException("未知 CDN 提供商: {$code}（支持 cloudflare/cloudfront/aliyun/tencent）"),
        };
    }

    /**
     * 入参归一化 + SSRF 白名单（供适配器统一使用）：
     * 相对路径 → https://{cdn.domain}{path}；绝对 URL 且 host == cdn.domain 保留；
     * 其余（外域/空）丢弃并告警日志。CDN 未启用时返回空数组。
     */
    public static function normalizeUrls(array $urls): array
    {
        if (!(config('cdn.enabled') ?: false)) {
            return [];
        }
        $domain = strtolower(rtrim((string) (config('cdn.domain') ?: ''), '/'));
        $result = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $url)) {
                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                if ($domain !== '' && $host === $domain) {
                    $result[] = $url;
                } else {
                    Log::warning("CDN purge 丢弃外域 URL: {$url}");
                }
            } elseif (str_contains($url, '://')) {
                Log::warning("CDN purge 丢弃非 http(s) URL: {$url}");
            } elseif ($domain !== '') {
                $result[] = 'https://' . $domain . '/' . ltrim($url, '/');
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * 按 URL 失效。fail-open：CDN 关闭/提供商停用直接返回；失败仅 Log::error 不抛异常。
     * 按厂商单请求限额分块循环；操作结束后写 wa_cdn_purge_logs（写失败不影响主流程）。
     */
    public static function purge(array $urls, ?string $provider = null): void
    {
        if (!(config('cdn.enabled') ?: false) || $urls === []) {
            return;
        }
        $code = self::code($provider);
        if (self::providerConfig($code) === null) {
            return; // 停用或未配置：静默跳过
        }
        foreach (self::chunks($code, $urls) as $chunk) {
            $status = 1;
            $message = '';
            try {
                self::make($code)->purge($chunk);
            } catch (\Throwable $e) {
                $status = 0;
                $message = $e->getMessage();
                Log::error('CDN purge 失败: ' . $message);
            }
            self::log($code, 'purge', $chunk, $status, $message);
        }
    }

    /**
     * 按标签失效。fail-open；不支持该能力的厂商由适配器抛 LogicException（此处捕获记日志）
     */
    public static function purgeByTag(string $tag, ?string $provider = null): void
    {
        if (!(config('cdn.enabled') ?: false) || $tag === '') {
            return;
        }
        $code = self::code($provider);
        if (self::providerConfig($code) === null) {
            return;
        }
        $status = 1;
        $message = '';
        try {
            self::make($code)->purgeByTag($tag);
        } catch (\Throwable $e) {
            $status = 0;
            $message = $e->getMessage();
            Log::error('CDN purgeByTag 失败: ' . $message);
        }
        self::log($code, 'purge_by_tag', [$tag], $status, $message);
    }

    /**
     * 预热。fail-open；按厂商单请求限额分块循环
     */
    public static function preload(array $urls, ?string $provider = null): void
    {
        if (!(config('cdn.enabled') ?: false) || $urls === []) {
            return;
        }
        $code = self::code($provider);
        if (self::providerConfig($code) === null) {
            return;
        }
        foreach (self::chunks($code, $urls) as $chunk) {
            $status = 1;
            $message = '';
            try {
                self::make($code)->preload($chunk);
            } catch (\Throwable $e) {
                $status = 0;
                $message = $e->getMessage();
                Log::error('CDN preload 失败: ' . $message);
            }
            self::log($code, 'preload', $chunk, $status, $message);
        }
    }

    private static function code(?string $provider): string
    {
        return $provider ?: (string) (config('cdn.default') ?: 'cloudflare');
    }

    /** 各厂商单请求 URL 数量上限（官方文档）；未列出的取最保守 30 */
    private const CHUNK_LIMITS = [
        'cloudflare' => 30,
        'aliyun'     => 100,
        'tencent'    => 1000,
        'cloudfront' => 3000,
    ];

    /** 按厂商限额分块（已归一化的外域 URL 在此丢弃） */
    private static function chunks(string $code, array $urls): array
    {
        $urls = self::normalizeUrls($urls);
        $limit = self::CHUNK_LIMITS[$code] ?? 30;
        return $urls === [] ? [] : array_chunk($urls, $limit);
    }

    /**
     * 写刷新日志；DB 写入失败仅记日志，不影响主流程
     */
    private static function log(string $code, string $type, array $urls, int $status, string $message): void
    {
        try {
            \plugin\admin\app\model\shop\CdnPurgeLogs::create([
                'provider' => $code,
                'type' => $type,
                'urls' => json_encode(array_values($urls), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => $status,
                'message' => $message,
                'admin_id' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('CDN 日志写入失败: ' . $e->getMessage());
        }
    }
}
