<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace app\model;

use Erik\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;

class SizeCharts extends BaseModel
{    use Encryptable;
    use SoftDeletes;
    protected $table = "erik_size_charts";
    protected $encryptable = ["name"];

}
