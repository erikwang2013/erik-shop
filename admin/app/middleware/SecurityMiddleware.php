<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Admin 全面安全防护 — 15类攻击检测
 */
class SecurityMiddleware implements MiddlewareInterface
{
    private const CODE_XSS=40001; private const CODE_SQLI=40002;
    private const CODE_CRLF=40003; private const CODE_PATH=40004;
    private const CODE_SIZE=40005; private const CODE_TYPE=40006;
    private const CODE_BRUTE=40008; private const CODE_UPLOAD=40009;
    private const CODE_XXE=40010; private const CODE_SSRF=40011;
    private const CODE_METHOD=40012;

    private const MAX_BODY = 20 * 1024 * 1024; // Admin允许20MB上传

    private const BLOCKED_EXT = [
        'php','php5','php7','php8','phtml','shtml','cgi','pl','py','rb','sh',
        'exe','bat','cmd','com','dll','so','js','jsp','asp','aspx',
    ];

    private const SSRF = [
        '/^127\.\d+\.\d+\.\d+$/', '/^10\.\d+\.\d+\.\d+$/',
        '/^172\.(1[6-9]|2\d|3[01])\.\d+\.\d+$/', '/^192\.168\.\d+\.\d+$/',
        '/localhost/i', '/169\.254\.169\.254/',
    ];

    private const XSS = [
        '/<script\b[^>]*>/i','/<iframe\b[^>]*>/i','/<object\b[^>]*>/i',
        '/<embed\b[^>]*>/i','/<link\b[^>]*>/i','/<meta\b[^>]*>/i','/<base\b[^>]*>/i',
        '/javascript\s*:/i','/vbscript\s*:/i','/data\s*:\s*text\/html/i',
        '/on\w+\s*=\s*["\']?[^"\'>]*["\']?/i',
        '/<svg\b[^>]*>/i','/<img[^>]+on\w+\s*=/i','/expression\s*\(/i',
        '/<marquee\b[^>]*>/i','/<applet\b[^>]*>/i','/<form\b[^>]*>/i',
    ];

    private const SQLi = [
        '/\b(union\s+(all\s+)?select)\b/i','/(\%27|\')\s*or\s+(\'[^\']*\'=\'[^\']*\'|\d+=\d+)/i',
        '/;\s*DROP\s+/i','/;\s*DELETE\s+FROM\s+/i','/\bexec\s*\(/i',
        '/\bxp_cmdshell\b/i','/\bbenchmark\s*\(/i','/\bsleep\s*\(/i',
        '/\bload_file\s*\(/i','/\binto\s+(outfile|dumpfile)\b/i',
    ];

    private const XXE = [
        '/<!ENTITY\s+\w+\s+(SYSTEM|PUBLIC)/i','/<!DOCTYPE\s+\w+\s+\[/i',
    ];

    private const ALLOWED_METHODS = ['GET','POST','PUT','DELETE','PATCH','OPTIONS','HEAD'];
    private const CRLF = '/[\r\n]/';

    public function process(Request $request, callable $next): Response
    {
        // HTTP方法
        if (!in_array(strtoupper($request->method()), self::ALLOWED_METHODS)) {
            return response('Method Not Allowed', 405);
        }

        // Content-Type
        if (in_array($request->method(), ['POST','PUT','PATCH'])) {
            $ct = $request->header('Content-Type','');
            if ($ct && !str_contains($ct,'json') && !str_contains($ct,'form-data') && !str_contains($ct,'form-urlencoded')) {
                return response('Unsupported Media Type', 415);
            }
        }

        // Body Size
        if ((int)$request->header('Content-Length','0') > self::MAX_BODY) {
            return response('Payload Too Large', 413);
        }

        // 文件上传
        foreach ($request->file() as $f) {
            if (!is_array($f)||!isset($f['name'])) continue;
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (in_array($ext, self::BLOCKED_EXT)) return response('Blocked', 403);
            $parts = explode('.', $f['name']);
            if (count($parts)>2 && in_array(strtolower($parts[count($parts)-2]), self::BLOCKED_EXT)) return response('Blocked', 403);
        }

        // XXE
        $body = $request->rawBody();
        if ($body) foreach (self::XXE as $p) if (preg_match($p,$body)) return response('XXE', 403);

        // XSS (排除富文本字段)
        $inputs = array_merge($request->all(), $request->route?->all()??[]);
        unset($inputs['file'],$inputs['image'],$inputs['avatar'],$inputs['content'],$inputs['description'],$inputs['body']);
        foreach ($inputs as $v) if (is_string($v)) foreach (self::XSS as $p) if (preg_match($p,$v)) return response('XSS', 403);

        // SQLi
        foreach ($request->all() as $v) if (is_string($v)) foreach (self::SQLi as $p) if (preg_match($p,$v)) return response('SQLi', 403);

        // CRLF
        foreach (['Authorization','X-Platform','API-Version','X-Forwarded-For','Referer'] as $h)
            if (is_string($v=$request->header($h,'')) && preg_match(self::CRLF,$v)) return response('CRLF', 403);

        // Path
        $path = $request->path();
        if (str_contains(rawurldecode($path),'..')||str_contains($path,"\0")) return response('Path',403);
        foreach (['.env','.git','phpmyadmin','/etc/','/proc/'] as $bp)
            if (str_contains(strtolower($path),$bp)) return response('Blocked',403);

        // SSRF
        foreach (['url','redirect','callback','webhook'] as $f)
            if ($v=$request->input($f,'')) if ($host=parse_url($v,PHP_URL_HOST))
                foreach (self::SSRF as $p) if (preg_match($p,$host)) return response('SSRF',403);

        // 暴力破解
        $path = $request->path();
        if (str_contains($path,'/login')||str_contains($path,'/auth/login')) try {
            $key = 'erik_admin_brute:'.$request->getRealIp();
            $c = (int)redis()->get($key);
            if ($c>=5) return response('Too Many Attempts',429);
            redis()->incr($key); redis()->expire($key,300);
        } catch(\Throwable $e){}

        $response = $next($request);

        // 安全响应头
        return $response->withHeaders([
            'X-Content-Type-Options'=>'nosniff','X-Frame-Options'=>'DENY',
            'X-XSS-Protection'=>'1; mode=block','Referrer-Policy'=>'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies'=>'none',
            'Cache-Control'=>'no-store, no-cache, must-revalidate','Server'=>'',
        ]);
    }
}
