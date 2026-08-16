<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 支付网关配置
 * 支持 Stripe / PayPal / Klarna / Adyen 等
 */

return [
    // 默认支付网关
    'default' => getenv('PAYMENT_DEFAULT_GATEWAY') ?: 'stripe',

    // 支付失败重试
    'retry' => [
        'max_attempts' => 3,
        'delay_minutes' => 10,
    ],

    // Webhook 验签 secret（从网关后台获取）
    'webhook' => [
        'stripe' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
        'paypal' => getenv('PAYPAL_WEBHOOK_SECRET') ?: '',
        'klarna' => getenv('KLARNA_WEBHOOK_SECRET') ?: '',
    ],

    // Stripe 配置
    'stripe' => [
        'secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
        'public_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
        'mode' => getenv('STRIPE_MODE') ?: 'sandbox',  // sandbox/live
    ],

    // PayPal 配置
    'paypal' => [
        'client_id' => getenv('PAYPAL_CLIENT_ID') ?: '',
        'client_secret' => getenv('PAYPAL_CLIENT_SECRET') ?: '',
        'webhook_id' => getenv('PAYPAL_WEBHOOK_ID') ?: '',  // 官方 verify-webhook-signature 验签必需
        'mode' => getenv('PAYPAL_MODE') ?: 'sandbox',   // sandbox/live
    ],

    // Klarna (BNPL 先买后付)
    'klarna' => [
        'username' => getenv('KLARNA_USERNAME') ?: '',
        'password' => getenv('KLARNA_PASSWORD') ?: '',
        'region' => getenv('KLARNA_REGION') ?: 'europe', // europe/north_america/asia_pacific
        'mode' => getenv('KLARNA_MODE') ?: 'sandbox',
    ],

    // Adyen
    'adyen' => [
        'api_key' => getenv('ADYEN_API_KEY') ?: '',
        'hmac_key' => getenv('ADYEN_HMAC_KEY') ?: '',  // Webhook 验签 HMAC 密钥
        'merchant_account' => getenv('ADYEN_MERCHANT_ACCOUNT') ?: '',
        'mode' => getenv('ADYEN_MODE') ?: 'sandbox',   // sandbox/live
    ],

    // 支付手续费率配置（用于分账计算）
    'gateway_fee' => [
        'stripe' => ['rate' => 2.9, 'fixed' => 0.30],   // 2.9% + $0.30
        'paypal' => ['rate' => 3.49, 'fixed' => 0.49],  // 3.49% + $0.49
        'klarna' => ['rate' => 2.99, 'fixed' => 0.30],
        'adyen' => ['rate' => 2.99, 'fixed' => 0.30],   // 与 KlarnaGateway 同档欧洲收单费率档位
    ],

    // 平台佣金率（用于分账）
    'platform_rate' => 5.0,             // 5%
];
