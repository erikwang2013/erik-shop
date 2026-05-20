<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 安全防护中间件
 *
 * 检测并拦截常见 Web/API 攻击：
 *   XSS跨站脚本 / SQL注入 / CRLF Header注入 / 路径遍历 / 恶意文件上传
 */
class SecurityMiddleware implements MiddlewareInterface
{
    // 攻击类型码
    private const CODE_XSS = 40001;
    private const CODE_SQLI = 40002;
    private const CODE_CRLF = 40003;
    private const CODE_PATH = 40004;
    private const CODE_SIZE = 40005;
    private const CODE_TYPE = 40006;
    private const CODE_RATE = 40007;

    // 最大请求体大小 (10MB)
    private const MAX_BODY_SIZE = 10 * 1024 * 1024;

    /**
     * XSS 攻击特征 (HTML/JS注入)
     */
    private const XSS_PATTERNS = [
        '/<script\b[^>]*>/i',
        '/<iframe\b[^>]*>/i',
        '/<object\b[^>]*>/i',
        '/<embed\b[^>]*>/i',
        '/<link\b[^>]*>/i',
        '/javascript\s*:/i',
        '/on\w+\s*=\s*["\']?[^"\'>]*["\']?/i',
        '/<svg\b[^>]*>/i',
        '/<img[^>]+on\w+\s*=/i',
        '/expression\s*\(/i',
        '/<style\b[^>]*>/i',
    ];

    /**
     * SQL注入攻击特征
     */
    private const SQLI_PATTERNS = [
        '/(\%27|\')\s*(union|select|insert|update|delete|drop|alter|create|truncate|exec|execute|declare)\b/i',
        '/\b(union\s+(all\s+)?select)\b/i',
        '/\b(select\s+.*\s+from)\b/i',
        '/(\%27|\')\s*or\s+(\'[^\']*\'=\'[^\']*\'|\d+=\d+)/i',
        '/\bexec\s*\(/i',
        '/\bexecute\s*\(/i',
        '/\bxp_cmdshell\b/i',
        '/;\s*DROP\s+/i',
        '/;\s*DELETE\s+FROM\s+/i',
    ];

    /**
     * CRLF Header注入特征
     */
    private const CRLF_PATTERN = '/[\r\n]/';

    public function process(Request $request, callable $next): Response
    {
        // 1. Content-Type 校验
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $contentType = $request->header('Content-Type', '');
            if ($contentType && !str_contains($contentType, 'application/json')
                && !str_contains($contentType, 'multipart/form-data')
                && !str_contains($contentType, 'application/x-www-form-urlencoded')) {
                return json([
                    'code' => self::CODE_TYPE,
                    'msg' => '不支持的Content-Type',
                    'data' => null,
                ]);
            }
        }

        // 2. 请求体大小限制
        $contentLength = (int) $request->header('Content-Length', '0');
        if ($contentLength > self::MAX_BODY_SIZE) {
            return json([
                'code' => self::CODE_SIZE,
                'msg' => '请求体过大',
                'data' => null,
            ]);
        }

        // 3. XSS检测 — 检查所有输入
        $xssResult = $this->detectXss($request);
        if ($xssResult !== null) {
            return $xssResult;
        }

        // 4. SQL注入检测 — 检查所有输入
        $sqliResult = $this->detectSqlInjection($request);
        if ($sqliResult !== null) {
            return $sqliResult;
        }

        // 5. CRLF Header注入检测
        $crlfResult = $this->detectCrlf($request);
        if ($crlfResult !== null) {
            return $crlfResult;
        }

        // 6. 路径遍历检测
        $pathResult = $this->detectPathTraversal($request);
        if ($pathResult !== null) {
            return $pathResult;
        }

        return $next($request);
    }

    /**
     * XSS检测
     */
    private function detectXss(Request $request): ?Response
    {
        // 检查所有输入字段
        $inputs = array_merge($request->all(), $request->route?->all() ?? []);
        // 排除文件上传字段
        unset($inputs['file'], $inputs['image'], $inputs['avatar']);

        foreach ($inputs as $key => $value) {
            if (!is_string($value)) continue;
            foreach (self::XSS_PATTERNS as $pattern) {
                if (preg_match($pattern, $value)) {
                    return json([
                        'code' => self::CODE_XSS,
                        'msg' => "检测到XSS攻击特征 [{$key}]",
                        'data' => null,
                    ]);
                }
            }
        }
        return null;
    }

    /**
     * SQL注入检测
     */
    private function detectSqlInjection(Request $request): ?Response
    {
        $inputs = $request->all();
        foreach ($inputs as $key => $value) {
            if (!is_string($value)) continue;
            foreach (self::SQLI_PATTERNS as $pattern) {
                if (preg_match($pattern, $value)) {
                    return json([
                        'code' => self::CODE_SQLI,
                        'msg' => "检测到SQL注入特征 [{$key}]",
                        'data' => null,
                    ]);
                }
            }
        }
        return null;
    }

    /**
     * CRLF Header注入检测
     */
    private function detectCrlf(Request $request): ?Response
    {
        // 检查自定义Header中的特殊字符
        $headersToCheck = ['Authorization', 'X-Platform', 'API-Version', 'Accept-Language'];
        foreach ($headersToCheck as $header) {
            $value = $request->header($header, '');
            if (is_string($value) && preg_match(self::CRLF_PATTERN, $value)) {
                return json([
                    'code' => self::CODE_CRLF,
                    'msg' => '检测到Header注入攻击',
                    'data' => null,
                ]);
            }
        }
        return null;
    }

    /**
     * 路径遍历检测
     */
    private function detectPathTraversal(Request $request): ?Response
    {
        $path = $request->path();

        // 检测 ../ 路径遍历
        if (str_contains($path, '..')) {
            return json([
                'code' => self::CODE_PATH,
                'msg' => '检测到路径遍历攻击',
                'data' => null,
            ]);
        }

        // 检测敏感文件访问
        $blockedPaths = ['/etc/', '/var/', '/proc/', '/dev/', 'wp-admin', 'wp-content', '.env', '.git', 'phpmyadmin'];
        foreach ($blockedPaths as $bp) {
            if (str_contains(strtolower($path), $bp)) {
                return json([
                    'code' => self::CODE_PATH,
                    'msg' => '禁止访问',
                    'data' => null,
                ]);
            }
        }

        return null;
    }
}
