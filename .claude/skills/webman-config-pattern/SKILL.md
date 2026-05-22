---
name: webman-config-pattern
description: Use when creating or modifying webman configuration files in the shop-php project — requires Copyright header, Chinese comments for every key, snake_case key names, and no leading backslash on global functions
---

# Webman 配置规范

## Overview

所有 webman 配置文件遵循统一规范：Copyright 头部、每个配置项带中文注释、snake_case 键名。

## When to Use

- 创建新的 config/*.php 文件
- 修改现有配置文件
- 添加新的配置项

## Core Pattern

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 配置说明（用一句话概括这个配置文件的作用）
 */

return [
    // 基础连接配置
    'host' => '127.0.0.1',          // 服务器地址
    'port' => 3306,                  // 端口号

    // 连接池配置（可选）
    'pool' => [
        'max_connections' => 5,      // 最大连接数
        'min_connections' => 1,      // 最小连接数
        'wait_timeout' => 3,         // 等待超时（秒）
    ],
];
```

## Server 端必需配置文件

| 文件 | 说明 |
|------|------|
| `config/database.php` | MySQL 连接，表前缀 `erik_`，连接池配置 |
| `config/redis.php` | Redis 缓存/session 连接 |
| `config/jwt.php` | JWT secret, access_ttl (7200), refresh_ttl (1209600) |
| `config/snowflake.php` | worker_id (server=1, admin=2), datacenter_id=1 |
| `config/hashids.php` | salt 随机字符串, min_length=8 |
| `config/encryption.php` | AES-256-CBC cipher, 32字节 key |
| `config/poster.php` | 验证类型（滑块/拼图/点击），过期时间，重试次数 |
| `config/scout.php` | ES 连接，索引前缀 `erik_shop_` |

## Quality Checklist

- [ ] Copyright 头部完整（2026 erik <erik@erik.xyz>）
- [ ] 文件顶部有配置用途说明注释
- [ ] 每个配置项有行内中文注释
- [ ] 配置键名使用 snake_case
- [ ] 无全局函数前导反斜杠
- [ ] 敏感值使用 env() 读取，不硬编码
