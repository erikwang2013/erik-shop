<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\controller\v1\SubscriptionController;
use app\model\OrderItems;
use app\model\Orders;
use app\model\ProductSkuPrices;
use app\model\ProductSkus;
use app\model\Products;
use app\model\Subscriptions;
use app\model\SubscriptionLogs;
use app\model\SubscriptionOrders;
use app\model\Users;
use support\Db;

/**
 * 订阅周期购控制器集成测试（store/index/cancel）
 * 覆盖：创建订阅并生成首期订单、入参校验、我的订阅列表归属隔离、取消状态流转与越权
 */
class SubscriptionControllerIntegrationTest extends IntegrationTestCase
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
            'email' => 'qa_subc_' . uniqid() . '@example.com', 'nickname' => 'QA Sub Ctrl', 'status' => 1,
        ]);
        $this->track('erik_users', (int) $user->id);
        return (int) $user->id;
    }

    /** 造商品 + SKU（uk_sku_code 唯一）+ USD 定价，返回 sku_id */
    private function seedSku(float $price): int
    {
        $product = Products::create(['title' => 'QA Sub Product', 'status' => 1]);
        $this->track('erik_products', (int) $product->id);
        $sku = ProductSkus::create([
            'product_id' => $product->id, 'sku_code' => 'SKUSUBC' . uniqid(),
            'default_price' => $price, 'stock' => 10, 'status' => 1,
        ]);
        $this->track('erik_product_skus', (int) $sku->id);
        $this->track('erik_product_sku_prices', (int) ProductSkuPrices::create([
            'sku_id' => $sku->id, 'currency_code' => 'USD', 'price' => $price,
        ])->id);
        return (int) $sku->id;
    }

    private function store(int $userId, array $body): array
    {
        $req = $this->makeRequest('POST', '/api/subscriptions', $body);
        $req->userId = $userId;
        $res = (new SubscriptionController())->store($req);
        return json_decode($res->rawBody(), true);
    }

    private function index(int $userId): array
    {
        $req = $this->makeRequest('GET', '/api/subscriptions');
        $req->userId = $userId;
        $res = (new SubscriptionController())->index($req);
        return json_decode($res->rawBody(), true)['data'];
    }

    private function cancel(int $userId, int $subId): array
    {
        $req = $this->makeRequest('POST', '/api/subscriptions/' . $subId . '/cancel');
        $req->userId = $userId;
        $res = (new SubscriptionController())->cancel($req, (string) $subId);
        return json_decode($res->rawBody(), true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_creates_subscription_and_first_order(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();
        $skuId = $this->seedSku(99.50);

        $data = $this->store($userId, ['sku_id' => (string) $skuId, 'interval_days' => 30, 'quantity' => 2]);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $subId = (int) $data['data']['subscription_id'];
        $orderId = (int) $data['data']['order_id'];
        $this->track('erik_subscriptions', $subId);
        $this->track('erik_orders', $orderId);
        $this->track('erik_subscription_orders', (int) SubscriptionOrders::where('subscription_id', $subId)->value('id'));
        $this->track('erik_subscription_logs', (int) SubscriptionLogs::where('subscription_id', $subId)->value('id'));
        $this->track('erik_order_items', (int) OrderItems::where('order_id', $orderId)->value('id'));
        $this->assertEqualsWithDelta(199.00, (float) $data['data']['first_amount'], 0.001);
        $this->assertSame(date('Y-m-d', strtotime('+30 days')), $data['data']['next_billing_at']);

        // 订阅记录
        $sub = Subscriptions::find($subId);
        $this->assertNotNull($sub);
        $this->assertSame('active', $sub->status);
        $this->assertSame(30, (int) $sub->interval_days);
        $this->assertSame(2, (int) $sub->quantity);
        $this->assertSame(date('Y-m-d', strtotime('+30 days')), $sub->next_billing_at);

        // 首期订单（99.50 × 2 = 199.00，按 USD 定价）
        $order = Orders::find($orderId);
        $this->assertNotNull($order);
        $this->assertStringStartsWith('SUB', (string) $order->order_no);
        $this->assertSame(0, (int) $order->status);                       // 待付款
        $this->assertSame('USD', $order->currency_code);
        $this->assertEqualsWithDelta(199.00, (float) $order->pay_amount, 0.001);
        $item = OrderItems::where('order_id', $orderId)->first();
        $this->assertSame($skuId, (int) $item->sku_id);
        $this->assertEqualsWithDelta(99.50, (float) $item->price, 0.001);
        $this->assertSame(2, (int) $item->quantity);

        // 首期关联 + 激活日志
        $so = SubscriptionOrders::where('subscription_id', $subId)->first();
        $this->assertSame($orderId, (int) $so->order_id);
        $this->assertSame(1, (int) $so->billing_cycle);
        $this->assertSame(1, SubscriptionLogs::where('subscription_id', $subId)->where('action', 'activate')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_validates_interval_and_sku(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();
        $skuId = $this->seedSku(10.00);

        // 非法周期
        $data = $this->store($userId, ['sku_id' => (string) $skuId, 'interval_days' => 45]);
        $this->assertSame(422, $data['code']);

        // SKU 不存在
        $data = $this->store($userId, ['sku_id' => '999999999999', 'interval_days' => 30]);
        $this->assertSame(404, $data['code']);

        $this->assertSame(0, Subscriptions::where('user_id', $userId)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_lists_only_own_subscriptions(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();
        $otherUserId = $this->seedUser();
        $skuId = $this->seedSku(20.00);

        $this->store($userId, ['sku_id' => (string) $skuId, 'interval_days' => 30]);
        $this->store($userId, ['sku_id' => (string) $skuId, 'interval_days' => 60]);

        $list = $this->index($userId);
        $this->assertCount(2, $list['list']);
        $this->assertSame(60, (int) $list['list'][0]['interval_days']);    // id desc

        // 他人列表不可见
        $otherList = $this->index($otherUserId);
        $this->assertCount(0, $otherList['list']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cancel_flow_and_ownership(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();
        $otherUserId = $this->seedUser();
        $skuId = $this->seedSku(20.00);

        $data = $this->store($userId, ['sku_id' => (string) $skuId, 'interval_days' => 30]);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $subId = (int) $data['data']['subscription_id'];
        $this->track('erik_subscriptions', $subId);

        // 非本人取消 → 404
        $data = $this->cancel($otherUserId, $subId);
        $this->assertSame(404, $data['code']);
        $this->assertSame('active', Subscriptions::find($subId)->status);

        // 本人取消 → cancelled
        $data = $this->cancel($userId, $subId);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $sub = Subscriptions::find($subId);
        $this->assertSame('cancelled', $sub->status);
        $this->assertNotNull($sub->cancelled_at);
        $this->assertSame(1, SubscriptionLogs::where('subscription_id', $subId)->where('action', 'cancel')->count());

        // 重复取消 → 422
        $data = $this->cancel($userId, $subId);
        $this->assertSame(422, $data['code']);
    }
}
