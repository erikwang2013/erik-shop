<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\common\OrderCancellation;
use app\model\Orders;
use support\Log;
use Workerman\Worker;

/**
 * 未付款订单超时自动取消（每 300 秒）
 * 超时分钟数由 cron.unpaid_cancel_minutes 配置，取消时恢复库存并退还优惠券
 */
class UnpaidOrderCron
{
    private static int $interval = 300;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('UnpaidOrderCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $minutes = max(1, (int) config('cron.unpaid_cancel_minutes', 30));
        $deadline = date('Y-m-d H:i:s', time() - $minutes * 60);
        // 排除已发起网关支付（有交易号）的待支付单，避免取消后用户仍完成支付
        // shortcut: 仅跳过取消，彻底释放这类库存需接入网关取消/作废接口
        $orders = Orders::where('status', 0)
            ->where('created_at', '<', $deadline)
            ->whereNotIn('id', function ($q) {
                $q->select('order_id')->from('shop_payments')
                    ->where('status', 0)
                    ->where('transaction_no', '!=', '');
            })
            ->limit(200)
            ->get();
        if ($orders->isEmpty()) {
            return;
        }

        $canceled = 0;
        foreach ($orders as $order) {
            try {
                OrderCancellation::cancel($order, [0], 'system', '超时未付款自动取消');
                $canceled++;
            } catch (\Throwable $e) {
                // 单笔失败（如状态已被用户取消）不影响其余订单
                Log::error("UnpaidOrderCron 取消失败 order_id={$order->id}: " . $e->getMessage());
            }
        }
        Log::info("UnpaidOrderCron 完成，自动取消 {$canceled} 个未付款订单");
    }
}
