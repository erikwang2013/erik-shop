<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\HashidsHelper;
use app\common\Jwt;
use app\common\SocialAuth;
use app\model\Users;
use app\model\UserSocialAccounts;
use Webman\Http\Request;

class SocialAuthController extends \app\controller\BaseApiController
{
    public function login(Request $request): \support\Response
    {
        $provider = $request->input('provider');  // google/apple/facebook
        $idToken = $request->input('id_token', '');
        $email = $request->input('email', '');
        $name = $request->input('name', '');

        if (!in_array($provider, ['google','apple','facebook'])) {
            return ApiResponse::fail('不支持的社交平台', 422);
        }
        if ($idToken === '') {
            return ApiResponse::fail('缺少 id_token', 422);
        }

        // 验证 id_token（fail-closed），provider_user_id 取自验证结果而非客户端提交
        try {
            $verified = SocialAuth::verify($provider, $idToken, $email);
        } catch (\Throwable $e) {
            return ApiResponse::fail('社交登录验证失败: ' . $e->getMessage(), 401);
        }
        $providerUserId = $verified['sub'];

        // 查找已有绑定
        $social = UserSocialAccounts::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)->first();

        if ($social) {
            $user = Users::find($social->user_id);
            if ($user) {
                $user->last_login_at = date('Y-m-d H:i:s');
                $user->last_login_ip = $request->getRealIp();
                $user->save();
                return ApiResponse::success([
                    'user_id' => HashidsHelper::encode($user->id),
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'is_new' => false,
                    'access_token' => Jwt::encode(['sub' => (string)$user->id, 'email' => $user->email, 'level' => $user->level]),
                    'refresh_token' => Jwt::encodeRefresh(['sub' => (string)$user->id], config('jwt.refresh_expire', 1209600)),
                    'expires_in' => config('jwt.default_expire', 7200),
                ], '登录成功');
            }
        }

        // 邮箱匹配已有账号：仅在平台返回的邮箱已通过验证时才绑定，
        // 防止攻击者提交他人邮箱接管账户（Apple 隐藏邮箱 / Facebook 未授权时 email 为空，不进入此分支）
        if ($email !== '' && !empty($verified['email'])) {
            $user = Users::where('email', $email)->first();
            if ($user) {
                UserSocialAccounts::create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => $providerUserId,
                ]);
                $user->last_login_at = date('Y-m-d H:i:s');
                $user->last_login_ip = $request->getRealIp();
                $user->save();
                return ApiResponse::success([
                    'user_id' => HashidsHelper::encode($user->id),
                    'nickname' => $user->nickname,
                    'email' => $user->email,
                    'is_new' => false,
                    'access_token' => Jwt::encode(['sub' => (string)$user->id, 'email' => $user->email, 'level' => $user->level]),
                    'refresh_token' => Jwt::encodeRefresh(['sub' => (string)$user->id], config('jwt.refresh_expire', 1209600)),
                    'expires_in' => config('jwt.default_expire', 7200),
                ], '登录成功');
            }
        }

        // 新注册：邮箱已被占用且未能通过社交平台验证所有权时拒绝，避免静默接管或唯一键冲突
        if ($email !== '' && Users::where('email', $email)->exists()) {
            return ApiResponse::fail('该邮箱已被注册，且无法通过社交平台验证邮箱所有权', 409);
        }

        // 新注册
        $user = Users::create([
            'nickname' => $name ?: ucfirst($provider) . 'User',
            'email' => $email,
            'password' => '',
            'salt' => bin2hex(random_bytes(3)),
            'invite_code' => strtoupper(substr(md5(uniqid()), 0, 8)),
            'status' => 1,
        ]);

        UserSocialAccounts::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);

        return ApiResponse::success([
            'user_id' => HashidsHelper::encode($user->id),
            'nickname' => $user->nickname,
            'email' => $user->email,
            'is_new' => true,
            'access_token' => Jwt::encode(['sub' => (string)$user->id, 'email' => $user->email, 'level' => $user->level]),
            'refresh_token' => Jwt::encodeRefresh(['sub' => (string)$user->id], config('jwt.refresh_expire', 1209600)),
            'expires_in' => config('jwt.default_expire', 7200),
        ], '注册成功');
    }
}
