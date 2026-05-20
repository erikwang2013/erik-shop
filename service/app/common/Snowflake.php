<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use Erikwang2013\Snowflake\Snowflake as SnowflakeSDK;

class Snowflake
{
    private static ?SnowflakeSDK $instance = null;

    public static function init(): void
    {
        if (self::$instance === null) {
            $startTimestamp = config('snowflake.start_timestamp', 1700000000000);
            $workerId = config('snowflake.worker_id', 1);
            $datacenterId = config('snowflake.datacenter_id', 1);
            self::$instance = new SnowflakeSDK($workerId, $datacenterId, $startTimestamp);
        }
    }

    public static function nextId(): string
    {
        return (string) self::$instance->id();
    }

    public static function parse(string $id): array
    {
        return self::$instance->parseId((int) $id);
    }
}
