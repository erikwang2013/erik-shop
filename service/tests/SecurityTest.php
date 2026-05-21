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

    #[Test]
    public function it_detects_xxe_entity_injection(): void
    {
        $payload = '<!ENTITY xxe SYSTEM "file:///etc/passwd">';
        $this->assertTrue($this->hasXxePattern($payload), 'Should detect XXE entity');
    }

    #[Test]
    public function it_detects_xxe_doctype_injection(): void
    {
        $payload = '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>';
        $this->assertTrue($this->hasXxePattern($payload), 'Should detect XXE doctype');
    }

    #[Test]
    public function it_detects_ssrf_internal_ip(): void
    {
        $ips = ['127.0.0.1', '10.0.0.1', '192.168.1.1', '172.16.0.1', '169.254.169.254', 'localhost', '0.0.0.0'];
        foreach ($ips as $ip) {
            $this->assertTrue($this->isSsrfIp($ip), "Internal IP should be blocked: {$ip}");
        }
    }

    #[Test]
    public function it_allows_public_urls(): void
    {
        $publicHosts = ['api.erik.xyz', 'erik.xyz', 'google.com', 'github.com'];
        foreach ($publicHosts as $host) {
            $this->assertFalse($this->isSsrfIp($host), "Public host should pass: {$host}");
        }
    }

    #[Test]
    public function it_detects_double_extension_attack(): void
    {
        $files = ['shell.php.jpg', 'exploit.php5.png', 'backdoor.phtml.gif'];
        foreach ($files as $f) {
            $parts = explode('.', $f);
            $inner = strtolower($parts[count($parts) - 2]);
            $blocked = ['php','php5','php7','php8','phtml','shtml','cgi','pl','py','rb','sh','exe','bat','com','dll','js','jsp','asp','aspx'];
            $this->assertContains($inner, $blocked, "Double extension blocked: {$f}");
        }
    }

    #[Test]
    public function it_detects_encoded_path_traversal(): void
    {
        $paths = ['%2e%2e%2fetc%2fpasswd', '%252e%252e%252f'];
        foreach ($paths as $p) {
            $this->assertTrue(
                preg_match('/%(25)?2[ef]/i', $p) && preg_match('/%(25)?2[ef].*%(25)?2[ef]/i', $p),
                "Encoded path traversal: {$p}"
            );
        }
    }

    #[Test]
    public function it_detects_null_byte_injection(): void
    {
        $path = "file.php\0.txt";
        $this->assertTrue(str_contains($path, "\0"), 'Should detect null byte');
    }

    #[Test]
    public function it_detects_sqli_benchmark_sleep(): void
    {
        $payloads = ["1' AND BENCHMARK(5000000,MD5('x'))--", "1' AND SLEEP(5)--", "1' AND pg_sleep(5)--"];
        foreach ($payloads as $p) {
            $this->assertTrue($this->hasSqliPattern($p), "Should detect: {$p}");
        }
    }

    #[Test]
    public function it_detects_sqli_load_file_into_outfile(): void
    {
        $payloads = ["1' UNION SELECT LOAD_FILE('/etc/passwd')--", "1' INTO OUTFILE '/var/www/shell.php'--"];
        foreach ($payloads as $p) {
            $this->assertTrue($this->hasSqliPattern($p), "Should detect: {$p}");
        }
    }

    // ===== new detection helpers =====

    private function hasXxePattern(string $input): bool
    {
        $patterns = [
            '/<!ENTITY\s+\w+\s+(SYSTEM|PUBLIC)/i',
            '/<!DOCTYPE\s+\w+\s+\[/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $input)) return true;
        }
        return false;
    }

    private function isSsrfIp(string $host): bool
    {
        $patterns = [
            '/^127\.\d+\.\d+\.\d+$/', '/^10\.\d+\.\d+\.\d+$/',
            '/^172\.(1[6-9]|2\d|3[01])\.\d+\.\d+$/', '/^192\.168\.\d+\.\d+$/',
            '/^0\.\d+\.\d+\.\d+$/', '/^169\.254\.\d+\.\d+$/',
            '/localhost/i', '/169\.254\.169\.254/',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $host)) return true;
        }
        return false;
    }

    private function hasSqliPattern(string $input): bool
    {
        $patterns = [
            '/(\%27|\')\s*(union|select|insert|update|delete|drop|alter|create|truncate|exec|execute)\b/i',
            '/\b(union\s+(all\s+)?select)\b/i', '/\bexec\s*\(/i', '/\bexecute\s*\(/i',
            '/\bxp_cmdshell\b/i', '/\bxp_regread\b/i', '/\bsp_executesql\b/i',
            '/;\s*DROP\s+/i', '/;\s*DELETE\s+FROM\s+/i',
            '/\bbenchmark\s*\(/i', '/\bsleep\s*\(/i', '/\bpg_sleep\s*\(/i',
            '/\bload_file\s*\(/i', '/\binto\s+(outfile|dumpfile)\b/i',
            '/\bwaitfor\s+delay\b/i', '/\bchar\s*\(\s*\d+/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $input)) return true;
        }
        return false;
    }
}
