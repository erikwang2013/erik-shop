<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\model\Payments;
use app\model\Orders;
use app\model\PaymentGateways;
use app\model\PaymentGatewayMethods;
use app\model\PlatformSettlements;
use app\common\PaymentGateway as Gateway;
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

        // 调用真实支付网关
        $gatewayObj = Gateway::make($gateway);
        $result = $gatewayObj->createPayment([
            'amount' => $order->pay_amount,
            'currency' => $order->currency_code,
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'methods' => [$method],
        ]);

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
        $payment = Payments::find($id);
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
     */
    public function webhook(Request $request, string $gateway): \support\Response
    {
        $payload = $request->rawBody();
        $signature = $request->header('Stripe-Signature', '');

        $gatewayObj = Gateway::make($gateway);
        $verified = $gatewayObj->verifyWebhook($payload, $signature);
        if (!$verified) {
            return ApiResponse::fail('签名验证失败', 403);
        }

        // 解析事件
        $event = json_decode($payload, true);
        $eventType = $event['type'] ?? '';

        if ($eventType === 'payment_intent.succeeded') {
            $txnId = $event['data']['object']['id'] ?? '';

            // 更新支付记录
            $payment = Payments::where('transaction_no', $txnId)->first();
            if ($payment) {
                $payment->status = 1;
                $payment->paid_at = date('Y-m-d H:i:s');
                $payment->save();

                // 更新订单状态
                $order = Orders::find($payment->order_id);
                if ($order && $order->status === 0) {
                    $order->status = 1;
                    $order->pay_at = date('Y-m-d H:i:s');
                    $order->pay_method = $payment->gateway;
                    $order->save();

                    // 创建分账记录
                    $platformRate = config('payment.platform_rate', 5.0);
                    $gatewayFeeRate = config("payment.gateway_fee.{$gateway}.rate", 2.9);
                    $gatewayFixedFee = config("payment.gateway_fee.{$gateway}.fixed", 0.30);

                    PlatformSettlements::create([
                        'order_id' => $order->id,
                        'payment_id' => $payment->id,
                        'total_amount' => $order->pay_amount,
                        'platform_fee' => round($order->pay_amount * $platformRate / 100, 2),
                        'platform_fee_rate' => $platformRate,
                        'payment_gateway_fee' => round($order->pay_amount * $gatewayFeeRate / 100 + $gatewayFixedFee, 2),
                        'currency_code' => $order->currency_code,
                        'status' => 0,
                    ]);
                }
            }
        }

        return ApiResponse::success(null, 'ok');
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
