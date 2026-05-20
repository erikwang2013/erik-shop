<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Redis 连接配置
 * 用于缓存、Session、队列、分布式锁、风控计数、API限流
 */

return [
    'default' => [
        'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',  // Redis地址
        'port'     => getenv('REDIS_PORT') ?: '6379',        // 端口
        'password' => getenv('REDIS_PASS') ?: null,          // 密码
        'database' => 0,          // 默认db: 缓存
        'prefix'   => 'erik:',    // key前缀
    ],

    // Session专用（可与default共用或分离）
    'session' => [
        'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port'     => getenv('REDIS_PORT') ?: '6379',
        'password' => getenv('REDIS_PASS') ?: null,
        'database' => 1,          // db1: Session
        'prefix'   => 'erik_session:',
    ],

    // 队列专用
    'queue' => [
        'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port'     => getenv('REDIS_PORT') ?: '6379',
        'password' => getenv('REDIS_PASS') ?: null,
        'database' => 2,          // db2: 队列
        'prefix'   => 'erik_queue:',
    ],

    // 限流计数器
    'rate_limit' => [
        'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port'     => getenv('REDIS_PORT') ?: '6379',
        'password' => getenv('REDIS_PASS') ?: null,
        'database' => 3,          // db3: 限流
        'prefix'   => 'erik_ratelimit:',
    ],
];
