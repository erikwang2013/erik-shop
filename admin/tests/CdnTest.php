<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace tests;

use app\common\Cdn;
use app\common\CdnException;
use app\common\cdn\AliyunProvider;
use app\common\cdn\CloudflareProvider;
use app\common\cdn\CloudFrontProvider;
use app\common\cdn\TencentProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * admin 侧 CDN 抽象层单元测试（Guzzle MockHandler 注入，无网络）
 *
 * 配置经反射修改（与 service 测试同法），用例后恢复；
 * SigV4 用 AWS 官方文档示例向量做已知答案断言。
 */
class CdnTest extends TestCase
{
    private mixed $origCdnConfig;
    private array $captured = [];

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
        \Webman\Config::load(dirname(__DIR__) . '/config', ['route', 'container']);
        $this->origCdnConfig = config('cdn');
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

    /** 开启 CDN（enabled + domain + 各厂商凭据），默认测试配置 */
    private function enableCdn(string $domain = 'cdn.erik.xyz'): void
    {
        $this->setCdnConfig([
            'enabled' => true,
            'default' => 'cloudflare',
            'domain' => $domain,
            'providers' => [
                'cloudflare' => ['api_token' => 'tok456', 'zone_id' => 'zone123'],
                'cloudfront' => [
                    'key_id' => 'AKIDEXAMPLE',
                    'secret_key' => 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY',
                    'distribution_id' => 'D12345',
                    'region' => 'us-east-1',
                ],
                'aliyun' => ['access_key_id' => 'ak123', 'access_key_secret' => 'sk123'],
                'tencent' => ['secret_id' => 'sid123', 'secret_key' => 'skey123', 'region' => 'ap-guangzhou'],
            ],
        ]);
    }

    /** 带请求捕获的 Mock 客户端：$this->captured 收集 [request, response] */
    private function mockClient(array $responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->captured));
        return new Client(['handler' => $stack]);
    }

    private function lastRequest(): \Psr\Http\Message\RequestInterface
    {
        $this->assertNotEmpty($this->captured, '应发起过 HTTP 请求');
        return end($this->captured)['request'];
    }

    private function lastRequestBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true) ?: [];
    }

    private function lastQuery(): string
    {
        return (string) $this->lastRequest()->getUri()->getQuery();
    }

    private function callPrivate(object $obj, string $method, array $args): mixed
    {
        $m = new \ReflectionMethod($obj, $method);
        return $m->invokeArgs($obj, $args);
    }

    // ===== 1. url() 三态 =====

    #[Test]
    public function url_disabled_returns_original(): void
    {
        // M2：即使配置了域名，总开关关闭也必须原样返回
        $this->setCdnConfig(['enabled' => false, 'domain' => 'cdn.erik.xyz']);
        $this->assertSame('/app/admin/upload/img/a.jpg', Cdn::url('/app/admin/upload/img/a.jpg'));
    }

    #[Test]
    public function url_prefixes_domain(): void
    {
        $this->enableCdn();
        $this->assertSame('https://cdn.erik.xyz/app/admin/upload/img/a.jpg', Cdn::url('/app/admin/upload/img/a.jpg'));
        $this->assertSame('https://cdn.erik.xyz/a.png', Cdn::url('a.png'));
    }

    #[Test]
    public function url_keeps_absolute_and_empty(): void
    {
        $this->enableCdn();
        $this->assertSame('https://origin.example.com/a.png', Cdn::url('https://origin.example.com/a.png'));
        $this->assertSame('http://cdn.erik.xyz/a.png', Cdn::url('http://cdn.erik.xyz/a.png'));
        $this->assertSame('', Cdn::url(''));
    }

    // ===== 2. Cloudflare purge / purgeByTag =====

    #[Test]
    public function cloudflare_purge_request_shape(): void
    {
        $this->enableCdn();
        $cdn = new CloudflareProvider(config('cdn.providers.cloudflare'), $this->mockClient([
            new Response(200, [], json_encode(['success' => true])),
        ]));
        $cdn->purge(['/app/admin/upload/img/a.jpg']);

        $req = $this->lastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/client/v4/zones/zone123/purge_cache', $req->getUri()->getPath());
        $this->assertSame('Bearer tok456', $req->getHeaderLine('Authorization'));
        $this->assertSame('https://cdn.erik.xyz/app/admin/upload/img/a.jpg', $this->lastRequestBody()['files'][0]);
    }

    #[Test]
    public function cloudflare_purge_by_tag_sends_tags(): void
    {
        $this->enableCdn();
        $cdn = new CloudflareProvider(config('cdn.providers.cloudflare'), $this->mockClient([
            new Response(200, [], json_encode(['success' => true])),
        ]));
        $cdn->purgeByTag('product:123');
        $this->assertSame(['product:123'], $this->lastRequestBody()['tags']);
    }

    // ===== 3. CloudFront body + SigV4 已知答案 =====

    #[Test]
    public function cloudfront_purge_request_shape(): void
    {
        $this->enableCdn();
        $cdn = new CloudFrontProvider(config('cdn.providers.cloudfront'), $this->mockClient([
            new Response(201, [], ''),
        ]));
        $cdn->purge(['/app/admin/upload/img/a.jpg']);

        $req = $this->lastRequest();
        $this->assertSame('/2020-05-31/distribution/D12345/invalidation', $req->getUri()->getPath());
        $this->assertStringStartsWith('AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/', $req->getHeaderLine('Authorization'));
        $body = $this->lastRequestBody();
        $this->assertSame(1, $body['InvalidationBatch']['Paths']['Quantity']);
        $this->assertSame('/app/admin/upload/img/a.jpg', $body['InvalidationBatch']['Paths']['Items'][0]);
        $this->assertMatchesRegularExpression('/^\d+-[0-9a-f]{8}$/', (string) $body['InvalidationBatch']['CallerReference']);
    }

    #[Test]
    public function sigv4_matches_aws_official_test_vector(): void
    {
        $this->enableCdn();
        $cdn = new CloudFrontProvider(config('cdn.providers.cloudfront'));
        $secret = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded; charset=utf-8',
            'host' => 'iam.amazonaws.com',
            'x-amz-date' => '20150830T123600Z',
        ];
        $canonical = $this->callPrivate($cdn, 'canonicalRequest', [
            'GET', '/', 'Action=ListUsers&Version=2010-05-08',
            $headers, 'content-type;host;x-amz-date',
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        ]);
        // AWS 官方文档给出的 canonical request（IAM GET 示例向量）
        $this->assertSame(
            "GET\n/\nAction=ListUsers&Version=2010-05-08\n"
            . "content-type:application/x-www-form-urlencoded; charset=utf-8\nhost:iam.amazonaws.com\nx-amz-date:20150830T123600Z\n\n"
            . "content-type;host;x-amz-date\n"
            . 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $canonical
        );
        $toSign = $this->callPrivate($cdn, 'stringToSign', ['20150830T123600Z', '20150830', 'us-east-1', 'iam', $canonical]);
        $this->assertSame(
            "AWS4-HMAC-SHA256\n20150830T123600Z\n20150830/us-east-1/iam/aws4_request\nf536975d06c0309214f805bb90ccff089219ecd68b2577efef23edd43b7e1a59",
            $toSign
        );
        $signature = hash_hmac('sha256', $toSign, $this->callPrivate($cdn, 'signingKey', [$secret, '20150830', 'us-east-1', 'iam']));
        $this->assertSame('5d672d79c15b13162d9279b0855cfba6789a8edb4c82c400e06b5924a6f2b5d7', $signature, '最终签名应与 AWS 官方示例一致');
    }

    // ===== 4. 阿里云 query + preload =====

    #[Test]
    public function aliyun_purge_query_shape(): void
    {
        $this->enableCdn();
        $cdn = new AliyunProvider(config('cdn.providers.aliyun'), $this->mockClient([
            new Response(200, [], json_encode(['RefreshTaskId' => 't1'])),
        ]));
        $cdn->purge(['/app/admin/upload/img/a.jpg']);

        $query = $this->lastQuery();
        $this->assertStringContainsString('Action=RefreshObjectCaches', $query);
        $this->assertStringContainsString('ObjectType=File', $query);
        $this->assertStringContainsString('AccessKeyId=ak123', $query);
        $this->assertStringContainsString('Signature=', $query);
        $this->assertStringContainsString(rawurlencode('https://cdn.erik.xyz/app/admin/upload/img/a.jpg'), $query);
    }

    #[Test]
    public function aliyun_preload_uses_push_object_cache(): void
    {
        $this->enableCdn();
        $cdn = new AliyunProvider(config('cdn.providers.aliyun'), $this->mockClient([
            new Response(200, [], json_encode(['PushTaskId' => 't2'])),
        ]));
        $cdn->preload(['/a.png']);
        $this->assertStringContainsString('Action=PushObjectCache', $this->lastQuery());
    }

    // ===== 5. 腾讯云 TC3 =====

    #[Test]
    public function tencent_purge_request_shape(): void
    {
        $this->enableCdn();
        $cdn = new TencentProvider(config('cdn.providers.tencent'), $this->mockClient([
            new Response(200, [], json_encode(['Response' => ['RequestId' => 'r1']])),
        ]));
        $cdn->purge(['/a.png']);

        $req = $this->lastRequest();
        $this->assertStringStartsWith('TC3-HMAC-SHA256 Credential=sid123/', $req->getHeaderLine('Authorization'));
        $body = $this->lastRequestBody();
        $this->assertSame('PurgeUrlsCache', $body['Action']);
        $this->assertSame('2018-06-06', $body['Version']);
        $this->assertSame(['https://cdn.erik.xyz/a.png'], $body['Urls']);
    }

    #[Test]
    public function tencent_preload_uses_push_urls_cache(): void
    {
        $this->enableCdn();
        $cdn = new TencentProvider(config('cdn.providers.tencent'), $this->mockClient([
            new Response(200, [], json_encode(['Response' => ['RequestId' => 'r2']])),
        ]));
        $cdn->preload(['/a.png']);
        $this->assertSame('PushUrlsCache', $this->lastRequestBody()['Action']);
    }

    // ===== 6. 能力矩阵：不支持者抛 LogicException =====

    #[Test]
    public function capability_matrix_throws_logic_exception(): void
    {
        $this->enableCdn();
        $cf = new CloudflareProvider(config('cdn.providers.cloudflare'));
        $cfx = new CloudFrontProvider(config('cdn.providers.cloudfront'));
        $ali = new AliyunProvider(config('cdn.providers.aliyun'));
        $tc = new TencentProvider(config('cdn.providers.tencent'));

        foreach ([$cf, $cfx] as $p) {
            try {
                $p->preload(['/a.png']);
                $this->fail('preload 不支持者应抛 LogicException: ' . $p::class);
            } catch (\LogicException) {
            }
        }
        foreach ([$cfx, $ali, $tc] as $p) {
            try {
                $p->purgeByTag('tag');
                $this->fail('purgeByTag 不支持者应抛 LogicException: ' . $p::class);
            } catch (\LogicException) {
            }
        }
        $this->assertTrue(true);
    }

    // ===== 7. make()：未知提供商 / 缺凭据 =====

    #[Test]
    public function make_unknown_provider_throws(): void
    {
        $this->enableCdn();
        $this->expectException(CdnException::class);
        Cdn::make('fastly');
    }

    #[Test]
    public function make_missing_credentials_throws_with_env_names(): void
    {
        $this->setCdnConfig([
            'enabled' => true,
            'default' => 'cloudflare',
            'domain' => 'cdn.erik.xyz',
            'providers' => [
                'cloudflare' => ['api_token' => '', 'zone_id' => ''],
            ],
        ]);
        $this->expectException(CdnException::class);
        $this->expectExceptionMessageMatches('/CF_API_TOKEN/');
        Cdn::make('cloudflare');
    }

    // ===== 8. fail-open：门面不抛异常 =====

    #[Test]
    public function facade_purge_is_fail_open(): void
    {
        // 缺凭据提供商 → make() 抛 CdnException，但门面应捕获并记日志，不向上抛
        $this->setCdnConfig([
            'enabled' => true,
            'default' => 'aliyun',
            'domain' => 'cdn.erik.xyz',
            'providers' => [
                'aliyun' => ['access_key_id' => '', 'access_key_secret' => ''],
            ],
        ]);
        Cdn::purge(['/a.png']); // 不应抛异常
        $this->assertTrue(true);
    }

    // ===== 9. SSRF 白名单：外域 URL 丢弃 =====

    #[Test]
    public function normalize_urls_drops_foreign_hosts(): void
    {
        $this->enableCdn();
        $result = Cdn::normalizeUrls([
            '/app/admin/upload/a.jpg',
            'https://cdn.erik.xyz/app/admin/upload/b.jpg',
            'https://evil.example.com/x.png',
            'ftp://cdn.erik.xyz/y.png',
            '',
        ]);
        $this->assertSame([
            'https://cdn.erik.xyz/app/admin/upload/a.jpg',
            'https://cdn.erik.xyz/app/admin/upload/b.jpg',
        ], $result, '相对路径补全、本站绝对 URL 保留、外域与空值丢弃');
    }

    // ===== 10. 阿里云错误路径：HTTP 200 + 任何 JSON Code 字段都判失败（M3） =====

    #[Test]
    public function aliyun_rejects_any_error_code(): void
    {
        $this->enableCdn();
        $cdn = new AliyunProvider(config('cdn.providers.aliyun'), $this->mockClient([
            new Response(200, [], '{"Code":"InvalidObjectPath","Message":"bad path"}'),
        ]));
        $this->expectException(CdnException::class);
        $this->expectExceptionMessageMatches('/InvalidObjectPath/');
        $cdn->purge(['/a.jpg']);
    }
}
