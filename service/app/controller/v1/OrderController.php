<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\InventoryLogger;
use app\common\RiskEngine;
use app\model\Orders;
use app\model\OrderItems;
use app\model\OrderLogs;
use app\model\Carts;
use app\model\Countries;
use app\model\Coupons;
use app\model\UserCoupons;
use app\model\ProductSkus;
use app\model\ProductSkuPrices;
use app\model\ProductHsCodes;
use app\model\TariffRules;
use app\model\VatSettings;
use app\model\ShippingZones;
use app\model\ShippingZoneRates;
use app\model\UserAddresses;
use app\model\Users;
use app\model\UserKyc;
use support\Db;
use Webman\Http\Request;

class OrderController extends \app\controller\BaseApiController
{
    /**
     * 订单列表
     * GET /api/orders
     */
    public function index(Request $request): \support\Response
    {
        $userId = $request->userId;
        $status = $request->input('status');
        [$page, $perPage] = $this->clampPage($request);

        $query = Orders::where('user_id', $userId);
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $paginator = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($order) {
            $order->makeHidden(['deleted_at', 'address_snapshot']);
            // 状态文本
            $order->status_text = [
                0 => '待付款', 1 => '已付款', 2 => '已发货',
                3 => '已收货', 4 => '已完成', 5 => '已取消',
                6 => '退款中', 7 => '已退款', 8 => '待审核',
            ][$order->status] ?? '未知';
            return $order;
        });

        return ApiResponse::paginate(
            $paginator->items(),
            $paginator->total(),
            $page,
            $perPage
        );
    }

    /**
     * 订单详情
     * GET /api/orders/{id}
     */
    public function show(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        $userId = $request->userId;

        $order = Orders::with(['items', 'logs', 'documents'])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$order) {
            return ApiResponse::fail('订单不存在', 404);
        }

        return ApiResponse::success($order);
    }

    /**
     * 创建订单（从购物车）
     * POST /api/orders
     */
    public function store(Request $request): \support\Response
    {
        $userId = $request->userId;
        $addressId = $request->input('address_id');
        $currencyCode = $request->input('currency_code', 'USD');
        $remark = $request->input('remark', '');
        $couponId = $request->input('coupon_id');
        // 包裹重量（克）；SKU 无重量字段，由客户端按购物车估算，缺省 500g
        $weightGrams = max(1, (int) $request->input('weight_grams', 500));

        // 验证地址
        $address = UserAddresses::where('id', $addressId)->where('user_id', $userId)->first();
        if (!$address) {
            return ApiResponse::fail('收货地址不存在', 422);
        }

        // 合规校验：禁售国家拦截 + KYC 必需市场校验
        $country = Countries::find($address->country_id);
        if ($country) {
            if (in_array($country->iso_code_2, config('country.blocked_countries', []))) {
                return ApiResponse::fail('商品无法配送到该国家/地区', 422);
            }
            if ($country->kyc_required || in_array($country->iso_code_2, config('country.kyc_required_countries', []))) {
                $kycOk = UserKyc::where('user_id', $userId)->where('status', 1)->exists();
                if (!$kycOk) {
                    return ApiResponse::fail('该国家/地区要求实名认证（KYC）后才能下单', 422);
                }
            }
        }

        // 获取购物车已选中商品
        $cartItems = Carts::where('user_id', $userId)->where('selected', 1)->get();
        if ($cartItems->isEmpty()) {
            return ApiResponse::fail('购物车无可结算商品', 422);
        }

        // 事务内创建订单 + 明细 + 原子扣库存 + 清购物车，任一步失败整体回滚
        try {
            $result = Db::transaction(function () use ($userId, $address, $country, $cartItems, $currencyCode, $remark, $couponId, $weightGrams, $request) {
                // 生成订单号
                $orderNo = 'ORD' . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 8));

                // 创建订单
                $order = Orders::create([
                    'order_no' => $orderNo,
                    'user_id' => $userId,
                    'status' => 0,   // 待付款
                    'currency_code' => $currencyCode,
                    'remark' => $remark,
                    'address_snapshot' => $address->toArray(),
                ]);

                // 批量查询分币种价格，避免循环内 N+1
                $priceMap = ProductSkuPrices::whereIn('sku_id', $cartItems->pluck('sku_id'))
                    ->where('currency_code', $currencyCode)
                    ->pluck('price', 'sku_id')
                    ->map(fn($p) => (float) $p)
                    ->toArray();

                $totalAmount = 0;

                // 批量预取 SKU（含商品标题），避免循环内 N+1 查询
                $skus = ProductSkus::with('product')
                    ->whereIn('id', $cartItems->pluck('sku_id'))
                    ->get()
                    ->keyBy('id');

                foreach ($cartItems as $cart) {
                    $sku = $skus[$cart->sku_id] ?? null;
                    if (!$sku) {
                        throw new \RuntimeException("SKU {$cart->sku_id} 不存在");
                    }

                    // 原子扣减库存（条件 stock >= quantity，并发下不会超卖）
                    $affected = ProductSkus::where('id', $sku->id)
                        ->where('stock', '>=', $cart->quantity)
                        ->decrement('stock', $cart->quantity);
                    if (!$affected) {
                        throw new \RuntimeException("SKU {$cart->sku_id} 库存不足");
                    }

                    // 库存流水（不可变账本：下单出库）
                    InventoryLogger::log(
                        (int) $sku->id,
                        'outbound',
                        -(int) $cart->quantity,
                        (int) ProductSkus::where('id', $sku->id)->value('stock'),
                        'order',
                        (int) $order->id,
                        $userId,
                        '下单扣减'
                    );

                    $price = $priceMap[$sku->id] ?? (float) $sku->default_price;
                    $subtotal = round($price * $cart->quantity, 2);

                    OrderItems::create([
                        'order_id' => $order->id,
                        'product_id' => $sku->product_id,
                        'sku_id' => $sku->id,
                        'title' => $sku->product->title ?? 'Product',
                        'image' => $sku->image,
                        'sku_attrs_snapshot' => $sku->attrs,
                        'price' => $price,
                        'quantity' => $cart->quantity,
                        'subtotal' => $subtotal,
                    ]);

                    $totalAmount += $subtotal;
                }

                // ===== 真实计费（对齐 api.md 5.3 / features.md 3.3） =====
                $totalAmount = round($totalAmount, 2);
                $discountAmount = 0.0;
                if (!empty($couponId)) {
                    $discountAmount = $this->applyCoupon($userId, $couponId, $totalAmount);
                }
                $shippingFee = $country ? $this->calcShipping($country, $weightGrams) : 0.0;
                $taxAmount = $country ? $this->calcTax($country, $cartItems, $skus, $priceMap) : 0.0;
                $payAmount = round($totalAmount - $discountAmount + $shippingFee + $taxAmount, 2);

                // 更新订单金额（discount/shipping/tax 字段已存在，此前从未写入）
                $order->total_amount = $totalAmount;
                $order->discount_amount = $discountAmount;
                $order->shipping_fee = $shippingFee;
                $order->tax_amount = $taxAmount;
                $order->pay_amount = $payAmount;
                $order->save();

                // ===== 风控旁路打分（config/risk.php，高分订单置 status=8 待审核） =====
                $userEmail = Users::where('id', $userId)->value('email') ?? '';
                $riskContext = [
                    'user_id' => $userId,
                    'ip' => $request->getRealIp(),
                    'email' => $userEmail,
                    'amount' => $payAmount,
                    'order_id' => $order->id,
                    'address_country_iso' => $country->iso_code_2 ?? '',
                    'ip_country' => $request->geoCountry ?? '',
                ];
                $risk = RiskEngine::score('order_create', $riskContext);
                RiskEngine::log('order_create', $riskContext, $risk);
                $order->risk_score = $risk['score'];
                $order->risk_result = $risk['result'];
                if ($risk['result'] === 'review') {
                    $order->status = 8;   // 待审核（人工审核通过前不可支付）
                    OrderLogs::create([
                        'order_id' => $order->id,
                        'to_status' => 8,
                        'operator' => 'risk',
                        'remark' => '风控标记：' . ($risk['score'] . '分 ' . json_encode($risk['details'], JSON_UNESCAPED_UNICODE)),
                    ]);
                }
                $order->save();

                // 核销优惠券（事务内，与订单同生共死）
                if (!empty($couponId) && $discountAmount > 0) {
                    UserCoupons::where('user_id', $userId)
                        ->where('coupon_id', $couponId)
                        ->where('status', 0)
                        ->update([
                            'status' => 1,
                            'used_at' => date('Y-m-d H:i:s'),
                            'used_order_id' => $order->id,
                        ]);
                    Coupons::where('id', $couponId)->increment('used_qty');
                }

                // 记录日志
                OrderLogs::create([
                    'order_id' => $order->id,
                    'to_status' => 0,
                    'operator' => 'user',
                    'remark' => '创建订单',
                ]);

                // 清空购物车已购买商品
                Carts::where('user_id', $userId)->where('selected', 1)->delete();

                return [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'total_amount' => $totalAmount,
                    'discount_amount' => $discountAmount,
                    'shipping_fee' => $shippingFee,
                    'tax_amount' => $taxAmount,
                    'pay_amount' => $payAmount,
                    'currency_code' => $currencyCode,
                ];
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            \support\Log::error('订单创建失败: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::fail('订单创建失败，请稍后重试', 500);
        }

        return ApiResponse::success($result, '订单创建成功');
    }

    /**
     * 优惠券折扣计算（校验 + 计费）
     * type: 1=满减(value=金额) / 2=折扣(value=折扣率%，如10=减10%) / 3=固定金额(value=金额)
     */
    private function applyCoupon(int $userId, string $couponId, float $subtotal): float
    {
        $userCoupon = UserCoupons::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->where('status', 0)
            ->first();
        if (!$userCoupon) {
            throw new \RuntimeException('优惠券不存在或已使用');
        }

        $coupon = Coupons::find($couponId);
        if (!$coupon || $coupon->status !== 1) {
            throw new \RuntimeException('优惠券不可用');
        }
        // 有效期
        $now = date('Y-m-d H:i:s');
        if (($coupon->start_at && $now < $coupon->start_at) || ($coupon->end_at && $now > $coupon->end_at)) {
            throw new \RuntimeException('优惠券不在有效期内');
        }
        // 满减门槛
        if ((float) $coupon->min_amount > 0 && $subtotal < (float) $coupon->min_amount) {
            throw new \RuntimeException('未达到优惠券使用门槛');
        }
        // 区域限定（countries JSON 非空时校验目的国——此处由调用方保证 country，简化按全量）
        $discount = match ((int) $coupon->type) {
            1 => min((float) $coupon->value, $subtotal),                    // 满减
            2 => round($subtotal * (float) $coupon->value / 100, 2),        // 折扣率
            3 => min((float) $coupon->value, $subtotal),                    // 固定金额
            default => 0.0,
        };
        return min($discount, $subtotal);
    }

    /**
     * 运费计算：目的国 → 物流分区 → 费率阶梯（取最低价物流商）
     */
    private function calcShipping(Countries $country, int $weightGrams): float
    {
        $zone = ShippingZones::where('status', 1)
            ->whereJsonContains('countries', $country->iso_code_2)
            ->first();
        if (!$zone) {
            return 0.0; // 无分区规则时不收运费（订单不阻断，运费后续可补）
        }
        $weightKg = $weightGrams / 1000;
        $rates = ShippingZoneRates::where('zone_id', $zone->id)
            ->where('weight_from', '<=', $weightKg)
            ->where(function ($q) use ($weightKg) {
                $q->where('weight_to', '>=', $weightKg)->orWhereNull('weight_to');
            })
            ->get();
        $minFee = null;
        foreach ($rates as $rate) {
            $fee = (float) $rate->price + $weightKg * (float) $rate->per_kg_price;
            $minFee = ($minFee === null) ? $fee : min($minFee, $fee);
        }
        return $minFee === null ? 0.0 : round($minFee, 2);
    }

    /**
     * 关税/VAT 估算：商品 HS Code → 目的国税率（无 HS 关联或税率规则则按 0）
     */
    private function calcTax(Countries $country, $cartItems, $skus, array $priceMap): float
    {
        $destCountryId = $country->id;
        $vat = VatSettings::where('country_id', $destCountryId)->first();
        $vatRate = (float) ($vat->vat_rate ?? 0);
        $dutyFreeThreshold = (float) ($vat->duty_free_threshold ?? 0);
        $vatFreeThreshold = (float) ($vat->vat_free_threshold ?? 0);

        $taxTotal = 0.0;
        // 按商品维度聚合申报价值（避免同商品多 SKU 重复查 HS）
        $productValues = [];
        foreach ($cartItems as $cart) {
            $sku = $skus[$cart->sku_id] ?? null;
            if (!$sku) {
                continue;
            }
            $price = $priceMap[$sku->id] ?? (float) $sku->default_price;
            $productValues[$sku->product_id] = ($productValues[$sku->product_id] ?? 0) + $price * (int) $cart->quantity;
        }

        foreach ($productValues as $productId => $value) {
            $hsCodeIds = ProductHsCodes::where('product_id', $productId)->pluck('hs_code_id');
            if ($hsCodeIds->isEmpty()) {
                continue;
            }
            $rule = TariffRules::where('dest_country_id', $destCountryId)
                ->whereIn('hs_code_id', $hsCodeIds)
                ->first();
            $dutyRate = (float) ($rule->duty_rate ?? 0);
            $duty = ($value >= $dutyFreeThreshold) ? round($value * $dutyRate / 100, 2) : 0.0;
            $vatAmount = (($value + $duty) >= $vatFreeThreshold) ? round(($value + $duty) * $vatRate / 100, 2) : 0.0;
            $taxTotal += $duty + $vatAmount;
        }
        return round($taxTotal, 2);
    }

    /**
     * 取消订单
     * POST /api/orders/{id}/cancel
     */
    public function cancel(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        $userId = $request->userId;

        $order = Orders::where('id', $id)->where('user_id', $userId)->first();
        if (!$order) {
            return ApiResponse::fail('订单不存在', 404);
        }

        if ($order->status !== 0) {
            return ApiResponse::fail('仅待付款订单可取消', 422);
        }

        try {
            Db::transaction(function () use ($order, $userId) {
                // 原子门闩：仅待付款订单可被本次取消，防止并发重复取消导致库存重复恢复
                $updated = Orders::where('id', $order->id)
                    ->where('status', 0)
                    ->update([
                        'status' => 5,
                        'canceled_at' => date('Y-m-d H:i:s'),
                    ]);
                if (!$updated) {
                    throw new \RuntimeException('订单状态已变化，请刷新后重试');
                }

                // 恢复库存
                $items = OrderItems::where('order_id', $order->id)->get();
                foreach ($items as $item) {
                    ProductSkus::where('id', $item->sku_id)->increment('stock', $item->quantity);
                    // 库存流水（不可变账本：取消回库）
                    InventoryLogger::log(
                        (int) $item->sku_id,
                        'inbound',
                        (int) $item->quantity,
                        (int) ProductSkus::where('id', $item->sku_id)->value('stock'),
                        'order',
                        (int) $order->id,
                        $userId,
                        '取消订单恢复库存'
                    );
                }

                OrderLogs::create([
                    'order_id' => $order->id,
                    'from_status' => 0,
                    'to_status' => 5,
                    'operator' => 'user',
                    'remark' => '用户取消订单',
                ]);
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            \support\Log::error('订单取消失败: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::fail('订单取消失败，请稍后重试', 500);
        }

        return ApiResponse::success(null, '订单已取消');
    }
}
