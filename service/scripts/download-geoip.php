#!/usr/bin/env php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * GeoLite2-Country 数据库下载脚本（一次性命令）
 *
 * 用法：MAXMIND_LICENSE_KEY=xxxx php scripts/download-geoip.php
 * 流程：从 MaxMind 官方接口下载 GeoLite2-Country tar.gz → 解压 .mmdb
 *       落位 database/geoip/GeoLite2-Country.mmdb（config/geoip.php 指向该路径）
 * 失败时输出手动放置指引。
 *
 * 免费 License Key 申请：https://www.maxmind.com/en/geolite2/signup
 */

$key = getenv('MAXMIND_LICENSE_KEY') ?: '';

if ($key === '') {
    fwrite(STDERR, <<<EOT
[错误] 未设置 MAXMIND_LICENSE_KEY 环境变量。

手动放置指引：
  1. 到 https://www.maxmind.com/en/geolite2/signup 注册并获取免费 License Key
  2. 执行：MAXMIND_LICENSE_KEY=<你的key> php scripts/download-geoip.php
  3. 或手动下载 GeoLite2-Country（tar.gz）并解压，
     将 GeoLite2-Country.mmdb 放到：database/geoip/GeoLite2-Country.mmdb
     （目录不存在则自行创建 database/geoip/）
未放置数据库时，GeoIP 相关功能将使用 config/geoip.php 中的默认区域（US）。

EOT);
    exit(1);
}

$targetDir = __DIR__ . '/../database/geoip';
$targetFile = $targetDir . '/GeoLite2-Country.mmdb';
$url = 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key='
    . urlencode($key) . '&suffix=tar.gz';

$tmpTar = tempnam(sys_get_temp_dir(), 'geolite2_');

echo "[下载] $url\n";
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_FILE => fopen($tmpTar, 'wb'),
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => ['timeout' => 120]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        $status = 0;
    } else {
        $status = 200;
        file_put_contents($tmpTar, $body);
    }
}

if ($status !== 200) {
    fwrite(STDERR, "[错误] 下载失败（HTTP $status）。License Key 无效或网络不可达。\n");
    unlink($tmpTar);
    exit(1);
}

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$mmdbFound = false;
$extractDir = sys_get_temp_dir() . '/geolite2_extract';
try {
    $phar = new PharData($tmpTar);
    $phar->extractTo($extractDir, null, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getFilename() === 'GeoLite2-Country.mmdb') {
            rename($file->getPathname(), $targetFile);
            $mmdbFound = true;
            break;
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "[错误] tar.gz 解压失败：" . $e->getMessage() . "\n");
}
@unlink($tmpTar);
if (is_dir($extractDir)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($extractDir);
}

if (!$mmdbFound) {
    fwrite(STDERR, <<<EOT
[错误] 未在下载包中找到 GeoLite2-Country.mmdb。
手动放置指引：手动下载 GeoLite2-Country tar.gz，解压后把
GeoLite2-Country.mmdb 放到：database/geoip/GeoLite2-Country.mmdb

EOT);
    exit(1);
}

$size = round(filesize($targetFile) / 1048576, 1);
echo "[完成] $targetFile （{$size} MB）\n";
