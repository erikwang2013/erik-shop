<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\common\BusinessException;
use app\common\SubscriptionOrderService;
use app\model\Orders;
use app\model\ProductSkus;
use app\model\SubscriptionLogs;
use app\model\SubscriptionOrders;
use app\model\Subscriptions;
use support\Db;
use support\Log;
use Workerman\Worker;

/**
 * 订阅自动续费 — 每日为到期订阅生成下一期订单（复用 SubscriptionController 首期建单逻辑）
 * 失败处理：SKU 下架/库存不足时记 fail 日志并将订阅置为 paused，避免每日空转重试
 */
class SubscriptionCron
{
    private static int $interval = 86400;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('SubscriptionCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $subscriptions = Subscriptions::where('status', 'active')
            ->where('next_billing_at', '<=', date('Y-m-d'))
            ->limit(200)
            ->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $renewed = 0;
        foreach ($subscriptions as $subscription) {
            try {
                self::renew($subscription) ? $renewed++ : null;
            } catch (BusinessException $e) {
                // 业务失败（下架/库存不足/缺地址）：暂停订阅，避免每日空转重试
                self::fail($subscription, $e->getMessage());
            } catch (\Throwable $e) {
                self::fail($subscription, '续费异常: ' . $e->getMessage());
                Log::error("SubscriptionCron 续费失败 subscription_id={$subscription->id}: " . $e->getMessage());
            }
        }
        Log::info("SubscriptionCron 完成，续费 {$renewed} 个订阅");
    }

    private static function renew(Subscriptions $subscription): bool
    {
        return Db::transaction(function () use ($subscription) {
            // 行锁重查：与用户取消订阅互斥，已被取消/暂停则跳过
            $locked = Subscriptions::where('id', $subscription->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'active') {
                return false;
            }

            // 收货地址复用最近一期订单的地址快照（首期订单已写入）
            $lastSubscriptionOrder = SubscriptionOrders::where('subscription_id', $locked->id)
                ->orderBy('id', 'desc')
                ->first();
            $lastOrder = $lastSubscriptionOrder ? Orders::find($lastSubscriptionOrder->order_id) : null;
            $addressSnapshot = $lastOrder ? ($lastOrder->address_snapshot ?? []) : [];
            if (empty($addressSnapshot)) {
                throw new BusinessException('缺少收货地址，自动续费失败');
            }

            $sku = ProductSkus::where('id', $locked->sku_id)->where('status', 1)->first();
            if (!$sku) {
                throw new BusinessException('SKU 已下架，自动续费失败');
            }
            if ((int) $sku->stock < (int) $locked->quantity) {
                throw new BusinessException("库存不足（剩 {$sku->stock}），自动续费失败");
            }

            $cycle = (int) SubscriptionOrders::where('subscription_id', $locked->id)->count() + 1;
            $order = SubscriptionOrderService::createOrder($locked, $addressSnapshot, $cycle);

            // 订单生成成功后再推进计费日并记日志（同事务提交）
            $locked->next_billing_at = date('Y-m-d', strtotime("+{$locked->interval_days} days"));
            $locked->save();
            SubscriptionLogs::create([
                'subscription_id' => $locked->id,
                'action' => 'renew',
                'remark' => "第 {$cycle} 期自动续费，生成订单 {$order->order_no}",
            ]);
            return true;
        });
    }

    private static function fail(Subscriptions $subscription, string $remark): void
    {
        Db::transaction(function () use ($subscription, $remark) {
            // 行锁重查：用户已取消/暂停时不覆盖用户操作
            $locked = Subscriptions::where('id', $subscription->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== 'active') {
                return;
            }
            $locked->status = 'paused';
            $locked->paused_at = date('Y-m-d H:i:s');
            $locked->save();
            SubscriptionLogs::create([
                'subscription_id' => $locked->id,
                'action' => 'fail',
                'remark' => mb_substr($remark, 0, 200),
            ]);
        });
    }
}
