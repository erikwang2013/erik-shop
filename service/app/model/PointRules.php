<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class PointRules extends BaseModel
{    use Encryptable;
    protected $table = "erik_point_rules";
    protected $encryptable = [];

}
