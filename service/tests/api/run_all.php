<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * API 全接口自动化测试运行器：清库/清Redis → 种子 → 逐个执行测试文件
 *
 * 用法：php run_all.php [文件名字串过滤]
 * 退出码：0 = 全部通过，非 0 = 存在失败
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/seed.php';

$filter = $argv[1] ?? '';
$files = ['test_auth.php', 'test_catalog.php', 'test_user.php', 'test_trade.php'];

$exit = 0;
foreach ($files as $f) {
    if ($filter && !str_contains($f, $filter)) {
        continue;
    }
    resetDb();
    resetRedis();
    seedData();
    $GLOBALS['__results'] = [];
    $exit |= runFile($f);
}
exit($exit);
