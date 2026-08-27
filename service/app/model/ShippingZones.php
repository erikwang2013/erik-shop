<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingZones extends BaseModel
{    use Encryptable;
    use SoftDeletes;
        protected $connection = 'mysql_rw';   // 读写分离：读走 read 副本（sticky 写后读主库）
protected $table = "shop_shipping_zones";
    protected $encryptable = []; // 分区名为运营数据，加密后按 name 查重/查询失效

}
