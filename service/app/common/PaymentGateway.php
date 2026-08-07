<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use GuzzleHttp\Client as HttpClient;
use Stripe\StripeClient;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

interface PaymentGatewayInterface
{
    public function createPayment(array $data): array;
    public function capturePayment(string $txnId): array;
    public function refundPayment(string $txnId, float $amount, string $currency = 'USD'): array;
    public function verifyWebhook(string $payload, string $signature, array $headers = []): bool;
    public function resolveCaptureId(string $txnId): string;
}

class PaymentGateway
{
    public static function make(string $code): PaymentGatewayInterface
    {
        return match ($code) {
            'stripe' => new StripeGateway(),
            'paypal' => new PayPalGateway(),
            default => throw new \InvalidArgumentException("Unknown gateway: {$code}"),
        };
    }
}

class StripeGateway implements PaymentGatewayInterface
{
    private StripeClient $client;
    private string $webhookSecret;

    public function __construct()
    {
        $this->client = new StripeClient(config('payment.stripe.secret_key', ''));
        $this->webhookSecret = config('payment.stripe.webhook_secret', '');
    }

    public function createPayment(array $data): array
    {
        $intent = $this->client->paymentIntents->create([
            'amount' => (int) round($data['amount'] * 100),
            'currency' => strtolower($data['currency']),
            'payment_method_types' => $data['methods'] ?? ['card'],
            'metadata' => [
                'order_id' => (string) ($data['order_id'] ?? ''),
                'order_no' => $data['order_no'] ?? '',
            ],
            'description' => "Order #{$data['order_no']}",
        ]);

        return [
            'gateway' => 'stripe',
            'txn_id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'status' => $intent->status,
            'amount' => $intent->amount / 100,
            'currency' => $intent->currency,
        ];
    }

    public function capturePayment(string $txnId): array
    {
        $intent = $this->client->paymentIntents->capture($txnId);

        return [
            'txn_id' => $intent->id,
            'status' => $intent->status,
            'captured' => $intent->status === 'succeeded',
        ];
    }

    public function refundPayment(string $txnId, float $amount, string $currency = 'USD'): array
    {
        $refund = $this->client->refunds->create([
            'payment_intent' => $txnId,
            'amount' => (int) round($amount * 100),
        ]);

        return [
            'refund_id' => $refund->id,
            'txn_id' => $txnId,
            'status' => $refund->status,
            'amount' => $refund->amount / 100,
        ];
    }

    public function verifyWebhook(string $payload, string $signature, array $headers = []): bool
    {
        try {
            Webhook::constructEvent($payload, $signature, $this->webhookSecret);
            return true;
        } catch (SignatureVerificationException $e) {
            return false;
        }
    }

    public function resolveCaptureId(string $txnId): string
    {
        // Stripe 退款直接使用 payment intent id，无需解析
        return $txnId;
    }
}

class PayPalGateway implements PaymentGatewayInterface
{
    private HttpClient $http;
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $mode = config('payment.paypal.mode', 'sandbox');
        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
        $this->clientId = config('payment.paypal.client_id', '');
        $this->clientSecret = config('payment.paypal.client_secret', '');
        $this->http = new HttpClient(['base_uri' => $this->baseUrl, 'timeout' => 15]);
    }

    private function getAccessToken(): string
    {
        $response = $this->http->post('/v1/oauth2/token', [
            'auth' => [$this->clientId, $this->clientSecret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);
        $token = json_decode($response->getBody(), true)['access_token'] ?? '';
        if ($token === '') {
            throw new \RuntimeException('PayPal OAuth 令牌获取失败');
        }
        return $token;
    }

    public function createPayment(array $data): array
    {
        $token = $this->getAccessToken();
        $amount = number_format($data['amount'], 2, '.', '');

        $response = $this->http->post('/v2/checkout/orders', [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => ['currency_code' => $data['currency'], 'value' => $amount],
                    'reference_id' => (string)($data['order_id'] ?? ''),
                ]],
            ],
        ]);

        $result = json_decode($response->getBody(), true);
        if (empty($result['id'])) {
            // 失败时抛异常而非伪造交易号，避免 webhook 永远匹配不上导致订单卡死
            throw new \RuntimeException('PayPal 创建支付失败: ' . json_encode($result));
        }

        return [
            'gateway' => 'paypal',
            'txn_id' => $result['id'],
            'client_secret' => '',
            'status' => $result['status'] ?? 'CREATED',
            'amount' => (float)$amount,
            'currency' => $data['currency'],
        ];
    }

    public function capturePayment(string $txnId): array
    {
        $token = $this->getAccessToken();
        $response = $this->http->post("/v2/checkout/orders/{$txnId}/capture", [
            'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
        ]);
        $result = json_decode($response->getBody(), true);

        return [
            'txn_id' => $txnId,
            'status' => $result['status'] ?? 'COMPLETED',
            'captured' => ($result['status'] ?? '') === 'COMPLETED',
        ];
    }

    public function refundPayment(string $txnId, float $amount, string $currency = 'USD'): array
    {
        $token = $this->getAccessToken();
        $response = $this->http->post("/v2/payments/captures/{$txnId}/refund", [
            'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
            'json' => ['amount' => ['currency_code' => $currency, 'value' => number_format($amount, 2, '.', '')]],
        ]);
        $result = json_decode($response->getBody(), true);
        if (empty($result['id'])) {
            throw new \RuntimeException('PayPal 退款失败: ' . json_encode($result));
        }

        return [
            'refund_id' => $result['id'],
            'txn_id' => $txnId,
            'status' => $result['status'] ?? 'COMPLETED',
            'amount' => $amount,
        ];
    }

    /**
     * PayPal 退款需要 capture id，而 createPayment 返回的是订单号
     * 通过查询订单详情解析出已捕获交易的 capture id；
     * 若传入的已是 capture id（直接调用退款），查询 404 时原样返回
     */
    public function resolveCaptureId(string $txnId): string
    {
        $token = $this->getAccessToken();
        try {
            $response = $this->http->get("/v2/checkout/orders/{$txnId}", [
                'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
                return $txnId;
            }
            throw $e;
        }

        $order = json_decode($response->getBody(), true);
        $captureId = $order['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';
        if ($captureId === '') {
            throw new \RuntimeException('PayPal 订单尚未捕获，无法退款');
        }
        return $captureId;
    }

    public function verifyWebhook(string $payload, string $signature, array $headers = []): bool
    {
        $webhookId = config('payment.paypal.webhook_id', '');
        if (empty($webhookId)) return false;


        // 五个验签字段来自请求 header（webman 以小写键返回）
        $transmissionId = $headers['paypal-transmission-id'] ?? '';
        $transmissionSig = $headers['paypal-transmission-sig'] ?? '';
        $transmissionTime = $headers['paypal-transmission-time'] ?? '';
        $certUrl = $headers['paypal-cert-url'] ?? '';
        $authAlgo = $headers['paypal-auth-algo'] ?? '';
        if ($transmissionId === '' || $transmissionSig === '' || $transmissionTime === ''
            || $certUrl === '' || $authAlgo === '') {
            return false;
        }

        $token = $this->getAccessToken();
        $response = $this->http->post('/v1/notifications/verify-webhook-signature', [
            'headers' => ['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'],
            'json' => [
                'auth_algo' => $authAlgo,
                'cert_url' => $certUrl,
                'transmission_id' => $transmissionId,
                'transmission_sig' => $transmissionSig,
                'transmission_time' => $transmissionTime,
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($payload, true),
            ],
        ]);

        $result = json_decode($response->getBody(), true);
        return ($result['verification_status'] ?? '') === 'SUCCESS';
    }
}
