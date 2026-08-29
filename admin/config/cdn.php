<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * CDN 配置
 * 支持 Cloudflare / AWS CloudFront / 阿里云 CDN / 腾讯云 CDN（Fastly/Akamai 预留）
 * 凭据一律来自 .env，禁止硬编码；缺凭据时 Cdn::make() 抛 CdnException 并指明 env 变量名
 */

return [
    // 总开关：false 时 Cdn::url() 原样返回、purge/preload 为空操作（行为零变化）
    'enabled' => (bool) filter_var(getenv('CDN_ENABLED'), FILTER_VALIDATE_BOOL),

    // 默认提供商：cloudflare / cloudfront / aliyun / tencent
    'default' => getenv('CDN_DEFAULT_PROVIDER') ?: 'cloudflare',

    // 回源 CDN 域名（不含 scheme），URL 重写目标；留空则关闭重写
    'domain' => getenv('CDN_DOMAIN') ?: '',

    'providers' => [
        'cloudflare' => [
            'api_token' => getenv('CF_API_TOKEN') ?: '',
            'zone_id'   => getenv('CF_ZONE_ID') ?: '',
        ],
        'cloudfront' => [
            'key_id'          => getenv('AWS_ACCESS_KEY_ID') ?: '',
            'secret_key'      => getenv('AWS_SECRET_ACCESS_KEY') ?: '',
            'distribution_id' => getenv('CLOUDFRONT_DISTRIBUTION_ID') ?: '',
            'region'          => getenv('AWS_REGION') ?: 'us-east-1',
        ],
        'aliyun' => [
            'access_key_id'     => getenv('ALIYUN_ACCESS_KEY_ID') ?: '',
            'access_key_secret' => getenv('ALIYUN_ACCESS_KEY_SECRET') ?: '',
        ],
        'tencent' => [
            'secret_id'  => getenv('TENCENT_SECRET_ID') ?: '',
            'secret_key' => getenv('TENCENT_SECRET_KEY') ?: '',
        ],
    ],
];
