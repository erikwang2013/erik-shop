<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class PointRules extends BaseModel
{    use Encryptable;
    protected $table = "shop_point_rules";
    public $timestamps = false;   // 表仅 created_at 列（DB 默认），无 updated_at
    protected $encryptable = [];

}
