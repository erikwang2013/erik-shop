<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * MySQL 数据库连接配置
 * 支持读写分离 (高并发)
 */

return [
    'default' => 'mysql',

    'connections' => [

        // 主连接 (单机模式)
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('DB_HOST') ?: '127.0.0.1',
            'port'        => getenv('DB_PORT') ?: '3306',
            'database'    => getenv('DB_NAME') ?: 'erik_shop',
            'username'    => getenv('DB_USER') ?: 'root',
            'password'    => getenv('DB_PASS') ?: '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
            'pool' => [
                'max_connections' => 50,
                'min_connections' => 10,
                'wait_timeout' => 2,
                'idle_timeout' => 60,
                'heartbeat_interval' => 45,
            ],
        ],

        // 读写分离连接 (高并发: 写主库 + 读副本)
        'mysql_rw' => [
            'driver'      => 'mysql',
            'read'        => [
                'host' => [
                    getenv('DB_READ_HOST_1') ?: getenv('DB_HOST') ?: '127.0.0.1',
                    getenv('DB_READ_HOST_2') ?: getenv('DB_HOST') ?: '127.0.0.1',
                ],
            ],
            'write'       => [
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
            ],
            'port'        => getenv('DB_PORT') ?: '3306',
            'database'    => getenv('DB_NAME') ?: 'erik_shop',
            'username'    => getenv('DB_USER') ?: 'root',
            'password'    => getenv('DB_PASS') ?: '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
            'pool' => [
                'max_connections' => 30,
                'min_connections' => 5,
                'wait_timeout' => 2,
                'idle_timeout' => 60,
                'heartbeat_interval' => 45,
            ],
            'sticky' => true, // 同一请求内写后读走主库
        ],
    ],
];
