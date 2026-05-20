<?php
namespace app\controller\v1;

use app\common\ApiResponse;
use app\common\HashidsHelper;
use app\model\Users;
use app\common\Jwt;
use Webman\Http\Request;

class AuthController extends \app\controller\BaseApiController
{
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

        return ApiResponse::success([
            'user_id' => HashidsHelper::encode($user->id),
            'nickname' => $user->nickname,
            'email' => $user->email,
            'access_token' => JWT::encode(['sub' => (string) $user->id, 'email' => $user->email, 'level' => $user->level]),
            'expires_in' => config('jwt.default_expire', 7200),
        ], '注册成功');
    }

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

        return ApiResponse::success([
            'user_id' => HashidsHelper::encode($user->id),
            'nickname' => $user->nickname,
            'email' => $user->email,
            'level' => $user->level,
            'access_token' => JWT::encode(['sub' => (string) $user->id, 'email' => $user->email, 'level' => $user->level]),
            'expires_in' => config('jwt.default_expire', 7200),
        ], '登录成功');
    }

    public function refresh(Request $request): \support\Response
    {
        $refreshToken = $request->input('refresh_token');
        if (empty($refreshToken)) return ApiResponse::fail('refresh_token不能为空', 422);

        try {
            $payload = JWT::decode($refreshToken);
            $newToken = JWT::encode([
                'sub' => $payload['sub'] ?? '',
                'email' => $payload['email'] ?? '',
                'level' => $payload['level'] ?? 0,
            ]);
            return ApiResponse::success([
                'access_token' => $newToken,
                'expires_in' => config('jwt.default_expire', 7200),
            ], 'Token已刷新');
        } catch (\Throwable $e) {
            return ApiResponse::fail('Token无效或已过期', 401);
        }
    }
}
