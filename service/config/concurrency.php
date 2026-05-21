<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 高并发优化配置
 */

return [
    // 数据库连接池 (高并发)
    'database_pool' => [
        'max_connections' => 50,       // 最大连接数
        'min_connections' => 10,       // 最小连接数
        'wait_timeout' => 2,           // 等待超时(秒)
        'idle_timeout' => 60,          // 空闲超时(秒)
        'heartbeat_interval' => 45,    // 心跳间隔(秒)
    ],

    // Redis连接池
    'redis_pool' => [
        'max_connections' => 30,
        'min_connections' => 5,
        'idle_timeout' => 60,
    ],

    // 响应缓存 (热点接口)
    'response_cache' => [
        'enabled' => true,
        'ttl' => [
            '/api/countries' => 3600,       // 国家数据: 1小时
            '/api/categories' => 1800,      // 分类: 30分钟
            '/api/settings' => 3600,        // 配置: 1小时
            '/api/faq' => 3600,             // FAQ: 1小时
            '/api/size-charts' => 3600,     // 尺码表: 1小时
            '/api/banners' => 300,          // 轮播图: 5分钟
            '/api/flash-sales' => 60,       // 秒杀: 1分钟
        ],
    ],

    // 限流规则
    'rate_limit' => [
        'enabled' => true,
        'default' => [60 => 100],           // 默认: 60秒100次
        '/api/auth/login' => [60 => 10],    // 登录: 60秒10次
        '/api/auth/register' => [300 => 5], // 注册: 300秒5次
        '/api/payment' => [60 => 5],        // 支付: 60秒5次
        '/api/orders' => [10 => 3],         // 下单: 10秒3次
        '/api/search' => [1 => 10],         // 搜索: 1秒10次
    ],

    // 异步队列 (慢操作)
    'queue' => [
        'email' => true,           // 邮件异步发送
        'feed_sync' => true,       // Feed同步异步
        'recommendation' => true,  // 推荐计算异步
        'export' => true,          // 大数据导出异步
        'log' => true,             // 操作日志批量写入
    ],

    // OPCache
    'opcache' => [
        'memory_consumption' => 256,     // 内存(MB)
        'interned_strings_buffer' => 16,
        'max_accelerated_files' => 20000,
        'revalidate_freq' => 60,        // 60秒检查一次
        'enable_cli' => true,           // CLI也启用
    ],

    // HTTP/2 + Keep-Alive
    'http' => [
        'keepalive_timeout' => 65,
        'max_keepalive_requests' => 1000,
    ],
];
