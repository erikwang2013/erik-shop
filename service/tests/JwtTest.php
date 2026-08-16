<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * JWT Test - TDD RED phase
 * Tests will fail until ErikJwt package is properly wired
 */
class JwtTest extends TestCase
{
    protected function setUp(): void
    {
        // Boot webman config
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__));
        }
        require_once BASE_PATH . '/vendor/autoload.php';

        // 测试环境显式提供签名密钥（Jwt::instance 对空密钥 fail-closed，不依赖 .env）
        if (!getenv('JWT_SECRET') && !getenv('JWT_SECRET_KEY')) {
            putenv('JWT_SECRET=' . bin2hex(random_bytes(32)));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_encodes_a_valid_token(): void
    {
        $payload = ['sub' => '1234567890', 'email' => 'test@example.com', 'level' => 1];

        // Load config
        $config = require BASE_PATH . '/config/jwt.php';
        $this->assertArrayHasKey('secret_key', $config, 'JWT config must have secret_key');
        $this->assertNotEmpty($config['secret_key'], 'JWT secret_key must not be empty');

        // Token must be a non-empty string with 3 dot-separated parts
        $token = \app\common\Jwt::encode($payload);
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT token must have 3 parts (header.payload.signature)');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_decodes_a_valid_token_and_returns_payload(): void
    {
        $payload = ['sub' => '1234567890', 'email' => 'test@example.com', 'level' => 1];
        $token = \app\common\Jwt::encode($payload);

        $decoded = \app\common\Jwt::decode($token);
        $this->assertIsArray($decoded);
        $this->assertEquals('1234567890', $decoded['sub']);
        $this->assertEquals('test@example.com', $decoded['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_invalid_token(): void
    {
        $result = \app\common\Jwt::decode('invalid.token.here');
        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_invalid_or_empty_token(): void
    {
        // JWT encode uses default expire; test that invalid strings return null
        $result = \app\common\Jwt::decode('expired.invalid.token.string');
        $this->assertNull($result, 'Invalid token should return null');
        $result = \app\common\Jwt::decode('');
        $this->assertNull($result, 'Empty token should return null');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_revokes_token_and_flips_revoked_state(): void
    {
        $token = \app\common\Jwt::encode(['sub' => '1234567890']);

        try {
            \support\Redis::ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis 不可用: ' . $e->getMessage());
            return;
        }

        $this->assertFalse(\app\common\Jwt::isRevoked($token), 'Fresh token must not be revoked');
        $this->assertTrue(\app\common\Jwt::revoke($token), 'Revoke must succeed');
        $this->assertTrue(\app\common\Jwt::isRevoked($token), 'Revoked token must be flagged');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_never_revokes_an_invalid_token(): void
    {
        $this->assertFalse(\app\common\Jwt::revoke('invalid.token.here'));
    }
}
