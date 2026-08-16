<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

interface PaymentGatewayInterface
{
    public function createPayment(array $data): array;
    public function capturePayment(string $txnId): array;
    public function refundPayment(string $txnId, float $amount, string $currency = 'USD'): array;
    public function verifyWebhook(string $payload, string $signature, array $headers = []): bool;
    public function resolveCaptureId(string $txnId): string;
}
