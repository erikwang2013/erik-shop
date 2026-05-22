<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全局中间件配置 (简化版)
 *
 * 请求 → Cors → Security → Locale → HashidsDecode → (JwtAuth) → HashidsEncode → 控制器
 */

return [
    app\middleware\Cors::class,
    app\middleware\SecurityMiddleware::class,
    app\middleware\LocaleMiddleware::class,
    app\middleware\HashidsDecode::class,
    app\middleware\HashidsEncode::class,
];
