<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use app\model\Countries;
use app\model\OrderItems;
use app\model\Orders;
use app\model\ProductSkuPrices;
use app\model\ProductSkus;
use app\model\SubscriptionOrders;
use app\model\Subscriptions;

/**
 * 订阅建单（首期与自动续费共用）
 *
 * 不自行开启事务：调用方负责事务边界与订阅状态流转，
 * 这样建单、next_billing_at、订阅日志可以同生共死。
 */
class SubscriptionOrderService
{
    /**
     * 为订阅生成一期订单（待付款）
     *
     * @param Subscriptions $subscription   订阅
     * @param array         $addressSnapshot 收货地址快照（来自用户地址或上一期订单）
     * @param int           $cycle           期数（首期 1，续费由调用方按已生成期数传入）
     * @throws BusinessException SKU 下架/库存不足
     */
    public static function createOrder(Subscriptions $subscription, array $addressSnapshot, int $cycle = 1): Orders
    {
        $sku = ProductSkus::with('product')
            ->where('id', $subscription->sku_id)
            ->where('status', 1)
            ->first();
        if (!$sku) {
            throw new BusinessException('SKU 不存在或已下架');
        }
        if (!$sku->product || (int) $sku->product->status !== 2) {
            throw new BusinessException('SKU 已下架或商品不可售');
        }

        // decimal 列返回字符串，保留字符串参与 Money 运算
        $quantity = max(1, (int) $subscription->quantity);
        $price = ProductSkuPrices::where('sku_id', $sku->id)
            ->where('currency_code', 'USD')
            ->value('price');
        if ($price === null) {
            // 不回退 default_price（CNY），避免按错误币种金额建单
            throw new BusinessException('该商品暂不支持 USD 订阅');
        }
        $price = (string) $price;
        $subtotal = Money::round(Money::mul($price, (string) $quantity));

        // 重量 = 商品重量 × 数量（克，向上取整）；商品未维护重量时回退 500 克
        $weightGrams = $sku->product ? (int) ceil((float) $sku->product->weight * $quantity) : 0;
        if ($weightGrams <= 0) {
            $weightGrams = 500;
        }
        $weightGrams = max(1, $weightGrams);

        $country = Countries::find($addressSnapshot['country_id'] ?? 0);
        $shippingFee = $country ? OrderPricing::shipping($country, $weightGrams) : '0.00';
        $taxAmount = $country ? OrderPricing::tax($country, [(string) $sku->product_id => $subtotal]) : '0.00';
        $payAmount = Money::round(Money::add(Money::add($subtotal, $shippingFee), $taxAmount));

        // 原子扣减库存（条件 stock >= quantity，并发下不会超卖）
        $affected = ProductSkus::where('id', $sku->id)
            ->where('status', 1)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);
        if (!$affected) {
            throw new BusinessException('库存不足');
        }

        $order = Orders::create([
            'order_no' => 'SUB' . date('Ymd') . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'user_id' => $subscription->user_id,
            'status' => 0,
            'currency_code' => 'USD',
            'total_amount' => $subtotal,
            'shipping_fee' => $shippingFee,
            'tax_amount' => $taxAmount,
            'pay_amount' => $payAmount,
            'address_snapshot' => $addressSnapshot,
        ]);

        // 库存流水（不可变账本：订阅下单出库）
        InventoryLogger::log(
            (int) $sku->id,
            'outbound',
            -$quantity,
            (int) ProductSkus::where('id', $sku->id)->value('stock'),
            'order',
            (int) $order->id,
            (int) $subscription->user_id,
            '订阅下单扣减库存'
        );

        OrderItems::create([
            'order_id' => $order->id,
            'product_id' => $sku->product_id,
            'sku_id' => $sku->id,
            'title' => '周期购 SKU ' . $sku->sku_code,
            'image' => $sku->image,
            'sku_attrs_snapshot' => $sku->attrs,
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
        ]);

        SubscriptionOrders::create([
            'subscription_id' => $subscription->id,
            'order_id' => $order->id,
            'billing_cycle' => $cycle,
            'status' => 'success',
        ]);

        return $order;
    }
}
