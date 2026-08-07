<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erik\Encryptable;

use Maize\Encryptable\Encryption;

/**
 * 模型字段加解密 trait（经典 Encryptable API 兼容层）
 *
 * erikwang2013/encryptable 包提供的是 CastsAttributes 实现，
 * 而项目模型使用经典 trait 模式：`use Encryptable` + `$encryptable = [...]`。
 * 本 trait 实现该经典 API，底层复用包的 Encryption::php()（ENCRYPTION_KEY 环境变量）。
 *
 * 用法：
 *   class User extends Model {
 *       use Encryptable;
 *       protected $encryptable = ['email', 'phone'];
 *   }
 */
trait Encryptable
{
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        return $this->decryptAttributeValue($key, $value);
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute($key, $this->encryptAttributeValue($key, $value));
    }

    protected function isEncryptableField(string $key): bool
    {
        return isset($this->encryptable) && in_array($key, $this->encryptable, true);
    }

    protected function encryptAttributeValue(string $key, $value)
    {
        if ($value === null || ! $this->isEncryptableField($key)) {
            return $value;
        }
        return Encryption::php()->encrypt($value);
    }

    protected function decryptAttributeValue(string $key, $value)
    {
        if ($value === null || ! $this->isEncryptableField($key)) {
            return $value;
        }
        return Encryption::php()->decrypt($value);
    }
}
