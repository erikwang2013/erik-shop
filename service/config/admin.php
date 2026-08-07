<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 管理后台内部接口配置
 * service 通过共享密钥校验 admin 发出的请求（X-Admin-Key header）
 */

return [
    // admin 后台调用内部管理接口的共享密钥（环境变量 ADMIN_API_KEY）
    'api_key' => getenv('ADMIN_API_KEY') ?: '',
];
