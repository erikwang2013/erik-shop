<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\HashidsHelper;

/**
 * Hashids ID 混淆编解码回归测试
 * 覆盖中间件链路的契约：encode(decode) 往返一致、ID 唯一性、垃圾输入返回 null
 */
class HashidsTest extends IntegrationTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function encode_decode_roundtrip(): void
    {
        foreach ([1, 42, 12345, 7297329173622235136] as $id) {
            $hash = HashidsHelper::encode($id);
            $this->assertNotSame((string) $id, $hash);
            $this->assertSame((string) $id, HashidsHelper::decode($hash));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function distinct_ids_produce_distinct_hashes(): void
    {
        $this->assertNotSame(HashidsHelper::encode(1), HashidsHelper::encode(2));
        $this->assertNotSame(HashidsHelper::encode(100), HashidsHelper::encode(101));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function decode_garbage_returns_null(): void
    {
        $this->assertNull(HashidsHelper::decode(''));
        $this->assertNull(HashidsHelper::decode('@@@not-a-hash@@@'));
        $this->assertNull(HashidsHelper::decode('abc'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function decodedId_resolves_hashid_and_passes_through_plain(): void
    {
        $controller = new class extends \app\controller\BaseApiController {
            public function pub(string $id): string
            {
                return $this->decodedId($id);
            }
        };
        $this->assertSame('12345', $controller->pub(HashidsHelper::encode(12345)));
        $this->assertSame('7297329173622235136', $controller->pub('7297329173622235136'));
    }
}
