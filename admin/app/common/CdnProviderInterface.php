<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

/**
 * CDN 提供商适配器统一接口
 * 实现：cdn\CloudflareProvider / cdn\CloudFrontProvider / cdn\AliyunProvider / cdn\TencentProvider
 * 能力矩阵：purge 4/4；preload 2/4（阿里云/腾讯云）；purgeByTag 1/4（Cloudflare）
 */
interface CdnProviderInterface
{
    /**
     * 按 URL 失效。入参为相对路径（自动补全为 https://{CDN_DOMAIN} 绝对 URL）或本站绝对 URL；
     * 外域绝对 URL 丢弃并 Log::warning。失败抛异常（由 Cdn 门面 fail-open 兜底）。
     */
    public function purge(array $urls): void;

    /**
     * 按标签失效。仅 Cloudflare 支持（Cache-Tag），其余实现必须抛 LogicException
     */
    public function purgeByTag(string $tag): void;

    /**
     * 预热。仅阿里云/腾讯云支持，其余实现必须抛 LogicException
     */
    public function preload(array $urls): void;
}
