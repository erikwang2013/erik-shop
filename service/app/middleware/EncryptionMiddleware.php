<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use app\common\Encryption;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * API 敏感数据加解密中间件
 *
 * 请求解密：客户端发送 X-Encrypted: 1 header + base64 密文 body，中间件解密为 JSON
 * 响应加密：客户端发送 X-Encrypt-Response: 1 header，中间件加密 data 字段
 */
class EncryptionMiddleware implements MiddlewareInterface
{
    private const SKIP_PATHS = [
        '/api/health',
        '/api/ping',
        '/apidoc',
    ];

    public function process(Request $request, callable $next): Response
    {
        $path = $request->path();
        foreach (self::SKIP_PATHS as $skip) {
            if (str_starts_with($path, $skip)) {
                return $next($request);
            }
        }

        // 请求解密
        if ($request->header('X-Encrypted') === '1') {
            $body = $request->rawBody();
            if (!empty($body)) {
                try {
                    $decrypted = Encryption::decrypt($body);
                    $request->setRawBody(json_encode($decrypted));
                } catch (\Throwable $e) {
                    return json([
                        'code' => 400,
                        'msg'  => 'Invalid encrypted request',
                        'data' => null,
                    ]);
                }
            }
        }

        /** @var Response $response */
        $response = $next($request);

        // 响应加密
        if ($request->header('X-Encrypt-Response') === '1') {
            $body = (string) $response->rawBody();
            if (!empty($body)) {
                try {
                    $data = json_decode($body, true);
                    if ($data !== null) {
                        $fields = $request->header('X-Encrypt-Fields', '');
                        if (!empty($fields)) {
                            foreach (explode(',', $fields) as $field) {
                                $f = trim($field);
                                if (isset($data[$f])) {
                                    $data[$f] = Encryption::encrypt($data[$f]);
                                }
                            }
                        } elseif (isset($data['data']) && $data['data'] !== null) {
                            $data['data'] = Encryption::encrypt($data['data']);
                            $data['encrypted'] = true;
                        }
                        return $response->withBody(json_encode($data, JSON_UNESCAPED_UNICODE));
                    }
                } catch (\Throwable $e) {
                    // 加密失败时返回原文
                }
            }
        }

        return $response;
    }
}
