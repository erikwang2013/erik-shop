<?php
/**
 * Here is your custom functions.
 */

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 获取默认 Redis 连接（webman 1.x 兼容辅助函数）
 * 内部使用 support\Redis 门面（基于 illuminate/redis + phpredis）
 */
if (!function_exists('redis')) {
    function redis()
    {
        return support\Redis::connection();
    }
}
