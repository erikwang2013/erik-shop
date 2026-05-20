<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 国家与货币种子数据
 * 通过 Snowflake ID 生成主键
 * 数据来源: erikwang2013/season 包
 */

return [
    'countries' => [
        // 北美
        ['name_en' => 'United States', 'name_cn' => '美国', 'iso_code_2' => 'US', 'iso_code_3' => 'USA', 'phone_code' => '1', 'currency_code' => 'USD', 'flag_emoji' => '🇺🇸', 'timezone' => 'America/New_York', 'price_display_mode' => 'tax_exclusive', 'kyc_required' => 0, 'sort' => 10],
        ['name_en' => 'Canada', 'name_cn' => '加拿大', 'iso_code_2' => 'CA', 'iso_code_3' => 'CAN', 'phone_code' => '1', 'currency_code' => 'CAD', 'flag_emoji' => '🇨🇦', 'timezone' => 'America/Toronto', 'price_display_mode' => 'tax_exclusive', 'kyc_required' => 0, 'sort' => 20],
        ['name_en' => 'Mexico', 'name_cn' => '墨西哥', 'iso_code_2' => 'MX', 'iso_code_3' => 'MEX', 'phone_code' => '52', 'currency_code' => 'MXN', 'flag_emoji' => '🇲🇽', 'timezone' => 'America/Mexico_City', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 30],

        // 欧洲
        ['name_en' => 'United Kingdom', 'name_cn' => '英国', 'iso_code_2' => 'GB', 'iso_code_3' => 'GBR', 'phone_code' => '44', 'currency_code' => 'GBP', 'flag_emoji' => '🇬🇧', 'timezone' => 'Europe/London', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 40],
        ['name_en' => 'Germany', 'name_cn' => '德国', 'iso_code_2' => 'DE', 'iso_code_3' => 'DEU', 'phone_code' => '49', 'currency_code' => 'EUR', 'flag_emoji' => '🇩🇪', 'timezone' => 'Europe/Berlin', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 50],
        ['name_en' => 'France', 'name_cn' => '法国', 'iso_code_2' => 'FR', 'iso_code_3' => 'FRA', 'phone_code' => '33', 'currency_code' => 'EUR', 'flag_emoji' => '🇫🇷', 'timezone' => 'Europe/Paris', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 60],
        ['name_en' => 'Italy', 'name_cn' => '意大利', 'iso_code_2' => 'IT', 'iso_code_3' => 'ITA', 'phone_code' => '39', 'currency_code' => 'EUR', 'flag_emoji' => '🇮🇹', 'timezone' => 'Europe/Rome', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 70],
        ['name_en' => 'Spain', 'name_cn' => '西班牙', 'iso_code_2' => 'ES', 'iso_code_3' => 'ESP', 'phone_code' => '34', 'currency_code' => 'EUR', 'flag_emoji' => '🇪🇸', 'timezone' => 'Europe/Madrid', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 80],
        ['name_en' => 'Netherlands', 'name_cn' => '荷兰', 'iso_code_2' => 'NL', 'iso_code_3' => 'NLD', 'phone_code' => '31', 'currency_code' => 'EUR', 'flag_emoji' => '🇳🇱', 'timezone' => 'Europe/Amsterdam', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 90],
        ['name_en' => 'Belgium', 'name_cn' => '比利时', 'iso_code_2' => 'BE', 'iso_code_3' => 'BEL', 'phone_code' => '32', 'currency_code' => 'EUR', 'flag_emoji' => '🇧🇪', 'timezone' => 'Europe/Brussels', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 100],
        ['name_en' => 'Sweden', 'name_cn' => '瑞典', 'iso_code_2' => 'SE', 'iso_code_3' => 'SWE', 'phone_code' => '46', 'currency_code' => 'SEK', 'flag_emoji' => '🇸🇪', 'timezone' => 'Europe/Stockholm', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 110],
        ['name_en' => 'Denmark', 'name_cn' => '丹麦', 'iso_code_2' => 'DK', 'iso_code_3' => 'DNK', 'phone_code' => '45', 'currency_code' => 'DKK', 'flag_emoji' => '🇩🇰', 'timezone' => 'Europe/Copenhagen', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 120],

        // 亚太
        ['name_en' => 'Japan', 'name_cn' => '日本', 'iso_code_2' => 'JP', 'iso_code_3' => 'JPN', 'phone_code' => '81', 'currency_code' => 'JPY', 'flag_emoji' => '🇯🇵', 'timezone' => 'Asia/Tokyo', 'price_display_mode' => 'both', 'kyc_required' => 0, 'sort' => 130],
        ['name_en' => 'South Korea', 'name_cn' => '韩国', 'iso_code_2' => 'KR', 'iso_code_3' => 'KOR', 'phone_code' => '82', 'currency_code' => 'KRW', 'flag_emoji' => '🇰🇷', 'timezone' => 'Asia/Seoul', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 1, 'sort' => 140],
        ['name_en' => 'Australia', 'name_cn' => '澳大利亚', 'iso_code_2' => 'AU', 'iso_code_3' => 'AUS', 'phone_code' => '61', 'currency_code' => 'AUD', 'flag_emoji' => '🇦🇺', 'timezone' => 'Australia/Sydney', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 150],
        ['name_en' => 'New Zealand', 'name_cn' => '新西兰', 'iso_code_2' => 'NZ', 'iso_code_3' => 'NZL', 'phone_code' => '64', 'currency_code' => 'NZD', 'flag_emoji' => '🇳🇿', 'timezone' => 'Pacific/Auckland', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 160],
        ['name_en' => 'Hong Kong', 'name_cn' => '香港', 'iso_code_2' => 'HK', 'iso_code_3' => 'HKG', 'phone_code' => '852', 'currency_code' => 'HKD', 'flag_emoji' => '🇭🇰', 'timezone' => 'Asia/Hong_Kong', 'price_display_mode' => 'tax_exclusive', 'kyc_required' => 0, 'sort' => 170],
        ['name_en' => 'Taiwan', 'name_cn' => '台湾', 'iso_code_2' => 'TW', 'iso_code_3' => 'TWN', 'phone_code' => '886', 'currency_code' => 'TWD', 'flag_emoji' => '🇹🇼', 'timezone' => 'Asia/Taipei', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 180],
        ['name_en' => 'Singapore', 'name_cn' => '新加坡', 'iso_code_2' => 'SG', 'iso_code_3' => 'SGP', 'phone_code' => '65', 'currency_code' => 'SGD', 'flag_emoji' => '🇸🇬', 'timezone' => 'Asia/Singapore', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 190],

        // 东南亚
        ['name_en' => 'Malaysia', 'name_cn' => '马来西亚', 'iso_code_2' => 'MY', 'iso_code_3' => 'MYS', 'phone_code' => '60', 'currency_code' => 'MYR', 'flag_emoji' => '🇲🇾', 'timezone' => 'Asia/Kuala_Lumpur', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 200],
        ['name_en' => 'Thailand', 'name_cn' => '泰国', 'iso_code_2' => 'TH', 'iso_code_3' => 'THA', 'phone_code' => '66', 'currency_code' => 'THB', 'flag_emoji' => '🇹🇭', 'timezone' => 'Asia/Bangkok', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 210],
        ['name_en' => 'Philippines', 'name_cn' => '菲律宾', 'iso_code_2' => 'PH', 'iso_code_3' => 'PHL', 'phone_code' => '63', 'currency_code' => 'PHP', 'flag_emoji' => '🇵🇭', 'timezone' => 'Asia/Manila', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 220],
        ['name_en' => 'Indonesia', 'name_cn' => '印尼', 'iso_code_2' => 'ID', 'iso_code_3' => 'IDN', 'phone_code' => '62', 'currency_code' => 'IDR', 'flag_emoji' => '🇮🇩', 'timezone' => 'Asia/Jakarta', 'price_display_mode' => 'tax_inclusive', 'kyc_required' => 0, 'sort' => 230],
    ],

    'currencies' => [
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_default' => 1],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_default' => 0],
        ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'is_default' => 0],
        ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'is_default' => 0],
        ['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩', 'is_default' => 0],
        ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'is_default' => 0],
        ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'CA$', 'is_default' => 0],
        ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'AU$', 'is_default' => 0],
        ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'is_default' => 0],
        ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'is_default' => 0],
        ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'SG$', 'is_default' => 0],
        ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => 'MX$', 'is_default' => 0],
        ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr', 'is_default' => 0],
        ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr', 'is_default' => 0],
        ['code' => 'TWD', 'name' => 'New Taiwan Dollar', 'symbol' => 'NT$', 'is_default' => 0],
        ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿', 'is_default' => 0],
        ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'is_default' => 0],
        ['code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => '₱', 'is_default' => 0],
        ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'is_default' => 0],
    ],
];
