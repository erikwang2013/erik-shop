<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\DistributedLock;
use app\model\Orders;
use app\model\OrderItems;
use app\model\ProductSkus;
use app\model\ProductSkuPrices;
use app\model\Subscriptions;
use app\model\SubscriptionOrders;
use app\model\SubscriptionLogs;
use support\Db;
use Webman\Http\Request;

/**
 * 订阅周期购 API
 *
 * 背景：erik_subscriptions 等表已建但无任何控制器/路由（docs/PLAN-RESEARCH.md §1 差距）。
 * 最小可用实现：创建订阅（立即生成首期订单待付款）、我的订阅列表、取消订阅。
 * 后续自动续费由 SubscriptionCron 处理（另行排期）。
 */
class SubscriptionController extends \app\controller\BaseApiController
{
    /**
     * 创建订阅（立即生成首期订单）
     * POST /api/subscriptions  {sku_id, interval_days: 30|60|90, quantity?}
     */
    public function store(Request $request): \support\Response
    {
        $userId = $request->userId;
        $skuId = $request->input('sku_id');
        $intervalDays = (int) $request->input('interval_days', 30);
        $quantity = max(1, (int) $request->input('quantity', 1));

        if (!in_array($intervalDays, [30, 60, 90], true)) {
            return ApiResponse::fail('interval_days 仅支持 30/60/90', 422);
        }
        $sku = ProductSkus::where('id', $skuId)->where('status', 1)->first();
        if (!$sku) {
            return ApiResponse::fail('SKU 不存在或已下架', 404);
        }

        try {
            // 用户粒度防重锁：同用户并发创建订阅会生成重复订阅+首期订单
            $result = DistributedLock::run("lock:subscribe:{$userId}", fn () => Db::transaction(function () use ($userId, $sku, $intervalDays, $quantity) {
                // 创建订阅
                $subscription = Subscriptions::create([
                    'user_id' => $userId,
                    'sku_id' => $sku->id,
                    'interval_days' => $intervalDays,
                    'quantity' => $quantity,
                    'next_billing_at' => date('Y-m-d', strtotime("+{$intervalDays} days")),
                    'gateway' => '',
                    'status' => 'active',
                ]);

                // 首期订单（简化：按 sku 默认币种价格直接建单，不走购物车）
                $price = ProductSkuPrices::where('sku_id', $sku->id)->where('currency_code', 'USD')->value('price');
                $price = (float) ($price ?? $sku->default_price);
                $subtotal = round($price * $quantity, 2);
                $order = Orders::create([
                    'order_no' => 'SUB' . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 8)),
                    'user_id' => $userId,
                    'status' => 0,
                    'currency_code' => 'USD',
                    'total_amount' => $subtotal,
                    'pay_amount' => $subtotal,
                    'address_snapshot' => [],
                ]);
                OrderItems::create([
                    'order_id' => $order->id,
                    'product_id' => $sku->product_id,
                    'sku_id' => $sku->id,
                    'title' => '周期购 SKU ' . $sku->sku_code,
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ]);

                SubscriptionOrders::create([
                    'subscription_id' => $subscription->id,
                    'order_id' => $order->id,
                    'billing_cycle' => 1,
                    'status' => 'success',
                ]);
                SubscriptionLogs::create([
                    'subscription_id' => $subscription->id,
                    'action' => 'activate',
                    'remark' => '创建订阅，生成首期订单 ' . $order->order_no,
                ]);

                return [
                    'subscription_id' => $subscription->id,
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'next_billing_at' => $subscription->next_billing_at,
                    'first_amount' => $subtotal,
                ];
            }));
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('操作繁忙，请稍后重试', 429);
        } catch (\Throwable $e) {
            \support\Log::error('创建订阅失败: ' . $e->getMessage(), ['user_id' => $userId, 'trace' => $e->getTraceAsString()]);
            return ApiResponse::fail('创建订阅失败，请稍后重试', 500);
        }

        return ApiResponse::success($result, '订阅创建成功');
    }

    /**
     * 我的订阅列表
     * GET /api/subscriptions
     */
    public function index(Request $request): \support\Response
    {
        $userId = $request->userId;
        $list = Subscriptions::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'sku_id' => $s->sku_id,
                'interval_days' => $s->interval_days,
                'quantity' => $s->quantity,
                'next_billing_at' => $s->next_billing_at,
                'status' => $s->status,
                'created_at' => $s->created_at,
            ]);
        return ApiResponse::success(['list' => $list]);
    }

    /**
     * 取消订阅
     * POST /api/subscriptions/{id}/cancel
     */
    public function cancel(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        $userId = $request->userId;

        // 订阅粒度锁：串行化取消/续订等状态变更，防止并发双取消重复写日志
        try {
            return DistributedLock::run("lock:subscribe:{$id}", function () use ($id, $userId) {
                $subscription = Subscriptions::where('id', $id)->where('user_id', $userId)->first();
                if (!$subscription) {
                    return ApiResponse::fail('订阅不存在', 404);
                }
                if ($subscription->status !== 'active') {
                    return ApiResponse::fail('仅进行中的订阅可取消', 422);
                }

                $subscription->status = 'cancelled';
                $subscription->cancelled_at = date('Y-m-d H:i:s');
                $subscription->save();
                SubscriptionLogs::create([
                    'subscription_id' => $subscription->id,
                    'action' => 'cancel',
                    'remark' => '用户取消订阅',
                ]);

                return ApiResponse::success(null, '订阅已取消');
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('操作繁忙，请稍后重试', 429);
        }
    }
}
