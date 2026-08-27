<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Banners extends BaseModel
{
    protected $connection = 'mysql_rw';   // 读写分离：读走 read 副本（sticky 写后读主库）
    use SoftDeletes;
    protected $table = "shop_banners";
}
