<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\DistributedLock;
use app\common\HashidsHelper;
use app\common\RiskEngine;
use app\model\Users;
use app\common\Jwt;
use support\Redis;
use Webman\Http\Request;

/**
 * @Apidoc\Group("auth")
 * @Apidoc\Sort(1)
 */
class AuthController extends \app\controller\BaseApiController
{
    /**
     * @Apidoc\Title("用户注册")
     * @Apidoc\Desc("邮箱注册新账号，需要人机验证(X-Poster-Token)")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/auth/register")
     * @Apidoc\Author("erik")
     * @Apidoc\Header(name="X-Poster-Token", type="string", require=true, desc="人机验证Token")
     * @Apidoc\Param(name="email", type="string", require=true, desc="邮箱")
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码")
     * @Apidoc\Param(name="nickname", type="string", require=false, desc="昵称", default="")
     * @Apidoc\Returned(name="user_id", type="string", desc="用户ID(hashids)")
     * @Apidoc\Returned(name="access_token", type="string", desc="JWT令牌")
     * @Apidoc\Returned(name="expires_in", type="int", desc="有效期(秒)")
     */
    public function register(Request $request): \support\Response
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $nickname = $request->input('nickname', '');

        if (empty($email) || empty($password)) {
            return ApiResponse::fail('邮箱和密码不能为空', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ApiResponse::fail('邮箱格式不正确', 422);
        }
        // 邮箱粒度锁：email_hash 无唯一索引，并发注册同一邮箱会创建重复账号
        try {
            return DistributedLock::run("lock:register:" . Users::emailHash($email), function () use ($email, $password, $nickname, $request) {
                if (Users::where('email_hash', Users::emailHash($email))->exists()) {
                    return ApiResponse::fail('该邮箱已注册', 422);
                }

                $salt = bin2hex(random_bytes(3));
                $user = Users::create([
                    'nickname' => $nickname ?: 'User' . substr(md5($email), 0, 8),
                    'email' => $email,
                    'email_hash' => Users::emailHash($email),
                    'password' => password_hash($password . $salt, PASSWORD_BCRYPT),
                    'salt' => $salt,
                    'invite_code' => strtoupper(substr(md5(uniqid()), 0, 8)),
                    'status' => 1,
                ]);

                // 生成邮箱验证 token（Redis 24h 过期，邮件占位发送）
                try {
                    $verifyToken = bin2hex(random_bytes(16));
                    Redis::setex("erik:email_verify:{$verifyToken}", 86400, (string)$user->id);
                    self::logMail($email, '验证您的邮箱', "验证链接: /api/email/verify token={$verifyToken}");
                } catch (\Throwable $e) {
                    // Redis 不可用时降级：注册成功但暂不发验证邮件（与限流中间件 fail-open 策略一致）
                    \support\Log::warning('邮箱验证token生成失败: ' . $e->getMessage());
                }

                // 风控旁路打分（注册事件，bypass 模式不阻断）
                $riskContext = ['user_id' => $user->id, 'ip' => $request->getRealIp(), 'email' => $email];
                RiskEngine::log('user_register', $riskContext, RiskEngine::score('user_register', $riskContext));

                return ApiResponse::success([
                    'user_id' => HashidsHelper::encode($user->id),
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'access_token' => Jwt::encode(['sub' => (string)$user->id, 'email' => $user->email, 'level' => $user->level]),
                    'refresh_token' => Jwt::encodeRefresh(['sub' => (string)$user->id], config('jwt.refresh_expire', 1209600)),
                    'expires_in' => config('jwt.default_expire', 7200),
                ], '注册成功');
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('操作繁忙，请稍后重试', 429);
        }
    }

    /**
     * @Apidoc\Title("用户登录")
     * @Apidoc\Desc("邮箱+密码登录，返回JWT令牌")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/auth/login")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="email", type="string", require=true, desc="邮箱")
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码")
     * @Apidoc\Returned(name="user_id", type="string", desc="用户ID")
     * @Apidoc\Returned(name="nickname", type="string", desc="昵称")
     * @Apidoc\Returned(name="level", type="int", desc="会员等级")
     * @Apidoc\Returned(name="access_token", type="string", desc="JWT令牌")
     * @Apidoc\Returned(name="expires_in", type="int", desc="有效期(秒)")
     */
    public function login(Request $request): \support\Response
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (empty($email) || empty($password)) {
            return ApiResponse::fail('邮箱和密码不能为空', 422);
        }

        $user = Users::where('email_hash', Users::emailHash($email))->where('status', 1)->first();
        if (!$user || !password_verify($password . $user->salt, $user->password)) {
            return ApiResponse::fail('邮箱或密码错误', 401);
        }

        $user->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request->getRealIp(),
        ]);

        return ApiResponse::success([
            'user_id' => HashidsHelper::encode($user->id),
            'nickname' => $user->nickname,
            'email' => $user->email,
            'level' => $user->level,
            'access_token' => Jwt::encode(['sub' => (string)$user->id, 'email' => $user->email, 'level' => $user->level]),
            'refresh_token' => Jwt::encodeRefresh(['sub' => (string)$user->id], config('jwt.refresh_expire', 1209600)),
            'expires_in' => config('jwt.default_expire', 7200),
        ], '登录成功');
    }

    /**
     * @Apidoc\Title("刷新Token")
     * @Apidoc\Desc("使用refresh_token获取新的access_token")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/auth/refresh")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="refresh_token", type="string", require=true, desc="刷新令牌")
     * @Apidoc\Returned(name="access_token", type="string", desc="新JWT令牌")
     * @Apidoc\Returned(name="expires_in", type="int", desc="有效期(秒)")
     */
    public function refresh(Request $request): \support\Response
    {
        $refreshToken = $request->input('refresh_token');
        if (empty($refreshToken)) {
            return ApiResponse::fail('refresh_token不能为空', 422);
        }
        try {
            $payload = Jwt::decode($refreshToken);
            if (empty($payload['sub']) || ($payload['type'] ?? '') !== 'refresh') {
                return ApiResponse::fail('Token无效', 401);
            }
            if (Jwt::isRevoked($refreshToken)) {
                return ApiResponse::fail('Token已失效，请重新登录', 401);
            }
            // 刷新轮换：同时签发新的 access_token 与 refresh_token
            return ApiResponse::success([
                'access_token' => Jwt::encode([
                    'sub' => $payload['sub'],
                    'email' => $payload['email'] ?? '',
                    'level' => $payload['level'] ?? 0,
                ]),
                'refresh_token' => Jwt::encodeRefresh(['sub' => $payload['sub']], config('jwt.refresh_expire', 1209600)),
                'expires_in' => config('jwt.default_expire', 7200),
            ], 'Token已刷新');
        } catch (\Throwable $e) {
            return ApiResponse::fail('Token无效或已过期', 401);
        }
    }

    /**
     * @Apidoc\Title("退出登录")
     * @Apidoc\Desc("吊销当前 access_token（及可选的 refresh_token）")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/auth/logout")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="refresh_token", type="string", require=false, desc="刷新令牌，一并吊销")
     */
    public function logout(Request $request): \support\Response
    {
        Jwt::revoke($this->extractToken($request));
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken) {
            Jwt::revoke($refreshToken);
        }
        return ApiResponse::success(null, '已退出登录');
    }

    /**
     * @Apidoc\Title("修改密码")
     * @Apidoc\Desc("验证旧密码后更新密码，吊销当前 access_token（及可选的 refresh_token）")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/auth/password/change")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="old_password", type="string", require=true, desc="旧密码")
     * @Apidoc\Param(name="new_password", type="string", require=true, desc="新密码")
     * @Apidoc\Param(name="refresh_token", type="string", require=false, desc="刷新令牌，一并吊销")
     */
    public function changePassword(Request $request): \support\Response
    {
        $oldPassword = $request->input('old_password');
        $newPassword = $request->input('new_password');
        if (empty($oldPassword) || empty($newPassword)) {
            return ApiResponse::fail('旧密码和新密码不能为空', 422);
        }
        if (strlen($newPassword) < 6 || strlen($newPassword) > 64) {
            return ApiResponse::fail('新密码长度须为6-64位', 422);
        }

        $user = Users::find($request->userId);
        if (!$user || !password_verify($oldPassword . $user->salt, $user->password)) {
            return ApiResponse::fail('旧密码错误', 401);
        }

        $salt = bin2hex(random_bytes(3));
        $user->update([
            'password' => password_hash($newPassword . $salt, PASSWORD_BCRYPT),
            'salt' => $salt,
        ]);

        // 改密后旧 token 全部失效
        Jwt::revoke($this->extractToken($request));
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken) {
            Jwt::revoke($refreshToken);
        }

        return ApiResponse::success(null, '密码已修改，请重新登录');
    }

    /**
     * @Apidoc\Title("申请密码重置")
     * @Apidoc\Desc("统一提示，不泄露邮箱是否存在；存在则发送一次性验证码（30分钟有效）")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/auth/password/reset")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="email", type="string", require=true, desc="邮箱")
     */
    public function passwordResetRequest(Request $request): \support\Response
    {
        $email = $request->input('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ApiResponse::fail('邮箱格式不正确', 422);
        }

        $user = Users::where('email_hash', Users::emailHash($email))->where('status', 1)->first();
        if ($user) {
            try {
                $code = (string)random_int(100000, 999999);
                Redis::setex("erik:password_reset:{$user->email_hash}", 1800, $code);
                self::logMail($email, '重置您的密码', "验证码: {$code}（30分钟内有效，一次性使用）");
            } catch (\Throwable $e) {
                \support\Log::warning('密码重置验证码生成失败: ' . $e->getMessage());
            }
        }

        // 统一响应，防止枚举已注册邮箱
        return ApiResponse::success(null, '若邮箱存在将收到邮件');
    }

    /**
     * @Apidoc\Title("确认密码重置")
     * @Apidoc\Desc("校验一次性验证码后更新密码，验证码立即失效")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/auth/password/reset/confirm")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="email", type="string", require=true, desc="邮箱")
     * @Apidoc\Param(name="code", type="string", require=true, desc="验证码")
     * @Apidoc\Param(name="new_password", type="string", require=true, desc="新密码")
     */
    public function passwordResetConfirm(Request $request): \support\Response
    {
        $email = $request->input('email');
        $code = $request->input('code');
        $newPassword = $request->input('new_password');
        if (empty($email) || empty($code) || empty($newPassword)) {
            return ApiResponse::fail('邮箱、验证码和新密码不能为空', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $code)) {
            return ApiResponse::fail('邮箱或验证码格式不正确', 422);
        }
        if (strlen($newPassword) < 6 || strlen($newPassword) > 64) {
            return ApiResponse::fail('新密码长度须为6-64位', 422);
        }

        $user = Users::where('email_hash', Users::emailHash($email))->first();
        $key = "erik:password_reset:" . ($user->email_hash ?? '');
        try {
            $stored = $user ? Redis::get($key) : null;
            if ($stored && hash_equals($stored, $code)) {
                Redis::del($key); // 一次性
            }
        } catch (\Throwable $e) {
            $stored = null; // Redis 不可用时视为无效，避免误重置
        }
        if (!$user || empty($stored) || !hash_equals($stored, $code)) {
            return ApiResponse::fail('验证码无效或已过期', 401);
        }
        $salt = bin2hex(random_bytes(3));
        $user->update([
            'password' => password_hash($newPassword . $salt, PASSWORD_BCRYPT),
            'salt' => $salt,
        ]);

        return ApiResponse::success(null, '密码已重置，请重新登录');
    }

    /**
     * @Apidoc\Title("邮箱验证")
     * @Apidoc\Desc("使用注册时发送的 token 完成邮箱验证（24小时有效，一次性）")
     * @Apidoc\Method("POST")
     * @Apidoc\Url("/api/auth/email/verify")
     * @Apidoc\Author("erik")
     * @Apidoc\Param(name="token", type="string", require=true, desc="验证token")
     */
    public function emailVerify(Request $request): \support\Response
    {
        $token = $request->input('token');
        if (empty($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return ApiResponse::fail('token格式不正确', 422);
        }

        $key = "erik:email_verify:{$token}";
        try {
            $userId = Redis::get($key);
            if ($userId) {
                Redis::del($key); // 一次性
            }
        } catch (\Throwable $e) {
            $userId = null; // Redis 不可用时视为无效
        }
        $user = $userId ? Users::find($userId) : null;
        if (!$user) {
            return ApiResponse::fail('token无效或已过期', 401);
        }
        if (!$user->email_verified_at) {
            $user->update(['email_verified_at' => date('Y-m-d H:i:s')]);
        }

        return ApiResponse::success(null, '邮箱验证成功');
    }

    /**
     * 邮件发送占位：项目暂无 SMTP 邮件设施（config/ 无 mail.php），先落日志。
     * 接入邮件服务后，将本方法替换为真实发送（可复用 erik_email_logs 表留痕）。
     */
    private static function logMail(string $to, string $subject, string $body): void
    {
        \support\Log::info("[mail-placeholder] to={$to} subject={$subject} body={$body}");
    }

    private function extractToken(Request $request): string
    {
        $header = $request->header('Authorization', '');
        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return $matches[1];
        }
        return (string)$request->input('token');
    }
}
