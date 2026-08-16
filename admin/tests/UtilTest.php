<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\common\Tests;

use plugin\admin\app\common\Util;
use PHPUnit\Framework\TestCase;

/**
 * Admin 公共工具单元测试（纯逻辑，无 DB 依赖）
 */
class UtilTest extends TestCase
{
    public function testPasswordHashAndVerify(): void
    {
        $hash = Util::passwordHash('secret123');
        $this->assertNotSame('secret123', $hash);
        $this->assertTrue(Util::passwordVerify('secret123', $hash));
        $this->assertFalse(Util::passwordVerify('wrong', $hash));
    }

    public function testHumanDate(): void
    {
        $this->assertSame('10秒前', Util::humanDate(time() - 10));
        $this->assertStringContainsString('分钟前', Util::humanDate(time() - 300));
        $this->assertStringContainsString('小时前', Util::humanDate(time() - 7200));
        $this->assertStringContainsString('天前', Util::humanDate(time() - 86400 * 2));
    }
}
