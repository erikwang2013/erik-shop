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
     * 通过 setParams 写回路由参数，控制器方法参数即可拿到解码后的 snowflake ID
     */
    private function decodeRouteParams(Request $request): void
    {
        $route = $request->route;
        if (!$route) {
            return;
        }
        $params = $route->param();
        $updates = [];
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                $decoded = HashidsHelper::decode($value);
                if ($decoded !== null) {
                    $updates[$key] = $decoded;
                }
            }
        }
        if ($updates) {
            $route->setParams($updates);
        }
    }

    /**
     * 解码请求参数中以_id结尾的字段
     * 解码结果写回 GET/POST 参数，控制器通过 $request->input() 即可读取
     */
    private function decodeQueryParams(Request $request): void
    {
        $all = $request->all();
        $updates = [];
        foreach ($all as $key => $value) {
            if (is_string($value) && (str_ends_with($key, '_id') || $key === 'id')) {
                $decoded = HashidsHelper::decode($value);
                if ($decoded !== null) {
                    $updates[$key] = $decoded;
                }
            }
        }
        if ($updates) {
            if ($request->isGet() || $request->isOptions()) {
                $request->setGet($updates);
            }
            $request->setPost($updates);
        }
    }
}
