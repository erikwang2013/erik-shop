<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use Erikwang2013\Hashids\HashidsManager;

class HashidsHelper
{
    private static ?HashidsManager $instance = null;

    public static function instance(): HashidsManager
    {
        if (self::$instance === null) {
            $config = config('hashids');
            if (empty($config['connections']['main']['salt'] ?? '')) {
                throw new \RuntimeException('HASHIDS_SALT is not configured. Set HASHIDS_SALT in .env');
            }
            self::$instance = new HashidsManager($config);
        }
        return self::$instance;
    }

    public static function encode(int|string $id): string
    {
        return self::instance()->encode((int) $id);
    }

    public static function decode(string $hash): ?string
    {
        $decoded = self::instance()->decode($hash);
        if (empty($decoded)) return null;
        return (string) $decoded[0];
    }
}
