<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 进程配置
 * webman HTTP服务 + Snowflake ID生成 + 文件监控
 */

use support\Log;
use support\Request;
use app\process\Http;

global $argv;

return [
    // HTTP服务主进程
    'webman' => [
        'handler' => Http::class,
        'listen' => 'http://0.0.0.0:8787',     // 监听地址
        'count' => cpu_count() * 4,             // 进程数（CPU核数*4）
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path(),
        ],
        // Worker启动回调：初始化Snowflake
        'onWorkerStart' => [app\process\SnowflakeWorker::class, 'onWorkerStart'],
    ],

    // 文件变更监控（开发环境自动重载）
    'monitor' => [
        'handler' => app\process\Monitor::class,
        'reloadable' => false,
        'constructor' => [
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app') ?: [],
              glob(base_path() . '/plugin/*/config') ?: [],
              glob(base_path() . '/plugin/*/api') ?: []),
            'monitorExtensions' => ['php', 'html', 'htm', 'env'],
            'options' => [
                'enable_file_monitor' => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ],
        ],
    ],
];
