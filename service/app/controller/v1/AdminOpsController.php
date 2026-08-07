<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
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
}
