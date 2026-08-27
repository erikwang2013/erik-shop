<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use support\Redis;
use support\Request;
use support\Response;
use app\common\ApiResponse;

/**
 * 人机验证（Poster）签发控制器
 *
 * 背景：PosterVerify 中间件校验 Redis 键 shop:poster:{token} == '1'（一次性消费），
 * 但此前全项目无任何签发端，客户端无法获取 X-Poster-Token。
 * 本控制器提供最小可用的文本算术题验证码：
 *   GET  /api/poster/challenge  → 生成题目，返回 token + question，答案存 Redis
 *   POST /api/poster/verify     → 提交答案，正确则写入 shop:poster:{token}='1'
 *
 * 与 PosterVerify 中间件完全兼容（中间件只查 shop:poster:{token}）。
 * 前端可在注册/下单/支付前先完成验证，携带 X-Poster-Token 调用受保护接口。
 */
class PosterController
{
    /**
     * 获取人机验证挑战
     * GET /api/poster/challenge
     */
    public function challenge(Request $request): Response
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $op = ['+', '-', 'x'][random_int(0, 2)];
        $answer = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            default => $a * $b,
        };

        $token = bin2hex(random_bytes(16));
        $expire = (int) config('poster.expire', 300);

        try {
            Redis::setex("shop:poster:ans:{$token}", $expire, (string) $answer);
        } catch (\Throwable $e) {
            return ApiResponse::fail('验证服务暂不可用', 500);
        }

        return ApiResponse::success([
            'token' => $token,
            'type' => 'math',
            'question' => "{$a} {$op} {$b} = ?",
        ]);
    }

    /**
     * 提交验证答案，通过后签发可消费 token
     * POST /api/poster/verify  {token, answer}
     */
    public function verify(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $answer = trim((string) $request->input('answer', ''));

        if ($token === '' || $answer === '') {
            return ApiResponse::fail('参数不完整', 422);
        }

        $ansKey = "shop:poster:ans:{$token}";

        try {
            $expected = Redis::get($ansKey);
            if ($expected === null) {
                return ApiResponse::fail('验证已过期，请刷新重试', 40002);
            }
            // 答案一次性消费（无论对错）
            Redis::del($ansKey);

            if ($expected !== $answer) {
                return ApiResponse::fail('验证答案错误', 40002);
            }

            $expire = (int) config('poster.expire', 300);
            Redis::setex("shop:poster:{$token}", $expire, '1');
        } catch (\Throwable $e) {
            return ApiResponse::fail('验证服务暂不可用', 500);
        }

        return ApiResponse::success(['token' => $token]);
    }
}
