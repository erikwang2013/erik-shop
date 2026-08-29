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
 * Cloudflare CDN 适配器（无官方 PHP SDK，轻量 HTTP 直调 purge_cache API）
 * 文档：https://developers.cloudflare.com/api/operations/zone-purge-files-by-cache-tag
 * 能力：purge（files）/ purgeByTag（tags）/ preload 不支持
 */
class CloudflareProvider implements CdnProviderInterface
{
    private array $config;
    private Client $http;

    public function __construct(array $config, ?Client $http = null)
    {
        $this->config = $config;
        $this->http = $http ?? new Client(['timeout' => 8, 'allow_redirects' => false, 'verify' => true]);
        $this->assertCredentials();
    }

    private function assertCredentials(): void
    {
        if ((string) ($this->config['api_token'] ?? '') === '' || (string) ($this->config['zone_id'] ?? '') === '') {
            throw new CdnException('Cloudflare 凭据缺失：请配置 .env 中 CF_API_TOKEN 与 CF_ZONE_ID');
        }
    }

    public function purge(array $urls): void
    {
        $this->request(['files' => Cdn::normalizeUrls($urls)]);
    }

    public function purgeByTag(string $tag): void
    {
        $this->request(['tags' => [$tag]]);
    }

    public function preload(array $urls): void
    {
        throw new \LogicException(__CLASS__ . ' 不支持预热');
    }

    private function request(array $body): void
    {
        $token = (string) $this->config['api_token'];
        $zoneId = (string) $this->config['zone_id'];
        if (($body['files'] ?? []) === [] && ($body['tags'] ?? []) === []) {
            return; // 归一化后无有效 URL（外域全被丢弃）
        }
        try {
            $resp = $this->http->post('https://api.cloudflare.com/client/v4/zones/' . $zoneId . '/purge_cache', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
            $data = json_decode((string) $resp->getBody(), true);
            if ($resp->getStatusCode() !== 200 || ($data['success'] ?? false) !== true) {
                throw new CdnException('Cloudflare purge_cache 失败: ' . json_encode($data ?: (string) $resp->getBody(), JSON_UNESCAPED_UNICODE));
            }
        } catch (GuzzleException $e) {
            throw new CdnException('Cloudflare purge_cache 请求异常: ' . $e->getMessage());
        }
    }
}
