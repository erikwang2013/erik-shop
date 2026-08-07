<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 社交登录配置
 * 用于验证 Google/Apple/Facebook 返回的 id_token（未配置时对应平台登录会被拒绝）
 */

return [
    'google' => [
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    ],
    'apple' => [
        'client_id' => getenv('APPLE_CLIENT_ID') ?: '',
    ],
    'facebook' => [
        'app_id' => getenv('FACEBOOK_APP_ID') ?: '',
        'app_secret' => getenv('FACEBOOK_APP_SECRET') ?: '',
    ],
];
