<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use app\model\OrderLogs;
use app\model\Orders;
use app\model\Payments;
use app\model\Refunds;
use support\Db;

/**
 * 退款入账公共逻辑：退款单 + 支付记录 + 订单状态 + 操作日志
 * 供 admin 执行退款（AdminOpsController）与支付 webhook 退款事件共用，保证状态流转规则唯一
 */
class RefundHelper
{
    /**
     * 订单状态联动：全额退完置已退款(7)，部分退置退款中(6)，已处于 6/7 不再回退
     */
    public static function orderStatusFor(bool $fullRefund, int $currentStatus): int
    {
        if ($fullRefund) {
            return 7;
        }
        return $currentStatus < 6 ? 6 : $currentStatus;
    }

    /**
     * 将一笔退款标记为已退款，并联动支付/订单状态（同一事务内原子执行）
     */
    public static function markRefunded(Refunds $refund, Payments $payment, float $amount, string $operator = 'system', string $remark = ''): void
    {
        Db::transaction(function () use ($refund, $payment, $amount, $operator, $remark) {
            $refund->status = 3;
            $refund->refunded_at = date('Y-m-d H:i:s');
            $refund->save();

            // 原子累加已退金额，防并发退款互相覆盖
            Payments::where('id', $payment->id)->increment('refunded_amount', $amount);
            $payment->refresh();
            $fullRefund = (float) $payment->refunded_amount >= (float) $payment->amount - 0.01;
            $payment->status = $fullRefund ? 2 : 1;
            $payment->save();

            $order = Orders::find($payment->order_id);
            if (!$order) {
                return; // 订单不存在时仅保留退款记录，不流转状态
            }
            $fromStatus = (int) $order->status;
            $toStatus = self::orderStatusFor($fullRefund, $fromStatus);
            $order->status = $toStatus;
            $order->save();

            OrderLogs::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'operator' => $operator,
                'remark' => $remark,
            ]);
        });
    }
}
