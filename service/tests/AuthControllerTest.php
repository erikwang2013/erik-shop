<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\Jwt;
use app\controller\v1\AuthController;
use app\controller\v1\SocialAuthController;
use app\model\Users;
use support\Redis;

/**
 * 认证体系控制器集成测试
 *
 * 覆盖 AuthController 全部 8 个接口（注册/登录/刷新/登出/改密/重置两段/邮箱验证）
 * 与 SocialAuthController（provider 校验、id_token 缺失、验证失败 401）。
 * 控制器直接调用（不走路由/中间件），断言状态码与响应结构。
 */
class AuthControllerTest extends IntegrationTestCase
{
    use TestSeederTrait;

    private function auth(): AuthController
    {
        return new AuthController();
    }

    private function makeUser(string $password = 'pass1234'): int
    {
        $email = 'auth_' . uniqid() . '@example.com';
        $salt = bin2hex(random_bytes(3));
        $user = Users::create([
            'nickname' => 'Auth QA', 'email' => $email,
            'email_hash' => Users::emailHash($email),
            'password' => password_hash($password . $salt, PASSWORD_BCRYPT),
            'salt' => $salt, 'invite_code' => 'AU' . substr(md5(uniqid()), 0, 6), 'status' => 1,
        ]);
        $this->trackCreated('erik_users', (int) $user->id);
        return (int) $user->id;
    }

    public function test_register_success_returns_tokens(): void
    {
        $req = $this->makeRequest('POST', '/api/auth/register', [
            'email' => 'reg_' . uniqid() . '@example.com',
            'password' => 'pass1234', 'nickname' => 'Reg QA',
        ]);
        [$code, $json] = $this->callController($this->auth(), 'register', $req);

        $this->assertSame(200, $code);
        $this->assertNotEmpty($json['data']['user_id'] ?? '', '应返回 hashids user_id');
        $this->assertNotEmpty($json['data']['access_token'] ?? '');
        $this->assertNotEmpty($json['data']['refresh_token'] ?? '');
        $this->assertSame(7200, $json['data']['expires_in'] ?? 0);
    }

    public function test_register_missing_fields_422(): void
    {
        $req = $this->makeRequest('POST', '/api/auth/register', ['email' => 'a@b.com']);
        [$code, $json] = $this->callController($this->auth(), 'register', $req);
        $this->assertSame(422, $code);
        $this->assertStringContainsString('不能为空', $json['msg'] ?? '');
    }

    public function test_register_duplicate_email_422(): void
    {
        $email = 'dup_' . uniqid() . '@example.com';
        $this->callController($this->auth(), 'register', $this->makeRequest('POST', '/x', ['email' => $email, 'password' => 'pass1234']));
        [$code] = $this->callController($this->auth(), 'register', $this->makeRequest('POST', '/x', ['email' => $email, 'password' => 'pass1234']));
        $this->assertSame(422, $code);
    }

    public function test_login_success_and_wrong_password(): void
    {
        $email = Users::find($this->makeUser())->email;

        [$code, $json] = $this->callController($this->auth(), 'login', $this->makeRequest('POST', '/x', ['email' => $email, 'password' => 'pass1234']));
        $this->assertSame(200, $code);
        $this->assertNotEmpty($json['data']['access_token'] ?? '');

        [$badCode] = $this->callController($this->auth(), 'login', $this->makeRequest('POST', '/x', ['email' => $email, 'password' => 'wrong']));
        $this->assertSame(401, $badCode);
    }

    public function test_refresh_rotates_token(): void
    {
        $token = Jwt::encodeRefresh(['sub' => '1'], 3600);
        [$code, $json] = $this->callController($this->auth(), 'refresh', $this->makeRequest('POST', '/x', ['refresh_token' => $token]));
        $this->assertSame(200, $code);
        $this->assertNotEmpty($json['data']['access_token'] ?? '');
        $this->assertNotEmpty($json['data']['refresh_token'] ?? '');
    }

    public function test_refresh_invalid_token_401(): void
    {
        [$code] = $this->callController($this->auth(), 'refresh', $this->makeRequest('POST', '/x', ['refresh_token' => 'invalid.token.here']));
        $this->assertSame(401, $code);
    }

    public function test_logout_revokes_token(): void
    {
        $token = Jwt::encode(['sub' => '1']);
        $req = $this->makeRequest('POST', '/x', [], ['Authorization' => 'Bearer ' . $token]);
        [$code] = $this->callController($this->auth(), 'logout', $req);
        $this->assertSame(200, $code);
        $this->assertTrue(Jwt::isRevoked($token), 'logout 后 access_token 应被吊销');
    }

    public function test_change_password_flow(): void
    {
        $userId = $this->makeUser('oldpass1');
        $token = Jwt::encode(['sub' => (string) $userId]);
        $req = $this->makeRequest('POST', '/x', ['old_password' => 'oldpass1', 'new_password' => 'newpass1'], ['Authorization' => 'Bearer ' . $token]);
        $req->userId = $userId;

        [$code, $json] = $this->callController($this->auth(), 'changePassword', $req);
        $this->assertSame(200, $code);

        // 旧密码登录应失败、新密码成功
        $email = Users::find($userId)->email;
        [$old] = $this->callController($this->auth(), 'login', $this->makeRequest('POST', '/x', ['email' => $email, 'password' => 'oldpass1']));
        [$ok] = $this->callController($this->auth(), 'login', $this->makeRequest('POST', '/x', ['email' => $email, 'password' => 'newpass1']));
        $this->assertSame(401, $old);
        $this->assertSame(200, $ok);
    }

    public function test_password_reset_full_flow(): void
    {
        $email = Users::find($this->makeUser())->email;
        $user = Users::where('email_hash', Users::emailHash($email))->first();

        // 申请重置（统一响应，不泄露邮箱是否存在）
        [$code, $json] = $this->callController($this->auth(), 'passwordResetRequest', $this->makeRequest('POST', '/x', ['email' => $email]));
        $this->assertSame(200, $code);

        // 从 Redis 取验证码（测试环境直接读）
        $code6 = Redis::get("erik:password_reset:{$user->email_hash}");
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code6);

        [$ok, $okJson] = $this->callController($this->auth(), 'passwordResetConfirm', $this->makeRequest('POST', '/x', [
            'email' => $email, 'code' => $code6, 'new_password' => 'newpass2',
        ]));
        $this->assertSame(200, $ok);

        // 验证码一次性：重复使用应 401
        [$again] = $this->callController($this->auth(), 'passwordResetConfirm', $this->makeRequest('POST', '/x', [
            'email' => $email, 'code' => $code6, 'new_password' => 'newpass3',
        ]));
        $this->assertSame(401, $again);
    }

    public function test_password_reset_invalid_email_format_422(): void
    {
        [$code] = $this->callController($this->auth(), 'passwordResetRequest', $this->makeRequest('POST', '/x', ['email' => 'not-an-email']));
        $this->assertSame(422, $code);
    }

    public function test_email_verify_flow(): void
    {
        $userId = $this->makeUser();
        $token = bin2hex(random_bytes(16));
        Redis::setex("erik:email_verify:{$token}", 600, (string) $userId);

        [$code] = $this->callController($this->auth(), 'emailVerify', $this->makeRequest('POST', '/x', ['token' => $token]));
        $this->assertSame(200, $code);
        $this->assertNotNull(Users::find($userId)->email_verified_at);

        // 一次性：二次使用 401
        [$again] = $this->callController($this->auth(), 'emailVerify', $this->makeRequest('POST', '/x', ['token' => $token]));
        $this->assertSame(401, $again);
    }

    public function test_email_verify_bad_token_format_422(): void
    {
        [$code] = $this->callController($this->auth(), 'emailVerify', $this->makeRequest('POST', '/x', ['token' => 'zz']));
        $this->assertSame(422, $code);
    }

    public function test_social_unsupported_provider_422(): void
    {
        $ctrl = new SocialAuthController();
        [$code] = $this->callController($ctrl, 'login', $this->makeRequest('POST', '/x', ['provider' => 'twitter', 'id_token' => 'x']));
        $this->assertSame(422, $code);
    }

    public function test_social_missing_id_token_422(): void
    {
        $ctrl = new SocialAuthController();
        [$code] = $this->callController($ctrl, 'login', $this->makeRequest('POST', '/x', ['provider' => 'google']));
        $this->assertSame(422, $code);
    }

    public function test_social_invalid_id_token_401(): void
    {
        $ctrl = new SocialAuthController();
        [$code, $json] = $this->callController($ctrl, 'login', $this->makeRequest('POST', '/x', [
            'provider' => 'google', 'id_token' => 'bad.token.zzz', 'email' => 'social_' . uniqid() . '@example.com',
        ]));
        $this->assertSame(401, $code);
        $this->assertStringContainsString('验证失败', $json['msg'] ?? '');
    }
}
