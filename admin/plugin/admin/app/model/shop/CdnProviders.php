<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace plugin\admin\app\model\shop;

use Maize\Encryptable\Encryptable;
use plugin\admin\app\model\Base;

/**
 * CDN 提供商配置（config 整体加密存储：凭据+域名等）
 */
class CdnProviders extends Base
{
    protected $table = 'wa_cdn_providers';

    /** 控制器 save() 经 updateOrCreate 整行写入，允许批量赋值 */
    protected $guarded = [];

    public $incrementing = true;

    protected $keyType = 'int';

    protected $casts = [
        'config' => Encryptable::class,
    ];

    /**
     * 跳过 Base 的雪花 ID 自动生成（本表为 int 自增主键），保留 Eloquent trait boot
     * 注：必须用 static::bootTraits()（转发调用），显式 Model::boot() 会把 static:: 绑回基类
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }
}
