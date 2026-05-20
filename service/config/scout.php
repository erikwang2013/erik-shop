<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Elasticsearch 搜索配置
 * 使用 erikwang2013/webman-scout 包
 * 用于商品多语言全文搜索、聚合筛选
 */

return [
    // Elasticsearch 连接
    'host' => getenv('ES_HOST') ?: '127.0.0.1',
    'port' => getenv('ES_PORT') ?: '9200',
    'scheme' => 'http',                  // http/https

    // 认证（可选）
    'username' => getenv('ES_USER') ?: null,
    'password' => getenv('ES_PASS') ?: null,

    // 索引前缀（所有索引统一前缀，方便管理）
    'prefix' => 'erik_shop_',

    // 搜索设置
    'search' => [
        'min_score' => 0.5,              // 最低相关性分数
        'fuzziness' => 'AUTO',           // 拼写容错
        'highlight' => [                  // 高亮设置
            'pre_tags' => ['<em class="highlight">'],
            'post_tags' => ['</em>'],
            'fields' => [
                'title' => ['fragment_size' => 60, 'number_of_fragments' => 1],
                'description' => ['fragment_size' => 100, 'number_of_fragments' => 2],
            ],
        ],
    ],

    // 索引设置
    'index' => [
        'number_of_shards' => 3,
        'number_of_replicas' => 1,
        'analysis' => [
            'analyzer' => [
                'multilingual' => [      // 多语言分析器（中/英/日/韩）
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase', 'asciifolding'],
                ],
            ],
        ],
    ],

    // 同步设置
    'sync' => [
        'chunk_size' => 500,             // 批量同步大小
        'queue' => false,                // 是否使用队列异步同步
    ],
];
