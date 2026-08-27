<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsCompanies extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "shop_logistics_companies";
    protected $encryptable = ["api_key"]; // name 为公开物流商名，加密后查重/查询失效

}
