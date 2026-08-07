<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Erikwang2013\Hashids\HashidsFactory;
use Erikwang2013\Hashids\HashidsManager;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Admin Hashids 请求解码中间件
 * 自动将请求参数的hashid解码为snowflake ID
 */
class HashidsDecode implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $all = $request->all();
        foreach ($all as $key => $value) {
            if (is_string($value) && (str_ends_with($key, '_id') || $key === 'id')) {
                $decoded = $this->decode($value);
                if ($decoded !== null) {
                    $request->offsetSet($key, $decoded);
                }
            }
        }

        return $next($request);
    }

    private function decode(string $hash): ?string
    {
        $instance = new HashidsManager(config('hashids'), new HashidsFactory());
        $decoded = $instance->decode($hash);
        if (empty($decoded)) return null;
        return (string) $decoded[0];
    }
}
