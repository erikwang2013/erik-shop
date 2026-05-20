<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全局中间件配置
 * 按顺序执行，顺序不可变
 *
 * 请求 → Cors → GeoIpMiddleware → LocaleMiddleware → HashidsDecode
 *      → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → 控制器
 */

return [
    app\middleware\Cors::class,            // CORS跨域处理
    app\middleware\GeoIpMiddleware::class,  // GeoIP区域识别（未登录用户）
    app\middleware\LocaleMiddleware::class, // Accept-Language解析
    app\middleware\HashidsDecode::class,    // 请求hashid→snowflake ID
    app\middleware\HashidsEncode::class,    // 响应snowflake ID→hashid
];
