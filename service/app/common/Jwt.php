<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * JWT 静态代理 - 封装 ErikJwt\JWT 实例方法
 */

namespace app\common;

use ErikJwt\JWT as ErikJwtInstance;
use support\Redis;

class Jwt
{
    private static ?ErikJwtInstance $instance = null;

    private static function instance(): ErikJwtInstance
    {
        if (self::$instance === null) {
            $config = config('jwt') ?: (require base_path() . '/config/jwt.php');
            if (empty($config['secret_key'])) {
                $config['secret_key'] = getenv('JWT_SECRET') ?: getenv('JWT_SECRET_KEY') ?: '';
            }
            if (empty($config['secret_key'])) {
                throw new \RuntimeException('JWT secret key is not configured. Set JWT_SECRET or JWT_SECRET_KEY in .env');
            }
            self::$instance = new ErikJwtInstance($config);
        }
        return self::$instance;
    }

    public static function encode(array $payload, int $expire = 0): string
    {
        $payload['type'] = 'access';
        return self::instance()->encode($payload, $expire);
    }

    public static function encodeRefresh(array $payload, int $expire = 0): string
    {
        $payload['type'] = 'refresh';
        return self::instance()->encode($payload, $expire);
    }

    public static function decode(string $token): ?array
    {
        try {
            return self::instance()->decode($token);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 吊销令牌（按 jti 加入 Redis 黑名单，TTL = 令牌剩余有效期）
     */
    public static function revoke(string $token): bool
    {
        $payload = self::decode($token);
        $ttl = ($payload['exp'] ?? 0) - time();
        if (empty($payload['jti']) || $ttl <= 0) {
            return false;
        }
        try {
            return (bool)Redis::setex(self::blacklistKey($payload['jti']), $ttl, '1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isRevoked(string $token): bool
    {
        $payload = self::decode($token);
        if (empty($payload['jti'])) {
            return false;
        }
        try {
            return (bool)Redis::exists(self::blacklistKey($payload['jti']));
        } catch (\Throwable $e) {
            // ponytail: Redis 不可用时 fail-open，已吊销 token 最多存活至自身 exp；需强一致改为注入 RedisTokenStorage（fail-closed）
            return false;
        }
    }

    private static function blacklistKey(string $jti): string
    {
        return 'shop:jwt_blacklist:' . $jti;
    }
}
