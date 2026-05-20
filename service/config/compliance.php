<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 商品合规配置
 * 按品类和目的国校验商品合规性
 * 配合 erik_compliance_categories + erik_country_compliance_rules 表使用
 */

return [
    // 合规分类代码列表（与数据库对应）
    'categories' => [
        'FDA' => '食品/药品接触材料（美国FDA）',
        'CE' => 'CE标志（欧盟）',
        'RoHS' => '有害物质限制（欧盟RoHS）',
        'FCM' => '食品接触材料（欧盟/日本）',
        'COSMETIC' => '化妆品注册',
        'TOY' => '玩具安全指令（EN71/CPSC）',
        'ELECTRONIC' => '电子电器（FCC/WEEE/EMC）',
        'CHILDREN' => '儿童用品（CPSIA/REACH）',
        'TEXTILE' => '纺织品（OEKO-TEX/REACH）',
        'BATTERY' => '电池（UN38.3/MSDS/锂电池标识）',
    ],

    // 默认管控级别
    'default_rule' => 'allowed',        // 未配置规则的品类默认允许

    // 合规检查缓存时间（秒）
    'cache_ttl' => 3600,                // 1小时

    // 合规文件上传限制
    'file' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['pdf', 'jpg', 'png', 'docx'],
    ],
];
