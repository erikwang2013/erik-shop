<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * CDN 配置（service 侧子集：仅 URL 输出重写，无提供商凭据——purge 由 admin 侧触发）
 * 完整配置见 admin/config/cdn.php
 */

return [
    // 总开关：false 时 Cdn::url() 原样返回（filter_var 保证 'false' 字符串解析为 false）
    'enabled' => (bool) filter_var(getenv('CDN_ENABLED'), FILTER_VALIDATE_BOOL),

    // 回源 CDN 域名（不含 scheme）；留空则 Cdn::url() 原样返回
    'domain' => getenv('CDN_DOMAIN') ?: '',
];
