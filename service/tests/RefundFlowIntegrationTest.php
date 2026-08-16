<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\controller\v1\AdminOpsController;
use app\controller\v1\RefundController;
use app\model\OrderLogs;
use app\model\Orders;
use app\model\Payments;
use app\model\Refunds;
use app\model\Users;
use support\Db;

/**
 * 退款申请闭环集成测试：用户申请（待审0）→ admin 审核（approve 执行 / reject 驳回）
 * 覆盖：归属校验、可退余额（已退+在审）、status 流转、订单/支付状态联动
 */
class RefundFlowIntegrationTest extends IntegrationTestCase
{
    /** @var array<string, int[]> */
    private array $created = [];

    protected function tearDown(): void
    {
        if (self::$dbAvailable) {
            foreach ($this->created as $table => $ids) {
                if ($ids) {
                    Db::table($table)->whereIn('id', $ids)->delete();
                }
            }
        }
        parent::tearDown();
    }

    private function track(string $table, int $id): void
    {
        $this->created[$table][] = $id;
    }

    /** 已付款订单：100 USD */
    private function seedPaidOrder(): array
    {
        $user = Users::create([
            'invite_code' => 'T' . substr(md5(uniqid()), 0, 8),   // uk_invite_code 唯一
            'email' => 'qa_' . uniqid() . '@example.com', 'nickname' => 'QA Refund', 'status' => 1,
        ]);
        $this->track('erik_users', (int) $user->id);
        $order = Orders::create([
            'order_no' => 'ORD' . date('Ymd') . uniqid(),
            'user_id' => $user->id, 'status' => 1,
            'pay_amount' => 100.00, 'currency_code' => 'USD', 'pay_at' => date('Y-m-d H:i:s'),
        ]);
        $this->track('erik_orders', (int) $order->id);
        $payment = Payments::create([
            'order_id' => $order->id, 'user_id' => $user->id,
            'gateway' => 'stripe', 'method' => 'card',
            'amount' => 100.00, 'currency_code' => 'USD',
            'status' => 1, 'transaction_no' => 'pi_qa_' . uniqid(),
        ]);
        $this->track('erik_payments', (int) $payment->id);
        return [(int) $user->id, (int) $order->id, (int) $payment->id];
    }

    private function applyRefund(int $userId, int $orderId, float $amount, string $reason = '质量问题'): array
    {
        $req = $this->makeRequest('POST', '/api/refunds', [
            'order_id' => (string) $orderId, 'amount' => $amount, 'reason' => $reason,
        ]);
        $req->userId = $userId;
        $res = (new RefundController())->apply($req);
        return [json_decode($res->rawBody(), true)];
    }

    private function listRefunds(int $userId): array
    {
        $req = $this->makeRequest('GET', '/api/refunds');
        $req->userId = $userId;
        $res = (new RefundController())->index($req);
        return json_decode($res->rawBody(), true)['data'];
    }

    private function showRefund(int $userId, int $refundId): array
    {
        $req = $this->makeRequest('GET', '/api/refunds/' . $refundId);
        $req->userId = $userId;
        $res = (new RefundController())->show($req, (string) $refundId);
        return json_decode($res->rawBody(), true);
    }

    private function adminApprove(int $refundId): array
    {
        $req = $this->makeRequest('POST', '/api/admin/refunds/' . $refundId . '/approve');
        $res = (new AdminOpsController())->approve($req, (string) $refundId);
        return [json_decode($res->rawBody(), true)];
    }

    private function adminReject(int $refundId, string $reason): array
    {
        $req = $this->makeRequest('POST', '/api/admin/refunds/' . $refundId . '/reject', ['reason' => $reason]);
        $res = (new AdminOpsController())->reject($req, (string) $refundId);
        return [json_decode($res->rawBody(), true)];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_apply_admin_approve_full_flow(): void
    {
        $this->requireDb();
        [$userId, $orderId, $paymentId] = $this->seedPaidOrder();

        // 用户申请部分退款 30
        [$data] = $this->applyRefund($userId, $orderId, 30.00);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $refundId = (int) $data['data']['refund_id'];
        $this->track('erik_refunds', $refundId);
        $refundNo = $data['data']['refund_no'];
        $this->assertStringStartsWith('R', $refundNo);
        $this->assertSame(0, (int) Refunds::find($refundId)->status);   // 待审

        // 列表与详情可见
        $list = $this->listRefunds($userId);
        $this->assertSame(1, (int) $list['total']);
        $this->assertSame($refundNo, $list['list'][0]['refund_no']);
        $detail = $this->showRefund($userId, $refundId);
        $this->assertSame(0, $detail['code']);
        $this->assertSame($refundId, (int) $detail['data']['id']);

        // admin approve：部分退款 → 退款单已退、支付已退金额 +30、订单置退款中(6)
        [$data] = $this->adminApprove($refundId);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->assertSame(3, (int) Refunds::find($refundId)->status);
        $this->assertNotNull(Refunds::find($refundId)->refunded_at);
        $this->assertEqualsWithDelta(30.00, (float) Payments::find($paymentId)->refunded_amount, 0.001);
        $this->assertSame(1, (int) Payments::find($paymentId)->status);   // 部分退，支付仍有效
        $this->assertSame(6, (int) Orders::find($orderId)->status);       // 退款中
        $this->assertSame(1, OrderLogs::where('order_id', $orderId)->count());

        // 补足剩余 70 全额退完 → 订单已退款(7)、支付失效(2)
        [$data] = $this->applyRefund($userId, $orderId, 70.00);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $refundId2 = (int) $data['data']['refund_id'];
        $this->track('erik_refunds', $refundId2);
        [$data] = $this->adminApprove($refundId2);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->assertEqualsWithDelta(100.00, (float) Payments::find($paymentId)->refunded_amount, 0.001);
        $this->assertSame(2, (int) Payments::find($paymentId)->status);
        $this->assertSame(7, (int) Orders::find($orderId)->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function apply_over_refundable_rejected(): void
    {
        $this->requireDb();
        [$userId, $orderId] = $this->seedPaidOrder();

        [$data] = $this->applyRefund($userId, $orderId, 60.00);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->track('erik_refunds', (int) $data['data']['refund_id']);

        // 在审 60 占额度，再申请 60 超可退余额 40 → 拒绝
        [$data] = $this->applyRefund($userId, $orderId, 60.00);
        $this->assertSame(422, $data['code']);
        $this->assertStringContainsString('超过可退余额', $data['msg']);
        $this->assertSame(1, Refunds::where('order_id', $orderId)->count());

        // 超额部分 50 也拒绝，40 可通过
        [$data] = $this->applyRefund($userId, $orderId, 50.00);
        $this->assertSame(422, $data['code']);
        [$data] = $this->applyRefund($userId, $orderId, 40.00);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->track('erik_refunds', (int) $data['data']['refund_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_reject_sets_rejected_and_frees_quota(): void
    {
        $this->requireDb();
        [$userId, $orderId, $paymentId] = $this->seedPaidOrder();

        [$data] = $this->applyRefund($userId, $orderId, 30.00);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $refundId = (int) $data['data']['refund_id'];
        $this->track('erik_refunds', $refundId);

        [$data] = $this->adminReject($refundId, '凭证不足');
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $refund = Refunds::find($refundId);
        $this->assertSame(2, (int) $refund->status);
        $this->assertSame('凭证不足', $refund->reject_reason);
        $this->assertNull($refund->refunded_at);
        // 驳回不动支付/订单状态
        $this->assertEqualsWithDelta(0.0, (float) Payments::find($paymentId)->refunded_amount, 0.001);
        $this->assertSame(1, (int) Orders::find($orderId)->status);

        // 驳回不占额度，可重新申请
        [$data] = $this->applyRefund($userId, $orderId, 40.00);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $this->track('erik_refunds', (int) $data['data']['refund_id']);

        // 已驳回的退款单不可再 approve
        [$data] = $this->adminApprove($refundId);
        $this->assertSame(422, $data['code']);
    }
}
