<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class RiskRules extends BaseModel
{    use Encryptable;
    protected $table = "shop_risk_rules";
    protected $encryptable = [];

}
