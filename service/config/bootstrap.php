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
    // 注：数据库 Eloquent 初始化由 support/bootstrap.php 中 require support\Db 完成
    // （webman/database 2.x 在文件加载时执行 Initializer::init，不能放在此处：Db::start() 不存在）
];
