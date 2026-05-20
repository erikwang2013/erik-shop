<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * MySQL 数据库连接配置
 * 表前缀 erik_ 用于业务表，wa_ 用于 webman-admin 系统表
 */

return [
    // 默认连接
    'default' => 'mysql',

    // 连接池配置
    'connections' => [

        // 主数据库连接
        'mysql' => [
            'driver'      => 'mysql',           // 数据库驱动
            'host'        => getenv('DB_HOST') ?: '127.0.0.1',  // 数据库地址
            'port'        => getenv('DB_PORT') ?: '3306',        // 端口
            'database'    => getenv('DB_NAME') ?: 'erik_shop',   // 数据库名
            'username'    => getenv('DB_USER') ?: 'root',        // 用户名
            'password'    => getenv('DB_PASS') ?: '',            // 密码
            'charset'     => 'utf8mb4',          // 字符集（支持emoji+多语言）
            'collation'   => 'utf8mb4_unicode_ci', // 排序规则
            'prefix'      => '',                 // 表前缀在模型中手动指定 erik_
            'strict'      => true,               // 严格模式
            'engine'      => null,               // 存储引擎（默认InnoDB）
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false, // 必须false，用于Swoole/Swow
                PDO::ATTR_PERSISTENT => false,        // 不持久连接
            ],

            // 连接池（webman常驻内存复用连接）
            'pool' => [
                'max_connections' => 20,         // 最大连接数
                'min_connections' => 5,          // 最小连接数
                'wait_timeout' => 3,             // 等待超时（秒）
                'idle_timeout' => 60,            // 空闲超时（秒）
                'heartbeat_interval' => 50,      // 心跳间隔（秒）
            ],
        ],
    ],
];
