<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 风控配置
 * 旁路模式：打分不阻塞流程，高分订单人工审核
 */

return [
    // 风控模式（旁路模式不阻塞下单）
    'mode' => 'bypass',                 // bypass/block（旁路/阻塞）

    // 风险分数阈值
    'high_threshold' => 80,             // ≥80分: 标记为人工审核
    'medium_threshold' => 50,           // ≥50分: 警告
    'low_threshold' => 20,              // ≥20分: 记录

    // 事件类型及默认权重
    'events' => [
        'user_register' => '用户注册',
        'user_login' => '用户登录',
        'order_create' => '下单',
        'payment_create' => '支付',
        'refund_request' => '退款申请',
    ],

    // 检测规则组
    'checks' => [
        'ip_reputation' => true,        // IP信誉检查
        'email_domain' => true,         // 邮箱域名检查
        'card_bin_check' => true,       // 信用卡BIN检查
        'velocity_check' => true,       // 频率检查
        'amount_anomaly' => true,       // 金额异常
        'address_mismatch' => true,     // 地址不匹配
        'device_fingerprint' => false,  // 设备指纹（需额外集成）
    ],

    // 频率限制
    'velocity' => [
        'order_per_hour' => 10,         // 每小时最大下单数
        'order_per_day' => 30,          // 每天最大下单数
        'payment_attempts' => 5,         // 同一订单支付尝试次数
        'register_per_ip_hour' => 3,    // 每小时同IP最大注册数
    ],
];
