<?php

require_once __DIR__ . '/../vendor/workerman/webman-framework/src/support/bootstrap.php';
// 初始化 Eloquent Capsule（webman/database 2.x 在文件加载时执行 Initializer::init）
require_once __DIR__ . '/../vendor/webman/database/src/support/Db.php';