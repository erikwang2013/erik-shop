<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 敏感数据加解密配置
 * 使用 erikwang2013/encryption 包（接口层）+ erikwang2013/encryptable（数据库层）
 */

return [
    // 加密密钥（AES-256需要32字节，生产环境通过环境变量设置）
    'key' => getenv('ENCRYPTION_KEY') ?: 'base64:your-32-byte-key-change-in-production!!',

    // 加密算法
    'cipher' => 'AES-256-CBC',

    // 数据库字段加密前缀（用于识别已加密值，encryptable包使用）
    'db_prefix' => '__encrypted__',

    // 加密时使用的序列化方式
    'serialize' => 'json',              // json/serialize
];
