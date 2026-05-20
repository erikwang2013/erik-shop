<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;

class SizeCharts extends BaseModel
{    use Encryptable;
    protected $table = "erik_size_charts";
    protected $encryptable = ["name"];

}
