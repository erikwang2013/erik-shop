<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\OrderItems;
use app\model\Orders;
use app\model\ProductSkuPrices;
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
            } catch (\Throwable $e) {
                self::fail($subscription, '续费异常: ' . $e->getMessage());
                Log::error("SubscriptionCron 续费失败 subscription_id={$subscription->id}: " . $e->getMessage());
            }
        }
        Log::info("SubscriptionCron 完成，续费 {$renewed} 个订阅");
    }

    private static function renew(Subscriptions $subscription): bool
    {
        $sku = ProductSkus::where('id', $subscription->sku_id)->where('status', 1)->first();
        if (!$sku) {
            self::fail($subscription, 'SKU 已下架，自动续费失败');
            return false;
        }
        if ((int) $sku->stock < (int) $subscription->quantity) {
            self::fail($subscription, "库存不足（剩 {$sku->stock}），自动续费失败");
            return false;
        }

        $price = ProductSkuPrices::where('sku_id', $sku->id)->where('currency_code', 'USD')->value('price');
        $price = (float) ($price ?? $sku->default_price);
        $quantity = (int) $subscription->quantity;
        $subtotal = round($price * $quantity, 2);

        Db::transaction(function () use ($subscription, $sku, $price, $quantity, $subtotal) {
            $order = Orders::create([
                'order_no' => 'SUB' . date('Ymd') . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
                'user_id' => $subscription->user_id,
                'status' => 0,
                'currency_code' => 'USD',
                'total_amount' => $subtotal,
                'pay_amount' => $subtotal,
                'address_snapshot' => [],
            ]);
            OrderItems::create([
                'order_id' => $order->id,
                'product_id' => $sku->product_id,
                'sku_id' => $sku->id,
                'title' => '周期购 SKU ' . $sku->sku_code,
                'price' => $price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ]);

            $cycle = (int) SubscriptionOrders::where('subscription_id', $subscription->id)->count() + 1;
            SubscriptionOrders::create([
                'subscription_id' => $subscription->id,
                'order_id' => $order->id,
                'billing_cycle' => $cycle,
                'status' => 'success',
            ]);

            $subscription->next_billing_at = date('Y-m-d', strtotime("+{$subscription->interval_days} days"));
            $subscription->save();
            SubscriptionLogs::create([
                'subscription_id' => $subscription->id,
                'action' => 'renew',
                'remark' => "第 {$cycle} 期自动续费，生成订单 {$order->order_no}",
            ]);
        });
        return true;
    }

    private static function fail(Subscriptions $subscription, string $remark): void
    {
        Db::transaction(function () use ($subscription, $remark) {
            $subscription->status = 'paused';
            $subscription->paused_at = date('Y-m-d H:i:s');
            $subscription->save();
            SubscriptionLogs::create([
                'subscription_id' => $subscription->id,
                'action' => 'fail',
                'remark' => mb_substr($remark, 0, 200),
            ]);
        });
    }
}
