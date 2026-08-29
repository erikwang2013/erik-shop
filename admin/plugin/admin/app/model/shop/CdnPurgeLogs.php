<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\model\shop;

use plugin\admin\app\model\Base;

/**
 * CDN 刷新日志（纯写/查询模型）
 */
class CdnPurgeLogs extends Base
{
    protected $table = 'wa_cdn_purge_logs';

    /** Cdn::purge/preload 经 create() 整行写入日志，允许批量赋值 */
    protected $guarded = [];

    public $incrementing = true;

    protected $keyType = 'int';

    /** 表无 updated_at 列：关掉 Eloquent 默认双时间戳，created_at 由 creating 事件补 */
    public $timestamps = false;

    /**
     * 跳过 Base 的雪花 ID 自动生成（本表为 bigint 自增主键），保留 Eloquent trait boot
     * 注：必须用 static::bootTraits()（转发调用），显式 Model::boot() 会把 static:: 绑回基类
     */
    protected static function boot(): void
    {
        static::bootTraits();
        static::creating(function (self $model) {
            if (!$model->created_at) {
                $model->created_at = date('Y-m-d H:i:s');
            }
        });
    }
}
