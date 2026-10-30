<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests\Integration;

use app\common\BusinessException;
use app\common\OrderCancellation;
use app\model\Coupons;
use app\model\InventoryLogs;
use app\model\OrderItems;
use app\model\OrderLogs;
use app\model\Orders;
use app\model\ProductSkus;
use app\model\Products;
use app\model\UserCoupons;
use PHPUnit\Framework\Attributes\Test;
use support\Db;
use tests\IntegrationTestCase;

/**
 * 订单取消事务回归测试：库存恢复 + 优惠券回退 + 状态/日志 + 重复取消门闩
 */
class OrderCancellationTest extends IntegrationTestCase
{
    /** @var array<string, int[]> table => ids */
    private array $created = [];
    /** @var int[] */
    private array $orderIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDb();
    }

    protected function tearDown(): void
    {
        if (self::$dbAvailable) {
            if ($this->orderIds) {
                Db::table('shop_inventory_logs')->where('reference_type', 'order')->whereIn('reference_id', $this->orderIds)->delete();
                Db::table('shop_order_logs')->whereIn('order_id', $this->orderIds)->delete();
                Db::table('shop_order_items')->whereIn('order_id', $this->orderIds)->delete();
            }
            foreach ($this->created as $table => $ids) {
                if ($ids) {
                    Db::table($table)->whereIn('id', $ids)->delete();
                }
            }
        }
        parent::tearDown();
    }

    private function track(string $table, int $id): void
    {
        $this->created[$table][] = $id;
    }

    /** @return array{0: int, 1: int} [product_id, sku_id] */
    private function seedSku(int $stock): array
    {
        $product = Products::create(['title' => 'QA Cancel ' . uniqid(), 'status' => 2]);
        $this->track('shop_products', (int) $product->id);
        $sku = ProductSkus::create([
            'product_id' => $product->id,
            'sku_code' => 'QAC' . strtoupper(bin2hex(random_bytes(6))),
            'default_price' => '10.00',
            'stock' => $stock,
            'status' => 1,
        ]);
        $this->track('shop_product_skus', (int) $sku->id);
        return [(int) $product->id, (int) $sku->id];
    }

    private function seedOrder(int $userId, int $status = 0, string $payAmount = '10.00'): Orders
    {
        $order = Orders::create([
            'order_no' => 'QAC' . strtoupper(bin2hex(random_bytes(8))),
            'user_id' => $userId,
            'status' => $status,
            'total_amount' => $payAmount,
            'pay_amount' => $payAmount,
            'currency_code' => 'USD',
        ]);
        $this->orderIds[] = (int) $order->id;
        $this->track('shop_orders', (int) $order->id);
        return $order;
    }

    private function seedItem(int $orderId, int $productId, int $skuId, int $quantity, string $price): void
    {
        OrderItems::create([
            'order_id' => $orderId,
            'product_id' => $productId,
            'sku_id' => $skuId,
            'title' => 'QA Item',
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => bcmul($price, (string) $quantity, 2),
        ]);
    }

    private function randomUserId(): int
    {
        return random_int(100000000000000000, 999999999999999999);
    }

    #[Test]
    public function cancel_restores_stock_and_coupon_and_writes_log(): void
    {
        $userId = $this->randomUserId();
        [$productA, $skuA] = $this->seedSku(5);
        [$productB, $skuB] = $this->seedSku(1);
        $order = $this->seedOrder($userId, 0, '35.00');
        $this->seedItem((int) $order->id, $productA, $skuA, 2, '10.00');
        $this->seedItem((int) $order->id, $productB, $skuB, 3, '5.00');

        $coupon = Coupons::create([
            'title' => 'QA Cancel Coupon', 'type' => 1, 'value' => '5.00',
            'total_qty' => 10, 'used_qty' => 1, 'status' => 1,
        ]);
        $this->track('shop_coupons', (int) $coupon->id);
        $userCoupon = UserCoupons::create([
            'user_id' => $userId,
            'coupon_id' => $coupon->id,
            'status' => 1,
            'used_at' => date('Y-m-d H:i:s'),
            'used_order_id' => $order->id,
        ]);
        $this->track('shop_user_coupons', (int) $userCoupon->id);

        OrderCancellation::cancel($order, [0], 'user', '用户取消订单');

        $order->refresh();
        $this->assertSame(5, (int) $order->status);
        $this->assertNotNull($order->canceled_at);

        $this->assertSame(7, (int) ProductSkus::find($skuA)->stock);
        $this->assertSame(4, (int) ProductSkus::find($skuB)->stock);

        $inboundA = InventoryLogs::where('sku_id', $skuA)->where('type', 'inbound')->first();
        $this->assertNotNull($inboundA);
        $this->assertSame(2, (int) $inboundA->quantity);
        $this->assertSame(7, (int) $inboundA->balance_after);
        $this->assertSame('order', $inboundA->reference_type);
        $this->assertSame((int) $order->id, (int) $inboundA->reference_id);
        $this->assertSame($userId, (int) $inboundA->operator_id);

        $inboundB = InventoryLogs::where('sku_id', $skuB)->where('type', 'inbound')->first();
        $this->assertNotNull($inboundB);
        $this->assertSame(3, (int) $inboundB->quantity);
        $this->assertSame(4, (int) $inboundB->balance_after);

        $userCoupon->refresh();
        $this->assertSame(0, (int) $userCoupon->status);
        $this->assertNull($userCoupon->used_at);
        $this->assertSame(0, (int) $userCoupon->used_order_id);
        $this->assertSame(0, (int) Coupons::find($coupon->id)->used_qty);

        $log = OrderLogs::where('order_id', $order->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(0, (int) $log->from_status);
        $this->assertSame(5, (int) $log->to_status);
        $this->assertSame('user', $log->operator);
        $this->assertSame('用户取消订单', $log->remark);
    }

    #[Test]
    public function duplicate_cancel_throws_and_does_not_restore_again(): void
    {
        [$productId, $skuId] = $this->seedSku(1);
        $order = $this->seedOrder($this->randomUserId(), 0, '15.00');
        $this->seedItem((int) $order->id, $productId, $skuId, 3, '5.00');

        $coupon = Coupons::create([
            'title' => 'QA Duplicate', 'type' => 1, 'value' => '1.00',
            'total_qty' => 10, 'used_qty' => 1, 'status' => 1,
        ]);
        $this->track('shop_coupons', (int) $coupon->id);
        $this->track('shop_user_coupons', (int) UserCoupons::create([
            'user_id' => $order->user_id, 'coupon_id' => $coupon->id, 'status' => 1,
            'used_at' => date('Y-m-d H:i:s'), 'used_order_id' => $order->id,
        ])->id);

        OrderCancellation::cancel($order, [0], 'user', '用户取消订单');
        $this->assertSame(4, (int) ProductSkus::find($skuId)->stock);

        // 复用内存中仍是 status=0 的模型，验证方法内的条件更新门闩而非内存状态
        try {
            OrderCancellation::cancel($order, [0], 'user', '用户取消订单');
            $this->fail('重复取消应抛 BusinessException');
        } catch (BusinessException) {
        }

        $this->assertSame(4, (int) ProductSkus::find($skuId)->stock);
        $this->assertSame(1, InventoryLogs::where('sku_id', $skuId)->where('type', 'inbound')->count());
        $this->assertSame(1, OrderLogs::where('order_id', $order->id)->count());
        $this->assertSame(5, (int) Orders::find($order->id)->status);
        $this->assertSame(0, (int) Coupons::find($coupon->id)->used_qty);
    }

    #[Test]
    public function cancel_rejects_order_outside_allowed_source_statuses(): void
    {
        [$productId, $skuId] = $this->seedSku(5);
        $order = $this->seedOrder($this->randomUserId(), 1, '10.00');
        $this->seedItem((int) $order->id, $productId, $skuId, 1, '10.00');

        try {
            OrderCancellation::cancel($order, [0], 'user', '用户取消订单');
            $this->fail('非允许状态应抛 BusinessException');
        } catch (BusinessException) {
        }

        $this->assertSame(1, (int) Orders::find($order->id)->status);
        $this->assertSame(5, (int) ProductSkus::find($skuId)->stock);
        $this->assertSame(0, OrderLogs::where('order_id', $order->id)->count());
        $this->assertSame(0, InventoryLogs::where('sku_id', $skuId)->count());
    }
}
