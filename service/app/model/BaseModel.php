<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use app\common\Snowflake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 所有模型的基类
 *
 * 提供：snowflake ID自动生成、软删除、erik_表前缀 、加密支持
 */
class BaseModel extends Model
{
    use SoftDeletes;

    // 禁用自增ID（snowflake生成）
    public $incrementing = false;

    // 主键类型：bigint超出PHP int范围，必须用string
    protected $keyType = 'string';

    // 日期格式
    protected $dateFormat = 'Y-m-d H:i:s';

    // 时间戳字段名
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';

    // 隐藏字段（JSON序列化时排除）
    protected $hidden = [];

    /**
     * Boot：自动生成snowflake ID
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (!$model->getKey()) {
                $model->setAttribute($model->getKeyName(), Snowflake::nextId());
            }
        });
    }
}
