<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * redis-queue 队列连接配置
 * 对齐 config/redis.php 的 queue 连接：db2 队列专用，prefix erik_queue:
 */

return [
    'default' => [
        'host' => 'redis://' . (getenv('REDIS_HOST') ?: '127.0.0.1') . ':' . (getenv('REDIS_PORT') ?: '6379'),
        'options' => [
            'auth' => getenv('REDIS_PASS') ?: null,
            'db' => 2,                   // db2: 队列（与 config/redis.php queue 一致）
            'prefix' => 'erik_queue:',   // 队列 key 前缀
            'max_attempts'  => 5,        // 消费失败最大重试次数
            'retry_seconds' => 5,        // 重试间隔秒数
        ],
        // Connection pool, supports only Swoole or Swow drivers.
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ]
    ],
];
