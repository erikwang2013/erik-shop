<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Admin 安全防护中间件
 * XSS / SQL注入 / CRLF / 路径遍历
 */
class SecurityMiddleware implements MiddlewareInterface
{
    private const CODE_XSS = 40301;
    private const CODE_SQLI = 40302;
    private const CODE_CRLF = 40303;
    private const CODE_PATH = 40304;
    private const CODE_SIZE = 40305;

    private const MAX_BODY_SIZE = 20 * 1024 * 1024; // 20MB for admin uploads

    private const XSS_PATTERNS = [
        '/<script\b[^>]*>/i', '/<iframe\b[^>]*>/i', '/<object\b[^>]*>/i',
        '/<embed\b[^>]*>/i', '/<link\b[^>]*>/i', '/javascript\s*:/i',
        '/on\w+\s*=\s*["\']?[^"\'>]*["\']?/i', '/<svg\b[^>]*>/i',
        '/<img[^>]+on\w+\s*=/i', '/expression\s*\(/i',
    ];

    private const SQLI_PATTERNS = [
        '/(\%27|\')\s*(union|select|insert|update|delete|drop|alter|create|truncate|exec|execute)\b/i',
        '/\b(union\s+(all\s+)?select)\b/i', '/\bexec\s*\(/i',
        '/;\s*DROP\s+/i', '/;\s*DELETE\s+FROM\s+/i',
    ];

    private const CRLF_PATTERN = '/[\r\n]/';

    public function process(Request $request, callable $next): Response
    {
        // Content-Type 校验
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $ct = $request->header('Content-Type', '');
            if ($ct && !str_contains($ct, 'application/json')
                && !str_contains($ct, 'multipart/form-data')
                && !str_contains($ct, 'application/x-www-form-urlencoded')) {
                return response('Bad Request', 400);
            }
        }

        // 请求体大小
        if ((int) $request->header('Content-Length', '0') > self::MAX_BODY_SIZE) {
            return response('Payload Too Large', 413);
        }

        // XSS
        $inputs = array_merge($request->all(), $request->route?->all() ?? []);
        unset($inputs['file'], $inputs['image'], $inputs['avatar'], $inputs['content'], $inputs['description']);
        foreach ($inputs as $value) {
            if (!is_string($value)) continue;
            foreach (self::XSS_PATTERNS as $p) {
                if (preg_match($p, $value)) return response('XSS Detected', 403);
            }
        }

        // SQL注入
        foreach ($request->all() as $value) {
            if (!is_string($value)) continue;
            foreach (self::SQLI_PATTERNS as $p) {
                if (preg_match($p, $value)) return response('SQL Injection Detected', 403);
            }
        }

        // CRLF
        foreach (['Authorization', 'X-Platform', 'API-Version'] as $h) {
            $v = $request->header($h, '');
            if (is_string($v) && preg_match(self::CRLF_PATTERN, $v)) return response('Header Injection', 403);
        }

        // 路径遍历
        $path = $request->path();
        if (str_contains($path, '..')) return response('Path Traversal', 403);

        return $next($request);
    }
}
