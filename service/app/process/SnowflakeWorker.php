<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\common\Snowflake;
use Workerman\Worker;

/**
 * Snowflake初始化进程
 * Worker启动时初始化Snowflake单例
 */
class SnowflakeWorker
{
    public function onWorkerStart(Worker $worker): void
    {
        Snowflake::init(
            config('snowflake.worker_id', 1),
            config('snowflake.datacenter_id', 1)
        );
    }
}
