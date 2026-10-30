<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\BusinessException;
use app\common\LockTimeoutException;
use app\common\Money;
use app\common\DistributedLock;
use app\common\InventoryLogger;
use app\common\OrderCancellation;
use app\common\OrderPricing;
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

        // 与列表一致：补状态文本（5.2 详情返回完整订单信息）
        $order->status_text = [
            0 => '待付款', 1 => '已付款', 2 => '已发货',
            3 => '已收货', 4 => '已完成', 5 => '已取消',
            6 => '退款中', 7 => '已退款', 8 => '待审核',
        ][(int) $order->status] ?? '未知';

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

        // 事务内创建订单 + 明细 + 原子扣库存 + 清购物车，任一步失败整体回滚
        // 用户粒度防重锁：同用户串行下单，杜绝连点重复创建订单
        try {
            $result = DistributedLock::run("lock:order:{$userId}", fn () => Db::transaction(function () use ($userId, $address, $country, $currencyCode, $remark, $couponId, $request) {
                // 购物车在事务内加行锁读取：防止结算期间购物车被并发修改（改数量/删除）
                $cartItems = Carts::where('user_id', $userId)->where('selected', 1)->lockForUpdate()->get();
                if ($cartItems->isEmpty()) {
                    throw new BusinessException('购物车无可结算商品');
                }

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

                // 批量查询分币种价格，避免循环内 N+1；decimal 列返回字符串，保留参与 Money 运算
                $priceMap = ProductSkuPrices::whereIn('sku_id', $cartItems->pluck('sku_id'))
                    ->where('currency_code', $currencyCode)
                    ->pluck('price', 'sku_id')
                    ->toArray();

                // 批量预取 SKU（含商品），避免循环内 N+1 查询
                $skus = ProductSkus::with('product')
                    ->whereIn('id', $cartItems->pluck('sku_id'))
                    ->get()
                    ->keyBy('id');

                // 预加载优惠券：用于按适用范围累计“适用小计”
                $coupon = null;
                if (!empty($couponId)) {
                    $coupon = Coupons::find($couponId);
                    if (!$coupon) {
                        throw new BusinessException('优惠券不可用');
                    }
                }
                $scopeIds = ($coupon && is_array($coupon->scope_ids)) ? array_map('strval', $coupon->scope_ids) : [];

                $totalAmount = '0';
                $applicableSubtotal = '0';
                $productValues = [];
                $weightTotal = 0.0;

                foreach ($cartItems as $cart) {
                    $sku = $skus[$cart->sku_id] ?? null;
                    if (!$sku || !$sku->product) {
                        throw new BusinessException('SKU 不存在或已下架');
                    }
                    // 下架 SKU / 未上架商品不可售
                    if ((int) $sku->status !== 1 || (int) $sku->product->status !== 2) {
                        throw new BusinessException('SKU 已下架或商品不可售');
                    }

                    // 原子扣减库存（条件 stock >= quantity，并发下不会超卖）
                    $affected = ProductSkus::where('id', $sku->id)
                        ->where('stock', '>=', $cart->quantity)
                        ->decrement('stock', $cart->quantity);
                    if (!$affected) {
                        throw new BusinessException('库存不足');
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

                    // 必须有该币种报价：不回退 default_price，否则会按 CNY 默认价错收请求币种
                    if (!isset($priceMap[$sku->id])) {
                        throw new BusinessException('该币种暂无报价');
                    }
                    $price = (string) $priceMap[$sku->id];
                    $subtotal = Money::round(Money::mul($price, (string) $cart->quantity));

                    // 重量 = 商品重量 × 数量（克），商品未维护重量时整单回退请求值
                    $weightTotal += (float) $sku->product->weight * (int) $cart->quantity;

                    // 优惠券适用范围：只累计命中的商品行（使用已加载关联，避免额外查询）
                    if ($coupon) {
                        $inScope = match ($coupon->scope_type) {
                            'category' => in_array((string) $sku->product->category_id, $scopeIds, true),
                            'product' => in_array((string) $sku->product_id, $scopeIds, true),
                            default => true,
                        };
                        if ($inScope) {
                            $applicableSubtotal = Money::add($applicableSubtotal, $subtotal);
                        }
                    }

                    $productValues[$sku->product_id] = Money::add($productValues[$sku->product_id] ?? '0', $subtotal);

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

                    $totalAmount = Money::add($totalAmount, $subtotal);
                }

                // 克，向上取整；总重 <= 0（商品未维护重量）时才回退请求值，最终至少 1 克
                $weightGrams = (int) ceil($weightTotal);
                if ($weightGrams <= 0) {
                    $weightGrams = (int) $request->input('weight_grams', 500);
                }
                $weightGrams = max(1, $weightGrams);

                // ===== 真实计费（对齐 api.md 5.3 / features.md 3.3；先聚合后舍入） =====
                $totalAmount = Money::round($totalAmount);
                $applicableSubtotal = Money::round($applicableSubtotal);
                $discountAmount = '0.00';
                if ($coupon) {
                    $discountAmount = $this->applyCoupon($userId, $coupon, $applicableSubtotal, (string) ($country->iso_code_2 ?? ''));
                }
                $shippingFee = $country ? OrderPricing::shipping($country, $weightGrams) : '0.00';
                $taxAmount = $country ? OrderPricing::tax($country, $productValues) : '0.00';
                $payAmount = Money::round(Money::add(Money::add(Money::sub($totalAmount, $discountAmount), $shippingFee), $taxAmount));

                // 更新订单金额（discount/shipping/tax 字段已存在，此前从未写入）
                $order->total_amount = $totalAmount;
                $order->discount_amount = $discountAmount;
                $order->shipping_fee = $shippingFee;
                $order->tax_amount = $taxAmount;
                $order->pay_amount = $payAmount;
                $order->save();

                // ===== 风控打分：仅 block 模式下 review 才进入人工审核 =====
                // bypass 模式只记录分数/结果，不改变订单状态、不阻断下单
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
                if (config('risk.mode', 'bypass') === 'block' && $risk['result'] === 'review') {
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
                if ($coupon && Money::cmp($discountAmount, '0') > 0) {
                    UserCoupons::where('user_id', $userId)
                        ->where('coupon_id', $coupon->id)
                        ->where('status', 0)
                        ->update([
                            'status' => 1,
                            'used_at' => date('Y-m-d H:i:s'),
                            'used_order_id' => $order->id,
                        ]);
                    Coupons::where('id', $coupon->id)->increment('used_qty');
                }

                // 记录日志
                OrderLogs::create([
                    'order_id' => $order->id,
                    'to_status' => 0,
                    'operator' => 'user',
                    'remark' => '创建订单',
                ]);

                // 只删除本次锁到并已下单的购物车行，避免误删并发新加/选中的商品
                Carts::whereIn('id', $cartItems->pluck('id'))->delete();

                return [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    // JSON 输出边界 (float) 展示转换（已结算金额为字符串，不再参与运算）
                    'total_amount' => (float) $totalAmount,
                    'discount_amount' => (float) $discountAmount,
                    'shipping_fee' => (float) $shippingFee,
                    'tax_amount' => (float) $taxAmount,
                    'pay_amount' => (float) $payAmount,
                    'currency_code' => $currencyCode,
                ];
            }));
        } catch (BusinessException $e) {
            return ApiResponse::fail($e->getMessage(), 422);
        } catch (LockTimeoutException $e) {
            return ApiResponse::fail('操作太快，请稍后重试', 429);
        } catch (\Throwable $e) {
            // 数据库/系统异常不向客户端泄露细节
            \support\Log::error('订单创建失败: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::fail('订单创建失败，请稍后重试', 500);
        }

        return ApiResponse::success($result, '订单创建成功');
    }

    /**
     * 优惠券折扣计算（校验 + 计费），返回金额字符串
     * type: 1=满减(value=金额) / 2=折扣(value=折扣率%，如10=减10%) / 3=固定金额(value=金额)
     *
     * @param Coupons $coupon             已加载的优惠券
     * @param string  $applicableSubtotal 命中的商品行小计（按券的适用范围累计）
     * @param string  $destCountryIso2    目的国 ISO2
     */
    private function applyCoupon(int $userId, Coupons $coupon, string $applicableSubtotal, string $destCountryIso2): string
    {
        $userCoupon = UserCoupons::where('user_id', $userId)
            ->where('coupon_id', $coupon->id)
            ->where('status', 0)
            ->first();
        if (!$userCoupon) {
            throw new BusinessException('优惠券不存在或已使用');
        }

        if ((int) $coupon->status !== 1) {
            throw new BusinessException('优惠券不可用');
        }
        // 有效期
        $now = date('Y-m-d H:i:s');
        if (($coupon->start_at && $now < $coupon->start_at) || ($coupon->end_at && $now > $coupon->end_at)) {
            throw new BusinessException('优惠券不在有效期内');
        }
        // 无适用商品（券范围与购物车不匹配）
        if (Money::cmp($applicableSubtotal, '0') <= 0) {
            throw new BusinessException('优惠券不适用于当前商品');
        }
        // 满减门槛（对适用小计比较）
        if (Money::cmp((string) $coupon->min_amount, '0') > 0 && Money::cmp($applicableSubtotal, (string) $coupon->min_amount) < 0) {
            throw new BusinessException('未达到优惠券使用门槛');
        }
        // 区域限定：countries 非空时目的国 ISO2 必须在列
        $countries = is_array($coupon->countries) ? array_map(fn ($c) => strtoupper((string) $c), $coupon->countries) : [];
        if ($countries && !in_array(strtoupper($destCountryIso2), $countries, true)) {
            throw new BusinessException('优惠券不适用于该地区');
        }

        $value = (string) $coupon->value;
        $cap = Money::cmp($value, $applicableSubtotal) < 0 ? $value : $applicableSubtotal;   // min(value, 适用小计)
        $discount = match ((int) $coupon->type) {
            1 => $cap,                                                                        // 满减
            2 => Money::round(Money::div(Money::mul($applicableSubtotal, $value), '100')),    // 折扣率：适用小计×value%
            3 => $cap,                                                                        // 固定金额
            default => '0.00',
        };
        // 折扣不超过适用小计
        return Money::cmp($discount, $applicableSubtotal) < 0 ? $discount : $applicableSubtotal;
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
            OrderCancellation::cancel($order, [0], 'user', '用户取消订单');
        } catch (BusinessException $e) {
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
