<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use app\model\Coupons;
use app\model\OrderItems;
use app\model\OrderLogs;
use app\model\Orders;
use app\model\ProductSkus;
use app\model\UserCoupons;
use support\Db;

/**
 * 订单取消（用户取消/超时取消/风控驳回共用）
 *
 * 在独立事务内完成：状态原子流转 + 恢复库存 + 退还优惠券 + 写订单日志。
 * 状态流转用条件更新做门闩，并发/重复取消不会重复恢复库存。
 */
class OrderCancellation
{
    /**
     * @param Orders  $order        订单（方法内重新做状态门闩，未持有行锁也安全）
     * @param array   $fromStatuses 允许取消的源状态
     * @param string  $operator     操作者（user/admin/system）
     * @param string  $remark       日志备注
     * @throws BusinessException 状态已变化等预期业务错误
     */
    public static function cancel(Orders $order, array $fromStatuses, string $operator, string $remark): void
    {
        Db::transaction(function () use ($order, $fromStatuses, $operator, $remark) {
            // 先记录原状态，供日志使用（条件更新后模型不会自动刷新）
            $fromStatus = (int) $order->status;

            // 原子门闩：仅指定状态可流转，防止并发重复取消导致库存重复恢复
            $updated = Orders::where('id', $order->id)
                ->whereIn('status', $fromStatuses)
                ->update([
                    'status' => 5,
                    'canceled_at' => date('Y-m-d H:i:s'),
                ]);
            if (!$updated) {
                throw new BusinessException('订单状态已变化，请刷新后重试');
            }

            // 恢复库存
            $items = OrderItems::where('order_id', $order->id)->get();
            foreach ($items as $item) {
                ProductSkus::where('id', $item->sku_id)->increment('stock', $item->quantity);
                // 库存流水（不可变账本：取消回库）；操作人取订单所属用户
                InventoryLogger::log(
                    (int) $item->sku_id,
                    'inbound',
                    (int) $item->quantity,
                    (int) ProductSkus::where('id', $item->sku_id)->value('stock'),
                    'order',
                    (int) $order->id,
                    (int) $order->user_id,
                    '取消订单恢复库存'
                );
            }

            // 退还本单核销的优惠券：恢复未使用，并回退券的已用数量
            $userCoupon = UserCoupons::where('used_order_id', $order->id)
                ->where('status', 1)
                ->first();
            if ($userCoupon) {
                $userCoupon->status = 0;
                $userCoupon->used_at = null;
                $userCoupon->used_order_id = 0;
                $userCoupon->save();

                Coupons::where('id', $userCoupon->coupon_id)
                    ->where('used_qty', '>', 0)
                    ->decrement('used_qty');
            }

            OrderLogs::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => 5,
                'operator' => $operator,
                'remark' => $remark,
            ]);
        });
    }
}
