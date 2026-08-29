<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * CDN 预热 CLI（阿里云/腾讯云支持；Cloudflare/CloudFront 不支持预热会记日志失败）
 *
 * 用法：
 *   php scripts/cdn_preload.php /app/admin/upload/img/20260829/a.jpg /app/admin/upload/img/20260829/b.jpg
 *   php scripts/cdn_preload.php --file list.txt      # 每行一个相对路径或 URL
 *   php scripts/cdn_preload.php --provider tencent /a.png
 *
 * 退出码：0=全部成功，1=部分/全部失败
 */

require_once __DIR__ . '/../vendor/autoload.php';

\Webman\Config::load(__DIR__ . '/../config', ['route', 'container']);

$args = $_SERVER['argv'] ?? [];
array_shift($args);

$provider = null;
$urls = [];
for ($i = 0; $i < count($args); $i++) {
    if ($args[$i] === '--file') {
        $file = $args[++$i] ?? '';
        if ($file === '' || !is_file($file)) {
            fwrite(STDERR, "文件不存在: {$file}\n");
            exit(2);
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $urls[] = trim($line);
        }
    } elseif ($args[$i] === '--provider') {
        $provider = $args[++$i] ?? null;
    } else {
        $urls[] = $args[$i];
    }
}

$urls = array_values(array_filter($urls));
if ($urls === []) {
    fwrite(STDERR, "用法: php scripts/cdn_preload.php <url1> [url2...] [--file list.txt] [--provider cloudflare|cloudfront|aliyun|tencent]\n");
    exit(2);
}

try {
    app\common\Cdn::preload($urls, $provider);
    echo "已提交预热 " . count($urls) . " 个 URL（提供商: " . ($provider ?: (string) config('cdn.default', 'cloudflare')) . "）\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '预热失败: ' . $e->getMessage() . "\n");
    exit(1);
}
