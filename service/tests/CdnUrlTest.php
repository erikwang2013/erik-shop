<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\Cdn;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * service 侧 Cdn::url() 重写逻辑单元测试（纯内存，无网络）
 * 语义与 admin 侧一致：未配置域名/空值/已是完整 URL → 原样返回。
 */
class CdnUrlTest extends TestCase
{
    private mixed $origCdnConfig;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        // 加载容器配置，否则 DB/Redis 解析路径因 support\Container 为空无法初始化
        \Webman\Config::load(dirname(__DIR__) . '/config', ['route']);
        $this->origCdnConfig = config('cdn');
        // 清除 Redis 缓存的 cdn_settings，保证各用例走 env/DB 路径互不干扰
        try {
            redis()->del('cdn_settings');
        } catch (\Throwable $e) {
            // Redis 不可用：domain() 会自动回退 env
        }
    }

    protected function tearDown(): void
    {
        $this->setCdnConfig($this->origCdnConfig);
    }

    /** 运行时替换 cdn 配置（webman Config 无 set API，反射改内存并清 flatCache） */
    private function setCdnConfig(mixed $value): void
    {
        $ref = new \ReflectionClass(\Webman\Config::class);
        $prop = $ref->getProperty('config');
        $all = $prop->getValue() ?: [];
        if ($value === null) {
            unset($all['cdn']);
        } else {
            $all['cdn'] = $value;
        }
        $prop->setValue(null, $all);
        $flat = $ref->getProperty('flatCache');
        $flat->setValue(null, []);
    }

    #[Test]
    public function url_returns_original_when_disabled(): void
    {
        // M2：即使配置了域名，总开关关闭也必须原样返回
        $this->setCdnConfig(['enabled' => false, 'domain' => 'cdn.erik.xyz']);
        $this->assertSame('/app/admin/upload/img/a.jpg', Cdn::url('/app/admin/upload/img/a.jpg'));
    }

    #[Test]
    public function url_prefixes_domain_when_configured(): void
    {
        $this->setCdnConfig(['enabled' => true, 'domain' => 'cdn.erik.xyz']);
        $this->assertSame('https://cdn.erik.xyz/app/admin/upload/img/a.jpg', Cdn::url('/app/admin/upload/img/a.jpg'));
        $this->assertSame('https://cdn.erik.xyz/app/admin/upload/img/a.jpg', Cdn::url('app/admin/upload/img/a.jpg'), '去掉多余前导斜杠');
    }

    #[Test]
    public function url_keeps_absolute_and_empty_untouched(): void
    {
        $this->setCdnConfig(['enabled' => true, 'domain' => 'cdn.erik.xyz']);
        $this->assertSame('https://origin.example.com/a.png', Cdn::url('https://origin.example.com/a.png'), '完整 URL 原样');
        $this->assertSame('http://cdn.erik.xyz/a.png', Cdn::url('http://cdn.erik.xyz/a.png'), 'http 完整 URL 原样');
        $this->assertSame('', Cdn::url(''), '空值原样');
    }

    /** DB 层：env 未配置域名时读 wa_options.cdn_settings（DB 不可用则跳过） */
    #[Test]
    public function url_uses_db_domain_when_env_unset(): void
    {
        $this->setCdnConfig(['enabled' => true, 'domain' => '']);
        try {
            \support\Db::table('wa_options')->where('name', 'cdn_settings')->delete();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用');
        }
        try {
            \support\Db::table('wa_options')->updateOrInsert(
                ['name' => 'cdn_settings'],
                ['value' => json_encode(['domain' => 'db.cdn.erik.xyz'])]
            );
            try {
                redis()->del('cdn_settings'); // 清 Redis 缓存，确保走 DB 路径
            } catch (\Throwable $e) {
                // Redis 不可用不影响 DB 层断言
            }
            $this->assertSame('https://db.cdn.erik.xyz/a.jpg', Cdn::url('/a.jpg'));

            // M2：管理端总开关关闭（enabled=false 且带 domain）→ 原样返回
            \support\Db::table('wa_options')->where('name', 'cdn_settings')
                ->update(['value' => json_encode(['enabled' => false, 'domain' => 'db.cdn.erik.xyz'])]);
            try {
                redis()->del('cdn_settings'); // 60s TTL 缓存会污染，必须先清
            } catch (\Throwable $e) {
                // Redis 不可用不影响 DB 层断言
            }
            $this->assertSame('/a.jpg', Cdn::url('/a.jpg'));
        } finally {
            \support\Db::table('wa_options')->where('name', 'cdn_settings')->delete();
        }
    }

    /** Redis 层：缓存命中优先于 DB/env（Redis 不可用则跳过） */
    #[Test]
    public function url_prefers_redis_domain(): void
    {
        $this->setCdnConfig(['enabled' => true, 'domain' => 'env.cdn.erik.xyz']);
        try {
            redis()->setex('cdn_settings', 60, json_encode(['domain' => 'redis.cdn.erik.xyz']));
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis 不可用');
        }
        try {
            $this->assertSame('https://redis.cdn.erik.xyz/a.jpg', Cdn::url('/a.jpg'));
        } finally {
            try {
                redis()->del('cdn_settings');
            } catch (\Throwable $e) {
                // 忽略清理失败
            }
        }
    }
}
