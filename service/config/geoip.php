<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * GeoIP2 配置
 * 使用 maxmind-db/reader 读取 MaxMind GeoLite2 数据库
 * 用于自动识别用户区域、语言、币种
 */

return [
    // MaxMind GeoLite2 Country 数据库路径
    'database_path' => base_path() . '/database/geoip/GeoLite2-Country.mmdb',

    // 数据库自动更新（需要设置license key）
    'auto_update' => false,

    // MaxMind License Key（用于自动下载更新）
    'license_key' => getenv('MAXMIND_LICENSE_KEY') ?: '',

    // 缓存时间（秒）
    'cache_ttl' => 86400,               // 24小时

    // 内网IP/开发环境的默认区域
    'default' => [
        'country_iso_code' => 'US',
        'country_name' => 'United States',
        'currency' => 'USD',
        'language' => 'en',
    ],
];
