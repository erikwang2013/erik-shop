<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\Money;
use PHPUnit\Framework\TestCase;

/**
 * Money 十进制字符串金额助手单元测试（架构规格 §1 自检断言）
 */
class MoneyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function add_keeps_mid_scale(): void
    {
        $this->assertSame('0.30000000', Money::add('0.1', '0.2', Money::SCALE_MID));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sub_obeys_scale(): void
    {
        $this->assertSame('3.01', Money::sub('5.00', '1.99', 2));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mul_keeps_mid_scale(): void
    {
        $this->assertSame('3.30000000', Money::mul('1.10', '3', Money::SCALE_MID));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function div_repeats_and_guards_zero(): void
    {
        $this->assertSame('0.33333333', Money::div('1', '3', Money::SCALE_MID));
        $this->expectException(\DivisionByZeroError::class);
        Money::div('1', '0');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cmp_compares_decimals(): void
    {
        $this->assertSame(0, Money::cmp('1.10', '1.1'));
        $this->assertSame(-1, Money::cmp('1.10', '1.2'));
        $this->assertSame(1, Money::cmp('2', '1.99'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function round_matches_php_half_away_from_zero(): void
    {
        $this->assertSame('3', Money::round('2.5', 0));
        $this->assertSame('-3', Money::round('-2.5', 0));
        $this->assertSame('1.01', Money::round('1.005', 2));
        $this->assertSame('-1.01', Money::round('-1.005', 2));
        $this->assertSame('1.00', Money::round('1.004', 2));
        $this->assertSame('0.00', Money::round('0.0049', 2));
        $this->assertSame('2.00', Money::round('1.999', 2));
        $this->assertSame('-2.00', Money::round('-1.999', 2));
        // 负零不输出负号
        $this->assertSame('0.00', Money::round('-0.004', 2));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cents_conversions_round_trip(): void
    {
        $this->assertSame(123, Money::toIntCents('1.23'));
        $this->assertSame(1999, Money::toIntCents('19.99'));
        $this->assertSame(-500, Money::toIntCents('-5.00'));
        $this->assertSame(1000, Money::toIntCents('9.999'));
        $this->assertSame('19.99', Money::fromCents(1999));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function format_normalizes_to_final_scale(): void
    {
        $this->assertSame('19.90', Money::format('19.9'));
        $this->assertSame('0.00', Money::format('0'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function normalize_accepts_only_well_formed_decimals(): void
    {
        // 合法：浮点/整数/带空白数字字符串
        $this->assertSame('12.30', Money::normalize(' 12.3 '));
        $this->assertSame('-5.00', Money::normalize(-5));
        $this->assertSame('9.99', Money::normalize(9.99));
        $this->assertSame('12.35', Money::normalize('12.345'));
        // bcmath 会抛 ValueError 的形态 → 归零而非 500
        $this->assertSame('0.00', Money::normalize('1e3'));
        $this->assertSame('0.00', Money::normalize('abc'));
        $this->assertSame('0.00', Money::normalize('1,000.5'));
        $this->assertSame('0.00', Money::normalize([]));
    }
}
