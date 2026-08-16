<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\Orders;
use app\model\OrderItems;
use app\model\Payments;
use app\model\PlatformSettlements;
use app\model\MerchantSettlements;
use app\model\MerchantProducts;
use app\model\Merchants;
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

        $created = 0;
        foreach ($orders as $order) {
            $payment = Payments::where('order_id', $order->id)->where('status', 1)->first();
            $total = (float) $order->pay_amount;

            // 费率统一：payment.gateway_fee / payment.platform_rate 为唯一费率源（与 webhook 同源），
            // cron.* 仅作兼容回退，消除此前 webhook 与 cron 双源漂移
            $gateway = $payment->gateway ?? 'stripe';
            $gf = config("payment.gateway_fee.{$gateway}", []);
            $gatewayFeeRate = (float) ($gf['rate'] ?? config('cron.payment_gateway_fee_rate', 2.90));
            $gatewayFeeFixed = (float) ($gf['fixed'] ?? config('cron.payment_gateway_fee_fixed', 0.30));
            $platformFeeRate = (float) (config('payment.platform_rate') ?? config('cron.platform_fee_rate', 3.00));

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

            // 卖家分账：订单商品 → 卖家商品关系(approved) → 卖家抽成 → MerchantSettlements
            self::createMerchantSettlements($order);
            $created++;
        }
        Log::info("SettlementCron 完成，生成 {$created} 笔平台结算单");
    }

    /**
     * 卖家分账（erik_merchant_settlements）
     * 数据链：order_items.product_id → erik_merchant_products(approved) → erik_merchants.commission_rate
     */
    private static function createMerchantSettlements(Orders $order): void
    {
        $items = OrderItems::where('order_id', $order->id)->get();
        $productIds = $items->pluck('product_id')->unique();
        if ($productIds->isEmpty()) {
            return;
        }
        $links = MerchantProducts::whereIn('product_id', $productIds)
            ->where('status', 'approved')
            ->get()
            ->keyBy('product_id');
        if ($links->isEmpty()) {
            return;
        }
        $merchantIds = $links->pluck('merchant_id')->unique();
        $merchants = Merchants::whereIn('id', $merchantIds)->get()->keyBy('id');

        $byMerchant = [];
        foreach ($items as $item) {
            $link = $links[$item->product_id] ?? null;
            if (!$link) {
                continue;
            }
            $mid = $link->merchant_id;
            $byMerchant[$mid] = ($byMerchant[$mid] ?? 0) + (float) $item->subtotal;
        }

        foreach ($byMerchant as $mid => $amount) {
            if (MerchantSettlements::where('order_id', $order->id)->where('merchant_id', $mid)->exists()) {
                continue;
            }
            $rate = (float) ($merchants[$mid]->commission_rate ?? 5.0);
            $commission = round($amount * $rate / 100, 2);
            MerchantSettlements::create([
                'merchant_id' => $mid,
                'order_id' => $order->id,
                'order_amount' => round($amount, 2),
                'commission_rate' => $rate,
                'commission_amount' => $commission,
                'settlement_amount' => round($amount - $commission, 2),
                'status' => 0,
            ]);
        }
    }
}
