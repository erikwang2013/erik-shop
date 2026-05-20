<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use Hashids\Hashids;

/**
 * Hashids 编解码封装
 * 用于接口层ID加解密，隐藏真实snowflake ID
 */
class HashidsHelper
{
    private static ?Hashids $instance = null;

    /**
     * 获取单例
     */
    public static function instance(): Hashids
    {
        if (self::$instance === null) {
            self::$instance = new Hashids(
                config('hashids.salt'),
                config('hashids.min_length'),
                config('hashids.alphabet')
            );
        }
        return self::$instance;
    }

    /**
     * 编码：snowflake ID → hashid
     */
    public static function encode(int|string $id): string
    {
        return self::instance()->encode((int)$id);
    }

    /**
     * 解码：hashid → snowflake ID
     * 解码失败时返回null
     */
    public static function decode(string $hash): ?string
    {
        $decoded = self::instance()->decode($hash);
        if (empty($decoded)) {
            return config('hashids.throw_on_decode_fail') ? null : '0';
        }
        return (string) $decoded[0];
    }
}
