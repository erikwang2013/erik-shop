<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests\Integration;

use app\common\PaymentSuccessService;
use app\model\OrderItems;
use app\model\Orders;
use app\model\Payments;
use app\model\PlatformSettlements;
use app\model\ProductSkus;
use app\model\Products;
use PHPUnit\Framework\Attributes\Test;
use support\Db;
use tests\IntegrationTestCase;

/**
 * 支付成功入账回归测试：订单/支付状态、分账、销量、幂等
 */
class PaymentSuccessServiceTest extends IntegrationTestCase
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
                Db::table('shop_platform_settlements')->whereIn('order_id', $this->orderIds)->delete();
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
    private function seedSku(): array
    {
        $product = Products::create(['title' => 'QA Pay ' . uniqid(), 'status' => 2]);
        $this->track('shop_products', (int) $product->id);
        $sku = ProductSkus::create([
            'product_id' => $product->id,
            'sku_code' => 'QAP' . strtoupper(bin2hex(random_bytes(6))),
            'default_price' => '10.00',
            'stock' => 10,
            'status' => 1,
        ]);
        $this->track('shop_product_skus', (int) $sku->id);
        return [(int) $product->id, (int) $sku->id];
    }

    private function seedOrder(int $status, string $payAmount): Orders
    {
        $order = Orders::create([
            'order_no' => 'QAP' . strtoupper(bin2hex(random_bytes(8))),
            'user_id' => random_int(100000000000000000, 999999999999999999),
            'status' => $status,
            'total_amount' => $payAmount,
            'pay_amount' => $payAmount,
            'currency_code' => 'USD',
        ]);
        $this->orderIds[] = (int) $order->id;
        $this->track('shop_orders', (int) $order->id);
        return $order;
    }

    private function seedItem(Orders $order, int $productId, int $skuId, int $quantity, string $price): void
    {
        OrderItems::create([
            'order_id' => $order->id,
            'product_id' => $productId,
            'sku_id' => $skuId,
            'title' => 'QA Pay Item',
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => bcmul($price, (string) $quantity, 2),
        ]);
    }

    private function seedPayment(Orders $order, string $gateway = 'stripe', int $status = 0, string $transactionNo = ''): Payments
    {
        $payment = Payments::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'gateway' => $gateway,
            'transaction_no' => $transactionNo,
            'amount' => $order->pay_amount,
            'currency_code' => $order->currency_code,
            'status' => $status,
        ]);
        $this->track('shop_payments', (int) $payment->id);
        return $payment;
    }

    #[Test]
    public function apply_marks_order_paid_creates_settlement_and_adds_sales(): void
    {
        [$productId, $skuId] = $this->seedSku();
        $order = $this->seedOrder(0, '100.00');
        $this->seedItem($order, $productId, $skuId, 2, '50.00');
        $payment = $this->seedPayment($order);

        $txnId = 'qa_txn_' . uniqid('', true);
        $this->assertTrue(PaymentSuccessService::apply($payment, $txnId));

        $order->refresh();
        $this->assertSame(1, (int) $order->status);
        $this->assertSame('stripe', $order->pay_method);
        $this->assertNotNull($order->pay_at);

        $payment->refresh();
        $this->assertSame(1, (int) $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame($txnId, $payment->transaction_no);

        $settlement = PlatformSettlements::where('order_id', $order->id)->first();
        $this->assertNotNull($settlement);
        $this->assertSame((int) $payment->id, (int) $settlement->payment_id);
        $this->assertSame('100.00', $settlement->total_amount);
        $this->assertSame('5.00', $settlement->platform_fee);          // 5% 平台佣金
        $this->assertSame('5.00', $settlement->platform_fee_rate);
        $this->assertSame('3.20', $settlement->payment_gateway_fee);   // 2.9% + 0.30
        $this->assertSame('91.80', $settlement->supplier_amount);
        $this->assertSame('USD', $settlement->currency_code);
        $this->assertSame(0, (int) $settlement->status);

        $this->assertSame(2, (int) ProductSkus::find($skuId)->sales_count);
        $this->assertSame(2, (int) Products::find($productId)->sales_count);
    }

    #[Test]
    public function repeated_apply_returns_false_without_duplicate_side_effects(): void
    {
        [$productId, $skuId] = $this->seedSku();
        $order = $this->seedOrder(0, '100.00');
        $this->seedItem($order, $productId, $skuId, 2, '50.00');
        $payment = $this->seedPayment($order);

        $this->assertTrue(PaymentSuccessService::apply($payment, 'qa_txn_' . uniqid('', true)));
        // 同一对象（内存 status 已更新）与重新读取的对象都必须拒绝
        $this->assertFalse(PaymentSuccessService::apply($payment, 'qa_txn_other'));
        $this->assertFalse(PaymentSuccessService::apply(Payments::find($payment->id), 'qa_txn_other'));

        $this->assertSame(1, PlatformSettlements::where('order_id', $order->id)->count());
        $this->assertSame(2, (int) ProductSkus::find($skuId)->sales_count);
        $this->assertSame(2, (int) Products::find($productId)->sales_count);
    }

    #[Test]
    public function order_status_latch_rejects_second_pending_payment_for_paid_order(): void
    {
        [$productId, $skuId] = $this->seedSku();
        $order = $this->seedOrder(1, '100.00');   // 订单已是已付款
        $this->seedItem($order, $productId, $skuId, 1, '100.00');
        $payment = $this->seedPayment($order, 'stripe', 0, 'qa_pre_' . uniqid('', true));

        $this->assertFalse(PaymentSuccessService::apply($payment, 'qa_txn_' . uniqid('', true)));

        $this->assertSame(0, PlatformSettlements::where('order_id', $order->id)->count());
        $this->assertSame(0, (int) ProductSkus::find($skuId)->sales_count);
        $this->assertSame(0, (int) Products::find($productId)->sales_count);
        $this->assertSame(0, (int) Payments::find($payment->id)->status);
        $this->assertSame(1, (int) Orders::find($order->id)->status);
    }

    #[Test]
    public function apply_keeps_existing_transaction_no(): void
    {
        [$productId, $skuId] = $this->seedSku();
        $order = $this->seedOrder(0, '10.00');
        $this->seedItem($order, $productId, $skuId, 1, '10.00');
        $existing = 'qa_existing_' . uniqid('', true);
        $payment = $this->seedPayment($order, 'stripe', 0, $existing);

        $this->assertTrue(PaymentSuccessService::apply($payment, 'qa_gateway_' . uniqid('', true)));

        $this->assertSame($existing, Payments::find($payment->id)->transaction_no);
    }

    #[Test]
    public function settlement_fees_round_after_aggregation(): void
    {
        [$productId, $skuId] = $this->seedSku();
        $order = $this->seedOrder(0, '19.99');
        $this->seedItem($order, $productId, $skuId, 1, '19.99');
        $payment = $this->seedPayment($order);

        $this->assertTrue(PaymentSuccessService::apply($payment, 'qa_txn_' . uniqid('', true)));

        $settlement = PlatformSettlements::where('order_id', $order->id)->first();
        $this->assertNotNull($settlement);
        $this->assertSame('19.99', $settlement->total_amount);
        $this->assertSame('1.00', $settlement->platform_fee);          // 19.99*5% = 0.9995 → 1.00
        $this->assertSame('0.88', $settlement->payment_gateway_fee);   // 0.57971 + 0.30 = 0.87971 → 0.88
        $this->assertSame('18.11', $settlement->supplier_amount);
    }
}
