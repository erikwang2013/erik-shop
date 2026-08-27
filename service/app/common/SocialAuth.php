<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\common;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\JWK;
use GuzzleHttp\Client as HttpClient;

/**
 * 社交登录 id_token 验证（fail-closed）
 * Google: tokeninfo 端点；Apple: 官方公钥 JWT 验签；Facebook: debug_token 端点
 */
class SocialAuth
{
    private static ?HttpClient $http = null;

    private static function http(): HttpClient
    {
        if (self::$http === null) {
            self::$http = new HttpClient(['timeout' => 10]);
        }
        return self::$http;
    }

    /**
     * 熔断保护的 GET + JSON 解析
     * 仅网络层异常（连接失败/超时/5xx）计入熔断；业务校验（token 无效等）在 call() 之外抛出，不计入
     * 4xx（如 Google tokeninfo 对无效 token 返回 400）视为业务失败返回空数组，
     * 由外层业务校验抛「token 无效」——否则攻击者用无效 token 即可刷开熔断器打挂整个社交登录
     */
    private static function httpGet(string $name, string $url, array $opts = []): array
    {
        return CircuitBreaker::call($name, function () use ($url, $opts) {
            try {
                $body = (string) self::http()->get($url, $opts)->getBody();
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                if ($e->getResponse() && $e->getResponse()->getStatusCode() < 500) {
                    return []; // 4xx：请求本身无效（无效 token 等），业务失败不计数
                }
                throw $e; // 5xx/网络错误：交给熔断器计数
            }
            return json_decode($body, true) ?: [];
        });
    }

    /**
     * 验证 id_token，返回 ['sub' => 平台用户ID, 'email' => 邮箱]
     * 验证失败抛异常，调用方应返回 401
     */
    public static function verify(string $provider, string $idToken, string $clientEmail = ''): array
    {
        return match ($provider) {
            'google' => self::verifyGoogle($provider, $idToken, $clientEmail),
            'apple' => self::verifyApple($provider, $idToken, $clientEmail),
            'facebook' => self::verifyFacebook($provider, $idToken, $clientEmail),
            default => throw new \InvalidArgumentException("不支持的社交平台: {$provider}"),
        };
    }

    private static function verifyGoogle(string $provider, string $idToken, string $clientEmail): array
    {
        $clientId = config('social.google.client_id', '');
        $info = self::httpGet("social:{$provider}", 'https://oauth2.googleapis.com/tokeninfo', ['query' => ['id_token' => $idToken]]);
        if (empty($info['sub'])) {
            throw new \RuntimeException('Google id_token 无效');
        }
        if ($clientId !== '' && !empty($info['aud']) && $info['aud'] !== $clientId) {
            throw new \RuntimeException('Google id_token 受众不匹配');
        }
        if ($clientEmail !== '' && !empty($info['email']) && strcasecmp((string)$info['email'], $clientEmail) !== 0) {
            throw new \RuntimeException('Google id_token 邮箱与提交邮箱不一致');
        }
        return ['sub' => (string)$info['sub'], 'email' => $info['email'] ?? ''];
    }

    private static function verifyApple(string $provider, string $idToken, string $clientEmail = ''): array
    {
        $clientId = config('social.apple.client_id', '');
        $keys = self::httpGet("social:{$provider}", 'https://appleid.apple.com/auth/keys');
        if (empty($keys['keys'])) {
            throw new \RuntimeException('Apple 公钥获取失败');
        }
        try {
            $decoded = FirebaseJwt::decode($idToken, JWK::parseKeySet($keys));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Apple id_token 无效: ' . $e->getMessage());
        }
        $arr = (array)$decoded;
        if (($arr['iss'] ?? '') !== 'https://appleid.apple.com') {
            throw new \RuntimeException('Apple id_token 签发者不匹配');
        }
        if ($clientId !== '' && ($arr['aud'] ?? '') !== $clientId) {
            throw new \RuntimeException('Apple id_token 受众不匹配');
        }
        if (empty($arr['sub'])) {
            throw new \RuntimeException('Apple id_token 缺少 sub');
        }
        $email = $arr['email'] ?? '';
        if ($clientEmail !== '' && $email !== '' && strcasecmp($email, $clientEmail) !== 0) {
            throw new \RuntimeException('Apple id_token 邮箱与提交邮箱不一致');
        }
        return ['sub' => (string)$arr['sub'], 'email' => $email];
    }

    private static function verifyFacebook(string $provider, string $idToken, string $clientEmail = ''): array
    {
        $appId = config('social.facebook.app_id', '');
        $appSecret = config('social.facebook.app_secret', '');
        if ($appId === '' || $appSecret === '') {
            throw new \RuntimeException('Facebook 登录未配置（缺少 FACEBOOK_APP_ID/FACEBOOK_APP_SECRET）');
        }
        $data = self::httpGet("social:{$provider}", 'https://graph.facebook.com/debug_token', ['query' => [
            'input_token' => $idToken,
            'access_token' => $appId . '|' . $appSecret,
        ]]);
        if (empty($data['data']['is_valid'])) {
            throw new \RuntimeException('Facebook token 无效');
        }
        if (empty($data['data']['user_id'])) {
            throw new \RuntimeException('Facebook token 缺少 user_id');
        }
        $email = $data['data']['email'] ?? '';
        if ($clientEmail !== '' && $email !== '' && strcasecmp($email, $clientEmail) !== 0) {
            throw new \RuntimeException('Facebook token 邮箱与提交邮箱不一致');
        }
        return ['sub' => (string)$data['data']['user_id'], 'email' => $email];
    }
}
