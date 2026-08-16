<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\controller\v1\KycController;
use app\model\UserKyc;
use app\model\Users;
use support\Db;

/**
 * KYC 实名认证控制器集成测试（submit/status）
 * 覆盖：提交入库（Encryptable 密文存储，模型可解密）、状态查询、空值校验、
 * 驳回后重提重置待审、已通过不可重提、状态查询按请求用户隔离
 */
class KycIntegrationTest extends IntegrationTestCase
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

    private function seedUser(): int
    {
        $user = Users::create([
            'invite_code' => 'T' . substr(md5(uniqid()), 0, 8),   // uk_invite_code 唯一
            'email' => 'qa_kyc_' . uniqid() . '@example.com', 'nickname' => 'QA KYC', 'status' => 1,
        ]);
        $this->track('erik_users', (int) $user->id);
        return (int) $user->id;
    }

    private function submit(int $userId, array $body): array
    {
        $req = $this->makeRequest('POST', '/api/kyc', $body);
        $req->userId = $userId;
        $res = (new KycController())->submit($req);
        return json_decode($res->rawBody(), true);
    }

    private function fetchStatus(int $userId): array
    {
        $req = $this->makeRequest('GET', '/api/kyc/status');
        $req->userId = $userId;
        $res = (new KycController())->status($req);
        return json_decode($res->rawBody(), true)['data'];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function submit_stores_encrypted_and_status_pending(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();

        $data = $this->submit($userId, ['real_name' => '张三', 'id_number' => '110101199001011234']);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $kycId = (int) UserKyc::where('user_id', $userId)->value('id');
        $this->track('erik_user_kyc', $kycId);

        // 模型读取自动解密还原明文
        $kyc = UserKyc::find($kycId);
        $this->assertSame('张三', $kyc->real_name);
        $this->assertSame('110101199001011234', $kyc->id_number);
        $this->assertSame(0, (int) $kyc->status);                 // 待审
        $this->assertSame('id_card', $kyc->id_type);

        // 原始存储为密文（非明文）
        $raw = Db::table('erik_user_kyc')->where('id', $kycId)->first();
        $this->assertNotSame('张三', (string) $raw->real_name);
        $this->assertNotSame('110101199001011234', (string) $raw->id_number);

        // 状态查询
        $s = $this->fetchStatus($userId);
        $this->assertTrue($s['submitted']);
        $this->assertSame(0, (int) $s['status']);
        $this->assertSame('id_card', $s['id_type']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function status_reports_not_submitted_by_default(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();

        $s = $this->fetchStatus($userId);
        $this->assertFalse($s['submitted']);
        $this->assertSame(0, (int) $s['status']);
        $this->assertNull($s['verified_at']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function empty_fields_rejected(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();

        $data = $this->submit($userId, ['real_name' => '', 'id_number' => '110101199001011234']);
        $this->assertSame(422, $data['code']);
        $data = $this->submit($userId, ['real_name' => '张三', 'id_number' => '   ']);
        $this->assertSame(422, $data['code']);
        $this->assertSame(0, UserKyc::where('user_id', $userId)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resubmit_after_reject_resets_to_pending(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();

        $this->submit($userId, ['real_name' => '张三', 'id_number' => '110101199001011234']);
        $kyc = UserKyc::where('user_id', $userId)->first();
        $this->track('erik_user_kyc', (int) $kyc->id);
        // 模拟 admin 驳回
        $kyc->status = 2;
        $kyc->reject_reason = '证件模糊';
        $kyc->save();

        $data = $this->submit($userId, [
            'real_name' => '李四', 'id_number' => '110101199001011235', 'id_type' => 'passport',
        ]);
        $this->assertSame(0, $data['code'], $data['msg'] ?? '');
        $kyc->refresh();
        $this->assertSame(0, (int) $kyc->status);                 // 重置待审
        $this->assertSame('', (string) $kyc->reject_reason);
        $this->assertNull($kyc->verified_at);
        $this->assertSame('李四', $kyc->real_name);
        $this->assertSame('passport', $kyc->id_type);
        $this->assertSame(1, UserKyc::where('user_id', $userId)->count());   // 复用同一行
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approved_kyc_cannot_resubmit(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();

        $this->submit($userId, ['real_name' => '张三', 'id_number' => '110101199001011234']);
        $kyc = UserKyc::where('user_id', $userId)->first();
        $this->track('erik_user_kyc', (int) $kyc->id);
        $kyc->status = 1;
        $kyc->verified_at = date('Y-m-d H:i:s');
        $kyc->save();

        $data = $this->submit($userId, ['real_name' => '李四', 'id_number' => '110101199001011235']);
        $this->assertSame(422, $data['code']);

        $kyc->refresh();
        $this->assertSame(1, (int) $kyc->status);
        $this->assertSame('张三', $kyc->real_name);               // 数据未被覆盖
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function status_scoped_to_requesting_user(): void
    {
        $this->requireDb();
        $userId = $this->seedUser();
        $otherUserId = $this->seedUser();

        $this->submit($userId, ['real_name' => '张三', 'id_number' => '110101199001011234']);
        $this->track('erik_user_kyc', (int) UserKyc::where('user_id', $userId)->value('id'));

        $s = $this->fetchStatus($otherUserId);
        $this->assertFalse($s['submitted']);                      // 他人 KYC 不可见
    }
}
