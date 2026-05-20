<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\model;

use DateTimeInterface;
use Erikwang2013\Snowflake\Snowflake;
use support\Model;

class Base extends Model
{
    protected $connection = 'plugin.admin.mysql';
    public $incrementing = false;
    protected $keyType = 'string';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (!$model->getKey()) {
                $workerId = (int) getenv('SNOWFLAKE_WORKER_ID') ?: 2;
                $datacenterId = (int) getenv('SNOWFLAKE_DATACENTER_ID') ?: 1;
                $startTimestamp = (int) getenv('SNOWFLAKE_START_TIMESTAMP') ?: 1700000000000;
                $snowflake = new Snowflake($workerId, $datacenterId, $startTimestamp);
                $model->setAttribute($model->getKeyName(), (string) $snowflake->id());
            }
        });
    }
}
