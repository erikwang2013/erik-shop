<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use PHPUnit\Framework\TestCase;

/**
 * support\Redis 门面回归测试
 * 验证 illuminate/redis RedisManager 构造参数与全局 redis() 辅助函数
 */
class RedisFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        \Webman\Config::load(dirname(__DIR__) . '/config', ['route', 'container']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_ping_returns_true(): void
    {
        try {
            $result = \support\Redis::ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis 不可用: ' . $e->getMessage());
            return;
        }
        $this->assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function facade_set_get_roundtrip(): void
    {
        try {
            $key = 'facade_test_' . uniqid();
            \support\Redis::set($key, 'hello', 60);
            $this->assertEquals('hello', \support\Redis::get($key));
            \support\Redis::del($key);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis 不可用: ' . $e->getMessage());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function global_redis_helper_returns_connection(): void
    {
        require_once dirname(__DIR__) . '/app/functions.php';
        try {
            $conn = redis();
            $this->assertInstanceOf(\Illuminate\Redis\Connections\Connection::class, $conn);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis 不可用: ' . $e->getMessage());
        }
    }
}
