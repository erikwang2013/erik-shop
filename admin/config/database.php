<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Admin 数据库连接配置
 * 与 service 共享同一个 shop 数据库
 * 必须有 plugin.admin.mysql 连接（webman-admin 框架要求）
 */

return [
    'default' => 'mysql',

    'connections' => [

        // 主连接（与 service 相同的数据库）
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('DB_HOST') ?: '127.0.0.1',
            'port'        => getenv('DB_PORT') ?: '3306',
            'database'    => getenv('DB_NAME') ?: 'shop',
            'username'    => getenv('DB_USER') ?: 'root',
            'password'    => getenv('DB_PASS') ?: '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ],
            'pool' => [
                'max_connections' => 20,
                'min_connections' => 5,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],

        // webman-admin 框架要求的连接名
        'plugin.admin.mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('DB_HOST') ?: '127.0.0.1',
            'port'        => getenv('DB_PORT') ?: '3306',
            'database'    => getenv('DB_NAME') ?: 'shop',
            'username'    => getenv('DB_USER') ?: 'root',
            'password'    => getenv('DB_PASS') ?: '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ],
            'pool' => [
                'max_connections' => 20,
                'min_connections' => 5,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
