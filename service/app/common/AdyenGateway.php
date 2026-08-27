<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use GuzzleHttp\Client as HttpClient;

/**
 * Adyen 支付网关 — Guzzle 直连官方 API 最小可用骨架
 *
 * 凭据：env ADYEN_API_KEY（x-api-key）、ADYEN_MERCHANT_ACCOUNT、ADYEN_HMAC_KEY（Webhook 验签）
 * 未配置凭据时构造抛异常，避免空凭据请求打到 Adyen
 */
class AdyenGateway implements PaymentGatewayInterface
{
    private HttpClient $http;
    private string $merchantAccount;
    private string $hmacKey;

    public function __construct()
    {
        $apiKey = (string) config('payment.adyen.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Adyen 未配置：请在 env 设置 ADYEN_API_KEY');
        }
        $this->merchantAccount = (string) config('payment.adyen.merchant_account', '');
        $this->hmacKey = (string) config('payment.adyen.hmac_key', '');
        $baseUrl = config('payment.adyen.mode', 'sandbox') === 'live'
            ? 'https://checkout-live.adyen.com'
            : 'https://checkout-test.adyen.com';
        $this->http = new HttpClient([
            'base_uri' => $baseUrl,
            'timeout' => 15,
            'headers' => [
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function createPayment(array $data): array
    {
        return CircuitBreaker::call('adyen', fn() => $this->doCreatePayment($data), null, [GatewayBusinessException::class]);
    }

    private function doCreatePayment(array $data): array
    {
        $response = $this->http->post('/v71/sessions', [
            'json' => [
                'merchantAccount' => $this->merchantAccount,
                'reference' => (string) ($data['order_no'] ?? $data['order_id'] ?? ''),
                'amount' => [
                    'currency' => $data['currency'] ?? 'USD',
                    'value' => (int) round($data['amount'] * 100),
                ],
                'returnUrl' => (string) ($data['return_url'] ?? ''),
                'countryCode' => $data['country'] ?? 'US',
                'shopperLocale' => $data['locale'] ?? 'en-US',
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        if (empty($result['id'])) {
            throw new GatewayBusinessException('Adyen 创建支付会话失败: ' . json_encode($result));
        }

        return [
            'gateway' => 'adyen',
            'txn_id' => $result['id'],
            'client_secret' => $result['sessionData'] ?? '',
            'status' => $result['status'] ?? 'created',
            'amount' => (float) $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
        ];
    }

    /**
     * 查询支付会话状态（status: completed/active/...）
     */
    public function capturePayment(string $txnId): array
    {
        return CircuitBreaker::call('adyen', fn() => $this->doCapturePayment($txnId), null, [GatewayBusinessException::class]);
    }

    private function doCapturePayment(string $txnId): array
    {
        $response = $this->http->get("/v71/sessions/{$txnId}");
        $result = json_decode($response->getBody(), true);

        return [
            'txn_id' => $txnId,
            'status' => $result['status'] ?? 'unknown',
            'captured' => ($result['status'] ?? '') === 'completed',
        ];
    }

    /**
     * 退款：txnId 传 Adyen 的 paymentPspReference
     */
    public function refundPayment(string $txnId, float $amount, string $currency = 'USD'): array
    {
        return CircuitBreaker::call('adyen', fn() => $this->doRefundPayment($txnId, $amount, $currency), null, [GatewayBusinessException::class]);
    }

    private function doRefundPayment(string $txnId, float $amount, string $currency = 'USD'): array
    {
        $response = $this->http->post("/v71/payments/{$txnId}/refunds", [
            'json' => [
                'merchantAccount' => $this->merchantAccount,
                'amount' => [
                    'currency' => $currency,
                    'value' => (int) round($amount * 100),
                ],
            ],
        ]);
        $result = json_decode($response->getBody(), true);

        return [
            'refund_id' => $result['pspReference'] ?? '',
            'txn_id' => $txnId,
            'status' => $result['status'] ?? 'received',
            'amount' => $amount,
        ];
    }

    /**
     * Adyen Webhook 验签：header Adyen-Signature = hex(HMAC-SHA256(hmacKey, 字段按序拼接))
     * 拼接顺序：pspReference:originalReference:merchantAccountCode:merchantReference:value:currency:eventCode:success
     */
    public function verifyWebhook(string $payload, string $signature, array $headers = []): bool
    {
        $signature = $signature ?: (string) ($headers['adyen-signature'] ?? '');
        if ($this->hmacKey === '' || $signature === '') {
            return false;
        }
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return false;
        }
        $parts = [
            (string) ($data['pspReference'] ?? ''),
            (string) ($data['originalReference'] ?? ''),
            (string) ($data['merchantAccountCode'] ?? ''),
            (string) ($data['merchantReference'] ?? ''),
            (string) ($data['amount'] ?? ($data['value'] ?? '')),
            (string) ($data['currency'] ?? ''),
            (string) ($data['eventCode'] ?? ''),
            ($data['success'] ?? false) ? 'true' : 'false',
        ];
        $expected = hash_hmac('sha256', implode(':', $parts), $this->hmacKey);
        return hash_equals($expected, $signature);
    }

    public function resolveCaptureId(string $txnId): string
    {
        // Adyen 退款使用 paymentPspReference，由支付回调带回，此处原样返回
        return $txnId;
    }
}
