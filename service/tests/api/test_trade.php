<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 交易链路测试：购物车/订单/支付/Webhook验签/退款/退货/单据/导出/优惠券/Admin
 */

function webhookSign(string $payload, string $secret = 'whsec_api_test'): string
{
    $ts = time();
    return 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $payload, $secret);
}

function addCart(array $h, $skuId, int $qty): void
{
    $r = http('POST', '/api/cart', ['sku_id' => enc($skuId), 'quantity' => $qty], $h);
    if (($r[1]['code'] ?? -1) !== 0) {
        throw new RuntimeException('加购失败: ' . json_encode($r[1]));
    }
}

/** 创建订单（间隔≥3.5s 以避开 10s/3 限流） */
function createOrder(array $h, string $addressId, array $extra = []): array
{
    static $last = 0;
    $wait = $last + 3.5 - microtime(true);
    if ($wait > 0) {
        usleep((int) ($wait * 1e6));
    }
    $last = microtime(true);
    $body = array_merge(['address_id' => $addressId, 'currency_code' => 'USD'], $extra);
    $r = http('POST', '/api/orders', $body, array_merge($h, ['X-Poster-Token: ' . posterToken()]));
    if (($r[1]['code'] ?? -1) !== 0) {
        throw new RuntimeException('下单失败: ' . json_encode($r[1]));
    }
    return $r[1]['data'];
}

$u = registerUser('buyer@test.local');
$h = authHeaders($u['token']);
$adminKey = envFromFile('ADMIN_API_KEY');

// ===== 地址（US 默认） =====
$addr = http('POST', '/api/user/addresses', [
    'name' => 'Buyer One', 'phone' => '+12025550123', 'country_id' => 1,
    'province' => 'CA', 'city' => 'Los Angeles', 'detail' => '100 Test Ave',
    'postal_code' => '90001', 'is_default' => 1,
], $h);
check('交易-地址创建', 'POST', '/api/user/addresses', [
    'headers' => $h,
    'body' => [
        'name' => 'Buyer One', 'phone' => '+12025550123', 'country_id' => 1,
        'province' => 'CA', 'city' => 'Los Angeles', 'detail' => '100 Test Ave',
        'postal_code' => '90001', 'is_default' => 1,
    ],
    'expect' => 200, 'expect_key' => 'id',
]);
$addressId = $addr[1]['data']['id'];

// ===== 购物车 =====
check('购物车-添加', 'POST', '/api/cart', ['headers' => $h, 'body' => ['sku_id' => enc(2001), 'quantity' => 2], 'expect' => 200]);
check('购物车-再添加', 'POST', '/api/cart', ['headers' => $h, 'body' => ['sku_id' => enc(2002), 'quantity' => 1], 'expect' => 200]);
check('购物车-数量越界', 'POST', '/api/cart', ['headers' => $h, 'body' => ['sku_id' => enc(2001), 'quantity' => 0], 'expect' => 422]);
check('购物车-SKU不存在', 'POST', '/api/cart', ['headers' => $h, 'body' => ['sku_id' => enc(99999), 'quantity' => 1], 'expect_any' => [404, 422]]);
check('购物车-缺参', 'POST', '/api/cart', ['headers' => $h, 'body' => ['quantity' => 1], 'expect' => 422]);
check('订单-地址不存在', 'POST', '/api/orders', [
    'headers' => array_merge($h, ['X-Poster-Token: ' . posterToken()]),
    'body' => ['address_id' => enc(999999)],
    'expect' => 422,
]);
check('购物车-列表', 'GET', '/api/cart', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'Test Cotton Dress']);
$cart = http('GET', '/api/cart', null, $h);
$cartItemId = $cart[1]['data'][0]['id'] ?? null;
if ($cartItemId) {
    check('购物车-更新数量', 'PUT', '/api/cart/' . $cartItemId, ['headers' => $h, 'body' => ['quantity' => 1], 'expect' => 200]);
    check('购物车-删除', 'DELETE', '/api/cart/' . $cartItemId, ['headers' => $h, 'expect' => 200]);
}
// 清掉列表里剩下的(1×2002)，只留 2×2001 用于订单A
foreach ($cart[1]['data'] ?? [] as $item) {
    if (($item['id'] ?? '') !== $cartItemId) {
        http('DELETE', '/api/cart/' . $item['id'], null, $h);
    }
}

// ===== 订单A（2×29.99 + 运费23 + 税0 = 82.98，运费按默认500g 取 zone1 最低 UPS 20+6×0.5） =====
addCart($h, 2001, 2);
$orderA = createOrder($h, $addressId); // 创建成功由 createOrder() 断言，金额由下方详情校验
check('订单-未登录401', 'POST', '/api/orders', ['headers' => ['X-Poster-Token: ' . posterToken()], 'body' => ['address_id' => $addressId], 'expect' => 401]);
check('订单-无人机验证40001', 'POST', '/api/orders', ['headers' => $h, 'body' => ['address_id' => $addressId], 'expect' => 200, 'expect_code' => 40001]);
check('订单-详情', 'GET', '/api/orders/' . $orderA['order_id'], ['headers' => $h, 'expect' => 200, 'expect_contains' => '82.98']);
check('订单-列表', 'GET', '/api/orders', ['headers' => $h, 'expect' => 200, 'expect_key' => 'list']);
check('订单-空购物车-无验证码40001', 'POST', '/api/orders', [
    'headers' => $h, 'body' => ['address_id' => $addressId],
    'expect' => 200, 'expect_code' => 40001,
]);

// ===== 支付A（预置待支付记录 → 幂等复用，不调用真实网关） =====
dbInsert('erik_payments', ['order_id', 'user_id', 'gateway', 'method', 'transaction_no', 'amount', 'currency_code', 'status'], [
    [db()->query("SELECT id FROM erik_orders WHERE order_no='{$orderA['order_no']}'")->fetchColumn(), $u['id'], 'stripe', 'card', 'pi_test_ok_001', 82.98, 'USD', 0],
]);
$payAId = db()->query("SELECT id FROM erik_payments WHERE transaction_no='pi_test_ok_001'")->fetchColumn();
check('支付-幂等复用待支付记录', 'POST', '/api/payment/create', [
    'headers' => array_merge($h, ['X-Poster-Token: ' . posterToken()]),
    'body' => ['order_id' => $orderA['order_id'], 'gateway' => 'stripe', 'method' => 'card'],
    'expect' => 200, 'expect_contains' => 'pi_test_ok_001',
]);
check('支付-重复创建复用同一支付单', 'POST', '/api/payment/create', [
    'headers' => array_merge($h, ['X-Poster-Token: ' . posterToken()]),
    'body' => ['order_id' => $orderA['order_id'], 'gateway' => 'stripe', 'method' => 'card'],
    'expect' => 200, 'expect_contains' => 'pi_test_ok_001',
]);
check('支付-状态待支付', 'GET', '/api/payment/status/' . enc($payAId), ['headers' => $h, 'expect' => 200, 'expect_contains' => '"status":0']);

// ===== Webhook 验签 =====
$sucPayload = json_encode(['id' => 'evt_ok_001', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_test_ok_001']]]);
check('Webhook-成功事件', 'POST', '/webhook/payment/stripe', ['body' => $sucPayload, 'headers' => ['Stripe-Signature: ' . webhookSign($sucPayload)], 'expect' => 200]);
$refPayload = json_encode(['id' => 'evt_ref_001', 'type' => 'payment_intent.refunded', 'data' => ['object' => ['id' => 'pi_test_refund_001', 'amount_refunded' => 5499]]]);
check('Webhook-签名错误403', 'POST', '/webhook/payment/stripe', ['body' => $sucPayload, 'headers' => ['Stripe-Signature: t=1,v1=deadbeef'], 'expect' => 403]);
check('Webhook-缺签名403', 'POST', '/webhook/payment/stripe', ['body' => $sucPayload, 'expect' => 403]);
check('Webhook-未知网关404', 'POST', '/webhook/payment/alipay', ['body' => $sucPayload, 'headers' => ['Stripe-Signature: ' . webhookSign($sucPayload)], 'expect' => 404]);
$otherPayload = json_encode(['id' => 'evt_other_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1']]]);
check('Webhook-未识别事件幂等200', 'POST', '/webhook/payment/stripe', ['body' => $otherPayload, 'headers' => ['Stripe-Signature: ' . webhookSign($otherPayload)], 'expect' => 200]);

check('支付-成功回调后已支付', 'GET', '/api/payment/status/' . enc($payAId), ['headers' => $h, 'expect' => 200, 'expect_contains' => '"status":1']);
check('订单A-已付款', 'GET', '/api/orders/' . $orderA['order_id'], ['headers' => $h, 'expect' => 200, 'expect_contains' => 'pay_at']);
check('支付-重复回调幂等', 'POST', '/webhook/payment/stripe', ['body' => $sucPayload, 'headers' => ['Stripe-Signature: ' . webhookSign($sucPayload)], 'expect' => 200]);
check('订单A-状态筛选', 'GET', '/api/orders?status=1', ['headers' => $h, 'expect' => 200, 'expect_contains' => '已付款']);

// ===== 退款申请（订单A已付款） =====
check('退款-申请', 'POST', '/api/refunds', ['headers' => $h, 'body' => ['order_id' => $orderA['order_id'], 'amount' => 82.98, 'reason' => '不想要了'], 'expect' => 200]);
check('退款-超可退余额422', 'POST', '/api/refunds', ['headers' => $h, 'body' => ['order_id' => $orderA['order_id'], 'amount' => 99999, 'reason' => 'x'], 'expect' => 422]);
check('退款-缺参422', 'POST', '/api/refunds', ['headers' => $h, 'body' => ['order_id' => $orderA['order_id']], 'expect' => 422]);
$refunds = http('GET', '/api/refunds', null, $h);
check('退款-列表', 'GET', '/api/refunds', ['headers' => $h, 'expect' => 200, 'expect_contains' => '不想要了']);
$refundId = $refunds[1]['data']['list'][0]['id'] ?? null;
check('退款-详情', 'GET', '/api/refunds/' . ($refundId ?? 'x'), ['headers' => $h, 'expect' => 200]);

// ===== Admin 退款流转 =====
check('Admin-密钥错误403', 'POST', '/api/admin/refunds/' . ($refundId ?? 'x') . '/approve', ['headers' => ['X-Admin-Key: wrong-key'], 'expect' => 403]);
check('Admin-缺密钥403', 'POST', '/api/admin/refunds/' . ($refundId ?? 'x') . '/approve', ['expect' => 403]);
if ($refundId) {
    check('Admin-退款通过', 'POST', '/api/admin/refunds/' . $refundId . '/approve', ['headers' => ['X-Admin-Key: ' . $adminKey], 'expect' => 200]);
    check('退款-通过后订单已退款', 'GET', '/api/orders/' . $orderA['order_id'], ['headers' => $h, 'expect' => 200, 'expect_contains' => '已退款']);
    check('退款-已退款订单再申请422', 'POST', '/api/refunds', ['headers' => $h, 'body' => ['order_id' => $orderA['order_id'], 'amount' => 1, 'reason' => 'x'], 'expect' => 422]);
    check('Admin-重复审核422', 'POST', '/api/admin/refunds/' . $refundId . '/approve', ['headers' => ['X-Admin-Key: ' . $adminKey], 'expect' => 422]);
}

// ===== 订单B：支付成功 → 退款Webhook =====
addCart($h, 2001, 1);
$orderB = createOrder($h, $addressId);
$orderBId = db()->query("SELECT id FROM erik_orders WHERE order_no='{$orderB['order_no']}'")->fetchColumn();
dbInsert('erik_payments', ['order_id', 'user_id', 'gateway', 'method', 'transaction_no', 'amount', 'currency_code', 'status'], [
    [$orderBId, $u['id'], 'stripe', 'card', 'pi_test_refund_001', 54.99, 'USD', 0],
]);
$sucBPayload = json_encode(['id' => 'evt_ok_b_001', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_test_refund_001']]]);
check('Webhook-B成功', 'POST', '/webhook/payment/stripe', ['body' => $sucBPayload, 'headers' => ['Stripe-Signature: ' . webhookSign($sucBPayload)], 'expect' => 200]);
check('Webhook-B退款事件', 'POST', '/webhook/payment/stripe', ['body' => $refPayload, 'headers' => ['Stripe-Signature: ' . webhookSign($refPayload)], 'expect' => 200]);
$payBId = db()->query("SELECT id FROM erik_payments WHERE transaction_no='pi_test_refund_001'")->fetchColumn();
check('支付B-已退款状态2', 'GET', '/api/payment/status/' . enc($payBId), ['headers' => $h, 'expect' => 200, 'expect_contains' => '"status":2']);
check('订单B-已退款7', 'GET', '/api/orders/' . $orderB['order_id'], ['headers' => $h, 'expect' => 200, 'expect_contains' => '已退款']);

// ===== 订单C：支付失败事件 =====
addCart($h, 2003, 1);
$orderC = createOrder($h, $addressId);
$orderCId = db()->query("SELECT id FROM erik_orders WHERE order_no='{$orderC['order_no']}'")->fetchColumn();
dbInsert('erik_payments', ['order_id', 'user_id', 'gateway', 'method', 'transaction_no', 'amount', 'currency_code', 'status'], [
    [$orderCId, $u['id'], 'stripe', 'card', 'pi_test_fail_001', 84.99, 'USD', 0],
]);
$failPayload = json_encode(['id' => 'evt_fail_1', 'type' => 'payment_intent.payment_failed', 'data' => ['object' => ['id' => 'pi_test_fail_001']]]);
check('Webhook-C失败事件', 'POST', '/webhook/payment/stripe', ['body' => $failPayload, 'headers' => ['Stripe-Signature: ' . webhookSign($failPayload)], 'expect' => 200]);
$payCId = db()->query("SELECT id FROM erik_payments WHERE transaction_no='pi_test_fail_001'")->fetchColumn();
check('支付C-失败状态3', 'GET', '/api/payment/status/' . enc($payCId), ['headers' => $h, 'expect' => 200, 'expect_contains' => '"status":3']);

// ===== 订单D：取消订单 =====
addCart($h, 2001, 1);
$orderD = createOrder($h, $addressId);
check('订单-取消', 'POST', '/api/orders/' . $orderD['order_id'] . '/cancel', ['headers' => $h, 'expect' => 200]);
check('订单-重复取消422', 'POST', '/api/orders/' . $orderD['order_id'] . '/cancel', ['headers' => $h, 'expect' => 422]);
check('订单-已取消状态5', 'GET', '/api/orders/' . $orderD['order_id'], ['headers' => $h, 'expect' => 200, 'expect_contains' => '已取消']);
check('支付-已取消订单不可支付422', 'POST', '/api/payment/create', [
    'headers' => array_merge($h, ['X-Poster-Token: ' . posterToken()]),
    'body' => ['order_id' => $orderD['order_id'], 'gateway' => 'stripe'],
    'expect' => 422,
]);

// ===== 订单E：优惠券闭环（4×29.99=119.96 ≥100 → 减20） =====
check('优惠券-可用列表', 'GET', '/api/coupons', ['headers' => $h, 'expect' => 200, 'expect_contains' => '满100减20']);
check('优惠券-领取', 'POST', '/api/coupons/' . enc(1) . '/claim', ['headers' => $h, 'expect' => 200]);
check('优惠券-重复领取422', 'POST', '/api/coupons/' . enc(1) . '/claim', ['headers' => $h, 'expect' => 422]);
check('优惠券-不存在404', 'POST', '/api/coupons/' . enc(99999) . '/claim', ['headers' => $h, 'expect' => 404]);
check('优惠券-过期券领取404', 'POST', '/api/coupons/' . enc(2) . '/claim', ['headers' => $h, 'expect' => 404]);
addCart($h, 2001, 4);
$orderE = createOrder($h, $addressId, ['coupon_id' => enc(1)]);
check('订单-优惠券生效', 'GET', '/api/orders/' . $orderE['order_id'], ['headers' => $h, 'expect' => 200, 'expect_contains' => '20.00']);
check('订单-优惠后实付122.96', 'GET', '/api/orders/' . $orderE['order_id'], ['headers' => $h, 'expect' => 200, 'expect_contains' => '122.96']);

// ===== 订单G：不支持的支付网关（确定性422，不触网） =====
addCart($h, 2001, 1);
$orderG = createOrder($h, $addressId);
check('支付-不支持的网关422', 'POST', '/api/payment/create', [
    'headers' => array_merge($h, ['X-Poster-Token: ' . posterToken()]),
    'body' => ['order_id' => $orderG['order_id'], 'gateway' => 'alipay', 'method' => 'card'],
    'expect' => 422,
]);

// ===== 订单F：退货（PDO 置已发货） =====
addCart($h, 2001, 1);
$orderF = createOrder($h, $addressId);
$orderFId = db()->query("SELECT id FROM erik_orders WHERE order_no='{$orderF['order_no']}'")->fetchColumn();
db()->exec("UPDATE erik_orders SET status = 2, shipping_at = NOW() WHERE id = {$orderFId}");
check('退货-申请', 'POST', '/api/returns', ['headers' => $h, 'body' => ['order_id' => $orderF['order_id'], 'reason_id' => 1], 'expect' => 200]);
check('退货-未发货订单422', 'POST', '/api/returns', ['headers' => $h, 'body' => ['order_id' => $orderG['order_id'], 'reason_id' => 1], 'expect' => 422]);
$returns = http('GET', '/api/returns', null, $h);
check('退货-列表', 'GET', '/api/returns', ['headers' => $h, 'expect' => 200, 'expect_key' => 'list']);
$returnId = $returns[1]['data']['list'][0]['id'] ?? null;
if ($returnId) {
    check('退货-面单未生成404', 'GET', '/api/returns/' . $returnId . '/label', ['headers' => $h, 'expect' => 404]);
    $retId = db()->query("SELECT id FROM erik_return_orders LIMIT 1")->fetchColumn();
    dbInsert('erik_return_labels', ['return_id', 'logistics_id', 'tracking_no', 'label_url'], [
        [$retId, 1, 'DHL-TRACK-001', 'https://img.example.com/label.pdf'],
    ]);
    check('退货-面单生成后返回', 'GET', '/api/returns/' . $returnId . '/label', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'label.pdf']);
}

// ===== 订单单据（异步PDF队列） =====
check('单据-商业发票', 'GET', '/api/orders/' . $orderA['order_id'] . '/documents/invoice', ['headers' => $h, 'expect' => 200]);
check('单据-装箱单', 'GET', '/api/orders/' . $orderA['order_id'] . '/documents/packing-list', ['headers' => $h, 'expect' => 200]);
check('单据-他人订单404', 'GET', '/api/orders/' . $orderA['order_id'] . '/documents/invoice', ['headers' => authHeaders(registerUser('other@test.local')['token']), 'expect' => 404]);

// ===== 导出 =====
$csv = http('GET', '/api/export/orders?format=csv', null, $h);
check('导出-CSV', 'GET', '/api/export/orders?format=csv', ['headers' => $h, 'expect' => 200, 'expect_contains' => $orderA['order_no']]);
$raw = is_array($csv[1]) ? json_encode($csv[1]) : (string) $csv[1];
check('导出-CSV为文件内容', 'GET', '/api/export/orders?format=csv', ['headers' => $h, 'expect' => 200, 'expect_contains' => '待付款']);
check('导出-XLSX', 'GET', '/api/export/orders?format=xlsx', ['headers' => $h, 'expect' => 200]);
check('导出-日期过滤', 'GET', '/api/export/orders?format=csv&date_from=' . date('Y-m-d', strtotime('-1 day')) . '&date_to=' . date('Y-m-d'), ['headers' => $h, 'expect' => 200, 'expect_contains' => $orderA['order_no']]);

// ===== Admin：风控审核 + 刊登 + 网关退款（不支持的网关，确定性422） =====
$orderHId = null;
addCart($h, 2001, 1);
$reviewOrder = createOrder($h, $addressId);
$orderHId = db()->query("SELECT id FROM erik_orders WHERE order_no='{$reviewOrder['order_no']}'")->fetchColumn();
db()->exec("UPDATE erik_orders SET status = 8 WHERE id = {$orderHId}");
check('Admin-风控审核通过', 'POST', '/api/admin/orders/' . enc($orderHId) . '/review', ['headers' => ['X-Admin-Key: ' . $adminKey], 'body' => ['action' => 'approve'], 'expect' => 200]);
addCart($h, 2001, 1);
$orderI = createOrder($h, $addressId);
$orderIId = db()->query("SELECT id FROM erik_orders WHERE order_no='{$orderI['order_no']}'")->fetchColumn();
db()->exec("UPDATE erik_orders SET status = 8 WHERE id = {$orderIId}");
check('Admin-风控审核驳回', 'POST', '/api/admin/orders/' . enc($orderIId) . '/review', ['headers' => ['X-Admin-Key: ' . $adminKey], 'body' => ['action' => 'reject'], 'expect' => 200]);
check('Admin-风控审核非法action422', 'POST', '/api/admin/orders/' . enc($orderIId) . '/review', ['headers' => ['X-Admin-Key: ' . $adminKey], 'body' => ['action' => 'hack'], 'expect' => 422]);
check('Admin-商品刊登', 'POST', '/api/admin/platform/listings', ['headers' => ['X-Admin-Key: ' . $adminKey], 'body' => ['product_id' => 1001, 'platform_account_id' => 1, 'platform_product_id' => 'PLAT-001'], 'expect' => 200, 'expect_key' => 'listing_id']);
check('Admin-刊登缺参422', 'POST', '/api/admin/platform/listings', ['headers' => ['X-Admin-Key: ' . $adminKey], 'body' => ['product_id' => 0, 'platform_account_id' => 0], 'expect' => 422]);

// 网关退款（alipay 不受支持 → 422，无外部调用）
addCart($h, 2001, 1);
$orderJ = createOrder($h, $addressId);
$orderJId = db()->query("SELECT id FROM erik_orders WHERE order_no='{$orderJ['order_no']}'")->fetchColumn();
dbInsert('erik_payments', ['order_id', 'user_id', 'gateway', 'method', 'transaction_no', 'amount', 'currency_code', 'status'], [
    [$orderJId, $u['id'], 'alipay', 'alipay', 'alipay_txn_001', 54.99, 'USD', 1],
]);
dbInsert('erik_refunds', ['order_id', 'user_id', 'refund_no', 'type', 'amount', 'reason', 'status'], [
    [$orderJId, $u['id'], 'RF-TEST-001', 1, 10.00, 'admin执行', 0],
]);
$adminRefundId = db()->query("SELECT id FROM erik_refunds WHERE refund_no='RF-TEST-001'")->fetchColumn();
check('Admin-执行退款(不支持的网关)422', 'POST', '/api/admin/refunds/' . enc($adminRefundId) . '/execute', ['headers' => ['X-Admin-Key: ' . $adminKey], 'expect' => 422]);

// ===== 订阅（周期购，自动创建首期订单） =====
check('订阅-创建', 'POST', '/api/subscriptions', ['headers' => $h, 'body' => ['sku_id' => enc(2001), 'interval_days' => 30, 'quantity' => 1], 'expect' => 200]);
check('订阅-非法周期422', 'POST', '/api/subscriptions', ['headers' => $h, 'body' => ['sku_id' => enc(2001), 'interval_days' => 15], 'expect' => 422]);
check('订阅-SKU不存在404', 'POST', '/api/subscriptions', ['headers' => $h, 'body' => ['sku_id' => enc(99999), 'interval_days' => 30], 'expect' => 404]);
check('订阅-列表', 'GET', '/api/subscriptions', ['headers' => $h, 'expect' => 200, 'expect_contains' => 'active']);
$subs = http('GET', '/api/subscriptions', null, $h);
$subId = $subs[1]['data']['list'][0]['id'] ?? null;
if ($subId) {
    check('订阅-取消', 'POST', '/api/subscriptions/' . $subId . '/cancel', ['headers' => $h, 'expect' => 200]);
    check('订阅-重复取消422', 'POST', '/api/subscriptions/' . $subId . '/cancel', ['headers' => $h, 'expect' => 422]);
}
