<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Admin 全局中间件配置
 * 请求 → Security → Platform → HashidsDecode → admin内置AccessControl → HashidsEncode → 控制器
 */

return [
    '' => [
        app\middleware\SecurityMiddleware::class,  // 安全攻击检测拦截
        app\middleware\PlatformMiddleware::class,  // 操作来源端识别
        app\middleware\HashidsDecode::class,       // 请求hashid→snowflake ID
        app\middleware\HashidsEncode::class,       // 响应snowflake ID→hashid
    ],
];
