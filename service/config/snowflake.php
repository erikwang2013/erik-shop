<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Snowflake 分布式ID生成配置
 * 使用 erikwang2013/snowflake-php 包
 * service worker_id=1, admin worker_id=2
 */

return [
    // Worker节点ID（不同服务/进程使用不同ID，0-31）
    'worker_id' => (int)(getenv('SNOWFLAKE_WORKER_ID') ?: 1),

    // 数据中心ID（0-31）
    'datacenter_id' => (int)(getenv('SNOWFLAKE_DATACENTER_ID') ?: 1),

    // 起始时间戳（毫秒，不可修改，修改会导致ID重复）
    'start_timestamp' => 1700000000000,  // 约2023-11-15
];
