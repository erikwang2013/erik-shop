---
name: snowflake-hashids-integration
description: Use when implementing ID generation, encryption, or search in shop-php models — covers snowflake bigint IDs (no auto-increment), hashids API encode/decode, encryptable DB columns, and Elasticsearch scout integration
---

# Snowflake + Hashids + Encryption + Scout 集成

## Overview

四层数据安全与搜索方案：
1. **Snowflake** — 分布式主键生成（bigint，非自增）
2. **Hashids** — 接口层 ID 加解密（隐藏真实 ID）
3. **Encryptable** — 数据库层敏感字段加密
4. **Scout** — Elasticsearch 全文搜索同步

## Data Flow

```
数据库存储:  snowflake bigint ID + 加密敏感字段
     ↓ 读取时 Encryptable trait 自动解密
控制器层:    原始 snowflake ID + 明文敏感数据
     ↓ 响应时 HashidsEncode 中间件编码
API 输出:    hashids 字符串 + 脱敏数据
```

## Snowflake ID 生成

```php
// app/common/Snowflake.php
use Erik\Snowflake\Snowflake as SnowflakeSDK;

class Snowflake
{
    private static ?SnowflakeSDK $instance = null;

    public static function init(int $workerId, int $datacenterId): void
    {
        self::$instance = new SnowflakeSDK($workerId, $datacenterId);
    }

    public static function nextId(): string
    {
        return (string) self::$instance->id();  // 强制转 string，防止 PHP int 溢出
    }
}
```

Worker 启动时在 `app/process/SnowflakeWorker.php` 初始化：
```php
class SnowflakeWorker
{
    public function onWorkerStart()
    {
        Snowflake::init(config('snowflake.worker_id'), config('snowflake.datacenter_id'));
    }
}
```

## Hashids 中间件自动编解码

### Decode（请求 → 控制器）
```php
// app/middleware/HashidsDecode.php
// 自动将 params 和 body 中 *_id 字段从 hashid 解码为 snowflake ID
// /api/v1/products/Ab3xK9pq → controller 收到 ['id' => '1234567890123456789']
```

### Encode（控制器 → 响应）
```php
// app/middleware/HashidsEncode.php
// 自动将响应 JSON 中的数字 ID 编码为 hashids
// controller return {'id': '1234567890123456789'} → 客户端收到 {'id': 'Ab3xK9pq'}
```

**控制器完全不感知 hashids**，始终操作原始 snowflake ID。

## Encryptable 数据库加密

```php
use Erik\Encryptable\Encryptable;

class User extends BaseModel
{
    use Encryptable;

    // 这些字段写入时自动加密，读取时自动解密
    protected $encryptable = [
        'email',       // 邮箱
        'mobile',      // 手机号
        'real_name',   // 真实姓名
    ];

    // 查询加密字段需使用 trait 提供的方法
    // User::whereEncrypted('email', 'test@example.com')->first();
}
```

## Scout Elasticsearch 同步

```php
use Erik\Scout\Searchable;

class Product extends BaseModel
{
    use Searchable;

    // 同步到 ES 的字段
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => strip_tags($this->description),
            'category_name' => $this->category->name ?? '',
            'brand' => $this->brand,
            'min_price' => (float) $this->min_price,
            'max_price' => (float) $this->max_price,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }

    // 索引名称
    public function searchableAs(): string
    {
        return 'erik_shop_products';
    }
}
```

## 完整模型示例

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Erik\Scout\Searchable;

class Product extends BaseModel
{
    use Encryptable;           // 敏感字段加解密
    use Searchable;            // ES 搜索同步

    protected $table = 'erik_products';

    protected $encryptable = [];  // 商品通常无敏感字段

    protected $casts = [
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'status' => 'integer',
        'is_hot' => 'boolean',
        'is_new' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function skus()
    {
        return $this->hasMany(ProductSku::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }
}
```

## Common Mistakes

- **snowflake ID 未转 string**：ID 长达 19 位，PHP int 会溢出，必须 `(string)` 转换
- **加密字段直接 WHERE**：WHERE email = 'xxx' 查到的是加密后值，需用 `whereEncrypted()`
- **Scout 未配置 ES 连接**：config/scout.php 中的 host 必须正确
- **中间件顺序**：HashidsDecode 必须最先执行（让后续中间件拿到真实 ID）
