<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use app\common\HashidsHelper;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Hashids 响应编码中间件
 * 自动将响应JSON中的snowflake ID编码为hashid
 * 匹配规则：以_id结尾的字段、id字段、数字型ID值
 */
class HashidsEncode implements MiddlewareInterface
{
    private static array $encodeFields = [
        'id', 'user_id', 'product_id', 'category_id', 'order_id', 'sku_id',
        'address_id', 'coupon_id', 'banner_id', 'shop_id', 'warehouse_id',
        'supplier_id', 'parent_id', 'inviter_id', 'return_id', 'refund_id',
        'review_id', 'cart_id', 'wishlist_id', 'notification_id', 'subscription_id',
    ];

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        // 只处理JSON响应
        $contentType = $response->getHeader('Content-Type');
        if (!str_contains(implode(',', $contentType), 'application/json')) {
            return $response;
        }

        $body = (string) $response->rawBody();
        $data = json_decode($body, true);
        if ($data === null) {
            return $response;
        }

        $encoded = $this->encodeIds($data);
        return $response->withBody(json_encode($encoded, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 递归编码数组中的ID字段
     */
    private function encodeIds(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->encodeIds($value);
            } elseif (is_string($value) && is_numeric($value) && $this->shouldEncode($key)) {
                $data[$key] = HashidsHelper::encode((int)$value);
            } elseif (is_int($value) && $value > 10000 && $this->shouldEncode($key)) {
                $data[$key] = HashidsHelper::encode($value);
            }
        }
        return $data;
    }

    /**
     * 判断字段是否需要编码
     */
    private function shouldEncode(string $key): bool
    {
        return in_array($key, self::$encodeFields) || str_ends_with($key, '_id') || $key === 'id';
    }
}
