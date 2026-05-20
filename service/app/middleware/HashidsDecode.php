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
 * Hashids 请求解码中间件
 * 自动将请求参数的hashid解码为snowflake ID
 * 匹配规则：URL路径参数中的hashid、body/json中以_id结尾的字段
 */
class HashidsDecode implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // 解码URL路径参数（如 /api/products/{id}）
        $this->decodeRouteParams($request);

        // 解码GET/POST参数中以_id结尾的字段
        $this->decodeQueryParams($request);

        return $next($request);
    }

    /**
     * 解码路由参数中的hashid
     */
    private function decodeRouteParams(Request $request): void
    {
        $params = $request->route?->param('id');
        if ($params && is_string($params)) {
            $decoded = HashidsHelper::decode($params);
            if ($decoded !== null) {
                // 注入解码后的ID到request属性
                $request->decodedRouteId = $decoded;
            }
        }
    }

    /**
     * 解码请求参数中以_id结尾的字段
     */
    private function decodeQueryParams(Request $request): void
    {
        $all = $request->all();
        foreach ($all as $key => $value) {
            if (is_string($value) && (str_ends_with($key, '_id') || $key === 'id')) {
                $decoded = HashidsHelper::decode($value);
                if ($decoded !== null) {
                    $request->offsetSet($key, $decoded);
                }
            }
        }
    }
}
