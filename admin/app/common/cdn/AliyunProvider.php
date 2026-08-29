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
 * 阿里云 CDN 适配器：RPC 风格签名（HMAC-SHA1）直调 RefreshObjectCaches / PushObjectCache
 * 文档：https://help.aliyun.com/zh/cdn/developer-reference/api-cdn-2018-05-10-refreshobjectcaches
 * 能力：purge（RefreshObjectCaches）/ preload（PushObjectCache）/ purgeByTag 不支持
 */
class AliyunProvider implements CdnProviderInterface
{
    private array $config;
    private Client $http;
    private const ENDPOINT = 'https://cdn.aliyuncs.com/';
    private const VERSION = '2018-05-10';

    public function __construct(array $config, ?Client $http = null)
    {
        $this->config = $config;
        $this->http = $http ?? new Client(['timeout' => 8, 'allow_redirects' => false, 'verify' => true]);
        $this->assertCredentials();
    }

    private function assertCredentials(): void
    {
        if ((string) ($this->config['access_key_id'] ?? '') === '' || (string) ($this->config['access_key_secret'] ?? '') === '') {
            throw new CdnException('阿里云 CDN 凭据缺失：请配置 .env 中 ALIYUN_ACCESS_KEY_ID 与 ALIYUN_ACCESS_KEY_SECRET');
        }
    }

    public function purge(array $urls): void
    {
        $this->call('RefreshObjectCaches', Cdn::normalizeUrls($urls));
    }

    public function preload(array $urls): void
    {
        $this->call('PushObjectCache', Cdn::normalizeUrls($urls));
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
        $keyId = (string) $this->config['access_key_id'];
        $keySecret = (string) $this->config['access_key_secret'];

        $params = [
            'AccessKeyId' => $keyId,
            'Action' => $action,
            'Format' => 'JSON',
            'ObjectPath' => implode(',', $urls),
            'ObjectType' => 'File',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => bin2hex(random_bytes(8)),
            'SignatureVersion' => '1.0',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => self::VERSION,
        ];
        $params['Signature'] = $this->rpcSignature('GET', $params, $keySecret);

        try {
            $resp = $this->http->get(self::ENDPOINT, ['query' => $params]);
            $body = (string) $resp->getBody();
            // 阿里云 RPC 错误为 HTTP 200 + JSON Code 字段（InvalidObjectPath/Forbidden.AccessKey 等），
            // 任何 Code 字段都判失败，不再只认 OperationDenied
            $data = json_decode($body, true);
            if ($resp->getStatusCode() !== 200 || (is_array($data) && isset($data['Code'])) || str_starts_with($body, '<?xml')) {
                throw new CdnException("阿里云 CDN {$action} 失败: HTTP " . $resp->getStatusCode() . ' ' . $body);
            }
        } catch (GuzzleException $e) {
            throw new CdnException("阿里云 CDN {$action} 请求异常: " . $e->getMessage());
        }
    }

    /**
     * RPC 签名（纯函数）：参数按键升序排序 → RFC3986 编码拼接 → HMAC-SHA1 → Base64
     * 密钥 = AccessKeySecret + '&'
     */
    private function rpcSignature(string $method, array $params, string $keySecret): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $name => $value) {
            $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }
        $stringToSign = $method . '&%2F&' . rawurlencode(implode('&', $pairs));
        return base64_encode(hash_hmac('sha1', $stringToSign, $keySecret . '&', true));
    }
}
