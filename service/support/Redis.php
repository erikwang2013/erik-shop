<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace support;

use Illuminate\Redis\RedisManager;

/**
 * Redis 门面（基于 illuminate/redis + phpredis 扩展）
 * 替代缺失的 webman/redis 插件，使用 config/redis.php 的连接配置
 *
 * 用法：
 *   support\Redis::get('key')
 *   support\Redis::set('key', 'value', 3600)
 *   support\Redis::connection('session')->get('key')
 */
class Redis
{
    private static ?RedisManager $manager = null;

    public static function manager(): RedisManager
    {
        if (static::$manager === null) {
            $config = config('redis', []);
            $connections = [];
            foreach ($config as $name => $conn) {
                if (is_array($conn) && isset($conn['host'])) {
                    $connections[$name] = $conn;
                }
            }
            if (empty($connections)) {
                $connections = ['default' => []];
            }
            // $app 容器参数仅在启用事件时使用（默认关闭），传空对象即可
            static::$manager = new RedisManager(new \stdClass(), 'phpredis', $connections);
        }
        return static::$manager;
    }

    public static function connection(?string $name = null)
    {
        return static::manager()->connection($name);
    }

    public static function __callStatic(string $method, array $args)
    {
        return static::connection()->{$method}(...$args);
    }
}
