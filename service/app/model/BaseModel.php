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
    // 封锁敏感列批量赋值；status/user_id/password 等列在注册/下单等流程中经 create() 批量赋值，
    // 纳入 guarded 会被 Eloquent 静默丢弃导致数据损坏，故仅封锁未参与批量赋值的敏感列
    protected $guarded = ['id', 'money', 'score', 'level', 'created_at', 'updated_at'];
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
