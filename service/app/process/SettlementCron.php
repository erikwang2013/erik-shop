<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\Orders;
use app\model\Payments;
use app\model\PlatformSettlements;
use support\Log;
use Workerman\Worker;

/**
 * 分账结算 — 每日为已付款订单生成平台结算单（erik_platform_settlements）
 * 平台佣金率/支付手续费率用 cron.platform_fee_rate / cron.payment_gateway_fee_rate 配置
 */
class SettlementCron
{
    private static int $interval = 86400;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('SettlementCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $paidOrderIds = PlatformSettlements::pluck('order_id')->all();
        $orders = Orders::where('status', 1)->whereNotIn('id', $paidOrderIds)->orderBy('id', 'desc')->limit(500)->get();
        if ($orders->isEmpty()) {
            return;
        }

        $platformFeeRate = (float) config('cron.platform_fee_rate', 3.00);
        $gatewayFeeRate = (float) config('cron.payment_gateway_fee_rate', 2.90);
        $gatewayFeeFixed = (float) config('cron.payment_gateway_fee_fixed', 0.30);
        $created = 0;

        foreach ($orders as $order) {
            $payment = Payments::where('order_id', $order->id)->where('status', 1)->first();
            $total = (float) $order->pay_amount;
            $platformFee = round($total * $platformFeeRate / 100, 2);
            $gatewayFee = round($total * $gatewayFeeRate / 100 + $gatewayFeeFixed, 2);
            $supplierAmount = round($total - $platformFee - $gatewayFee, 2);

            PlatformSettlements::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id ?? 0,
                'total_amount' => $total,
                'platform_fee' => $platformFee,
                'platform_fee_rate' => $platformFeeRate,
                'payment_gateway_fee' => $gatewayFee,
                'supplier_amount' => max(0, $supplierAmount),
                'affiliate_amount' => 0,
                'currency_code' => $order->currency_code ?: 'USD',
                'status' => 0,
            ]);
            $created++;
        }
        Log::info("SettlementCron 完成，生成 {$created} 笔平台结算单");
    }
}
