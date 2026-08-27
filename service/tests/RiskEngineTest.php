<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\RiskEngine;
use PHPUnit\Framework\TestCase;

/**
 * RiskEngine 风控打分单元测试（旁路模式，引擎内部 try/catch 吞异常不阻塞）
 * 覆盖：临时邮箱域名 / 大额订单分档 / 地址国家不匹配 / 组合达 review
 * Redis 依赖规则（velocity/ip_reputation）：Redis 不可用时跳过（引擎降级为不扣分）
 */
class RiskEngineTest extends TestCase
{
    /** @var string[] */
    private array $redisKeys = [];

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        \Webman\Config::load(dirname(__DIR__) . '/config', ['route', 'container']);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->redisKeys as $key) {
                \support\Redis::del($key);
            }
        } catch (\Throwable) {
            // Redis 不可用时无需清理
        }
        parent::tearDown();
    }

    private function redisAvailable(): bool
    {
        try {
            return (bool) \support\Redis::ping();
        } catch (\Throwable) {
            return false;
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function temp_email_domain_scores_40_record(): void
    {
        $r = RiskEngine::score('user_register', ['user_id' => 0, 'ip' => '', 'email' => 'buyer@mailinator.com']);
        $this->assertSame(40, $r['score']);
        $this->assertSame('record', $r['result']);
        $this->assertArrayHasKey('email_domain', $r['details']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function large_amount_buckets(): void
    {
        // payment_create 不触发 velocity，ip 留空不触发 ip_reputation，无 Redis 依赖
        $r = RiskEngine::score('payment_create', ['user_id' => 0, 'amount' => 25000]);
        $this->assertSame(40, $r['score']);
        $this->assertSame('record', $r['result']);

        $r = RiskEngine::score('payment_create', ['user_id' => 0, 'amount' => 6000]);
        $this->assertSame(20, $r['score']);
        $this->assertSame('record', $r['result']);

        $r = RiskEngine::score('payment_create', ['user_id' => 0, 'amount' => 1000]);
        $this->assertSame(0, $r['score']);
        $this->assertSame('pass', $r['result']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function address_mismatch_scores_20(): void
    {
        // user_id=0 跳过 velocity，ip 留空跳过 ip_reputation，仅测地址规则
        $r = RiskEngine::score('order_create', [
            'user_id' => 0, 'ip' => '', 'address_country_iso' => 'US', 'ip_country' => 'CN',
        ]);
        $this->assertSame(20, $r['score']);
        $this->assertSame('record', $r['result']);
        $this->assertArrayHasKey('address_mismatch', $r['details']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function combined_rules_reach_review(): void
    {
        // 临时邮箱 40 + 大额 40 = 80 ≥ high_threshold → review
        $r = RiskEngine::score('payment_create', [
            'user_id' => 0, 'email' => 'buyer@yopmail.com', 'amount' => 25000,
        ]);
        $this->assertSame(80, $r['score']);
        $this->assertSame('review', $r['result']);
        $this->assertArrayHasKey('email_domain', $r['details']);
        $this->assertArrayHasKey('amount', $r['details']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function velocity_check_flags_after_threshold(): void
    {
        if (!$this->redisAvailable()) {
            $this->markTestSkipped('Redis 不可用');
        }
        $userId = random_int(1000000, 9999999);
        $this->redisKeys[] = "shop:risk:orders:{$userId}:h:" . date('YmdH');
        $r = [];
        for ($i = 1; $i <= 11; $i++) {
            $r = RiskEngine::score('order_create', ['user_id' => $userId]);
        }
        $this->assertSame(30, $r['score']);                        // 第 11 次超 order_per_hour=10
        $this->assertSame('record', $r['result']);
        $this->assertArrayHasKey('velocity', $r['details']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ip_reputation_scores_20(): void
    {
        if (!$this->redisAvailable()) {
            $this->markTestSkipped('Redis 不可用');
        }
        $ip = '203.0.113.' . random_int(1, 250);
        $this->redisKeys[] = "shop:brute:{$ip}:login";
        $this->redisKeys[] = "shop:risk:reg:{$ip}:h:" . date('YmdH');
        \support\Redis::set("shop:brute:{$ip}:login", 1);

        $r = RiskEngine::score('user_register', ['user_id' => 0, 'ip' => $ip]);
        $this->assertSame(20, $r['score']);
        $this->assertSame('record', $r['result']);
        $this->assertArrayHasKey('ip_reputation', $r['details']);
    }
}
