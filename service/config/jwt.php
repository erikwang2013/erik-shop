<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * JWT 认证配置
 * 使用 erikwang2013/jwt-webman 包（ErikJwt\JWT）
 */

return [
    // 签名密钥（生产环境必须通过 JWT_SECRET 环境变量设置，为空时 Jwt 类会拒绝启动）
    'secret_key' => getenv('JWT_SECRET') ?: '',

    // 加密算法
    'algorithm' => 'HS256',

    // 默认过期时间（秒）- access token
    'default_expire' => 7200,

    // 刷新token过期时间（秒）
    'refresh_expire' => 1209600,

    // Token 签发者
    'issuer' => 'erik.xyz',

    // Token 接收者
    'audience' => 'erik-shop',

    // 允许时钟偏差（秒）
    'leeway' => 60,
];
