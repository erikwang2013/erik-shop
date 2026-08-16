<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 定时任务配置
 * 供 app/process/ 下各 Cron 进程使用；外部接口未配置时对应任务自动跳过并记录日志
 */

return [
    // 汇率更新源（{base} 为基准币种占位，默认 USD）
    'exchange_rate_source' => 'https://open.er-api.com/v6/latest/USD',

    // 物流轨迹查询 API 模板（{tracking_no} 占位符），需与 erik_logistics_companies.api_key 配合；env TRACKING_API_URL 可空
    'tracking_api_url' => getenv('TRACKING_API_URL') ?: '',

    // 合规规则外部源 URL（返回 [{country_id, compliance_category_id, rule, restriction_reason}]）；env COMPLIANCE_SOURCE_URL 可空
    'compliance_source_url' => getenv('COMPLIANCE_SOURCE_URL') ?: '',

    // 平台订单同步 API 模板（{account_id} 占位符），需与 erik_platform_accounts.api_key 配合；env PLATFORM_SYNC_URL 可空
    'platform_sync_url' => getenv('PLATFORM_SYNC_URL') ?: '',

    // 退货超时自动关闭天数
    'return_expire_days' => 7,

    // 推荐计算统计窗口（天）
    'recommendation_days' => 90,

    // 平台结算参数
    'platform_fee_rate' => 3.00,            // 平台佣金率（%）
    'payment_gateway_fee_rate' => 2.90,     // 支付手续费率（%）
    'payment_gateway_fee_fixed' => 0.30,    // 支付固定手续费（USD）
];
