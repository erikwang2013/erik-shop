<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * JWT 认证配置
 * 使用 erikwang2013/jwt-webman 包
 */

return [
    // 签名密钥（生产环境通过环境变量设置）
    'secret' => getenv('JWT_SECRET') ?: 'your-256-bit-secret-key-change-in-production',

    // 加密算法
    'algorithm' => 'HS256',              // HS256/HS384/HS512/RS256

    // Access Token 有效期（秒）
    'access_ttl' => 7200,               // 2小时

    // Refresh Token 有效期（秒）
    'refresh_ttl' => 1209600,           // 14天

    // Token 签发者
    'issuer' => 'erik.xyz',

    // Token 接收者
    'audience' => 'erik-shop',

    // 允许的时钟偏差（秒）
    'leeway' => 60,

    // 刷新Token后是否使旧Token失效
    'blacklist_enabled' => true,        // 启用黑名单

    // 黑名单存储（redis/file）
    'blacklist_storage' => 'redis',
];
