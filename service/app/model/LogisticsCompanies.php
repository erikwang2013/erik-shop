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
    protected $table = "erik_logistics_companies";
    protected $encryptable = ["name", "api_key"];

}
