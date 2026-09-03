<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\DistributedLock;
use app\common\Money;
use app\model\Orders;
use app\model\Payments;
use app\model\Refunds;
use Webman\Http\Request;

/**
 * 用户退款申请（仅已付款订单，写入待审退款单，由 admin 审核）
 */
class RefundController extends \app\controller\BaseApiController
{
    /**
     * 申请退款
     * POST /api/refunds  {order_id, amount, reason}
     * 仅已付款(status=1)订单可申请；退款金额 ≤ 可退余额（实付 - 已退 - 在审）
     */
    public function apply(Request $request): \support\Response
    {
        $userId = (int) $request->userId;
        $orderId = $this->decodedId((string) $request->input('order_id', ''));
        // 请求金额入参：Money::normalize 归一分位字符串（拒绝科学计数等 bcmath 不接受的形态），再参与十进制校验/入库
        $amount = Money::normalize($request->input('amount', 0));
        $reason = (string) $request->input('reason', '');

        if ($orderId === '' || Money::cmp($amount, '0') <= 0) {
            return ApiResponse::fail('订单与退款金额不能为空', 422);
        }
        if (mb_strlen($reason) > 256) {
            return ApiResponse::fail('退款原因过长', 422);
        }

        // 并发锁：查支付记录、算可退余额、校验与创建退款单需在同一订单锁内完成，防止并发申请各自读到同一在审余额
        try {
            return DistributedLock::run('lock:refund:' . $orderId, function () use ($userId, $orderId, $amount, $reason) {
                // 锁内重查订单，防止锁前读到旧状态
                $order = Orders::where('id', $orderId)->where('user_id', $userId)->first();
                if (!$order) {
                    return ApiResponse::fail('订单不存在', 404);
                }
                // 已付款(1)或部分退款中(6)可继续申请剩余额度；已退款(7)由可退余额校验兜底
                if (!in_array((int) $order->status, [1, 6], true)) {
                    return ApiResponse::fail('仅已付款订单可申请退款', 422);
                }

                $payment = Payments::where('order_id', $order->id)->where('status', 1)->first();
                if (!$payment) {
                    return ApiResponse::fail('该订单无已支付记录，无法退款', 422);
                }

                // 可退余额 = 实付 - 已退(status=3) - 在审(status=0/1)，驳回(status=2)不占额度
                $pending = (string) (Refunds::where('order_id', $order->id)->whereIn('status', [0, 1])->sum('amount') ?? 0);
                $refundable = Money::round(Money::sub(Money::sub((string) $payment->amount, (string) $payment->refunded_amount), $pending));
                // 超限校验保留原 1 分容差，改十进制比较；提示文案 max(0) 为纯展示层格式
                if (Money::cmp($amount, Money::add($refundable, '0.01')) > 0) {
                    return ApiResponse::fail('退款金额超过可退余额（剩余可退 ' . number_format(max((float) $refundable, 0.0), 2) . '）', 422);
                }

                $refund = Refunds::create([
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'refund_no' => 'R' . date('YmdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
                    'type' => 1,
                    'amount' => $amount,
                    'reason' => $reason,
                    'status' => 0, // 待审
                ]);

                return ApiResponse::success([
                    'refund_id' => $refund->id,
                    'refund_no' => $refund->refund_no,
                ], '退款申请已提交，等待审核');
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('操作繁忙，请稍后重试', 429);
        }
    }

    /**
     * 我的退款申请列表（分页）
     * GET /api/refunds?page=&per_page=
     */
    public function index(Request $request): \support\Response
    {
        $userId = (int) $request->userId;
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

        $query = Refunds::where('user_id', $userId);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $perPage)->get()->toArray();

        return ApiResponse::paginate($items, $total, $page, $perPage);
    }

    /**
     * 退款申请详情（限本人）
     * GET /api/refunds/{id}
     */
    public function show(Request $request, string $id): \support\Response
    {
        $userId = (int) $request->userId;
        $refund = Refunds::where('id', $this->decodedId($id))->where('user_id', $userId)->first();
        if (!$refund) {
            return ApiResponse::fail('退款记录不存在', 404);
        }

        return ApiResponse::success($refund->toArray());
    }
}
