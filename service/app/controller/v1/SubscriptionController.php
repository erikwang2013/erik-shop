<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\BusinessException;
use app\common\DistributedLock;
use app\common\LockTimeoutException;
use app\common\SubscriptionOrderService;
use app\model\ProductSkus;
use app\model\Subscriptions;
use app\model\SubscriptionLogs;
use app\model\UserAddresses;
use support\Db;
use Webman\Http\Request;

/**
 * 订阅周期购 API
 *
 * 背景：shop_subscriptions 等表已建但无任何控制器/路由（docs/PLAN-RESEARCH.md §1 差距）。
 * 最小可用实现：创建订阅（立即生成首期订单待付款）、我的订阅列表、取消订阅。
 * 后续自动续费由 SubscriptionCron 处理（另行排期）。
 */
class SubscriptionController extends \app\controller\BaseApiController
{
    /**
     * 创建订阅（立即生成首期订单）
     * POST /api/subscriptions  {sku_id, interval_days: 30|60|90, quantity?, address_id}
     */
    public function store(Request $request): \support\Response
    {
        $userId = $request->userId;
        $skuId = $request->input('sku_id');
        $addressId = $request->input('address_id');
        $intervalDays = (int) $request->input('interval_days', 30);
        $quantity = max(1, (int) $request->input('quantity', 1));

        if (!in_array($intervalDays, [30, 60, 90], true)) {
            return ApiResponse::fail('interval_days 仅支持 30/60/90', 422);
        }
        // 订阅订单必须有收货地址：首期写入订单快照，后续续费复用
        $address = UserAddresses::where('id', $addressId)->where('user_id', $userId)->first();
        if (!$address) {
            return ApiResponse::fail('收货地址不存在', 422);
        }
        $sku = ProductSkus::where('id', $skuId)->where('status', 1)->first();
        if (!$sku) {
            return ApiResponse::fail('SKU 不存在或已下架', 404);
        }

        try {
            // 用户粒度防重锁：同用户并发创建订阅会生成重复订阅+首期订单
            $result = DistributedLock::run("lock:subscribe:{$userId}", fn () => Db::transaction(function () use ($userId, $sku, $address, $intervalDays, $quantity) {
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

                // 首期订单：统一走 SubscriptionOrderService（地址快照/运费/税费/原子扣库存）
                $order = SubscriptionOrderService::createOrder($subscription, $address->toArray(), 1);

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
                    // JSON 输出边界 (float) 展示转换
                    'first_amount' => (float) $order->pay_amount,
                ];
            }));
        } catch (BusinessException $e) {
            return ApiResponse::fail($e->getMessage(), 422);
        } catch (LockTimeoutException $e) {
            return ApiResponse::fail('操作太快，请稍后重试', 429);
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
                return Db::transaction(function () use ($id, $userId) {
                    // 行锁重查：与 SubscriptionCron 续费互斥，避免取消后订单又被续费生成
                    $subscription = Subscriptions::where('id', $id)->where('user_id', $userId)->lockForUpdate()->first();
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
            });
        } catch (LockTimeoutException $e) {
            return ApiResponse::fail('操作太快，请稍后重试', 429);
        } catch (\Throwable $e) {
            \support\Log::error('取消订阅失败: ' . $e->getMessage(), ['subscription_id' => $id, 'trace' => $e->getTraceAsString()]);
            return ApiResponse::fail('取消失败，请稍后重试', 500);
        }
    }
}
