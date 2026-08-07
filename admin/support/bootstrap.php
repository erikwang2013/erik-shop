<?php

require_once __DIR__ . '/../vendor/workerman/webman-framework/src/support/bootstrap.php';

// Linux 下加载 .env（Windows 由 windows.php 加载；immutable 不覆盖已有环境变量）
if (class_exists('Dotenv\Dotenv') && is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();
}