<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 跨境运营配置
 * 默认市场、禁售国家、默认币种、GeoIP语言映射
 */

return [
    // 运营市场（平台可销售的目标国家/地区）
    'markets' => [
        'US', 'CA', 'GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'SE', 'DK',
        'JP', 'KR', 'AU', 'NZ', 'HK', 'TW', 'SG', 'MY', 'TH', 'PH', 'ID',
    ],

    // 默认目标市场（用户未选择时使用）
    'default' => [
        'country' => 'US',              // 默认国家
        'currency' => 'USD',            // 默认币种
        'language' => 'en',             // 默认语言
    ],

    // 禁售国家（商品不可配送到这些国家）
    'blocked_countries' => [
        // 示例：'CU', 'IR', 'KP', 'SY',  -- 根据合规要求配置
    ],

    // 需要KYC实名认证的市场
    'kyc_required_countries' => [
        'KR',                           // 韩国
    ],

    // GeoIP 区域→语言映射
    'language_map' => [
        'US' => 'en', 'GB' => 'en', 'CA' => 'en', 'AU' => 'en', 'NZ' => 'en',
        'DE' => 'de', 'FR' => 'fr', 'IT' => 'it', 'ES' => 'es',
        'JP' => 'ja', 'KR' => 'ko',
        'CN' => 'zh_CN', 'HK' => 'zh_HK', 'TW' => 'zh_HK',
    ],

    // 价格展示规则（由shop_countries表的price_display_mode覆盖）
    // 'tax_inclusive': 含税价（欧盟），'tax_exclusive': 不含税（美国），'both': 并列（日本）
];
