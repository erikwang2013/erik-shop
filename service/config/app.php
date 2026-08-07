<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 应用核心配置
 * debug/时区/请求类/路径等
 */

use support\Request;

return [
    // 调试模式（开发环境启用，生产环境必须关闭）
    'debug' => env('APP_DEBUG', false),

    // 错误报告级别（开发环境全部报告）
    'error_reporting' => E_ALL,

    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 请求类（可自定义增加方法）
    'request_class' => Request::class,

    // 静态文件根目录
    'public_path' => base_path() . DIRECTORY_SEPARATOR . 'public',

    // 运行时目录（日志/缓存/session）
    'runtime_path' => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',

    // 控制器后缀（IndexController 中的 Controller 部分）
    'controller_suffix' => 'Controller',

    // 是否复用控制器实例（false=每次请求新建，避免属性污染）
    'controller_reuse' => false,
];
