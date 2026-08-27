<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use support\Redis;
use Throwable;

/**
 * 熔断打开异常：调用方应映射为 HTTP 503（服务暂不可用）
 */
class CircuitBreakerOpenException extends \RuntimeException
{
}

/**
 * 轻量 Redis 熔断器（保护外部 API 调用：支付网关 / 社交登录）
 *
 * 状态键：
 *   shop_cb_state:{name}  存在即熔断中（TTL = open_seconds）
 *   shop_cb_fail:{name}   连续失败计数（TTL = open_seconds，滑动窗口）
 *
 * 半开探测为简化版：无独立状态机，熔断 TTL 过期后下一个请求自然放行即为探测，
 * 成功 → recordSuccess 复位（DEL 两个 key），失败 → 计数重新累积
 * （ponytail: 简化半开探测；若需严格半开（限流探测/并发探测/失败即重开）
 *   需引入独立 HALF_OPEN 状态键 + 探测窗口）
 *
 * 业务性失败（网关 HTTP 成功但业务拒绝，如卡被拒/无效 token）不计入失败计数：
 * 由调用方通过 call() 的 nonCountingExceptions 白名单声明，防止攻击者用无效请求打挂熔断器
 *
 * 故障决策（fail-open）：所有 Redis 操作异常时静默放行，不熔断、不抛错
 * —— 熔断器是降级保护，Redis 挂了不应把外部 API 一并打挂
 */
final class CircuitBreaker
{
    /**
     * 熔断中？
     */
    public static function isOpen(string $name): bool
    {
        try {
            return (bool) Redis::exists(self::stateKey($name));
        } catch (Throwable $e) {
            return false; // fail-open：Redis 不可用时不熔断
        }
    }

    /**
     * 成功：复位熔断状态与失败计数
     */
    public static function recordSuccess(string $name): void
    {
        try {
            Redis::del(self::stateKey($name), self::failKey($name));
        } catch (Throwable $e) {
            // fail-open：忽略
        }
    }

    /**
     * 失败：连续计数，达到阈值打开熔断
     */
    public static function recordFailure(string $name): void
    {
        try {
            $count = (int) Redis::incr(self::failKey($name));
            if ($count === 1) {
                Redis::expire(self::failKey($name), self::openSeconds()); // 首错起算滑动窗口
            }
            if ($count >= self::failThreshold()) {
                Redis::set(self::stateKey($name), '1', 'EX', self::openSeconds());
                Redis::del(self::failKey($name));
            }
        } catch (Throwable $e) {
            // fail-open：忽略
        }
    }

    /**
     * 执行受熔断保护的调用
     *
     * @param string   $name     熔断器名称（如 stripe / social:google）
     * @param callable $fn       实际调用
     * @param callable|null $fallback 熔断/失败时的降级回调，缺省则抛异常
     * @param string[] $nonCountingExceptions 业务异常类白名单（如卡被拒/无效 token），
     *                                        命中则不计入失败计数（防止攻击者用无效请求打挂熔断器）
     * @throws CircuitBreakerOpenException 熔断中且无 fallback
     * @throws Throwable fn 内部异常（非熔断且无 fallback）
     */
    public static function call(string $name, callable $fn, ?callable $fallback = null, array $nonCountingExceptions = []): mixed
    {
        if (self::isOpen($name)) {
            if ($fallback !== null) {
                return $fallback();
            }
            throw new CircuitBreakerOpenException("Circuit breaker open: {$name}");
        }

        try {
            $result = $fn();
            self::recordSuccess($name);
            return $result;
        } catch (Throwable $e) {
            if (!self::isNonCounting($e, $nonCountingExceptions)) {
                self::recordFailure($name);
            }
            if ($fallback !== null) {
                return $fallback();
            }
            throw $e;
        }
    }

    private static function isNonCounting(Throwable $e, array $classes): bool
    {
        foreach ($classes as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }
        return false;
    }

    private static function stateKey(string $name): string
    {
        return "shop_cb_state:{$name}";
    }

    private static function failKey(string $name): string
    {
        return "shop_cb_fail:{$name}";
    }

    private static function failThreshold(): int
    {
        return (int) config('concurrency.circuit_breaker.fail_threshold', 5);
    }

    private static function openSeconds(): int
    {
        return (int) config('concurrency.circuit_breaker.open_seconds', 30);
    }
}
