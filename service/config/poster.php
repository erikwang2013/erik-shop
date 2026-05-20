<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Poster 随机验证配置
 * 用于敏感操作的人机验证（注册、下单、支付等）
 * 使用 erikwang2013/poster-php 包
 * 支持：滑块拼图、文字点击、图形旋转
 */

return [
    // 验证类型（随机选择一种）
    'types' => ['slide', 'click', 'rotate'],  // 滑块/点击/旋转

    // 验证有效期（秒）
    'expire' => 300,                         // 5分钟

    // 最大重试次数
    'max_attempts' => 3,

    // 验证结果存储（redis/file）
    'storage' => 'redis',

    // 需要验证的路由前缀
    'protected_routes' => [
        '/api/auth/register',
        '/api/orders',
        '/api/payment/create',
        '/api/refunds',
    ],
];
