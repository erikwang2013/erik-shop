<?php

require_once __DIR__ . '/../vendor/workerman/webman-framework/src/support/bootstrap.php';

// Linux 下加载 .env（Windows 由 windows.php 加载；immutable 不覆盖已有环境变量）
if (class_exists('Dotenv\Dotenv') && is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();
}

// 注册 SecureEncrypter：修复 PHP 8.3 openssl 空 IV warning → ErrorException → 500
// （与 service 侧一致；见 app/common/SecureEncrypter.php 说明）
\Maize\Encryptable\Encryption::setResolver(function (string $abstract) {
    $config = new \Maize\Encryptable\Config\EnvEncryptableConfig();
    return match ($abstract) {
        \Maize\Encryptable\PHPEncrypter::class => new \app\common\SecureEncrypter($config),
        \Maize\Encryptable\DBEncrypter::class => new \Maize\Encryptable\DBEncrypter($config),
        default => throw new \RuntimeException("Unknown encryptable resolver class: {$abstract}"),
    };
});