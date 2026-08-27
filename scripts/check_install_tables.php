<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * install.sql 与 InstallController $tables_to_install 双源表清单一致性校验
 *
 * 背景：InstallController 硬编码待安装表清单（含 wa_ 系统表 + shop_ 业务表），
 * install.sql 只含 shop_ 业务表（wa_ 表由 webman-admin 插件自建）。
 * 新增/删除业务表时若只改一处，安装向导的冲突检测与导入就会漂移。
 *
 * 校验规则：
 *   1. install.sql 中的 shop_ 表必须全部出现在 InstallController 清单（防漏装）
 *   2. InstallController 清单中的 shop_ 表必须存在于 install.sql（防多余/改名残留）
 *   3. wa_ 表允许仅存在于清单（插件自建），不在对比范围
 *
 * 用法：php scripts/check_install_tables.php
 * 通过输出 OK；漂移输出差异并退出码 1（可接入 Makefile/CI）。
 */

$root = dirname(__DIR__);
$installSql = $root . '/install.sql';
$installController = $root . '/admin/plugin/admin/app/controller/InstallController.php';

$sqlTables = [];
preg_match_all('/CREATE TABLE `([^`]+)`/', file_get_contents($installSql), $m);
foreach ($m[1] as $t) {
    if (str_starts_with($t, 'shop_')) {
        $sqlTables[] = $t;
    }
}
$sqlTables = array_values(array_unique($sqlTables));
sort($sqlTables);

$ctrlContent = file_get_contents($installController);
preg_match('/\$tables_to_install = \[(.*?)\];/s', $ctrlContent, $cm);
preg_match_all("/'([a-z_0-9]+)'/", $cm[1] ?? '', $cm2);
$ctrlTables = array_values(array_unique($cm2[1]));
sort($ctrlTables);

$ctrlErik = array_values(array_filter($ctrlTables, fn($t) => str_starts_with($t, 'shop_')));

$onlySql = array_diff($sqlTables, $ctrlErik);     // install.sql 有但清单缺（漏装风险）
$onlyCtrl = array_diff($ctrlErik, $sqlTables);    // 清单有但 install.sql 无（多余/改名残留）

$failures = 0;
echo "install.sql shop_ 表: " . count($sqlTables) . "\n";
echo "InstallController shop_ 清单: " . count($ctrlErik) . "（另含 wa_ 系统表 " . count($ctrlTables) - count($ctrlErik) . " 张，插件自建不参与校验）\n";

if ($onlySql) {
    $failures++;
    echo "[FAIL] install.sql 存在但 InstallController 清单缺失（" . count($onlySql) . "）:\n  " . implode("\n  ", $onlySql) . "\n";
}
if ($onlyCtrl) {
    $failures++;
    echo "[FAIL] InstallController 清单存在但 install.sql 缺失（" . count($onlyCtrl) . "）:\n  " . implode("\n  ", $onlyCtrl) . "\n";
}

if ($failures === 0) {
    echo "OK：双源表清单一致\n";
    exit(0);
}
echo "共 {$failures} 类漂移，请同步 install.sql 与 InstallController \$tables_to_install\n";
exit(1);
