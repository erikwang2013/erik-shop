<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 控制器冒烟检查（防复发门禁）
 *
 * 背景：ShopOrderController / ShopPaymentController 曾因方法签名与父类 Crud 不兼容，
 * 在 PHP 8.3 下类加载即 Fatal error（订单/支付管理菜单一开即崩）。
 *
 * 本脚本执行两层检查：
 *   1. php -l 语法检查 admin 插件全部 PHP 文件
 *   2. 反射加载 admin 全部控制器（捕获签名不兼容等类加载期错误）
 *
 * 用法：
 *   php scripts/smoke_controllers.php
 *   任意检查失败以非 0 退出码退出（可接入 Makefile / CI 门禁）。
 */

$adminDir = __DIR__ . '/../admin';
$autoload = $adminDir . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "FAIL: 未找到 {$autoload}，请先执行 cd admin && composer install\n");
    exit(1);
}
require $autoload;

$failures = 0;

// ---- 1. php -l 语法检查 ----
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($adminDir . '/plugin', FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $failures++;
        echo "SYNTAX FAIL: {$file->getPathname()}\n";
        echo implode("\n", $out) . "\n";
    }
    unset($out);
}

// ---- 2. 反射加载全部控制器（捕获签名不兼容等类加载期错误） ----
$ctrlDir = $adminDir . '/plugin/admin/app/controller';
$it2 = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($ctrlDir, FilesystemIterator::SKIP_DOTS)
);
$total = 0;
foreach ($it2 as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $class = 'plugin\admin\app\controller\\' .
        str_replace('/', '\\', substr($file->getPathname(), strlen($ctrlDir) + 1, -4));
    $total++;
    try {
        new ReflectionClass($class);
    } catch (\Throwable $e) {
        $failures++;
        echo "LOAD FAIL: {$class} => " . $e->getMessage() . "\n";
    }
}

echo "Smoke check: {$total} controllers loaded, " . ($failures === 0 ? 'ALL PASS' : "{$failures} FAILURES") . "\n";
exit($failures === 0 ? 0 : 1);
