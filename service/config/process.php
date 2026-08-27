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

    // ---- 定时任务进程（各一个常驻进程，按自身周期循环执行）----

    // 汇率更新（每小时）
    'exchange_rate_cron' => [
        'handler' => app\process\ExchangeRateCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 物流轨迹拉取（每30分钟）
    'shipment_tracking_cron' => [
        'handler' => app\process\ShipmentTrackingCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 商品Feed同步（默认每60分钟，config/feed.php 配置）
    'product_feed_cron' => [
        'handler' => app\process\ProductFeedCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 推荐计算（每日）
    'recommendation_cron' => [
        'handler' => app\process\RecommendationCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 合规规则更新（每日）
    'compliance_cron' => [
        'handler' => app\process\ComplianceCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 退货超时关闭（每小时）
    'return_expire_cron' => [
        'handler' => app\process\ReturnExpireCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 降价/到货通知（每10分钟）
    'price_alert_cron' => [
        'handler' => app\process\PriceAlertCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 支付对账（每6小时）
    'payment_reconcile_cron' => [
        'handler' => app\process\PaymentReconcileCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 分账结算（每日）
    'settlement_cron' => [
        'handler' => app\process\SettlementCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 多平台订单同步（每5分钟）
    'platform_order_sync_cron' => [
        'handler' => app\process\PlatformOrderSyncCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 隐私合规执行（每小时：数据删除宽限期/导出/opt-out）
    'privacy_compliance_cron' => [
        'handler' => app\process\PrivacyComplianceTask::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 订阅周期购自动续费（每日）
    'subscription_cron' => [
        'handler' => app\process\SubscriptionCron::class,
        'count' => 1,
        'reloadable' => false,
    ],

    // 客服实时 IM WebSocket（客户端以 ?token=JWT&session_id=xxx 连接）
    'chat_ws' => [
        'handler' => app\process\ChatWs::class,
        // 默认 8788；与 admin（8788）同机共存时设 CHAT_WS_PORT 错开
        'listen' => 'websocket://0.0.0.0:' . (getenv('CHAT_WS_PORT') ?: '8788'),
        'count' => 1,
        'reloadable' => false,
    ],
];
