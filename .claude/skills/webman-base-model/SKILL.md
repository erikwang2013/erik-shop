---
name: webman-base-model
description: Use when creating Eloquent model classes in shop-php server/ — requires extending BaseModel for snowflake ID generation (bigint, no auto-increment), Encryptable trait for sensitive columns, and erik_ table prefix
---

# Webman BaseModel 模式

## Overview

所有模型继承 `app\model\BaseModel`，自动获得 snowflake ID 生成、敏感字段加解密、erik_ 表前缀。

## When to Use

- 创建新的 Eloquent Model 类
- 需要 snowflake ID 的数据库表
- 需要对敏感字段（邮箱、手机、地址）加密存储

## Core Pattern

### BaseModel 基类

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use app\common\Snowflake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseModel extends Model
{
    use SoftDeletes;

    public $incrementing = false;        // 禁用自增
    protected $keyType = 'string';       // 主键类型（bigint 太大，PHP用string）

    protected $dateFormat = 'Y-m-d H:i:s';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->getKey()) {
                $model->setAttribute($model->getKeyName(), Snowflake::nextId());
            }
        });
    }
}
```

### 业务模型示例

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class User extends BaseModel
{
    protected $table = 'erik_users';      // erik_ 表前缀

    use Encryptable;

    protected $encryptable = [             // 敏感字段加密
        'email',
        'mobile',
    ];

    protected $hidden = [                  // JSON 序列化时隐藏
        'password',
        'salt',
    ];

    protected $casts = [
        'money' => 'decimal:2',
        'score' => 'integer',
        'status' => 'integer',
    ];
}
```

## 关键约定

| 约定 | 说明 |
|------|------|
| `$incrementing = false` | BaseModel 已设置，子类无需重复 |
| `$keyType = 'string'` | snowflake ID 超出 PHP int 范围，用 string |
| 表名 | 必须使用 `erik_` 前缀，显式声明 `$table` |
| 时间戳 | BaseModel 已定义 `created_at/updated_at/deleted_at` |
| 敏感字段 | 使用 `Encryptable` trait + `$encryptable` 数组 |
| 关联关系 | 使用完整的 `app\model\RelatedModel::class` |

## 关联关系（不使用前导反斜杠）

```php
public function addresses()
{
    return $this->hasMany(UserAddress::class, 'user_id');
}

public function orders()
{
    return $this->hasMany(Order::class, 'user_id');
}
```

## Common Mistakes

- **忘记 $table**：默认表名是类名复数小写，必须显式指定 `erik_` 前缀
- **int 溢出**：snowflake ID 是 64 位，远超 PHP int，必须 `$keyType = 'string'`
- **重复生成 ID**：BaseModel 的 boot 已处理，子类不要再覆盖 creating
- **encryptable 字段查询**：加密字段无法直接 WHERE，需用 Encryptable 提供的查询方法
