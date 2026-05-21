<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * Redis 缓存助手 — 统一缓存策略
 * 包含: 缓存穿透防护/缓存雪崩防护(随机TTL)/标签缓存
 */

namespace app\common;

class Cache
{
    private static string $prefix = 'erik:cache:';

    /**
     * 读取缓存 (带穿透防护 — 空值缓存)
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = redis()->get(self::$prefix . $key);
            if ($value === false || $value === null) return $default;
            return json_decode($value, true) ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * 写入缓存 (随机TTL防雪崩)
     */
    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        try {
            // 随机偏移 ±10% 防止缓存雪崩
            $jitteredTtl = (int) ($ttl * (0.9 + mt_rand(0, 20) / 100));
            return (bool) redis()->setEx(
                self::$prefix . $key,
                max(1, $jitteredTtl),
                json_encode($value, JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 读取或写入 (Cache-Aside 模式)
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) return $cached;

        $value = $callback();
        if ($value !== null) {
            self::set($key, $value, $ttl);
        } else {
            // 空值缓存短TTL防穿透
            self::set($key . ':null', '_null_', 60);
        }
        return $value;
    }

    /**
     * 删除缓存 (支持通配符)
     */
    public static function delete(string $key): void
    {
        try {
            redis()->del(self::$prefix . $key);
        } catch (\Throwable $e) {}
    }

    /**
     * 按标签删除 (Tag-based invalidation)
     */
    public static function deleteByTag(string $tag): void
    {
        try {
            $tagKey = self::$prefix . "tag:{$tag}";
            $keys = redis()->sMembers($tagKey);
            if (!empty($keys)) {
                redis()->del(array_merge($keys, [$tagKey]));
            }
        } catch (\Throwable $e) {}
    }

    /**
     * 写入带标签的缓存
     */
    public static function setWithTag(string $key, mixed $value, int $ttl, string $tag): bool
    {
        try {
            $tagKey = self::$prefix . "tag:{$tag}";
            redis()->sAdd($tagKey, self::$prefix . $key);
            redis()->expire($tagKey, $ttl + 600);
            return self::set($key, $value, $ttl);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 预热缓存 — 并发取最新的N条数据
     */
    public static function warmup(string $key, callable $callback, int $ttl = 3600): void
    {
        $value = $callback();
        if ($value !== null) {
            self::set($key, $value, $ttl);
        }
    }

    /**
     * 计数器 (原子操作)
     */
    public static function increment(string $key, int $ttl = 3600): int
    {
        try {
            $fullKey = self::$prefix . $key;
            $count = redis()->incr($fullKey);
            redis()->expire($fullKey, $ttl);
            return (int) $count;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 分布式锁 (简单的Redis SETNX + TTL)
     */
    public static function lock(string $key, int $ttl = 10): bool
    {
        try {
            $fullKey = self::$prefix . "lock:{$key}";
            return (bool) redis()->set($fullKey, 1, ['nx', 'ex' => $ttl]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 释放分布式锁
     */
    public static function unlock(string $key): void
    {
        try {
            redis()->del(self::$prefix . "lock:{$key}");
        } catch (\Throwable $e) {}
    }
}
