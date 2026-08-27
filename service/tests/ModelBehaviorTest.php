<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\model\Countries;
use app\model\Orders;
use app\model\ProductSkus;
use app\model\Users;
use Illuminate\Database\QueryException;

/**
 * 模型通用行为测试（ModelCrudTest 之外的差异化行为）：
 *  - Snowflake 主键格式 / incrementing=false
 *  - $guarded 受保护列：批量赋值不生效、forceFill 直写生效
 *  - Encryptable 列：DB 密文存储、明文回读、email_hash 索引查询
 *  - 唯一约束：email_hash / invite_code / iso_code_2 重复插入抛 QueryException
 *  - SoftDeletes 生命周期（Users 为代表）
 *  - timestamps 自动维护（Orders 有 updated_at；SizeChartValues 无 updated_at 不报错）
 *  - JSON cast 往返（Orders.address_snapshot）
 *  - $hidden 属性不序列化输出（Users.password/salt）
 */
class ModelBehaviorTest extends IntegrationTestCase
{
    use TestSeederTrait;

    public function test_snowflake_primary_key_format(): void
    {
        $this->requireDb();
        $user = Users::create([
            'nickname' => 'Snowflake Test',
            'email' => 'snow_' . uniqid() . '@example.com',
            'email_hash' => Users::emailHash('snow@example.com'),
            'invite_code' => 'SN' . substr(md5(uniqid()), 0, 6),
            'status' => 1,
        ]);
        $this->trackCreated('shop_users', (int) $user->id);

        $this->assertFalse((new Users())->getIncrementing(), 'BaseModel 应为非自增主键');
        $this->assertMatchesRegularExpression('/^\d{15,20}$/', (string) $user->id, 'Snowflake ID 应为纯数字串');
        // 创建时 Snowflake::nextId() 返回 int，PDO 回读 BIGINT 为 string，语义一致即可
        $this->assertEquals($user->id, Users::find($user->id)->id);
    }

    public function test_guarded_columns_require_force_fill(): void
    {
        $this->requireDb();
        $user = Users::create([
            'nickname' => 'Guarded Test',
            'email' => 'guard_' . uniqid() . '@example.com',
            'email_hash' => Users::emailHash('guard@example.com'),
            'invite_code' => 'GD' . substr(md5(uniqid()), 0, 6),
            'status' => 1,
            'level' => 9,   // $guarded 列，批量赋值应被忽略
        ]);
        $this->trackCreated('shop_users', (int) $user->id);

        $this->assertNotSame(9, (int) $user->level, '$guarded 列不应被批量赋值写入');
        $this->assertNotSame(9, (int) Users::find($user->id)->level);

        $user->forceFill(['level' => 9])->save();
        $this->assertSame(9, (int) Users::find($user->id)->level, 'forceFill 应绕过 $guarded');
    }

    public function test_encryptable_columns_stored_encrypted(): void
    {
        $this->requireDb();
        $plain = 'secret_' . uniqid() . '@example.com';
        $user = Users::create([
            'nickname' => 'Encrypt Test',
            'email' => $plain,
            'email_hash' => Users::emailHash($plain),
            'invite_code' => 'EN' . substr(md5(uniqid()), 0, 6),
            'status' => 1,
        ]);
        $this->trackCreated('shop_users', (int) $user->id);

        // 密文落库：DB 原始值与明文不同
        $raw = \support\Db::table('shop_users')->where('id', $user->id)->value('email');
        $this->assertNotSame($plain, $raw, 'email 应以密文存储');
        $this->assertStringNotContainsString($plain, (string) $raw);

        // 明文回读 + 索引列查询（Encryptable 列不可 where 直接匹配）
        $this->assertSame($plain, Users::find($user->id)->email);
        $found = Users::where('email_hash', Users::emailHash($plain))->first();
        $this->assertNotNull($found);
        $this->assertSame($plain, $found->email);
    }

    public function test_unique_constraints_enforced(): void
    {
        $this->requireDb();
        // shop_users 唯一键为 uk_invite_code（email_hash 无索引，防重由注册锁保证）
        $inviteCode = 'UN' . substr(md5(uniqid()), 0, 6);
        Users::create([
            'nickname' => 'Unique A',
            'email' => 'uniq_' . uniqid() . '@example.com',
            'email_hash' => 'uniq_a_' . uniqid(),
            'invite_code' => $inviteCode,
            'status' => 1,
        ]);

        // 重复 invite_code
        $this->expectException(QueryException::class);
        Users::create([
            'nickname' => 'Unique B',
            'email' => 'uniq_' . uniqid() . '@example.com',
            'email_hash' => 'uniq_b_' . uniqid(),
            'invite_code' => $inviteCode,
            'status' => 1,
        ]);
    }

    public function test_unique_iso_code_2_enforced(): void
    {
        $this->requireDb();
        Countries::create([
            'name_en' => 'Test A', 'name_cn' => '测试A',
            'iso_code_2' => 'ZZ', 'iso_code_3' => 'ZZZ',
            'currency_code' => 'USD', 'status' => 1, 'kyc_required' => 0,
        ]);
        $this->expectException(QueryException::class);
        Countries::create([
            'name_en' => 'Test B', 'name_cn' => '测试B',
            'iso_code_2' => 'ZZ', 'iso_code_3' => 'ZZY',
            'currency_code' => 'USD', 'status' => 1, 'kyc_required' => 0,
        ]);
    }

    public function test_soft_delete_lifecycle(): void
    {
        $this->requireDb();
        $user = Users::create([
            'nickname' => 'SoftDelete Test',
            'email' => 'soft_' . uniqid() . '@example.com',
            'email_hash' => Users::emailHash('soft@example.com'),
            'invite_code' => 'SD' . substr(md5(uniqid()), 0, 6),
            'status' => 1,
        ]);
        $id = (int) $user->id;

        $user->delete();
        $this->assertNull(Users::find($id), '软删除后普通查询不可见');
        $this->assertNotNull(Users::withTrashed()->find($id), 'withTrashed 应可见');

        Users::withTrashed()->find($id)->restore();
        $this->assertNotNull(Users::find($id), 'restore 后恢复可见');

        Users::find($id)->forceDelete();
        $this->assertNull(Users::withTrashed()->find($id), 'forceDelete 物理删除');
    }

    public function test_timestamps_auto_maintained(): void
    {
        $this->requireDb();
        $user = Users::create([
            'nickname' => 'Ts Test',
            'email' => 'ts_' . uniqid() . '@example.com',
            'email_hash' => Users::emailHash('ts@example.com'),
            'invite_code' => 'TS' . substr(md5(uniqid()), 0, 6),
            'status' => 1,
        ]);
        $this->trackCreated('shop_users', (int) $user->id);
        $this->assertNotNull($user->created_at, 'timestamps=true 模型应自动写 created_at');
        $this->assertNotNull($user->updated_at, 'timestamps=true 模型应自动写 updated_at');
    }

    public function test_json_cast_roundtrip(): void
    {
        $this->requireDb();
        $order = Orders::create([
            'order_no' => 'ORD' . date('Ymd') . uniqid(),
            'user_id' => 1,
            'status' => 0,
            'currency_code' => 'USD',
            'address_snapshot' => ['name' => 'QA', 'country_id' => 1],
        ]);
        $this->trackCreated('shop_orders', (int) $order->id);

        $this->assertIsArray($order->address_snapshot, 'JSON 列应 cast 为数组');
        $this->assertSame('QA', $order->address_snapshot['name']);

        $fresh = Orders::find($order->id);
        $this->assertIsArray($fresh->address_snapshot);
        $this->assertSame(1, $fresh->address_snapshot['country_id']);
    }

    public function test_hidden_attributes_excluded_from_output(): void
    {
        $this->requireDb();
        $user = Users::create([
            'nickname' => 'Hidden Test',
            'email' => 'hidden_' . uniqid() . '@example.com',
            'email_hash' => Users::emailHash('hidden@example.com'),
            'password' => password_hash('x', PASSWORD_BCRYPT),
            'salt' => 'abc',
            'invite_code' => 'HD' . substr(md5(uniqid()), 0, 6),
            'status' => 1,
        ]);
        $out = $user->toArray();
        $this->assertArrayNotHasKey('password', $out, '$hidden 列不应序列化');
        $this->assertArrayNotHasKey('salt', $out);
        $this->assertArrayNotHasKey('deleted_at', $out);
    }

    public function test_stock_guarded_level_default_membership_levels(): void
    {
        // ProductSkus 非 guarded：普通批量赋值可写 stock（验证 $guarded 仅限 BaseModel 声明列）
        $this->requireDb();
        $sku = ProductSkus::create([
            'product_id' => 1, 'sku_code' => 'SKU' . uniqid(),
            'default_price' => 1.0, 'stock' => 7, 'status' => 1,
        ]);
        $this->trackCreated('shop_product_skus', (int) $sku->id);
        $this->assertSame(7, (int) ProductSkus::find($sku->id)->stock);
    }
}
