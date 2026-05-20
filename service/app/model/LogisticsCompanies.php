<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class LogisticsCompanies extends BaseModel
{    use Encryptable;
    protected $table = "erik_logistics_companies";
    protected $encryptable = ["name", "api_key"];

}
