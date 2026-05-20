<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use Erik\Snowflake\Snowflake as SnowflakeSDK;

/**
 * Snowflake 分布式ID生成器封装
 * 64位bigint，非自增，Worker启动时初始化单例
 */
class Snowflake
{
    private static ?SnowflakeSDK $instance = null;

    /**
     * Worker启动时调用，初始化Snowflake实例
     */
    public static function init(int $workerId, int $datacenterId): void
    {
        self::$instance = new SnowflakeSDK(
            $workerId,
            $datacenterId,
            config('snowflake.start_timestamp')
        );
    }

    /**
     * 获取下一个ID（强制string类型，防止PHP int溢出）
     */
    public static function nextId(): string
    {
        return (string) self::$instance->id();
    }
}
