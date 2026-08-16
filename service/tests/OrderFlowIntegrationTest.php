<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\HashidsHelper;
use app\controller\v1\OrderController;
use app\model\Carts;
use app\model\Countries;
use app\model\Coupons;
use app\model\InventoryLogs;
use app\model\OrderItems;
use app\model\OrderLogs;
use app\model\Orders;
use app\model\ProductSkuPrices;
use app\model\ProductSkus;
use app\model\Products;
use app\model\UserAddresses;
use app\model\UserCoupons;
use app\model\Users;
use support\Db;

/**
 * 订单真实计费路径集成测试
 * 走 OrderController::store/cancel 完整事务：建单+明细+原子扣库存+清购物车+优惠券+库存流水+取消回库
 */
class OrderFlowIntegrationTest extends IntegrationTestCase
{
    /** @var array<string, int[]> table => ids */
    private array $created = [];
    private ?int $userId = null;
    private ?int $addressId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDb();
        $this->seedBase();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function track(string $table, int $id): void
    {
        $this->created[$table][] = $id;
    }

    private function cleanup(): void
    {
        if (!self::$dbAvailable) {
            return;
        }
        $orders = $this->created['erik_orders'] ?? [];
        foreach ([
            'erik_order_items' => ['order_id', $orders],
            'erik_order_logs' => ['order_id', $orders],
            'erik_payments' => ['order_id', $orders],
            'erik_platform_settlements' => ['order_id', $orders],
        ] as $table => [$col, $ids]) {
            if ($ids) {
                Db::table($table)->whereIn($col, $ids)->delete();
            }
        }
        if ($this->userId !== null) {
            Db::table('erik_inventory_logs')->where('operator_id', $this->userId)->delete();
            Db::table('erik_risk_logs')->where('user_id', $this->userId)->delete();
            \support\Redis::del('erik:risk:orders:' . $this->userId . ':h:' . date('YmdH'));
        }
        foreach ($this->created as $table => $ids) {
            if ($ids) {
                Db::table($table)->whereIn('id', $ids)->delete();
            }
        }
    }

    private function seedBase(): void
    {
        $user = Users::create([
            'invite_code' => 'T' . substr(md5(uniqid()), 0, 8),   // uk_invite_code 唯一
            'email' => 'qa_' . uniqid() . '@example.com',
            'nickname' => 'QA Order Test',
            'status' => 1,
        ]);
        $this->userId = (int) $user->id;
        $this->track('erik_users', $this->userId);

        $country = Countries::create([
            'name_en' => 'Test Country', 'name_cn' => '测试国家',
            'iso_code_2' => chr(65 + random_int(0, 25)) . chr(65 + random_int(0, 25)),   // uk_iso_code_2 唯一
            'iso_code_3' => 'USA',
            'currency_code' => 'USD', 'status' => 1, 'kyc_required' => 0,
        ]);
        $this->track('erik_countries', (int) $country->id);

        $address = UserAddresses::create([
            'user_id' => $this->userId, 'name' => 'QA', 'phone' => '1234567890',
            'country_id' => $country->id, 'detail' => '1 Test St', 'is_default' => 1,
        ]);
        $this->addressId = (int) $address->id;
        $this->track('erik_user_addresses', $this->addressId);
    }

    private function seedSku(float $price, int $stock): int
    {
        $product = Products::create(['title' => 'QA Product', 'status' => 1]);
        $this->track('erik_products', (int) $product->id);
        $sku = ProductSkus::create([
            'product_id' => $product->id, 'sku_code' => 'SKU' . uniqid(),
            'default_price' => $price, 'stock' => $stock, 'status' => 1,
        ]);
        $this->track('erik_product_skus', (int) $sku->id);
        $this->track('erik_product_sku_prices', (int) ProductSkuPrices::create([
            'sku_id' => $sku->id, 'currency_code' => 'USD', 'price' => $price,
        ])->id);
        return (int) $sku->id;
    }

    private function addCart(int $skuId, int $qty): void
    {
        $this->track('erik_carts', (int) Carts::create([
            'user_id' => $this->userId, 'sku_id' => $skuId,
            'product_id' => (int) ProductSkus::find($skuId)->product_id,
            'quantity' => $qty, 'selected' => 1,
        ])->id);
    }

    private function storeOrder(array $body = []): array
    {
        $req = $this->makeRequest('POST', '/api/orders', $body + [
            'address_id' => (string) $this->addressId,
            'currency_code' => 'USD',
        ]);
        $req->userId = $this->userId;
        $res = (new OrderController())->store($req);
        return [$res->getStatusCode(), json_decode($res->rawBody(), true)];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_creates_order_deducts_stock_and_clears_cart(): void
    {
        $skuId = $this->seedSku(24.99, 10);
        $this->addCart($skuId, 2);

        [, $data] = $this->storeOrder();
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $orderId = (int) $data['data']['order_id'];
        $this->track('erik_orders', $orderId);

        $order = Orders::find($orderId);
        $this->assertSame(0, (int) $order->status);                              // 待付款（风控 pass）
        $this->assertEqualsWithDelta(49.98, (float) $order->pay_amount, 0.001);  // 无运费/税费规则 → 仅商品价
        $this->assertSame('USD', $order->currency_code);
        $this->assertSame(8, (int) ProductSkus::find($skuId)->stock);            // 10-2 原子扣减
        $this->assertSame(0, Carts::where('user_id', $this->userId)->count());   // 购物车已清空

        $item = OrderItems::where('order_id', $orderId)->first();
        $this->assertSame(2, (int) $item->quantity);
        $this->assertEquals(24.99, (float) $item->price);
        $this->assertEquals(49.98, (float) $item->subtotal);
        $this->assertSame(1, OrderLogs::where('order_id', $orderId)->count());

        $log = InventoryLogs::where('sku_id', $skuId)->where('type', 'outbound')->first();
        $this->assertNotNull($log);
        $this->assertSame(-2, (int) $log->quantity);
        $this->assertSame(8, (int) $log->balance_after);
        $this->assertSame('order', $log->reference_type);
        $this->assertSame($orderId, (int) $log->reference_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_rolls_back_when_stock_insufficient(): void
    {
        $skuId = $this->seedSku(9.99, 3);
        $this->addCart($skuId, 5);

        [, $data] = $this->storeOrder();
        $this->assertSame(422, $data['code']);
        $this->assertStringContainsString('库存不足', $data['msg']);

        $this->assertSame(3, (int) ProductSkus::find($skuId)->stock);              // 事务回滚：库存未变
        $this->assertSame(1, Carts::where('user_id', $this->userId)->count());     // 回滚：购物车保留
        $this->assertSame(0, Orders::where('user_id', $this->userId)->count());    // 回滚：无订单
        $this->assertSame(0, InventoryLogs::where('sku_id', $skuId)->count());     // 回滚：无库存流水
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_applies_coupon_and_marks_it_used(): void
    {
        $skuId = $this->seedSku(30.00, 5);
        $this->addCart($skuId, 2);   // 小计 60.00

        $coupon = Coupons::create([
            'title' => 'QA满减', 'type' => 1, 'value' => 5.00, 'min_amount' => 0,
            'status' => 1, 'total_qty' => 10, 'per_user_limit' => 1,
        ]);
        $this->track('erik_coupons', (int) $coupon->id);
        $this->track('erik_user_coupons', (int) UserCoupons::create([
            'user_id' => $this->userId, 'coupon_id' => $coupon->id, 'status' => 0,
        ])->id);

        [, $data] = $this->storeOrder(['coupon_id' => (string) $coupon->id]);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $orderId = (int) $data['data']['order_id'];
        $this->track('erik_orders', $orderId);

        $order = Orders::find($orderId);
        $this->assertEquals(60.00, (float) $order->total_amount);
        $this->assertEquals(5.00, (float) $order->discount_amount);
        $this->assertEquals(55.00, (float) $order->pay_amount);

        $uc = UserCoupons::where('user_id', $this->userId)->first();
        $this->assertSame(1, (int) $uc->status);
        $this->assertSame($orderId, (int) $uc->used_order_id);
        $this->assertSame(1, (int) Coupons::find($coupon->id)->used_qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cancel_restores_stock_and_rejects_double_cancel(): void
    {
        $skuId = $this->seedSku(15.00, 4);
        $this->addCart($skuId, 3);
        [, $data] = $this->storeOrder();
        $orderId = (int) $data['data']['order_id'];
        $this->track('erik_orders', $orderId);
        $this->assertSame(1, (int) ProductSkus::find($skuId)->stock);   // 4-3

        $hash = HashidsHelper::encode($orderId);
        $req = $this->makeRequest('POST', "/api/orders/{$hash}/cancel");
        $req->userId = $this->userId;
        $controller = new OrderController();
        $res = $controller->cancel($req, $hash);
        $this->assertSame(0, json_decode($res->rawBody(), true)['code']);

        $order = Orders::find($orderId);
        $this->assertSame(5, (int) $order->status);                       // 已取消
        $this->assertSame(4, (int) ProductSkus::find($skuId)->stock);     // 库存恢复
        $inbound = InventoryLogs::where('sku_id', $skuId)->where('type', 'inbound')->first();
        $this->assertNotNull($inbound);
        $this->assertSame(3, (int) $inbound->quantity);
        $this->assertSame(4, (int) $inbound->balance_after);

        // 二次取消：原子门闩拒绝，不重复回补
        $res2 = $controller->cancel($req, $hash);
        $this->assertSame(422, json_decode($res2->rawBody(), true)['code']);
        $this->assertSame(4, (int) ProductSkus::find($skuId)->stock);
        $this->assertSame(1, InventoryLogs::where('sku_id', $skuId)->where('type', 'inbound')->count());
    }
}
