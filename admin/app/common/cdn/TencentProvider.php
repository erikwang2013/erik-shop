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
 * 腾讯云 CDN 适配器：TC3-HMAC-SHA256 签名直调 PurgeUrlsCache / PushUrlsCache
 * 文档：https://cloud.tencent.com/document/api/228/37870
 * 能力：purge（PurgeUrlsCache）/ preload（PushUrlsCache）/ purgeByTag 不支持
 */
class TencentProvider implements CdnProviderInterface
{
    private array $config;
    private Client $http;
    private const SERVICE = 'cdn';
    private const HOST = 'cdn.tencentcloudapi.com';
    private const VERSION = '2018-06-06';

    public function __construct(array $config, ?Client $http = null)
    {
        $this->config = $config;
        $this->http = $http ?? new Client(['timeout' => 8, 'allow_redirects' => false, 'verify' => true]);
        $this->assertCredentials();
    }

    private function assertCredentials(): void
    {
        if ((string) ($this->config['secret_id'] ?? '') === '' || (string) ($this->config['secret_key'] ?? '') === '') {
            throw new CdnException('腾讯云 CDN 凭据缺失：请配置 .env 中 TENCENT_SECRET_ID 与 TENCENT_SECRET_KEY');
        }
    }

    public function purge(array $urls): void
    {
        $this->call('PurgeUrlsCache', Cdn::normalizeUrls($urls));
    }

    public function preload(array $urls): void
    {
        $this->call('PushUrlsCache', Cdn::normalizeUrls($urls));
    }

    public function purgeByTag(string $tag): void
    {
        throw new \LogicException(__CLASS__ . ' 不支持按标签失效');
    }

    private function call(string $action, array $urls): void
    {
        if ($urls === []) {
            return;
        }
        $secretId = (string) $this->config['secret_id'];
        $secretKey = (string) $this->config['secret_key'];

        $body = json_encode(['Action' => $action, 'Version' => self::VERSION, 'Urls' => array_values($urls)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);
        $headers = [
            'content-type' => 'application/json; charset=utf-8',
            'host' => self::HOST,
            'x-tc-action' => strtolower($action),
        ];
        $authorization = $this->tc3Authorization($secretId, $secretKey, $timestamp, $date, $body, $headers, 'content-type;host;x-tc-action');

        try {
            $resp = $this->http->post('https://' . self::HOST . '/', [
                'headers' => [
                    'Authorization' => $authorization,
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Host' => self::HOST,
                    'X-TC-Action' => strtolower($action),
                    'X-TC-Timestamp' => (string) $timestamp,
                    'X-TC-Version' => self::VERSION,
                    'X-TC-Region' => (string) ($this->config['region'] ?? 'ap-guangzhou'),
                ],
                'body' => $body,
            ]);
            $data = json_decode((string) $resp->getBody(), true);
            if ($resp->getStatusCode() !== 200 || isset($data['Response']['Error'])) {
                throw new CdnException("腾讯云 CDN {$action} 失败: HTTP " . $resp->getStatusCode() . ' ' . (string) $resp->getBody());
            }
        } catch (GuzzleException $e) {
            throw new CdnException("腾讯云 CDN {$action} 请求异常: " . $e->getMessage());
        }
    }

    /**
     * TC3-HMAC-SHA256 签名（纯函数，供单测断言）
     * @param array<string,string> $headers 待签名头（小写键，升序拼接）
     */
    private function tc3Authorization(
        string $secretId,
        string $secretKey,
        int $timestamp,
        string $date,
        string $payload,
        array $headers,
        string $signedHeaders
    ): string {
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim((string) $value) . "\n";
        }
        $canonicalRequest = "POST\n/\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . hash('sha256', $payload);

        $credentialScope = $date . '/' . self::SERVICE . '/tc3_request';
        $stringToSign = "TC3-HMAC-SHA256\n" . $timestamp . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);

        $secretDate = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', self::SERVICE, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

        return 'TC3-HMAC-SHA256 Credential=' . $secretId . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
    }
}
