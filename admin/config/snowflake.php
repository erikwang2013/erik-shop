<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * Admin Snowflake 配置
 * worker_id=2 区分于 service worker_id=1
 */

return [
    'worker_id' => (int)(getenv('SNOWFLAKE_WORKER_ID') ?: 2),
    'datacenter_id' => (int)(getenv('SNOWFLAKE_DATACENTER_ID') ?: 1),
    'start_timestamp' => 1700000000000,
];
