<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use app\model\InventoryLogs;

/**
 * 库存流水记录器（不可变账本）
 *
 * 背景：README 声称「库存流水(不可变账本)」但 InventoryLogs 此前无任何业务写入
 * （docs/PLAN-RESEARCH.md §7 差距）。本类在下单扣减/取消恢复等库存变动点记录流水。
 *
 * 约定：
 *   - quantity：负=出库（outbound），正=入库（inbound）
 *   - balance_after：变动后的 SKU 库存快照
 *   - reference_type/id：关联单据（order/return/purchase_order 等）
 */
class InventoryLogger
{
    /**
     * 记录一条库存流水（异常不影响主流程）
     */
    public static function log(
        int $skuId,
        string $type,
        int $quantity,
        int $balanceAfter,
        string $referenceType,
        int $referenceId,
        int $operatorId = 0,
        string $remark = '',
        int $warehouseId = 0
    ): void {
        try {
            InventoryLogs::create([
                'warehouse_id' => $warehouseId,
                'sku_id' => $skuId,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'operator_id' => $operatorId,
                'remark' => $remark,
            ]);
        } catch (\Throwable $e) {
            \support\Log::warning('库存流水记录失败: ' . $e->getMessage(), [
                'sku_id' => $skuId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        }
    }
}
