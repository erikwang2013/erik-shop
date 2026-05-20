<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全局辅助函数
 */

use app\common\HashidsHelper;

/**
 * 获取当前登录用户ID
 */
if (!function_exists('userId')) {
    function userId(): ?string
    {
        return request()->userId ?? null;
    }
}

/**
 * 获取当前API版本
 */
if (!function_exists('apiVersion')) {
    function apiVersion(): string
    {
        return request()->apiVersion ?? 'v1';
    }
}

/**
 * 获取当前语言
 */
if (!function_exists('currentLocale')) {
    function currentLocale(): string
    {
        return request()->locale ?? 'en';
    }
}

/**
 * 获取当前区域（GeoIP识别或手动选择）
 */
if (!function_exists('currentCountry')) {
    function currentCountry(): string
    {
        return request()->geoCountry ?? 'US';
    }
}

/**
 * 编码ID为hashid（手动编码时使用）
 */
if (!function_exists('hashidEncode')) {
    function hashidEncode(int|string $id): string
    {
        return HashidsHelper::encode($id);
    }
}

/**
 * 解码hashid为原始ID
 */
if (!function_exists('hashidDecode')) {
    function hashidDecode(string $hash): ?string
    {
        return HashidsHelper::decode($hash);
    }
}
