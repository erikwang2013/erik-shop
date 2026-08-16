<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\PaymentGateway as Gateway;
use app\common\RefundHelper;
use app\common\RiskEngine;
use app\model\OrderLogs;
use app\model\Orders;
use app\model\PaymentGateways;
use app\model\PaymentGatewayMethods;
use app\model\Payments;
use app\model\PlatformSettlements;
use app\model\Refunds;
use support\Db;
use Webman\Http\Request;

class PaymentController extends \app\controller\BaseApiController
{
    /**
     * 获取可用支付方式（按国家+币种）
     * GET /api/payment/methods?country=DE&currency=EUR
     */
    public function methods(Request $request): \support\Response
    {
        $country = $request->input('country', 'US');
        $currency = $request->input('currency', 'USD');

        $methods = PaymentGatewayMethods::where('status', 1)
            ->whereJsonContains('countries', $country)
            ->whereJsonContains('currencies', $currency)
            ->with('gateway')
            ->get()
            // 仅返回已实现网关，避免向前端暴露 Klarna/Adyen 等未实现配置
            ->filter(fn($m) => in_array($m->gateway->code ?? '', ['stripe', 'paypal']))
            ->map(fn($m) => [
                'id' => $m->id,
                'gateway' => $m->gateway->code ?? '',
                'gateway_name' => $m->gateway->name ?? '',
                'method_code' => $m->method_code,
                'method_name' => $this->methodName($m->method_code),
                'min_amount' => $m->min_amount,
                'max_amount' => $m->max_amount,
                'is_bnpl' => in_array($m->method_code, ['klarna_paylater', 'afterpay']),
            ]);

        return ApiResponse::success($methods);
    }

    /**
     * 创建支付
     * POST /api/payment/create
     */
    public function create(Request $request): \support\Response
    {
        $userId = $request->userId;
        $orderId = $request->input('order_id');
        $gateway = $request->input('gateway', 'stripe');
        $method = $request->input('method', 'card');

        $order = Orders::where('id', $orderId)->where('user_id', $userId)->first();
        if (!$order) {
            return ApiResponse::fail('订单不存在', 404);
        }

        if ($order->status !== 0) {
            return ApiResponse::fail('订单状态不可支付', 422);
        }

        // 创建支付记录
        $payment = Payments::create([
            'order_id' => $order->id,
            'user_id' => $userId,
            'gateway' => $gateway,
            'method' => $method,
            'amount' => $order->pay_amount,
            'currency_code' => $order->currency_code,
            'status' => 0,  // 待支付
        ]);

        // 风控旁路打分（支付事件，bypass 模式不阻断）
        $riskContext = [
            'user_id' => $userId,
            'ip' => $request->getRealIp(),
            'amount' => (float) $order->pay_amount,
            'order_id' => $order->id,
        ];
        RiskEngine::log('payment_create', $riskContext, RiskEngine::score('payment_create', $riskContext));

        // 调用支付网关
        try {
            $gatewayObj = Gateway::make($gateway);
            $result = $gatewayObj->createPayment([
                'amount' => $order->pay_amount,
                'currency' => $order->currency_code,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'methods' => [$method],
            ]);
        } catch (\InvalidArgumentException $e) {
            $payment->status = 3;
            $payment->save();
            return ApiResponse::fail('不支持的支付网关: ' . $gateway, 422);
        } catch (\Throwable $e) {
            $payment->status = 3;
            $payment->save();
            \support\Log::error('支付创建失败 [gateway=' . $gateway . ', order=' . $order->order_no . ']: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::fail('支付网关错误，请稍后重试', 500);
        }

        // 记录网关交易号
        $payment->transaction_no = $result['txn_id'];
        $payment->save();

        return ApiResponse::success([
            'payment_id' => $payment->id,
            'order_no' => $order->order_no,
            'amount' => $order->pay_amount,
            'currency' => $order->currency_code,
            'gateway' => $gateway,
            'method' => $method,
            'client_secret' => $result['client_secret'] ?? '',
            'txn_id' => $result['txn_id'],
        ], '支付创建成功');
    }

    /**
     * 查询支付状态
     * GET /api/payment/status/{id}
     */
    public function status(Request $request, string $id): \support\Response
    {
        $id = $this->decodedId($id);
        // 限定当前用户，防止越权查询他人支付记录
        $payment = Payments::where('id', $id)->where('user_id', $request->userId)->first();
        if (!$payment) {
            return ApiResponse::fail('支付记录不存在', 404);
        }

        $statusText = ['待支付', '已支付', '已退款', '失败'][$payment->status] ?? '未知';

        return ApiResponse::success([
            'status' => $payment->status,
            'status_text' => $statusText,
            'transaction_no' => $payment->transaction_no,
        ]);
    }

    /**
     * 支付Webhook回调
     * POST /webhook/payment/{gateway}
     * 覆盖成功/退款/失败事件，未识别事件显式记录支付日志
     */
    public function webhook(Request $request, string $gateway): \support\Response
    {
        $payload = $request->rawBody();
        $signature = $request->header('Stripe-Signature', '');
        $headers = $request->header();   // webman 以小写键返回全部 header，PayPal 验签字段从这读取

        try {
            $gatewayObj = Gateway::make($gateway);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::fail('未知支付网关', 404);
        }

        try {
            $verified = $gatewayObj->verifyWebhook($payload, $signature, $headers);
        } catch (\Throwable $e) {
            \support\Log::error("支付 webhook 验签异常 [{$gateway}]: " . $e->getMessage());
            return ApiResponse::fail('签名验证失败', 403);
        }
        if (!$verified) {
            return ApiResponse::fail('签名验证失败', 403);
        }

        // 解析事件并按类型分发：成功/退款/失败显式处理，未识别事件记录日志
        $event = json_decode($payload, true);
        $eventType = $event['type'] ?? '';
        $eventId = $event['id'] ?? '';

        try {
            switch ($eventType) {
                case 'payment_intent.succeeded':
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->handlePaymentSucceeded($event, $eventType);
                    break;
                case 'payment_intent.refunded':
                case 'PAYMENT.CAPTURE.REFUNDED':
                    $this->handlePaymentRefunded($event, $eventType);
                    break;
                case 'payment_intent.payment_failed':
                case 'PAYMENT.CAPTURE.DENIED':
                    $this->handlePaymentFailed($event, $eventType);
                    break;
                default:
                    \support\Log::info("支付 webhook 未处理事件 [{$gateway}] id={$eventId} type={$eventType}");
            }
        } catch (\Throwable $e) {
            \support\Log::error('支付 webhook 处理失败: ' . $e->getMessage(), [
                'gateway' => $gateway,
                'event' => $eventId,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::fail('webhook 处理失败', 500);
        }

        return ApiResponse::success(null, 'ok');
    }

    /**
     * 支付成功事件：订单置已付款 + 支付记录 + 分账（幂等：仅待付款记录可入账）
     */
    private function handlePaymentSucceeded(array $event, string $eventType): void
    {
        $txnId = '';
        if ($eventType === 'payment_intent.succeeded') {
            $txnId = $event['data']['object']['id'] ?? '';
        } elseif ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            // PayPal 捕获完成：优先按订单号匹配（create 时保存的 transaction_no 为订单号）
            $txnId = $event['data']['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
            if ($txnId === '') {
                $txnId = $event['data']['resource']['id'] ?? '';
            }
        }
        if ($txnId === '') {
            return;
        }

        $payment = Payments::where('transaction_no', $txnId)->first();
        if (!$payment || (int) $payment->status !== 0) {
            return; // 未知或已处理，幂等返回
        }

        Db::transaction(function () use ($payment) {
            // 原子门闩：仅待付款订单可被本次标记已支付，重复 webhook 不会重复入账
            $updated = Orders::where('id', $payment->order_id)
                ->where('status', 0)
                ->update([
                    'status' => 1,
                    'pay_at' => date('Y-m-d H:i:s'),
                    'pay_method' => $payment->gateway,
                ]);
            if (!$updated) {
                return;
            }

            $payment->status = 1;
            $payment->paid_at = date('Y-m-d H:i:s');
            $payment->save();

            $order = Orders::find($payment->order_id);
            $platformRate = config('payment.platform_rate', 5.0);
            $gatewayFeeRate = config("payment.gateway_fee.{$payment->gateway}.rate", 2.9);
            $gatewayFixedFee = config("payment.gateway_fee.{$payment->gateway}.fixed", 0.30);

            // 创建分账记录
            PlatformSettlements::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'total_amount' => $order->pay_amount,
                'platform_fee' => round($order->pay_amount * $platformRate / 100, 2),
                'platform_fee_rate' => $platformRate,
                'payment_gateway_fee' => round($order->pay_amount * $gatewayFeeRate / 100 + $gatewayFixedFee, 2),
                'supplier_amount' => max(0, round($order->pay_amount - $order->pay_amount * $platformRate / 100 - ($order->pay_amount * $gatewayFeeRate / 100 + $gatewayFixedFee), 2)),
                'affiliate_amount' => 0,
                'currency_code' => $order->currency_code,
                'status' => 0,
            ]);
        });
    }

    /**
     * 退款事件：落退款记录 + 联动支付/订单状态（同一事件重复投递不重复入账）
     */
    private function handlePaymentRefunded(array $event, string $eventType): void
    {
        $eventId = $event['id'] ?? '';
        if ($eventType === 'payment_intent.refunded') {
            // Stripe：amount_refunded 为累计已退金额，按与本地已退的差值增量入账
            $object = $event['data']['object'] ?? [];
            $txnId = (string) ($object['id'] ?? '');
            $refundedTotal = (float) ($object['amount_refunded'] ?? 0) / 100;
        } else {
            // PayPal：resource.amount 为单笔退款金额
            $resource = $event['data']['resource'] ?? [];
            $txnId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? $resource['capture_id'] ?? $resource['id'] ?? '');
            $refundedTotal = (float) ($resource['amount']['value'] ?? 0);
        }
        if ($txnId === '' || $refundedTotal <= 0 || $eventId === '') {
            return;
        }

        $payment = Payments::where('transaction_no', $txnId)->first();
        if (!$payment || (int) $payment->status !== 1) {
            return; // 无支付记录或未支付完成，忽略
        }

        // 增量口径：Stripe 累计值取差值，PayPal 单笔金额直接用
        $amount = $eventType === 'payment_intent.refunded'
            ? round($refundedTotal - (float) $payment->refunded_amount, 2)
            : $refundedTotal;

        // 事件幂等：refund_no 由事件 ID 派生，重复投递跳过（uk_refund_no 兜底防并发重复插入）
        $refundNo = 'WH' . substr(md5($eventId), 0, 29);
        if ($amount <= 0 || Refunds::where('refund_no', $refundNo)->exists()) {
            return;
        }

        Db::transaction(function () use ($payment, $refundNo, $amount) {
            $refund = Refunds::create([
                'order_id' => $payment->order_id,
                'user_id' => $payment->user_id,
                'refund_no' => $refundNo,
                'type' => 1,
                'amount' => $amount,
                'reason' => '网关退款回调',
                'status' => 3,
                'refunded_at' => date('Y-m-d H:i:s'),
            ]);
            RefundHelper::markRefunded($refund, $payment, $amount, 'system', '网关退款回调入账');
        });
    }

    /**
     * 支付失败事件：支付记录标记失败 + 订单日志（订单保持待付款，用户可重试）
     */
    private function handlePaymentFailed(array $event, string $eventType): void
    {
        if ($eventType === 'payment_intent.payment_failed') {
            $object = $event['data']['object'] ?? [];
            $txnId = (string) ($object['id'] ?? '');
            $error = (string) ($object['last_payment_error']['message'] ?? '');
        } else {
            // PAYMENT.CAPTURE.DENIED
            $resource = $event['data']['resource'] ?? [];
            $txnId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'] ?? '');
            $error = (string) ($resource['status_details']['reason'] ?? '');
        }
        if ($txnId === '') {
            return;
        }

        $payment = Payments::where('transaction_no', $txnId)->first();
        if (!$payment || (int) $payment->status !== 0) {
            return; // 未知或非待支付状态，忽略（幂等）
        }

        $payment->status = 3;
        $payment->gateway_data = json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);
        $payment->save();

        OrderLogs::create([
            'order_id' => $payment->order_id,
            'from_status' => 0,
            'to_status' => 0,
            'operator' => 'system',
            'remark' => '支付失败：' . ($error !== '' ? $error : '网关返回失败事件'),
        ]);
    }

    private function methodName(string $code): string
    {
        return [
            'card' => '信用卡/借记卡',
            'ideal' => 'iDEAL',
            'sofort' => 'SOFORT',
            'klarna_paylater' => 'Klarna先买后付',
            'afterpay' => 'Afterpay分期',
            'paypal' => 'PayPal',
            'bancontact' => 'Bancontact',
            'giropay' => 'Giropay',
            'eps' => 'EPS',
            'alipay' => '支付宝',
            'wechat_pay' => '微信支付',
        ][$code] ?? $code;
    }
}
