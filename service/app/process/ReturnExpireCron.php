<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\ReturnOrders;
use support\Db;
use support\Log;
use Workerman\Worker;

/**
 * 退货超时关闭 — 每小时将超过7天未审核的退货单自动驳回
 * 超时天数可用 cron.return_expire_days 配置
 */
class ReturnExpireCron
{
    private static int $interval = 3600;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('ReturnExpireCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $expireDays = max(1, (int) config('cron.return_expire_days', 7));
        $deadline = date('Y-m-d H:i:s', strtotime("-{$expireDays} days"));
        $ids = ReturnOrders::where('status', 0)
            ->where('created_at', '<', $deadline)
            ->pluck('id')
            ->all();
        if (empty($ids)) {
            return;
        }

        $count = ReturnOrders::whereIn('id', $ids)->update([
            'status' => 5,
            'remark' => Db::raw("CONCAT(remark, ' 超过{$expireDays}天未审核自动关闭')"),
        ]);
        Log::info("ReturnExpireCron 完成，自动驳回 {$count} 单超时退货");
    }
}
