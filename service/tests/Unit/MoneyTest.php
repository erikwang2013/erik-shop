<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests\Unit;

use app\common\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Money 十进制字符串金额助手回归测试（架构规格 §1 自检断言）
 */
class MoneyTest extends TestCase
{
    #[Test]
    public function add_keeps_mid_scale_and_handles_zero_and_negative(): void
    {
        $this->assertSame('0.30000000', Money::add('0.1', '0.2', Money::SCALE_MID));
        $this->assertSame('0.00000000', Money::add('0', '0'));
        $this->assertSame('-0.25', Money::add('-1.50', '1.25', 2));
    }

    #[Test]
    public function sub_obeys_scale_and_negative_results(): void
    {
        $this->assertSame('3.01', Money::sub('5.00', '1.99', 2));
        $this->assertSame('-1.00', Money::sub('0', '1', 2));
        $this->assertSame('0.00000000', Money::sub('0', '0'));
    }

    #[Test]
    public function mul_keeps_mid_scale_and_handles_zero_and_negative(): void
    {
        $this->assertSame('3.30000000', Money::mul('1.10', '3', Money::SCALE_MID));
        $this->assertSame('0.00000000', Money::mul('0', '5'));
        $this->assertSame('-3.00000000', Money::mul('-2', '1.5'));
    }

    #[Test]
    public function div_is_scale_limited_and_guards_zero_divisor(): void
    {
        $this->assertSame('0.33333333', Money::div('1', '3', Money::SCALE_MID));
        $this->assertSame('-2.50', Money::div('-10', '4', 2));
        // 负除数语义与 PHP 一致
        $this->assertSame('-0.25', Money::div('1', '-4', 2));

        $this->expectException(\DivisionByZeroError::class);
        Money::div('1', '0');
    }

    #[Test]
    public function cmp_compares_decimals(): void
    {
        $this->assertSame(0, Money::cmp('1.10', '1.1'));
        $this->assertSame(0, Money::cmp('0', '-0'));
        $this->assertSame(-1, Money::cmp('1.10', '1.2'));
        $this->assertSame(1, Money::cmp('2', '1.99'));
        $this->assertSame(-1, Money::cmp('-2', '-1.99'));
    }

    #[Test]
    public function round_matches_php_half_away_from_zero(): void
    {
        $this->assertSame('3', Money::round('2.5', 0));
        $this->assertSame('-3', Money::round('-2.5', 0));
        $this->assertSame('1', Money::round('0.5', 0));
        $this->assertSame('-1', Money::round('-0.5', 0));
        // 1.005 在 float 下会变成 1.00，bcmath 路径必须给 1.01
        $this->assertSame('1.01', Money::round('1.005', 2));
        $this->assertSame('-1.01', Money::round('-1.005', 2));
        $this->assertSame('0.01', Money::round('0.005', 2));
        $this->assertSame('-0.01', Money::round('-0.005', 2));
        $this->assertSame('1.00', Money::round('1.004', 2));
        $this->assertSame('0.00', Money::round('0.0049', 2));
        $this->assertSame('2.00', Money::round('1.999', 2));
        $this->assertSame('-2.00', Money::round('-1.999', 2));
        $this->assertSame('0.00', Money::round('0', 2));
        // 负零不输出负号
        $this->assertSame('0.00', Money::round('-0.004', 2));
        $this->assertSame('0.00', Money::round('-0', 2));
        // scale 大于输入精度时补零
        $this->assertSame('1.5000', Money::round('1.5', 4));
    }

    #[Test]
    public function cents_conversions_round_trip(): void
    {
        $this->assertSame(0, Money::toIntCents('0'));
        $this->assertSame(123, Money::toIntCents('1.23'));
        $this->assertSame(1999, Money::toIntCents('19.99'));
        $this->assertSame(-500, Money::toIntCents('-5.00'));
        $this->assertSame(101, Money::toIntCents('1.005'));
        $this->assertSame(1000, Money::toIntCents('9.999'));
        $this->assertSame(0, Money::toIntCents('0.004'));
        $this->assertSame(-1, Money::toIntCents('-0.005'));
        $this->assertSame(268, Money::toIntCents('2.675'));

        $this->assertSame('0.00', Money::fromCents(0));
        $this->assertSame('0.01', Money::fromCents(1));
        $this->assertSame('19.99', Money::fromCents(1999));
        $this->assertSame('-5.00', Money::fromCents(-500));
        $this->assertSame('19.99', Money::fromCents('1999'));
    }

    #[Test]
    public function format_normalizes_to_final_scale(): void
    {
        $this->assertSame('19.90', Money::format('19.9'));
        $this->assertSame('0.00', Money::format('0'));
        $this->assertSame('-1.01', Money::format('-1.005'));
    }

    #[Test]
    public function normalize_accepts_only_well_formed_decimals(): void
    {
        // 合法：浮点/整数/带空白数字字符串
        $this->assertSame('12.30', Money::normalize(' 12.3 '));
        $this->assertSame('-5.00', Money::normalize(-5));
        $this->assertSame('9.99', Money::normalize(9.99));
        $this->assertSame('0.00', Money::normalize(0));
        $this->assertSame('12.35', Money::normalize('12.345'));
        $this->assertSame('-12.35', Money::normalize('-12.345'));
        $this->assertSame('3.50', Money::normalize('+3.5'));
        $this->assertSame('0.00', Money::normalize('-0'));
        // float 二进制误差在 scale=2 收敛
        $this->assertSame('0.30', Money::normalize(0.1 + 0.2));
    }

    #[Test]
    public function normalize_rejects_bcmath_incompatible_inputs(): void
    {
        // bcmath 会抛 ValueError 的形态 → 归零而非 500
        $this->assertSame('0.00', Money::normalize('1e3'));
        $this->assertSame('0.00', Money::normalize('-1e3'));
        $this->assertSame('0.00', Money::normalize('abc'));
        $this->assertSame('0.00', Money::normalize('1,000.5'));
        $this->assertSame('0.00', Money::normalize('0x1A'));
        $this->assertSame('0.00', Money::normalize('.5'));
        $this->assertSame('0.00', Money::normalize('1.'));
        $this->assertSame('0.00', Money::normalize('   '));
    }

    #[Test]
    public function normalize_rejects_non_scalar_inputs(): void
    {
        $this->assertSame('0.00', Money::normalize([]));
        $this->assertSame('0.00', Money::normalize(['1.00']));
        $this->assertSame('0.00', Money::normalize(null));
        $this->assertSame('0.00', Money::normalize(true));
    }
}
