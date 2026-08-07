<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use Erikwang2013\Security\SecurityGuard;
use PHPUnit\Framework\TestCase;
use Webman\Config;

/**
 * WAF 安全防护测试 — 验证真实 SecurityGuard（erikwang2013/security-php）
 *
 * 使用与 SecurityMiddleware 相同的配置（config/plugin/erikwang2013/security-php/app.php），
 * 验证攻击 payload 被检测并拦截、正常输入放行。
 */
class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(dirname(__DIR__) . '/config', ['route', 'container']);
        SecurityGuard::reset();
        SecurityGuard::init(Config::get('plugin.erikwang2013.security-php.app', []));
    }

    protected function tearDown(): void
    {
        SecurityGuard::reset();
    }

    /**
     * 执行检测并返回是否应拦截
     */
    private function shouldBlock(array $data): bool
    {
        // 每个测试用独立 IP，避免触发 IP 黑名单互扰
        $ip = '203.0.113.' . random_int(100, 200);
        $threats = SecurityGuard::guard($data, [
            'ip'     => $ip,
            'method' => 'POST',
            'uri'    => '/api/test',
        ]);
        return SecurityGuard::shouldBlock($threats);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_xss_script_tags(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['payload' => '<script>alert("xss")</script>']),
            '<script> 标签应被 XSS 检测器拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_xss_event_handlers(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['payload' => '<img src=x onerror=alert(1)>']),
            'onerror 事件处理器应被 XSS 检测器拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_javascript_protocol(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['payload' => 'javascript:alert(1)']),
            'javascript: 伪协议应被 XSS 检测器拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_sql_injection_union_select(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['query' => "1' UNION SELECT * FROM users--"]),
            'UNION SELECT 注入应被 SQL 注入检测器拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_sql_injection_drop_table(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['query' => "1'; DROP TABLE users;--"]),
            'DROP TABLE 注入应被 SQL 注入检测器拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_xxe_entity_injection(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['payload' => '<!ENTITY xxe SYSTEM "file:///etc/passwd">']),
            'XXE 外部实体应被 XXE 检测器拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_xxe_doctype_injection(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['payload' => '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>']),
            'DOCTYPE 内部子集 XXE 应被拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_path_traversal(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['path' => '../../etc/passwd']),
            '路径遍历 ../ 应被拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_encoded_path_traversal(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['path' => '%2e%2e%2fetc%2fpasswd']),
            'URL 编码路径遍历应被拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_ssrf_internal_ip(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['url' => 'http://169.254.169.254/latest/meta-data/']),
            '云元数据 SSRF 应被拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_credit_card_data_leak(): void
    {
        $this->assertTrue(
            $this->shouldBlock(['card' => '4111 1111 1111 1111']),
            '信用卡号泄露应被敏感数据检测器拦截'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_normal_input(): void
    {
        $normalInputs = [
            'test@example.com',
            'John Doe',
            '123 Main St, New York',
            'This is a normal product description',
            'https://api.erik.xyz/v1/products',
        ];
        foreach ($normalInputs as $input) {
            $this->assertFalse(
                $this->shouldBlock(['value' => $input]),
                "正常输入不应被拦截: '{$input}'"
            );
        }
    }
}
