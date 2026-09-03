<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

/**
 * 金额十进制字符串运算助手 —— 全项目金额计算唯一入口。
 *
 * 约定：
 * - 一律 decimal 字符串参与运算（Eloquent decimal 列经 PDO 返回即 string，保留字符串，不转 float）
 * - 显式传 scale，禁止依赖 bcscale（默认 0 会静默截断）
 * - 中间运算 scale=8（SCALE_MID），最终金额用 round() 收敛到 scale=2（SCALE_FIN）
 * - 先聚合后舍入：禁止每步先 round 再乘加
 *
 * 边界：round() 与 PHP round($v, $scale) 默认一致（half away from zero，负数同向远离零）；
 * toIntCents/fromCents 负责网关最小货币单位（分）换算；format() 仅用于必须字符串的对外协议字段。
 */
final class Money
{
    public const SCALE_MID = 8;   // 中间运算精度
    public const SCALE_FIN = 2;   // 最终金额精度

    /** a + b，scale 位小数 */
    public static function add(string $a, string $b, int $scale = self::SCALE_MID): string
    {
        return bcadd($a, $b, $scale);
    }

    /** a - b，scale 位小数 */
    public static function sub(string $a, string $b, int $scale = self::SCALE_MID): string
    {
        return bcsub($a, $b, $scale);
    }

    /** a * b，scale 位小数 */
    public static function mul(string $a, string $b, int $scale = self::SCALE_MID): string
    {
        return bcmul($a, $b, $scale);
    }

    /** a / b，scale 位小数；除数为 0 抛 DivisionByZeroError */
    public static function div(string $a, string $b, int $scale = self::SCALE_MID): string
    {
        if (bccomp($b, '0', $scale) === 0) {
            throw new \DivisionByZeroError('Money::div 除数为 0');
        }
        return bcdiv($a, $b, $scale);
    }

    /** 十进制字符串比较（scale=8），返回 -1/0/1，语义同运算符 >= / < / > 的替代 */
    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE_MID);
    }

    /**
     * 十进制字符串精确舍入，与 PHP round($v, $scale) 默认一致：half away from zero
     * （负数同向远离零，如 round('-2.5', 0) === '-3'），杜绝 float 二进制误差。
     * 实现：|x|*10^s 在 scale=8 下精确（≤8 位小数输入），+0.5 后截断得 floor，再 /10^s；
     * 结果非零且原数为负时补负号（避免 '-0.00'）。
     *
     * round('2.5', 0) === '3'；round('-2.5', 0) === '-3'
     * round('1.005', 2) === '1.01'；round('0.0049', 2) === '0.00'
     */
    public static function round(string $num, int $scale = self::SCALE_FIN): string
    {
        $neg = str_starts_with($num, '-');
        $abs = $neg ? substr($num, 1) : $num;
        $factor = (string) (10 ** $scale);
        $shifted = bcadd(bcmul($abs, $factor, self::SCALE_MID), '0.5', self::SCALE_MID);
        $floored = bcadd($shifted, '0', 0);
        $result = bcdiv($floored, $factor, $scale);
        return ($neg && bccomp($result, '0', $scale) !== 0) ? '-' . $result : $result;
    }

    /** 元 → 网关最小货币单位(分)：先 scale=2 收敛再乘 100（整数精确） */
    public static function toIntCents(string $amount): int
    {
        return (int) bcmul(self::round($amount, self::SCALE_FIN), '100', 0);
    }

    /** 网关分 → 元字符串 */
    public static function fromCents(int|string $cents, int $scale = self::SCALE_FIN): string
    {
        return bcdiv((string) $cents, '100', $scale);
    }

    /**
     * 外部入参归一（E 类）：任意数值 → 分位金额字符串。
     * bcmath 只接受 well-formed 十进制，故先 trim 再按形状校验——拒绝科学计数法
     * （'1e3'）、hex、千分位等 is_numeric 放行但 bcmath 抛 ValueError 的形态；
     * 非数值/非标量（如 amount[]= 数组入参）返回 '0.00'。JSON number 与普通数字字符串均可安全进入。
     */
    public static function normalize(mixed $value): string
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return '0.00';
        }
        $s = trim((string) $value);
        return preg_match('/^[+-]?\d+(\.\d+)?$/', $s) ? self::round($s) : '0.00';
    }

    /**
     * 协议边界格式化：scale 位小数的纯十进制字符串（无千分位）。
     * 仅用于必须字符串金额的对外协议（PayPal/Adyen/Klarna JSON value、Feed 金额字段）；
     * 普通 JSON 展示数值用 (float) 只转类型、不做运算。
     */
    public static function format(string $amount, int $scale = self::SCALE_FIN): string
    {
        return self::round($amount, $scale);
    }
}
