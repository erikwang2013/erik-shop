<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\HashidsHelper;
use app\model\Users;
use Webman\Http\Request;

/**
 * 认证控制器
 * 注册、登录、刷新Token、登出
 */
class AuthController extends \app\controller\BaseApiController
{
    /**
     * 注册
     * POST /api/auth/register
     */
    public function register(Request $request): \support\Response
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $nickname = $request->input('nickname', '');

        if (empty($email) || empty($password)) {
            return ApiResponse::fail('邮箱和密码不能为空', 422);
        }

        if (Users::where('email', $email)->exists()) {
            return ApiResponse::fail('该邮箱已注册', 422);
        }

        $salt = bin2hex(random_bytes(3));
        $user = Users::create([
            'nickname' => $nickname ?: 'User' . substr(md5($email), 0, 8),
            'email' => $email,
            'password' => password_hash($password . $salt, PASSWORD_BCRYPT),
            'salt' => $salt,
            'invite_code' => strtoupper(substr(md5(uniqid()), 0, 8)),
            'status' => 1,
        ]);

        // TODO: 集成 erikwang2013/jwt-webman 生成token
        $token = $this->generateToken($user);

        return ApiResponse::success([
            'user_id' => HashidsHelper::encode($user->id),
            'nickname' => $user->nickname,
            'email' => $user->email,
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'expires_in' => config('jwt.access_ttl', 7200),
        ], '注册成功');
    }

    /**
     * 登录
     * POST /api/auth/login
     */
    public function login(Request $request): \support\Response
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (empty($email) || empty($password)) {
            return ApiResponse::fail('邮箱和密码不能为空', 422);
        }

        $user = Users::where('email', $email)->where('status', 1)->first();
        if (!$user || !password_verify($password . $user->salt, $user->password)) {
            return ApiResponse::fail('邮箱或密码错误', 401);
        }

        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp();
        $user->save();

        $token = $this->generateToken($user);

        return ApiResponse::success([
            'user_id' => HashidsHelper::encode($user->id),
            'nickname' => $user->nickname,
            'email' => $user->email,
            'level' => $user->level,
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'expires_in' => config('jwt.access_ttl', 7200),
        ], '登录成功');
    }

    /**
     * 刷新Token
     * POST /api/auth/refresh
     */
    public function refresh(Request $request): \support\Response
    {
        $refreshToken = $request->input('refresh_token');

        if (empty($refreshToken)) {
            return ApiResponse::fail('refresh_token不能为空', 422);
        }

        // TODO: JWT refresh逻辑
        // $payload = Jwt::refresh($refreshToken);

        return ApiResponse::success([
            'access_token' => 'new_token_placeholder',
            'expires_in' => config('jwt.access_ttl', 7200),
        ], 'Token已刷新');
    }

    /**
     * 生成JWT Token
     */
    private function generateToken($user): array
    {
        // TODO: 集成 erikwang2013/jwt-webman
        return [
            'access_token' => 'access_token_' . $user->id . '_' . time(),
            'refresh_token' => 'refresh_token_' . $user->id . '_' . time(),
        ];
    }
}
