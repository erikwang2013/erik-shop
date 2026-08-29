<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\Cdn;
use app\common\cdn\CloudflareProvider;
use app\common\CdnException;
use Maize\Encryptable\Encryption;
use plugin\admin\app\controller\shop\CdnProviderController;
use plugin\admin\app\model\shop\CdnProviders;
use PHPUnit\Framework\TestCase;

/**
 * CDN 管理端配置解析单测（无网络；DB 不可用时 DB 相关用例自动跳过，env 兜底回归不依赖 DB）
 */
class CdnProviderConfigTest extends TestCase
{
    private ?array $origCdnConfig = null;

    protected function setUp(): void
    {
        // 加载容器配置，否则 support\Container 为空导致 webman/database Initializer 报错、DB 用例永远跳过
        \Webman\Config::load(dirname(__DIR__) . '/config', ['route']);
        // 注册 SecureEncrypter resolver（admin/support/bootstrap.php 在生产运行注册；单测环境手动注册）
        try {
            Encryption::setResolver(function (string $abstract) {
                $config = new \Maize\Encryptable\Config\EnvEncryptableConfig();
                return match ($abstract) {
                    \Maize\Encryptable\PHPEncrypter::class => new \app\common\SecureEncrypter($config),
                    \Maize\Encryptable\DBEncrypter::class => new \Maize\Encryptable\DBEncrypter($config),
                    default => throw new \RuntimeException("Unknown encryptable resolver class: {$abstract}"),
                };
            });
        } catch (\Throwable $e) {
            // .env 未加载导致密钥缺失时忽略（DB 用例会因连接失败自动跳过）
        }
        $this->origCdnConfig = $this->cdnConfig();
    }

    protected function tearDown(): void
    {
        $this->setCdnConfig($this->origCdnConfig);
    }

    /** 读取当前 cdn 配置（webman Config 无 get/set API，反射读内存） */
    private function cdnConfig(): ?array
    {
        $ref = new \ReflectionClass(\Webman\Config::class);
        $prop = $ref->getProperty('config');
        return ($prop->getValue()['cdn'] ?? null);
    }

    /** 运行时替换 cdn 配置（webman Config 无 set API，反射改内存并清 flatCache） */
    private function setCdnConfig(?array $value): void
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

    /** DB 可用性探针（表不存在/连接失败 → DB 用例跳过） */
    private function dbAvailable(): bool
    {
        try {
            CdnProviders::where('code', '__probe__')->first();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** ① 无 DB 行 → providerConfig/make() 走 env（回归：基础版行为不变） */
    public function test_make_falls_back_to_env_when_no_db_row(): void
    {
        $this->setCdnConfig([
            'enabled' => true,
            'domain' => 'cdn.erik.xyz',
            'default' => 'cloudflare',
            'providers' => [
                'cloudflare' => ['api_token' => 'env-token', 'zone_id' => 'env-zone'],
            ],
        ]);
        $config = Cdn::providerConfig('cloudflare');
        $this->assertIsArray($config);
        $this->assertSame('env-token', $config['api_token']);
        $this->assertSame('env-zone', $config['zone_id']);
        $provider = Cdn::make('cloudflare');
        $this->assertInstanceOf(CloudflareProvider::class, $provider);
    }

    /** ② DB 行覆盖 env 同名字段（DB 可用时） */
    public function test_db_row_overrides_env(): void
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('MySQL 不可用');
        }
        $this->setCdnConfig([
            'enabled' => true,
            'domain' => 'cdn.erik.xyz',
            'default' => 'cloudflare',
            'providers' => ['cloudflare' => ['api_token' => 'env-token', 'zone_id' => 'env-zone']],
        ]);
        CdnProviders::updateOrCreate(['code' => 'cloudflare'], [
            'name' => 'Cloudflare',
            'enabled' => 1,
            'config' => json_encode(['api_token' => 'db-token'], JSON_UNESCAPED_SLASHES),
            'weight' => 0,
        ]);
        try {
            $config = Cdn::providerConfig('cloudflare');
            $this->assertSame('db-token', $config['api_token']); // DB 覆盖 env
            $this->assertSame('env-zone', $config['zone_id']);   // env 兜底缺失字段
            // 加密落库 round-trip：原始存储值为密文，不等于明文 JSON 且不含明文片段
            $row = CdnProviders::where('code', 'cloudflare')->first(['config']);
            $raw = (string) $row->getRawOriginal('config');
            $this->assertNotSame('{"api_token":"db-token"}', $raw);
            $this->assertStringNotContainsString('db-token', $raw);
        } finally {
            CdnProviders::where('code', 'cloudflare')->delete();
        }
    }

    /** ③ enabled=0 → providerConfig 返回 null 且 purge 静默跳过（DB 可用时） */
    public function test_disabled_provider_returns_null_and_purge_skips(): void
    {
        if (!$this->dbAvailable()) {
            $this->markTestSkipped('MySQL 不可用');
        }
        $this->setCdnConfig([
            'enabled' => true,
            'domain' => 'cdn.erik.xyz',
            'default' => 'cloudflare',
            'providers' => ['cloudflare' => ['api_token' => 'env-token', 'zone_id' => 'env-zone']],
        ]);
        CdnProviders::updateOrCreate(['code' => 'cloudflare'], [
            'name' => 'Cloudflare',
            'enabled' => 0,
            'config' => json_encode(['api_token' => 'db-token'], JSON_UNESCAPED_SLASHES),
            'weight' => 0,
        ]);
        try {
            $this->assertNull(Cdn::providerConfig('cloudflare'));
            // purge 静默跳过：不抛异常（fail-open）
            Cdn::purge(['/a.jpg', '/b.jpg']);
            $this->assertTrue(true);
        } finally {
            CdnProviders::where('code', 'cloudflare')->delete();
        }
    }

    /** ④ 凭据留空保存 → 不覆盖既有值 */
    public function test_merge_config_keeps_existing_when_submitted_empty(): void
    {
        $existing = ['api_token' => 'old-token', 'zone_id' => 'old-zone'];
        $merged = CdnProviderController::mergeConfig($existing, ['api_token' => '', 'zone_id' => 'new-zone'], 'cloudflare');
        $this->assertSame('old-token', $merged['api_token']); // 留空不覆盖
        $this->assertSame('new-zone', $merged['zone_id']);    // 非空覆盖
    }

    /** ⑤ save 的 code 白名单拒绝未知值 */
    public function test_save_code_whitelist_rejects_unknown(): void
    {
        $this->assertTrue(CdnProviderController::isSupported('cloudflare'));
        $this->assertTrue(CdnProviderController::isSupported('tencent'));
        $this->assertFalse(CdnProviderController::isSupported('akamai'));
        $this->assertFalse(CdnProviderController::isSupported(''));
    }
}
