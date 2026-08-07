<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

/**
 * 敏感数据加解密封装
 * 用于接口层敏感数据传输
 */
class Encryption
{
    private static string $key;
    private static string $cipher;

    /**
     * 初始化加密参数
     */
    private static function init(): void
    {
        if (!isset(self::$key)) {
            $key = config('encryption.key', '');
            if (empty($key)) {
                throw new \RuntimeException('ENCRYPTION_KEY is not configured. Set ENCRYPTION_KEY in .env');
            }
            // 支持 Laravel 风格 base64: 前缀
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }
            $keyLength = strlen($key);
            if (!in_array($keyLength, [16, 24, 32], true)) {
                throw new \RuntimeException('ENCRYPTION_KEY must decode to 16/24/32 bytes for AES cipher');
            }
            self::$key = $key;
            self::$cipher = config('encryption.cipher', 'AES-256-CBC');
        }
    }

    /**
     * 加密数据
     */
    public static function encrypt(mixed $data): string
    {
        self::init();
        $json = json_encode($data);
        $iv = random_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($json, self::$cipher, self::$key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * 解密数据
     */
    public static function decrypt(string $payload): mixed
    {
        self::init();
        $decoded = base64_decode($payload);
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);
        $json = openssl_decrypt($encrypted, self::$cipher, self::$key, 0, $iv);
        return json_decode($json, true);
    }
}
