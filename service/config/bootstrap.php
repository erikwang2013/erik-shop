<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 引导类配置
 * Worker 启动时按顺序执行引导类的 onWorkerStart 方法
 */

return [
    support\bootstrap\Session::class,    // Session初始化
    support\bootstrap\Db::class,         // 数据库连接池
    support\bootstrap\Redis::class,      // Redis连接池
];
