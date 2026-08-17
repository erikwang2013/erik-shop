<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use support\Redis;

/**
 * Redis 分布式锁（单机/多实例通用，锁落在全局 Redis，见 config/redis.php）
 *
 * 用法：
 *   DistributedLock::run('lock:order:123', function () { ... });
 *
 * 说明：
 *   - key 传完整锁键，约定统一加 lock: 前缀
 *   - ttl 秒自动过期防死锁，持锁进程崩溃也会自动释放
 *   - 释放用 Lua 原子比对 token，只删自己持有的锁，不会误删他人锁
 *
 * 故障决策（fail-closed）：Redis 连接失败时直接抛异常，由调用方按 500 处理；
 * 不跳过加锁继续执行。锁保护的是写操作，fail-open 会让并发写无保护裸奔，
 * 宁可报错也不无锁执行。
 */
class DistributedLock
{
    /**
     * 获取锁后执行回调，finally 中原子释放
     *
     * @param string   $key         完整锁键（约定 lock: 前缀，如 lock:order:123）
     * @param callable $fn          受锁保护的回调
     * @param int      $ttl         锁过期秒数，需大于回调执行耗时
     * @param int      $waitSeconds 拿不到锁时自旋等待的最大秒数
     * @throws \RuntimeException 等待超时（操作繁忙）
     */
    public static function run(string $key, callable $fn, int $ttl = 10, int $waitSeconds = 5): mixed
    {
        $token = uniqid('', true);
        $deadline = microtime(true) + $waitSeconds;

        while (!Redis::set($key, $token, 'EX', $ttl, 'NX')) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('操作繁忙，请稍后重试');
            }
            usleep(50_000); // 50ms 自旋间隔
        }

        try {
            return $fn();
        } finally {
            self::release($key, $token);
        }
    }

    /**
     * 原子释放：仅当锁仍是自己持有的才删除
     */
    private static function release(string $key, string $token): void
    {
        Redis::eval(
            "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end",
            1,
            $key,
            $token
        );
    }
}
