<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use app\model\OrderItems;
use app\model\Orders;
use app\model\Payments;
use app\model\PlatformSettlements;
use app\model\ProductSkus;
use app\model\Products;
use support\Db;
use support\Log;

/**
 * 支付成功入账 — webhook 与对账补单共用，保证订单/支付/分账/销量口径一致
 */
class PaymentSuccessService
{
    /**
     * 入账（幂等：仅待支付记录可入账，订单状态原子门闩防重复）
     */
    public static function apply(Payments $payment, string $txnId): bool
    {
        if ((int) $payment->status !== 0) {
            return false;
        }

        return (bool) Db::transaction(function () use ($payment, $txnId) {
            // 原子门闩：仅待付款订单可被本次标记已支付，重复回调/对账不会重复入账
            $updated = Orders::where('id', $payment->order_id)
                ->where('status', 0)
                ->update([
                    'status' => 1,
                    'pay_at' => date('Y-m-d H:i:s'),
                    'pay_method' => $payment->gateway,
                ]);
            if (!$updated) {
                // 网关已扣款但订单不可入账（已取消/已退款等），留痕供人工核对，不改订单/支付状态
                Log::warning('支付成功但订单不可入账', [
                    'order_id' => $payment->order_id,
                    'payment_id' => $payment->id,
                    'txn_id' => $txnId,
                ]);
                return false;
            }

            $payment->status = 1;
            $payment->paid_at = date('Y-m-d H:i:s');
            if ((string) $payment->transaction_no === '' && $txnId !== '') {
                $payment->transaction_no = $txnId;
            }
            $payment->save();

            $order = Orders::find($payment->order_id);
            // decimal 列/配置经字符串入 Money：先乘除聚合、末位一次 round(2)（与 SettlementCron 同口径）
            $total = (string) $order->pay_amount;
            $platformRate = (string) config('payment.platform_rate', 5.0);
            $gatewayFeeRate = (string) config("payment.gateway_fee.{$payment->gateway}.rate", 2.9);
            $gatewayFixedFee = (string) config("payment.gateway_fee.{$payment->gateway}.fixed", 0.30);
            $platformFee = Money::round(Money::div(Money::mul($total, $platformRate), '100'));
            $gatewayFee = Money::round(Money::add(Money::div(Money::mul($total, $gatewayFeeRate), '100'), $gatewayFixedFee));
            $supplierAmount = Money::sub(Money::sub($total, $platformFee), $gatewayFee);

            // 创建分账记录
            PlatformSettlements::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'total_amount' => $total,
                'platform_fee' => $platformFee,
                'platform_fee_rate' => $platformRate,
                'payment_gateway_fee' => $gatewayFee,
                'supplier_amount' => Money::cmp($supplierAmount, '0') > 0 ? $supplierAmount : '0.00',
                'currency_code' => $order->currency_code,
                'status' => 0,
            ]);

            // 销量：订单明细驱动的 SKU/商品累计（与订单已付同事务，仅入账一次）
            foreach (OrderItems::where('order_id', $order->id)->get() as $item) {
                ProductSkus::where('id', $item->sku_id)->increment('sales_count', (int) $item->quantity);
                Products::where('id', $item->product_id)->increment('sales_count', (int) $item->quantity);
            }

            return true;
        });
    }
}
