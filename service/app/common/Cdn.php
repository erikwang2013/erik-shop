<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

/**
 * CDN URL 重写辅助（service 侧仅输出 URL，不做 purge/preload——由 admin 侧统一触发）
 * 与 admin/app/common/Cdn.php 的 url() 逻辑保持一致。
 */
class Cdn
{
    /**
     * 本地相对路径 → CDN URL。
     * 未配置域名/空值/已是完整 URL → 原样返回（旧地址兼容，零迁移）。
     * 域名来源：Redis cdn_settings（60s 缓存，管理端保存后主动失效）→ wa_options → env。
     */
    public static function url(string $path): string
    {
        if ($path === '' || str_starts_with($path, 'http')) {
            return $path;
        }
        $domain = self::domain();
        if ($domain === '') {
            return $path;
        }
        return 'https://' . rtrim($domain, '/') . '/' . ltrim($path, '/');
    }

    private static function domain(): string
    {
        $json = null;
        try {
            $json = redis()->get('cdn_settings');
        } catch (\Throwable $e) {
            // Redis 不可用 → 继续走 DB，不因单层故障跳过整个解析
        }
        if ($json === null || $json === false) {
            try {
                $row = \support\Db::table('wa_options')->where('name', 'cdn_settings')->value('value');
                if ($row !== null) {
                    $json = (string) $row;
                    try {
                        redis()->setex('cdn_settings', 60, $json);
                    } catch (\Throwable $e) {
                        // 回写缓存失败不阻断
                    }
                }
            } catch (\Throwable $e) {
                // DB 不可用 → 回退 env
            }
        }
        if ($json) {
            $settings = json_decode((string) $json, true);
            if (is_array($settings) && !empty($settings['domain'])) {
                if (isset($settings['enabled']) && !$settings['enabled']) {
                    return ''; // 管理端总开关关闭 → 原样返回
                }
                return (string) $settings['domain'];
            }
        }
        // env 兜底：总开关关闭时原样返回
        if (!(config('cdn.enabled') ?: false)) {
            return '';
        }
        return (string) (config('cdn.domain') ?: '');
    }
}
