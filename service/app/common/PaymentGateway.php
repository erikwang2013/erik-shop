<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use Stripe\StripeClient;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

interface PaymentGatewayInterface
{
    public function createPayment(array $data): array;
    public function capturePayment(string $txnId): array;
    public function refundPayment(string $txnId, float $amount): array;
    public function verifyWebhook(string $payload, string $signature): bool;
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
        $intent = $this->client->paymentIntents->retrieve($txnId);
        $intent = $this->client->paymentIntents->capture($txnId);

        return [
            'txn_id' => $intent->id,
            'status' => $intent->status,
            'captured' => $intent->status === 'succeeded',
        ];
    }

    public function refundPayment(string $txnId, float $amount): array
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

    public function verifyWebhook(string $payload, string $signature): bool
    {
        try {
            Webhook::constructEvent($payload, $signature, $this->webhookSecret);
            return true;
        } catch (SignatureVerificationException $e) {
            return false;
        }
    }
}

class PayPalGateway implements PaymentGatewayInterface
{
    public function createPayment(array $data): array
    {
        // TODO: PayPal REST API 集成
        return [
            'gateway' => 'paypal',
            'txn_id' => 'PAYPAL_' . uniqid(),
            'status' => 'created',
            'amount' => $data['amount'],
            'currency' => $data['currency'],
        ];
    }

    public function capturePayment(string $txnId): array
    {
        return ['txn_id' => $txnId, 'status' => 'captured'];
    }

    public function refundPayment(string $txnId, float $amount): array
    {
        return ['refund_id' => 'REFUND_' . uniqid(), 'status' => 'completed'];
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        // PayPal: 验证 webhook 签名
        return true;
    }
}
