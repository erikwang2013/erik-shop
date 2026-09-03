<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

interface PaymentGatewayInterface
{
    public function createPayment(array $data): array;
    public function capturePayment(string $txnId): array;
    /** 退款入参金额为元级 decimal（字符串优先，兼容 float/int），网关内经 Money 精确换算为最小货币单位 */
    public function refundPayment(string $txnId, int|string|float $amount, string $currency = 'USD'): array;
    public function verifyWebhook(string $payload, string $signature, array $headers = []): bool;
    public function resolveCaptureId(string $txnId): string;
}
