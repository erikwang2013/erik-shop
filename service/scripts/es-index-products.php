#!/usr/bin/env php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * ES 商品索引器（一次性命令）
 *
 * 用法：php scripts/es-index-products.php
 * 依赖：env ELASTICSEARCH_HOST（兼容旧 ES_HOST/ES_PORT）；未配置时提示后退出（搜索自动降级 SQL）
 * 流程：创建 erik_shop_products 索引（多语言 settings + mappings）→ 全量批量导入商品
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../support/bootstrap.php';

use app\model\Products;
use Erikwang2013\WebmanScout\EngineManager;

$hosts = config('plugin.erikwang2013.webman-scout.app.elasticsearch.hosts', []);
if (empty($hosts)) {
    echo "[跳过] 未配置 ELASTICSEARCH_HOST，搜索将使用 SQL 降级\n";
    exit(0);
}

$index = (new Products())->searchableAs();
$engine = app(EngineManager::class)->engine();

try {
    $body = ['mappings' => Products::getSearchMapping()];
    $settings = config('scout.index', []);
    if ($settings) {
        $body['settings'] = $settings;
    }
    $engine->createIndex($index, ['body' => $body]);
    echo "[索引] {$index} 已就绪\n";

    $chunk = (int) config('scout.sync.chunk_size', 500) ?: 500;
    Products::makeAllSearchable($chunk);
    echo "[完成] 商品已写入 ES 索引 {$index}\n";
} catch (\Throwable $e) {
    echo "[失败] ES 连接或写入异常（搜索将自动降级 SQL）: " . $e->getMessage() . "\n";
    exit(1);
}
