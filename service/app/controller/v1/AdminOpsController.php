<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\HashidsHelper;
use app\common\PaymentGateway as Gateway;
use app\model\OrderLogs;
use app\model\Orders;
use app\model\Payments;
use app\model\Refunds;
use support\Db;
use Webman\Http\Request;

/**
 * 管理后台内部操作接口（需 X-Admin-Key 密钥）
 * 仅被 admin 后台调用，不面向终端用户
 */
class AdminOpsController extends \app\controller\BaseApiController
{
    /**
     * 执行真实退款
     * POST /api/admin/refunds/{id}/execute
     * 仅处理待审(0)/通过(1)的退款单，驳回(2)/已退款(3)拒绝执行
     */
    public function executeRefund(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        $refund = Refunds::where('id', $id)->first();
        if (!$refund) {
            return ApiResponse::fail('退款单不存在', 404);
        }
        if (!in_array((int) $refund->status, [0, 1], true)) {
            return ApiResponse::fail('仅待审或已通过的退款单可执行退款', 422);
        }

        $order = Orders::find($refund->order_id);
        if (!$order) {
            return ApiResponse::fail('订单不存在', 404);
        }

        $payment = Payments::where('order_id', $order->id)->where('status', 1)->first();
        if (!$payment) {
            return ApiResponse::fail('该订单无已支付记录，无法退款', 422);
        }

        $amount = min((float) $refund->amount, (float) $payment->amount);
        $oldOrderStatus = (int) $order->status;

        try {
            $gatewayObj = Gateway::make($payment->gateway);

            // PayPal 退款需 capture id（transaction_no 保存的是订单号），先解析
            $txnId = $payment->transaction_no;
            if ($payment->gateway === 'paypal') {
                $txnId = $gatewayObj->resolveCaptureId($txnId);
            }

            $result = $gatewayObj->refundPayment($txnId, $amount, $payment->currency_code);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::fail('不支持的支付网关: ' . $payment->gateway, 422);
        } catch (\Throwable $e) {
            \support\Log::error('退款执行失败 [refund=' . $refund->refund_no . ']: ' . $e->getMessage(), [
                'refund_id' => $refund->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::fail('网关退款失败，请查看日志', 502);
        }

        // 事务落库：退款单 + 支付记录 + 订单状态 + 操作日志
        try {
            Db::transaction(function () use ($refund, $payment, $order, $amount, $result, $oldOrderStatus) {
                $refund->status = 3;
                $refund->refunded_at = date('Y-m-d H:i:s');
                $refund->save();

                $payment->status = 2;
                $payment->refunded_amount = $amount;
                $payment->save();

                $order->status = 7;
                $order->refunded_at = date('Y-m-d H:i:s');
                $order->save();

                OrderLogs::create([
                    'order_id' => $order->id,
                    'from_status' => $oldOrderStatus,
                    'to_status' => 7,
                    'operator' => 'admin',
                    'remark' => '退款执行完成，网关交易号 ' . ($result['refund_id'] ?? ''),
                ]);
            });
        } catch (\Throwable $e) {
            \support\Log::error('退款落库失败: ' . $e->getMessage(), [
                'refund_id' => $refund->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::fail('退款已提交但本地记录失败，请核对网关', 500);
        }

        return ApiResponse::success([
            'refund_id' => $refund->id,
            'refund_no' => $refund->refund_no,
            'refund_amount' => $amount,
            'gateway_refund_id' => $result['refund_id'] ?? '',
        ], '退款执行成功');
    }

    /**
     * 风控订单审核（放行/驳回）
     * POST /api/admin/orders/{id}/review  {action: approve|reject, remark?}
     * 仅 status=8（待审核）订单可流转：approve → 0（待付款）/ reject → 5（已取消）
     */
    public function reviewOrder(Request $request, string $id): \support\Response
    {
        $action = $request->input('action', 'approve');
        $remark = (string) $request->input('remark', '');
        if (!in_array($action, ['approve', 'reject'], true)) {
            return ApiResponse::fail('action 仅支持 approve/reject', 422);
        }

        // 注意：HashidsDecode 中间件的 setParams 对 webman 控制器方法参数不生效，
        // 路由参数 {id} 传入的是 hashid，此处需显式解码
        $id = HashidsHelper::decode($id) ?: $id;

        $order = Orders::where('id', $id)->first();
        if (!$order) {
            return ApiResponse::fail('订单不存在', 404);
        }
        if ((int) $order->status !== 8) {
            return ApiResponse::fail('订单不在待审核状态', 422);
        }

        try {
            Db::transaction(function () use ($order, $action, $remark) {
                $toStatus = $action === 'approve' ? 0 : 5;
                // 原子流转：仅 status=8 可流转，防并发重复审核
                $affected = Orders::where('id', $order->id)->where('status', 8)->update(['status' => $toStatus]);
                if (!$affected) {
                    throw new \RuntimeException('订单状态已变化，请刷新重试');
                }
                OrderLogs::create([
                    'order_id' => $order->id,
                    'from_status' => 8,
                    'to_status' => $toStatus,
                    'operator' => 'admin',
                    'remark' => ($action === 'approve' ? '风控审核通过' : '风控审核驳回') . ($remark !== '' ? '：' . $remark : ''),
                ]);
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            \support\Log::error('风控审核失败: ' . $e->getMessage(), ['order_id' => $id, 'trace' => $e->getTraceAsString()]);
            return ApiResponse::fail('审核失败，请稍后重试', 500);
        }

        return ApiResponse::success(null, $action === 'approve' ? '已放行，订单进入待付款' : '已驳回，订单已取消');
    }
}
