<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Erikwang2013\Hashids\HashidsManager;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Admin Hashids 响应编码中间件
 * 自动将响应JSON中的snowflake ID编码为hashid
 */
class HashidsEncode implements MiddlewareInterface
{
    private array $encodeFields = [
        'id', 'user_id', 'product_id', 'category_id', 'order_id', 'sku_id',
        'address_id', 'coupon_id', 'banner_id', 'shop_id', 'warehouse_id',
        'supplier_id', 'parent_id', 'inviter_id', 'return_id', 'refund_id',
        'review_id', 'cart_id', 'notification_id',
    ];

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        // 只处理JSON响应
        $contentType = implode(',', (array)$response->getHeader('Content-Type'));
        if (!str_contains($contentType, 'application/json')) {
            return $response;
        }

        $body = (string) $response->rawBody();
        $data = json_decode($body, true);
        if ($data === null) return $response;

        $encoded = $this->encodeIds($data);
        return $response->withBody(json_encode($encoded, JSON_UNESCAPED_UNICODE));
    }

    private function encodeIds(array $data): array
    {
        $instance = new HashidsManager(config('hashids'));
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->encodeIds($value);
            } elseif (is_string($value) && is_numeric($value) && $this->shouldEncode($key)) {
                $data[$key] = $instance->encode((int) $value);
            } elseif (is_int($value) && $value > 10000 && $this->shouldEncode($key)) {
                $data[$key] = $instance->encode($value);
            }
        }
        return $data;
    }

    private function shouldEncode(string $key): bool
    {
        return in_array($key, $this->encodeFields) || str_ends_with($key, '_id') || $key === 'id';
    }
}
