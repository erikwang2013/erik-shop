<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use Maize\Encryptable\Exceptions\DecryptException;
use Maize\Encryptable\Exceptions\EncryptException;
use Maize\Encryptable\PHPEncrypter;

/**
 * 安全 Encrypter（修复 PHP 8.3 空 IV 警告）
 *
 * 背景：erikwang2013/encryptable 的 PHPEncrypter::openSSLEncrypt/openSSLDecrypt/isEncrypted
 * 调用 openssl_encrypt/decrypt 时未显式传 IV。PHP 8.0-8.2 隐式使用空字符串 IV（CBC 下
 * 即 16 字节 \0），但 PHP 8.3 起对"未传 IV"发出 E_WARNING
 * （"Using an empty Initialization Vector (iv) is potentially insecure"），
 * 本项目的全局错误处理器将其转为 ErrorException，导致注册/登录等所有 Encryptable
 * 字段写入返回 500。
 *
 * 修复：显式传入 16 字节零 IV —— 与旧版本隐式行为字节级一致（旧密文可正常解密），
 * 仅消除 warning。加密强度维持与原实现相同（如需更强 IV 随机化需配合密钥轮换，
 * 属后续增强，不在本修复范围）。
 *
 * 注册：support/bootstrap.php 中通过 Encryption::setResolver() 绑定。
 */
class SecureEncrypter extends PHPEncrypter
{
    /**
     * CBC 类 cipher 的固定零 IV（与 PHP 8.0-8.2 隐式行为一致，保证旧数据可解密）
     */
    protected function iv(): string
    {
        return str_repeat("\0", openssl_cipher_iv_length($this->getEncryptionCipher()));
    }

    protected function openSSLEncrypt(string $value): string
    {
        $cipher = $this->getEncryptionCipher();
        $value = openssl_encrypt(
            $value,
            $cipher,
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $this->iv()
        );

        if ($value === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return $value;
    }

    protected function openSSLDecrypt(string $payload): string
    {
        $cipher = $this->getEncryptionCipher();
        $iv = $this->iv();

        foreach ($this->getDecryptionKeyRing() as $key) {
            $decrypted = openssl_decrypt($payload, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            if ($decrypted !== false && str_starts_with($decrypted, self::DIRTY_BIT_KEY)) {
                return $decrypted;
            }
        }

        throw new DecryptException('Could not decrypt the data.');
    }

    public function isEncrypted($value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $cipher = $this->getEncryptionCipher();
        $iv = $this->iv();

        foreach ($this->getDecryptionKeyRing() as $key) {
            try {
                $decoded = $this->base64Decode($value);
                $decrypted = openssl_decrypt($decoded, $cipher, $key, OPENSSL_RAW_DATA, $iv);
                if ($decrypted !== false && str_starts_with($decrypted, self::DIRTY_BIT_KEY)) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }
}
