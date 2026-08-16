<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use GuzzleHttp\Client as HttpClient;

/**
 * Klarna (BNPL) 支付网关 — Guzzle 直连官方 API 最小可用骨架
 *
 * 凭据：env KLARNA_USERNAME / KLARNA_PASSWORD（支付 API 用 basic auth 用户名/密码）
 *       env KLARNA_WEBHOOK_SECRET（Webhook 验签 HMAC 密钥）
 * 未配置凭据时构造抛异常，避免空凭据请求打到 Klarna
 */
class KlarnaGateway implements PaymentGatewayInterface
{
    private HttpClient $http;
    private string $webhookSecret;

    private const REGION_BASE = [
        'europe' => 'https://api.klarna.com',
        'north_america' => 'https://api-na.klarna.com',
        'asia_pacific' => 'https://api-oa.klarna.com',
    ];

    public function __construct()
    {
        $username = (string) config('payment.klarna.username', '');
        $password = (string) config('payment.klarna.password', '');
        if ($username === '' || $password === '') {
            throw new \RuntimeException('Klarna 未配置：请在 env 设置 KLARNA_USERNAME / KLARNA_PASSWORD');
        }
        $region = (string) config('payment.klarna.region', 'europe');
        $baseUrl = self::REGION_BASE[$region] ?? self::REGION_BASE['europe'];
        $this->http = new HttpClient([
            'base_uri' => $baseUrl,
            'timeout' => 15,
            'auth' => [$username, $password],
        ]);
        $this->webhookSecret = (string) config('payment.webhook.klarna', '');
    }

    public function createPayment(array $data): array
    {
        $response = $this->http->post('/payments/v1/sessions', [
            'json' => [
                'purchase_country' => $data['country'] ?? 'US',
                'purchase_currency' => $data['currency'] ?? 'USD',
                'locale' => $data['locale'] ?? 'en-US',
                'order_amount' => (int) round($data['amount'] * 100),
                'order_lines' => $data['order_lines'] ?? [],
                'merchant_references' => [
                    'order_id' => (string) ($data['order_id'] ?? ''),
                    'order_no' => (string) ($data['order_no'] ?? ''),
                ],
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        if (empty($result['session_id'])) {
            throw new \RuntimeException('Klarna 创建支付会话失败: ' . json_encode($result));
        }

        return [
            'gateway' => 'klarna',
            'txn_id' => $result['session_id'],
            'client_token' => $result['client_token'] ?? '',
            'status' => 'created',
            'amount' => (float) $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
        ];
    }

    /**
     * 查询订单状态（Klarna 需用 ordermanagement 的 order_id 查询，session_id 无法查询；
     * 传入 txn_id 为 order_id 时有效）
     */
    public function capturePayment(string $txnId): array
    {
        $response = $this->http->get("/ordermanagement/v1/orders/{$txnId}");
        $result = json_decode($response->getBody(), true);

        return [
            'txn_id' => $txnId,
            'status' => $result['status'] ?? 'unknown',
            'captured' => in_array($result['status'] ?? '', ['CAPTURED', 'PARTIALLY_CAPTURED'], true),
        ];
    }

    public function refundPayment(string $txnId, float $amount, string $currency = 'USD'): array
    {
        $response = $this->http->post("/ordermanagement/v1/orders/{$txnId}/refunds", [
            'json' => ['refunded_amount' => (int) round($amount * 100)],
        ]);
        $result = json_decode($response->getBody(), true);

        return [
            'refund_id' => $result['refund_id'] ?? '',
            'txn_id' => $txnId,
            'status' => 'refunded',
            'amount' => $amount,
        ];
    }

    /**
     * Klarna Webhook 验签：header Klarna-Signature = base64(HMAC-SHA256(raw body, webhook secret))
     */
    public function verifyWebhook(string $payload, string $signature, array $headers = []): bool
    {
        $signature = $signature ?: (string) ($headers['klarna-signature'] ?? $headers['verify_webhook_signature'] ?? '');
        if ($this->webhookSecret === '' || $signature === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $payload, $this->webhookSecret, true));
        return hash_equals($expected, $signature);
    }

    public function resolveCaptureId(string $txnId): string
    {
        // Klarna 退款直接使用 ordermanagement 的 order_id，无需解析
        return $txnId;
    }
}
