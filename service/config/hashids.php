<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * Hashids 配置 - 接口层ID加解密
 * 使用 erikwang2013/hashids 包
 */

return [

    'default' => 'main',

    'connections' => [

        // 主连接 - 用于API请求/响应ID编解码
        'main' => [
            // salt 为空时 HashidsHelper 会拒绝使用，生产环境必须通过 HASHIDS_SALT 设置
            'salt' => getenv('HASHIDS_SALT') ?: '',
            'length' => 8,
        ],

    ],

];
