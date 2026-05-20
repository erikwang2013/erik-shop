<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 商品 Feed 配置
 * 用于生成 Google Merchant Center / Meta Commerce 商品同步文件
 */

return [
    // Google Merchant Center
    'google' => [
        'enabled' => false,
        'feed_url' => getenv('APP_URL') . '/feed/google.xml',
        'title' => 'Erik Shop Products',
        'description' => 'Cross-Border E-Commerce',
        'default_google_category' => '100', // 默认Google商品分类ID
    ],

    // Meta / Facebook Commerce
    'meta' => [
        'enabled' => false,
        'feed_url' => getenv('APP_URL') . '/feed/meta.xml',
    ],

    // Feed 文件存储路径
    'output_path' => base_path() . '/public/feed/',

    // 定时更新间隔（分钟）
    'sync_interval' => 60,              // 每小时

    // 每次同步最大商品数
    'batch_size' => 500,
];
