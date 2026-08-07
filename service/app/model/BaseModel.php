<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use app\common\Snowflake;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $dateFormat = 'Y-m-d H:i:s';
    // 所有模型均显式构造写入字段（无 request 全量直传），放宽批量赋值默认限制
    protected $guarded = [];
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';

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
