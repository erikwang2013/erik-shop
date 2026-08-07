<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\process;

use app\model\Payments;
use GuzzleHttp\Client as HttpClient;
use Stripe\StripeClient;
use support\Log;
use Workerman\Worker;

/**
 * 支付对账 — 每6小时核对超过2小时仍处于待支付状态的订单
 * 网关凭据已配置时查询真实支付状态并同步（stripe/paypal），否则跳过并记录
 */
class PaymentReconcileCron
{
    private static int $interval = 21600;

    public function onWorkerStart(Worker $worker): void
    {
        while (true) {
            $start = microtime(true);
            try {
                self::run();
            } catch (\Throwable $e) {
                Log::error('PaymentReconcileCron 执行异常: ' . $e->getMessage());
            }
            $sleep = max(1, self::$interval - (int)(microtime(true) - $start));
            sleep($sleep);
        }
    }

    public static function run(): void
    {
        $stripeKey = (string) config('payment.stripe.secret_key', '');
        $paypalClientId = (string) config('payment.paypal.client_id', '');
        $paypalSecret = (string) config('payment.paypal.client_secret', '');
        if ($stripeKey === '' && $paypalClientId === '') {
            Log::info('PaymentReconcileCron 跳过：未配置 Stripe/PayPal 网关凭据');
            return;
        }

        $deadline = date('Y-m-d H:i:s', strtotime('-2 hours'));
        $payments = Payments::where('status', 0)
            ->where('transaction_no', '!=', '')
            ->where('created_at', '<', $deadline)
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();
        if ($payments->isEmpty()) {
            return;
        }

        $stripe = $stripeKey !== '' ? new StripeClient($stripeKey) : null;
        $http = $paypalClientId !== '' ? new HttpClient(['timeout' => 15]) : null;
        $reconciled = 0;

        foreach ($payments as $payment) {
            try {
                $status = null;
                if ($payment->gateway === 'stripe' && $stripe) {
                    $intent = $stripe->paymentIntents->retrieve($payment->transaction_no);
                    $status = $intent->status;
                } elseif ($payment->gateway === 'paypal' && $http) {
                    $base = 'https://api-m.' . (config('payment.paypal.mode', 'sandbox') === 'live' ? '' : 'sandbox.') . 'paypal.com';
                    $tokenResponse = $http->post($base . '/v1/oauth2/token', [
                        'auth' => [$paypalClientId, $paypalSecret],
                        'form_params' => ['grant_type' => 'client_credentials'],
                    ]);
                    $token = json_decode($tokenResponse->getBody(), true)['access_token'] ?? '';
                    if ($token !== '') {
                        $order = $http->get($base . '/v2/checkout/orders/' . $payment->transaction_no, [
                            'headers' => ['Authorization' => "Bearer {$token}"],
                        ]);
                        $status = json_decode($order->getBody(), true)['status'] ?? '';
                    }
                }
                if ($status === null) {
                    continue;
                }
                if ($status === 'succeeded' || $status === 'COMPLETED') {
                    $payment->status = 1;
                    $payment->paid_at = $payment->paid_at ?: date('Y-m-d H:i:s');
                    $payment->save();
                    $reconciled++;
                } elseif ($status === 'canceled' || $status === 'failed' || $status === 'requires_payment_method') {
                    $payment->status = 3;
                    $payment->save();
                    $reconciled++;
                }
            } catch (\Throwable $e) {
                Log::warning('PaymentReconcileCron 核对失败 txn=' . $payment->transaction_no . ': ' . $e->getMessage());
            }
        }
        Log::info("PaymentReconcileCron 完成，核对更新 {$reconciled} 笔支付");
    }
}
