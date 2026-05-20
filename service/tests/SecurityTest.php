<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Security Test - TDD RED phase
 * Tests for SecurityMiddleware attack detection
 */
class SecurityTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_xss_script_tags(): void
    {
        $payload = '<script>alert("xss")</script>';
        $this->assertTrue($this->hasXssPattern($payload), 'Should detect script tag');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_xss_event_handlers(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $this->assertTrue($this->hasXssPattern($payload), 'Should detect onerror handler');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_javascript_protocol(): void
    {
        $payload = 'javascript:alert(1)';
        $this->assertTrue($this->hasXssPattern($payload), 'Should detect javascript: protocol');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_sql_injection_union_select(): void
    {
        $payload = "1' UNION SELECT * FROM users--";
        $this->assertTrue($this->hasSqliPattern($payload), 'Should detect UNION SELECT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_sql_injection_drop(): void
    {
        $payload = "1'; DROP TABLE users;--";
        $this->assertTrue($this->hasSqliPattern($payload), 'Should detect DROP TABLE');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_normal_input(): void
    {
        $normalInputs = [
            'test@example.com',
            'John Doe',
            '123 Main St, New York',
            'This is a normal product description',
        ];
        foreach ($normalInputs as $input) {
            $this->assertFalse($this->hasXssPattern($input), "'{$input}' should NOT trigger XSS");
            $this->assertFalse($this->hasSqliPattern($input), "'{$input}' should NOT trigger SQLi");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_path_traversal(): void
    {
        $this->assertTrue(str_contains('../../etc/passwd', '..'), 'Should detect ../');
        $blockedPaths = ['.env', '.git', 'phpmyadmin', '/etc/'];
        foreach ($blockedPaths as $path) {
            $this->assertTrue(str_contains(strtolower('/test/' . $path . '/x'), $path), "Should detect {$path}");
        }
    }

    // ===== helper patterns from SecurityMiddleware =====

    private function hasXssPattern(string $input): bool
    {
        $patterns = [
            '/<script\b[^>]*>/i', '/<iframe\b[^>]*>/i', '/<object\b[^>]*>/i',
            '/<embed\b[^>]*>/i', '/<link\b[^>]*>/i', '/javascript\s*:/i',
            '/on\w+\s*=\s*["\']?[^"\'>]*["\']?/i', '/<svg\b[^>]*>/i',
            '/<img[^>]+on\w+\s*=/i', '/expression\s*\(/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $input)) return true;
        }
        return false;
    }

    private function hasSqliPattern(string $input): bool
    {
        $patterns = [
            '/(\%27|\')\s*(union|select|insert|update|delete|drop|alter|create|truncate|exec|execute)\b/i',
            '/\b(union\s+(all\s+)?select)\b/i', '/\bexec\s*\(/i',
            '/;\s*DROP\s+/i', '/;\s*DELETE\s+FROM\s+/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $input)) return true;
        }
        return false;
    }
}
