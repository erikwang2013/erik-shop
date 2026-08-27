<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * API 测试引导：HTTP 客户端 / 断言收集器 / DB+Redis 工具 / 人机验证与账号助手
 */

require_once __DIR__ . '/../../vendor/autoload.php';

define('API_BASE', 'http://127.0.0.1:8787');
define('API_VER', '2026-05-20');
define('API_DB', 'erik_shop_api_test');

/** 读取 .env（框架 createUnsafeMutable 以 .env 为唯一权威，测试侧同步读取） */
function envFromFile(string $key): string
{
    static $env = null;
    if ($env === null) {
        $env = [];
        foreach (file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v, " \t\n\r\"'");
        }
    }
    return $env[$key] ?? '';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . API_DB . ';charset=utf8mb4', 'qa', 'qa_pass');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function redisClient(): Redis
{
    static $r = null;
    if ($r === null) {
        $r = new Redis();
        $r->connect('127.0.0.1', 6379);
    }
    return $r;
}

function hashids(): Hashids\Hashids
{
    static $h = null;
    if ($h === null) {
        $h = new Hashids\Hashids(envFromFile('HASHIDS_SALT'), 0);
    }
    return $h;
}

function enc($id): string
{
    return hashids()->encode((int) $id);
}

/** 通用 HTTP 客户端。返回 [httpStatus, decodedJson|raw, curlError]。$body 为数组时 JSON 编码，为字符串时原样发送（webhook 验签用） */
function http(string $method, string $path, array|string|null $body = null, array $headers = []): array
{
    $ch = curl_init(API_BASE . $path);
    $hdrs = ['API-Version: ' . API_VER, 'Accept-Language: en', 'X-Platform: web'];
    if ($body !== null) {
        $hdrs[] = 'Content-Type: application/json';
    }
    // 调用方传入 Accept-Language 时以调用方为准，避免重复 header（服务端取第一个）
    if (in_array(true, array_map(fn($h) => str_starts_with($h, 'Accept-Language:'), $headers), true)) {
        $hdrs = array_values(array_filter($hdrs, fn($h) => !str_starts_with($h, 'Accept-Language:')));
    }
    $hdrs = array_merge($hdrs, $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $hdrs,
    ]);
    if (is_array($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    } elseif (is_string($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return [$status, null, $err];
    }
    $json = json_decode($resp, true);
    return [$status, $json === null ? $resp : $json, null];
}

/** 断言收集器：$opts 支持 expect / expect_any (HTTP状态) / expect_code (业务码) / expect_key / expect_contains */
function check(string $label, string $method, string $path, array $opts = []): bool
{
    [$status, $json, $err] = http($method, $path, $opts['body'] ?? null, $opts['headers'] ?? []);
    // 自愈：意外 429（限流窗口未清）时仅清限流计数并重试一次（不清 poster/token 等流程状态键）
    if ($status === 429 && ($opts['expect'] ?? null) !== 429 && ($opts['expect_any'] ?? null) !== [429]) {
        clearRateLimits();
        [$status, $json, $err] = http($method, $path, $opts['body'] ?? null, $opts['headers'] ?? []);
    }
    $fail = null;
    if ($err !== null && $err !== '') {
        $fail = 'curl error: ' . $err;
    } elseif (isset($opts['expect']) && $status !== $opts['expect']) {
        // 应用惯例：业务错误返回 HTTP 200 + code（401/403/422 等）；429/500 用真实 HTTP 状态码
        $codeIsStatus = $status === 200 && $opts['expect'] >= 400 && $opts['expect'] < 500
            && is_array($json) && ($json['code'] ?? null) === $opts['expect'];
        if (!$codeIsStatus) {
            $fail = "HTTP {$status} != {$opts['expect']}";
        }
    } elseif (isset($opts['expect_any']) && !in_array($status, $opts['expect_any'], true)) {
        // expect_any 同样支持业务错误惯例：HTTP 200 + body code 命中任一值
        $codeOk = $status === 200 && is_array($json) && in_array($json['code'] ?? -1, $opts['expect_any'], true);
        if (!$codeOk) {
            $fail = 'HTTP ' . $status . ' not in [' . implode(',', $opts['expect_any']) . ']';
        }
    } elseif (isset($opts['expect_code']) && (!is_array($json) || ($json['code'] ?? -1) !== $opts['expect_code'])) {
        $fail = '业务码 ' . (is_array($json) ? ($json['code'] ?? 'null') : '非JSON') . ' != ' . $opts['expect_code'];
    } elseif (isset($opts['expect_key']) && (!is_array($json) || !array_key_exists($opts['expect_key'], $json['data'] ?? []))) {
        $fail = '缺少 data.' . $opts['expect_key'];
    } elseif (isset($opts['expect_contains']) && !str_contains(is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : (string) $json, $opts['expect_contains'])) {
        $fail = '响应未包含: ' . $opts['expect_contains'];
    }
    $GLOBALS['__results'][] = [$label, $method, $path, $fail ? 'FAIL' : 'PASS', $fail ?? ''];
    if ($fail) {
        $resp = is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : (string) $json;
        fwrite(STDERR, "FAIL [{$label}] {$method} {$path} — {$fail}\n  resp: " . substr($resp, 0, 220) . "\n");
    }
    return !$fail;
}

/** 完成一次人机验证，返回可消费的 X-Poster-Token */
function posterToken(): string
{
    [$s, $json] = http('GET', '/api/poster/challenge');
    if ($s !== 200 || !isset($json['data']['token'])) {
        throw new RuntimeException('poster challenge 失败: ' . json_encode($json));
    }
    $token = $json['data']['token'];
    // 题面形如 "3 x 2 = ?"，x→*、'='与'?'剥离后 eval 求值
    $expr = str_replace(['x', '=', '?'], ['*', '', ''], $json['data']['question']);
    $answer = eval('return ' . trim($expr) . ';');
    [$s2, $r2] = http('POST', '/api/poster/verify', ['token' => $token, 'answer' => (string) $answer]);
    if ($s2 !== 200 || ($r2['code'] ?? -1) !== 0) {
        throw new RuntimeException('poster verify 失败: ' . json_encode($r2));
    }
    return $token;
}

function authHeaders(string $token): array
{
    return ['Authorization: Bearer ' . $token];
}

/** 注册新用户（自动完成人机验证），返回 [userId, accessToken, refreshToken, data] */
function registerUser(string $email, string $password = 'Passw0rd123'): array
{
    [$s, $json] = http('POST', '/api/auth/register', [
        'email' => $email, 'password' => $password, 'nickname' => 'Tester',
    ], ['X-Poster-Token: ' . posterToken()]);
    // 429 自愈：限流窗口未清时仅清限流计数并重试一次（与 check() 一致）
    if ($s === 429) {
        clearRateLimits();
        [$s, $json] = http('POST', '/api/auth/register', [
            'email' => $email, 'password' => $password, 'nickname' => 'Tester',
        ], ['X-Poster-Token: ' . posterToken()]);
    }
    if ($s !== 200 || ($json['code'] ?? -1) !== 0) {
        throw new RuntimeException('注册失败: ' . json_encode($json));
    }
    return [
        'id' => hashids()->decode($json['data']['user_id'])[0] ?? 0,
        'token' => $json['data']['access_token'],
        'refresh' => $json['data']['refresh_token'],
        'data' => $json['data'],
    ];
}

/** 登录已有用户 */
function loginUser(string $email, string $password = 'Passw0rd123'): array
{
    [$s, $json] = http('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
    if ($s !== 200 || ($json['code'] ?? -1) !== 0) {
        throw new RuntimeException('登录失败: ' . json_encode($json));
    }
    return [
        'id' => hashids()->decode($json['data']['user_id'])[0] ?? 0,
        'token' => $json['data']['access_token'],
        'refresh' => $json['data']['refresh_token'],
        'data' => $json['data'],
    ];
}

/** 构造伪造/过期 JWT（HS256，与 erikwang2013/jwt-webman 默认声明一致） */
function forgeJwt(string $secret, array $claims): string
{
    $h = ['alg' => 'HS256', 'typ' => 'JWT'];
    $seg = function (array $v): string {
        return rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
    };
    $sign = hash_hmac('sha256', $seg($h) . '.' . $seg($claims), $secret, true);
    return $seg($h) . '.' . $seg($claims) . '.' . rtrim(strtr(base64_encode($sign), '+/', '-_'), '=');
}

/** 清空全部业务表（保留表结构） */
function resetDb(): void
{
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = $pdo->query("SHOW TABLES FROM `" . API_DB . "`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $pdo->exec("TRUNCATE TABLE `{$t}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo 'DB 已清空: ' . count($tables) . " 张表\n";
}

/** 清空本服务 Redis 命名空间（shop 前缀全部键：shop:*、shop_session:*、shop_queue:*、shop_ratelimit:* 等） */
function resetRedis(): void
{
    $r = redisClient();
    $keys = $r->keys('shop*');
    if ($keys) {
        foreach (array_chunk($keys, 500) as $chunk) {
            $r->del($chunk);
        }
    }
    echo 'Redis shop* 键已清空: ' . count($keys) . "\n";
}

/**
 * 仅清限流/暴力破解计数键（shop_ratelimit:*、shop_brute:*）。
 * 429 自愈时使用：全量 resetRedis 会连带清掉 poster/邮箱验证/密码重置 token，破坏测试流程状态。
 */
function clearRateLimits(): void
{
    $r = redisClient();
    $keys = array_merge($r->keys('shop:shop_ratelimit:*'), $r->keys('shop:shop_brute:*'));
    if ($keys) {
        foreach (array_chunk($keys, 500) as $chunk) {
            $r->del($chunk);
        }
    }
}

/** 汇总输出（运行结束时调用） */
function finish(string $group): int
{
    $rows = $GLOBALS['__results'];
    $passed = count(array_filter($rows, fn($r) => $r[3] === 'PASS'));
    $total = count($rows);
    echo "\n===== {$group} 结果: {$passed}/{$total} 通过 =====\n";
    return $passed === $total ? 0 : 1;
}

/** 运行单个测试文件，返回其失败数 */
function runFile(string $file): int
{
    echo "\n--- 执行 {$file} ---\n";
    require_once $file;
    return finish($file);
}
