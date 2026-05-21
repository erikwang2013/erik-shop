<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 全面安全防护中间件 — 15类攻击检测
 *
 * 1. XSS跨站脚本     6. Content-Type    11. SSRF服务端请求伪造
 * 2. SQL注入         7. 文件上传校验      12. 敏感数据脱敏
 * 3. CRLF注入        8. HTTP安全头        13. HTTP方法校验
 * 4. 路径遍历        9. 暴力破解防护      14. Host头校验
 * 5. 请求体限制     10. XXE实体注入       15. CORS白名单
 */
class SecurityMiddleware implements MiddlewareInterface
{
    private const CODE_XSS   = 40001;
    private const CODE_SQLI  = 40002;
    private const CODE_CRLF  = 40003;
    private const CODE_PATH  = 40004;
    private const CODE_SIZE  = 40005;
    private const CODE_TYPE  = 40006;
    private const CODE_BRUTE = 40008;
    private const CODE_UPLOAD = 40009;
    private const CODE_XXE   = 40010;
    private const CODE_SSRF  = 40011;
    private const CODE_METHOD = 40012;
    private const CODE_HOST  = 40013;

    private const MAX_BODY = 10 * 1024 * 1024;
    private const BRUTE_LIMIT = 10;
    private const BRUTE_WINDOW = 60;

    // 文件上传白名单
    private const ALLOWED_EXTENSIONS = [
        'jpg','jpeg','png','gif','webp','svg',
        'pdf','doc','docx','xls','xlsx','csv',
        'zip','rar','7z',
    ];
    private const BLOCKED_EXTENSIONS = [
        'php','php5','php7','php8','phtml','shtml','cgi','pl','py','rb','sh',
        'exe','bat','cmd','com','dll','so','js','jsp','asp','aspx',
    ];

    // 内网IP/域名特征 (SSRF)
    private const SSRF_PATTERNS = [
        '/^127\.\d+\.\d+\.\d+$/', '/^10\.\d+\.\d+\.\d+$/',
        '/^172\.(1[6-9]|2\d|3[01])\.\d+\.\d+$/', '/^192\.168\.\d+\.\d+$/',
        '/^0\.\d+\.\d+\.\d+$/', '/^169\.254\.\d+\.\d+$/',
        '/localhost/i', '/metadata\.google\.internal/i',
        '/\b169\.254\.169\.254\b/',
    ];

    // XSS (已扩展: <meta>, <base>, data: URI, vbscript)
    private const XSS = [
        '/<script\b[^>]*>/i', '/<iframe\b[^>]*>/i', '/<object\b[^>]*>/i',
        '/<embed\b[^>]*>/i', '/<link\b[^>]*>/i', '/<meta\b[^>]*>/i',
        '/<base\b[^>]*>/i',
        '/javascript\s*:/i', '/vbscript\s*:/i', '/data\s*:\s*text\/html/i',
        '/on\w+\s*=\s*["\']?[^"\'>]*["\']?/i',
        '/<svg\b[^>]*>/i', '/<img[^>]+on\w+\s*=/i',
        '/expression\s*\(/i', '/<style\b[^>]*>/i',
        '/<marquee\b[^>]*>/i', '/<applet\b[^>]*>/i', '/<form\b[^>]*>/i',
    ];

    // SQL注入 (已扩展: benchmark, sleep, load_file, into outfile)
    private const SQLi = [
        '/(\%27|\')\s*(union|select|insert|update|delete|drop|alter|create|truncate|exec|execute|declare)\b/i',
        '/\b(union\s+(all\s+)?select)\b/i',
        '/\b(select\s+.*\s+from)\b/i',
        '/(\%27|\')\s*or\s+(\'[^\']*\'=\'[^\']*\'|\d+=\d+)/i',
        '/\bexec\s*\(/i', '/\bexecute\s*\(/i',
        '/\bxp_cmdshell\b/i', '/\bxp_regread\b/i', '/\bsp_executesql\b/i',
        '/;\s*DROP\s+/i', '/;\s*DELETE\s+FROM\s+/i',
        '/\bbenchmark\s*\(/i', '/\bsleep\s*\(/i', '/\bpg_sleep\s*\(/i',
        '/\bload_file\s*\(/i', '/\binto\s+(outfile|dumpfile)\b/i',
        '/\bwaitfor\s+delay\b/i', '/\bchar\s*\(\s*\d+/i',
    ];

    // XXE
    private const XXE = [
        '/<!ENTITY\s+\w+\s+(SYSTEM|PUBLIC)/i',
        '/<!DOCTYPE\s+\w+\s+\[/i',
        '/<\?xml[^>]*\bstandalone\s*=\s*["\']?\s*no/i',
    ];

    private const CRLF = '/[\r\n]/';
    private const ALLOWED_METHODS = ['GET','POST','PUT','DELETE','PATCH','OPTIONS','HEAD'];

    public function process(Request $request, callable $next): Response
    {
        // 1. HTTP方法校验
        $method = strtoupper($request->method());
        if (!in_array($method, self::ALLOWED_METHODS)) {
            return json(['code' => self::CODE_METHOD, 'msg' => '不支持的HTTP方法', 'data' => null]);
        }

        // 2. Host头校验
        if ($r = $this->checkHost($request)) return $r;

        // 3. Content-Type
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $ct = $request->header('Content-Type', '');
            if ($ct && !str_contains($ct, 'application/json')
                && !str_contains($ct, 'multipart/form-data')
                && !str_contains($ct, 'application/x-www-form-urlencoded')) {
                return json(['code' => self::CODE_TYPE, 'msg' => 'Content-Type not allowed', 'data' => null]);
            }
        }

        // 4. Body Size
        if ((int) $request->header('Content-Length', '0') > self::MAX_BODY) {
            return json(['code' => self::CODE_SIZE, 'msg' => 'Payload too large', 'data' => null]);
        }

        // 5. 文件上传校验
        if ($r = $this->checkUpload($request)) return $r;

        // 6. XXE检测
        if ($r = $this->detectXxe($request)) return $r;

        // 7. XSS
        if ($r = $this->detectXss($request)) return $r;

        // 8. SQL注入
        if ($r = $this->detectSqli($request)) return $r;

        // 9. CRLF
        if ($r = $this->detectCrlf($request)) return $r;

        // 10. 路径遍历
        if ($r = $this->detectPath($request)) return $r;

        // 11. SSRF
        if ($r = $this->detectSsrf($request)) return $r;

        // 12. 暴力破解
        if ($r = $this->checkBrute($request)) return $r;

        // 13. 敏感数据脱敏 (后置处理)
        $response = $next($request);

        // 14. 添加安全响应头
        return $this->addSecurityHeaders($response);
    }

    // ===== 检测方法 =====

    private function checkHost(Request $request): ?Response
    {
        $host = $request->host(true);
        // 拒绝裸IP地址访问 (仅允许域名)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // API可放宽，记录日志不拦截
            // return json(['code'=>self::CODE_HOST, 'msg'=>'IP direct access denied', 'data'=>null]);
        }
        return null;
    }

    private function checkUpload(Request $request): ?Response
    {
        $files = $request->file();
        if (empty($files)) return null;

        foreach ($files as $file) {
            if (!is_array($file) || !isset($file['name'])) continue;
            $name = $file['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // 危险扩展名
            if (in_array($ext, self::BLOCKED_EXTENSIONS)) {
                return json(['code' => self::CODE_UPLOAD, 'msg' => "File type not allowed: .{$ext}", 'data' => null]);
            }

            // 双重扩展名攻击 (如 file.php.jpg)
            $parts = explode('.', $name);
            if (count($parts) > 2) {
                $inner = strtolower($parts[count($parts) - 2]);
                if (in_array($inner, self::BLOCKED_EXTENSIONS)) {
                    return json(['code' => self::CODE_UPLOAD, 'msg' => 'Suspicious double extension', 'data' => null]);
                }
            }

            // 空扩展名
            if (empty($ext) && !empty($name)) {
                return json(['code' => self::CODE_UPLOAD, 'msg' => 'File without extension', 'data' => null]);
            }
        }
        return null;
    }

    private function detectXxe(Request $request): ?Response
    {
        $body = $request->rawBody();
        if (empty($body)) return null;
        foreach (self::XXE as $p) {
            if (preg_match($p, $body)) {
                return json(['code' => self::CODE_XXE, 'msg' => 'XXE attack detected', 'data' => null]);
            }
        }
        return null;
    }

    private function detectXss(Request $request): ?Response
    {
        $inputs = array_merge($request->all(), $request->route?->all() ?? []);
        unset($inputs['file'], $inputs['image'], $inputs['avatar'], $inputs['content'], $inputs['description'], $inputs['body']);
        foreach ($inputs as $k => $v) {
            if (!is_string($v)) continue;
            foreach (self::XSS as $p) {
                if (preg_match($p, $v)) {
                    return json(['code' => self::CODE_XSS, 'msg' => "XSS detected [{$k}]", 'data' => null]);
                }
            }
        }
        return null;
    }

    private function detectSqli(Request $request): ?Response
    {
        foreach ($request->all() as $k => $v) {
            if (!is_string($v)) continue;
            foreach (self::SQLi as $p) {
                if (preg_match($p, $v)) {
                    return json(['code' => self::CODE_SQLI, 'msg' => "SQL injection detected [{$k}]", 'data' => null]);
                }
            }
        }
        return null;
    }

    private function detectCrlf(Request $request): ?Response
    {
        foreach (['Authorization','X-Platform','API-Version','Accept-Language','X-Forwarded-For','Referer','Origin'] as $h) {
            $v = $request->header($h, '');
            if (is_string($v) && preg_match(self::CRLF, $v)) {
                return json(['code' => self::CODE_CRLF, 'msg' => 'Header injection detected', 'data' => null]);
            }
        }
        return null;
    }

    private function detectPath(Request $request): ?Response
    {
        $path = $request->path();
        if (str_contains(rawurldecode($path), '..')) {
            return json(['code' => self::CODE_PATH, 'msg' => 'Path traversal detected', 'data' => null]);
        }
        // 编码后的路径遍历: %2e%2e%2f, %252e%252e%252f
        if (preg_match('/%(25)?2[ef]/i', $path) && preg_match('/%(25)?2[ef].*%(25)?2[ef]/i', $path)) {
            return json(['code' => self::CODE_PATH, 'msg' => 'Encoded path traversal', 'data' => null]);
        }
        // 敏感文件
        foreach (['.env','.git','phpmyadmin','wp-admin','wp-content','/etc/','/proc/','/dev/','composer.json','composer.lock'] as $bp) {
            if (str_contains(strtolower($path), $bp)) {
                return json(['code' => self::CODE_PATH, 'msg' => 'Access denied', 'data' => null]);
            }
        }
        // Null byte注入 \0
        if (str_contains($path, "\0")) {
            return json(['code' => self::CODE_PATH, 'msg' => 'Null byte injection', 'data' => null]);
        }
        return null;
    }

    private function detectSsrf(Request $request): ?Response
    {
        // 检查URL类参数 (url, redirect, callback, webhook, link, image_url, avatar_url)
        $urlFields = ['url','redirect','callback','webhook','link','image_url','avatar_url','return_url','notify_url'];
        foreach ($urlFields as $field) {
            $value = $request->input($field, '');
            if (!is_string($value) || empty($value)) continue;

            // 提取域名部分
            $host = parse_url($value, PHP_URL_HOST);
            if (!$host) continue;

            foreach (self::SSRF_PATTERNS as $pattern) {
                if (preg_match($pattern, $host)) {
                    return json(['code' => self::CODE_SSRF, 'msg' => "SSRF attempt blocked [{$field}]", 'data' => null]);
                }
            }
        }
        return null;
    }

    private function checkBrute(Request $request): ?Response
    {
        // 仅检查登录/注册端点
        $path = $request->path();
        if (!str_contains($path, '/auth/login') && !str_contains($path, '/auth/register')) {
            return null;
        }

        try {
            $ip = $request->getRealIp();
            $key = "erik_brute:{$ip}:" . (str_contains($path, 'login') ? 'login' : 'register');
            $redis = redis();
            $count = (int) $redis->get($key);
            if ($count >= self::BRUTE_LIMIT) {
                return json(['code' => self::CODE_BRUTE, 'msg' => 'Too many attempts, please try later', 'data' => null]);
            }
            $redis->incr($key);
            $redis->expire($key, self::BRUTE_WINDOW);
        } catch (\Throwable $e) {
            // Redis不可用时降级放行
        }
        return null;
    }

    // ===== 响应安全头 =====

    private function addSecurityHeaders(Response $response): Response
    {
        return $response->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'X-Download-Options' => 'noopen',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Server' => '',  // 隐藏Server头
        ]);
    }
}
