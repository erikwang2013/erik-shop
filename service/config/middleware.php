<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全局中间件配置
 * 按顺序执行，顺序不可变
 *
 * 请求 → Cors → Security → RateLimit → Platform → GeoIp → Locale → HashidsDecode
 *      → VersionRoute → (PosterVerify) → (JwtAuth) → HashidsEncode → Encryption → 控制器
 */

return [
    app\middleware\Cors::class,            // CORS跨域处理
    app\middleware\SecurityMiddleware::class, // 安全攻击检测拦截
    app\middleware\RateLimitMiddleware::class, // 令牌桶限流
    app\middleware\PlatformMiddleware::class, // 操作来源端识别
    app\middleware\GeoIpMiddleware::class,  // GeoIP区域识别（未登录用户）
    app\middleware\LocaleMiddleware::class, // Accept-Language解析
    app\middleware\HashidsDecode::class,    // 请求hashid→snowflake ID
    app\middleware\VersionRoute::class,     // API版本路由(API-Version header)
    app\middleware\HashidsEncode::class,    // 响应snowflake ID→hashid
    app\middleware\EncryptionMiddleware::class, // 接口数据加解密（X-Encrypt-Response header 触发）
];
