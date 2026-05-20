<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * Hashids 配置
 * 用于接口层的ID加解密，隐藏真实snowflake ID
 * 使用 erikwang2013/hashids 包
 */

return [
    // 盐值（生产环境通过环境变量设置，不可泄露）
    'salt' => getenv('HASHIDS_SALT') ?: 'erik-shop-hashids-salt-change-me',

    // 最小hash长度（越长越安全，但URL更丑）
    'min_length' => 8,

    // 字符集（默认使用小写字母+数字，避免大小写混淆）
    'alphabet' => 'abcdefghijklmnopqrstuvwxyz23456789',

    // 从hashid还原ID时，是否抛出异常（false则返回0）
    'throw_on_decode_fail' => true,
];
