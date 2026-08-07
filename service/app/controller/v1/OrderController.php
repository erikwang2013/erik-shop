<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Orders;
use app\model\OrderItems;
use app\model\OrderLogs;
use app\model\Carts;
use app\model\Countries;
use app\model\ProductSkus;
use app\model\ProductSkuPrices;
use app\model\UserAddresses;
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
        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 10), 50);

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
            $result = Db::transaction(function () use ($userId, $address, $cartItems, $currencyCode, $remark) {
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

                // 更新订单金额
                $order->total_amount = $totalAmount;
                $order->pay_amount = $totalAmount;
                $order->save();

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
     * 取消订单
     * POST /api/orders/{id}/cancel
     */
    public function cancel(Request $request, string $id): \support\Response
    {
        $userId = $request->userId;

        $order = Orders::where('id', $id)->where('user_id', $userId)->first();
        if (!$order) {
            return ApiResponse::fail('订单不存在', 404);
        }

        if ($order->status !== 0) {
            return ApiResponse::fail('仅待付款订单可取消', 422);
        }

        try {
            Db::transaction(function () use ($order) {
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
