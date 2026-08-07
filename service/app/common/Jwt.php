<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * JWT 静态代理 - 封装 ErikJwt\JWT 实例方法
 */

namespace app\common;

use ErikJwt\JWT as ErikJwtInstance;

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
}
