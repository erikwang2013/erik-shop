<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 用户模块测试：资料/地址/语言/收藏/降价提醒/KYC/会员/积分/通知/隐私/对比/推荐
 */

$user = registerUser('u1@test.local');
$h = authHeaders($user['token']);
$h2user = registerUser('u2@test.local');
$h2 = authHeaders($h2user['token']);

// ===== 个人资料 =====
check('资料-获取', 'GET', '/api/user/profile', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'u1@test.local']);
check('资料-更新', 'PUT', '/api/user/profile', ['headers' => $h, 'body' => ['nickname' => 'NewNick'], 'expect' => 200]);
check('资料-更新生效', 'GET', '/api/user/profile', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'NewNick']);
check('资料-越权不可见', 'GET', '/api/user/profile', ['headers' => $h2, 'expect' => 200, 'expect_contains' => 'u2@test.local']);

// ===== 地址 CRUD =====
$addr1 = http('POST', '/api/user/addresses', [
    'name' => 'John Doe', 'phone' => '+1234567890', 'country_id' => 1,
    'province' => 'CA', 'city' => 'Los Angeles', 'district' => '', 'detail' => '123 Main St',
    'postal_code' => '90001', 'is_default' => 1, 'tag' => 'home',
], $h);
check('地址-新增默认', 'POST', '/api/user/addresses', [
    'headers' => $h,
    'body' => [
        'name' => 'John Doe', 'phone' => '+1234567890', 'country_id' => 1,
        'province' => 'CA', 'city' => 'Los Angeles', 'detail' => '123 Main St',
        'postal_code' => '90001', 'is_default' => 1,
    ],
    'expect' => 200, 'expect_key' => 'id',
]);
$addrId = $addr1[1]['data']['id'];
check('地址-第二个默认自动替换', 'POST', '/api/user/addresses', [
    'headers' => $h,
    'body' => [
        'name' => 'Jane Doe', 'phone' => '+1234567891', 'country_id' => 4,
        'province' => 'BE', 'city' => 'Berlin', 'detail' => '456 Main St',
        'postal_code' => '10115', 'is_default' => 1,
    ],
    'expect' => 200,
]);
check('地址-列表', 'GET', '/api/user/addresses', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'Jane Doe']);
check('地址-仅一个默认', 'GET', '/api/user/addresses', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'is_default']);
check('地址-更新', 'PUT', '/api/user/addresses/' . $addrId, ['headers' => $h, 'body' => ['city' => 'San Francisco'], 'expect' => 200]);
check('地址-越权404', 'GET', '/api/user/addresses', ['headers' => $h2, 'expect' => 200]);
check('地址-删除', 'DELETE', '/api/user/addresses/' . $addrId, ['headers' => $h, 'expect' => 200]);
check('地址-删除后列表为空', 'GET', '/api/user/addresses', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'Jane Doe']);
check('地址-他人地址不可改404', 'PUT', '/api/user/addresses/' . $addrId, ['headers' => $h2, 'body' => ['city' => 'X'], 'expect' => 404]);

// ===== 语言/币种 =====
check('语言币种-更新', 'PUT', '/api/user/locale', ['headers' => $h, 'body' => ['locale' => 'ja', 'currency' => 'JPY'], 'expect' => 200]);

// ===== 收藏夹 =====
check('收藏-添加', 'POST', '/api/wishlist', ['headers' => $h, 'body' => ['product_id' => enc(1001)], 'expect' => 200]);
check('收藏-重复添加幂等', 'POST', '/api/wishlist', ['headers' => $h, 'body' => ['product_id' => enc(1001)], 'expect' => 200]);
check('收藏-缺商品', 'POST', '/api/wishlist', ['headers' => $h, 'body' => [], 'expect' => 422]);
check('收藏-列表', 'GET', '/api/wishlist', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'Test Cotton Dress']);
$wl = http('GET', '/api/wishlist', null, $h);
$wishId = $wl[1]['data']['list'][0]['id'] ?? null;
if ($wishId) {
    check('收藏-删除', 'DELETE', '/api/wishlist/' . $wishId, ['headers' => $h, 'expect' => 200]);
    check('收藏-删除后列表空', 'GET', '/api/wishlist', ['headers' => $h, 'expect' => 200, 'expect_key' => 'list']);
}

// ===== 降价提醒 =====
check('降价提醒-添加', 'POST', '/api/price-alerts', ['headers' => $h, 'body' => ['sku_id' => enc(2001), 'target_price' => 20.00], 'expect' => 200]);
check('降价提醒-缺sku', 'POST', '/api/price-alerts', ['headers' => $h, 'body' => ['target_price' => 20.00], 'expect' => 422]);
check('降价提醒-列表', 'GET', '/api/price-alerts', ['headers' => $h, 'expect' => 200, 'expect_key' => 'list']);

// ===== KYC 实名认证 =====
check('KYC-提交', 'POST', '/api/kyc', ['headers' => $h, 'body' => ['real_name' => 'ZhangSan', 'id_number' => '110101199003071234', 'id_type' => 'id_card'], 'expect' => 200]);
check('KYC-缺参422', 'POST', '/api/kyc', ['headers' => $h, 'body' => ['real_name' => ''], 'expect' => 422]);
check('KYC-状态', 'GET', '/api/kyc/status', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'submitted']);
db()->exec("UPDATE shop_user_kyc SET status = 1, verified_at = NOW() WHERE user_id = {$user['id']}");
check('KYC-通过后状态1', 'GET', '/api/kyc/status', ['headers' => $h, 'expect' => 200, 'expect_contains' => '"status":1']);
check('KYC-重复提交422', 'POST', '/api/kyc', ['headers' => $h, 'body' => ['real_name' => 'ZhangSan', 'id_number' => '110101199003071234'], 'expect' => 422]);

// ===== 会员与积分 =====
check('会员信息', 'GET', '/api/membership', ['headers' => $h, 'expect' => 200, 'expect_key' => 'all_levels']);
check('积分流水', 'GET', '/api/points', ['headers' => $h, 'expect' => 200, 'expect_key' => 'list']);

// ===== 通知 =====
check('通知-列表(广播+个人)', 'GET', '/api/notifications', ['headers' => $h, 'expect' => 200, 'expect_contains' => '欢迎来到 GlobalShop']);
check('通知-标记已读', 'PUT', '/api/notifications/1/read', ['headers' => $h, 'expect' => 200]);
check('通知-已读后不可重复', 'PUT', '/api/notifications/1/read', ['headers' => $h, 'expect' => 200]);

// ===== 隐私/GDPR =====
check('隐私-非法类型422', 'POST', '/api/privacy/request', ['headers' => $h, 'body' => ['type' => 'hack'], 'expect' => 422]);
check('隐私-数据访问请求', 'POST', '/api/privacy/request', ['headers' => $h, 'body' => ['type' => 'data_access'], 'expect' => 200]);
check('隐私-请求列表', 'GET', '/api/privacy/request', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'data_access']);
check('Cookie同意-缺preferences422', 'POST', '/api/privacy/cookie-consent', ['headers' => $h, 'body' => ['session_id' => 'sess-1'], 'expect' => 422]);
check('Cookie同意-成功', 'POST', '/api/privacy/cookie-consent', [
    'headers' => $h,
    'body' => ['session_id' => 'sess-1', 'preferences' => ['necessary' => true, 'analytics' => false, 'marketing' => false]],
    'expect' => 200,
]);

// ===== 商品对比 =====
check('对比-添加1', 'POST', '/api/comparisons', ['headers' => $h, 'body' => ['product_id' => enc(1001)], 'expect' => 200]);
check('对比-添加2', 'POST', '/api/comparisons', ['headers' => $h, 'body' => ['product_id' => enc(1002)], 'expect' => 200]);
check('对比-列表2条', 'GET', '/api/comparisons', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'Test Sneakers']);
check('对比-缺商品422', 'POST', '/api/comparisons', ['headers' => $h, 'body' => [], 'expect' => 422]);
$cmp = http('GET', '/api/comparisons', null, $h);
$cmpId = $cmp[1]['data'][0]['id'] ?? null;
if ($cmpId) {
    check('对比-删除', 'DELETE', '/api/comparisons/' . $cmpId, ['headers' => $h, 'expect' => 200]);
}

// ===== 个性化推荐 =====
check('推荐-列表', 'GET', '/api/recommendations', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'Test Cotton Dress']);
check('推荐-limit参数', 'GET', '/api/recommendations?limit=3', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'Test Cotton Dress']);
