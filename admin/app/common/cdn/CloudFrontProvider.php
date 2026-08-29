<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common\cdn;

use app\common\Cdn;
use app\common\CdnException;
use app\common\CdnProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * AWS CloudFront 适配器：自实现最小 SigV4 创建 invalidation（不引 AWS SDK）
 * 文档：https://docs.aws.amazon.com/cloudfront/latest/APIReference/API_CreateInvalidation.html
 * 能力：purge（invalidation）/ preload 与 purgeByTag 不支持
 */
class CloudFrontProvider implements CdnProviderInterface
{
    private array $config;
    private Client $http;
    private const SERVICE = 'cloudfront';
    private const HOST = 'cloudfront.amazonaws.com';

    public function __construct(array $config, ?Client $http = null)
    {
        $this->config = $config;
        $this->http = $http ?? new Client(['timeout' => 8, 'allow_redirects' => false, 'verify' => true]);
        $this->assertCredentials();
    }

    private function assertCredentials(): void
    {
        if (
            (string) ($this->config['distribution_id'] ?? '') === ''
            || (string) ($this->config['key_id'] ?? '') === ''
            || (string) ($this->config['secret_key'] ?? '') === ''
        ) {
            throw new CdnException('CloudFront 凭据缺失：请配置 .env 中 CLOUDFRONT_DISTRIBUTION_ID、AWS_ACCESS_KEY_ID、AWS_SECRET_ACCESS_KEY');
        }
    }

    public function purge(array $urls): void
    {
        $paths = $this->toCloudFrontPaths(Cdn::normalizeUrls($urls));
        if ($paths === []) {
            return;
        }
        $body = json_encode([
            'InvalidationBatch' => [
                'Paths' => [
                    'Quantity' => count($paths),
                    'Items' => $paths,
                ],
                'CallerReference' => time() . '-' . bin2hex(random_bytes(4)),
            ],
        ], JSON_UNESCAPED_SLASHES);

        $uri = '/2020-05-31/distribution/' . $this->config['distribution_id'] . '/invalidation';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $headers = [
            'host' => self::HOST,
            'x-amz-date' => $amzDate,
        ];
        $signature = $this->signV4('POST', $uri, '', $headers, 'host;x-amz-date', hash('sha256', $body), $dateStamp, $amzDate);
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->config['key_id'] . '/' . $dateStamp . '/' . $this->region() . '/' . self::SERVICE . '/aws4_request, '
            . 'SignedHeaders=host;x-amz-date, Signature=' . $signature;

        try {
            $resp = $this->http->post('https://' . self::HOST . $uri, [
                'headers' => [
                    'Authorization' => $authorization,
                    'X-Amz-Date' => $amzDate,
                    'Content-Type' => 'application/json',
                ],
                'body' => $body,
            ]);
            if ($resp->getStatusCode() !== 201) {
                throw new CdnException('CloudFront createInvalidation 失败: HTTP ' . $resp->getStatusCode() . ' ' . (string) $resp->getBody());
            }
        } catch (GuzzleException $e) {
            throw new CdnException('CloudFront createInvalidation 请求异常: ' . $e->getMessage());
        }
    }

    public function purgeByTag(string $tag): void
    {
        throw new \LogicException(__CLASS__ . ' 不支持按标签失效');
    }

    public function preload(array $urls): void
    {
        throw new \LogicException(__CLASS__ . ' 不支持预热');
    }

    private function region(): string
    {
        return (string) ($this->config['region'] ?? 'us-east-1');
    }

    /** CDN 绝对 URL → CloudFront 的 / 开头路径（去掉 scheme/host） */
    private function toCloudFrontPaths(array $urls): array
    {
        $paths = [];
        foreach ($urls as $url) {
            $path = (string) parse_url($url, PHP_URL_PATH);
            if ($path !== '') {
                $paths[] = $path;
            }
        }
        return array_values(array_unique($paths));
    }

    /**
     * SigV4 签名（纯函数，供单测以 AWS 官方文档示例向量断言）
     * @param array<string,string> $headers 待签名头（小写键）
     */
    private function signV4(
        string $method,
        string $uri,
        string $query,
        array $headers,
        string $signedHeaders,
        string $payloadHash,
        string $dateStamp,
        string $amzDate
    ): string {
        $canonical = $this->canonicalRequest($method, $uri, $query, $headers, $signedHeaders, $payloadHash);
        $toSign = $this->stringToSign($amzDate, $dateStamp, $this->region(), self::SERVICE, $canonical);
        return hash_hmac('sha256', $toSign, $this->signingKey((string) ($this->config['secret_key'] ?? ''), $dateStamp, $this->region(), self::SERVICE));
    }

    private function canonicalRequest(string $method, string $uri, string $query, array $headers, string $signedHeaders, string $payloadHash): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ':' . trim((string) $value);
        }
        return $method . "\n" . $uri . "\n" . $query . "\n" . implode("\n", $lines) . "\n\n" . $signedHeaders . "\n" . $payloadHash;
    }

    private function stringToSign(string $amzDate, string $dateStamp, string $region, string $service, string $canonicalRequest): string
    {
        return "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $dateStamp . '/' . $region . '/' . $service . "/aws4_request\n" . hash('sha256', $canonicalRequest);
    }

    private function signingKey(string $secret, string $dateStamp, string $region, string $service): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
