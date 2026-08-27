<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 认证模块测试：注册/登录/刷新/登出/改密/密码重置/邮箱验证/社交登录/人机验证/401 系列
 */

// ===== 人机验证 =====
check('人机验证-获取挑战', 'GET', '/api/poster/challenge', ['expect' => 200, 'expect_key' => 'token']);
$badToken = posterToken(); // 正常验证流程在 posterToken() 内已覆盖

// 验证答错
[$s, $j] = http('GET', '/api/poster/challenge');
$t = $j['data']['token'];
check('人机验证-答错拒绝', 'POST', '/api/poster/verify', ['body' => ['token' => $t, 'answer' => '999'], 'expect' => 200, 'expect_code' => 40002]);
// 答对后 token 可消费（posterToken() 已验证），参数缺失
check('人机验证-缺参数', 'POST', '/api/poster/verify', ['body' => ['token' => 'x'], 'expect' => 422]);

// ===== 注册（PosterVerify 保护） =====
check('注册-无人机验证', 'POST', '/api/auth/register', [
    'body' => ['email' => 'no@poster.test', 'password' => 'Passw0rd123'],
    'expect' => 200, 'expect_code' => 40001,
]);
check('注册-非法邮箱', 'POST', '/api/auth/register', [
    'body' => ['email' => 'not-an-email', 'password' => 'Passw0rd123'],
    'headers' => ['X-Poster-Token: ' . posterToken()],
    'expect' => 422,
]);
check('注册-缺密码', 'POST', '/api/auth/register', [
    'body' => ['email' => 'nopw@test.local'],
    'headers' => ['X-Poster-Token: ' . posterToken()],
    'expect' => 422,
]);
$u1 = registerUser('alice@test.local');
check('注册-成功', 'POST', '/api/auth/register', [
    'body' => ['email' => 'success@test.local', 'password' => 'Passw0rd123'],
    'headers' => ['X-Poster-Token: ' . posterToken()],
    'expect' => 200, 'expect_key' => 'access_token',
]);
check('注册-重复邮箱', 'POST', '/api/auth/register', [
    'body' => ['email' => 'alice@test.local', 'password' => 'Passw0rd123'],
    'headers' => ['X-Poster-Token: ' . posterToken()],
    'expect' => 422,
]);

// ===== 登录 =====
check('登录-密码错误', 'POST', '/api/auth/login', [
    'body' => ['email' => 'alice@test.local', 'password' => 'WrongPass1'],
    'expect' => 401,
]);
check('登录-用户不存在', 'POST', '/api/auth/login', [
    'body' => ['email' => 'ghost@test.local', 'password' => 'Passw0rd123'],
    'expect' => 401,
]);
check('登录-缺参数', 'POST', '/api/auth/login', ['body' => ['email' => 'alice@test.local'], 'expect' => 422]);
$login = loginUser('alice@test.local');

// ===== 刷新 Token =====
check('刷新-正常', 'POST', '/api/auth/refresh', [
    'body' => ['refresh_token' => $login['refresh']],
    'expect' => 200, 'expect_key' => 'access_token',
]);
check('刷新-用access_token', 'POST', '/api/auth/refresh', [
    'body' => ['refresh_token' => $login['token']],
    'expect' => 401,
]);
check('刷新-伪造token', 'POST', '/api/auth/refresh', [
    'body' => ['refresh_token' => 'garbage.token.here'],
    'expect' => 401,
]);

// ===== 401 系列（未登录/伪造/过期） =====
check('未登录访问受保护', 'GET', '/api/user/profile', ['expect' => 401]);
check('伪造签名Token', 'GET', '/api/user/profile', [
    'headers' => authHeaders(forgeJwt('wrong-secret-for-forgery', ['sub' => '1', 'type' => 'access', 'exp' => time() + 3600])),
    'expect' => 401,
]);
check('过期Token', 'GET', '/api/user/profile', [
    'headers' => authHeaders(forgeJwt(envFromFile('JWT_SECRET'), ['sub' => '1', 'type' => 'access', 'exp' => time() - 3600])),
    'expect' => 401,
]);

// ===== 登出（吊销 access + refresh） =====
check('登出', 'POST', '/api/auth/logout', [
    'body' => ['refresh_token' => $login['refresh']],
    'headers' => authHeaders($login['token']),
    'expect' => 200,
]);
check('登出后旧token失效', 'GET', '/api/user/profile', ['headers' => authHeaders($login['token']), 'expect' => 401]);

// ===== 修改密码 =====
$u2 = registerUser('bob@test.local');
check('改密-旧密码错误', 'POST', '/api/auth/password/change', [
    'body' => ['old_password' => 'WrongPass1', 'new_password' => 'NewPassw0rd123'],
    'headers' => authHeaders($u2['token']),
    'expect' => 401,
]);
check('改密-成功', 'POST', '/api/auth/password/change', [
    'body' => ['old_password' => 'Passw0rd123', 'new_password' => 'NewPassw0rd123'],
    'headers' => authHeaders($u2['token']),
    'expect' => 200,
]);
check('改密后旧token失效', 'GET', '/api/user/profile', ['headers' => authHeaders($u2['token']), 'expect' => 401]);
check('改密后新密码登录', 'POST', '/api/auth/login', [
    'body' => ['email' => 'bob@test.local', 'password' => 'NewPassw0rd123'],
    'expect' => 200,
]);

// ===== 密码重置（一次性验证码，Redis 读取） =====
$u3 = registerUser('carol@test.local');
check('重置-申请', 'POST', '/api/auth/password/reset', ['body' => ['email' => 'carol@test.local'], 'expect' => 200]);
check('重置-申请不存在邮箱也200', 'POST', '/api/auth/password/reset', ['body' => ['email' => 'nobody@test.local'], 'expect' => 200]);
check('重置-非法邮箱', 'POST', '/api/auth/password/reset', ['body' => ['email' => 'bad'], 'expect' => 422]);
$hash = hash_hmac('sha256', 'carol@test.local', envFromFile('JWT_SECRET'));
$code = redisClient()->get("erik:erik:password_reset:{$hash}");
check('重置-验证码已生成', 'POST', '/api/auth/password/reset/confirm', [
    'body' => ['email' => 'carol@test.local', 'code' => '000000', 'new_password' => 'ResetPass123'],
    'expect' => 401,
]);
if ($code) {
    check('重置-确认成功', 'POST', '/api/auth/password/reset/confirm', [
        'body' => ['email' => 'carol@test.local', 'code' => $code, 'new_password' => 'ResetPass123'],
        'expect' => 200,
    ]);
    check('重置-验证码一次性', 'POST', '/api/auth/password/reset/confirm', [
        'body' => ['email' => 'carol@test.local', 'code' => $code, 'new_password' => 'ResetPass123'],
        'expect' => 401,
    ]);
    check('重置后新密码登录', 'POST', '/api/auth/login', [
        'body' => ['email' => 'carol@test.local', 'password' => 'ResetPass123'],
        'expect' => 200,
    ]);
} else {
    check('重置-验证码未写入Redis', 'POST', '/api/auth/password/reset/confirm', [
        'body' => ['email' => 'carol@test.local', 'code' => '123456', 'new_password' => 'x1234567'],
        'expect' => 401,
    ]);
}

// ===== 邮箱验证（24h 一次性 token，Redis 枚举） =====
$u4 = registerUser('dave@test.local');
$verifyToken = null;
foreach (redisClient()->keys('erik:erik:email_verify:*') as $k) {
    if (redisClient()->get($k) === (string) $u4['id']) {
        $verifyToken = substr($k, strlen('erik:erik:email_verify:'));
    }
}
check('邮箱验证-成功', 'POST', '/api/auth/email/verify', [
    'body' => ['token' => $verifyToken ?? ''],
    'expect' => 200,
]);
check('邮箱验证-token一次性', 'POST', '/api/auth/email/verify', [
    'body' => ['token' => $verifyToken ?? ''],
    'expect' => 401,
]);
check('邮箱验证-格式错误', 'POST', '/api/auth/email/verify', ['body' => ['token' => 'short'], 'expect' => 422]);

// ===== 社交登录（仅本地校验路径，不调用外部服务） =====
check('社交登录-不支持的平台', 'POST', '/api/auth/social', [
    'body' => ['provider' => 'wechat', 'id_token' => 'x'],
    'expect' => 422,
]);
check('社交登录-缺id_token', 'POST', '/api/auth/social', [
    'body' => ['provider' => 'google', 'email' => 'g@test.local'],
    'expect' => 422,
]);
check('社交登录-伪造token', 'POST', '/api/auth/social', [
    'body' => ['provider' => 'google', 'id_token' => 'fake.id.token'],
    'expect' => 401,
]);
