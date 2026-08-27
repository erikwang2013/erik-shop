<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\model\OrderItems;
use app\model\Orders;
use app\model\ProductSkuPrices;
use app\model\ProductSkus;
use app\model\Products;
use app\model\Subscriptions;
use app\model\SubscriptionLogs;
use app\model\SubscriptionOrders;
use app\model\Users;
use app\process\SubscriptionCron;
use support\Db;

/**
 * 订阅自动续费 cron 集成测试 — SubscriptionCron::run()（静态方法，不依赖 Timer/Worker 循环）
 * 覆盖：到期订阅生成续费订单 + billing_cycle 递增 + next_billing_at 顺延；SKU 下架/库存不足置 paused 且不生成订单
 */
class SubscriptionCronIntegrationTest extends IntegrationTestCase
{
    /** @var array<string, int[]> */
    private array $created = [];

    protected function tearDown(): void
    {
        if (self::$dbAvailable) {
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

    private function seedUser(): int
    {
        $user = Users::create([
            'invite_code' => 'T' . substr(md5(uniqid()), 0, 8),   // uk_invite_code 唯一
            'email' => 'qa_sub_' . uniqid() . '@example.com', 'nickname' => 'QA Sub', 'status' => 1,
        ]);
        $this->track('shop_users', (int) $user->id);
        return (int) $user->id;
    }

    /** 造商品 + SKU（uk_sku_code 唯一）+ USD 定价，返回 sku_id */
    private function seedSku(float $price, int $stock, int $status = 1): int
    {
        $product = Products::create(['title' => 'QA Sub Product', 'status' => 1]);
        $this->track('shop_products', (int) $product->id);
        $sku = ProductSkus::create([
            'product_id' => $product->id, 'sku_code' => 'SKUSUB' . uniqid(),
            'default_price' => $price, 'stock' => $stock, 'status' => $status,
        ]);
        $this->track('shop_product_skus', (int) $sku->id);
        $this->track('shop_product_sku_prices', (int) ProductSkuPrices::create([
            'sku_id' => $sku->id, 'currency_code' => 'USD', 'price' => $price,
        ])->id);
        return (int) $sku->id;
    }

    /** 造到期订阅（next_billing_at=昨天，status=active） */
    private function seedDueSubscription(int $userId, int $skuId, int $quantity, int $intervalDays = 30): Subscriptions
    {
        $sub = Subscriptions::create([
            'user_id' => $userId, 'sku_id' => $skuId,
            'interval_days' => $intervalDays, 'quantity' => $quantity,
            'next_billing_at' => date('Y-m-d', strtotime('-1 day')), 'status' => 'active',
        ]);
        $this->track('shop_subscriptions', (int) $sub->id);
        return $sub;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function renewal_generates_next_order_and_advances_cycle(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();
        $skuId = $this->seedSku(99.50, 10);
        $sub = $this->seedDueSubscription($userId, $skuId, 1);

        // 已有第 1 期记录，断言本次生成第 2 期（billing_cycle = 现有 count + 1）
        $first = Orders::create([
            'order_no' => 'SUB' . date('Ymd') . uniqid(),
            'user_id' => $userId, 'status' => 0, 'currency_code' => 'USD',
        ]);
        $this->track('shop_orders', (int) $first->id);
        $prior = SubscriptionOrders::create([
            'subscription_id' => $sub->id, 'order_id' => $first->id,
            'billing_cycle' => 1, 'status' => 'success',
        ]);
        $this->track('shop_subscription_orders', (int) $prior->id);

        SubscriptionCron::run();

        $order = Orders::where('user_id', $userId)->where('id', '!=', $first->id)->first();
        $this->assertNotNull($order, '到期订阅应生成续费订单');
        $this->assertStringStartsWith('SUB', (string) $order->order_no);
        $this->assertSame(0, (int) $order->status);                       // 新订单待付款
        $this->assertSame('USD', $order->currency_code);
        $this->assertEquals(99.50, (float) $order->total_amount);
        $this->assertEquals(99.50, (float) $order->pay_amount);

        $item = OrderItems::where('order_id', $order->id)->first();
        $this->assertNotNull($item);
        $this->assertSame($skuId, (int) $item->sku_id);
        $this->assertEquals(99.50, (float) $item->price);
        $this->assertSame(1, (int) $item->quantity);
        $this->assertEquals(99.50, (float) $item->subtotal);

        $so = SubscriptionOrders::where('subscription_id', $sub->id)->orderByDesc('billing_cycle')->first();
        $this->assertNotNull($so);
        $this->assertSame((int) $order->id, (int) $so->order_id);
        $this->assertSame(2, (int) $so->billing_cycle);                   // 周期数 +1
        $this->assertSame('success', $so->status);

        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertSame(date('Y-m-d', strtotime('+30 days')), $sub->next_billing_at);   // 顺延一个周期

        $log = SubscriptionLogs::where('subscription_id', $sub->id)->where('action', 'renew')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('第 2 期', (string) $log->remark);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function insufficient_stock_pauses_subscription_without_order(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();
        $skuId = $this->seedSku(50.00, 0);          // 库存 0
        $sub = $this->seedDueSubscription($userId, $skuId, 2);   // 需要 2 件

        SubscriptionCron::run();

        $sub->refresh();
        $this->assertSame('paused', $sub->status);
        $this->assertNotNull($sub->paused_at);
        $this->assertSame(0, Orders::where('user_id', $userId)->count());
        $this->assertSame(0, SubscriptionOrders::where('subscription_id', $sub->id)->count());

        $log = SubscriptionLogs::where('subscription_id', $sub->id)->where('action', 'fail')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('库存不足', (string) $log->remark);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sku_off_shelf_pauses_subscription_without_order(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();
        $skuId = $this->seedSku(50.00, 10, 0);      // 下架 SKU
        $sub = $this->seedDueSubscription($userId, $skuId, 1);

        SubscriptionCron::run();

        $sub->refresh();
        $this->assertSame('paused', $sub->status);
        $this->assertNotNull($sub->paused_at);
        $this->assertSame(0, Orders::where('user_id', $userId)->count());
        $this->assertSame(0, SubscriptionOrders::where('subscription_id', $sub->id)->count());

        $log = SubscriptionLogs::where('subscription_id', $sub->id)->where('action', 'fail')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('已下架', (string) $log->remark);
    }
}
