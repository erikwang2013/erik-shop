<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 隐私合规配置
 * GDPR（欧盟）/ CCPA（加州）/ PIPL（中国）合规
 */

return [
    // Cookie Consent 配置
    'cookie_consent' => [
        'enabled' => true,
        'version' => '1.0',             // Cookie政策版本（更新后需重新征求）
        'categories' => [
            'necessary' => '必要Cookie（会话、安全）',
            'analytics' => '分析Cookie（GA/统计）',
            'marketing' => '营销Cookie（广告追踪）',
        ],
    ],

    // 数据保留期限（天）
    'data_retention' => [
        'user_inactive' => 730,         // 非活跃用户数据保留2年
        'order_history' => 3650,        // 订单记录保留10年（税务要求）
        'analytics_logs' => 180,        // 分析日志6个月
        'deleted_user_grace' => 30,     // 删除请求宽限期30天
    ],

    // 数据删除时保留的字段（税务审计）
    'retain_on_deletion' => [
        'orders.order_no',
        'orders.total_amount',
        'orders.currency_code',
        'orders.created_at',
        'orders.paid_at',
        'payments.transaction_no',
        'payments.amount',
    ],
];
